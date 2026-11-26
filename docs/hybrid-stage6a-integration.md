# Stage 6A - isolated Hybrid integration and verification

26 November 2026. **Isolated integration verified; not production-ready or activated.**

## Branch, path and baseline

- Integration branch: `hybrid-promotion-v1-stage6a-integration`.
- Exact worktree: `C:\xampp\htdocs\dharma chemical sales app\storage\hybrid-stage6a`.
- Parent/HEAD: `1f6385c` (Customer Profile), retaining Dashboard commit `fae2ebf`.
- Hybrid source reviewed: `storage/hybrid-stage1`, branch `hybrid-promotion-v1-stage1`, baseline `80331eb`, including its untracked Stage 1-5 files and complete Stage 5 readiness report.
- No commit, staging, branch merge, main edit, push or deployment was performed. Git worktree/branch registration was the only shared Git metadata operation.

Main and the original Hybrid worktree remain preserved. Main still has the same two modified documentation files and untracked Hybrid policy document found at preflight. No hard reset, clean, whole-worktree copy, UI-commit cherry-pick, original-data deletion or financial activation was used.

## Exact integration inventory

`docs/hybrid-stage6a-inventory.json` is the precise path/SHA-256 inventory. It records 83 selectively reviewed Stage 1-5 application, configuration, migration, documentation and test files, plus the Stage 6A additions/adjustments. Source hashes and final integrated hashes are separate; the inventory itself is excluded from its own digest to avoid a circular hash.

The nine reviewed existing Hybrid-modified files transferred were:

- `app/Controllers/InvoiceController.php`
- `app/Controllers/InvoiceDocumentController.php`
- `app/Controllers/ReportController.php`
- `app/Services/InvoiceDocuments.php`
- `app/Services/Invoices.php`
- `app/Services/OfflineSales.php`
- `app/Services/Reports.php`
- `app/Support/LocalPdf.php`
- `app/Views/invoices.php`

The new Hybrid services/controllers/views/assets, disabled configuration, migrations 025-027, seven prior-stage policy/reports and selected synthetic test helpers are enumerated individually in the JSON inventory. No `.env`, account file, test database, backup, SQL dump, browser profile, screenshot or generated test evidence was transferred as source. Migration execution/007-repair/recovery runners were deliberately excluded from the transfer. The preserved Stage 5 synthetic fixture was imported separately into new ignored test storage as described below, not packaged as integrated source.

Stage 6A added these test sources:

- `tests/hybrid-stage6a-fixture-restore.php`
- `tests/hybrid-stage6a-readers.php`
- `tests/hybrid-stage6a-browser.php`
- `tests/hybrid-stage6a-browser.js`
- `tests/hybrid-stage6a-http.php`
- `tests/hybrid-stage6a-http.js`

Stage 6A also extended `tests/hybrid-ui-bridge.php` and `tests/hybrid-ui-router.php` for real dashboard/customer controllers and role authorization. Existing parent `tests/http-router.php` serves the unmodified real front controller during the full HTTP test.

## Conflicts and dependency resolutions

### Stored sale-time overlap

`InvoiceDocuments.php` was the only file modified both by the Hybrid patch and main since the Hybrid baseline. A file-level three-way content merge retained main's `withSaleTime()`/`sale_time_utc` support and added Hybrid document dispatch and return snapshots. It completed without conflict markers. The source transfer checked that the other eight modified files still matched their common baseline on main before applying their reviewed changes.

Main's company snapshot phone, legacy invoice template, invoice-document stylesheet and original date/time layout were retained. They were not overwritten by older Hybrid baseline files.

### Isolated connection injection

The dashboard and customer controllers contained direct default-factory connection calls. To test their actual controller/view paths without accidental default-port access, integration adds optional server-side PDO injection:

- `app/Controllers/AuthController.php`: optional dashboard connection used for reports and representative stock.
- `app/Controllers/CustomerController.php`: optional billing/location connection used for customer-related reads.

Existing callers/default behavior remain valid. No view or design file changes were required. The combined reader suite forbids loading the default database factory and verifies these controllers against the physically proved fixture. The full HTTP test separately exercises the normal factory with explicit child-process test connection variables.

### Document CSP compatibility

Review of the real front controller found a compatibility issue not exercised by the old CLI bridge: its `style-src 'self'` header blocks Hybrid's trusted inline print CSS. This is resolved only for authenticated Hybrid HTML documents using a random per-request style nonce. The header and sole trusted style block must agree; embedded fonts remain permitted through `font-src data:`. Scripts remain same-origin, forms and framing remain denied. No global CSP, shared CSS, financial route, posting gate or HTTP/CLI writer guard was weakened.

This scoped change affects:

- `app/Controllers/InvoiceDocumentController.php`
- `app/Services/InvoiceDocuments.php`
- `app/Services/HybridDocuments.php`
- `app/Views/hybrid-document.php`

