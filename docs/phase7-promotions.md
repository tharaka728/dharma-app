# Phase 7 — Promotions and Discounts

Implemented for the existing local application on 2026-11-09. Only the Dharma Chemical app and `dharma_sales_phase1` were changed. No invoice, payment, stock deduction, reservation or offline engine is included. Stop before Phase 8.

## URLs and first real promotion

- Admin list/search/filters: **http://127.0.0.1:8088/admin/promotions**
- Add / preview before saving: **http://127.0.0.1:8088/admin/promotions/new**
- View and immutable rule history: `/admin/promotions/{id}`
- Edit / preview: `/admin/promotions/{id}/edit`
- Representative read-only current offers: **http://127.0.0.1:8088/rep/promotions**

Both dashboards link Promotions. To create the first real offer:

1. Sign in as admin, choose **+ Add Promotion**, select your existing product, and enter the real promotion name/description.
2. Choose Buy X/Get Y, Percentage, or Fixed LKR per paid unit. Enter only that type's values, optional start/end dates and enabled/disabled status. Blank dates are unbounded. Disabled drafts may overlap; enabled offers cannot overlap another enabled period for that product.
3. Enter a paid quantity and click **Preview Only**. Review original price, paid/free quantities, total required physical stock, gross, discount and payable. The preview calculates the draft even when its date/status means it is not eligible now; this is clearly labelled. Nothing is saved, reserved or deducted by preview.
4. Click **Save Promotion** when correct. The detail page shows the saved rule, dates, status and immutable revision history. Activate/deactivate through the detail page. Representatives see only currently applicable offers; actual automatic invoice application is deferred to Phase 8.

Existing Products selling prices are displayed and remain authoritative. Promotions never change those prices. Products must be active before an enabled promotion can be published. Disabled drafts can be prepared for inactive products, but cannot apply until both product and promotion are active.

## Rules and exact calculations

At most one automatic rule applies to a product. There is no stacking, category offer, bundle, cross-product free item or manual invoice discount implementation.

- **Buy X/Get Y:** X and Y are positive whole-unit integers. Free quantity is `floor(paid / X) × Y`, separately from paid quantity. Required representative stock for future posting is paid + free. Only complete groups qualify. This never creates physical stock or changes stock during viewing/preview. Future invoice posting must deduct both quantities atomically from the representative's real balance.
- **Percentage:** 0.01% through 100.00%, at most two decimal places. Gross is paid quantity × original unit price. Calculate the percentage discount on the entire paid line, round that discount **half-up to the nearest LKR 0.01**, then subtract it from the exact gross. Do not round each unit's percentage separately or round the net independently. Free units have no paid-line discount amount.
- **Fixed:** a positive decimal LKR reduction per paid unit, at most two decimal places, never above the current Products selling price when created/edited/enabled. Discount is paid quantity × fixed per-unit amount. Equal-to-price is allowed, giving zero payable. Negative/excessive amounts are rejected.
- Monetary operations use integer-string cents arithmetic, not PHP/JavaScript binary floating point. Percentage basis points and large intermediate products are exact; twenty independent SQL decimal comparisons verify the implementation. API money uses decimal strings; IDs, version numbers and large quantity fields are also serialized as strings.
- Preview/quote line totals are capped at `DECIMAL(15,2)`, matching the existing future invoice amount columns. Whole-unit and paid+free arithmetic checks signed-64-bit bounds before multiplication/addition. Very large impossible lines produce validation errors rather than rounding, overflow or scientific notation.

Dates are **inclusive Asia/Colombo calendar dates**. A one-day offer starts at Colombo midnight and remains eligible through that date's final instant; it is ineligible from midnight the next day. Optional start/end dates are stored explicitly, with equivalent UTC timestamp metadata. Outside-period, disabled and inactive-product offers are not eligible. Future adjacent periods beginning the following day are allowed; sharing the same boundary date is overlap.

Enabled overlap is rejected across all three types, including future periods and activation/edit changes. The same product's write operations share a locked promotion gate; no deterministic winner or silent best-offer selection is used. Unexpected ambiguous eligible rows from external database corruption cause the quote service to fail closed with 409.

If the Products price later drops below an enabled fixed discount, representatives do not see it as applicable and the quote service raises an explicit validation error. It never silently clamps the discount, changes master prices or makes a negative payable. Admins can filter **Fixed discount exceeds price**, then edit or deactivate the offer; deactivation always preserves its prior values even if now invalid at the changed price.

## History, concurrency and security

The existing `promotions` and immutable `promotion_versions` are reused. Each edit and activation/deactivation appends a new rule revision, including name/description/status snapshots, product, type/values and effective dates. Earlier rule values remain protected by existing database immutability triggers. Records are not deleted.

Each save/status form has a UUID. `promotion_commands` stores its actor, canonical payload hash and original result. Replays return the original success without duplicate offers/revisions; changed details under an old UUID are rejected. Header version checks reject stale edits. Writers lock the singleton promotion gate, current header/product and overlapping offers, publishing header/revision/command/audit together. Standalone writes use READ COMMITTED with bounded deadlock/lock-timeout retries. Gate serialization protects concurrent overlap checks; current locking reads avoid stale decisions.

Every mutation, including draft-preview POST, is admin-only and CSRF-protected. SQL values are bound with native PDO prepares; text is UTF-8 validated and HTML-escaped. Anonymous access is denied. Representatives have GET-only currently applicable listing/quote routes and cannot inspect private drafts/history or mutate any promotion. Explicit select lists exclude product costs and credentials. Product, customer, warehouse and representative inventory records are never written by promotion services.

