<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Support\InventoryException;

/** Shared authoritative read/lock context, restricted to verified isolated databases. */
class PdoHybridInvoiceContext implements HybridInvoiceContext
{
    public function __construct(protected PDO $pdo,protected ?array $databaseScope=null) {}
    protected function one(string $sql, array $values = []): ?array
    {
        $q = $this->pdo->prepare($sql); $q->execute($values); return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function database(): string { return (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn(); }
    public function schemaReady(): bool
    {
        $q = $this->pdo->prepare('SELECT checksum,status FROM schema_migrations WHERE version=?'); $q->execute(['025_hybrid_invoice_snapshots.sql']);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row && $row['status'] === 'applied' && hash_equals($row['checksum'], hash_file('sha256', dirname(__DIR__, 2).'/database/migrations/025_hybrid_invoice_snapshots.sql'));
    }
    public function transaction(callable $work, bool $readOnly): array
    {
        HybridDatabaseScope::verify($this->pdo,$this->databaseScope);
        $own = !$this->pdo->inTransaction();
        if ($own) { $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); $this->pdo->beginTransaction(); }
        else { $this->pdo->exec('SAVEPOINT hybrid_review'); }
        try {
            $result = $work();
            if ($own) { $readOnly ? $this->pdo->rollBack() : $this->pdo->commit(); }
            else { if ($readOnly) { $this->pdo->exec('ROLLBACK TO SAVEPOINT hybrid_review'); } $this->pdo->exec('RELEASE SAVEPOINT hybrid_review'); }
            return $result;
        } catch (\Throwable $e) {
            if ($own && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            elseif (!$own && $this->pdo->inTransaction()) { $this->pdo->exec('ROLLBACK TO SAVEPOINT hybrid_review'); $this->pdo->exec('RELEASE SAVEPOINT hybrid_review'); }
            throw $e;
        }
    }
    public function inventoryGate(): array
    {
        $row = $this->one('SELECT s.*,l.type location_type,l.is_active location_active FROM inventory_settings s LEFT JOIN stock_locations l ON l.id=s.warehouse_location_id WHERE s.id=1 FOR UPDATE');
        if (!$row || $row['stock_model'] !== 'PRODUCT' || $row['location_type'] !== 'WAREHOUSE' || !(int)$row['location_active']) { throw new InventoryException(['stock' => 'Active product inventory is required.'], 409); }
        return $row;
    }
    public function controls(): ?array
    {
        return $this->schemaReady() ? $this->one('SELECT * FROM hybrid_operating_state WHERE id=1 FOR UPDATE') : null;
    }
    public function actor(array $identity): array
    {
        $a = $this->one('SELECT id,name,role,is_active,version FROM users WHERE id=? FOR UPDATE', [$identity['id'] ?? '0']);
        if (!$a || !(int)$a['is_active'] || !in_array($a['role'], ['ADMIN','SALES_REP'], true)) { throw new InventoryException(['access' => 'Active authenticated account required.'], 403); }
        return $a;
    }
    public function source(string $source): void
    {
        if ($source === 'warehouse') { return; }
        $a = $this->one("SELECT id FROM users WHERE id=? AND role='SALES_REP' AND is_active=1 FOR UPDATE", [$source]);
        $state = $this->one('SELECT rep_id FROM rep_operating_state WHERE rep_id=? FOR UPDATE', [$source]);
        if (!$a || !$state) { throw new InventoryException(['stock_source' => 'Active representative operating state is required.'], 409); }
    }
    public function customer(string $id): ?array { return $this->one('SELECT * FROM customers WHERE id=? FOR UPDATE', [$id]); }
    public function promotionGate(): void
    {
        if (!$this->one('SELECT id FROM promotion_write_gate WHERE id=1 FOR UPDATE')) { throw new InventoryException(['promotion' => 'Promotion gate is unavailable.'], 409); }
        if ($this->one('SELECT batch_id FROM inventory_balances WHERE quantity<>0 LIMIT 1 FOR UPDATE') || $this->one('SELECT id FROM inventory_movements WHERE batch_id IS NOT NULL LIMIT 1 FOR UPDATE')) { throw new InventoryException(['stock' => 'Historical batch stock requires reconciliation.'], 409); }
    }
    public function product(string $id): ?array
    {
        return $this->one('SELECT id,sku,name,name_si,selling_price,version,is_active FROM products WHERE id=? FOR UPDATE', [$id]);
    }
    public function quote(array $actor, string $id, string $quantity, \DateTimeImmutable $instant): array { return (new Promotions($this->pdo))->quote($actor, $id, $quantity, $instant); }
    public function balance(array $settings, string $source, string $product): array
    {
        if ($source === 'warehouse') {
            $location = (string)$settings['warehouse_location_id'];
            $balance = $this->one('SELECT quantity FROM product_stock_balances WHERE product_id=? FOR UPDATE', [$product]);
        } else {
            $loc = $this->one("SELECT id,is_active FROM stock_locations WHERE rep_id=? AND type='REP' FOR UPDATE", [$source]);
            if (!$loc || !(int)$loc['is_active']) { throw new InventoryException(['stock' => 'Representative location unavailable.'], 409); }
            $location = (string)$loc['id'];
            $balance = $this->one('SELECT quantity FROM representative_stock_balances WHERE rep_id=? AND product_id=? FOR UPDATE', [$source,$product]);
        }
        $quantity = (string)($balance['quantity'] ?? '0');
        $ledger = $this->one('SELECT COALESCE(SUM(quantity_delta),0) quantity FROM inventory_movements WHERE location_id=? AND product_id=? AND batch_id IS NULL FOR UPDATE', [$location,$product]);
        if ((string)$ledger['quantity'] !== $quantity) { throw new InventoryException(['stock' => 'Stock ledger does not reconcile.'], 409); }
        return [$location,$quantity];
    }
    public function outstanding(array $actor, string $customer): string { return (new Invoices($this->pdo))->outstanding($actor, $customer); }
}
