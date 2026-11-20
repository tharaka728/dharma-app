# Version 1 business and technical design

Status: Phase 1 foundation authorized on 2026-11-08. Approved decisions and batch amendments below supersede earlier proposals. Only foundation code and migrations are implemented; later business workflows remain gated by pending decisions.

## 1. Scope and architecture

PHP 8+, PDO, InnoDB MySQL, HTML/CSS, Bootstrap 5 hosted locally, vanilla JavaScript, IndexedDB and a service worker. Currency: LKR. Two roles: ADMIN and SALES_REP. Administration is online; representative selling is offline-first. No Laravel, React or frontend framework.

Suggested directories:

```text
public/                         Apache document root
  index.php
  api/index.php                 JSON API entry point
  sw.js                         Static application-shell cache
  manifest.webmanifest
  assets/css/
  assets/vendor/                Bootstrap assets, available offline
  assets/js/ui/
  assets/js/api/
  assets/js/domain/              Calculations and validation
  assets/js/db/                  IndexedDB access and version upgrades
  assets/js/sync/                Queue, retries, reconciliation
app/Controllers/
app/Services/                   Sale, inventory, promotion business rules
app/Repositories/               PDO prepared queries
app/Middleware/                 Authentication, authorization, CSRF
app/Support/
config/                         Private configuration
database/migrations/            Future SQL migrations
database/seeds/
storage/                        Private logs and exports
tests/
docs/
```

Configure the XAMPP virtual host to serve only public/. Secrets and logs must not be web-readable. Service-worker scope and API base paths must support the chosen deployment path. The worker caches static assets, not authenticated API responses indiscriminately. Representative UI reads IndexedDB, including when connected. MySQL owns accepted transactions; IndexedDB owns unsynchronized transactions.

ADMIN has all business access. SALES_REP sees only assigned customers, its own stock and its own invoices. Product cost prices are admin-only and excluded from representative bootstrap/API payloads. Customer lists, counts, exports and detail endpoints enforce the same scope. An old local assignment is not sufficient server authorization.

Online authentication uses password_hash(), password_verify(), PDO prepared statements, regenerated PHP session IDs, HttpOnly/SameSite cookies, Secure cookies on HTTPS, CSRF protection and server authorization. Do not trust submitted rep IDs or calculated totals. Login throttling, output escaping and request-size limits belong in the foundation.

## 2. Database conventions

- All business tables use InnoDB and utf8mb4. Foreign-key types match exactly. Important records use RESTRICT deletion, not cascading business-history deletion.
- Internal entity IDs: BIGINT UNSIGNED. UUIDs: CHAR(36) ASCII with binary collation, normalized to lowercase. Validate UUID syntax; mobile sale UUIDs originate from crypto.randomUUID().
- Amounts: DECIMAL(15,2). Percentage rates: DECIMAL(5,2). Never FLOAT. JavaScript uses integer minor units; API monetary fields are decimal strings. Pending default: positive whole units (BIGINT), represented by the foundation schema; fractional selling requires an approved schema change before business implementation.
- UTC DATETIME(6) timestamps, UTC PHP/database connections. Display and derive invoice business dates using Asia/Colombo. Keep client-created and server-received times separately. Use server time/counters for synchronization, never device clocks.
- Common mutable-record fields: created_at_utc, updated_at_utc, version. Users/products/customers use is_active and optional archived_at_utc; never reuse archived IDs or SKUs. Published rule versions and finalized financial records are immutable.
- PK = primary key; FK = foreign key; UQ = unique constraint. Every listed *_id relationship is enforced by an FK unless explicitly called a generic reference. Required fields are NOT NULL; fields explicitly described as optional may be NULL.
- Enforce nonnegative quantities/amounts, valid statuses, positive deductions and discount bounds in services and supported database CHECK constraints. Confirm the actual XAMPP database engine/version before writing migrations; some XAMPP installations use MariaDB.

## 3. All proposed tables and important columns

### Identity, permissions and customers

| Table | Important columns | Keys and constraints |
|---|---|---|
| users | id, name, username, password_hash VARCHAR(255), role ADMIN/SALES_REP, is_active, archived_at_utc, timestamps, version | PK id; UQ username |
| devices | id UUID, user_id, label, registered_at_utc, revoked_at_utc, last_sync_at_utc | PK id; FK user_id -> users; UQ (id,user_id) for owner validation |
| rep_operating_state | rep_id, primary_device_id (optional), inventory_revision, updated_at_utc | PK/FK rep_id -> users; composite FK (primary_device_id,rep_id) -> devices(id,user_id); service requires SALES_REP role |
| permissions | id, code, description | PK id; UQ code; initial code sales.manual_item_discount |
| user_permissions | user_id, permission_id, enabled, max_discount_percent, granted_by_user_id, timestamps, version | PK (user_id,permission_id); FKs to users/permissions; granted_by_user_id -> users; enabled defaults false; cap must be 0..100 |
| customers | id, shop_name, contact_person, phone, address, area, assigned_rep_id, credit_limit, is_active, archived_at_utc, timestamps, version | PK id; FK assigned_rep_id -> users; one assigned rep per customer; nonnegative credit_limit |

The service restricts customer assignees and stock owners to SALES_REP users. Keep one current assignment on customers; assignment changes are recorded in audit_log. Unassigned shops may be permitted for admin setup if approved. Permissions are explicit grants with optional limits, allowing later codes without adding user boolean columns. Disabled/missing grants deny manual discounts.

### Products and versioned promotions

| Table | Important columns | Keys and constraints |
|---|---|---|
| categories | id, name, is_active, timestamps, version | PK id; UQ name |
| products | id, sku, name, category_id, cost_price, selling_price, is_active, archived_at_utc, timestamps, version | PK id; UQ sku including inactive products; FK category_id -> categories; prices >= 0 |
| promotions | id, name, is_active, current_version, timestamps | PK id |
| promotion_versions | id, promotion_id, version_number, product_id, type PERCENTAGE/BUY_X_GET_Y, percent_rate (optional), buy_quantity (optional), free_quantity (optional), valid_from_utc, valid_until_utc (optional), published_by_user_id, published_at_utc | PK id; UQ (promotion_id,version_number); FKs promotion_id -> promotions, product_id -> products, published_by_user_id -> users |

