# Phase 11 preparation — awaiting reporting definitions

**Historical preparation record:** the owner subsequently approved these definitions. This document is superseded by `docs/phase11-reports.md`; its pending/not-enabled statements below describe the earlier preparation stage.

Phase 11 is authorized, but is **not complete or enabled**. No report routes have been wired into `public/index.php`, and no Reports navigation has been enabled. Existing production invoice posting, synchronization and printing are unchanged. No migration or production database write has been performed.

The project rule in `AGENTS.md` states: “Default proposals still need owner approval before business implementation.” A pending owner question requests confirmation of these report semantics before financial calculations are implemented:

1. Weeks run Monday–Sunday using Asia/Colombo dates.
2. Gross issued sales means pre-discount totals of non-void invoices; net sales excludes voids. Void counts remain separately identifiable.
3. Collections mean posted amounts applied after change, including collections on invoices subsequently voided, whose cash remains a liability rather than a physical refund.
4. Receivables, refund liabilities and stock are current snapshots, distinct from period activity.
5. Product filters select matching invoices for whole-invoice financial totals, with explicit labeling; product-sales rows include only matching lines. No invented allocation of payments/debt to products.
6. Actual physical refunds are unavailable because no authoritative refund-posting source currently exists; never infer refunds from Refund Due.

Inspected sources: existing invoice/item snapshots, payment allocations and receipt/change records, immutable void/refund liabilities, product-only warehouse/representative balances and movements, offline operations/reviews, location lookups, document controller and local PDF renderer. Device-local drafts that have not reached the server cannot be counted by a server report and must be identified as unavailable.

Prepared but not activated:

- `app/Services/Reports.php`: fresh account/role checks, scoped customer/representative filter validation, existing location-table lookup/paging, current-assignment option scoping, UTF-8 CSV formula protection. Financial report calculation method intentionally remains unimplemented pending the owner answer.
- `app/Controllers/ReportController.php`: GET-only route definitions, private response headers, validated CSV response structure; not wired into the application.
- `app/Views/reports.php`, `public/assets/js/reports.js`: draft responsive report filters/table view; not reachable by HTTP.
- `app/Views/customer-location-filters.php`: allows an explicitly supplied lookup URL while retaining the existing Customers default.
- `tests/report-foundations.php`, dedicated-test CLI command `report-foundations`.

Verified in this preparation session:

- Live app-only read-only invoice/payment/stock integrity and offline integrity checks passed.
- Existing Phase 10: 31 document checks and 33 actual browser checks passed, including real PDF generation.
- Phase 9: 97 isolated offline service checks and 7 successful concurrent synchronization checks passed.
- New report foundations: 48 scoped filter, lookup, CSV, Colombo-boundary and all-table no-write checks passed in `dharma_sales_phase9_test` only. Synthetic fixtures never entered production.
- Six existing customer-location filter UI checks passed. All six changed/new PHP files passed lint; new JavaScript syntax passed.

Next: obtain the pending answer, implement read-only financial/stock report queries and summaries, integrate the routes/navigation, add complete financial/CSV/permission/browser tests, run all Phases 1–10 regressions and finish the Phase 11 report. Proposed dashboard paths are `/admin/reports` and `/rep/reports`; these are **not available yet**. Stop before Phase 12.
