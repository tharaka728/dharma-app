# Stage 6C release-compatible isolated integration and acceptance

26 November 2026. **Isolated source integration and software verification completed. No operational deployment, activation or genuine financial posting is approved.**

## Location and exact changes

Worktree: `C:\xampp\htdocs\dharma chemical sales app\storage\hybrid-stage6a`.
Branch: `hybrid-promotion-v1-stage6a-integration`; HEAD remains `1f6385c`.
Stage 6A/6B reports, inventories, the pinned contract and HTTP/CLI prototype were reviewed first. Prior-stage files and uncommitted work were retained. No commit, staging, reset, clean, push, merge or main-source modification occurred.

Four existing files changed in Stage 6C:

- `app/Views/hybrid-invoice.php`: scoped display-language adapter and translated server-side no-JavaScript warning. The server-selected language is explicit, preserving the owner's English preview.
- `app/Views/hybrid-returns.php`: correct page language, existing localization assets, scoped adapter and server-side no-JavaScript warning. Form IDs, protocol values, actions and business controls are preserved.
- `app/Views/reports.php`: presentation-only translations for Hybrid linked-credit headings, mobile card labels, notes and unavailable-cash-refund wording. Other report types retain their existing translation path.
- `app/Controllers/ReportController.php`: only Hybrid linked-credit CSV headings use the scoped catalogue. Financial rows, keys, filters, permissions and calculations are unchanged.

New application/installation source:

- `app/isolated-bootstrap.php`
- `app/Support/HybridFreshInstaller.php`
- `app/Support/HybridIsolatedRuntime.php`
- `app/Support/HybridWorkerClient.php`
- `app/Support/HybridReportText.php`
- `bin/hybrid-fresh-install.php`
- `bin/hybrid-worker.php`
- `database/fresh-install/contract-v1.json`
- `public/hybrid-isolated.php`
- `public/assets/i18n/hybrid-si-LK.json`
- `public/assets/js/hybrid-localization.js`

New targeted tests:

- `tests/hybrid-stage6c-migrations.php`
- `tests/hybrid-stage6c-runtime-safety.php`
- `tests/hybrid-stage6c-http.php`
- `tests/hybrid-stage6c-http.js`
- `tests/hybrid-stage6c-router.php`
- `tests/hybrid-stage6c-locale-inventory.js`
- `tests/hybrid-stage6c-locale-browser.php`
- `tests/hybrid-stage6c-locale-browser.js`
- `tests/hybrid-stage6c-report-locale.php`

Reporting adds `docs/hybrid-stage6c-runtime.md`, `docs/hybrid-stage6c-acceptance.md`, this report and `docs/hybrid-stage6c-inventory.json`: **24 new files and 4 modified existing files**. The JSON inventory contains exact before/after hashes and is authoritative; its own digest is excluded to avoid circularity.

Final Git status: 12 tracked modified files and 118 untracked entries, including preserved prior-stage work. Only the four existing-file changes above belong to Stage 6C; earlier tracked modifications are preserved. Full final porcelain status is retained in ignored evidence. Main retains its original two modified documentation files and untracked Hybrid policy document.

All original migrations 001–027, normal `bin/migrate.php`/Migrator, original physical CLI guard, feature/pilot configurations, shared CSS, shared Sinhala catalogue, Dashboard and Customer Profile remain byte-identical to preflight. The existing default-deny preview router/bridge and stable-ID fixture correction are unchanged. No offline vault/draft/queue/passphrase data was accessed or modified.

## Explicit installer integration

The Stage 6B preparation is now integrated into an explicitly selected application CLI workflow. `bin/hybrid-fresh-install.php` requires `--new-isolated`, a verified disposable manifest and the exact versioned contract hash. It does not load `.env` or a default connection and does not enter the normal operational migration path.

Before DDL it verifies the physical server/schema/data directory/run identity, schema-only migration account, supported server version, every original raw SQL hash and the empty new installation. The only permitted initial application table is the bootstrap run identity. Existing/completed, populated, unknown and partially installed targets are refused by this entry point. Before preparation it verifies exact completed 001–006 journal rows, complete schema definitions and seed-data fingerprints. The separately journaled preparation changes only the empty `city_town` collation, then original 007 executes normally. Applying/applied semantics and immutable original checksums remain intact. No incomplete migration is marked applied.