Proposed v1 rules: each promotion applies to one product; BUY_X_GET_Y grants the same product; at most one applicable promotion per product at a time. The service rejects overlapping effective rules. For bought quantity q, free units = floor(q / X) * Y. Free items consume inventory. Percentage discount applies to paid quantity only. Rep cannot override selling price, rule eligibility or free quantity. Proposed manual discounts are allowed only with an enabled permission and cap, and replace automatic percentage discounts rather than stack. This policy requires approval. Future cross-product gifts or combined rules can add rule/item association tables.

### Inventory

| Table | Important columns | Keys and constraints |
|---|---|---|
| stock_locations | id, type WAREHOUSE/REP, code, rep_id (optional), is_active | PK id; UQ code; UQ rep_id; FK rep_id -> users; REP requires rep_id, WAREHOUSE forbids it; one configured warehouse in v1 |
| inventory_balances | location_id, product_id, batch_id, quantity, version, updated_at_utc | PK (location_id,product_id,batch_id); FK location; composite FK batch/product; quantity >= 0 |
| inventory_transactions | id UUID, type OPENING/RECEIPT/TRANSFER/SALE/ADJUSTMENT (future RETURN/REVERSAL), invoice_id (optional), reverses_transaction_id (optional), reason, created_by_user_id, posted_at_utc | PK id; UQ invoice_id; FKs invoice_id -> invoices, reverses_transaction_id -> inventory_transactions, created_by_user_id -> users |
| stock_transfers | id UUID, inventory_transaction_id, from_location_id, to_location_id, created_by_user_id, posted_at_utc | PK id; UQ inventory_transaction_id; FKs to inventory_transactions, stock_locations and users; source != destination |
| stock_transfer_items | transfer_id, product_id, batch_id, quantity | PK (transfer_id,product_id,batch_id); FK transfer and composite FK batch/product; quantity > 0 |
| inventory_movements | id, transaction_id, line_number, location_id, product_id, batch_id, batch_version_id, quantity_delta, quantity_after, created_at_utc | PK id; UQ (transaction_id,line_number); FKs to inventory_transactions/stock_locations/products; delta != 0; quantity_after >= 0 |

Posted transactions/movements are append-only. Transfers are immediately posted atomic warehouse-to-rep operations; drafts are not stock movements. OPENING and RECEIPT allow initial stock and replenishment; ADJUSTMENT requires admin authority and a reason. No receipt/adjustment UI is implied by this proposal. Ledger sums reconcile with inventory_balances. Batch/expiry tracking is approved for v1. See section 3A for authoritative batch keys, revisions and allocations. There is no product-only stock balance.

### Invoice numbers and sales

| Table | Important columns | Keys and constraints |
|---|---|---|
| invoice_sequence_counters | rep_id, next_sequence | PK/FK rep_id -> users |
| invoice_number_allocations | id UUID, rep_id, device_id, start_sequence, end_sequence, allocated_at_utc | PK id; FKs rep_id -> users and (device_id,rep_id) -> devices(id,user_id); UQ (rep_id,start_sequence); non-overlap enforced by locked counter allocation |
| invoices | id UUID, invoice_number, rep_id, device_id, customer_id, number_allocation_id, sequence_number, business_date, created_at_client_utc, received_at_utc, subtotal, automatic_discount_total, manual_discount_total, discount_total, grand_total, payment_type CASH/CREDIT/BANK_TRANSFER, currency LKR, status FINALIZED (future REVERSED), accepted_at_utc, customer/shop/address snapshot, rep-name snapshot, client_payload_hash | PK id; UQ invoice_number; UQ (rep_id,sequence_number); FKs to users, devices, customers and invoice_number_allocations; composite device-owner FK; service validates allocation owner/range |
| invoice_items | id, invoice_id, line_number, product_id, product_name_snapshot, sku_snapshot, quantity, free_quantity, unit_price, gross_amount, automatic_discount_amount, manual_discount_amount, discount_amount, line_total, price_version, promotion_version_id (optional), manual_discount_permission_version (optional), manual_discount_reason (optional), cost_price_snapshot (server-only) | PK id; UQ (invoice_id,line_number); FKs invoice_id -> invoices, product_id -> products, promotion_version_id -> promotion_versions; quantity > 0, free_quantity >= 0 |

Sale quantity means charged quantity. Inventory deduction = quantity + free_quantity. Gross amount = quantity * unit_price. Line total = gross amount - discount amount. Discount amount = automatic + manual; it cannot exceed gross amount. Header totals are sums of rounded line values, with half-up rounding to two decimal places. No extra invoice-wide discount in v1. Invoice status is a financial state, not a device delivery state; local sync statuses are specified below.

Snapshot printed customer details, product names and prices so later master-data edits do not rewrite documents. Cost prices are captured by the server, never sent to reps. Index invoices by (rep_id,created_at_client_utc), (customer_id,created_at_client_utc), (payment_type,business_date), business_date; invoice_items by product_id. Payment type describes the sale arrangement, not proof of settlement.

### Credit and settlement foundation

These are proposed schema foundations for future outstanding-credit reports; collection screens and reports remain out of scope.

| Table | Important columns | Keys and constraints |
|---|---|---|
| payments | id UUID, customer_id, received_by_user_id, method CASH/BANK_TRANSFER, amount, reference (optional), received_at_client_utc, accepted_at_utc, status PENDING/POSTED, confirmed_by_user_id (optional), confirmed_at_utc (optional) | PK id; FKs to customers/users; amount > 0 |
| payment_allocations | payment_id, invoice_id, customer_id, amount | PK (payment_id,invoice_id); FKs to payments/invoices; amount > 0 |

Allocate payments transactionally; never allocate more than payment amount or invoice outstanding balance, and enforce same customer. Outstanding = accepted invoice grand total minus posted allocated payments (future credit/reversal amounts also reduce it). Cash invoices can create an immediate settlement record in the sale transaction. Approved: BANK_TRANSFER remains PENDING until an admin confirms receipt. Only POSTED allocated payments reduce outstanding. Credit limits generate warnings and review flags, never automatic rejection. Offline outstanding is an estimate. CASH is paid when collected as a pending default; partial collection policy remains unresolved.

### Synchronization and audit

