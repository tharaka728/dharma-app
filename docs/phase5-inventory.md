# Phase 5 — admin inventory and batches

Stock entry now has a prominent **+ Add Stock** button, product-first receipt form, new-batch continuation and explicit opening stock. See [current entry instructions and verification](phase5-stock-entry.md); its workflow supersedes the original batch-first directions below.

Implemented and verified on 2026-11-09 against `dharma_sales_phase1` at `127.0.0.1:3309`. Only the existing application and its own schema were changed. Phase 6 has not begun.

## Screens and first real stock entry

Sign in with an existing administrator account. Inventory is now linked from the admin dashboard.

1. Open **http://127.0.0.1:8088/admin/inventory/settings**. Select an existing real active warehouse, or enter your real central warehouse code. Save. No warehouse or stock was automatically created. Warehouse selection is fixed afterward; a change needs a separate operational review. The near-expiry default is 90 calendar days and can be changed here. Leave low-stock thresholds blank until real quantities are chosen; zero is an explicit threshold, not blank.
2. Open **http://127.0.0.1:8088/admin/inventory/batches**. Choose an existing active product. Enter its actual printed batch number, optional manufacturing date, required expiry date, acquisition information, status and provenance/reason. Save. Batch creation alone changes no stock. Quarantine is the safe form default; choose Saleable only after checking the physical batch.
3. Click **Receive** beside that batch, or open **http://127.0.0.1:8088/admin/inventory/receipts**. Search product/SKU/batch and select the existing identity. Enter actual receipt date, whole-unit quantity, optional supplier/reference, optional decimal LKR unit cost, notes and a mandatory reason. Review the confirmation checkbox and post once.
4. Review **http://127.0.0.1:8088/admin/inventory** and **http://127.0.0.1:8088/admin/inventory/movements**. The receipt must appear in immutable history and the batch balance must reconcile. Repeating the same submitted request ID returns its original result without another movement. If a connection fails after submitting, retry with that same ID rather than opening a new receipt for the same delivery.

Additional screens:

- Batch revision/control form: `/admin/inventory/batches/{id}/edit`.
- Controlled adjustments: **http://127.0.0.1:8088/admin/inventory/adjustments**.
- CSV download, retaining movement filters: `/admin/inventory/movements.csv`.

Receipts can replenish an existing batch. Batch identities are unique per product using the original case-sensitive database constraint; outer whitespace is trimmed. Do not change the printed identity just to bypass an existing batch. An incorrect identity is preserved/quarantined; identify and correct physical stock through explicit ledger entries and the appropriate real batch, with reasons.

## Stock, dates and history

The implementation reuses `product_batches`, immutable `product_batch_versions`, `stock_locations`, `inventory_balances`, immutable `inventory_transactions` and `inventory_movements`. There is no second stock ledger. New controls and settings extend these structures.

- A balance key is `(location_id, product_id, batch_id)`; quantities are signed-64-bit whole units with a nonnegative balance constraint. PHP validates exact decimal integer strings before conversion and checks addition overflow. Product totals are SQL decimal/integer sums, not binary floating-point arithmetic.
- Physical stock is the warehouse balance. Available stock is that physical quantity only when the product is active, the batch is explicitly Saleable and its expiry date has not passed. Quarantined, blocked and recalled stock is unavailable. Expired stock remains physical and is clearly labelled. There is no persisted reservation model: the screens explicitly say **reserved stock is not tracked**; they do not invent reserved quantities or promise allocation availability after future reservations.
- Representative-held physical balances, if present in the existing schema, are reported separately and excluded from central warehouse totals. Phase 5 cannot transfer to representatives or post into representative locations.
- The owner approved saleability through the expiry date in Asia/Colombo; expiry begins at midnight on the following date. Server-side checks use this rule. `Inventory::today()` and `Inventory::saleable()` expose the same rule for future services; future invoice and representative posting must call/enforce it rather than trust a UI flag. No invoice implementation is included.
- Near-expiry includes today through `today + configured days`, inclusive. The default is 90; supported settings are 0–3650 days. Low-stock alerts use available warehouse stock at or below an explicitly configured per-product threshold. Unset thresholds generate no alerts.
- Manufacturing date is optional. A provided date cannot be future-dated or follow expiry. Receipts cannot be future-dated or precede the recorded manufacturing date. An admin may physically receive expired/quarantined goods for tracking, but that does not make them saleable.
- Revising dates or batch controls appends a new immutable batch revision and an audit entry. Existing movements retain their original revision/expiry snapshot. Optimistic revision/control versions reject conflicting edits. Batch identities and ledger rows cannot be edited/deleted through the app; existing database immutability triggers remain in place.
- Positive/negative adjustments require a reason. Negative stock is prohibited. A correction may be an explicit adjustment or an exact reversal referencing one of this phase's single-movement receipt/adjustment transactions. A reversal must exactly negate its original warehouse batch movement, and an original may be reversed only once. Sales, transfers and future financial transactions are not reversed by this screen.
- Unit costs are optional `DECIMAL(15,2)` LKR values, validated and returned as decimal strings. No financial ledger, cost averaging, inventory valuation or invoice costing has been implemented.

## Posting and concurrency

All HTTP routes and services require a currently active admin. POST routes require CSRF; SQL values use native PDO bound parameters. Submitted stock totals, locations and actor identities are not authoritative: the server selects the configured warehouse, locked balance and authenticated actor.

