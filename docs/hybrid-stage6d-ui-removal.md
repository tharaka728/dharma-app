# Stage 6D: discarded Hybrid Invoice UI removed

26 November 2026. This supersedes the prior Stage 6D UI acceptance/preview recommendations. Work is confined to isolated worktree `storage/hybrid-stage6d-ui`, branch `hybrid-promotion-v1-stage6d-ui`, HEAD `8477ddac2271114a1f74ff539924541eb4974df5`. No commit, push or merge.

## Removed (exclusive discarded-interface files)

- `app/Views/hybrid-invoice.php`
- `app/Views/hybrid-invoice-fields.php`
- `public/assets/js/hybrid-invoice.js`
- `public/assets/css/hybrid-invoice.css`
- `public/assets/css/hybrid-unified.css`
- `tests/hybrid-ui-domain.js`
- `tests/hybrid-ui-preview.ps1`
- `tests/hybrid-ui-browser.php`
- `tests/hybrid-stage6d-browser.js`
- `tests/hybrid-stage6d-issue.js`
- `tests/hybrid-stage6d-issue.php`
- `tests/hybrid-stage6d-preview-prepare.php`
- `tests/hybrid-stage6d-preview-safety.php`
- `tests/hybrid-stage6d-visual.js`
- `tests/hybrid-stage6d-visual.php`
- `tests/hybrid-ui-browser.js`

The removed views, scripts and CSS served only the discarded editor. The removed browser/domain tests and preview/issue runners exercised only that interface; authoritative financial tests remain. Some files were untracked Stage 6D additions and therefore do not appear as deletions in Git diff. No shared POS or production asset was deleted.

## Restored and preserved

- `app/Views/invoices.php` is exactly the checkpoint template minus the discarded checkout link. Its original create layout, pilot controls, admin source picker and legacy behavior remain in their authorized paths. Existing Hybrid snapshot readers and linked-return actions remain.
- `public/assets/js/pos.js` is restored to exact checkpoint bytes; original `pos.css` remains unchanged. No replacement UI was implemented.
- `InvoiceController` no longer registers `/rep/invoices/hybrid/new` or renders a Hybrid editor. Its worker allowlist drops `hybrid-create`. Eligible non-pilot GET/HTML checkout requests return HTTP 409 without an editor. Unversioned new-online-sale API requests remain rejected; no legacy fallback was introduced. Versioned Hybrid API review/issue and eligibility remain under their existing service gates.
- PROMO_CHOICE_V1, historical-return contracts, signed offline handling, Dashboard and Customer Profile are preserved. Source activation remains OFF, sale/return/custody posting OFF, financial hold ON, and operational database/binding allowlists empty. No database state was inspected or changed.
- All 83 recorded service/config/migration/protected-view/style/service-worker files match their pre-removal SHA-256 hashes. Engine rules, immutable snapshots, allocations, atomic writer, linked returns, migrations and safety guards were not edited.
- Shared returns/report translations and localization remain. The locale inventory/browser suites drop only discarded-editor coverage. Shared HTTP/CLI test harnesses still support returns, stored documents, Dashboard/Profile and financial API tests; their abandoned owner-preview mode returns unavailable before dispatch. Reusable controller/scope/financial tests remain, with removed-editor expectations updated.
- Old UI reports/inventories and ignored screenshots are historical evidence. The prior UI/readiness reports now carry superseded notices; no gallery or screenshots were generated.

## Verification performed without database connections

- `tests/hybrid-ui-removal.php`: 24 checks PASS (fake PDO reads only): removed routes/worker alias, eligible GET and HTML/API POST fail-closed behavior, inactive actor, preserved API/returns/admin routes, source holds and deleted assets.
- `tests/run.php`: 12 checks PASS.
- `tests/hybrid-invoice-foundations.php`: 17 cases / 2,635 assertions PASS, including snapshots, replay, rollback, strict routing, offline rejection and holds.
- `tests/hybrid-promotion-policy.php`: 20 cases / 13,533 assertions PASS.
- `tests/hybrid-return-domain.php`: 34 checks PASS.
- JS invoice-discount/offline domain suites: 12 tests PASS, including exact discount allocation and AES-GCM/reconciliation.
- PHP lint: 9 changed/new PHP files PASS. JS syntax: 7 changed JS files PASS. Original template/POS comparison and 83 protected hashes PASS. CRLF-aware whitespace check PASS.

Database-backed suites (including `tests/database.php`), genuine posting, migrations, deployment and activation were not run. No connection to 3306, 3309 or any database was made. Operational localhost main was not modified.

## Remaining integration blockers

Eligible new online representative checkout is intentionally unavailable (409) until separately approved integration directly into the ORIGINAL New Invoice page. It must not be activated or replaced by legacy checkout. Existing migration 007/raw-byte packaging and target-runtime preflight blockers remain unresolved; no release-readiness claim is made. Existing native-language/physical-print acceptance limitations remain historical and outside this removal.

Stop here. No new UI implementation is started or authorized by this removal.
