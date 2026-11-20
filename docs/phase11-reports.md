# Phase 11 — Reports & Business Analytics

Admin: **http://127.0.0.1:8088/admin/reports**. Representative: **http://127.0.0.1:8088/rep/reports**. Sign in using an existing account. Both dashboards link to Reports. No fictional business data was added to the application database.

## Reports and exports

Each report is available under `/admin/reports/TYPE` and `/rep/reports/TYPE`, except company reconciliation, which is admin-only. Append `/csv` before the query string to export the same authorized report.

| TYPE | Contents |
| --- | --- |
| overview | Period gross/net/credit sales and posted/cash-method collections; current receivables, overdue balances, refund liabilities and warehouse/rep units; server-known pending/conflict counts. Screen includes a sales trend table; overview CSV exports the summary metrics and actual-refund availability. |
| sales | Daily, Monday-based weekly or monthly gross, discounts, net, cash/credit split, paid/free units, finalized and separately identifiable void counts. |
| products | Product/SKU/name snapshots, paid/free quantities, gross, discounts and net for matching invoice lines. Snapshot name/SKU changes remain separate rows, never silently relabeled using current catalogue names. |
| customers | Customer period purchases, last purchase dates, current scoped debt/overdue debt. Admins also see configured limits and full current company credit usage/remaining. Purchase history links to existing authorized invoice history. |
| locations | District/town sales using existing location IDs and lookups. Unmapped legacy addresses are explicitly identified. |
| representatives | Period cash/credit/net sales, collections attributable to owned invoices, outstanding debt, paid/free units, current assigned stock, server-known pending/conflict counts. Warehouse invoices retain their admin numbering owner. |
| collections | Posted payment entries or grouped totals by local posting day, customer or actual collector, with separate collected-on-voided-invoices amounts. Entries retain invoice owner, method, validity and refund-liability status. |
| outstanding | Current unpaid/partially paid invoices, paid/outstanding amounts, due dates, overdue days and overdue amounts. |
| refunds | Current void refund liabilities and void timestamps. Actual refunds: **Unavailable — refund posting not implemented.** |
| inventory | Current product-only warehouse/rep quantities, current Products selling price and estimated selling-price value. No costs, batches, suppliers or expiry model. |
| movements | Existing additions/opening entries, adjustments, allocations, rep returns, invoice deductions and reversals; signed quantities, after-balances, timestamp, reason and responsible user. |
| offline | Server-received operation UUID, representative, status, conflict code and submitted provisional cash; never counted as confirmed collections until an accepted invoice/payment exists. |
| reconciliation | Twelve company-wide invoice/payment/refund/offline/stock integrity checks with violation counts and PASS/INVESTIGATE. Admin only. |

Examples:

```text
http://127.0.0.1:8088/admin/reports/sales?period=month&group=day
http://127.0.0.1:8088/admin/reports/sales/csv?period=custom&from=2026-11-01&to=2026-11-30&group=week
http://127.0.0.1:8088/admin/reports/collections/csv?period=today
http://127.0.0.1:8088/admin/reports/inventory/csv
http://127.0.0.1:8088/rep/reports/representatives
```

CSV output has stable ordered field names, UTF-8 BOM, exact decimal strings and formula-injection protection, including leading whitespace/control characters. Potential formula cells, including signed negative movement values, are prefixed with an apostrophe. Filenames include report type and selected dates. Current-snapshot exports also carry an as-of timestamp; their balances are not historical balances. Export is limited to 10,000 rows and refuses larger results rather than silently truncating. Narrow the filters if needed. Screen results paginate at 50 rows; pagination retains filters. Overview CSV contains summary metrics; use Sales CSV for the trend rows.

## Owner-approved financial definitions

