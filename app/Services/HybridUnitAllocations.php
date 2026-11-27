<?php
declare(strict_types=1);
namespace App\Services;

use App\Services\PromotionCalculator as M;

/** B1: immutable positions, bounded memory, no iteration over individual units. */
final class HybridUnitAllocations
{
    public const VERSION = 'HYBRID_UNITS_B1_V1';

    public static function segment(array $segment, string $unitPrice): array
    {
        $n = Inventory::quantity($segment['paid_quantity']);
        if (M::cents($segment['product_discount_amount']) !== '0' && M::cents($segment['replacement_discount_amount']) !== '0') {
            throw new \LogicException('Product and replacement benefits cannot overlap a segment.');
        }
        $gross = M::cents($unitPrice);
        if (M::multiply($gross, (string)$n) !== M::cents($segment['gross_amount'])) {
            throw new \LogicException('Segment gross does not match original units.');
        }
        $parts = []; $cuts = [0, $n];
        foreach (['product_discount_amount', 'replacement_discount_amount'] as $key) {
            [$q, $r] = InvoiceDiscount::divide(M::cents($segment[$key]), (string)$n);
            $parts[$key] = [$q, (int)$r]; $cuts[] = (int)$r;
        }
        $cuts = array_values(array_unique($cuts)); sort($cuts, SORT_NUMERIC);
        $runs = []; $base = '0';
        for ($i = 1; $i < count($cuts); $i++) {
            $start = $cuts[$i - 1]; $count = $cuts[$i] - $start;
            if (!$count) { continue; }
            $v = ['gross' => $gross];
            foreach ($parts as $key => [$q, $r]) { $v[$key] = $start < $r ? M::add($q, '1') : $q; }
            $deductions = M::add($v['product_discount_amount'], $v['replacement_discount_amount']);
            if (M::compare($deductions, $gross) > 0) { throw new \LogicException('Unit component exceeds gross.'); }
            $v['capacity'] = M::subtract($gross, $deductions);
            $base = M::add($base, M::multiply($v['capacity'], (string)$count));
            $runs[] = ['position_start' => $start, 'position_count' => $count, 'v' => $v];
        }
        $invoice = M::cents($segment['invoice_discount_amount']);
        if (M::compare($invoice, $base) > 0) { throw new \LogicException('Invoice allocation exceeds capacity.'); }
        $allocated = '0'; $ranking = [];
        foreach ($runs as $i => &$run) {
            [$share, $residual] = $base === '0' ? ['0', '0'] : InvoiceDiscount::divide(M::multiply($invoice, $run['v']['capacity']), $base);
            $run['v']['invoice_discount_amount'] = $share;
            $allocated = M::add($allocated, M::multiply($share, (string)$run['position_count']));
            $ranking[] = ['index' => $i, 'residual' => $residual, 'start' => $run['position_start']];
        }
        unset($run);
        usort($ranking, static fn($a, $b) => -M::compare($a['residual'], $b['residual']) ?: $a['start'] <=> $b['start']);
        // The residual is strictly below n, even when monetary products exceed PHP_INT_MAX.
        $left = (int)M::subtract($invoice, $allocated); $extras = [];
        foreach ($ranking as $rank) { $i = $rank['index']; $extras[$i] = min($left, $runs[$i]['position_count']); $left -= $extras[$i]; }
        if ($left !== 0) { throw new \LogicException('Unallocated invoice cents.'); }
        $out = [];
        foreach ($runs as $i => $run) {
            foreach ([[$extras[$i], true], [$run['position_count'] - $extras[$i], false]] as [$count, $extra]) {
                if (!$count) { continue; }
                $v = $run['v'];
                if ($extra) { $v['invoice_discount_amount'] = M::add($v['invoice_discount_amount'], '1'); }
                if (M::compare($v['invoice_discount_amount'], $v['capacity']) > 0) { throw new \LogicException('Negative original unit net.'); }
                $v['net'] = M::subtract($v['capacity'], $v['invoice_discount_amount']); unset($v['capacity']);
                $start = $run['position_start'] + ($extra ? 0 : $extras[$i]);
                $last = count($out) - 1;
                if ($last >= 0 && $out[$last]['cents'] === $v && $out[$last]['position_start'] + $out[$last]['position_count'] === $start) {
                    $out[$last]['position_count'] += $count;
                } else { $out[] = ['position_start' => $start, 'position_count' => $count, 'cents' => $v]; }
            }
        }
        foreach (['gross' => 'gross_amount', 'product_discount_amount' => 'product_discount_amount', 'replacement_discount_amount' => 'replacement_discount_amount', 'invoice_discount_amount' => 'invoice_discount_amount', 'net' => 'payable_amount'] as $component => $key) {
            $sum = '0'; foreach ($out as $run) { $sum = M::add($sum, M::multiply($run['cents'][$component], (string)$run['position_count'])); }
            if ($sum !== M::cents($segment[$key])) { throw new \LogicException('Unit component conservation failed: '.$key); }
        }
        return $out;
    }

