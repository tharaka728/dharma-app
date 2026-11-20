# Phase 6 — representatives and product stock allocation

Completed for the existing local application on 2026-11-09. Phase 5 was inspected first: the simplified product/quantity/selling-price entry was implemented, its 59 product-stock checks and 7 concurrency checks passed, and ten existing migration checksums matched. No batch/expiry workflow was reactivated. Phase 7 has not begun.

## URLs and staff workflow

- **Sales Representatives:** http://127.0.0.1:8088/admin/representatives
- **Add Representative:** http://127.0.0.1:8088/admin/representatives/new
- **Allocate Stock:** http://127.0.0.1:8088/admin/stock/allocate
- **Representative Stock Summary:** http://127.0.0.1:8088/admin/stock/representatives
- **Returns:** http://127.0.0.1:8088/admin/stock/returns
- **Representative Corrections:** http://127.0.0.1:8088/admin/stock/corrections
- **Stock Transfer History:** http://127.0.0.1:8088/admin/stock/history
- **Representative's own stock:** http://127.0.0.1:8088/rep/stock
- **Representative's own history:** http://127.0.0.1:8088/rep/stock/history

The admin dashboard links Sales Representatives, Allocate Stock, Representative Stock and Transfer History. Warehouse Inventory also links allocation, including a preselected product action. Representative dashboards display their own assigned products, quantities, current selling prices and recent allocation/return/correction history, with links to full paginated pages.

Create only real staff accounts. Enter name, unique username, phone and optional assigned area; choose and confirm a strong 12–72 byte password. The server creates only a SALES_REP role through this form. Existing Phase 2 representative accounts appear automatically; unknown phone/area fields remain blank rather than invented. Usernames are normalized lowercase and remain unique across active/inactive users. Name/phone/area lengths and phone syntax use the existing validation conventions.

View a representative to edit details, activate/deactivate or reset the password. Password reset identifies the target staff member, requires confirmation and the current account version, and revokes existing sessions using the existing authentication stamp. Passwords are hashed with `password_hash`; neither plaintext nor hashes are included in profile JSON, forms, audit payloads, URLs or application error messages. Password fields are never repopulated after a validation failure. Share the chosen password privately with the real staff member; no default password, account or outbound message is created.

Deactivation prevents sign-in and new allocations. It does not delete the account, stock or history; admins can still return stock or record a reasoned correction. Reactivation preserves the same identity and stock relationships. The app never edits an administrator through a representative endpoint or lets reps edit their own balances.

To allocate: choose a representative, choose a product, enter whole-unit quantity, then **Allocate**. Warehouse stock must already exist through Phase 5 Add Stock. Only active representatives/products can receive allocations. To return: choose the representative/product/quantity and **Return Stock**. Returns work for inactive representatives/products so held goods can be recovered. No selling price is changed by allocation or return; all displays use the authoritative current Products price.

Use **Representative Stock Correction** only for a verified physical discrepancy. Select add/remove and enter a mandatory reason. A correction intentionally changes recorded physical stock at that representative; it is not a warehouse transfer. Warehouse discrepancies continue to use the existing admin Inventory adjustment form. Neither correction path allows a negative balance.

## Balance, ledger and privacy model

The existing `product_stock_balances` remains the warehouse balance. New `representative_stock_balances` is keyed by `(rep_id, product_id)`. Existing `stock_locations` records represent ownership; a representative location is created only on the first successful stock posting, never with fabricated stock. Existing `rep_operating_state` records serialize representative operations and carry inventory revision metadata.

Allocations and returns reuse immutable `inventory_transactions`, `stock_transfers` and `inventory_movements`. `product_stock_transfer_items` supplies product-only transfer lines because the preserved original transfer-item table requires batch keys. No synthetic batch, expiry date or second ledger is introduced. Each allocation/return has one immutable transfer and exactly two equal/opposite movements, warehouse and representative, with their resulting quantities. For example, 500 warehouse units allocated 100 gives warehouse 400 and rep 100. Returns reverse the direction while preserving the combined physical quantity. Rep corrections have an explicit ADJUSTMENT transaction, one rep movement, reason and audit entry.

Warehouse reconciliation and history were corrected to filter the configured warehouse location. They no longer sum representative-side movement legs into company available stock. Representative reconciliation independently compares each owner/product balance with that representative location's ledger sum. Stock summary and transfer history never invent quantities; empty accounts have no allocated products until real posting.

Admin services and HTTP routes reload authorization. Representative reads independently verify an active SALES_REP identity, derive ownership from the authenticated user, and reject a forged `rep_id`; URL/query parameters cannot widen scope. Representative payloads use explicit whitelists for own product code/name, available quantity, selling price and scoped history. They exclude costs, password material, other representative balances and warehouse balances. Own-stock routes are GET-only. Admin allocation/return/correction/account writes require CSRF. Profile/status/password edits use optimistic version checks.

