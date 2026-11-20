# Short references, invoice discounts and live filters

Implemented 11 November 2026 in the existing application. No deployment or network changes. Owner separately approved the financial policy and the exact eight-record historical mapping during this task.

## Change impact and final behavior

The original generated PRD/CUS codes were derived from form UUIDs. Those UUIDs are now retained only as internal submission keys; transactional prefix counters and immutable reservations allocate readable business codes. Existing invoice, payment, stock, transfer, device and synchronization identities are unchanged. Existing GET list services remain the source of filtered results and enforce their original role scopes.

Invoice totals originally summed promoted paid lines. The approved change applies an additional invoice percentage to those payable amounts, allocates its rounded cents to the lines and stores immutable policy metadata. This affects preview/confirmation, posted header/item amounts, receipts/change/credit checks, offline revalidation, reports and read-only documents. Stock still consumes exactly paid plus free quantities. Existing historical financial snapshots are neither recalculated nor backfilled.

## Automatic reference codes

| Record | Format | Storage/behavior |
| --- | --- | --- |
| Product | P001, P002, … | Existing products.sku remains authoritative; meaningful manual SKU remains supported under collapsed Optional custom SKU. |
| Customer | C001, C002, … | Existing customer_code; create form assigns automatically, edit preserves current code. |
| Stock receipt/correction | S001, S002, … | Separate immutable display reference; transaction UUID unchanged. |
| Representative allocation/return/correction | A001, A002, … | Separate display reference; transaction/transfer UUID unchanged. |
| Promotion | M001, M002, … | Separate display reference; promotion/revision IDs unchanged. |
| Representative | R001, R002, … | Separate display reference; user ID, username and authentication unchanged. |

Padding is at least three digits, so P099/P100/P999/P1000 are valid. Counters lock inside the creating transaction. Unique reservations, master-code histories and database constraints prevent collisions/reuse. Failed transactions do not publish a code. Committed reservations cannot be updated/deleted. Retired manual codes also remain reserved. New automatic product/customer requests require their existing stable hidden form token; competing repeated forms cannot create two records. Two different concurrent tokens were also tested and received distinct codes.

Manual master codes/history seed or constrain the sequence; numbers are not forced back to 001 when records reserve them. No internal ID or invoice numbering convention was converted to a cosmetic short reference.

### Approved historical migration — applied

The deterministic dry run found eight records. The owner approved it before execution. A fresh backup preceded the data migration; the exact script was rehearsed on its isolated restore, then applied transactionally to dharma_sales_phase1.

| Scope | Approved result |
| --- | --- |
| One generated product code | P001; original SKU retained in product_sku_history. |
| One generated customer code | C001; original code retained in customer_code_history. |
| Two stock entries | S001, S002 display aliases; original UUIDs retained. |
| Three stock transfers | A001, A002, A003 display aliases; original UUIDs retained. |
| One representative | R001 display alias; username/user ID unchanged. |

No existing promotion needed mapping. The complete old-to-new mapping remains private at `C:\mariadb-clean\ayurveda-phase1-local\private\code-map-20261111165911.json`; its SHA-256 is `160aa891fde94f630b5a81223c93077070ad2fa32e7d03ab5be625ff9b61a8ce`. The original dry-run file is unchanged. Adjacent `.isolated-applied.json` and `.applied.json` receipts record execution results.

Both executions verified **19 protected tables byte-for-byte unchanged** and preserved all **26 existing audit rows**. Eight audit entries were appended with a system actor and the owner-approved map hash. Issued invoice item/customer snapshots, official invoice numbers, payments, stock ledgers/balances, device/sync records and user accounts were not rewritten. The two master records increment versions so stale authorized snapshots must refresh or require review; agreed offline amounts are never silently changed. Live server currently has zero registered offline devices/submissions.

Search Products/Customers by either current code or old code history. Stock history can search the short reference, product code/name or original transaction UUID. Historical invoices continue to show the immutable code printed when issued; that is intentional historical traceability, not a missed renumbering.

## Invoice Discount

The invoice form offers No Discount (0%), 25%, 30%, 35% and Other. Other requires a valid 0–100 percentage with at most two decimal places. Administrators and representatives already authorized to issue invoices may apply it; no extra cap/permission was invented. Custom input is server-validated independently of browser controls.

Approved policy **INVOICE_PERCENT_V1**:

1. Calculate each paid line using existing promotions. Promotional free units add stock demand, not a discountable selling amount.
2. Sum the payable paid lines and apply the chosen percentage. Round the invoice discount half-up to LKR cents using exact integer/string arithmetic.
3. Allocate that rounded amount proportionally to eligible paid lines using largest remainders; original input line order resolves ties. Product locks still acquire in stable ID order before balance locks.
4. Preserve each line's automatic discount and record the allocated invoice discount in its existing manual_discount_amount column. Header automatic/manual/combined totals and line net values reconcile exactly.
5. Save an immutable percentage, eligible base, amount and policy-version snapshot. Audit new issuance with percentage/amount/policy. Confirmation fingerprints include approved terms.

