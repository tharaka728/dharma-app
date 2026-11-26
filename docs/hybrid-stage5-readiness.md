# Hybrid Promotion V1 - Stage 5 isolated implementation and readiness

26 November 2026. **NOT PRODUCTION READY.** Document/report implementation and isolated verification are complete within the tested scope. Fresh-install migration, historical-return posting acceptance, full Sinhala UI/native-language acceptance, combined-main integration, HTTP runtime approval and printer acceptance remain unresolved below.

## Preflight and isolation

Work was confined to `storage/hybrid-stage1`, branch `hybrid-promotion-v1-stage1`, baseline `80331eb`. The policy and Stage 2C, 3 and 4 reports were read against this worktree; the Stage 1-4 source and dirty state were verified. Earlier untracked work was preserved, not claimed as newly integrated or committed.

Main remains at Customer Profile commit `1f6385c`, with Dashboard commit `fae2ebf` preserved. Its pre-existing dirty files remain unchanged: `docs/promotion-benefit-design-conflict.md`, `docs/promotion-pilot-release-runbook.md`, and untracked `docs/hybrid-promotion-v1.md`. No merge, cherry-pick, commit, push, deployment, Apache/network configuration change or operational project edit was performed. The paused Invoice UI redesign was not resumed; invoice-view changes add document access only.

The documented disposable instance was restarted hidden on loopback port **58171**, schema `dharma_sales_hybrid_50b64bc4a06f471ebcc14f17294f395e_test`. Its exact data directory, run identity, native prepares and schema-only runtime grants were reverified before access. No default application connection factory, operational backup or operational data was used by the test harness. Ports **3306 and 3309 were not accessed**.

Synthetic fixture controls were enabled only through injected test configuration and auditable disposable control events, then disabled. Source configuration remains disabled, the financial posting hold remains true, and operational database/product binding allowlists remain empty. The source writer's physical CLI isolation guard was not weakened. Generated accounts, backups, imports, databases, screenshots and browser profiles stay under ignored, unserved storage; none was staged or committed.

## Exact Stage 5 files

New application source:

- `app/Services/HybridDocuments.php`
- `app/Views/hybrid-document.php`
- `app/Views/hybrid-document-links.php`

Modified existing application source:

- `app/Services/InvoiceDocuments.php` - explicit Hybrid render dispatch and consistent read-only linked-return snapshot.
- `app/Services/Invoices.php` - display-safe immutable return/void identities and amounts in scoped Hybrid details.
- `app/Services/Reports.php` - separate Hybrid benefits, product benefits, representative activity, residual-void and stock reports.
- `app/Controllers/InvoiceDocumentController.php` - scoped linked-return routes, optional read-service injection and Hybrid thermal/A4 PDF selection.
- `app/Controllers/ReportController.php` - explicit new report route names.
- `app/Support/LocalPdf.php` - fixed allowlisted Node fallback for this host, retaining the existing local Edge process, resource blocking and private temporary streams.
- `app/Views/invoices.php` - links to finalized linked-return/residual-void documents, independent of new-posting gate availability.

New tests:

- `tests/hybrid-stage5-fixtures.php`
- `tests/hybrid-stage5-documents.php`
- `tests/hybrid-stage5-reporting.php`
- `tests/hybrid-stage5-recovery.php`
- `tests/hybrid-stage5-pdf.php`
- `tests/hybrid-stage5-browser.php`
- `tests/hybrid-stage5-browser.js`
- `tests/hybrid-pdf-render.ps1`

Modified existing tests:

- `tests/hybrid-ui-bridge.php`
- `tests/hybrid-ui-router.php`

New report: `docs/hybrid-stage5-readiness.md`.

No migration file, source feature configuration, historical-return control, pilot setting, offline draft/queue/vault code, shared workspace stylesheet or shared Sinhala dictionary was changed in Stage 5. `app/Views/invoice-document.php` and its legacy receipt/A4 styles remain unchanged. Other Stage 1-4 dirty files remain as they were at preflight.

## Document architecture and verification

`InvoiceDocuments` dispatches a sealed Hybrid record to a dedicated renderer; legacy records retain the existing renderer. Authorized invoice scope is reloaded server-side. Return reads validate the original seal, immutable return positions, custody projection and stock linkage within a consistent read-only transaction. No current offer or product price recalculates a historical benefit. No document read allocates a number, posts a payment/return, changes stock or creates a business audit event.

