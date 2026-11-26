# Stage 6E-2: Hybrid in the ORIGINAL New Invoice form

26 November 2026. Isolated implementation and owner-preview handoff only. No commit, push, merge, public deployment, operational access or activation.

## Branch, baseline and original-form proof

- Branch: hybrid-promotion-v1-stage6e2-original.
- Worktree: C:/xampp/htdocs/dharma chemical sales app/storage/hybrid-stage6e2-original.
- Exact baseline and current HEAD: 83228b1f79694be1721101cc0b45a58e14cb5667.
- Stage 6E-1 worktree remains clean at that checkpoint. Local main was not written.
- Same user-facing route: /rep/invoices/new. InvoiceController renders app/Views/invoices.php with its original invoice-form, original line partial, original product-card POS adapter, customer/search/categories/cart/payment/summary/navigation and unchanged pos.css.
- No separate invoice template, editor, user-facing route, resurrected Hybrid editor assets or alternate checkout was created. The former /rep/invoices/hybrid/new remains absent. API-shaped /new GET remains fail-closed rather than exposing another editor.
- Browser evidence verifies one original form, data-template=app/Views/invoices.php, the served branch header, visible enabled cart selectors, correct defaults/custom interaction, browser executable/PID and source worktree. The browser helpers exercise actual original product-card buttons and actual invoice-form controls; they do not inject another page.

## First milestone and owner preview

**http://127.0.0.1:63843/rep/invoices/new?language=en**

Sinhala: http://127.0.0.1:63843/rep/invoices/new?language=si-LK

The first milestone completed before full checkout integration: 16 actual Edge checks on desktop and 390px mobile; No Discount (0%) default, 25%, 30%, 35%, Other/custom, enabled custom input and actual viewport visibility. An initial restricted Edge GPU launch failed; the authorized test browser was rerun outside that sandbox. Initial screenshot scrolling and preview locale were corrected, then actual desktop/mobile images were inspected before continuing.

First milestone screenshots: [desktop](../storage/stage2c-10dec2fdf18c4263b96843c7c58c17f1/milestone1c/cart-controls-1440.png), [mobile](../storage/stage2c-10dec2fdf18c4263b96843c7c58c17f1/milestone1c/cart-controls-390.png).

Final actual controls/review: [English desktop](../storage/stage2c-10dec2fdf18c4263b96843c7c58c17f1/final-original/review-1440-en.png), [Sinhala mobile](../storage/stage2c-10dec2fdf18c4263b96843c7c58c17f1/final-original/review-390-si-LK.png). These are focused evidence, not a redesign/gallery.

Select the synthetic assigned customer, add the synthetic Glucose card, enter paid quantity 8 and product discount 25%, then click Review invoice. Leave whole-invoice discount 0% for LKR 750.00; choose Other invoice discount and 10% for LKR 675.00. Confirmation is disabled in this owner preview. All customer/product/stock values are synthetic and come through existing authorized services; the preview catalogue scopes the retained fixture before pagination.

## Implemented behavior

Product intent stays in original cart rows: default 0%, preset/custom selection and explicit eligible complete-shortage replacement. Server eligibility controls whether replacement may be selected. Paid, qualified, eligible remainder, actual free quantities and P/R/I/payable values come from authoritative review. Replacement retains its existing approved rate contract; it is never an inferred substitute for a partial shortage.

The original invoice-discount fieldset branches only for Hybrid (0-50%, sequential server policy); legacy INVOICE_PERCENT_V1 and PROMO_CHOICE_V1 retain their existing contracts. Original POS totals consume a presentation mapping of server review values. There is no new JavaScript money calculator. Existing reviewed input, UUID, exact financial engine, immutable snapshots, writer and linked returns remain authoritative.