The normal migrator remains unchanged and does not discover preparation files outside `database/migrations/`. Existing installations receive no silent compatibility DDL. The library retains the tested narrow recovery primitive for an exact journaled preparation interruption; the new entry command does not blindly resume partial installations. See `hybrid-stage6c-runtime.md` for selection, recovery boundaries and the exact command.

**77 integrated migration checks passed**, including both schema-default collations, original 1267 reproduction, repeatability, interrupted preparation recovery, interrupted-original-007 rejection, populated/unknown shapes, checksum/order failures and unjournaled changes. The explicit command also completed clean installation through original 027 on **two separate disposable MariaDB 10.11.16 instances**:

| Port | New schema | Default | Final journal |
| --- | --- | --- | --- |
| 63828 | `dharma_sales_hybrid_8d936be0b1b846d6952bfafd50419c69_test` | `utf8mb4_unicode_ci` | 28 applied rows: original 27 + preparation; original hashes match |
| 63830 | `dharma_sales_hybrid_4e16a82baabe4049bf32afcd72216c86_test` | `utf8mb4_general_ci` | Same successful chain and checksums |

These are **explicitly prepared clean installations**, not a claim that the unassisted original chain passes. The original chain still reproduces **1267 under both defaults** and leaves original 007 `applying`. The existing-install rejection test confirmed the entry command leaves its journal unchanged.

Raw 007 hashes remain:

```text
Integration: f46167f2b163ed69b351e61cb0bebfc30804db0713d1e22973f6e96d7a94800a
Local main:  161effa594c58f45223bf68161ba3a6f3b623dc34bafff0a33080008a337fa9c
```

The pre-existing line-ending difference was preserved. Required operational preflight: compare actual deployed raw bytes against the existing deployed journal before authorizing any release. No operational journal/schema/data was inspected. Other MariaDB/MySQL versions are not accepted by the pinned installation contract without separate validation.

## Integrated isolated HTTP financial runtime

The application entry `public/hybrid-isolated.php` now invokes application modules and `bin/hybrid-worker.php`, rather than a test-controller prototype. The normal front controller, Apache/XAMPP configuration and owner-preview server are unchanged. Runtime selection requires a private `HYBRID_ISOLATED_PROFILE`; absent/mismatched configuration fails closed before any default connection.

The ingress uses real form login, authenticated sessions, current roles, CSRF, strict online Hybrid dispatch and bounded payloads. It supports actual Hybrid UI/review/issue/returns plus scoped existing invoice history/details, documents and report readers. It does not fall back to legacy/offline/stock/payment financial writers. The worker independently verifies signed job identity/expiry, current account/password stamp, route permissions, exact disposable database identity and DML-only grants. Existing authoritative resolver/bindings, review fingerprints, feature/financial holds, transactions, immutable snapshots, audits and exactly-once commands are unchanged. The original CLI physical guard remains mandatory.

Private IPC is outside `public/`. Test setup protected each runtime DACL before writing secrets, removing inherited broad Users/Authenticated Users access and allowing the service identity, owner and system administrators. Runtime-only database credentials and signing keys are separate private files; keys are not exposed through URLs, process arguments or HTTP responses. Request/response files are bounded and cleaned up; final inspection found zero pending job files. See the runtime document for the host-permission precondition.

Execution has two default slots (configurable 1–4), an eight-second default parent deadline (bounded 100–15,000ms), a 15-second PHP limit, five-second lock wait and ten-second statement timeout. The executable/script are fixed, contexts are signed and expire, and no HTTP client selects a database or executable. Unknown outcomes after possible financial dispatch return 503 and instruct retry of the same UUID/payload; they never claim nothing posted. Explicit validation/hold rejections retain their structured status codes.

**40 integrated HTTP checks + 3 IPC rejection checks + 4 database backstops passed.** Coverage includes real login, anonymous/CSRF/body-token rejection, actor/customer scope, Glucose 6/8/12, Suwada 12, 25/30/35/Other, remainder, replacement, strict cap, stale review, holds/disablement, command collisions/replay, rollback after stock/payment, linked-return post/replay/scope and unsupported offline routes. Invalid MAC, expired job and occupied slots are rejected.

The timeout test deliberately commits a synthetic invoice, delays its acknowledgement beyond an 800ms deadline, receives safe uncertainty, then recovers an accepted replay using the same UUID with no duplicate. Independent checks confirm two expected invoices in that run (the original and timeout case), original invoice payable/cash 675.00, outstanding 0.00, linked-return Refund Due 67.50 and net stock decrement 8 after returning one paid unit. Invoice/return reconciliations pass. Refund Due is not an actual cash refund.

