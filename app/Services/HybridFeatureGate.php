<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\InventoryException;

final class HybridFeatureGate
{
    public static function configured(array $config): void
    {
        if (($config['enabled'] ?? false) !== true || empty($config['databases']) || empty($config['configuration_version'])) {
            throw new InventoryException(['hybrid' => 'Hybrid checkout is disabled.'], 409);
        }
    }
    public static function require(array $config, string $database, ?array $control, bool $schemaReady, string $operation): void
    {
        self::configured($config);
        if (!in_array($operation, ['preview','sale_posting','return_posting','custody'], true) || !in_array($database, $config['databases'], true)
            || !$schemaReady || !$control || (string)($control['id'] ?? '') !== '1' || (string)($control['enabled'] ?? '') !== '1'
            || !preg_match('/^[1-9][0-9]*$/D', (string)($control['version'] ?? ''))) {
            throw new InventoryException(['hybrid' => 'Hybrid schema or control readiness is unavailable.'], 409);
        }
        if ($operation !== 'preview' && (($config[$operation] ?? false) !== true || (string)($control[$operation] ?? '') !== '1'
            || ($operation!=='custody' && ($config['financial_posting_hold'] ?? true) !== false))) {
            throw new InventoryException(['hybrid' => 'Hybrid financial or custody posting is held.'], 403);
        }
    }
}