## Transactions and repeated requests

All stock writers share Phase 5's locked inventory-settings gate first. Phase 6 then locks the target user/representative operating state, product, warehouse balance and representative balance. It validates both ledger sums before inserting movements. Current/locking reads and READ COMMITTED standalone transactions ensure a waiter observes a preceding committed allocation. Insufficient stock, overflow, inactive allocation targets or reconciliation mismatches abort the whole command. Existing balance and ledger snapshots remain intact on failure.

Transaction UUID plus canonical payload hash provide duplicate-request protection. Identical actor/UUID/payload replays return the original result; changed details return 409. Both balance updates, transfer header/items, movement legs, representative revision, audit and ordered ADMIN/REP change metadata commit together. Change metadata prepares for later review; no offline synchronization API or engine is implemented. Deadlocks/lock timeouts retry twice and otherwise return a safe 409; retry the same request ID if submission was interrupted.

Shared gate serialization means two allocations cannot both spend the same warehouse units: the second rechecks the locked current balance. Paired ledger conservation is checked in automated tests and the live verification CLI. Transfer headers, ledger rows and new product transfer items are immutable; corrections append new entries.

## Migration, backup and preserved data

Exact target: `dharma_sales_phase1` on `127.0.0.1:3309`. A fresh application-only dump was verified before applying **011_representative_stock.sql**:

`C:\mariadb-clean\ayurveda-phase1-local\private\phase6-representative-backup\before-phase6.sql`

Size **107,828 bytes**. SHA-256 **F6AAD0BB9CBD847BF9CD510161CD1ACB73E6FB993860277D7A185A0F346DD5F3**; reverified afterward. Prior/recovery backups were preserved.

Migration 011 only adds phone/assigned-area fields to users, the representative product balance table, product-only transfer items and their two immutability triggers. It does not change existing roles, names, credentials, products, customers, quantities or history, and seeds no staff/allocations. Existing schemas for authentication, stock ownership, operating state and transfers were reused. All eleven migration checksums match; current schema has 44 tables and 32 immutability triggers. No HTTP migration runner was added.

Before implementation there were two real product-only warehouse transactions/movements and two product balances. Their exact content hashes remained unchanged afterward, as did products, customers, the saved legacy batch/revision and central location. No legacy batch stock or batch movements existed, so no conversion was needed. The legacy guard still rejects transfers if such quantities/history are discovered; it never aggregates or discards them. No test representatives, representative balances or transfers were retained.

## Validation and limits

- **91** Phase 6 service/form checks: create/edit/deactivate/reactivate, username/password validation, password hashing and actual session revocation, representative/admin role separation, allocation/return conservation, idempotency, insufficient stock, corrections/reasons, warehouse/rep reconciliation, corruption blocking, immutable transfer items, scoped reads, no cost leakage, screen rendering and CSRF.
- **11** multi-process allocation contention/retry/no-write checks. Two independent PHP processes contend on the same held database gate, retry and safely return 409 without writing stock. Together with locked-balance/insufficient-stock tests this verifies the serialization path without committing fabricated stock. A successful two-client posting exercise against a committed real representative was deliberately not performed; it would require an owner-selected real representative and authorized quantities.
- **213** live HTTP authentication/session/authorization/route checks: new admin screens, profile secrecy, CSRF, malformed JSON, rep dashboard data sections, own-only APIs, forged scope denial and GET-only own stock.
- Existing **12** foundation, **16** database constraint, **69** Products, **91** Customers, **91** locations/filters, **59** product-stock, **124** archived batch-service and **7** Phase 5 concurrency checks passed. Total **784 PHP/database/concurrency checks**.
- **12 JavaScript regressions** passed. **61 PHP files** passed lint. Migration repeats, native PDO prepares, UTC connections and restricted DML-only runtime grants verified.
- Live warehouse and representative reconciliation passed; the verification also checks every product transfer's two matching source/destination legs. No test stock was committed; full original-table fingerprints were checked after rollback. Existing HTTP authentication fixtures were cleaned up.
- Health remains ready. Apache 8088 and MariaDB 3309 remain bound to `127.0.0.1`. No WordPress, other database, recovery file, global XAMPP or port changes.

Human desktop/mobile review and the first owner-authorized real staff account/allocation remain operational checks. No known implementation blocker remains. No invoices, payments, promotions, discounts or offline synchronization were implemented; stop before Phase 7.

Re-run from the project directory using the existing private credential wrapper:

```powershell
& C:\xampp\php\php.exe storage/phase1-local/command.php representative-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php representative-concurrency
& C:\xampp\php\php.exe storage/phase1-local/command.php representative-verify
& C:\xampp\php\php.exe storage/phase1-local/command.php authentication-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php product-stock-tests
```