**11 runtime-safety checks** cover forbidden-port manifests before connection, wrong grants, denied routes/legacy payloads, valid physical runtime proof and unchanged existing-install journal. Added read routes were exercised through actual authenticated desktop/mobile report and receipt screens.

This is **integrated application source with isolated acceptance**, not an approved operational identity policy or live service. Execution remains restricted to physically proved disposable manifests. A future approved target-host review must verify the CLI executable, service supervision, private IPC/ACLs, production identity/grants and concurrency on the actual server platform. Nothing in this stage enables operational financial routes.

## Sinhala status and printing acceptance

The scoped catalogue contains **147 draft entries**. It extends presentation only, with existing shared translations as fallback. It does not change financial IDs, rate choices, request fingerprints or enum values. Options without explicit values retain their original protocol value before label translation. Raw audit/custody JSON and user-entered data are not translated. The server language is explicit so the owner's forced-English preview does not accidentally follow a browser default.

The source candidate inventory contains **197 candidates**, including dynamic templates/shared text. This is an inventory for review, not a coverage percentage or proof that every exceptional/error path is localized. Rendered residuals and the source inventory remain in ignored evidence. Existing document dictionaries continue to reject missing required Sinhala document keys.

**22 real-application Sinhala Edge checks passed** at 1365px and 390px: checkout Review, unchanged 25%/condition/action values, correct 750.00/50.00 components, financial hold, no page overflow, manager return/custody labels, localized report headings/mobile card labels and stored Sinhala 58mm receipt styling. Software screenshots were inspected for glyph rendering and wrapping. The mobile report intentionally uses card labels rather than a visible desktop table header.

**8 report-localization checks passed**: Sinhala HTML/CSV labels and liability note, preserved machine field names, and byte-equivalent parsed CSV data rows across English/Sinhala. Native wording approval is **NOT PERFORMED**. Dynamic validations, less common custody/manager states and other report variants still require full reviewer coverage; English fallback must not be mistaken for complete acceptance.

`hybrid-stage6c-acceptance.md` contains the native-speaker checklist and physical-printer checklist. It covers connectivity, measured 58/80mm printable width, Sinhala glyphs, feeds/cutters, long invoices/returns, A4/PDF parity, failures/reprints and proof of no posting/stock side effects. **No physical printer/Bluetooth/feed/cutter test was performed or marked PASS.** Existing 58mm/80mm/A4/bilingual HTML and local PDF software paths were retained and checked separately.

## Regression ledger

Counts are per suite; they are not a deduplicated grand total.

| Suite | Stage 6C result |
| --- | --- |
| Integrated installation / recovery | 77 checks; explicit CLI chain on two separate instances |
| Runtime safety | 11 checks |
| Integrated financial HTTP | 40 HTTP + 3 IPC negative checks; 4 database backstops |
| Hybrid checkout database | 17 cases / 141 checks; concurrency, exact cents, gates and failure rollback |
| A1–E1 returns/custody | 157 checks; original positions, debt-first credit, cap/free recovery, manager authorization, concurrency, 11 failure stages and residual void |
| Legacy invoices | 26 checks |
| PROMO_CHOICE_V1 | 36 checks; original pilot settings unchanged |
| Historical-return adapter | 55 checks, unchanged financial assertions and complete business-row rollback |
| Stored bilingual documents | 64 checks / 18 immutable HTML render cases, 58mm/80mm/A4 |
| Financial/report oracle | 35 checks; component, collection, receivable, return/void and stock reconciliation |
| Combined scoped readers/controllers | 30 checks; zero business writes, default factory forbidden, protected hashes |
| Dashboard / Customer Profile Edge | 28 checks, including mobile designs and authorization |
| Normal front-controller HTTP Edge | 12 checks plus no-posting backstop; real login, readers, 58mm/PDF/private reports |
| Sinhala application Edge | 22 checks across desktop/mobile checkout, returns, report and receipt |
| Scoped Hybrid report locale | 8 checks; CSV data identical |
| Owner-preview smoke | 51 assertions; exact Glucose/Suwada reviews, incorrect binding 409, six denied write-route probes 403 |
| Integration foundations / offline rejection | 17 cases / 2,635 assertions |
| General foundations | 12 checks |
| Original database constraint suite | 16 checks on a newly named installed disposable schema; rollback, unchanged normal Migrator repeatability, UTC/native prepares |
| Hybrid UI JavaScript domain | 5 tests |
| Syntax / whitespace / file integrity | New/changed PHP and JS checked; original migration hashes and protected baseline files preserved |