| Table | Important columns | Keys and constraints |
|---|---|---|
| sync_operations | id UUID, user_id, device_id, operation_type SALE_CREATE, payload_schema_version, canonical_payload_hash, result_status RECEIVED/ACCEPTED/REJECTED, invoice_id (optional), attempt_count, last_error_code (optional), last_error_message (optional), received_at_utc, last_attempt_at_utc, accepted_at_utc (optional) | PK id; FKs to users/devices/invoices; UQ invoice_id; for SALE_CREATE id = invoice UUID |
| sync_stream_state | scope_key, last_change_id | PK scope_key; keys ADMIN and REP:{id} |
| change_log | scope_key, change_id, entity_type, entity_key, entity_version, action UPSERT/REMOVE, changed_at_utc | PK (scope_key,change_id); FK scope_key -> sync_stream_state; entity_key is generic, not an FK |
| audit_log | id, actor_user_id (optional for system), device_id (optional), action, entity_type, entity_key, reason (optional), before_json (optional), after_json (optional), occurred_at_utc | PK id; FKs actor_user_id -> users, device_id -> devices; generic entity reference has no FK |

Use one operation UUID equal to the invoice UUID for sale creation, not competing deduplication identifiers. New future operation types get their own UUIDs. Recorded results must not contain session secrets. Audit payloads exclude password hashes and credentials. Index operation owner/status and audit entity/time.

Do not use naive MAX(auto_increment_id) as a committed synchronization cursor: overlapping database transactions can commit out of ID order. Allocate stream change IDs under a locked sync_stream_state row held to commit. Changes in one scope therefore commit in cursor order. Multi-scope writes acquire scope locks in a consistent order. Customer reassignment publishes REMOVE to the previous rep and UPSERT to the new rep. Existing rep invoices keep their historical shop snapshot, not access to the shop's new data.

## 4. Offline invoice-number contract

Format: REP-{REP_ID}-{YYYYMMDD}-{SEQUENCE}; sequence has minimum four-digit padding and may grow beyond four digits. Date is the Asia/Colombo business date saved at finalization.

Approved: sequence increases for the lifetime of a rep and does NOT reset daily. Nonrecycled allocation ranges are approved. Minimum width and gap handling remain proposed defaults. Example: REP-12-20261003-0001, then REP-12-20261004-0002. This preserves the requested format while avoiding date-dependent allocation and clock-reset reuse.

Online registration/bootstrap reserves a block (suggested 1,000 numbers) for that device. Lock invoice_sequence_counters for the rep; allocate [next_sequence,next_sequence+999], advance the counter, and commit both together. Allocation blocks are never recycled, even after device loss. A subsequent device gets a different range. Server unique constraints provide a final collision guard.

IndexedDB atomically consumes a sequence in the same transaction that saves an invoice, items, reservation and queue entry. Refresh, simultaneous tabs and crashes cannot intentionally consume the same sequence twice. Print only after commit. An invoice carries allocation ID and sequence; server recomputes the formatted number and verifies range/ownership. UUID remains the technical key.

Reinstallation or lost local counters requires online registration and a new range; never reconstruct counters from only downloaded invoices or reuse old allocations. An exhausted range blocks new offline finalization until online allocation, so prefetch blocks before exhaustion. Gaps are permitted and audited; finalized numbers are never reused. Backups must not blindly restore a consumed counter into an active device; any restore needs online reconciliation/new allocation. If daily-reset sequences are mandatory, redesign to reserve per-rep/per-date blocks for a defined offline horizon before implementation.

## 5. IndexedDB model and local sale contract

Object stores: products, product_batches, product_batch_versions, policy_grants, categories, customers, promotions (published versions), permissions, inventory_snapshots, sales, sale_items, sale_item_batch_allocations, inventory_reservations, outbox, number_allocations, sync_metadata and offline_session. All stores are partitioned by user/device; a user switch never exposes another user's records.

For each local finalized sale, run one read/write IndexedDB transaction over the relevant stores:

1. Generate sale UUID using crypto.randomUUID(); set operation UUID to the same value.
2. Verify offline session allowance, downloaded active customer/product, assigned ownership and permission/rule versions.
3. Recalculate approved promotion, charged quantity, free quantity and totals.
4. Validate available units = last coherent server balance - unresolved local reservations. Validate aggregate demand per (product_id,batch_id), including free units across all lines. Validate batch expiry and downloaded batch revision.
5. Consume one allocated invoice sequence.
6. Save immutable invoice payload/items, save batch allocations, reserve quantity + free_quantity per batch and enqueue SALE_CREATE.
7. Set local sync_status pending; commit. Only then show success and enable printing.

Reservation is the representation of local stock deduction. No second deduction occurs on retry or acknowledgement. Failure to save any component rolls the entire local transaction back. Require durability:'strict' where supported, request persistent storage, and handle quota errors. Saved records should survive normal refresh/closure/restart, but device loss, browser clearing, eviction or abrupt storage failure cannot be absolutely prevented by a PWA. Provide pending-count visibility and an export/recovery plan. Service-worker updates and IndexedDB migrations preserve pending sales and numbering state.

Local delivery states:

| State | Meaning and transition |
|---|---|
| pending | Durable local sale waiting to send |
| syncing | Attempt in progress; no additional stock deduction |
| synced | Server acceptance durably recorded locally |
| failed | Known rejection/error; retained with error code, message, retryability and attempt time |

Startup recovers abandoned syncing operations to pending (with the same UUID). Network uncertainty goes back to pending with a last transport error; known server rejection becomes failed. Reauthentication pauses sending. Failed sales retain stock reservations because goods may already have left the rep. Neither retry nor failure releases stock. No rep editing/deleting finalized payloads. Any discrepancy requiring a business correction is an admin review; return/reversal implementation is deferred.

## 6. API synchronization contract

### Bootstrap

POST /api/v1/sync/bootstrap after online login/device registration: return schema_version, server_time_utc, current user/device, offline_access_expires_at_utc, scoped active master data/rules/permissions, rep inventory snapshot/revision, committed change cursor, relevant accepted invoice history, and number allocations. Never return cost prices or another rep's data. Mark offline-ready only after shell caching and the required dataset have committed locally.

### Push

POST /api/v1/sync/push accepts bounded batches. Each sale operation contains:

