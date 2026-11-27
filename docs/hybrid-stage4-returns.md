# Hybrid Promotion V1 — Stage 4 isolated linked returns and custody

> **Superseded discount behavior - 27 November 2026:** This historical stage record retains its original examples, test counts and evidence. Any instruction or example applying invoice discount to a line with free entitlement or nonzero product/replacement discount is superseded by the [approved eligibility correction](hybrid-stage6e2-original-invoice.md#approved-eligibility-correction-and-owner-verification---27-november-2026). Such lines now receive invoice discount 0.00; a 0% product selection alone does not exclude a line. Glucose 8 at 100 with remainder discount 50 remains payable 750 even at invoice rate 10%; adding 5 ordinary unpromoted units at 100 gives eligible subtotal 500, invoice discount 50 and total 1,200. Existing immutable snapshots and historical results are not rewritten; the strict combined 50% cap remains. Old screenshots and PASS counts do not verify the new rule.

Date: 26 November 2026. Worktree: `storage/hybrid-stage1`, branch `hybrid-promotion-v1-stage1`. This is an isolated implementation and verification handoff, not deployment or activation approval.

## Scope and result

Implemented original-position linked credits, physical custody, administrative disposition and residual Hybrid voids. Existing invoice identities, totals, payments, initial SALE movements and sealed promotion snapshots remain immutable. Legacy and historical-return workflows retain their policies. The application in the main checkout was not changed.

All source configuration remains disabled, with the financial posting hold retained and empty operational database/product-binding allowlists. The CLI and physical disposable-database guard remains mandatory for writers. There is no operational HTTP posting path in this handoff. Browser verification used the existing test-only loopback HTTP-to-CLI bridge, never the application's default database factory.

No connection was made to the prohibited operational endpoint on port 3309 or damaged legacy endpoint on port 3306. No operational migrations, payments, stock movements, feature controls, pilot settings, encrypted drafts, passphrases or offline queues were changed.

## Exact Stage 4 source changes

New services:

- `app/Services/HybridReturnRequest.php` — strict original-line/physical-condition requests, versioned policy and command identity; browser positions and prices rejected.
- `app/Services/HybridReturnPolicy.php` — pure A1–E1 planning, immutable history reconstruction, component reversals, retained-cap checks and debt/refund arithmetic.
- `app/Services/HybridInvoiceBalances.php` — shared original-invoice financial projection and scalar SQL aggregates for lists, credit checks and reports.
- `app/Services/HybridCustodyLedger.php` — physical receipt, supplement, acceptance, review, rejection, reclassification, release and disposal reconstruction.
- `app/Services/HybridReturns.php` — scoped locks, previews, exact review hashes, atomic commands, stock adjustments, linked credit posting and residual voids.
- `app/Services/HybridCustody.php` — custody facade over the same coordinator.
- `app/Services/HybridReturnReconciliation.php` — original allocations, custody/acceptance linkage, immutable event projection and saleable-stock checks.

Other new files:

- `app/Controllers/HybridReturnController.php`
- `app/Views/hybrid-returns.php`
- `public/assets/js/hybrid-returns.js`
- `public/assets/css/hybrid-returns.css`
- `database/migrations/026_hybrid_linked_returns.sql`
- `database/migrations/027_hybrid_custody_dispositions.sql`
- `docs/hybrid-stage4-returns.md` (this report)

Existing source files modified in this stage:

- `app/Controllers/InvoiceController.php` — delegates explicit Hybrid return routes.
- `app/Services/Invoices.php` — optional coordinator injection, linked-credit-aware debt/list/payment reads, and rejection of legacy full-original voids for Hybrid invoices.
- `app/Services/Reports.php` — linked-credit-aware current debt and liability reads; separate Hybrid credit report.
- `app/Views/invoices.php` — linked-credit display, gated workflow link, and suppression of the legacy void form for Hybrid invoices.
- `app/Services/HybridFeatureGate.php` — custody has its own control; financial posting holds still protect sale/return posting and controlled release.
- `app/Services/HybridInvoiceReader.php` — reads linked adjustments through the shared projection instead of rejecting future return tables.
- `app/Services/HybridInvoiceReconciliation.php` — adds return/custody reconciliation to original sealed invoice checks.

New test files:

- `tests/hybrid-return-domain.php`
- `tests/hybrid-returns-database.php`
- `tests/hybrid-return-worker.php`
- `tests/hybrid-return-browser.php`
- `tests/hybrid-return-browser.js`
- `tests/hybrid-stage4-close.php`

Existing tests modified:

- `tests/hybrid-database.php` — expects the new explicit Hybrid residual-void boundary and version-aware return availability.
- `tests/hybrid-legacy-regression.php` — additionally verifies unchanged full-original legacy void, stock restoration, Refund Due and replay.
- `tests/hybrid-ui-bridge.php` — optional synthetic Stage 4 coordinator injection.
- `tests/hybrid-ui-router.php` — optional Stage 4 loopback test routes.

Earlier untracked Stage 1–3 files remain in place; this stage does not claim to have created them or integrated them into main. `OfflineSales.php`, source configuration and the existing migration 025 were not changed in Stage 4. Main's unrelated dirty documents were preserved.

## Policy and architecture evidence

| Policy | Implementation and evidence |
| --- | --- |
| A1 | Credit reverses original gross/P/R/I/net vectors only. A synthetic Suwada replacement return of two units reverses R=50.00 and I=15.00, crediting 135.00; retained replacement benefits are not clawed back. Current free-stock feasibility is never reconsidered. |
| B1 | Original discounted-remainder runs are consumed before qualified runs. Accepted ranges are reconstructed and compared against immutable original allocations; repeated and competing requests cannot reuse positions. |
| C1 | Exact integer-cent test `2 × retained discounts > retained gross` blocks acceptance. A mixed replacement/free-offer scenario is blocked after returning the other product. No manager override exists. |
| D1 | Any existing posted noncash payment or historical noncash settlement blocks financial return/void acceptance. A synthetic confirmed bank transfer exercises this block. Pending custody can still hold goods without issuing credit. |
| E1 | Original promotion quantities determine excess delivered free units. Missing mandatory free goods block posting. Supplemental goods join the same pending receipt, then a fresh eligible review accepts that receipt once. |

`HybridReturnPolicy` has no database, current-price lookup or clock. Compact allocation runs remain bounded even for the billion-unit synthetic case. Returns do not redistribute original residual cents: at a one-cent unit price, the first half of a billion positions need not carry half the invoice's net value.

Every request reloads active actor and current invoice/customer scope. Representatives can use only their own authorized original invoice/source. Only current administrators may perform residual voids or manager dispositions. Source products are locked by stable ID. Existing inventory/control/user/customer/promotion gates remain compatible; the shared inventory gate serializes payment/return/stock conflicts before invoice and cursor projections can diverge.

One unresolved custody receipt per invoice deliberately reserves that invoice's return workflow. It records only physically received quantities, conditions, evidence and holding references. Financial cursors and credit totals do not advance. Further goods use a supplemental intake; another financial receipt cannot bypass the unresolved custody. Rejected custody remains held until manager review. Manager review records a decision, never a financial waiver. Reclassification/disposal remain physical events; only accepted Saleable goods or an authorized later quarantine release enter available stock.

Accepted commands bind actor, operation, invoice and canonical payload. The command result is appended last; identical scoped replay returns that result even after posting is disabled. Changed payloads reject. Database conflicts retry only recognized transient failures with the same identity. Client mutation retries retain the same request in memory when an outcome is uncertain; the UI disables competing actions until resolution. No new offline storage exists.

## Database preparation and isolation

Migrations 026 and 027 are additive, forward-only, checksum-verified and parser-compatible. They use InnoDB, exact DECIMAL money, restricted foreign keys, immutable event triggers and financial-child insertion guards. Position overlap is validated under application locks and reconstructed during reconciliation; a unique starting position alone is not represented as an interval-overlap guarantee.

| File | SHA-256 |
| --- | --- |
| `025_hybrid_invoice_snapshots.sql` (unchanged) | `662054eb3025354eff03c9da928e3b618fa433a4ca69a8b6632342924d45f2c1` |
| `026_hybrid_linked_returns.sql` | `912b831ea2d3674bee50ef7b6dfd3852683fec2b00c921c5f419f5457a137d5f` |
| `027_hybrid_custody_dispositions.sql` | `1e00ad4388f60c4b130d560d6cbcebb83489e8d43c9033bfc4d6e54faca24fac` |

026/027 were applied only to the already verified disposable schema `dharma_sales_hybrid_50b64bc4a06f471ebcc14f17294f395e_test` on loopback port 58171. Physical port/data-directory identity, manifest identity and restricted schema-only runtime grants were checked before access. Runtime has no DDL/global/operational-schema access. Migration files were frozen after rehearsal; 007, 016, 024 and 025 were not rewritten.

The disposable baseline retains the previously documented isolated collation compatibility fixture. This is not evidence that migration 007 now works on a pristine fresh install: that independent blocker remains unresolved.

Test-only injected configuration and synthetic control events enabled the fixture's return/custody paths during tests, then disabled them. Source and operational gates were never enabled. Final closeout disables all disposable Hybrid controls before shutting down only this verified instance. Ignored storage retains synthetic data, manifests, browser profiles, screenshots, logs and closeout evidence; no backup or original data was deleted.

## Financial and stock examples

Glucose eight paid plus one free, product discount 25% on two remainder units, then invoice discount 10%:

- Original gross 800.00; P=50.00, R=0.00, I=75.00; payable 675.00.
- First and second paid positions credit 67.50 each. The next qualified paid position credits 90.00 and requires recovery of the original free unit. Three separate accepted returns equal a combined credit of 225.00.
- Residual void after those three paid positions and the free unit have returned credits only 450.00 for the remaining five paid positions. Cumulative credit is 675.00 and total restored physical quantity is nine, with no duplicate original reversal.
- With original cash collected 575.00, returning the two remainder positions credits 135.00: debt reduction 100.00 and Refund Due 35.00. No cash payment or transferable credit is created.
- In the browser custody case, three paid Quarantine units plus one recovered Unsaleable free unit yield credit 225.00 after acceptance, with zero initial saleable restoration. Releasing one accepted paid Quarantine unit subsequently increases available stock by one and adds no financial credit. The synthetic starting stock 500 becomes 491 at issue and 492 after that release.

All return stock transactions are positive ADJUSTMENT entries with `invoice_id=NULL`; original SALE linkage lives in Hybrid tables. Quarantine/Unsaleable goods and pending Saleable receipts do not automatically become available stock. Original invoice/payment rows remain unchanged.

Invoice details, listing, customer credit checks and payment acceptance share linked-credit-aware debt. Reports retain issued-sales and actual-collection definitions. The new `/rep/reports/hybridreturns` and `/admin/reports/hybridreturns` report shows linked gross/P/R/I/net reversals, debt reduction and Refund Due separately, using acceptance dates and existing server scope. Refund liability reporting includes partial returns without requiring a terminal void. No joins multiply invoice totals through unit runs. Actual cash refunds remain unavailable.

## Verification results

| Suite | Result |
| --- | --- |
| Stage 4 pure return/custody domain | 34 checks passed, no database |
| Stage 4 disposable database | 157 checks passed |
| Real Edge Stage 4 workflow | 13 checks passed, plus database credit/stock backstop |
| Stage 2C checkout database regression | 17 cases / 141 checks passed |
| Stage 2B pure foundations | 17 cases / 2,635 assertions passed |
| Stage 1 pure promotion engine | 20 cases / 13,533 assertions passed |
| Legacy invoice disposable regression | 26 checks passed, including legacy full void and replay |
| Existing PROMO_CHOICE_V1 disposable regression | 36 checks passed, including legacy full void; pilot configuration unchanged |
| Existing historical-return pure calculation | 6 checks passed |
| Existing Stage 3 controller integration | 28 checks passed |
| `tests/run.php` | 12 foundation checks passed |
| Guarded `tests/database.php` wrapper | 16 constraints, repeat migration checksums, UTC/native prepares; test fixtures rolled back |
| JavaScript | Hybrid UI domain 5, invoice discount 4, mixed-oil 3, offline domain 8 tests passed; new return JS syntax check passed |
| PHP | Syntax checks passed for all Stage 4 application/controller/view/test files and touched regression files |

Real competing PHP workers prove: different reviewed commands contending for the same original positions produce exactly one accepted return and a stale/conflicting rejection; identical concurrent UUIDs produce one posting and one accepted replay. No double position consumption occurs. A payment after preview invalidates that preview. Tests also cover disabled posting with accepted replay, different representative scope, deactivation, changed-payload collision, administrator authorization, immutable SQL update/delete rejection, exact listing/report liabilities and reconciliation of physical-to-financial acceptance.

Failure injection verifies complete business-row rollback at eleven financial-posting boundaries: command, custody event/receipt, stock, financial credit, allocations, acceptance, projection, streams, audit and result. Six pending-intake boundaries and eight existing-custody conversion boundaries also roll back without losing or re-receiving the earlier receipt. The test digest includes original invoices, numbering, payments, stock balances/movements, commands/results, return/custody evidence, projections, sync streams and audit rows. Auto-increment gaps are not misrepresented as financial rollback failures.

Real Edge exercises current-scope invoice selection, mandatory free recovery, blocked confirmation, pending Quarantine intake, supplemental Unsaleable free recovery on the same receipt, original-net acceptance, administrator quarantine release, absent representative manager controls and CSRF rejection. Phone viewport checks confirm no page overflow and visible stacked free-recovery inputs. Screenshots were inspected; final artifacts are under ignored `storage/stage2c-*/stage4-ui-*/`. The initial sandboxed Edge process was denied GPU access, so successful browser runs used the reviewed elevated test command with the browser sandbox intact.

## Remaining blockers and next stage

1. Source remains isolated and unmerged. Existing Stage 1–3 untracked work requires an intentional reviewed integration baseline; no commit, deployment or root checkout modification was performed here.
2. Operational HTTP posting remains intentionally blocked by the existing physical CLI isolation guard, source disabled configuration, posting hold and empty allowlists. Runtime/deployment authorization and a separate operational schema/product/offer preflight are still required. Do not remove these protections based on synthetic results.
3. Migration 007's independent fresh-install problem remains. No operational backup/restore or production migration rehearsal was authorized or claimed in Stage 4.
4. Stage 5 should complete stored-snapshot return documents, 58/80mm thermal and A4/PDF formats, Sinhala financial-copy review, broader report/document acceptance and physical-printer/device acceptance. Current online return UI is sufficient for isolated testing and uses English copy.
5. Existing noncash settlements, retained-cap failures and missing mandatory free goods continue to block financial acceptance. Administrative custody review supplies no waiver. Cash payouts and transferable customer credit remain unsupported.
6. Pending custody conservatively permits one unresolved receipt per original invoice. More elaborate concurrent physical reservation workflows would need a separate approved design; they are not silently inferred here.
7. The older historical-return posting suite hardcodes another restored schema on port 3309. It was inspected but not executed, and its target-specific permission controls were not widened. Historical-return arithmetic passed its six existing pure checks; full historical posting acceptance remains a separate isolated-harness task. Unchanged source paths and guards are not claimed as a substitute for that posting test.

Stop at Stage 4 implementation and isolated reporting. Stage 5, operational migrations, activation, posting-hold removal and genuine financial posting require separate authorization.