Invoice documents show original paid subtotal; paid, qualified, remainder and actual free quantities; unit prices; product/replacement/invoice rates and monetary components; combined monetary discounts; original payable; actual receipt received/applied/change history; linked credits; current debt and Refund Due. Stored company/contact/footer and product language snapshots are retained. Return/residual-void documents show original invoice and immutable adjustment references, paid/free quantities, original gross/P/R/I/net reversals, debt reduction, Refund Due increment, receipt conditions and custody event/manager audit references including disposition quantity, conditions, actor and stock transaction.

Current balances and custody history are clearly separate from original monetary components. The liability notice states: **Refund Due is a liability. Actual cash refunds are not recorded by this system.** It neither claims cash was paid nor infers a zero physical refund. Operator reasons, evidence and stored company fields are printed as recorded; they are not retrospectively translated or rewritten.

Document routes:

```text
/{rep|admin}/invoices/{invoice}/receipt?paper=58|80&document_lang=en|si-LK
/{rep|admin}/invoices/{invoice}/a4?document_lang=en|si-LK
/{rep|admin}/invoices/{invoice}/pdf?layout=receipt&paper=58|80&document_lang=en|si-LK
/{rep|admin}/invoices/{invoice}/pdf?document_lang=en|si-LK
/{rep|admin}/invoices/{invoice}/hybrid-returns/{return}/{receipt|a4|pdf}
```

Both linked returns and residual voids use the final route. Finalized documents remain available with Hybrid selection/posting disabled. HTTP verification proved private `no-store` documents/PDF/CSV and denial of another representative's invoice. The document CSP stays restrictive; test fetches were moved to a normal authenticated page rather than weakening it. There is no remote PDF service or private service-worker cache addition.

All **18 final PDFs** were generated through application `LocalPdf`: invoice/return/residual void x English/Sinhala x 58mm/80mm/A4. All **68 pages** were rasterized with Windows' native PDF renderer and paper widths checked within two device-independent pixels. Poppler/Python executables were unavailable on this host; native PDF rasterization supplied independent PDF-page inspection without installing dependencies or changing system execution policy. Representative first, component, receipt-history, audit and final pages were visually inspected in each layout/language family. The Sinhala/Latin font clipping found during early review was corrected using the embedded Sinhala Unicode range and Latin fallback; long disposition text wraps without squeezing unit labels vertically.

Final synthetic packet page counts (detailed five-product invoice plus custody audit, not a claimed one-page layout):

| Language | Layout | Invoice | Linked return | Residual void |
| --- | --- | ---: | ---: | ---: |
| English | 58mm | 4 | 3 | 5 |
| English | 80mm | 3 | 3 | 4 |
| English | A4 | 3 | 3 | 4 |
| Sinhala | 58mm | 4 | 4 | 8 |
| Sinhala | 80mm | 3 | 3 | 4 |
| Sinhala | A4 | 3 | 3 | 4 |

Thermal PDFs use a finite 297mm page length, matching the existing conservative roll-paper strategy. Software paper-size/rendering success does not establish printer feed, cutter, Bluetooth transport or actual printable-dot-width acceptance. **Physical Bluetooth printing is NOT VERIFIED.**

Final document artifacts and page evidence: `storage/stage2c-d4329bccf5bc4c1db6d44f4c5dfad8d8/stage5-documents-006fb825b09b/`. Browser evidence is under the same run's `stage5-ui-*` directories. These are synthetic test artifacts, not operational invoices or deployment packages.

## Financial reporting and reconciliation

Existing issued-sales and actual-payment-date collection definitions were retained. New reports are separate and retain current actor/customer/invoice scope:

- `hybridbenefits`: immutable original invoice S/P/R/I/payable, actual free quantities and separate linked/current balances, with validity displayed.
- `hybridproducts`: original issued-product paid/qualified/remainder/free quantities and P/R/I/net amounts. Product filters select matching rows; invoice-level financial reports retain whole-invoice cohorts.
- `hybridreturns`: linked original component reversals, debt reduction and Refund Due increments by acceptance date.
- `hybridvoids`: residual voids from the same immutable linked-credit source, without pretending to be a legacy full-original reversal.
- `hybridactivity`: representative issued benefits by invoice dates, separate from return credits by their acceptance dates. Collections keep the existing separate report.
- `hybridstock`: original SALE, linked saleable restoration and manager release movements. `UNION` deduplicates transaction identities shared by return acceptance and custody. Current ledger and available quantities are shown alongside period movements; pending and non-saleable custody never invent a saleable movement.