```json
{
  "operation_uuid": "same UUID as sale_uuid",
  "operation_type": "SALE_CREATE",
  "payload_schema_version": 1,
  "device_id": "registered device UUID",
  "sale": {
    "sale_uuid": "crypto.randomUUID() result",
    "invoice_number": "REP-12-20261003-0001",
    "number_allocation_id": "allocation UUID",
    "sequence_number": 1,
    "business_date": "2026-10-03",
    "created_at_client_utc": "2026-10-03T04:30:00.000Z",
    "customer_id": 81,
    "customer_version": 4,
    "payment_type": "CASH",
    "currency": "LKR",
    "items": [
      {
        "line_number": 1,
        "product_id": 9,
        "product_version": 3,
        "quantity": 10,
        "free_quantity": 2,
        "unit_price": "500.00",
        "automatic_discount_amount": "0.00",
        "manual_discount_amount": "0.00",
        "discount_amount": "0.00",
        "line_total": "5000.00",
        "promotion_version_id": 7
      }
    ],
    "subtotal": "5000.00",
    "discount_total": "0.00",
    "grand_total": "5000.00"
  }
}
```

Actual JSON must contain valid UUIDs; example labels are explanatory. Rep identity comes from the authenticated session, not a trusted payload field. Snapshot display fields and permission/rule references are included in the final contract; request snapshots never grant authority. Define a canonical payload normalization/hash specification covering immutable business fields, excluding mutable delivery status/retry timestamps. Never use raw JSON byte ordering as equality. Server computes the hash.

Return one result per UUID: accepted (new), already_accepted (identical retry), or rejected, with invoice UUID/number, accepted timestamp, error_code/message, retryable flag and inventory_revision where relevant. Do not mark an entire batch synced because HTTP succeeded. Each sale has its own database transaction; earlier successes survive a later rejection. Send in local creation/sequence order initially; inventory is authoritative under locking regardless of arrival order.

Acceptance procedure:

1. Authenticate owner; check request schema and device belongs to owner. Look up operation/invoice by UUID before mutable product/assignment/stock rules.
2. Existing accepted UUID + same owner + same canonical hash returns existing acceptance, without another stock write, even if current product/rule/assignment has changed. Do not disclose other owners' transactions. Revoked devices cannot create new sales; returning old receipts can occur only after valid owner reauthentication.
3. Existing UUID with changed immutable contents returns UUID_PAYLOAD_MISMATCH. Never silently overwrite.
4. For new/rejected identical operations, validate number allocation, current eligibility or approved historical policy, quantities, money and permissions. Recompute totals; never silently change the printed invoice. Stale-rule conflicts return a review error.
5. Lock and validate authoritative rep inventory, customer credit state for warning/review (never rejection solely for exceeding a credit limit), and commit accepted sale/items, payment records if required, stock ledger/balances, audit entries, change stream and operation acceptance atomically.
6. Uniqueness races block/retry safely and return existing identical acceptance. Rejections commit an operation error record without sale/inventory effects. Retrying an identical rejected operation may revalidate after stock/policy correction; do not permanently cache every rejection as unchangeable. Database deadlock/timeout is a retryable transport/service outcome, not successful acceptance.

The API preserves schema-v1 compatibility for an agreed offline window after deployments. A response received but not committed locally is treated as unacknowledged and is safely retried.

### Pull and stock reconciliation

GET /api/v1/sync/pull?cursor=... returns scoped ordered master-data changes/removals, next_cursor, has_more and server_time. Apply each page and cursor in one local transaction. Retained change-log limits require a full scoped resnapshot when cursors expire; a resnapshot never erases pending/failed sales.

POST /api/v1/sync/reconcile accepts the bounded list of all unresolved local sale UUIDs. Return a coherent full rep inventory snapshot with inventory_revision plus accepted receipts for matching supplied UUIDs. Read balances and acceptance receipts from one consistent database snapshot (REPEATABLE READ/consistent snapshot, or an equivalent locked read); do not mix receipts from a newer commit with older balances. If the unresolved list is too large, coordinate pages under one explicit snapshot/session token or defer balance replacement until all pages reconcile. Do not independently replace balances from push responses.

In one local transaction: reject an older inventory_revision, mark returned accepted sales synced, replace server inventory snapshot, clear only reservations represented by those receipts, retain all unresolved/failed reservations, and recompute available stock. No interleaving local sale can escape reservation accounting. Push acknowledgement can mark delivery synced, but reservation stays until a coherent inventory snapshot reflects acceptance; track an explicit awaiting_inventory_reconciliation flag rather than using only sync_status to calculate reservations.

Example:

| Stage | Server balance | Local snapshot | Reservations | Local available |
|---|---:|---:|---:|---:|
| Bootstrap | 50 | 50 | 0 | 50 |
| Offline A sells 20 | 50 | 50 | 20 | 30 |
| Offline B sells 20 | 50 | 50 | 40 | 10 |
| Server accepts A; response lost | 30 | 50 | 40 | 10 |
| Reconcile knows A accepted | 30 | 30 | 20 (B) | 10 |
| Accept B and reconcile | 10 | 10 | 0 | 10 |

Each database deducted A/B once. Snapshot replacement plus acknowledged-reservation removal keeps the same local available stock. Never replace working stock with raw server stock while unaccounted local sales exist. If a new authoritative snapshot makes unresolved reservations exceed stock, expose a discrepancy and block new affected sales; never hide it by silently discarding transactions or clamping away the debt.

Synchronization runs on app opening, manual Sync now and connectivity return with authenticated fetch/retry/backoff. navigator.onLine is only a hint. Background Sync is optional, not required for correctness. Use a local expiring sync lease/lock across tabs and worker; server idempotency remains the final guarantee.

## 7. Inventory database transaction strategy

Every posted inventory operation uses a single MySQL transaction. Lock relevant rep_operating_state rows (ordered rep IDs), and inventory balance rows in deterministic (location_id,product_id,batch_id) order. Precreate or safely create missing balance rows; do not depend on an unlocked nonexistent-row read. Allocate change stream locks in a globally consistent order. Customer credit locks and sequence-allocation locks also follow a documented global order. Retry bounded deadlocks with the same UUID.

Transfer: validate positive quantities and warehouse ownership, lock warehouse and rep balances, ensure warehouse balance covers aggregate items, subtract/add, append one transaction and paired movements, increment rep inventory_revision, write transfer header/items, audit and scoped changes, commit. Failure rolls everything back.

