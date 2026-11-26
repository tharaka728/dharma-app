<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\InventoryException;

final class HybridInvoiceRequest
{
    public static function requested(array $input): bool
    {
        foreach (['hybrid', 'policy_version', 'product_discount', 'replacement_discount', 'replace_free_entitlement'] as $field) {
            if (array_key_exists($field, $input)) { return true; }
        }
        foreach ($input as $value) {
            if (($value instanceof \stdClass || is_array($value)) && self::requested((array)$value)) { return true; }
        }
        return false;
    }
    public static function rejectOffline(array $input): void
    {
        if (self::requested($input)) { throw new InventoryException(['hybrid' => 'Hybrid V1 is online only.'], 409); }
    }
    public static function object(mixed $value): array
    {
        if ($value instanceof \stdClass) { $value = (array)$value; }
        if (!is_array($value)) { throw new InventoryException(['hybrid' => 'A versioned object is required.']); }
        return $value;
    }
    public static function keys(array $value, array $allowed): void
    {
        if (array_diff(array_keys($value), $allowed)) { throw new InventoryException(['hybrid' => 'Unsupported or client-calculated Hybrid fields.']); }
    }
    public static function normalize(array $input, bool $issue = false): array
    {
        self::keys($input, ['request_id','customer_id','stock_source','payment_type','amount_received','hybrid','review_hash','csrf_token','intent']);
        $id = $input['request_id'] ?? null;
        if (!is_string($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $id)) { throw new InventoryException(['request_id' => 'A stable UUID is required.']); }
        $h = self::object($input['hybrid'] ?? null);
        self::keys($h, ['policy_version','lines','invoice_discount_percent','replacement_discount']);
        if (($h['policy_version'] ?? null) !== HybridPromotionPolicy::POLICY) { throw new InventoryException(['hybrid' => 'Unsupported Hybrid version.'], 409); }
        if (!is_array($h['lines'] ?? null) || $h['lines'] !== array_values($h['lines']) || count($h['lines']) < 1 || count($h['lines']) > 100) { throw new InventoryException(['lines' => 'Supply 1–100 ordered product selections.']); }
        foreach ($h['lines'] as &$line) {
            $line = self::object($line); self::keys($line, ['product_id','paid_quantity','product_discount','replace_free_entitlement']);
            Representatives::id($line['product_id'] ?? null, 'product_id'); Inventory::quantity($line['paid_quantity'] ?? null);
            if (isset($line['product_discount'])) { $line['product_discount'] = self::object($line['product_discount']); }
        }
        unset($line);
        if (isset($h['replacement_discount'])) { $h['replacement_discount'] = self::object($h['replacement_discount']); }
        $h['channel'] = 'ONLINE';
        if (!in_array($input['payment_type'] ?? null, ['CASH','CREDIT'], true)) { throw new InventoryException(['payment_type' => 'Choose cash or credit.']); }
        $review = $input['review_hash'] ?? null;
        if ($issue && (!is_string($review) || !preg_match('/^[0-9a-f]{64}$/D', $review))) { throw new InventoryException(['review' => 'Review before issue.']); }
        $source = $input['stock_source'] ?? '';
        if (!is_string($source)) { throw new InventoryException(['stock_source' => 'Invalid stock source.']); }
        try { $received = Products::money($input['amount_received'] ?? '0.00'); }
        catch (\App\Support\ProductException $e) { throw new InventoryException(['amount_received' => 'Enter a nonnegative LKR decimal with at most two decimal places.']); }
        return ['request_id' => $id, 'customer_id' => Representatives::id($input['customer_id'] ?? null, 'customer_id'),
            'stock_source' => $source, 'payment_type' => $input['payment_type'], 'amount_received' => $received,
            'hybrid' => $h, 'review_hash' => $review];
    }
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
