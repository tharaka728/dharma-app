# Dharma Chemical sales application

**Phase 12 UI and readiness review:** responsive role-aware navigation, icon cards, scrollable tables and automatic product/customer codes are implemented. **READY FOR CONTROLLED PILOT ONLY** on the existing localhost setup; production is blocked by unsupported runtime branches and pending HTTPS/real-device acceptance. [Readiness, tests, security, backup/restore and deployment assessment](docs/phase12-readiness.md). [Manual acceptance checklist](docs/phase12-uat.md). No public deployment was performed.

**Phase 11 Reports:** [Admin reports](http://127.0.0.1:8088/admin/reports), [Representative reports](http://127.0.0.1:8088/rep/reports). Read-only sales, collections, current receivables/refund liabilities, inventory, performance, location summaries, offline status and admin reconciliation, with protected CSV exports. [Definitions, filters, limitations and verification](docs/phase11-reports.md). Stop before Phase 12. Earlier phase completion boundaries below are historical.

**Phase 10 invoice printing is available:** open [Admin invoice history](http://127.0.0.1:8088/admin/invoices) or [Representative history](http://127.0.0.1:8088/rep/invoices), then choose **Print Receipt**, **Print A4**, **Download PDF** or **Share Invoice**. [Printing instructions, document URLs, local PDF dependencies, tests and device limitations](docs/phase10-printing.md). Final documents require an authorized online session. Stop before Phase 11; earlier phase completion boundaries below are historical.

**Phase 9 offline workspace is available locally:** [New Sale](http://127.0.0.1:8088/offline/index.html#new), [Sync Status](http://127.0.0.1:8088/offline/index.html#status), [Pending Sales](http://127.0.0.1:8088/offline/index.html#pending), [Admin device/conflict/cash review](http://127.0.0.1:8088/admin/offline). Register online before offline use. Sales are provisional until accepted; offline credit is disabled. See [Phase 9 usage, encryption/device policy, backups, tests and HTTPS limitations](docs/phase9-offline.md). Actual phone installation has not been verified; no reachable HTTPS deployment was added. Stop before Phase 10.

**Phase 8 online billing is available:** [Admin New Invoice](http://127.0.0.1:8088/admin/invoices/new), [Admin history](http://127.0.0.1:8088/admin/invoices), [Representative New Invoice](http://127.0.0.1:8088/rep/invoices/new), [Representative history](http://127.0.0.1:8088/rep/invoices). See [Phase 8 instructions, approved financial rules, verified backup and tests](docs/phase8-invoicing.md). Product-only stock, partial cash/change, strict permitted credit, immutable snapshots and controlled void/refund liabilities are implemented. No offline invoicing, printing or physical refunds. Phase 9 has not begun.

Earlier phase notes below describe historical completion boundaries; the Phase 9 instructions above are current.

**Phase 7 Promotions is available:** [Admin Promotions](http://127.0.0.1:8088/admin/promotions), [Add / Preview](http://127.0.0.1:8088/admin/promotions/new), and [Representative current offers](http://127.0.0.1:8088/rep/promotions). Buy X/Get Y, percentage and fixed LKR rules are versioned, non-stacking and date-aware. [Phase 7 instructions and verification](docs/phase7-promotions.md). No offers were seeded; invoice/stock posting remains deferred to Phase 8.

**Phase 6 is available:** [Sales Representatives](http://127.0.0.1:8088/admin/representatives), [Allocate Stock](http://127.0.0.1:8088/admin/stock/allocate), [Representative Stock](http://127.0.0.1:8088/admin/stock/representatives). Representative dashboards show only their own product stock and history. See [Phase 6 usage, migration and verification](docs/phase6-representatives.md). No staff or allocations were seeded; Phase 7 remains out of scope.

**Current Inventory is product-only:** [http://127.0.0.1:8088/admin/inventory](http://127.0.0.1:8088/admin/inventory). Click **+ Add Stock**, select a product, enter quantity and selling price, then **Save Stock**. Batch/expiry entry has been retired; original records are preserved. [Current workflow, migration and verification](docs/phase5-simple-inventory.md) supersedes the older Phase 5 instructions below.

**Add stock:** click **+ Add Stock** in Inventory or open [Goods Receipt](http://127.0.0.1:8088/admin/inventory/receipts). Choose a product, select/create its batch, then record the receipt or explicit opening stock. See [stock-entry instructions and verification](docs/phase5-stock-entry.md).

**Phase 5 Inventory is available:** [http://127.0.0.1:8088/admin/inventory](http://127.0.0.1:8088/admin/inventory). Admin-only batches, goods receipts, controlled adjustments, immutable movement history, expiry controls and CSV exports reuse the foundation stock ledger. Configure your real central warehouse before posting. See [Phase 5 setup, stock model and verification](docs/phase5-inventory.md). No batches or stock transactions were seeded.

**Customer location enhancement:** searchable district and dependent town selection, persistent admin-created towns, and safe legacy-address review are available. See [lookup usage, migrations and verification](docs/customer-location-lookups.md).

**Customer list filters:** district and town filters now use searchable lookup dropdowns, including town-only filtering across districts. See [usage and verification](docs/customer-list-filters.md).

**Phase 4 Customers is available:** [http://127.0.0.1:8088/admin/customers](http://127.0.0.1:8088/admin/customers). Admin-only customer master data includes contact/location details, controlled customer types, credit terms, optional credit limits and reviewed shared-phone handling. See [Phase 4 usage and verification](docs/phase4-customers.md). No customers or financial balances are seeded. Inventory and batch management remain out of scope.

**Phase 3 Products is available:** [http://127.0.0.1:8088/admin/products](http://127.0.0.1:8088/admin/products). Admins can create categories, add/edit/view/search/filter products and activate/deactivate them. See [Phase 3 usage and verification](docs/phase3-products.md). No catalogue records are seeded. Other business modules remain planned.

**Phase 2 is now implemented.** Use [current login, dashboards, secure admin setup and test commands](docs/phase2-setup.md) and [the Phase 2 verification report](docs/phase2-verification.md). Open **http://127.0.0.1:8088/login**. The root now redirects to login. No default admin/password was created.

The instructions below are historical Phase 1 documentation. Do not follow their XAMPP MySQL startup, broad database grant, global Apache editing or generated-password instructions for this local instance. The current setup uses clean MariaDB on port 3309, private credentials, hidden-password admin provisioning and a separate loopback-only Apache configuration without global changes.

Phase 1 foundation for Ayurveda sales and billing. PHP 8+, PDO, InnoDB, locally hosted Bootstrap 5.3.3. LKR; UTC storage; Asia/Colombo business dates. No POS, login screens, dashboards, PWA, sync engine, PDF or printing is implemented. No Composer/npm build is needed.

Read [the design](docs/v1-design.md), especially the authoritative batch amendments in section 3A and pending decisions in section 9.

## Windows XAMPP setup

1. Open PowerShell in the project:

   ```powershell
   Set-Location 'C:\xampp\htdocs\dharma chemical sales app'
   & C:\xampp\php\php.exe -v
   & C:\xampp\mysql\bin\mysql.exe --version
   Copy-Item .env.example .env
   ```

   Copy only if `.env` does not exist. Edit it locally; it is ignored by Git. Values are single-line literals, optionally quoted, without interpolation. Existing process variables take precedence. Requires `pdo_mysql`, PHP >=8.0 and MariaDB >=10.4 or MySQL >=8.0.16 with enforced CHECK constraints. Verified installed tools: PHP 8.0.30 and MariaDB 10.4.32. MySQL compatibility has not been tested.

2. Start MySQL in the XAMPP Control Panel. In phpMyAdmin SQL, or the mysql client as a DB administrator, create a local development schema/account:

   ```sql
   CREATE DATABASE dharma_sales CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'dharma_app'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_YOUR_OWN_STRONG_PASSWORD';
   GRANT ALL PRIVILEGES ON dharma_sales.* TO 'dharma_app'@'127.0.0.1';
   ```

   Do not use the example password literally. Inspect existing schemas/accounts instead of resetting them. If your installation resolves local connections to a `localhost` account, create that host account consistently. Configure DB_HOST, DB_PORT (normally 3306), DB_NAME, DB_USER and DB_PASSWORD in `.env`. This broad schema grant is for local migration development only. Outside local development use separate migration credentials; runtime must have only necessary DML privileges, never DDL/TRIGGER/DROP.

3. Run migrations:

   ```powershell
   & C:\xampp\php\php.exe bin/migrate.php
   & C:\xampp\php\php.exe bin/migrate.php
   ```

   Second run reports Already applied. The runner uses a DB advisory lock, checksum and applying/applied journal. MySQL DDL implicitly commits: partial migrations stay applying and block blind retries. Back up first; restore or reconcile manually after failure. Never delete the journal row and rerun blindly, edit deployed migrations, or directly import SQL files through phpMyAdmin. Add forward migrations instead. No reset/down command exists.

4. Configure Apache to expose **only public/**. Back up Apache configuration. In `C:\xampp\apache\conf\httpd.conf`, enable `mod_rewrite` and the include for `conf/extra/httpd-vhosts.conf`. Add [docs/apache-vhost.conf.example](docs/apache-vhost.conf.example) to that included file. Preserve a localhost default virtual host before it if needed for localhost/phpMyAdmin.

   Because this project is inside the default htdocs directory, also add this global block to httpd.conf, outside a virtual host, to prevent access through the localhost project path even when .htaccess is disabled:

   ```apache
   <Directory "C:/xampp/htdocs/dharma chemical sales app">
       Require all denied
       AllowOverride None
   </Directory>
   ```

   The vhost's more specific public block grants local access. Root .htaccess is defense in depth where overrides are enabled. Never set DocumentRoot to the repository root. Add to `C:\Windows\System32\drivers\etc\hosts` with an administrator editor:

   ```text
   127.0.0.1 dharma-sales.local
   ```

   Validate and restart Apache in XAMPP:

   ```powershell
   & C:\xampp\apache\bin\httpd.exe -t
   ```

   Open `http://dharma-sales.local/` and `http://dharma-sales.local/api/v1/health`. JSON returns 200 when migrated or 503 when unavailable/unmigrated. Unknown routes give JSON 404; unsupported methods give 405. Confirm `.env`, `config/database.php` and `storage/` are denied on both the named host and the localhost project path. The example vhost permits local access only. Actual phone deployment requires later HTTPS/LAN configuration.

   Leave APP_BASE_PATH empty for this vhost. Future subpath deployment must map directly to public/ and set the prefix, e.g. `/sales`; never expose the repository root. No service-worker scope is registered yet.

5. Optional development admin provisioning:

   ```powershell
   & C:\xampp\php\php.exe bin/create-admin.php owner 'Local Owner'
   ```

   Requires APP_ENV=development. Generates a random password, displays it once and stores only password_hash. Do not log/share its output. Duplicate usernames never overwrite users. No login screen exists. See [seed policy](database/seeds/README.md).

## Inspect with phpMyAdmin

Open `http://localhost/phpmyadmin` with Apache/MySQL running. Select `dharma_sales`, then Structure or Relation view for tables/FKs. Inspect schema_migrations for two applied versions, permissions for the discount code, and users after provisioning. Never share password hashes. Read-only queries:

```sql
SELECT VERSION(), @@session.time_zone;
SHOW CREATE TABLE inventory_balances;
SHOW CREATE TABLE sale_item_batch_allocations;
SHOW TRIGGERS;
SELECT version, status, applied_at_utc FROM schema_migrations ORDER BY version;
```

phpMyAdmin uses its own connection timezone; application PDO sets +00:00. No business stock/invoice data is seeded. Do not manually edit financial/history records. Backups must include data, migration journal and triggers.

## Verification

```powershell
& C:\xampp\php\php.exe tests/run.php
Get-ChildItem app,bin,config,public,tests -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

For DB checks create a dedicated empty database `dharma_foundation_test` and grant your development user access. In the same PowerShell session:

```powershell
$env:DB_NAME = 'dharma_foundation_test'
& C:\xampp\php\php.exe tests/database.php
Remove-Item Env:DB_NAME
```

The script refuses names without `_test`, applies migrations twice, and rolls back fixtures. Schema remains for inspection; no database is dropped. It checks native prepares, UTC, uniqueness, FKs, negative stock, date ordering, immutable history and UUID format. It does not verify future business workflows. See [actual verification results](docs/phase1-verification.md). The ordinary XAMPP DB was stopped; verification used an isolated loopback instance on port 3307, without modifying normal DB or Apache configuration.

## Files and relationships

Created: `.env.example`, `.gitignore`, root/public `.htaccess`, `AGENTS.md`; `app/bootstrap.php`; HealthController and health view; middleware Authorization/Csrf/Session; support Database/Http/Migrator/Validation; `config/database.php`; `public/index.php`; Bootstrap CSS/license; `bin/migrate.php`, `bin/create-admin.php`; two SQL migrations; seed README; two test scripts; Apache example and verification report. Empty Services/Repositories/assets-css/storage directories have `.gitkeep`. Modified: README and docs/v1-design.md. Ignored storage may retain local verification artifacts.

| Group | Tables and relationships |
|---|---|
| Identity | users, devices, rep_operating_state, permissions, user_permissions, customers; device-owner FKs and explicit capped discount grants |
| Catalogue | categories, products, product_batches, product_batch_versions, promotions, promotion_versions, offline_policy_grants; immutable dates and approved policy history |
| Inventory | stock_locations, inventory_balances, inventory_transactions, stock_transfers, stock_transfer_items, inventory_movements; batch keys and immutable posted ledger |
| Sales | invoice_sequence_counters, invoice_number_allocations, invoices, invoice_items, sale_item_batch_allocations; lifetime sequence uniqueness and owner-bound allocations/grants |
| Settlement | payments, payment_allocations; matching customer FKs and pending bank confirmation |
| Sync/review | sync_operations, sync_stream_state, change_log, audit_log, review_flags; UUID identity, scoped cursor foundation and review records |

There are 31 business tables plus schema_migrations. All use InnoDB and restrictive FKs. Money is DECIMAL; row-level CHECKs and immutable-history triggers are present. Cross-row rules (batch sums, ledger reconciliation, number-range overlaps/membership, expiry, policy/role eligibility and payment totals) require later transactional services. Schema presence does not mean those workflows exist. Session/CSRF/role helpers are foundations; login, throttling and protected business endpoints are not exposed in Phase 1.

Pending: whole units, primary phone/replacement, FEFO/physical confirmation, exact expiry boundary, batch correction/conflict review, promotion stacking/caps/reasons/rounding, delayed historical-grant acceptance, cash/partial collections, offline-expiry behavior, stock-reduction authority, customer deactivation/reassignment, retention/recovery, number width/gaps, tax and legal document fields. See design section 9 for approved rules separately. 80mm/A4 printing is approved for later phases. Stop before Phase 2.
#   d h a r m a - a p p  
 