Snapshot/header/line queries never join monetary invoice totals through allocation runs. Exact DECIMAL/string arithmetic is retained. The existing shared balance projection supplies invoice details/listing, payment acceptance, credit checks and current report debt/liabilities.

Independent full-basket evidence:

| Component | LKR |
| --- | ---: |
| Original paid gross | 5,800.00 |
| Product discounts | 412.50 |
| Replacement monetary discount | 600.00 |
| Sequential invoice discount | 478.75 |
| Combined monetary discounts | 1,491.25 |
| Original payable | 4,308.75 |
| Cash received / applied / change | 10,000.00 / 4,308.75 / 5,691.25 |
| First linked credit / Refund Due increment | 225.00 / 225.00 |
| Residual void credit | 4,083.75 |
| Cumulative credit / Refund Due | 4,308.75 / 4,308.75 |

The first credit reverses gross 300.00, P=50.00 and I=25.00 from original positions. Three paid Quarantine goods plus one recovered Unsaleable free good initially restore zero saleable stock. A manager releases one paid unit; the later residual void receives only the remaining five Glucose paid units. Glucose stock moves 500 -> 491 at issue -> 492 on manager release -> 497 on residual void; two paid Quarantine units and one Unsaleable free unit remain controlled. No original full quantity is restored twice. The stock report contains exactly 11 movement rows for the five-product packet, with deduplicated custody/return references and every current ledger quantity equal to available stock.

For a second representative's issued credit invoice, collected 100.00 leaves debt 4,208.75; a later payment of 1.00 leaves 4,207.75 in both invoice and customer/report projections. Product-level components sum back to the invoice totals. A Glucose filter retains the whole 5,800.00 invoice in the invoice report but only Glucose's 675.00 net in the product report.

## Test results and acceptance coverage

| Final suite | Result |
| --- | --- |
| Stage 5 document/report integration | 64 checks; 18 bilingual immutable HTML cases |
| Stage 5 financial/report oracle | 35 checks |
| Stage 5 real Edge documents/reports | 73 checks, including private PDF route, six CSV reports, phone layout and disabled-gate historical access |
| Stage 5 local PDF | 18 PDFs; 68 rasterized pages; all paper widths checked |
| Stage 5 migration/restore rehearsal | 20 checks; exact 007 failure reproduced twice per rehearsal |
| Checkout database regression | 17 cases / 141 checks |
| Return/custody database regression | 157 checks |
| Pure promotion engine | 20 cases / 13,533 assertions |
| Pure integration foundations | 17 cases / 2,635 assertions |
| Pure return/custody policy | 34 checks |
| Existing representative checkout real Edge | 56 checks plus three database exactly-once backstops |
| Existing return real Edge | 13 checks plus credit/stock database backstop |
| Existing controller integration | 28 checks |
| Legacy invoice regression | 26 checks, including unchanged full-original void and replay |
| PROMO_CHOICE_V1 regression | 36 checks, including full void; source pilot/hold configuration unchanged |
| Existing historical-return pure arithmetic | 6 checks |
| `tests/run.php` | 12 checks |
| Guarded dedicated `tests/database.php` | 16 constraints, checksum repeatability, UTC/native prepares; fixtures rolled back |
| JavaScript domain regressions | Hybrid UI 5; invoice discounts 4; mixed-oil 3; offline encryption/reconciliation 8 |
| Syntax/whitespace | PHP source/tests and new browser JS passed; tracked diff whitespace check passed |

Coverage of the owner's end-to-end list:

| Requested area | Evidence |
| --- | --- |
| 1, 23: active representative/customer/source scope | Two representatives, assignment/deactivation/spoofing tests, current-scope document denial and admin controls |
| 2-9: product selection, zero defaults, Glucose 6/1, Suwada 12/1, groups, remainders, independent rates, sequential discount | Pure independent oracles, real checkout database and 56-check representative UI |
| 10-13: strict cap, replacement, conflicts/stock shortages, review hash/exactly-once issue | Checkout database plus independent unit oracle, actual competing PHP workers and injected rollback |
| 14: documents/PDF | 18 bilingual layouts/PDFs, paper-size checks and authenticated HTTP download |
| 15: debt/payments | Checkout and 35-check financial/report oracle |
| 16-22: A1-E1, custody, manager disposition, Refund Due, repeated returns, residual void, concurrency/rollback | 157 database checks, 34 pure checks and 13-check return browser |
| 24: online-only/legacy signed drafts | Strict Hybrid offline rejection and unchanged offline code; eight existing offline domain/encryption tests |
| 25: legacy policies and historical returns | Legacy 26, pilot 36, historical arithmetic 6; full historical posting suite BLOCKED below |