- Asia/Colombo controls all calendar boundaries. Today/yesterday use local days; This week covers Monday–Sunday; This month covers the full current calendar month. Custom dates are inclusive. Payment/stock timestamps use half-open UTC intervals converted from local midnight, avoiding timezone-table dependencies.
- Gross issued sales are pre-discount totals of non-void finalized invoices. Net sales are final totals after discounts, excluding voids. Cash and credit are included. Free quantities are separate and carry no invented paid revenue. Accepted offline sales appear once through their authoritative invoice; pending/rejected/unsent sales do not enter revenue.
- Collections use posted payment allocation amounts and actual server payment posting dates, not invoice dates or provisional device cash dates. Change is excluded. Collections later owed back following a void remain visible as collected cash with a refund liability, not earned sales revenue. Cash-method collections and all-method posted collections are separately labeled.
- Outstanding balances, overdue balances, refund liabilities and stock are **current snapshots** regardless of the selected period. Due today is not overdue; overdue starts the next Colombo date. Financial snapshots still respect current role and selected invoice-cohort filters. Every view shows an as-of time, and snapshot CSVs include one.
- Product/category financial filters select whole invoices. The screen explicitly states **“Whole-invoice totals for invoices containing the selected product/category.”** Product-sales rows use matching lines only. Payments/debt are not proportionally invented for individual products. Search selects an invoice cohort using invoice/customer/SKU/product text; it is not a financial allocation rule.
- Actual physical refunds have no authoritative posting source in the implemented application. They are displayed as unavailable, never inferred from Refund Due and never represented as zero refunds.
- Admin company credit usage includes all current non-void debt for the customer, ignoring product/period filters. Other customer balance columns follow the selected cohort. Representatives do not receive company-wide credit usage from other representatives' invoices.

## Filters and scope

Presets, custom dates, trend grouping, representative, customer, product, category, district, city/town, current invoice payment status and search are available. District/town controls reuse the existing searchable dependent-dropdown script and lookup tables, including administrator-added towns. Town-only filters use unique IDs. District changes clear incompatible towns. Reset Filters removes the whole query; date edits select Custom range automatically. A second search box filters currently displayed rows without changing server totals.

Filter applicability is explicit on screen:

- Financial reports: date filters affect period sales/collections; current balances ignore dates. Location grouping/filtering uses **current** customer lookup IDs, not reconstructed historical addresses. Invoice ownership and current customer assignment define representative access.
- Stock: representative/product/category/search only. There is no stock-to-customer/geography/payment relationship to infer. Current stock ignores dates; movements use movement timestamps. A transfer has equal/opposite location legs; absolute legs must not be summed as new company stock. Server stock excludes device-local provisional reservations.
- Offline: receipt-period/representative/customer/product/category/current geography filters; search matches operation UUID or representative name. Invoice payment status does not apply to provisional operations.
- Reconciliation: company-wide integrity checks deliberately ignore all filters.

All report/lookup/CSV endpoints require authentication and fresh active-role checks. Representatives cannot select another representative or an unassigned customer, cannot use admin endpoints, and cannot run company reconciliation. Their invoices must meet the existing current-assignment AND ownership policy, even for historical activity. Current own stock remains available independently of customer assignment. No cost price, password, signing key, device body or private configuration enters representative report payloads.

## Read-only implementation and safety

`app/Services/Reports.php` reuses existing source tables and performs independent payment/item pre-aggregation to avoid join fan-out. Queries use native PDO parameters and SQL DECIMAL arithmetic, with exact string formatting in PHP; no binary floating-point money calculations. Each report runs in a consistent read-only repeatable-read transaction. Pagination/counts/summary values share the same snapshot.

`ReportController.php`, `app/Views/reports.php`, `public/assets/js/reports.js` and dashboard/front-controller integration provide the UI and protected CSV downloads. The existing location partial accepts a report lookup URL while retaining the original Customers API default. `/admin/reports/towns` and `/rep/reports/towns` read the existing geographic lookup tables; neither creates towns nor bypasses admin-only town creation.

Responses use private no-store headers. No report data or CSV is added to the service-worker allowlist. All report actions are GET-only and do not invoke invoice posting, payment creation, stock posting or sync mutation. Existing logout CSRF protection remains. Phase 10 layouts, PDF renderer, invoice numbering and finalized values were not modified.