Every financial input invalidates the review; stale responses are ignored. The original Review area displays server evidence. During unresolved confirmation the cart/payment/discount controls and customer/catalogue are locked. Uncertain replies keep the original command in memory and require an identical retry; no silent new UUID is generated. There is no Hybrid sale IndexedDB/local-storage/offline queue. Leaving/reloading while a command is unresolved triggers the browser leave warning; no persistent Hybrid recovery mechanism is claimed.

Disabled/unavailable runtime and unsupported eligible HTML posting remain HTTP 409 with no legacy fallback. The original form is online-only, JavaScript-assisted; its old non-JS business POST is not an escape to legacy. Admin /admin/invoices/new and approved pilot/historical/offline contracts remain unchanged. Representative Hybrid form handling independently rejects admin actors.

## Required financial examples (synthetic unit price LKR 100.00)

| Selection | Qualified / remainder / actual free | Product discount | Payable |
| --- | --- | --- | --- |
| Glucose 8, product 25%, invoice 0% | 6 / 2 / 1 | 50.00 | 750.00 |
| Glucose 8, product 25%, invoice 10% | 6 / 2 / 1 | 50.00 | 675.00 |
| Glucose 6 / 12, invoice 0% | 6/0/1; 12/0/2 | 0.00 | 600.00 / 1200.00 |
| Suwada Kudu 12, invoice 0% | 12 / 0 / 1 | 0.00 | 1200.00 |
| Ordinary 2, no benefits | 0 / 2 / 0 | 0.00 | 200.00 |

Multiline: ordinary 2 at 12.50%, Glucose 8 at 25%, invoice 10% = 832.50. Explicit eligible replacement 24 at 25%, invoice 0% = 1800.00. Strict cap, partial/paid/free shortages and unsupported offer conflicts reject without clamping or repricing.

## Focused acceptance

Counts are per suite, not a deduplicated grand total.

| Check | Result |
| --- | --- |
| First actual-cart milestone | 16 browser checks PASS; desktop/mobile actual selector screenshots |
| Final actual original-form browser | 72 checks PASS; financial examples, zero benefits, custom/multiline/replacement/cap/conflicts, original search/category/stepper/removal, fingerprint invalidation, offline, writes denied; English/Sinhala at 1440, 390 and 360px |
| Actual original-form cash/credit/retry | 12 browser checks PASS through separate authenticated disposable-only runtime; same command body/UUID on retry, accepted 201 then replay 200, controls locked during lost acknowledgement |
| Financial backstops | Exactly two sealed invoices: cash 675.00 fully paid; credit 150.00, paid 50.00, debt 100.00; stock -9 Glucose / -2 ordinary; no duplicate after lost acknowledgement |
| Existing authenticated API runtime | 40 checks PASS, including auth/CSRF, scoped review, holds, replay/collision/rollback and bounded timeout after commit plus accepted recovery |
| Scoped original-form safety | 15 checks PASS; wrong Glucose/Suwada stable-ID binding, cross-rep source/customer, disabled 409, admin rejection, pilot original form, no alternate API editor, original Dashboard/Profile controller rendering and full-row preservation |
| Retained controller/read-only regressions | 18 + 9 checks PASS; authoritative eligibility/financial review, scope, holds and pilot preserved |
| Removal/route regression | 24 checks PASS using fake PDO only; discarded route/assets absent, unversioned new-sale rejection and source OFF/held |
| Hybrid database | 17 cases / 141 checks PASS; atomic writer, concurrency, immutable SQL guards and exact reconciliation |
| Linked returns/custody | 157 checks PASS; original allocations, debt/refund, custody, residual void and failure rollback |
| Legacy / PROMO_CHOICE_V1 | 26 / 36 checks PASS; original configuration unchanged |
| Historical-return adapter | 55 checks PASS; existing disclosed receipt-label adaptation only; complete business-row rollback. Strict old hardcoded-port entry point was not executed |
| Foundation database | 16 checks PASS on a separately newly named installed empty schema in this same disposable server; original database.php, migration repeatability, UTC and native prepares |
| Disposable runtime safety | 11 checks PASS; forbidden endpoint manifests rejected before connection, grants/physical identity and existing-install refusal |
| Pure foundations/policy/offline | Foundation 12; Hybrid 17 cases/2635 assertions; policy 20 cases/13533 assertions; JS invoice-discount/offline 12 tests PASS, including AES-GCM and reconciliation |
| Source syntax/preservation | 14 changed/new PHP files and 7 changed/new JS files PASS syntax; CRLF-aware whitespace PASS; 16 discarded files absent; 89 protected files have identical text and no protected Git content diff (new-checkout line endings are disclosed, not claimed byte-identical) |
| Owner-preview data safety | Complete ordered row digests for all 90 primary disposable application tables unchanged during final browser, review and denied-write probes; repeated after safety/controller checks |