The final reruns have no unexplained test failures. Early font-layout defects and browser-test context/locale assumptions were corrected and rerun. Browser runs needed the reviewed elevated test command because sandboxed Edge GPU processes were denied access; the browser sandbox itself was not disabled. Synthetic database failure tests retain ordinary auto-increment gaps and never renumber financial identities.

## Migration 007 findings and proposed remediation - implementation stopped

**Exact cause:** migration 006 adds `customers.city_town` with its table's `utf8mb4_general_ci` collation. Migration 007 explicitly creates `location_towns` with `utf8mb4_unicode_ci`, including generated `name_key`. Its final join `t.name_key=LOWER(TRIM(c.city_town))` compares incompatible implicit collations and raises MariaDB **1267** even with no customers. District mapping is not the mismatch: `location_districts` retains the general collation.

The unchanged chain reproduced this twice on separate newly named empty schemas on the verified disposable instance. Both preserve the 001-006 applied journal and 007 `applying` evidence; 025-027 are never reached on that pristine chain. Creating the database itself with unicode collation does not correct a table that explicitly chooses the charset's default general collation. Migration 007 SHA-256 remains `f46167f2b163ed69b351e61cb0bebfc30804db0713d1e22973f6e96d7a94800a`.

025-027 have no new dependency on that comparison. They work on the documented earlier empty compatibility baseline and survive backup/restore, but this does not turn the complete fresh-install chain into a pass. A later migration cannot repair an earlier migration that prevents the runner from reaching it.

Proposed separately reviewed work:

1. Specify a versioned, checksum-verified fresh-install bootstrap compatibility step executed between 006 and unchanged 007. Require exact expected schema/column/collation and an empty new customer table; run the original 007 normally afterward and record success only when its SQL really finishes.
2. Test clean installs under both expected server collation defaults, repeatability, failure recovery and the existing deployed journal/checksum cases. Never silently skip 007 or mark a failed migration applied.
3. Treat any already partially applied installation as a distinct, backed-up reconciliation case; do not blindly rerun DDL or delete its journal.
4. If existing installations need later collation normalization, design a separate forward repair with data/collation audit and restore rehearsal. It is not a substitute for the fresh-install bootstrap step.

This requires a separate migration-runner/bootstrap code change and review. As instructed, that portion stops at the proposal. No repair, deployed migration rewrite or operational DDL was performed.

## Migration 025-027 and synthetic restore

All three unchanged migration checksums matched the existing applied journal and repeated migration checks. A synthetic-only full backup was restored into a newly generated `dharma_sales_hybrid_<32 hex>_test` schema on the same physically proved disposable server. A new restore runtime account has only that schema's DML grants; physical scope and run identity were reverified. Existing disposable trigger definers were preserved, with test-only DDL privilege on the new restore schema; no operational account/grant was read or changed.

The immutable original backup hash was checked after a separate import-copy restore. Every table's canonical row count/digest matched, source rows were unchanged, original/returned unit and financial projections reconciled, current stock matched ledgers, update immutability remained enforced and 025-027 journal checksums matched. Restored feature controls were disabled and finalized return documents remained readable. Recovery evidence and original/import SQL remain in ignored `stage5-recovery-*` storage. This is not an operational-data restore rehearsal or an operational backup claim.

| Migration | SHA-256 |
| --- | --- |
| 025 | `662054eb3025354eff03c9da928e3b618fa433a4ca69a8b6632342924d45f2c1` |
| 026 | `912b831ea2d3674bee50ef7b6dfd3852683fec2b00c921c5f419f5457a137d5f` |
| 027 | `1e00ad4388f60c4b130d560d6cbcebb83489e8d43c9033bfc4d6e54faca24fac` |

## Sinhala review and protected UI compatibility

Hybrid document labels use a private document dictionary and embedded local Noto Sinhala fonts; unknown Sinhala document keys fail explicitly instead of silently becoming English. Browser language/font checks, Sinhala product snapshots, native PDF rasterization and visual text/amount review passed. Stored company/operator data retains its original language. A native-speaker financial/legal wording sign-off was **not performed**. The existing Hybrid checkout/return UI still contains English copy; this stage does not claim complete Sinhala UI localization or resume the paused UI redesign.