The real application HTTP test checks the nonce/header match, actual 58mm content width, authenticated PDF and private report download. Legacy document rendering retains its existing template/styles.

## Protected designs and source preservation

All eight approved design/shared files remain byte-identical to `1f6385c`:

```text
app/Views/dashboard.php
app/Views/dashboard-activity.php
app/Views/dashboard-metrics.php
app/Views/dashboard-trend.php
app/Views/customer-profile.php
app/Views/customers.php
public/assets/css/workspace.css
public/assets/i18n/si-LK.json
```

The same integration-baseline hash check protects `CompanyLanguages.php`, legacy `invoice-document.php`, `invoice-document.css` and migration 007: **12 protected files unchanged**. The eight design files and three parent printing files also match main byte-for-byte. Migration 007 has a pre-existing checkout line-ending difference: main SHA-256 `161effa594c58f45223bf68161ba3a6f3b623dc34bafff0a33080008a337fa9c`, integration SHA-256 `f46167f2b163ed69b351e61cb0bebfc30804db0713d1e22973f6e96d7a94800a`. Normalized logical content matches and Git reports no migration change. Neither copy was edited or normalized. Any later migration preflight must verify deployed raw bytes separately; this difference was not repaired in Stage 6A.

Main and the original Hybrid source were not edited. Protected dashboard/profile screenshots were inspected in the combined application, including phone layout. The existing fixed navigation remains the approved design; full-page captures can show it at the capture viewport position.

The paused Invoice UI redesign was not resumed. Only previously verified Hybrid checkout/returns/document additions were integrated. The new document nonce and test injection do not change dashboard/customer markup or styling.

## Disposable database isolation

New instance, not either earlier server:

```text
Host:      127.0.0.1
Port:      63824
Schema:    dharma_sales_hybrid_a817c38f7ca64a41ad8f728f29484386_test
Run:       storage/stage2c-284cc436a7c447aa9465655d5adb24aa
Data:      <integration worktree>/<run>/data
Runtime:   hybrid_rt_e325cdaf97@127.0.0.1
Grants:    USAGE globally; SELECT, INSERT, UPDATE, DELETE on this schema only
```

Before use, the harness checked exact port/data directory, generated run identity, native prepares and schema-only runtime grants. Both forbidden ports are rejected before connection. Operational schema access and runtime DDL are denied. No operational configuration, credentials, data or backup was used. The active IDE login-file tab was not accessed.

A **retained Stage 5 synthetic backup** was hash-verified and streamed into this newly bootstrapped disposable schema. Only the bootstrap identity table existed before import; a populated application schema cannot be overwritten by the helper. Test-only trigger definers were rebound in memory to this new instance's generated migration account; the immutable original backup was rehashed afterward. Old credentials, backup files and evidence were not copied into the source integration. New generated test credentials/data/evidence remain ignored and unserved.

This restores an already-applied **compatibility fixture**, including the documented earlier 007 amendment. It is not a pristine installation and is not presented as one. **No migration runner or migration script was executed in Stage 6A. Migration 007 was neither changed nor run.** Migrations 025-027 were integrated as source; their already-applied fixture schema/checksums were verified, not reapplied operationally.

025/026/027 source SHA-256 values remain:

```text
025 662054eb3025354eff03c9da928e3b618fa433a4ca69a8b6632342924d45f2c1
026 912b831ea2d3674bee50ef7b6dfd3852683fec2b00c921c5f419f5457a137d5f
027 1e00ad4388f60c4b130d560d6cbcebb83489e8d43c9033bfc4d6e54faca24fac
```

Synthetic configuration/control overrides were limited to the physically verified tests and audited fixture events. Source configuration stays disabled, all operational allowlists empty and financial posting hold true. Final closeout disables all disposable controls and shuts down only this new instance, retaining its data/artifacts. Old test data and earlier evidence remain untouched.

## Combined verification results