## Phase 8 integration contract — read only now

`Promotions::quote(actor, productId, paidQuantity, optionalTrustedInstant)` selects zero or one currently eligible rule and uses the authoritative product selling price. It returns currency, unit price, price version, paid/free/required-stock quantities, gross, discount, payable, business date, promotion ID, immutable rule-version ID and a rule snapshot. No rule means normal undiscounted paid pricing. Inactive products and invalid/ambiguous rules fail explicitly.

A standalone quote uses one repeatable-read transaction so price/rule reads are coherent. This is a point-in-time calculation, not a stock reservation, offline grant, immutable price promise or completed invoice. The optional instant is for trusted server integration/tests; HTTP callers cannot supply an eligibility date override.

Phase 8 must persist the original unit price, paid/free quantities, promotion/version identifier and rule snapshot, discount and final payable. It must revalidate eligibility and available representative stock while holding the posting locks in one transaction. Review and coordinate the shared inventory gate/representative-state/promotion-gate/product/balance/stream lock order before implementing posting; do not acquire the promotion gate after holding a product lock that a promotion writer needs. The pure arithmetic helper alone does not authorize sales or decide eligibility. No Phase 8 functionality was implemented here.

## Migration, backup and rollback policy

Inspection found zero existing promotion headers/revisions. The actual MariaDB constraint names were inspected before extension. Applied **012_promotions.sql** only to `dharma_sales_phase1` at `127.0.0.1:3309`:

- Extends existing headers with description/optimistic version.
- Extends immutable versions with FIXED type/amount, optional calendar dates, nullable start UTC and immutable name/description/status snapshots.
- Replaces the existing two-type rule CHECK with a validated three-type CHECK; retains foreign keys, prior date checks and immutability triggers.
- Adds a write-gate singleton and immutable idempotency command table. No business offers are seeded.

The application-only backup, outside served files, was created and SHA-256 verified before DDL and checked again afterward:

`C:\mariadb-clean\ayurveda-phase1-local\private\phase7-promotions-backup\before-phase7.sql`

Size **114,423 bytes**. SHA-256 **27E49D399E2F6221C3FDC0A875F641190BB64D51C133B9FCF1FAFC475817650F**. Existing backups/recovery files were not modified. All twelve applied migration checksums match. Current schema: 46 tables, 34 immutability triggers.

Migrations are forward-only and MySQL DDL is not transactional. Do not edit deployed migrations, reset migration state, delete revisions/commands or drop the extended tables to roll back. A correction should use a reviewed forward migration. For an application rollback, first deactivate affected real offers through the audited UI and review/disable incompatible promotion routes before deploying code that cannot understand FIXED/calendar fields. Retain the expanded schema/history. A full backup restore requires a separately approved maintenance/data-reconciliation plan because it would discard later legitimate staff, stock or customer changes; no restore was attempted.

## Verification and limits

- **121** promotion checks: all calculation types, groups/free stock demand, half-cent/line rounding, independent decimal oracles, large exact amounts/overflow, invalid/excessive rules, inclusive Colombo boundaries, optional dates, inactive state/product, price-drop handling, create/edit/activation overlap, fail-closed ambiguity, duplicate requests, stale edits, immutable history, permissions/CSRF, responsive form rendering and immediate price/stock preservation checks. Fixtures rolled back.
- **11** multi-process promotion contention/retry/no-write checks: two independent PHP requests wait on the same promotion gate and fail safely after bounded lock retries. No fictitious offer was committed for the concurrency test. Stale-edit, overlapping activation and duplicate-request behavior are exercised with rollback fixtures; a successful multi-client production publish was not performed.
- **241** live HTTP authentication/session/authorization/route checks, including actual read-only HTML/API preview against an existing product, rep current offers/quote, CSRF, malformed JSON, and rep write/private-draft denial.
- Existing **93** representative checks, **59** product-stock, **12** foundation, **16** database constraints, **69** Products, **91** Customers, **91** locations/filters, **124** archived batch-service, **11** representative contention and **7** inventory contention checks passed. Total **946 PHP/database/concurrency checks** for this run; the representative audit assertions include existing legitimate audit records.
- **13 JavaScript tests** passed. **69 PHP files** passed lint. Migration repeatability, native prepares, UTC connections, DML-only runtime grants and live warehouse/rep reconciliation verified.
- No promotion headers/revisions remain after tests. Current real stock/staff/master records and prior history are preserved; business fixtures were rolled back and existing authentication fixtures cleaned up. Apache 8088 and MariaDB 3309 remain bound to `127.0.0.1`; health reports ready.

Remaining limits: human desktop/mobile visual review is useful; previews are not physical-stock checks; fixed offers may need admin review after a master-price reduction; successful concurrent live publishing is intentionally left for an owner-authorized real offer. There are no invoices, billing, payments, actual stock deduction, automatic stacking, category/bundle promotions, batch fields or offline synchronization in Phase 7. No WordPress, unrelated database, recovery backup or global XAMPP change was made.

Re-run using the existing private credential wrapper (no passwords in arguments):

```powershell
& C:\xampp\php\php.exe storage/phase1-local/command.php promotion-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php promotion-concurrency
& C:\xampp\php\php.exe storage/phase1-local/command.php authentication-tests
node --test tests/promotions-ui.js tests/product-stock-ui.js tests/customer-location-filters-ui.js tests/customer-locations-ui.js
```
