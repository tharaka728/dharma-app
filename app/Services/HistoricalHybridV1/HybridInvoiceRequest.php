<?php
declare(strict_types=1);
namespace App\Services\HistoricalHybridV1;
// Read-only historical evaluator. Pinned ed147d41894b109fae277e8e69b40e8a3dfecb57; source blob 10b1dd209d8d58430bc95096899a12ec71916b2b.
final class HybridInvoiceRequest
{
    /** Canonical object keys, preserving ordered lists and their rounding ties. */
    public static function json(mixed $value): string
    {
        $sort = static function ($v) use (&$sort) {
            if (!is_array($v)) { return $v; }
            if ($v !== array_values($v)) { ksort($v, SORT_STRING); }
            foreach ($v as &$child) { $child = $sort($child); } unset($child); return $v;
        };
        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    public static function digest(mixed $value): string { return hash('sha256', self::json($value)); }
}
