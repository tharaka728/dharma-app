<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Support\Database;
use App\Support\Http;
final class HealthController
{
    public function show(bool $json): void
    {
        $ready = false;
        try {
            $pdo = Database::connect();
            $ready = (int) $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE status='applied' AND version IN ('001_foundation.sql','002_immutability.sql','003_authentication.sql','004_product_management.sql','005_product_cost_constraint.sql','006_customer_management.sql','007_customer_locations.sql','008_location_mapping_collation.sql','009_admin_inventory.sql','010_product_stock.sql','011_representative_stock.sql','012_promotions.sql','013_online_invoices.sql','014_offline_sales.sql','015_short_business_codes.sql','016_invoice_discount_snapshots.sql')")->fetchColumn() === 16;
        } catch (\Throwable $e) { /* Public health never discloses credentials or server errors. */ }
        if ($json) {
            Http::json(['status' => $ready ? 'ready' : 'setup_required', 'database' => $ready ? 'ready' : 'unavailable_or_unmigrated'], $ready ? 200 : 503);
            return;
        }
        $base = Http::escape(rtrim(getenv('APP_BASE_PATH') ?: '', '/'));
        $status = $ready ? 'Database foundation ready' : 'Database setup required';
        require APP_ROOT . '/app/Views/health.php';
    }
}