    public static function invoice(array $calculation): array
    {
        $segments = [];
        foreach ($calculation['lines'] as $line) {
            foreach ($line['segments'] as $index => $segment) {
                $segments[] = ['line_number' => $line['line_number'], 'segment_number' => $index + 1,
                    'kind' => $segment['kind'], 'return_priority' => $segment['kind'] === 'DISCOUNT_ELIGIBLE' ? 0 : 1,
                    'paid_quantity' => $segment['paid_quantity'], 'runs' => self::segment($segment, $line['unit_price'])];
            }
        }
        return ['version' => self::VERSION, 'segments' => $segments];
    }

    /** Rebuild projections from locked accepted ranges, rejecting overlaps, gaps or altered cents. */
    public static function consumeAfter(array $allocation, int $lineNumber, int $quantity, array $acceptedReturns): array
    {
        $cursors = [];
        foreach ($acceptedReturns as $acceptedRanges) {
            if (!is_array($acceptedRanges) || !$acceptedRanges) { throw new \LogicException('Invalid accepted return evidence.'); }
            $count = 0;
            foreach ($acceptedRanges as $range) {
                if (!is_int($range['position_count'] ?? null) || $range['position_count'] < 1 || $range['position_count'] > PHP_INT_MAX - $count) {
                    throw new \LogicException('Invalid accepted position count.');
                }
                $count += $range['position_count'];
            }
            $original = self::consume($allocation, $lineNumber, $count, $cursors);
            if (HybridInvoiceRequest::json($original['ranges']) !== HybridInvoiceRequest::json($acceptedRanges)) {
                throw new \LogicException('Accepted returns overlap, skip original positions or alter original cents.');
            }
            $cursors = $original['next_cursors'];
        }
        return self::consume($allocation, $lineNumber, $quantity, $cursors);
    }

    /** Pure proposal only. Caller must lock/rebuild cursors from accepted append-only events. */
    public static function consume(array $allocation, int $lineNumber, int $quantity, array $cursors): array
    {
        if ($quantity < 1) { throw new \InvalidArgumentException('Positive return quantity required.'); }
        if (($allocation['version'] ?? null) !== self::VERSION) { throw new \LogicException('Unsupported unit allocation version.'); }
        $segments = array_values(array_filter($allocation['segments'], static fn($s) => $s['line_number'] === $lineNumber));
        usort($segments, static fn($a, $b) => $a['return_priority'] <=> $b['return_priority'] ?: $a['line_number'] <=> $b['line_number'] ?: $a['segment_number'] <=> $b['segment_number']);
        $ranges = []; $net = '0'; $left = $quantity;
        foreach ($segments as $s) {
            $key = $s['line_number'].':'.$s['segment_number']; $n = Inventory::quantity($s['paid_quantity']);
            $cursor = $cursors[$key] ?? 0;
            if (!is_int($cursor) || $cursor < 0 || $cursor > $n) { throw new \LogicException('Invalid consumed-position projection.'); }
            $take = min($left, $n - $cursor);
            foreach ($s['runs'] as $run) {
                $start = max($cursor, $run['position_start']); $end = min($cursor + $take, $run['position_start'] + $run['position_count']);
                if ($end <= $start) { continue; }
                $ranges[] = ['segment' => $key, 'position_start' => $start, 'position_count' => $end - $start, 'cents' => $run['cents']];
                $net = M::add($net, M::multiply($run['cents']['net'], (string)($end - $start)));
            }
            $cursors[$key] = $cursor + $take; $left -= $take;
            if (!$left) { break; }
        }
        if ($left) { throw new \LogicException('Return exceeds unconsumed original paid positions.'); }
        return ['ranges' => $ranges, 'next_cursors' => $cursors, 'net_credit' => M::money($net)];
    }
}