Examples, not seeded business records:

| Payable after promotions | Rate | Invoice discount | Final payable |
| --- | ---: | ---: | ---: |
| LKR 10,000.00 | 25% | LKR 2,500.00 | LKR 7,500.00 |
| LKR 251.00 | 12.5% | LKR 31.38 | LKR 219.62 |
| LKR 251.00 | 100% | LKR 251.00 | LKR 0.00 |

Live preview uses a debounced CSRF-protected server preview; it never issues an invoice. An explicit Review and Confirm & Issue are still required. Changing quantities/customer/discount invalidates the displayed confirmation. Cash applies only up to discounted total, excess is change, partial cash stays outstanding, credit permission/limits remain enforced, and voids reverse stock once and create only the actual received-money refund liability. Later payments do not apply the percentage again.

Details, 80mm/A4 documents and PDFs display the stored percentage/amount where the new policy exists; combined discount totals remain explicit. Sales/CSV totals use header totals; product reports use allocated line net values. A new reconciliation check compares discount snapshots, header/manual totals, line allocations and decimal SQL rounding. Historical invoices without the new snapshot retain their original financial values and presentation.

### Offline compatibility

New locally created drafts include the canonical rate, amount, eligible subtotal and INVOICE_PERCENT_V1 inside the signed body. PHP and JavaScript independently calculate the same allocation. Server validation rejects unsupported policy/tampered amounts and does not reprice. Legacy signed bodies without invoice-discount metadata keep their original zero invoice discount; the queue sends preserved bodies and never rewrites them.

Static cache version is **dharma-static-v13-1**. The financial domain and app assets use the explicit invoice-percent-v1 query revision together with the updated shell. The same eleven-file static allowlist remains; private reports/documents/APIs are not cached. No skipWaiting or vault deletion was added. Save/export pending records if appropriate, close all app tabs, then reopen to apply a waiting worker. Never clear encrypted storage to update. Snapshot expiry, device registration/revocation, 15-minute locking and unresolved-record retention are unchanged.

## Live filtering

The shared enhancement targets only list GET forms; business POST forms are never auto-submitted. Text inputs debounce for 300 ms; named dropdown/date changes apply immediately when valid. Previous requests are aborted and their generation checked, even when a delayed response ignores abort. Results update from the same authenticated server route without a page reload; active typing focus/caret and form listeners survive replacement. Wide tables are re-enhanced for independent scrolling.

Filtering resets page to one. Pagination retains the query; URLs update using history, refresh/bookmarks remain valid, and Back/Forward reload the corresponding server view. Reset links reload the unfiltered view, rebuilding dependent controls. Native submit buttons remain functional without JavaScript; they are hidden only after successful enhancement and reappear on update errors. Incomplete/reversed report dates do not issue a request. Colombo report calculations remain on the server. Existing visible-row report search is rebound after partial updates.

Covered existing forms:

- Products and Customers, including search/status/category/type and dependent district/town IDs.
- Inventory and stock history; representative lists, representative-stock summaries and transfer history.
- Promotions; invoice history with existing payment/outstanding filters; assigned-customer filters where present.
- All report tabs, including collections, receivables and movements.
- Admin offline review now has search/status filters with parameterized SQL and preserved pagination. The devices section is explicitly not filtered by sale filters.

District changes clear incompatible towns before filtering; the filter waits for town lookup completion. Typing inside district/town option-search controls searches lookup options rather than incorrectly triggering a customer-list request. Existing invoice and offline customer/product option searches remain immediate and local. No unsupported sorting or new standalone payment module was invented.

## Database/backup evidence

Only additive schema migrations **015_short_business_codes.sql** and **016_invoice_discount_snapshots.sql** were applied. Existing deployed SQL files retain checksums. Schema is 56 tables/53 triggers/16 applied migrations. No HTTP migration path exists. Runtime credentials remain restricted DML-only and private.

Verified pre-change backups (all preserved):

| Before | Private backup directory | SHA-256 of application.sql |
| --- | --- | --- |
| Migration 015 | phase12-20261111164458 | a5f2fdace08a85a6aaa23a474fecb4f0e5790fdf99357bad24924abea9c96288 |
| Migration 016 | phase12-20261111165207 | 17fee74faf137238364b5796a88e6ebd91f39f9d96b5e1acbf7b65dfeebb4a9d |
| Reviewed data migration | phase12-20261111171030 | d4708215511b7efac18ff8b4c33f0f38e381ec8b55c05af71fa20e21435b6272 |