Sale: validate aggregate charged + free quantities per product/batch, lock rep balances, reject insufficient stock, decrement balances once, append SALE transaction/movements, increment rep inventory_revision, write invoice/items and acceptance atomically. Conditional UPDATE ... WHERE quantity >= demand provides an additional guard. No negative server balance is allowed. Ledger source invoice uniqueness prevents a second SALE posting.

V1 assumes one primary selling device. Enforce it at registration and new-sale synchronization; all tabs on that device share the same local database. Device switching requires draining the previous queue; lost-device replacement is an admin exception, with possible later rejected sales documented. Database includes device IDs and allocations for future multi-device stock partitioning, not conflict resolution now.

Warehouse-to-rep additions are compatible with offline selling. Admin reductions of rep stock or customer reassignment can invalidate pending sales. Proposed restriction: postpone rep-stock reductions/device switching until a coordinated sync, except explicit reviewed emergencies. No server can know all unuploaded transactions with certainty. New sales rejected for shortages stay visible and reserved locally; goods/customer documents require admin reconciliation.

## 8. Offline access and time

Approved: successful online verification grants seven days of offline access, refreshed only by successful authenticated online verification. Store user/display role, device ID, scope IDs, last verified server time, server-issued expiry and downloaded permissions; never plaintext passwords, password hashes or a long-lived bearer/session token in IndexedDB. HTTP session cookie remains managed by the browser. Optionally sign the offline allowance to detect simple metadata alteration, but this does not make a compromised device trusted or authorize API requests.

If online PHP session expired, pause sync and require login, keeping every sale. An unexpired local offline allowance can continue offline access while disconnected even if the PHP session would have expired. At offline allowance expiry, proposed behavior is to lock new invoice creation and customer browsing, preserve the queue, show an expiry/pending-count screen and require online login. No automatic indefinite extension. Extending offline access after expiry is a separate owner decision. Seven days is approved; expiry-screen behavior remains a pending default.

Device clock rollback can undermine client expiry checks; keep the latest trusted server time and a local high-water time and detect obvious rollback. This is practical access control, not tamper-proof enforcement. Offline revocation cannot take immediate effect. OS screen lock and managed device hygiene protect local customer data; a local PIN alone is not encryption. Logout/account switching hides the previous user's data and does not destroy pending sales; purge only after synchronized data retention rules or explicit reviewed recovery.

UTC storage avoids timezone ambiguity and supports future integrations. Asia/Colombo governs displayed dates, reporting business_date and invoice date segments. Save explicit business_date at finalization alongside UTC creation time; retain suspected client clock errors for review. Do not renumber finalized documents after a clock correction.

## 9. Approved rules and remaining owner decisions

Approved: batch manufacturing/expiry tracking in v1; lifetime continuous rep sequences and nonrecycled offline ranges; admin-defined promotions and explicit manual-discount permission; downloaded approved prices/rules usable up to seven days with conflict review; warning/review credit limits; bank transfers pending admin confirmation; seven-day offline access after online verification; LKR; later 80mm receipts and A4 PDFs.

Pending before business workflows:
1. Whole units, one primary phone, FEFO suggestions with physical batch confirmation, expired-stock blocking and cash paid when collected are proposed defaults. The foundation schema accommodates these defaults without activating selling behavior.
2. Promotion overlap, same-product gifts, stacking/replacement rules, rep caps, reason requirements and rounding approval.
3. Exact historical-policy acceptance/review procedure and treatment after the seven-day grant expires; no client timestamp alone proves sale time. Assignment/deactivation/revocation conflicts require review.
4. Expiry-day boundary, batch-date correction authority and offline expiry conflict disposition (section 3A).
5. Partial payments, collections, bank-confirmation audit process and outstanding report definition. Credit excess alone must never reject a sale.
6. Offline expiry screen, clock tolerance, phone replacement, stock reductions while offline, queue recovery/export, history and change-log retention.
7. Unassigned customers, inactive customer treatment, opening/receipt/adjustment authority and failed-sale admin procedure.
8. Minimum invoice width, gaps and allocation block size; tax fields/rates, company/customer legal fields and document language/compliance. 80mm and A4 formats are approved for later phases; no Sri Lankan tax rule is assumed.
## 10. Development and verification gates

1. Phase 1 foundation is authorized. Resolve remaining rules before implementing affected business workflows.
2. Foundation: actual engine/version check, migrations, authentication, role/ownership and API error conventions.
3. Admin products/customers/assignments, opening stock and atomic warehouse-to-rep transfers.
4. Offline app shell, IndexedDB, device registration, number allocation and scoped bootstrap.
5. Offline invoices, approved promotions, manual-permission enforcement, local inventory and offline print/download workflow.
6. Idempotent push/pull/reconcile, failure visibility, primary-device restriction and expiry handling.
7. Pilot on actual phones with HTTPS, backups/recovery and upgrade verification.

Reports, returns, reversals, damage workflows and complex multi-device resolution remain deferred. Reporting schema supports gross = paid-unit subtotal, discounts = automatic + manual discounts, net = grand total, free units = free_quantity, and groupings by business_date, rep, product, customer and payment_type. Never count unaccepted sales in authoritative server reports; clearly label local pending totals separately.

Required future acceptance tests: response lost after commit; two simultaneous requests for one UUID; same UUID/different payload; 50-unit A20/B20 example; partial push; concurrent transfer/sale; free-unit shortage; local transaction rollback; refresh/restart recovery; multi-tab numbering; number-range exhaustion; lost counter/device replacement; stale product/assignment/permission; credit-limit warning/review; old snapshot arrival; acceptance after snapshot; session expiry; failed reservation retention; IndexedDB migration with pending sales. Phase 1 foundation verification exists in tests/. The workflow acceptance tests listed here remain future work.

## 3A. Authoritative Phase 1 batch and policy amendments

This section supersedes the product-only table keys and unsettled payment defaults in the original table catalogue. SQL in `database/migrations/001_foundation.sql` is the complete executable foundation schema. No business posting endpoints exist yet.

