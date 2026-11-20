# Phase 8 — Online Sales Invoicing & Billing

Implemented in the existing Dharma Chemical application. Product-level stock only. Phase 9, offline synchronization, printing, PDF export, gateways and physical refunds remain outside this phase.

## URLs and first real invoice

- Admin New Invoice: http://127.0.0.1:8088/admin/invoices/new
- Admin history: http://127.0.0.1:8088/admin/invoices
- Representative New Invoice: http://127.0.0.1:8088/rep/invoices/new
- Representative sales history: http://127.0.0.1:8088/rep/invoices
- Assigned customers: http://127.0.0.1:8088/rep/customers
- Number prefix: http://127.0.0.1:8088/admin/invoices/settings
- Details: `/admin/invoices/{UUID}` or `/rep/invoices/{UUID}`.
- Customer history: append `?customer_id={customerID}` to the appropriate history URL. Representative filtering uses `?rep_id={representativeID}` on admin history.

1. In Customers, open the real customer's detail or edit page. Save **Billing permission and representative assignment** separately: select an active representative if they will sell to this customer. Enable **Allow Credit Sales** only where authorized. Configure credit terms and the optional limit using the existing contact/master-data edit form. Both forms reject stale versions. Saving neither creates invoices nor changes stock.
2. Open **New Invoice**. An admin explicitly selects Central warehouse or a representative; representatives automatically use their own stock. Select the real customer, search/select products, add one paid whole-unit quantity per product, choose CASH/CREDIT and enter actual cash received (0.00 if none). Use **+ Add product** for more lines. Availability is advisory; the server checks again when posting. **Load stock availability** is also available without JavaScript.
3. Click **Review invoice**. Check the customer/source, current unit prices, offer, paid/free demand, discount, total, cash applied, change, outstanding and due date. Click **Confirm & Issue Invoice** only for the real sale. It atomically issues the immutable invoice, payment and stock movements, then opens its detail page. Changing financial inputs, master data, business date or customer outstanding requires another review.

The detail page shows original product/customer/representative snapshots, cash receipts, paid/outstanding and validity. Additional cash payments are recorded there. Admins can void there only with a reason and explicit confirmation. No invoice editor or deletion route exists. No business data is preloaded.

## Approved financial and access rules

The owner's Phase 8 approval supersedes the original warning-only credit proposal:

- CASH is due on the current Asia/Colombo date. Partial payment is allowed and remains explicitly outstanding. It does not implicitly enable credit sales. Credit permission/limit checks apply to CREDIT invoices; cash partial payment remains allowed under the separately approved cash policy.
- CREDIT requires `customers.allow_credit_sales=1`, default **0 for every existing and new customer**. Its due date is business date plus configured whole credit days. An enabled limit is a hard maximum for existing active customer outstanding plus the new invoice's net outstanding after its immediate payment. Equality is allowed; zero is a real zero limit. A disabled/unconfigured limit is unlimited, but never grants credit permission.
- Cash is the only receipt method enabled in this phase, including payments against CREDIT invoices. Apply the smaller of cash received and remaining debt; excess is separately recorded as change. Change is not a payment or revenue. A zero-value invoice is PAID without creating a fictitious payment.
- Validity is **ISSUED / VOIDED**, derived from an append-only void entry. Payment status is independently **UNPAID / PARTIAL / PAID**, derived from immutable posted allocations against the original total. A voided partially paid invoice therefore remains historically PARTIAL; its active outstanding becomes zero.
- An admin void restores paid + free units to the **original stock location exactly once**, removes remaining active receivables, and records the total already paid as **Refund Due**. Original invoices, payments and allocations stay unchanged. Refund Due is a customer liability, not evidence of a physical refund. There is no refund execution endpoint in Phase 8 and no automatic cash refund.
- Representatives can see only their own source invoices whose customers are **currently assigned** to them. Reassignment immediately revokes the previous representative's customer search, invoice access and ability to receive payments; admins retain full history. Unassigned customers are admin-only. Deactivated customers cannot receive new invoices but authorized historical reads/payments remain available.
- Admins can invoice all active customers but must explicitly select a real warehouse/representative stock source. Representative invoices cannot use warehouse or another representative's stock. Costs and secrets are excluded from every invoice/options payload.

## Money, confirmation and numbering

`PromotionCalculator` supplies exact integer-string cents arithmetic shared by quotations, invoice header sums, payments and change. No floating-point monetary calculation is used. All money is LKR decimal strings limited to DECIMAL(15,2). Percentage discounts round half-up once on the paid line to LKR 0.01; fixed discounts apply per paid unit. One eligible same-product promotion only, with paid and free quantities snapshotted separately. Full rule JSON and immutable promotion revision are saved on each line. Prices/rules are read under locks, using one server-time instant for every line's Colombo business date.

Review produces a fingerprint of the actual customer/version, source, prices, rules, quantities, financial totals/date and current customer outstanding. Issuance recomputes it server-side and rejects stale confirmation. Submitted totals are never authoritative. Browser search improves native selects; forms and additional product lines also work with JavaScript disabled.

Number format is `PREFIX-ownerID-00000001`. The default configurable prefix is DCS. The existing **lifetime per-owner sequence counters** are reused: a representative stock source owns its sequence, even for admin-issued sales; a warehouse sale uses the issuing administrator's counter. Allocation uses a locked counter, never MAX. A prefix change never resets counters or changes old numbers. Numbers remain unique across owners and prefixes. A failed transaction rolls back the increment; voided numbers are permanently retained. Do not reset counters, recycle numbers or manually reuse unused offline ranges. Legacy offline allocation/grant structures remain preserved but no offline devices or synthetic grants are created.