**No database migration or production data modification was necessary.** Migrations 001–014 retain verified checksums, with 53 tables and 49 triggers. No backup replacement/deletion or new pre-migration backup was required because no migration occurred. WordPress, recovery files, other business databases, XAMPP configuration and ports were not changed. Both live listeners remain `127.0.0.1` (8088 and 3309).

## Verification

Dedicated synthetic tests run only in existing `dharma_sales_phase9_test`, never the real application database:

```powershell
C:\xampp\php\php.exe storage/phase1-local/phase9-test.php report-foundations
C:\xampp\php\php.exe storage/phase1-local/phase9-test.php reports-browser
node --test tests/reports-ui.js
```

The new suite covers exact cash/credit/partial/free/void totals, whole-invoice versus matching-line semantics, current balances versus period dates, historic day/week/month buckets, overdue days, Colombo midnight posting boundaries, provisional conflict exclusion, full current credit usage, customer reassignment, pagination/all-page exports, empty authorized business cohorts and all-table no-write hashes. Historical source fixtures are appended only inside rolled-back isolated transactions; immutable records/triggers are never changed or disabled. Real-browser tests cover every report/CSV, tampered permissions, invalid dates, mobile width, location controls, navigation, actual CSV formula neutralization and private headers. HTTP reporting preserves financial and stock hashes; authentication legitimately changes session/audit records.

Live restricted-runtime verification executed all thirteen report queries successfully and all twelve reconciliation checks passed: metadata coverage, invoice snapshots/totals, posted payment allocation totals, receipt/change linkage, no overpayment, void debt/refund liabilities, paid/free stock deductions, exact void reversals, transfer conservation, accepted offline matching and both warehouse/rep ledger balances. Existing live invoice/offline integrity checks also passed.

Final verification on 2026-11-09:

| Test group | Passed |
| --- | --- |
| New report calculations, historical boundaries, credit usage, pagination, reassignment and no-write checks | 80 |
| Real report browser, CSV, mobile, lookup and permission checks | 42 |
| Report filter/lookup/CSV foundations | 48 |
| JavaScript unit regressions, including two new report UI tests | 30 |
| Foundation / database constraints | 12 / 16 |
| Products / Customers / locations | 69 / 91 / 91 |
| Legacy inventory / simplified inventory | 124 / 59 |
| Representatives / promotions / invoices | 155 / 121 / 167 |
| Inventory / representative / promotion / invoice contention | 7 / 11 / 11 / 56 |
| Authentication / sessions / route protection | 305 |
| Offline service / successful sync concurrency / real encrypted PWA browser | 97 / 7 / 25 |
| Phase 10 document service / real print-and-PDF browser | 31 / 33 |
| PHP lint | 102 application/test files plus both CLI wrappers |
| JavaScript syntax | 24 files |

The former authentication assertion that Reports was an unfinished placeholder was updated to assert its implemented navigation and protected report/CSV routes; the complete authentication suite then passed. No production posting or synchronization rule was changed to make tests pass. Representative suite counts depend on the accumulated isolated fixtures; the value above is the actual regression-run count. Existing migration repeatability and checksum checks passed.

## Source-data limitations

Device-only encrypted drafts are invisible to the server until submitted; zero server pending operations does not prove no unuploaded device sales. Pending/conflicted counts reflect server-received operations within the selected receipt period, not a complete device backlog. Conflicts are RECEIVED operations with error codes; rejected records remain separate and are not revenue. No automatic offline-policy changes or repricing were introduced.

Historical location reports follow current customer location IDs; original invoice address snapshots remain intact in invoice documents. Selling-price stock value is an estimate, not cost/profit accounting. There is no actual physical-refund report or profit report without authoritative source data. Browser table search affects only the current page; CSV uses server filters. No report PDF was added; on-screen reports and CSV are the Phase 11 outputs. Physical printer/Android/share hardware limitations from Phase 10 remain unchanged. Stop before Phase 12.