| Table/change | Foundation decision |
|---|---|
| product_batches | Stable id, product_id and case-sensitive batch_number, unique within a product. Identity is immutable. No recycling batch numbers. |
| product_batch_versions | Manufacturing/expiry dates are normalized here instead of mutable columns on product_batches. Immutable revision id, batch_id, version_number, dates, reason, author and server time; unique batch/version. Latest version_number is the current revision. Creation of a batch and revision 1 must be one future service transaction. |
| inventory_balances | PK (location_id,product_id,batch_id); composite FK (batch_id,product_id) prevents cross-product stock. Signed BIGINT with nonnegative CHECK. Product stock is an aggregate, never a second balance to mutate. |
| stock_transfer_items | PK (transfer_id,product_id,batch_id), positive whole quantity. Future transfer command includes explicit batch quantities. |
| inventory_movements | Batch and batch-revision FKs, signed nonzero delta, nonnegative resulting batch quantity. Immutable posted records. |
| sale_item_batch_allocations | One row per invoice item/batch, charged quantity and free_quantity separately; composite FKs ensure item product, batch product and batch revision agree. Store printed batch/date snapshots. Multiple batches can satisfy one sale line. |
| offline_policy_grants | Immutable server-side grant, rep/device owner, issued/expires server times (maximum seven days), schema version, approved snapshot and hash. Snapshot contains approved price/product versions, published promotions, permission caps, customer scope and batch revisions. Exclude cost prices and secrets. Server-issued grant identity is authority; client-provided snapshots are not. |
| review_flags | Operation/invoice references, code/details and resolution metadata. CREDIT_LIMIT_WARNING does not itself reject a sale. Conflicts can reference rejected operations without creating accepted invoices. |
| payments | PENDING or POSTED; bank posting requires confirming-user FK and time. Future service verifies ADMIN role and audits confirmation. Pending allocations do not reduce outstanding. Composite customer FKs prevent cross-customer payment allocation. |
| devices | Primary state is stored only in rep_operating_state.primary_device_id, removing the redundant is_primary flag. Composite FK verifies ownership; future registration enforces SALES_REP and single-phone policy if approved. |
| immutable records | Migration 002 denies UPDATE/DELETE for batch identities/revisions, grants, invoice allocations, invoice headers/items/batch allocations, posted inventory transactions/movements/transfers and audit rows. Corrections require append-only records; no reversal workflow is implemented. |

Batch service contract for later phases:

1. FEFO suggests the earliest unexpired batch with available stock; rep confirms the physical batch. This default awaits approval. Free stock may use different batches from charged stock. Per-line sums of allocated charged/free quantities must exactly equal the invoice item quantities. These cross-row sums require transactional services, not a row CHECK.
2. Lock order is rep operating-state rows by rep id, customer rows by id where needed, batch identities by id (serializes revision publication with stock posting), balances by (location_id,product_id,batch_id), sequence counters by rep id where needed, then sync streams by scope_key. All writers acquire only applicable locks in this same order; precreate balance rows safely. Hold locks through commit. Allocate numbered ranges with the locked lifetime counter, never scan for MAX.
3. Future posting atomically writes header/items/allocations, batch movements/balances, revision, acceptance, review/audit and stream changes. Reject batch shortage; never substitute another batch silently. Balance quantity equals ledger deltas per location/product/batch. Expired goods remain visible stock; expiry never deletes stock or rewrites the ledger.
4. Proposed boundary: batch expiry_date is the last sellable Asia/Colombo date (block when business_date > expiry_date). Owner must approve the boundary and clock tolerance. Local finalization checks trusted-time high-water and downloaded revision; future server validation also checks authoritative revision and server receipt time. Reject/review an expired batch, suspected clock error or conflicting revision without changing a printed invoice. Goods already delivered require admin review; reservations stay until coherently resolved.
5. Date correction requires ADMIN, reason, batch identity lock, incremented immutable revision, audit entry and scoped changes to every affected holder. Previous movement/allocation revisions and printed snapshots stay unchanged. Neither an old downloaded date nor a seven-day price grant overrides a safety expiry conflict. Exact historical-expiry disposition remains an owner decision.

Updated future sync contract (not implemented in Phase 1):

```json
{
  "policy_grant_id": "11111111-1111-4111-8111-111111111111",
  "items": [{
    "line_number": 1,
    "product_id": 9,
    "quantity": 10,
    "free_quantity": 2,
    "batch_allocations": [
      {"batch_id": 31, "batch_version_id": 42, "quantity": 8, "free_quantity": 0},
      {"batch_id": 32, "batch_version_id": 43, "quantity": 2, "free_quantity": 2}
    ]
  }]
}
```

The fragment extends the original sale payload. Canonical hashing includes grant id, sorted batch allocations, batch revisions and immutable snapshots. Normalize money/UUIDs and deterministic object ordering; reject duplicates, never rely on raw JSON bytes. Bootstrap/pull include batches/revisions and grants. Inventory snapshot/reservation keys include user/device/location/product/batch. Reservation amount is charged+free per batch across all sale lines. Reconciliation returns a coherent full batch snapshot and matching accepted UUID receipts from one consistent database snapshot. Apply it atomically and clear only represented reservations; retain failed/unresolved reservations and the awaiting_inventory_reconciliation flag. The original A20/B20 reconciliation proof applies independently to every batch.

Seven-day historical prices/rules require a stored server-issued grant and server-verified owner, device, snapshot hash and grant interval. A client timestamp alone never proves an offline sale. Proposed safe automatic-acceptance window ends at the server-issued expiry measured at receipt; delayed submissions and changed assignment, permission, batch date or safety state require documented review. No silent repricing, renumbering, batch substitution or reservation release. Owner must approve the exact review policy before implementing sync. Credit warnings do not become hard rejection rules.

Foundation constraints do not replace future services: role eligibility, promotion overlap, allocation-range overlap/membership, batch expiry, allocation sums, payment totals, current policy authority, scoped access and ledger/balance equality require transactional checks. UUID format and row-level amounts/relationships are enforced now. Runtime DB users must not receive DDL/TRIGGER/DROP privileges; immutable triggers cannot protect against a privileged schema owner. Separate migration credentials are required outside local development. Login endpoints, throttling persistence, CSRF integration and authentication flows remain later work; helper foundations are present, with no protected business routes exposed.

## Phase 2 authorization amendment

