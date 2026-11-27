> Superseded by the owner's final UI removal decision on 26 November 2026. The separate editor and owner preview have been removed; the URLs and UI acceptance below are historical. Current state: [UI removal](hybrid-stage6d-ui-removal.md).

> **Superseded discount behavior - 27 November 2026:** This historical stage record retains its original examples, test counts and evidence. Any instruction or example applying invoice discount to a line with free entitlement or nonzero product/replacement discount is superseded by the [approved eligibility correction](hybrid-stage6e2-original-invoice.md#approved-eligibility-correction-and-owner-verification---27-november-2026). Such lines now receive invoice discount 0.00; a 0% product selection alone does not exclude a line. Glucose 8 at 100 with remainder discount 50 remains payable 750 even at invoice rate 10%; adding 5 ordinary unpromoted units at 100 gives eligible subtotal 500, invoice discount 50 and total 1,200. Existing immutable snapshots and historical results are not rewritten; the strict combined 50% cap remains. Old screenshots and PASS counts do not verify the new rule.

# Stage 6D-UI — unified New Invoice acceptance

26 November 2026. Isolated implementation and targeted acceptance completed. Owner preview is running. No operational release, activation, commit, push or merge occurred.

## Baseline and location

- Branch: `hybrid-promotion-v1-stage6d-ui`.
- Worktree: `C:\xampp\htdocs\dharma chemical sales app\storage\hybrid-stage6d-ui`.
- Parent and current HEAD: `8477ddac2271114a1f74ff539924541eb4974df5`.
- The checkpointed Stage 6A worktree remains clean on its original branch. Local main and the original Stage 1 worktree were not changed. Main's existing documentation edits remain preserved.
- Stage 6A/B/C reports, inventories, policy, installer/worker boundaries, original POS, Hybrid editor, pilot and offline contracts informed this change.

## Architecture and owner-approved routing

The owner explicitly chose Hybrid for **all eligible new online representative sales, including zero discounts and zero free goods**. `InvoiceController` serves the unified screen at `/rep/invoices/new` for non-pilot representatives. Disabled/unavailable Hybrid returns an error, never the legacy form. Unversioned new representative sale submissions fail closed. Authentication, current actor checks, customer/source scope, CSRF and strict Hybrid request validation remain mandatory.

The original product-card adapter `createPOS` supplies search, categories, prices, customer search/selection, cart, quantity steppers and removal. Optional presentation hooks mount the existing Hybrid controls in each cart card; existing callers retain their defaults. Product discount always starts at zero. Qualifying/remainder/free quantities and all monetary components come from server eligibility/review. Details expand in the cart and review. No new financial calculator exists in the browser.

The existing Hybrid payload, review hash, resolver, allocations, snapshots, writer and pending-command retry logic remain in use. The isolated worker adds only GET access to the unified screen; HTML POST to that route remains denied by the worker. Its physical CLI guard, private IPC verification, source activation defaults and financial hold are unchanged.

The separately authorized PROMO_CHOICE_V1 pilot retains its original New Invoice contract and screen. Existing invoice/payment/read/void identities and signed legacy offline sync remain unchanged. Explicit historical-return contracts retain their existing authorization/normalization path; this is not a fallback from failed Hybrid review. Hybrid requests with incompatible fields are still rejected. Administrators' existing checkout is unchanged.

The old `/rep/invoices/hybrid/new` URL is retained as an alias to the same unified view. Routine eligible representatives are not offered two checkout screens. Hybrid stays online-only; neither its cart nor failed requests enter IndexedDB, local storage or an offline queue. Offline/PWA/encryption source files were not edited.

## Exact source changes

The companion `hybrid-stage6d-ui-inventory.json` records SHA-256 before/after values for **18 files: 9 modified and 9 new**, including these two documentation files. The inventory excludes its own digest.

Modified:

- `app/Controllers/InvoiceController.php` — explicit unified GET/new-sale routing; pilot and historical contracts preserved.
- `app/Support/HybridWorkerClient.php` — unified GET permitted; no new financial HTML POST.
- `app/Views/hybrid-invoice.php` — New Invoice presentation, original POS assets, customer search, assigned-source display.
- `public/assets/js/pos.js` — optional row, review and translation hooks; original default behavior retained.
- `public/assets/js/hybrid-invoice.js` — connects existing Hybrid editor to product cards; compact server-verified details and line amounts.
- `public/assets/i18n/hybrid-si-LK.json` — three scoped draft labels.
- `tests/hybrid-ui-router.php` — unified GET/redirect in the existing preview allowlist; every writer still denied.
- `tests/hybrid-ui-bridge.php` — matching allowlist, explicit preview locale and fixture filtering before catalogue pagination.
- `tests/hybrid-ui-preview.ps1` — separate port 63833 and verified native process shutdown.

New:

- `public/assets/css/hybrid-unified.css` — checkout-only layout, readable amounts and non-overlapping mobile Review control.
- `tests/hybrid-stage6d-browser.js` — actual-card review/negative/offline/layout acceptance using existing CDP helpers.
- `tests/hybrid-stage6d-issue.js`, `tests/hybrid-stage6d-issue.php` — real form authentication and actual-card synthetic cash/credit confirmation through the guarded worker.
- `tests/hybrid-stage6d-routing.php` — zero-benefit, disabled/no-fallback, JSON object, pilot, scope and incorrect-binding checks.
- `tests/hybrid-stage6d-preview-prepare.php` — append-only synthetic fixture setup in a physically proved disposable context.
- `tests/hybrid-stage6d-preview-safety.php` — complete-table digest checks and disabled-posting proof.
- This report and its JSON inventory.

## Financial examples

All example unit prices are synthetic LKR 100.00. Invoice discount is zero unless stated.

| Selection | Qualified / remainder / actual free | Product discount | Payable |
| --- | --- | --- | --- |
| Ordinary 2, product 25% | 0 / 2 / 0 | 50.00 | 150.00 |
| Glucose 6, product 25% | 6 / 0 / 1 | 0.00 | 600.00 |
| Glucose 8, product 25% | 6 / 2 / 1 | 50.00 | 750.00 |
| Glucose 12, product 25% | 12 / 0 / 2 | 0.00 | 1200.00 |
| Suwada 12, product 25% | 12 / 0 / 1 | 0.00 | 1200.00 |
| Ordinary 2, no benefits | 0 / 2 / 0 | 0.00 | 200.00 |
| Glucose 8 product 25%, invoice 10% | 6 / 2 / 1 | 50.00; invoice 75.00 | 675.00 |

Multiline: ordinary 2 at custom 12.50% plus Glucose 8 at 25% gives S=1000.00, P=75.00, R=0.00, sequential I=92.50, payable=832.50. Explicit synthetic replacement 24 at 25% produces R=600.00 and no free delivery. Product 50% followed by invoice 50% is rejected by the combined cap. Wrong stable-ID binding returns 409; no binding check was weakened.

## Test evidence

Counts are per suite, not a deduplicated total. Tests used only synthetic records and the physically verified disposable instance.

| Suite | Result |
| --- | --- |
| Actual unified product-card browser | 45 checks PASS: ordinary/zero, Glucose 6/8/12, Suwada 12, defaults/custom/multiline/sequential, cap, replacement, monetary/oil conflicts, paid/free shortages, offline and six denied writes |
| Final desktop/mobile layout | 3 additional width checks PASS; final English desktop/mobile and Sinhala mobile screenshots inspected |
| New routing | 12 checks PASS: zero-benefit Hybrid, disabled/no fallback, JSON objects, customer/source scope, incorrect stable binding, pilot screen, worker GET-only addition |
| Actual-card authenticated confirmation | 8 checks PASS; exactly two sealed/reconciled synthetic cash/credit invoices in a separate temporary HTTP runtime |
| Existing authenticated HTTP/CLI integration | 40 checks + 3 IPC rejection checks + 4 financial/stock/immutable backstops PASS; stale review, holds, replay/collision, timeout ambiguity, scope and rollback included |
| Runtime safety | 11 checks PASS; forbidden ports rejected before connection; grants and existing-install journal verified |
| Hybrid database | 17 cases / 141 checks PASS |
| A1–E1 returns/custody | 157 checks PASS, including recovery, retained cap, noncash block, pending custody, manager scope, residual void and rollback |
| Legacy invoices / PROMO_CHOICE_V1 | 26 / 36 checks PASS |
| Historical-return adapter | 55 checks PASS with previously disclosed receipt-label adaptation; strict original entry point not run |
| Stored bilingual documents | 64 checks / 18 immutable render cases PASS; 58mm, 80mm, A4 |
| Financial/report oracle | 35 checks PASS; original P/R/I, collections, debt, Refund Due, returns, residual void, deduplicated stock |
| Combined scoped readers | 30 checks PASS, including protected file hashes and zero business writes; repeated after restoring checkpoint migration bytes |
| Dashboard/Profile real Edge | 28 checks PASS, including mobile and authorization |
| Existing Sinhala real application | 22 checks PASS; checkout/returns/reports/receipt desktop/mobile software coverage |
| Report locale | 8 checks PASS; parsed CSV financial rows unchanged |
| Integration/offline rejection foundations | 17 cases / 2635 assertions PASS |
| General foundations | 12 checks PASS |
| JS domains | Hybrid 5, legacy invoice discount 4, offline reconciliation/AES-GCM 8 PASS |
| Preview boundary | Six browser write probes and ten final direct route probes returned 403; complete digests of all 90 database tables unchanged during preview checks |
| Syntax and diff | Nine changed/new PHP files linted; application JS syntax and whitespace checks PASS |

The existing historical adapter preserves financial assertions and changes only its documented stale receipt wording to `Return credit`; the original hardcoded-port entry point was never executed. No full Phase 9 live-browser suite was run: its fixed schema/bootstrap was not repurposed. Offline confidence here comes from unchanged storage/sync/encryption source, eight domain/encryption checks, unchanged optional POS defaults and explicit Hybrid offline rejection.

Targeted development runs exposed harness assumptions (an incorrect fixture helper argument, selecting a product from the first unfiltered catalogue page, and filtering a fixture after pagination). These were corrected in test code. Browser review also identified mixed-language POS labels and a floating mobile Review button; the scoped translator and checkout CSS fixed those. Final listed runs pass. No policy or resolver change was needed.

## Isolation and migration-byte evidence

Disposable database port: **63832**, loopback only. Schema: `dharma_sales_hybrid_16d02288876344d887c6d9dd767a0cb1_test`. Run/data live only under this worktree's ignored `storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42` directory. Runtime grants are schema-only SELECT/INSERT/UPDATE/DELETE with global USAGE. Bootstrap/run identity, physical data directory and server port were proved before application tests. Ports 3306/3309 and operational data were never accessed.

Installation reused the exact reviewed Stage 6C Windows migration bytes and pinned explicit compatibility-preparation workflow on this new disposable instance. It completed original 001–027 plus the separate preparation entry. This is a compatibility-prepared test fixture, **not an unassisted pristine-chain PASS**.

The checkpoint has LF bytes for original 001–024 while the existing installer contract pins the previous Windows CRLF worktree bytes (007: checkpoint `161effa594c58f45223bf68161ba3a6f3b623dc34bafff0a33080008a337fa9c`, supported Windows rehearsal `f46167f2b163ed69b351e61cb0bebfc30804db0713d1e22973f6e96d7a94800a`). Individually verified baseline bytes were used temporarily for that disposable rehearsal and checksum-pinned historical adapter. **All 27 migration files and the historical test were restored exactly to checkpoint bytes afterward.** No migration or contract edit remains in the source diff. A fresh LF checkout's installer-contract packaging mismatch remains an explicit release blocker; it was not silently repaired here.

All 12 protected files match checkpoint bytes, including Dashboard/Profile views, shared CSS/catalogue, legacy printing and 007. Source Hybrid configuration is disabled, allowlists/bindings empty and posting hold true. The private financial test policy is disabled/held after testing, its temporary HTTP servers closed and IPC empty. Its DACL is restricted to the verified owner identity, SYSTEM and Administrators. The owner preview uses a different HTTP entry and cannot dispatch those financial routes.

Generated logs, database files, accounts, manifests, IPC keys, browser profiles and screenshots remain ignored/unserved, outside the source inventory. Evidence files include `stage6c-http.json`, `stage6c-runtime-safety.json`, suite logs, `browser-result.json`, `stage6d-final-boundary.json` and `stage6d-preview-row-digests.json` in the run directory. Pointer `stage6d-screenshots-current.txt` identifies the final inspected screenshots.

## Owner preview and commands

**Running URL: http://127.0.0.1:63833/**. Direct English: `/rep/invoices/new?language=en`; Sinhala: `/rep/invoices/new?language=si-LK`. The preview automatically selects its synthetic representative; no operational login is required.

Select the synthetic customer, click the Glucose card, enter paid quantity 8, select product 25%, leave invoice discount zero, enter cash 750.00 and click Review invoice. Expected: qualified 6, remainder 2, free 1, P=50.00, payable 750.00. Expand line details. Try 6/12 and Suwada 12, or Other invoice 10% for Glucose 8 payable 675.00. Confirmation remains disabled. Conflict/limited/partial/replacement products are deliberate negative/edge fixtures, not real stock.

Run these from the new worktree only:

```powershell
Set-Location -LiteralPath 'C:\xampp\htdocs\dharma chemical sales app\storage\hybrid-stage6d-ui'
# Stop this preview and its proved disposable database:
powershell -NoProfile -ExecutionPolicy Bypass -File tests/hybrid-ui-preview.ps1 Stop
# Start it again using the retained synthetic fixture; no migration runs:
powershell -NoProfile -ExecutionPolicy Bypass -File tests/hybrid-ui-preview.ps1 Start
```

Both commands were verified, and the preview was left running. The separate earlier owner preview on 63825 was not modified or stopped. Preview permits only synthetic page/options/eligibility/review and safe static assets. Posting, payment, return, stock, offline sync, private-file and test-session routes are denied before controller/DB dispatch; the CLI bridge independently enforces the same boundary.

## Acceptance limits and next steps

| Area | Status / remaining condition |
| --- | --- |
| Unified UI and synthetic financial integration | PASS in the tested isolated scope; source remains uncommitted |
| Dashboard/Profile | Protected bytes and 28 browser checks PASS |
| Migration 007 / original pristine chain | Existing 1267 blocker retained; compatibility-prepared fixture only |
| Fresh checkout raw-byte contract | BLOCKED for release packaging: LF checkpoint versus pinned Windows CRLF; deployed checksum preflight requires separate authorization |
| Historical returns | Financial adapter PASS; stale strict receipt-label assertion remains outside this UI scope |
| Operational HTTP/CLI runtime | BLOCKED / not authorized: target identity, grants, service supervision, IPC/ACL platform acceptance and deployment preflight |
| Sinhala | Desktop/mobile software PASS; draft wording and uncommon dynamic/error paths still need named native review |
| Physical printing | NOT TESTED: existing 58/80mm/A4/PDF software retained; hardware checklist from Stage 6C remains required |
| Offline PWA | Storage/sync source unchanged; domain/encryption/rejection checks PASS; no full browser reacceptance claimed |
| Operational activation / genuine records | NOT AUTHORIZED; source gates OFF and hold ON |

Native checklist: approve the three added labels (Search customers, Your assigned stock, Quantities and discount components), product/remainder/free versus replacement wording, sequential discount/cap explanations, cash/change/debt terms and mobile wrapping. Existing Stage 6C native/physical-printer checklists continue to apply. No native or hardware PASS is claimed.

Next: owner review of this running UI, native wording and physical-printer acceptance, followed only by a separately authorized release-packaging/target-host preflight. No automatic commit, push, merge, deployment or activation. **Stop after Stage 6D-UI preview handoff.**