| Suite | Result and evidence |
| --- | --- |
| Combined dashboard/profile browser | **28 checks**: admin and both rep dashboards, populated metrics, approved navigation, actual profile billing/CSRF fields, phone overflow checks, linked invoice/receipt readers and authorization |
| Combined controller/read services | **30 checks**: default factory forbidden, real dashboard/customer controllers, stored sale time, legacy and Hybrid documents, debt/refund, stock/financial reconciliation, zero business writes and protected hashes |
| Actual `public/index.php` HTTP + real login/session | **12 checks**: anonymous redirect, admin/rep login, dashboard/profile, admin-only profile, nonce-styled 58mm receipt, authenticated PDF, private stock CSV and disabled Hybrid preview rejection; no invoice posted |
| Hybrid checkout database | **17 cases / 141 checks**, including review fingerprints, real competing commands, immutable evidence, stock, payments and rollback |
| Hybrid returns/custody database | **157 checks**, including A1-E1, repeated positions, concurrency, custody supplementation/conversion, manager dispositions, residual void and complete rollback |
| Integrated checkout browser | **56 checks**, plus 3 exactly-once database backstops |
| Integrated return browser | **13 checks**, plus credit/stock backstop |
| Integrated documents/reports browser | **73 checks**, including 18 bilingual layouts, scoped access, disablement visibility, private PDF/CSV and phone rendering; rerun after nonce fix |
| Integrated document/report service | **64 checks**, 18 immutable bilingual HTML cases; rerun after nonce fix |
| Financial/report oracle | **35 checks**, exact S/P/R/I/net, product cohorts, payments, linked credits/voids and deduplicated stock reconciliation |
| Existing controller integration | **28 checks** |
| Legacy invoice regression | **26 checks**, including full-original void, stock, Refund Due and replay |
| PROMO_CHOICE_V1 regression | **36 checks**, source pilot/hold configuration preserved |
| Pure promotion engine | **20 cases / 13,533 assertions** |
| Pure integration foundations | **17 cases / 2,635 assertions**, including unsupported offline payload rejection and missing physical proof |
| Pure return/custody | **34 checks** |
| Existing historical-return arithmetic | **6 checks**, full posting remains blocked separately |
| General foundations | **12 checks** |
| JavaScript domains | Hybrid UI **5**, invoice discounts **4**, mixed-oil **3**, offline reconciliation/encryption **8** |
| Syntax/whitespace | Selected PHP source/tests and new browser JS passed; tracked diff whitespace check passed |

The initial reader-test JSON baseline had a UTF-8 BOM from the evidence writer; it was corrected and the reader suite passed. A closing cross-check also detected the existing 007 line-ending difference described above; its logical-content and unchanged-baseline checks passed without editing the migration. No financial defect or mutation resulted. No remaining unexplained test failure is hidden in these totals.

The normal full HTTP application test uses the actual front controller, default factory and authentication with explicit child-process environment variables for the new proved schema. There is no `.env` in the integration worktree. The full application test cannot select a forbidden port. Active Hybrid synthetic issue/return browser suites retain the existing test-only HTTP-to-CLI bridge because the financial writer intentionally requires CLI/physical proof. This distinction is deliberate and no production HTTP posting readiness is inferred.

## Remaining blocker matrix

| Area | Stage 6A result | Remaining boundary |
| --- | --- | --- |
| Dashboard + Customer Profile + Hybrid source combination | PASS in isolated integration | Approved designs and parent print dependencies preserved; branch still uncommitted/unmerged |
| Combined financial/stock readers and reports | PASS on synthetic fixture | Operational preflight/data acceptance not authorized |
| Hybrid issue/returns/authorization/legacy regression | PASS on physically isolated synthetic fixture | No genuine posting or production activation |
| Migration 007 | **BLOCKED / previously reproduced FAIL** | Known MariaDB 1267 mismatch remains. Not run, rewritten, repaired or bypassed in this stage. Pristine migration chain is not passing |
| Migrations 025-027 | PASS as reviewed source and existing fixture schema | No operational migration execution; no pristine-chain claim |
| Historical-return posting | **BLOCKED** | Existing suite references forbidden port 3309; neither executed there nor retargeted to it. Pure arithmetic passed; full posting acceptance remains outstanding |
| HTTP financial readiness | **BLOCKED, intentionally guarded** | Read-only application/auth/document/report HTTP passed. Financial writer's CLI/physical guard and source holds remain intact; actual deployment bindings/configuration not approved |
| Sinhala | **PARTIAL acceptance / BLOCKED for full sign-off** | Bilingual document software checks passed. Full Hybrid UI localization/native financial wording acceptance remains incomplete |
| Physical printing | **NOT TESTED / NOT VERIFIED** | No actual supported Bluetooth printer, feed/cutter or printable-width acceptance |
| Production rollout/rollback | **NOT AUTHORIZED / not production-ready** | No commit, push, main merge, deployment, operational repair, gate activation or hold removal |

No Stage 5 blocker was silently resolved by using its fixture. The new evidence resolves the previously untested **isolated combined-source/UI** question and the real-front-controller document CSP incompatibility. It does not resolve pristine installation, historical posting, production financial HTTP, native language or hardware acceptance.

## Stop and review handoff

Review the JSON inventory and unstaged diff in this integration worktree. The owner can separately authorize a commit or subsequent remediation stage; none was inferred. Keep main's approved designs and all existing documentation edits intact. A later rollout must retain Hybrid linked-credit-aware readers and immutable history when actions are disabled; a blind pre-Hybrid rollback remains unsafe.

Work stops after Stage 6A isolated integration and verification. The disposable server and test HTTP/browser processes are closed at completion, data is retained, and no operational activation has occurred.