The owner subsequently authorized secure authentication and initial dashboards. Phase 2 implements login/logout, CSRF, database-backed throttling, expiring sessions and server-side role protection, plus explicitly nonfunctional business-module navigation. Migration 003 is additive; migrations 001/002 remain immutable. This authorization does not approve pending business defaults in section 9 or any offline billing/sync implementation. Current setup and evidence are in `docs/phase2-setup.md` and `docs/phase2-verification.md`.

## Phase 3 authorization amendment

The owner authorized Product Management only: admin catalogue/category entry, listing/search/filter/view/edit, decimal-string prices and deactivation. Unit labels and descriptions extend products; optional cost uses NULL for unknown values. Additive forward migrations 004/005 preserve existing records and retain SKU ownership history. No inventory, batch, invoice or offline policy/posting behavior is activated. Scope and actual verification are in `docs/phase3-products.md`.

## Phase 4 authorization amendment

The owner authorized admin Customer Management with unique codes, contact/location/type/notes fields, day-based credit terms and optional LKR limits as master data. Existing representative assignment remains preserved but not editable or operationally allocated. Shared phone numbers require explicit reviewed/audited acknowledgment, and customers are deactivated rather than deleted. Additive migration 006 preserves existing credit checks and historical relationships. No balances, credit ledger, invoicing, collections, offline synchronization or Phase 5 inventory/batch functionality is activated. Current scope and test evidence are in `docs/phase4-customers.md`.

## Phase 4 location enhancement amendment

The owner additionally authorized Phase 4 district/town lookup selection and persistent admin-created towns. Migrations 007/008 preserve legacy address strings and enforce valid relational pairs. Unmapped legacy locations require review rather than inference. This does not expand scope to Phase 5. See `docs/customer-location-lookups.md`, including the explicit mixed-collation migration reconciliation procedure.

## Phase 5 authorization amendment

The owner authorized central-warehouse batch management, receipts, audited adjustments/reversals and immutable movement reporting only. The approved expiry boundary is the end of the expiry date in Asia/Colombo; stock becomes expired at midnight the following day. Quarantined, blocked and recalled batches are always unavailable. Near-expiry defaults to configurable 90 days; per-product low-stock thresholds remain unset until explicitly configured. Whole-unit quantities reuse the existing batch/location balances and ledger. Optional manufacturing dates, receipt metadata, batch controls and alert configuration are introduced by migration 009. No representative allocation, invoicing, payments or offline engine is activated. See `docs/phase5-inventory.md` for concurrency discipline, verification and operational limitations.

## Phase 5 product-only simplification — owner-authorized override

The owner explicitly replaced batch-specific admin inventory with product-only manufactured stock. This supersedes the earlier Phase 5 batch/expiry administration rule for current central stock. Product, quantity and authoritative Products selling price form the entry workflow. Additions increment balances; corrections append reasoned adjustments. Migration 010 preserves all historical batch tables/records, reuses the immutable movement ledger with nullable batch references, and introduces the sole active product-only balance plus an explicit mode gate. Existing batch quantities/history block automatic activation/posting and require a separate reviewed conversion. No synthetic batches, expiry dates or silently aggregated balances are allowed. Future rep/invoice/offline contracts must be reviewed for this changed model before any Phase 6 implementation. See `docs/phase5-simple-inventory.md`.

## Phase 6 authorization amendment — product-level representative stock

The owner authorized representative account management, secure admin password provisioning/reset, product-level warehouse allocations, returns, reasoned corrections and own-stock views only. This extends the product-only Phase 5 override to representative stock: no batches, expiry or suppliers. Reuse users, stock locations, operating state, immutable transactions/transfers/movements; additive migration 011 supplies representative product balances and product transfer lines. Keep the shared settings gate ahead of operating-state/product/balance locks; lock ADMIN then REP metadata streams last. Warehouse and representative ledgers reconcile separately; allocations/returns conserve quantity, while intentional physical corrections require reasons. Cost data never enters rep payloads. No invoice, promotion, payment or offline engine is authorized here. Verification and limits are in `docs/phase6-representatives.md`. Stop before Phase 7.

## Phase 7 authorization amendment — automatic product promotions

The owner authorized admin-managed same-product Buy X/Get Y, percentage and fixed-LKR-per-paid-unit offers, immutable rule history, inclusive optional Asia/Colombo dates and read-only representative eligibility/quote access. One automatic offer per product is enforced by rejecting overlapping enabled periods under a shared promotion write gate, including concurrent edits/activation. Percentage discounts round half-up to a cent on the paid line; fixed amounts cannot exceed current Products price. Integer-string arithmetic and decimal-string outputs prepare a read-only Phase 8 quote contract with original price, paid/free demand, immutable promotion snapshot, discount and payable. No physical stock, master prices or invoices are changed by promotion publication/preview. Migration 012 extends the existing promotion tables and adds only command/gate metadata. See `docs/phase7-promotions.md` for price-drop behavior, backup, forward rollback policy and verification. Do not implement Phase 8 invoicing, payments or offline posting without separate instruction.

## Phase 8 owner-approved online billing amendment

Phase 8 is now explicitly authorized for online invoice review/issue, payments, current customer assignment, admin voiding and immutable stock/financial history. The owner approved partial cash payments with change excluded from revenue; explicit default-disabled Allow Credit Sales; hard configured credit limits (superseding section 9 and the Phase 1 warning-only rule); active-representative customer assignment and current-assignment access enforcement; exact stock reversal plus separate Refund Due on void, without claiming a physical refund. Product-only inventory overrides continue. Reuse lifetime per-owner counters and original invoice/payment/stock tables, with migration 013 for online references and append-only metadata. Details, lock discipline, backup, limitations and tests are in `docs/phase8-invoicing.md`. No Phase 9 or offline engine is authorized.

## Phase 9 owner-approved offline amendment

The owner authorized the Phase 9 offline shell, encrypted local provisional cash sales and authenticated foreground synchronization. This supersedes the earlier proposed device-side/offline official number allocation: a local sale has only its UUID reference until server acceptance assigns the existing lifetime official sequence. No official sequential number is issued on the device. Product-level stock overrides continue.