The eight protected Dashboard/Customer/shared files in main and the six corresponding files present in the old isolated baseline all retain their preflight hashes: **14 protected file hashes unchanged**. The two newer view files absent from the old worktree were not imported. Source changes have no direct overlap with the two approved UI commits. New document styling is inline to its standalone document; no `workspace.css` or shared `si-LK.json` change was necessary. Main's Customer Profile remains presentation-only with the same customer/billing inputs. The new invoice return metadata is additive; existing totals/identities remain stable.

This is a static compatibility finding. The combined redesign-plus-Hybrid tree was neither merged nor run, so combined runtime/visual acceptance is **NOT TESTED**. The protected commits must be preserved during any later explicitly approved integration, followed by customer/dashboard debt and navigation acceptance against Hybrid linked-credit projections.

## Release-readiness matrix

| Area | Status | Evidence / remaining limit |
| --- | --- | --- |
| Code verification | PASS | Syntax, pure/domain, database and browser suites above; isolated only |
| Financial reconciliation | PASS | Original S/P/R/I/net, debt-first returns, cash-backed Refund Due, residual void, restore |
| Stock reconciliation | PASS | Original SALE, paid/free returns, controlled custody, deduplicated movements, ledger equality |
| Role authorization | PASS | Current active actors, assignment/own-source scope, cross-rep denial, admin-only dispositions |
| Online-only enforcement | PASS | Explicit Hybrid offline rejection; no queue, draft, vault or service-worker-private-cache changes |
| Sinhala documents | PASS | Bilingual labels/fonts/amounts and all three formats rendered; native-speaker sign-off separate |
| Full Sinhala UI / wording acceptance | BLOCKED | Existing Hybrid UI English copy and native financial/legal review remain |
| 58mm / 80mm / A4 / PDF software output | PASS | 18 final PDFs, 68 rasterized pages, verified widths, authenticated PDF route |
| Physical Bluetooth printing | NOT TESTED | NOT VERIFIED on an actual supported printer |
| Complete pristine migration chain | FAIL | Exact 007 collation failure reproduced on new empty schemas |
| Migrations 025-027 on documented baseline | PASS | Frozen checksums, constraints, immutability, repeatability and restored evidence |
| Synthetic backup/restore | PASS | New schema, immutable original backup, complete row/table digest match and reconciliation |
| Operational backup/restore | NOT TESTED | Operational access/backups explicitly prohibited |
| HTTP runtime/configuration | BLOCKED | Private isolated HTTP routes pass; financial writer intentionally CLI/physical-test-only; no production HTTP authorization/preflight |
| All-authorized-representative scope | PASS | Role-based implementation and multiple scoped test representatives; operational rollout not tested/authorized |
| Feature/posting gates | PASS | Disabled source, retained posting hold, empty allowlists; historical visibility survives disable/restore |
| Legacy invoice/promotion compatibility | PASS | Unchanged legacy template, 26/36 posting/void/replay checks and existing domain regressions |
| Full historical-return posting compatibility | BLOCKED | Existing posting suite hardcodes a separate restored schema on forbidden port 3309; not executed or retargeted by weakening controls; six pure checks pass |
| Dashboard/Customer Profile preservation | PASS | Approved commits and all 14 protected file hashes unchanged; no direct source overlap |
| Combined-main UI integration compatibility | NOT TESTED | No merge/cherry-pick/combined runtime performed |
| Production rollback safety | BLOCKED | Disable/read/restore behavior proved synthetically; target-specific compatible rollout/rollback approval and rehearsal remain |

## Next actions and stop boundary

Review the concrete 007 bootstrap proposal, then separately authorize its implementation. Build a physically verified, parameterized historical-return regression adapter without widening operational pilot controls. Complete native Sinhala copy/UI acceptance, combined-tree dashboard/customer/invoice/report acceptance, target-specific HTTP/database/product-binding preflight and real printer/device testing before any release decision.

Rollback must disable new Hybrid actions while retaining schema, immutable evidence and linked-credit-aware readers, payments and reporting. Do not restore pre-Hybrid readers over accepted linked credits, erase tables, undo original financial entries or infer physical refunds. Production activation, posting-hold removal and any operational migration need separate owner authorization after the unresolved checks are addressed.

The disposable controls were disabled and only the physically verified disposable server was shut down at closeout; data/artifacts remain retained. No main integration, operational activation or genuine financial posting occurred. **Work stops after Stage 5 isolated implementation, verification and readiness reporting.**
