# Phase 5 — simplified product inventory

The owner explicitly replaced batch/expiry-based administration with product-only stock for manufactured products. This instruction supersedes earlier Phase 5 workflows and batch-specific admin-stock defaults. No Phase 6 functionality is included.

## Three steps

Inventory: **http://127.0.0.1:8088/admin/inventory**

1. Click **+ Add Stock** and select an existing product.
2. Enter the quantity to add and selling price in LKR. The existing Products price fills automatically; changing it explicitly updates Products when saved.
3. Click **Save Stock**. Inventory opens with a success message, updated quantity, current selling price and stock value.

Direct entry: **http://127.0.0.1:8088/admin/inventory/add**. The former `/receipts` URL also opens the simplified form. Create real catalogue products through the existing Products module first. The normal workflow has no suppliers, batch numbers, manufacturing/expiry dates, receipt modes or warehouse setup form.

Each list row shows product code, name, selling price, available quantity, quantity × selling price, and Add Stock / Adjust / History links. Search by code/name; pagination preserves search/product filters. Inactive products remain visible with their held quantity and can be corrected, but must be activated before new stock is added.

**Adjust** opens `/admin/inventory/adjustments?product_id={id}`. Select Add stock correction or Remove stock, enter a positive whole-unit amount and mandatory reason, then save. This adds/subtracts the amount; it never replaces the total. Removal exceeding current stock is rejected.

**History** opens `/admin/inventory/history?product_id={id}` and records quantity added/removed, quantity afterward, date displayed in Asia/Colombo, responsible admin, reason and transaction ID. Storage timestamps remain UTC. `/movements` is a history alias; authorized CSV remains at `/admin/inventory/movements.csv` with formula protection and a 10,000-row export limit.

## Model and prices

`product_stock_balances` is the sole active product-only central balance, keyed by product ID. Existing `inventory_transactions` and immutable `inventory_movements` are reused. New product movements have NULL batch/revision references; no artificial batch identities, manufacturing dates or expiry dates are generated. New product-only quantities start at computed zero until an admin actually posts stock.

There is one configured central stock location (CENTRAL infrastructure was configured during migration because none existed). Staff do not manage warehouses in this workflow. No representative allocations, reservations, invoices or offline engine are implemented.

`products.selling_price` is the only authoritative current selling price. Stock entry locks the product and validates its submitted version. If the admin edits its price, the same transaction updates Products, increments its version and appends an explicit product-price audit record. A stale product/price form fails with 409 before posting stock. Select the product again or reload to review the current price before saving. History captures a price snapshot for audit only; snapshots are never competing active prices.

Quantities are whole signed-64-bit integers with nonnegative balance constraints and checked addition overflow. Input money uses existing decimal-string validation and `DECIMAL(15,2)`. Stock value uses SQL decimal multiplication (including values exceeding native integer cents); no PHP/JavaScript floating-point money calculations are used. It represents current selling value, not purchase cost, inventory cost accounting or revenue.

## Integrity and access

- All screens/APIs require a currently active admin. POST requires the existing CSRF token. The local JavaScript binds the selected product to its price/version; without JavaScript, the fallback Choose product GET form loads that product's price explicitly.
- Save Stock accepts product, positive quantity and selling price; adjustments require direction and reason. Submitted totals, actors and locations are never trusted.
- A READ COMMITTED transaction locks the shared inventory settings gate, the product and its balance. It checks the movement sum, updates the authoritative price if explicitly changed, inserts the immutable transaction/movement, updates balance/version and appends audit records together. Lock conflicts retry twice; unresolved conflicts return 409.
- Every form has a UUID/payload hash. The same actor/UUID/payload replays as a no-op, including after the original save changed the product price/version. Reusing a UUID with different details is rejected. A new form/new UUID is a new stock request, so check history before intentionally resubmitting a delivery/count through another form.
- Balance/ledger mismatches block affected posting and are visibly flagged. Existing immutability triggers still prevent ledger edits/deletes. Corrections append adjustment entries.

## Compatibility and backup

Pre-migration inspection found **two products, two customers, one batch definition and one batch revision; zero locations, stock balances, stock transactions and movements**. The saved batch definition was preserved. No quantities needed converting, aggregating, reclassifying or discarding.

A new application-only private backup was created and SHA-256 verified before DDL:

`C:\mariadb-clean\ayurveda-phase1-local\private\product-stock-simplification-backup\before-product-stock.sql`

Size **104,868 bytes**. SHA-256 **10A54A0051664BBAD37DC2415B0A17119A48B888A947442FC44753E92CA241B9**. Existing backups were not overwritten or removed.

Applied **010_product_stock.sql** only to `dharma_sales_phase1` on `127.0.0.1:3309`:

- Adds explicit BATCH/PRODUCT mode and a product-only balance table.
- Expands movement batch/revision references to nullable with paired-NULL validation and a direct product FK. No existing movement is rewritten.
- Adds optional historical selling-price snapshot to immutable transaction metadata.
- Configures central location infrastructure without seeding any stock.
- Activates PRODUCT mode only when there are no nonzero batch balances and no movement history. If another installation has batch stock/history, the mode stays BATCH and product posting stays blocked; the migration must never be treated as an automatic conversion.

Runtime also refuses product posting if any batch movement or nonzero batch balance is later discovered, with a visible compatibility warning. Such data requires a separate owner-reviewed, backed-up conversion that documents each source location/product/batch balance, quarantined/blocked stock, ledger reconciliation and explicit transfer/correction entries. Historical batch identities, revisions and movements must remain intact. No silent SUM into product balances is permitted.

Old batch mutation routes are retired and legacy service writers reject PRODUCT mode. Former batch/settings screens redirect to simple Inventory. `/admin/inventory/legacy` retains authorized access to preserved historical movement reporting; original batch definitions also remain unchanged in MariaDB. This is a deliberate model switch, not two active stock systems. Original migrations 001–009 were not modified. Ten migration checksums verified; schema now has 42 tables and 30 immutability triggers.

## Verification

- **59** new product-only checks: addition/increment, authoritative price updates, exact stock value, stale-version handling, idempotency, invalid money/quantities, reasoned corrections, negative stock prevention, legacy compatibility blocking, ledger reconciliation, admin permissions, CSRF, simple forms and original-data fingerprints.
- **7** multi-process gate contention/retry/no-write checks using the current product-stock service. No fabricated stock was committed for concurrency testing.
- **124** archived batch-service regression checks executed only in an explicitly temporary BATCH-mode rollback transaction; they do not enable old stock entry in the running app.
- **173** live HTTP authentication/session/authorization/route checks, including the new form, retired routes and export.
- Existing **12** foundation, **16** database constraint, **69** Products, **91** Customers and **91** district/town checks passed. Total **642 PHP/database checks**; all business fixtures rolled back or existing auth-test users cleaned up.
- **12 JavaScript tests** passed, including product price/version binding and existing customer dropdown regressions. **52 PHP files** passed lint.
- Product, customer, original batch and revision fingerprints unchanged. No real stock was entered during implementation. Health ready; runtime credentials unchanged and DML-only. Apache and MariaDB remain localhost-only.

Re-run new checks with the existing private wrapper:

```powershell
& C:\xampp\php\php.exe storage/phase1-local/command.php product-stock-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php inventory-concurrency
node --test tests/product-stock-ui.js tests/customer-location-filters-ui.js tests/customer-locations-ui.js
```

Human desktop/mobile appearance review remains useful. No WordPress, recovery files, unrelated databases, ports or global XAMPP configuration were changed. Phase 6 has not begun.
