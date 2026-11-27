# Historical Hybrid snapshot compatibility implementation

27 November 2026, Asia/Colombo. Source and database-independent verification only, in `storage/hybrid-stage6e2-original`. Implements the bounded design in [the compatibility review](hybrid-historical-snapshot-compatibility-review-20261127.md); existing review/approval records remain unchanged. No integration run, deployment or activation is authorized by this report.

## Exact application and test changes in this task

Modified application files:

- `app/Services/HybridInvoiceSnapshots.php`: sealed-read validation, bounded evaluator dispatch, equivalent-result status, current-only construction/persistence assertion.
- `app/Services/HybridInvoiceResolver.php`: server-stamped future review revision before hashing.
- `app/Services/PdoHybridInvoiceSnapshots.php`: enforce the current revision at new persistence.
- `app/Services/HybridInvoiceReader.php`: return the verified stored policy identifier rather than the current constant.

New read-only namespace files, all under `app/Services/HistoricalHybridV1/`:

- `HybridPromotionPolicy.php`
- `InvoiceDiscount.php`
- `PromotionCalculator.php`
- `HybridUnitAllocations.php`
- `BenefitPolicy.php`
- `Inventory.php`
- `Products.php`
- `HybridInvoiceRequest.php`
- `MixedOil.php`

Modified tests:

- `tests/hybrid-invoice-foundations.php`: current server revision assertion and accepted historical replay while disabled/held, with no state mutation.
- `tests/hybrid-return-domain.php`: synthetic current snapshot helper supplies the server review metadata now required for new construction; its existing financial expectations remain intact.

New test and frozen evidence:

- `tests/hybrid-snapshot-compatibility.php`
- `tests/fixtures/hybrid-snapshot-compatibility/legacy-675.json`
- `tests/fixtures/hybrid-snapshot-compatibility/current-unmarked-750.json`
- `tests/fixtures/hybrid-snapshot-compatibility/current-marked-750.json`
- `tests/fixtures/hybrid-snapshot-compatibility/mixed-marked-1200.json`
- `tests/fixtures/hybrid-snapshot-compatibility/equivalent-unmarked.json`
- `tests/fixtures/hybrid-snapshot-compatibility/legacy-replacement-810.json`
- `tests/fixtures/hybrid-snapshot-compatibility/sources.json`
- `tests/fixtures/hybrid-snapshot-compatibility/README.md`
- This new implementation report.

The approved current `app/Services/HybridPromotionPolicy.php` was not edited in this task. Its SHA256 remains `97d0ec256011567ba992d9c378b44ab030339ea27b46d159e0eda361222ebbcd`. Existing integration expectation corrections, documentation, untracked approval/preflight evidence and all other prior work remain preserved.

## Historical evaluator provenance

Pinned commit: `ed147d41894b109fae277e8e69b40e8a3dfecb57`; original policy blob: `cddfcd862909f096f06a26d7e80a626ce3d44916`. Source was read from Git before copying. The complete pure policy, invoice allocator, decimal-string calculator and unit allocator were checked against their original bodies after removing only namespace/provenance/import adaptations: all four comparisons passed.

Dependencies are frozen alongside the policy, not silently delegated to mutable current arithmetic. `BenefitPolicy` keeps original constants and pure validation/application/eligibility/revision methods only. `Products::money()`, `Inventory::quantity()`, canonical JSON/digest functions and the mixed-oil constant are extracted from the pinned original sources. Feature gates, PDO operations, configuration and environment access are not copied. Source blob IDs and LF-normalized hashes are recorded in `sources.json` and verified by the pure suite. Support exception classes remain shared. The policy still rejects mixed rules; the frozen constant does not enable them.

The legacy evaluator has no posting/HTTP/request route. It is used only in the sealed, unmarked read-validation branch. It supports this explicitly pinned calculation family, not unidentified earlier implementations.

## Validation and future-write boundaries

`reconcile()` now always requires a complete supported seal for reads. Validation checks recognized root structure, 1-100 ordered lines, review policy/unit versions, allocation version, original review fingerprint, allocation digest, seal digest/counts and original product inputs. Each candidate must reproduce the **entire** saved calculation and every segment/unit-run allocation; a total alone is insufficient. Neither the snapshot nor its review/allocations is replaced by the reconstruction.

For already-sealed unmarked V1 snapshots, exactly two supported candidates are evaluated: current eligibility and frozen legacy stacking. One full match gives that mathematical compatibility result; two full matches give `BOTH_COMPATIBLE`, without claiming an issuance era; no match rejects. Zero invoice rate alone is not enough for equivalence because the saved invoice discount base can differ. The equivalent golden instead uses an ordinary unpromoted line whose complete outputs match both rules.

Future server reviews carry `review.calculation_revision=HYBRID_INVOICE_ELIGIBILITY_V2` before the review hash is computed. Marked snapshots can use only current math; unknown/null/historical revision markers fail closed. `build()` requires this marker and validates current math before adding a seal. `PdoHybridInvoiceSnapshots::insertSealed()` independently requires a complete current marked snapshot. Browser top-level, Hybrid-object and line-level revision injection remain rejected by the existing strict request whitelist. A stale pre-change review must be refreshed for a new issue; a previously accepted command replays its old result through verified historical reading instead.