A programmatic stepper test originally omitted native quantity-change/blur behavior; the test was corrected to dispatch that event. No shared stepper implementation change was required. No full Phase 9 browser suite or new physical-printer/native-language sign-off is claimed. Dashboard/Profile received unchanged-source and actual controller-render checks, not a new full browser reacceptance.

## Isolation, holds and packaging

Dedicated database: loopback 63842, new physical data directory under this worktree's ignored storage. Ports 3306/3309 were never connected. Only newly generated synthetic fixtures were created, not operational/replacement masters or an operational import.

The existing explicit compatibility-assisted installer was used only for this new disposable instance and the dedicated foundation schema. The reviewed pinned Windows rehearsal packaging was used: 025-027 and the separate compatibility SQL needed exact checkpoint raw bytes rather than checkout CRLF conversion. No SQL content, deployed migration, contract or application migration source diff was changed. This remains compatibility-assisted installation evidence, not pristine-chain or release-packaging acceptance.

Operational source activation remains false, source database/binding allowlists empty and source financial hold true. The owner preview has independent HTTP and CLI allowlists and a held service. Its disposable database controls finish enabled for review only, with sale/return/custody posting all 0. Private financial acceptance used a distinct ACL-protected runtime on 63844; its configuration is now disabled/all posting false/held, and that HTTP process is stopped. Only the write-denying owner preview and its disposable database remain running.

Private databases, accounts, credentials, runtime profiles/keys, sessions, IPC, logs and screenshots remain ignored/unserved. No generated runtime artifact belongs in the source inventory. Existing engine/config/migration/offline/Dashboard/Profile files and pos.css have no Git content diff. Existing translation values are preserved; added terms/statuses reuse existing terminology or are explicitly draft Sinhala.

## Exact source scope and remaining blockers

The accompanying source inventory lists the 24 implementation/test/documentation content paths and hashes. It separately records four non-content Git-status entries: database/fresh-install/006a_customer_locations_compat_v1.sql and migrations 025-027. Git status currently marks those four M after restoring pinned checkpoint raw bytes, but each file is byte-identical to its HEAD blob and the SQL content diff is empty. They are packaging/index line-ending status artifacts, not SQL edits; nothing is staged. Changes are limited to the six requested original invoice files, InvoiceController, public-static localization/cache support, shared isolated test boundaries, focused tests and this documentation. No separate editor asset or stylesheet was added.

Release remains blocked on: migration 007's original collation failure and deployed raw-checksum/canonical LF-vs-CRLF packaging; real product/promotion master/revision bindings and live price/assignment/stock-ledger preflight; target-specific operational HTTP/CLI identity/grants/IPC/ACL/supervision/timeouts and recovery acceptance; named native Sinhala financial/error wording review; and actual 58/80mm, A4/PDF/driver/Bluetooth/glyph/feed/cutter acceptance. All require separately scoped approval. The disposable CLI/physical guards remain intact and cannot be repointed at operational data.

Stop after isolated handoff. No commit, push, merge, activation, genuine posting, operational data inspection or deployment is authorized or performed by Stage 6E-2.