Approved policy: maximum seven-day authorized snapshots; new sales stop when expired; only unchanged current product prices/versions and promotion revisions/eligibility auto-accept; conflicts retain agreed totals and provisional cash for review. Offline credit, refunds and voids are disabled. Exactly one primary registered signing device per representative, with admin revocation and explicit stock/cash reconciliation (including unknown unuploaded records) required before replacement. Revoked devices never auto-post. Sensitive local records require a separate passphrase-derived authenticated-encryption key and a 15-minute inactivity lock; unresolved data is never automatically deleted, and only reconciled Synced history is pruned after 30 days. IndexedDB alone is not encryption, and browser keys are not claimed to be hardware-backed.

The shared inventory gate serializes posting and coherent bootstrap snapshots/acceptance receipts. Only represented receipts release provisional local reservations; failed or ambiguous commands retain them. Reuse devices, grants, sync operations, invoices/payments and product-level inventory ledgers. Migration 014 supplies only necessary channel/control/audit metadata. Detailed device privacy/replacement policy, backup, actual isolated/browser tests and HTTPS deployment limitations are in `docs/phase9-offline.md`. No Phase 10 printing/PDF functionality is authorized.

## Phase 10 authorized read-only document amendment

The owner authorized 80mm thermal receipts, A4 printing and local A4 PDF generation from finalized invoice/item/customer/promotion snapshots. Current server payment/void state is read in one consistent read-only transaction, with the existing current-assignment representative scope. Documents never invoke invoice review/issue/payment/void or stock services. The installed local Edge headless engine renders self-contained escaped HTML through loopback CDP with external document resources blocked; no remote invoice service is used. Optional company contacts are explicit environment configuration, never invented. No schema migration is needed.

The Phase 9 encrypted device vault does not contain a complete verified authoritative invoice document and current payment/void state. Offline final-invoice printing is therefore disabled, with explicit provisional labeling for pending previews. Documents/PDFs stay outside the service-worker static allowlist. Downloaded/shared copies leave app storage protection and must be handled securely by the user. Android uses supported browser print dialogs/installed print services; no universal Bluetooth ESC/POS support is claimed. See `docs/phase10-printing.md`. Stop before Phase 11.

## Phase 11 owner-approved reporting amendment

Reports are read-only projections of existing finalized invoices, immutable item/customer/price/promotion snapshots, posted payment allocations, void liabilities, product-only balances/movements and server-known offline operations. No new financial ledger, reporting tables, schema migration or posting changes are introduced. Colombo calendar weeks are Monday–Sunday; current week/month presets cover their full calendar period. Gross sales are non-void pre-discount invoice totals, net sales are non-void final totals, and free quantities are separate. Collections use actual posted payment dates, excluding change and retaining later-voided collections with refund-liability status. Actual physical refunds remain unavailable because no authoritative refund-posting source exists.

Current receivables, liabilities and stock are snapshots with explicit as-of timestamps and do not use historical period filters. Product/category financial filters select whole invoices; product sales include matching lines only. Current assignment and invoice ownership remain mandatory for representative financial reports; exports have identical scope. Current location IDs group customers using existing lookup tables, with unmapped addresses identified. Queries run in consistent read-only transactions with native prepared parameters and decimal SQL arithmetic. See `docs/phase11-reports.md` for report-specific filter semantics and unavailable device-local drafts. Stop before Phase 12.

## Browser references (reference links)

## Phase 12 authorized usability and readiness amendment

The owner authorized shared role-aware navigation, responsive layouts, independent table scrolling, simpler report presentation and automatic business codes for new products/customers. Blank-code creates use PRD/CUS plus a stable form UUID rendered server-side; existing unique code/history constraints reject competing/repeated submissions. Optional explicit admin codes remain supported. Primary keys, existing codes, invoice lifetime sequences, payment/stock/offline UUIDs and financial semantics remain unchanged. No migration is needed. Essential current-balance/cohort/valuation warnings remain visible; detailed report definitions move to collapsed help.

Health readiness now checks all fourteen required migrations. The customer report uses equivalent single-pass aggregation to avoid repeated scans, preserving authorization and current company-credit usage. Final testing uses only the dedicated test database; application backup restoration uses fresh isolated schemas and rebinds trigger definers only in a separate restore import copy. Readiness is controlled local pilot only; supported runtime upgrades, HTTPS and real-device UAT remain prerequisites for production. See `docs/phase12-readiness.md`. No public deployment is authorized.

## Browser references

## Owner-approved 11 November 2026 usability and billing enhancement

Short P/C/S/A/M/R business references use transactional prefix counters and immutable reservations. Internal primary keys, official invoice numbers, signed offline identifiers and idempotency tokens remain unchanged. Existing manual master codes remain valid. The owner reviewed and approved the pinned eight-record mapping; the explicit CLI data migration retains old product/customer code history, leaves issued snapshots/financial and stock records intact and appends audit entries. Master version increments deliberately cause stale snapshots to require refresh/review.

The owner separately approved invoice-level 0–100% discounts (up to two decimal places), after line promotions and excluding free-unit value. Policy INVOICE_PERCENT_V1 rounds the invoice discount half-up to cents, distributes that exact amount proportionally across eligible paid lines by largest remainders with original line order breaking ties, and stores the original automatic and allocated invoice components in existing immutable header/item money columns. New immutable percentage/base/amount/policy snapshots are additive; old issued invoices are not backfilled or recalculated. Existing signed drafts without this policy retain legacy zero invoice discounts. New signed drafts use the approved policy and are server-revalidated without changing agreed totals. Cash, credit limits, void liabilities and paid/free stock rules remain unchanged except for using the approved discounted payable total.

List filtering progressively enhances existing authorized GET routes with debounced, abortable, generation-checked partial updates. POST workflows remain explicit and CSRF-protected. See docs/short-codes-discounts-live-filters.md. No mobile deployment or Tailscale activation is performed by this work.

- crypto.randomUUID() requires a secure context: https://developer.mozilla.org/en-US/docs/Web/API/Crypto/randomUUID
- Service workers require HTTPS; localhost is a development exception, ordinary HTTP LAN phone access is not: https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API
- Background Sync has limited browser availability: https://developer.mozilla.org/en-US/docs/Web/API/Background_Synchronization_API
- Persistent storage is requested, not an absolute guarantee against user deletion: https://developer.mozilla.org/en-US/docs/Web/API/Storage_API/Storage_quotas_and_eviction_criteria
- IndexedDB durability options: https://developer.mozilla.org/en-US/docs/Web/API/IDBDatabase/transaction