## Transactions and ledger model

All current stock writers and invoice/payment/void/customer-billing commands acquire the existing `inventory_settings` gate first. Applicable source user/operating state locks follow, then customer, promotion gate, products in ascending ID order, source balances, lifetime counter/settings, and ADMIN then REP stream metadata. Locks remain held through commit. READ COMMITTED current reads and bounded deadlock/timeout retries prevent stale credit/stock checks. The shared gate intentionally serializes posting for this small-company foundation; future throughput changes require reviewing every writer's lock discipline.

- Reuse `invoices`, `invoice_items`, `payments`, `payment_allocations`, `inventory_transactions`, `inventory_movements`, `rep_operating_state` and metadata streams.
- Every issue creates one SALE transaction and one negative product movement per invoice line, consuming paid + free units. Warehouse and representative balances remain distinct and must reconcile with their respective movement sums before posting.
- A void appends an ADJUSTMENT transaction with `reverses_transaction_id` pointing to the original SALE and one equal/opposite movement per line. No stock totals can be submitted or edited directly.
- UUIDs and canonical payload hashes provide invoice/payment/void idempotency. Same UUID/actor/payload replays the original result. Changed details or another actor under the same UUID are rejected. Invoice UUID is also its initial receipt request ID; later receipts require fresh UUIDs. A second void request cannot reverse stock twice.
- Existing immutable invoice/stock/audit triggers remain. New metadata, receipts, voids, payments and allocations are protected against UPDATE/DELETE by triggers. Credit checks, stock, header/lines, receipts, balances, audit and stream entries succeed or roll back together.

## Migration and verified backup

Only **013_online_invoices.sql** was applied to `127.0.0.1:3309/dharma_sales_phase1`. Migrations 001–012 were not edited.

- Adds explicit customer credit permission, default off; does not assign representatives or alter existing contact/credit-limit values.
- Extends existing invoice headers for online posting: nullable offline-only references, a channel CHECK preserving mandatory references for OFFLINE records, and a direct numbering-owner FK. No fake devices, grants or batches.
- Adds immutable line promotion JSON, unique invoice/product lines and nullable unknown cost snapshots.
- Adds only invoice settings, immutable online detail metadata, receipt command records and void/refund-liability records. Existing stock/payment business entities are reused.
- 50 total tables and 44 triggers; all 13 deployed migration checksums verified, and repeat migration execution passed.

Before migration, a new private single-transaction dump of the exact 46-table application database was created at:

`C:\mariadb-clean\ayurveda-phase1-local\private\phase8-backup-20261109-113130\before-phase8.sql`

Size **119,641 bytes**; SHA-256 **E99CD1FBAE625C4BE0151EC8977C882F5817BE6D8CD8A178780B4F1628331DA5**. Verified database target, all 46 CREATE TABLE definitions, dump completion marker and file hash. `verified.json` is beside the dump. No restore drill was performed; no existing database was reset. Do not reverse financial changes by dropping tables or editing deployed SQL. Any rollback/restore requires a separate reviewed procedure, especially after real sales exist.

## Verification

Executed with the established private credential wrapper; runtime remains DML-only and uses native PDO prepares with UTC connections:

| Suite | Passed checks |
|---|---:|
| Foundation | 12 |
| Database constraints / repeat migrations | 16 |
| Products | 69 |
| Customers | 91 |
| District/town lookup and filters | 91 |
| Archived batch inventory regression | 124 |
| Active product-only stock | 59 |
| Representative management/transfers | 93 |
| Promotions | 121 |
| Invoice/payment/credit/void service | 164 |
| Live localhost authentication/roles/CSRF/screens | 293 |
| Existing inventory/rep/promotion lock contention | 29 |
| Invoice lock contention and all-table preservation | 53 |
| **PHP/database suite total** | **1,215** |

Additionally: **15 JavaScript tests passed**, **78 PHP files passed lint** (plus the private wrapper), invoice JavaScript syntax passed, health API returned ready, and both 8088/3309 listeners were confirmed bound to 127.0.0.1. Seven financial integrity queries plus warehouse/representative ledger reconciliation passed. Read-only HTTP review was checked to leave invoice/payment/stock tables unchanged; an existing real invoice detail also rendered successfully.

Invoice fixtures are created only inside an outer rollback transaction. Every application table's content hash is compared before/after the invoice suite; no fictional customers, sales, payments or stock entries are retained. Authentication tests use their established disposable user cleanup. Concurrency tests use two separate PHP processes, a held real posting gate, timeout/retry checks and before/after preservation. **Successful competing committed sales and real browser/mobile end-to-end automation were not exercised**: no fictional committed sales are introduced into this operational database. Responsive/keyboard controls, server-rendered HTTP screens and browser-search logic were checked, but final manual review on the owner's phone/browser remains useful.

Useful local verification commands (private configuration required; do not expose it in public/):

```powershell
C:\xampp\php\php.exe storage/phase1-local/command.php invoice-tests
C:\xampp\php\php.exe storage/phase1-local/command.php invoice-concurrency
C:\xampp\php\php.exe storage/phase1-local/command.php invoice-verify
C:\xampp\php\php.exe storage/phase1-local/command.php authentication-tests
node --test tests/customer-location-filters-ui.js tests/customer-locations-ui.js tests/product-stock-ui.js tests/promotions-ui.js tests/invoices-ui.js
```

WordPress, other databases, existing recovery backups, global XAMPP configuration and ports were not changed. No Phase 9 work was started.
