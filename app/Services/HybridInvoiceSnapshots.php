<?php
declare(strict_types=1);
namespace App\Services;

/** Complete immutable source representation. Seal is derived last, never supplied by the client. */
final class HybridInvoiceSnapshots
{
    public const CURRENT_REVISION = 'HYBRID_INVOICE_ELIGIBILITY_V2';
    public const HISTORICAL = 'LEGACY_STACKING_COMPATIBLE';
    public const CURRENT = 'CURRENT_ELIGIBILITY_COMPATIBLE';
    public const EQUIVALENT = 'BOTH_COMPATIBLE';

    public static function build(array $request, array $prepared): array
    {
        $snapshot = ['snapshot_version' => 'HYBRID_SNAPSHOT_V1', 'request' => $request['hybrid'],
            'review' => $prepared['review'], 'allocations' => $prepared['allocations']];
        self::currentRevision($snapshot);
        self::validate($snapshot, false);
        $counts = ['lines' => count($snapshot['review']['calculation']['lines']), 'segments' => count($snapshot['allocations']['segments']), 'runs' => 0];
        foreach ($snapshot['allocations']['segments'] as $s) { $counts['runs'] += count($s['runs']); }
        $snapshot['seal'] = ['digest' => HybridInvoiceRequest::digest($snapshot), 'counts' => $counts, 'allocation_version' => HybridUnitAllocations::VERSION];
        return $snapshot;
    }
    public static function reconcile(array $snapshot): void
    {
        self::interpretation($snapshot);
    }

    /** Read-only mathematical compatibility, never an assertion of issuance date. */
    public static function interpretation(array $snapshot): string
    {
        return self::validate($snapshot, true);
    }

    /** New persistence must never use the historical/unmarked read path. */
    public static function requireCurrent(array $snapshot): void
    {
        self::currentRevision($snapshot);
        self::reconcile($snapshot);
    }

    private static function currentRevision(array $snapshot): void
    {
        if (($snapshot['review']['calculation_revision'] ?? null) !== self::CURRENT_REVISION) {
            throw new \LogicException('New Hybrid snapshots require the server current calculation revision.');
        }
    }