Standalone stock commands use a READ COMMITTED transaction. Phase 5 first locks the singleton inventory settings row to serialize warehouse commands/configuration, then batch identity/latest revision and its balance; movement reconciliation/current request checks use current reads. Any applicable product lock is taken before the batch on creation. The ADMIN change-stream lock is taken last and held through commit. There is no representative operating-state lock because no representative allocation is performed. Future services must follow the broader documented operating-state/batch/balance/stream lock order and coordinate with this gate.

Within that transaction the service validates the existing balance against its ledger sum, inserts one immutable transaction and movement with resulting quantity and batch revision, updates the balance/version, appends the ADMIN change marker and audit event, then commits together. Lock timeouts/deadlocks are retried twice; an unresolved conflict returns 409 with an instruction to reuse the request ID. Transactions supplied by a trusted caller use a savepoint; such callers must retain the same locking/commit discipline.

Each posting has a UUID stored as the transaction primary key plus a SHA-256 hash of canonical validated input. Replaying the same UUID/actor/payload is a no-op result; changed payload or actor is rejected. This is transport retry protection, not a claim that separate delivery submissions with new UUIDs can be identified automatically. Reconciliation checks both ledger-only and balance-only keys. An affected mismatching balance blocks posting; operators must investigate rather than directly edit totals.

## Reporting and exports

Stock views filter by search, product, category, batch text, expiry and physical/available/zero status, with batches ordered by expiry then stable ID for future FEFO allocation. Product totals reflect matching batches; real products without batches show calculated zero when the filters permit it. No allocation engine is implemented.

Movement history supports product/category/batch/search/type filters, 50-row pagination and authorized CSV export. Exports include location, product, batch, expiry snapshot, receipt details, movement/result quantities, reference, reason, user and reversal reference. Every cell is escaped with a CSV writer and formula-like values, including leading whitespace/format characters, are prefixed with an apostrophe. A CSV contains at most 10,000 matching rows; larger requests are rejected explicitly and must be narrowed. The current export is operational history, not a financial report.

## Migration and backup

The exact target and all previous applied checksums were verified before migration. A new private, application-only dump was created before any DDL:

`C:\mariadb-clean\ayurveda-phase1-local\private\phase5-inventory-backup\before-phase5.sql`

Size: **99,708 bytes**. SHA-256: **E0FD54A68A95A1F02DB77C0C5B8D1B6DDC33AF17B9F5CC6B1BF7D2BAB2EDA629**. The dump is outside the served project and its hash was checked again afterward. Previous backups were preserved.

Applied **009_admin_inventory.sql**:

- Non-destructive relaxation of `product_batch_versions.manufacturing_date` to nullable.
- New `inventory_settings` (single configuration row, 90-day default; no warehouse selected), `inventory_product_settings` (no threshold seeds), `inventory_batch_controls` (no batch seeds).
- Additional immutable transaction metadata: payload hash, receipt date, supplier, reference, optional decimal unit cost and notes; posting-time index.
- Existing migrations 001–008 were unchanged. All nine applied checksums verified. Current schema: 41 tables, 30 immutability triggers. No HTTP migration runner was introduced.

Original products and customers retained exactly the same content hashes before/after implementation:

- Products: `aa97a9103357d5964e20dc5fd4b98910ec7a4d644259909ce18406cda145034b`.
- Customers: `41821964328a375b0d495b6a7861436acc4995985cd62e6b7bab4176fdacbb69`.

There remain zero real batch identities/revisions, warehouse locations, balances, transactions and movements after testing. The first real entries are intentionally left to the owner. No product/customer master records were altered.

## Verification

- **108** inventory checks: batch uniqueness, optional manufacturing date, receipt persistence/cost precision, retry deduplication/payload conflicts, positive/negative adjustments, reasons, insufficient stock, integer overflow, exact reversals, immutable history, revisions/conflicts, stock/search/expiry filters, alert boundaries, reconciliation/corruption blocking, CSV escaping, admin permissions, CSRF and rendered screens.
- **7** multi-process checks: two actual PHP connections contend on the posting gate and retry/time out safely with 409; stock and audit content remain unchanged. This checks real lock contention without committing fictional stock. It does not simulate a successful two-client posting against a committed real batch; that operational exercise should use the owner's first real batch and authorized quantities.
- **170** live HTTP authentication/session/authorization/route checks, including new inventory pages/APIs, CSV, CSRF and rep denial.
- Existing **12** foundation, **16** database constraint, **69** Products, **91** Customers and **91** location/filter checks passed. Migration repeats, native prepares and UTC session checks passed.
- Total **564 PHP/database/concurrency checks**, plus **11 JavaScript regression tests**. **47 PHP files** passed lint.
- Temporary business fixtures were created only inside rollback transactions. Original table content fingerprints were checked after rollback. Existing auth-test user fixtures were cleaned up. No fictional product, batch or movement was retained.
- Health reports ready. Apache 8088 and MariaDB 3309 remain bound to `127.0.0.1`; runtime privileges remain DML-only. Global XAMPP configuration, port 3306, WordPress, other databases and recovery files were not touched.

Re-run from the project directory with the existing private local credential wrapper (no secrets in command arguments):

```powershell
& C:\xampp\php\php.exe storage/phase1-local/command.php inventory-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php inventory-concurrency
& C:\xampp\php\php.exe storage/phase1-local/command.php authentication-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php foundation-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php database-regression
& C:\xampp\php\php.exe storage/phase1-local/command.php product-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php customer-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php location-tests
node --test tests/customer-location-filters-ui.js tests/customer-locations-ui.js
```

Remaining operational checks: visually review the responsive native controls in desktop/mobile browsers and verify the first authorized real delivery against its physical documents. The warehouse and low-stock thresholds deliberately remain unconfigured. There are no representative allocations, invoices, payments or offline synchronization features in Phase 5.
