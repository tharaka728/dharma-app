<?php
declare(strict_types=1);
namespace App\Services;

/** Complete immutable source representation. Seal is derived last, never supplied by the client. */
final class HybridInvoiceSnapshots
{
    public static function build(array $request, array $prepared): array
    {
        $snapshot = ['snapshot_version' => 'HYBRID_SNAPSHOT_V1', 'request' => $request['hybrid'],
            'review' => $prepared['review'], 'allocations' => $prepared['allocations']];
        self::reconcile($snapshot);
        $counts = ['lines' => count($snapshot['review']['calculation']['lines']), 'segments' => count($snapshot['allocations']['segments']), 'runs' => 0];
        foreach ($snapshot['allocations']['segments'] as $s) { $counts['runs'] += count($s['runs']); }
        $snapshot['seal'] = ['digest' => HybridInvoiceRequest::digest($snapshot), 'counts' => $counts, 'allocation_version' => HybridUnitAllocations::VERSION];
        return $snapshot;
    }
    public static function reconcile(array $snapshot): void
    {
        if (($snapshot['snapshot_version'] ?? null) !== 'HYBRID_SNAPSHOT_V1') { throw new \LogicException('Unsupported Hybrid snapshot.'); }
        $review = $snapshot['review']; $hash = $review['review_hash']; unset($review['review_hash']);
        if (!hash_equals($hash, HybridInvoiceRequest::digest($review))) { throw new \LogicException('Review fingerprint changed.'); }
        $calculation = $review['calculation']; $products = [];
        foreach ($calculation['lines'] as $line) {
            $products[] = ['product_id' => $line['product_id'], 'unit_price' => $line['unit_price'], 'price_version' => $line['price_version'],
                'available_quantity' => $line['required_stock'], 'promotion' => $line['promotion_snapshot']];
        }
        // Reconstruct from immutable originals, never from current catalogue/stock.
        $rebuilt = HybridPromotionPolicy::calculate($snapshot['request'], $products);
        if (HybridInvoiceRequest::json($rebuilt) !== HybridInvoiceRequest::json($calculation)) { throw new \LogicException('Snapshot component or quantity mismatch.'); }
        $allocations = HybridUnitAllocations::invoice($rebuilt);
        if (HybridInvoiceRequest::json($allocations) !== HybridInvoiceRequest::json($snapshot['allocations']) || HybridInvoiceRequest::digest($allocations) !== $review['unit_allocation_digest']) { throw new \LogicException('Original unit allocations changed.'); }
        if (isset($snapshot['seal'])) {
            $seal = $snapshot['seal']; unset($snapshot['seal']);
            $runs = 0; foreach ($allocations['segments'] as $s) { $runs += count($s['runs']); }
            if (!hash_equals($seal['digest'], HybridInvoiceRequest::digest($snapshot)) || $seal['allocation_version'] !== HybridUnitAllocations::VERSION
                || HybridInvoiceRequest::json($seal['counts']) !== HybridInvoiceRequest::json(['lines' => count($rebuilt['lines']), 'segments' => count($allocations['segments']), 'runs' => $runs])) { throw new \LogicException('Hybrid invoice seal does not reconcile.'); }
        }
    }
    public static function legacyMapping(array $snapshot): array
    {
        self::reconcile($snapshot); $c = $snapshot['review']['calculation'];
        return ['subtotal' => $c['original_paid_subtotal'], 'automatic_discount_total' => '0.00',
            'manual_discount_total' => $c['combined_monetary_discount'], 'discount_total' => $c['combined_monetary_discount'], 'grand_total' => $c['final_payable_total']];
    }
}