No schema change is required: the new field is inside existing review JSON and its hash/seal. Typed policy, rounding and allocation identifiers/columns stay unchanged; no old JSON gains a field. Existing PHP consumers read named review fields; JavaScript preview consumers tolerate extra returned metadata. In-memory checkout and SQL-recorder persistence-contract tests pass. Actual SQL/HTTP behavior remains unverified under this task's restrictions.

## Preservation evidence

| Frozen original | Verified original amounts and allocations |
| --- | --- |
| Old Glucose | Gross 800, product 50, invoice 75, net 675; segment invoice allocations 60/15; qualified segment net 540 and remainder net 135; unit credits 67.50,67.50,90; three-unit return 225; five-unit residual void 450; full cumulative refund liability 675 when fully cash paid. |
| Current Glucose | Gross 800, product 50, invoice 0, net 750; segment invoice allocations 0/0; unit credits 75,75,100; three-unit return 250; residual void 500. Both marked future and already-issued unmarked current snapshots validate. |
| Current mixed basket | Glucose payable 750, ordinary payable 450, eligible base 500, invoice discount 50, total 1200. |
| Old replacement | Gross 1200, replacement 300, invoice 90, net 810; original two-unit credit 135 is preserved. |

Repeated validation leaves serialized arrays and frozen file hashes unchanged. Tests preserve original debt-first examples: old net 675/cash 575/two-unit credit 135 gives debt reduction 100 and refund 35; current 750/650/150 gives 100/50. Fully paid historical amounts do not acquire a new-policy debt. Historical accepted sale replay is exercised under disabled/held controls and returns the original identity/result with unchanged in-memory state.

Existing read, payment/replay, return, refund-liability and document paths continue through the same reader/reconciler and retain stored amounts. The reader's physical projection comparisons, official money mapping, payment/stock conservation and server scope are unchanged. Return planning consumes saved original unit positions, never reconstructed current prices. No financial record, signature, official ID, stock row or accepted event was rewritten or backfilled.

## Independent frozen evidence and tests executed

Golden historical arrays were captured once from the pinned original Git code in an isolated reference namespace, not from the new compatibility implementation. Current-case component cents were specified independently (Glucose 750; ordinary 450; mixed 1200), and original pinned unit allocation produced fixed runs. All metadata is synthetic. No database fixture builder, application bootstrap or PDO construction was used. These are source-controlled JSON test artifacts, not operational fixture preparation.

All six unsigned canonical digests were independently checked with a separate JSON ordering routine and .NET SHA256. Expected digests are hard-coded in the test and listed in the fixture README. Tests do not regenerate expected originals from the evaluator under test.

| Safe check | Result |
| --- | --- |
| PHP lint | PASS: all 16 new/modified PHP files (four application files, nine frozen helpers, three test files) |
| `tests/hybrid-snapshot-compatibility.php` | PASS: 122 assertions; old/current/equivalent full matches, fixed digests, source pins, unchanged originals, returns, historical exact/odd-cent/stacked cap checks, tampering, resealed mismatches, missing seals, unknown revisions, no downgrade and client revision rejection |
| `tests/hybrid-invoice-foundations.php` | PASS: 19 cases / 2,647 assertions; current metadata, historical accepted replay, unchanged held state, preview/revalidation, SQL-recorder persistence and existing safety invariants |
| `tests/hybrid-promotion-policy.php` | PASS: 26 cases / 15,348 assertions; approved current eligibility, mixed 1200, rounding/ties and strict 50% cap |
| `tests/hybrid-return-domain.php` | PASS: 34 checks; current original-net and retained-cap invariants |
| `tests/run.php` | PASS: 12 foundation checks |
| Source pin body comparisons / independent SHA verification | PASS: four complete original bodies; six golden unsigned digests |
| `git diff --check` | PASS; existing LF/CRLF normalization notices remain |

PHP test runs used `-n`; only mbstring was loaded for suites needing text validation. No database driver was loaded. The existing non-failing PHP 8.5 reflection deprecation in the foundation suite remains unchanged. No integration suite or HTTP/browser action was executed.

## Remaining limits and stop point

- Existing review/allocation/seal digests are unkeyed SHA256, not new digital signatures. Tampering relative to original trusted digests and incompatible self-rehashed packets is rejected; wholesale replacement of a valid packet and every trusted reference cannot be authenticated by a self-hash alone. Authorization, append-only storage and independently retained evidence remain essential. No signature/backfill claim is made.
- Actual historical cohorts and their physical projections have not been inspected. Only the pinned historical family is supported; other versions/mismatches fail closed and require evidence rather than guessed interpretation. No issuance date is inferred.
- The Stage 5 document suite still has fixed old 225 return/refund assertions. Genuine old fixtures retain those amounts; new current fixtures need 250. Unmarked Stage 5 artifact provenance remains unresolved; a fixture-envelope marker is not invoice authority. No unrelated fixtures or document tests were edited to manufacture acceptance.
- Database/HTTP integration, real payments/receipts, grants, concurrency and document rendering remain unverified. Future arithmetic changes need a new explicitly reviewed revision/frozen evaluator; do not mutate the pinned helper family or update evidence hashes to hide changed behavior.

The source-only compatibility fix and safe tests are complete. Stop for review. Integration testing still requires separate authorization; production activation remains NO-GO. No database connection on any port (including 63842, 3306 or 3309), HTTP request, migration, provisioning, seeding, database fixture preparation, real financial posting, main-worktree edit, commit, merge, push, deployment, activation or financial-hold change occurred.
