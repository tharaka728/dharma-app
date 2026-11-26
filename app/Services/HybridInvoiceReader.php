<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Support\InventoryException;
use App\Services\PromotionCalculator as M;

/** Gate-independent scoped historical reader. Never recalculates from current prices/offers. */
final class HybridInvoiceReader
{
    public function __construct(private PDO $pdo) {}
    private function rows(string $sql,array $params): array { $q=$this->pdo->prepare($sql); $q->execute($params); return $q->fetchAll(PDO::FETCH_ASSOC); }
    private static function equalRows(array $actual,array $expected): void
    {
        if (count($actual)!==count($expected)) { throw new \LogicException('Hybrid physical child count mismatch.'); }
        foreach ($expected as $i=>$values) {
            foreach ($values as $key=>$value) {
                if ($key==='snapshot_json' && isset($actual[$i][$key])) {
                    if (HybridInvoiceRequest::json(json_decode($actual[$i][$key],true,512,JSON_THROW_ON_ERROR))!==$value) { throw new \LogicException('Hybrid stored JSON projection mismatch.'); }
                    continue;
                }
                if (!array_key_exists($key,$actual[$i]) || ($value===null ? $actual[$i][$key]!==null : (string)$actual[$i][$key] !== (string)$value)) { throw new \LogicException('Hybrid stored projection mismatch: '.$key); }
            }
        }
    }
    public function get(array $identity,string $invoice): array
    {
        $own=!$this->pdo->inTransaction();
        if ($own) { $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $this->pdo->beginTransaction(); }
        try { return $this->read($identity,$invoice); }
        finally { if ($own && $this->pdo->inTransaction()) { $this->pdo->rollBack(); } }
    }
    private function read(array $identity,string $invoice): array
    {
        $a=(new Invoices($this->pdo))->actor($identity);
        if (!preg_match('/^[0-9a-f-]{36}$/D',$invoice)) { throw new InventoryException(['invoice'=>'Invalid invoice identity.']); }
        $scope=$a['role']==='ADMIN'?'':' AND i.rep_id=? AND c.assigned_rep_id=?'; $params=[$invoice]; if ($scope!=='') { array_push($params,$a['id'],$a['id']); }
        $official=$this->rows('SELECT i.*,d.source_location_id,d.issued_by_user_id,d.review_hash FROM invoices i JOIN online_invoice_details d ON d.invoice_id=i.id JOIN customers c ON c.id=i.customer_id WHERE i.id=?'.$scope,$params)[0]??null;
        if (!$official) { throw new InventoryException(['invoice'=>'Hybrid invoice is not accessible.'],404); }
        $header=$this->rows('SELECT * FROM hybrid_invoice_snapshots WHERE invoice_id=?',[$invoice])[0]??null;
        $seal=$this->rows('SELECT * FROM hybrid_invoice_seals WHERE invoice_id=?',[$invoice])[0]??null;
        if (!$header || !$seal) { throw new InventoryException(['hybrid'=>'Hybrid invoice is unsealed or incomplete.'],409); }
        $snapshot=json_decode($header['snapshot_json'],true,512,JSON_THROW_ON_ERROR); HybridInvoiceSnapshots::reconcile($snapshot);
        $r=$snapshot['review']; $c=$r['calculation']; $proof=$snapshot['seal'];
        self::equalRows([$seal],[['digest'=>$proof['digest'],'line_count'=>$proof['counts']['lines'],'segment_count'=>$proof['counts']['segments'],'run_count'=>$proof['counts']['runs'],'allocation_version'=>$proof['allocation_version']]]);
        self::equalRows([$header],[['invoice_id'=>$invoice,'policy_version'=>$c['policy_version'],'currency'=>'LKR','configuration_version'=>$r['configuration_version'],'rounding_version'=>$c['rounding_version'],
            'allocation_version'=>$r['unit_allocation_version'],'actor_user_id'=>$r['actor']['id'],'customer_id'=>$r['customer']['id'],'source_location_id'=>$r['source_location_id'],'business_date'=>$r['business_date'],
            'original_subtotal'=>$c['original_paid_subtotal'],'product_discount'=>$c['product_discount_total'],'replacement_discount'=>$c['replacement_discount_total'],'invoice_discount_base'=>$c['invoice_discount_base'],
            'invoice_percent'=>$c['invoice_discount_percent'],'invoice_discount'=>$c['invoice_discount_amount'],'combined_discount'=>$c['combined_monetary_discount'],'payable'=>$c['final_payable_total'],'review_hash'=>$r['review_hash']]]);
        self::equalRows([$official],[HybridInvoiceSnapshots::legacyMapping($snapshot)+['id'=>$invoice,'customer_id'=>$r['customer']['id'],'rep_id'=>$r['number_owner'],'source_location_id'=>$r['source_location_id'],
            'issued_by_user_id'=>$r['actor']['id'],'business_date'=>$r['business_date'],'payment_type'=>$r['payment_type'],'currency'=>'LKR','review_hash'=>$r['review_hash'],'posting_channel'=>'ONLINE']]);
        $lines=[]; $items=[]; $segments=[]; $runs=[];
        foreach ($c['lines'] as $line) {
            $lines[]=['line_number'=>$line['line_number'],'product_id'=>$line['product_id'],'price_version'=>$line['price_version'],'promotion_revision_id'=>$line['promotion_revision_id'],
                'paid_quantity'=>$line['paid_quantity'],'qualifying_quantity'=>$line['qualifying_paid_quantity'],'remainder_quantity'=>$line['discount_eligible_quantity'],'free_entitlement'=>$line['free_entitlement'],
                'actual_free_quantity'=>$line['actual_free_quantity'],'substituted_quantity'=>$line['substituted_free_quantity'],'product_percent'=>$line['product_discount_percent'],
                'replacement_percent'=>$line['replacement_discount_percent'],'snapshot_json'=>HybridInvoiceRequest::json($line)]+self::amounts($line);
            $items[]=['line_number'=>$line['line_number'],'product_id'=>$line['product_id'],'quantity'=>$line['paid_quantity'],'free_quantity'=>$line['actual_free_quantity'],'unit_price'=>$line['unit_price'],
                'gross_amount'=>$line['gross_amount'],'automatic_discount_amount'=>'0.00','manual_discount_amount'=>$line['discount_amount'],'discount_amount'=>$line['discount_amount'],'line_total'=>$line['payable_amount'],
                'price_version'=>$line['price_version'],'promotion_version_id'=>$line['promotion_revision_id']];
            foreach ($line['segments'] as $i=>$segment) { $segments[]=['line_number'=>$line['line_number'],'segment_number'=>$i+1,'kind'=>$segment['kind'],'return_priority'=>$segment['kind']==='DISCOUNT_ELIGIBLE'?0:1,'paid_quantity'=>$segment['paid_quantity']]+self::amounts($segment); }
        }
        foreach ($snapshot['allocations']['segments'] as $segment) {
            foreach ($segment['runs'] as $run) {
                $amounts=[]; foreach (['gross_amount'=>'gross','product_discount_amount'=>'product_discount_amount','replacement_discount_amount'=>'replacement_discount_amount','invoice_discount_amount'=>'invoice_discount_amount','payable_amount'=>'net'] as $key=>$component) { $amounts[$key]=M::money($run['cents'][$component]); }
                $runs[]=['line_number'=>$segment['line_number'],'segment_number'=>$segment['segment_number'],'position_start'=>$run['position_start'],'position_count'=>$run['position_count']]+$amounts;
            }
        }
        self::equalRows($this->rows('SELECT * FROM hybrid_invoice_line_snapshots WHERE invoice_id=? ORDER BY line_number',[$invoice]),$lines);
        self::equalRows($this->rows('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY line_number',[$invoice]),$items);
        self::equalRows($this->rows('SELECT * FROM hybrid_invoice_segments WHERE invoice_id=? ORDER BY line_number,segment_number',[$invoice]),$segments);
        self::equalRows($this->rows('SELECT * FROM hybrid_unit_allocation_runs WHERE invoice_id=? ORDER BY line_number,segment_number,position_start',[$invoice]),$runs);
        $balances=(new HybridInvoiceBalances($this->pdo))->read($invoice,$snapshot);
        $receipts=$this->rows('SELECT r.request_id,r.received_amount,r.applied_amount,r.change_amount,r.created_at_utc,u.name received_by FROM invoice_receipts r JOIN users u ON u.id=r.actor_user_id WHERE r.invoice_id=? ORDER BY r.created_at_utc,r.request_id',[$invoice]);
        return ['snapshot'=>$snapshot,'receipts'=>$receipts,'policy_version'=>HybridPromotionPolicy::POLICY,'financial'=>$balances['financial'],'adjustments'=>$balances];
    }
    private static function amounts(array $line): array { return array_intersect_key($line,array_flip(['gross_amount','product_discount_amount','replacement_discount_amount','invoice_discount_amount','payable_amount'])); }
}
