<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

/** Seal-last persistence within a verified isolated owning invoice transaction. */
final class PdoHybridInvoiceSnapshots
{
    public function __construct(private PDO $pdo,private ?\Closure $afterInsert=null,private ?array $databaseScope=null) {}
    private function insert(string $table, array $values): void
    {
        $q = $this->pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')');
        $q->execute(array_values($values));
        if ($this->afterInsert) { ($this->afterInsert)($table); }
    }
    public function insertSealed(string $invoice, array $snapshot): void
    {
        if (!$this->pdo->inTransaction()) { throw new \LogicException('Snapshot insertion requires the owning invoice transaction.'); }
        HybridDatabaseScope::verify($this->pdo,$this->databaseScope);
        HybridInvoiceSnapshots::requireCurrent($snapshot);
        if (!isset($snapshot['seal'])) { throw new \LogicException('A verified seal is required.'); }
        $r = $snapshot['review']; $c = $r['calculation'];
        if ($r['request_id'] !== $invoice) { throw new \LogicException('Invoice/command identity mismatch.'); }
        $q = $this->pdo->prepare('SELECT i.*,d.source_location_id,d.issued_by_user_id,d.review_hash FROM invoices i JOIN online_invoice_details d ON d.invoice_id=i.id WHERE i.id=? FOR UPDATE');
        $q->execute([$invoice]); $official = $q->fetch(PDO::FETCH_ASSOC);
        if (!$official || $official['posting_channel'] !== 'ONLINE' || (string)$official['customer_id'] !== (string)$r['customer']['id']
            || (string)$official['issued_by_user_id'] !== $r['actor']['id'] || (string)$official['source_location_id'] !== $r['source_location_id'] || $official['review_hash'] !== $r['review_hash']) { throw new \LogicException('Official invoice authority mapping mismatch.'); }
        foreach (HybridInvoiceSnapshots::legacyMapping($snapshot) as $key => $value) { if ($official[$key] !== $value) { throw new \LogicException('Official invoice money mapping mismatch.'); } }
        $q = $this->pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY line_number FOR UPDATE'); $q->execute([$invoice]); $items = $q->fetchAll(PDO::FETCH_ASSOC);
        if (count($items) !== count($c['lines'])) { throw new \LogicException('Official invoice child count mismatch.'); }
        foreach ($c['lines'] as $i => $line) {
            $mapping = ['line_number' => (string)$line['line_number'], 'product_id' => $line['product_id'], 'quantity' => $line['paid_quantity'], 'free_quantity' => $line['actual_free_quantity'],
                'unit_price' => $line['unit_price'], 'gross_amount' => $line['gross_amount'], 'automatic_discount_amount' => '0.00',
                'manual_discount_amount' => $line['discount_amount'], 'discount_amount' => $line['discount_amount'], 'line_total' => $line['payable_amount'], 'price_version' => $line['price_version']];
            foreach ($mapping as $key => $value) { if ((string)$items[$i][$key] !== $value) { throw new \LogicException('Official invoice line mapping mismatch: '.$key); } }
            if (($items[$i]['promotion_version_id'] === null ? null : (string)$items[$i]['promotion_version_id']) !== $line['promotion_revision_id']) { throw new \LogicException('Official promotion identity mismatch.'); }
        }
        $this->insert('hybrid_invoice_snapshots', ['invoice_id' => $invoice, 'policy_version' => $c['policy_version'], 'currency' => 'LKR',
            'configuration_version' => $r['configuration_version'], 'rounding_version' => $c['rounding_version'], 'allocation_version' => $r['unit_allocation_version'],
            'actor_user_id' => $r['actor']['id'], 'customer_id' => $r['customer']['id'], 'source_location_id' => $r['source_location_id'], 'business_date' => $r['business_date'],
            'original_subtotal' => $c['original_paid_subtotal'], 'product_discount' => $c['product_discount_total'], 'replacement_discount' => $c['replacement_discount_total'],
            'invoice_discount_base' => $c['invoice_discount_base'], 'invoice_percent' => $c['invoice_discount_percent'], 'invoice_discount' => $c['invoice_discount_amount'],
            'combined_discount' => $c['combined_monetary_discount'], 'payable' => $c['final_payable_total'], 'review_hash' => $r['review_hash'], 'snapshot_json' => HybridInvoiceRequest::json($snapshot)]);
        foreach ($c['lines'] as $line) {
            $this->insert('hybrid_invoice_line_snapshots', ['invoice_id' => $invoice, 'line_number' => $line['line_number'], 'product_id' => $line['product_id'],
                'price_version' => $line['price_version'], 'promotion_revision_id' => $line['promotion_revision_id'], 'paid_quantity' => $line['paid_quantity'],
                'qualifying_quantity' => $line['qualifying_paid_quantity'], 'remainder_quantity' => $line['discount_eligible_quantity'], 'free_entitlement' => $line['free_entitlement'],
                'actual_free_quantity' => $line['actual_free_quantity'], 'substituted_quantity' => $line['substituted_free_quantity'], 'product_percent' => $line['product_discount_percent'],
                'replacement_percent' => $line['replacement_discount_percent'], 'snapshot_json' => HybridInvoiceRequest::json($line)] + self::amounts($line));
            foreach ($line['segments'] as $i => $segment) {
                $this->insert('hybrid_invoice_segments', ['invoice_id' => $invoice, 'line_number' => $line['line_number'], 'segment_number' => $i+1,
                    'kind' => $segment['kind'], 'return_priority' => $segment['kind'] === 'DISCOUNT_ELIGIBLE' ? 0 : 1, 'paid_quantity' => $segment['paid_quantity']] + self::amounts($segment));
            }
        }
        foreach ($snapshot['allocations']['segments'] as $segment) {
            foreach ($segment['runs'] as $run) {
                $money = []; foreach (['gross_amount' => 'gross','product_discount_amount' => 'product_discount_amount','replacement_discount_amount' => 'replacement_discount_amount','invoice_discount_amount' => 'invoice_discount_amount','payable_amount' => 'net'] as $key => $component) { $money[$key] = PromotionCalculator::money($run['cents'][$component]); }
                $this->insert('hybrid_unit_allocation_runs', ['invoice_id' => $invoice, 'line_number' => $segment['line_number'], 'segment_number' => $segment['segment_number'],
                    'position_start' => $run['position_start'], 'position_count' => $run['position_count']] + $money);
            }
        }
        // Re-read physical rows before sealing. Catch count mismatches and accidental late writes.
        foreach (['lines' => 'hybrid_invoice_line_snapshots','segments' => 'hybrid_invoice_segments','runs' => 'hybrid_unit_allocation_runs'] as $key => $table) {
            $q = $this->pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE invoice_id=?'); $q->execute([$invoice]);
            if ((int)$q->fetchColumn() !== $snapshot['seal']['counts'][$key]) { throw new \LogicException('Hybrid physical child count mismatch.'); }
        }
        $seal = $snapshot['seal'];
        $this->insert('hybrid_invoice_seals', ['invoice_id' => $invoice, 'digest' => $seal['digest'], 'line_count' => $seal['counts']['lines'],
            'segment_count' => $seal['counts']['segments'], 'run_count' => $seal['counts']['runs'], 'allocation_version' => $seal['allocation_version']]);
    }
    private static function amounts(array $line): array
    {
        return array_intersect_key($line,array_flip(['gross_amount','product_discount_amount','replacement_discount_amount','invoice_discount_amount','payable_amount']));
    }
}