Directories are under `C:\mariadb-clean\ayurveda-phase1-local\private\`; each has its verification.json and fresh isolated restore. Before the approved code migration, the 56-table restore matched 201 rows, 53 triggers, 16 checksums and four negative constraint tests. Source trigger definers/backup bytes were not altered; portability changes occur only in a separate restore import copy. Do not restore an old backup over the live database as a rollback shortcut.

Post-change backup and isolated restore also passed: `phase12-20261111172213/application.sql`, SHA-256 `533dc0e82c4d4335fa22dcfb54717088f1c84fdf0f1b3bb46dedca61d83a8c2d`; 56 tables, 219 rows, 53 triggers, 16 checksums and four negative constraint tests. Live reconciliation passed with the same three existing invoices and zero refunds due. Both live listeners remain 127.0.0.1 only.

## Files changed

- Schema: migrations 015/016; BusinessCode allocation; Products, Customers, Representatives, ProductStock, RepresentativeStock and Promotions creation/search/reference projection.
- Financial: InvoiceDiscount; PromotionCalculator exposes its existing exact integer helpers; Invoices, OfflineSales, Reports, HealthController and verify-invoices; discount form partial, invoice/detail/document views.
- UI: product/customer create, product-stock/history, representative/promotion and offline-admin views, shared footer; new live-filters.js and invoice-discount.js; workspace/reports/location-filter enhancements; offline domain/app/shell/service worker.
- Explicit maintenance: preview-short-code-mapping.php and apply-reviewed-code-map.php; private execution receipts.
- Tests: billing-enhancements.php, short-code-concurrency.php, invoice-discount-domain.js; existing phase12/offline/concurrency/browser/installability tests and private-config CLI dispatchers.
- Documentation: this report, AGENTS.md and design amendment. No XAMPP/global/WordPress/recovery changes.

## Verification results

All synthetic business/financial test data was confined to `dharma_sales_phase9_test` or the explicitly named isolated restore. No fictional business transactions were posted to the application schema.

| Suite | Passed |
| --- | ---: |
| Foundation / database constraints | 12 / 16 |
| Products / customers / locations | 69 / 91 / 91 |
| Historical inventory / product stock | 124 / 59 |
| Representatives / promotions / invoices | 262 / 121 / 170 |
| Inventory / rep / promotion / invoice contention | 7 / 11 / 11 / 59 |
| Discount/code financial workflows, actual PDF, reconciliation and history search | 100 |
| Distinct-token concurrent code creation | 2 |
| Legacy/new signed offline cases | 105 |
| Concurrent sync baseline / discounted race | 7 / 9 |
| Report foundations / calculations / browser | 48 / 81 / 42 |
| Complete isolated workflow / final layout-live filtering browser | 33 / 585 |
| Encrypted PWA browser including actual signed 25% draft and retries | 26 |
| Authentication / documents / document browser | 305 / 31 / 33 |
| JavaScript unit tests | 38 |
| Public installation metadata/assets | 21 |

Final PHP lint passed for 122 files; JavaScript syntax passed for 31 files. The last product form change was separately linted, and the final filter validation-message change passed JavaScript syntax. The database-unavailable simulation still returns sanitized HTTP 503 without stopping MariaDB.

Representative check counts include accumulated isolated fixture cohorts. Browser layout matrix covers 320, 360, 375, 390, 414, 768, 1024 and 1440 px. It verifies server-backed live previews, debounce/partial results, delayed-response rejection, focus retention, dropdown queries, independent table scrolling and role navigation. Existing server tests cover combined location filters, date/report boundaries and access scopes. Actual Android touch/installed-PWA and physical printer tests remain NOT TESTED; no mobile HTTPS endpoint has been enabled.

All test failures encountered while building were corrected before final runs: an initial dry-run mapping used the wrong inventory timestamp column (read-only query failure), and an isolated discounted-race test-harness string replacement needed correction. No failed production financial posting was attempted. Remaining limits are deployment/runtime/real-device prerequisites from Phase 12; the requested enhancements do not authorize production deployment.

## Local verification URLs

- Products: http://127.0.0.1:8088/admin/products
- Customers: http://127.0.0.1:8088/admin/customers
- Inventory / stock history: http://127.0.0.1:8088/admin/inventory and /admin/inventory/history
- New admin invoice: http://127.0.0.1:8088/admin/invoices/new
- New representative invoice: http://127.0.0.1:8088/rep/invoices/new
- Reports: http://127.0.0.1:8088/admin/reports
- Representative offline workspace: http://127.0.0.1:8088/offline/index.html

Use real live records only for actual authorized business entries. For test invoices use the dedicated disposable environment. Stop before deployment or unrelated features.