    private static function validate(array $snapshot, bool $sealed): string
    {
        if (($snapshot['snapshot_version'] ?? null) !== 'HYBRID_SNAPSHOT_V1'
            || array_diff(array_keys($snapshot), ['snapshot_version','request','review','allocations','seal'])
            || !is_array($snapshot['request'] ?? null) || !is_array($snapshot['review'] ?? null)
            || !is_array($snapshot['allocations'] ?? null)) { throw new \LogicException('Unsupported or incomplete Hybrid snapshot.'); }
        $review = $snapshot['review']; $calculation = $review['calculation'] ?? null;
        if (!is_array($calculation) || !is_array($calculation['lines'] ?? null)
            || $calculation['lines'] !== array_values($calculation['lines']) || count($calculation['lines']) < 1 || count($calculation['lines']) > 100
            || ($review['policy_version'] ?? null) !== HybridPromotionPolicy::POLICY
            || ($review['unit_allocation_version'] ?? null) !== HybridUnitAllocations::VERSION
            || ($snapshot['allocations']['version'] ?? null) !== HybridUnitAllocations::VERSION
            || !is_array($snapshot['allocations']['segments'] ?? null)) { throw new \LogicException('Unsupported Hybrid calculation/allocation structure.'); }
        $marked = array_key_exists('calculation_revision', $review);
        if ($marked && $review['calculation_revision'] !== self::CURRENT_REVISION) { throw new \LogicException('Unknown Hybrid calculation revision.'); }
        foreach (['review_hash','unit_allocation_digest'] as $field) {
            if (!is_string($review[$field] ?? null) || !preg_match('/^[0-9a-f]{64}$/D', $review[$field])) { throw new \LogicException('Missing Hybrid fingerprint.'); }
        }
        $hash = $review['review_hash']; unset($review['review_hash']);
        if (!hash_equals($hash, HybridInvoiceRequest::digest($review))) { throw new \LogicException('Review fingerprint changed.'); }
        if (!hash_equals($review['unit_allocation_digest'], HybridInvoiceRequest::digest($snapshot['allocations']))) { throw new \LogicException('Original unit allocation digest changed.'); }
        if ($sealed) {
            $seal = $snapshot['seal'] ?? null;
            if (!is_array($seal) || array_diff(array_keys($seal), ['digest','counts','allocation_version'])
                || !is_string($seal['digest'] ?? null) || !preg_match('/^[0-9a-f]{64}$/D', $seal['digest'])
                || !is_array($seal['counts'] ?? null) || ($seal['allocation_version'] ?? null) !== HybridUnitAllocations::VERSION) { throw new \LogicException('A complete supported Hybrid seal is required.'); }
            $unsigned = $snapshot; unset($unsigned['seal']);
            if (!hash_equals($seal['digest'], HybridInvoiceRequest::digest($unsigned))) { throw new \LogicException('Hybrid invoice seal does not reconcile.'); }
            $runs = 0;
            foreach ($snapshot['allocations']['segments'] as $segment) {
                if (!is_array($segment) || !is_array($segment['runs'] ?? null)) { throw new \LogicException('Invalid unit run structure.'); }
                $runs += count($segment['runs']);
            }
            if (HybridInvoiceRequest::json($seal['counts']) !== HybridInvoiceRequest::json(['lines'=>count($calculation['lines']), 'segments'=>count($snapshot['allocations']['segments']), 'runs'=>$runs])) { throw new \LogicException('Hybrid seal counts changed.'); }
        }
        $products = [];
        foreach ($calculation['lines'] as $index => $line) {
            if (!is_array($line) || ($line['line_number'] ?? null) !== $index + 1) { throw new \LogicException('Invalid original line ordering.'); }
            foreach (['product_id','unit_price','price_version','required_stock'] as $field) {
                if (!is_string($line[$field] ?? null)) { throw new \LogicException('Incomplete original product input.'); }
            }
            if (!array_key_exists('promotion_snapshot', $line)) { throw new \LogicException('Missing original promotion.'); }
            $products[] = ['product_id'=>$line['product_id'], 'unit_price'=>$line['unit_price'], 'price_version'=>$line['price_version'],
                'available_quantity'=>$line['required_stock'], 'promotion'=>$line['promotion_snapshot']];
        }
        $matches = [];
        $candidates = [self::CURRENT => [HybridPromotionPolicy::class, HybridUnitAllocations::class]];
        if (!$marked && $sealed) { $candidates[self::HISTORICAL] = [HistoricalHybridV1\HybridPromotionPolicy::class, HistoricalHybridV1\HybridUnitAllocations::class]; }
        foreach ($candidates as $name => [$policy, $units]) {
            try {
                $rebuilt = $policy::calculate($snapshot['request'], $products);
                if (HybridInvoiceRequest::json($rebuilt) === HybridInvoiceRequest::json($calculation)
                    && HybridInvoiceRequest::json($units::invoice($rebuilt)) === HybridInvoiceRequest::json($snapshot['allocations'])) { $matches[] = $name; }
            } catch (\App\Support\HybridCalculationException|\App\Support\InventoryException|\App\Support\ProductException $e) {
                // An explicit supported evaluator rejected these immutable inputs.
            }
        }
        if (!$matches) { throw new \LogicException('Snapshot calculation or original allocations match no supported revision.'); }
        return count($matches) === 2 ? self::EQUIVALENT : $matches[0];
    }
    public static function legacyMapping(array $snapshot): array
    {
        self::reconcile($snapshot); $c = $snapshot['review']['calculation'];
        return ['subtotal' => $c['original_paid_subtotal'], 'automatic_discount_total' => '0.00',
            'manual_discount_total' => $c['combined_monetary_discount'], 'discount_total' => $c['combined_monetary_discount'], 'grand_total' => $c['final_payable_total']];
    }
}