The historical adapter retains Stage 6B's disclosed document-only wording adaptation (`Noncash return adjustment` → main's `Return credit`). The strict original label assertion remains stale; no financial expectation was weakened. Its original entry point still points to forbidden 3309 and was never executed or retargeted.

During this stage, targeted checks caught and resolved the owner-preview locale mismatch, an exhausted previously consumed synthetic reporting fixture (retested with a newly appended fixture, never reset), and a browser assertion that incorrectly searched a hidden mobile table header instead of its card label. These were display/test-fixture issues. A temporary generated-test syntax error was corrected before execution. No remaining unexpected failure is hidden in the ledger, and no calculator/resolver/financial-policy change was made.

## Isolation evidence and closeout

Primary run: `storage/stage2c-e7ab5f85efe84e759cd627eb19255183`.
Secondary run: `storage/stage2c-89c6d3d693ff46d4b3a9b910803c5b35`.
Both are new physically verified instances; no operational backup or data import was used. Runtime grants are schema-only DML. DDL provisioning is restricted to these disposable contexts. The historical test-name schema exists only on the primary disposable server.

Evidence includes `stage6c-install-evidence.json`, `stage6c-migrations.json`, `stage6c-runtime-safety.json`, `stage6c-http.json`, `stage6c-report-locale.json`, source/rendered locale inventories, browser screenshots and final Git/file hashes. Private credentials, databases, profiles and raw evidence remain ignored/unserved and are excluded from the source inventory.

All temporary HTTP/Edge processes were closed. Both Stage 6C database instances were shut down after checks; the primary was briefly reopened for final read-layout/safety checks and then disabled/closed again. Data/evidence were retained. All disposable action controls are disabled, source activation defaults remain false and the source posting hold remains true. Operational ports 3306/3309 and the active IDE login file were never accessed.

Owner preview remains **http://127.0.0.1:63825/**, with actual Review actions working and all posting/payment/return/stock routes still denied. Its port-63824 database and server were not repurposed for financial tests.

## Updated readiness matrix

| Area | Status | Remaining condition |
| --- | --- | --- |
| Explicit fresh-install application workflow | PASS in isolation | MariaDB 10.11.16, both tested defaults; no automatic operational DDL |
| Original unassisted 001–027 chain | EXPECTED FAIL 1267 | Original 007 is unchanged; use only the explicitly selected prepared workflow |
| Deployed raw 007/checksum preflight | NOT TESTED / BLOCKED for release | Operational inspection requires separate authorization |
| HTTP-to-CLI application integration | PASS in isolation | Real auth/CSRF, bounded private IPC, holds, reconciliation and replay verified |
| Operational HTTP identity/service readiness | BLOCKED / NOT AUTHORIZED | Target identity/grants, platform/CLI/ACL/supervision and operational release review |
| Historical financial regressions | PASS with disclosed text adaptation | Strict original receipt-label assertion remains stale |
| Checkout/returns/legacy/financial readers | PASS in tested scope | No genuine posting approval |
| Dashboard/Profile and owner preview | PASS | Approved designs and default-deny demo preserved |
| Sinhala software/layout/localized labels | PASS in tested scope | Draft wording; dynamic/exceptional-path review remains |
| Native Sinhala acceptance | NOT TESTED / NOT APPROVED | Named native reviewer and checklist decisions |
| 58mm/80mm/A4/PDF software rendering | PASS in tested scope | No physical-output claim |
| Physical Bluetooth/width/glyph/feed/cutter acceptance | NOT TESTED | Actual hardware and printed evidence required |
| Other DB versions/platforms | NOT TESTED | Fail-closed contract; separate validation required |
| Commit/merge/deployment/activation/genuine posting | NOT AUTHORIZED | Separate owner instruction and operational preflight |

Recommended next stage: review these unstaged integration artifacts, obtain native Sinhala and physical-printer acceptance, then authorize a narrowly defined target-host/release preflight covering identity/grants, CLI/ACL/service behavior, deployed raw checksums and backup/recovery. Do not automatically perform that stage, commit, merge, deploy, activate a gate or issue a genuine financial record. **Stop after Stage 6C.**
