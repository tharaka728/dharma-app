# Hybrid Stage 5 fixture provenance resolution

27 November 2026, Asia/Colombo. Source and database-independent verification only, on branch `hybrid-promotion-v1-stage6e2-original` at HEAD `9deb82c`. Implements the remaining Stage 5 document-test expectation and fixture-provenance separation from [the compatibility review](hybrid-historical-snapshot-compatibility-review-20261127.md), using the sealed validation built in [the implementation report](hybrid-historical-snapshot-implementation-20261127.md). No integration run, deployment or activation is authorized by this report.

## Exact files changed in this task

- `tests/hybrid-stage5-cohort.php` (new): pure cohort resolver. `stage5ResolveCohort()` combines the fixture-envelope marker with the verified sealed calculation outcome and returns a `HISTORICAL` or `CURRENT` cohort with a provenance note; unknown markers, marker/mathematics mismatches and mathematically equivalent (era-ambiguous) results block with an explicit diagnostic. `stage5CohortExpectations()` holds the two fixed expectation sets.
- `tests/hybrid-stage5-documents.php`: the fixed old `225.00` return/refund assertions are replaced by cohort expectations resolved from the stored sealed snapshot via `HybridInvoiceSnapshots::interpretation()`. Unmarked artifacts are no longer silently treated as historical.
- `tests/hybrid-stage5-reporting.php`: the previous missing-marker fallback to fixed legacy totals is replaced by the same sealed-outcome cohort resolution. Unknown markers still fail closed.
- `tests/hybrid-stage5-provenance.php` (new): pure database-independent regression suite.
- This new report only; previous approval, review and implementation records are preserved unchanged.

The approved current `app/Services/HybridPromotionPolicy.php` (SHA256 `97d0ec256011567ba992d9c378b44ab030339ea27b46d159e0eda361222ebbcd`) and every pinned `app/Services/HistoricalHybridV1/` helper are unchanged; the snapshot-compatibility suite re-verifies the pinned source hashes. No application file, migration, fixture artifact or stored record was edited.

## Historical/current fixture distinction implemented

- **Verified historical fixture** (sealed calculation matches only the pinned legacy stacking revision, envelope unmarked): expectations stay the original sealed values — first three-unit return credit `225.00`, refund increment `225.00`, total credits `4308.75`, residual void `4083.75`. The missing marker is never the basis for this classification; the full-calculation sealed match is. Issuance era is still not independently established and the note says so.
- **Verified current fixture** (marked `UNPROMOTED_LINES_V1`, or unmarked but matching only the current eligibility revision): expectations are the current values — first three-unit return credit `250.00`, refund increment `250.00`, total credits `4787.50`, residual void `4537.50`.
- **Mathematically equivalent results** (both revisions reproduce the complete saved calculation): verification succeeds, but cohort selection blocks instead of inventing an era, because the Stage 5 expectation sets diverge.
- **Missing, unknown or mismatched provenance** (unknown envelope marker, marker claiming current against a legacy-compatible seal, resealed revision relabeling, tampered or incomplete snapshot evidence): blocking condition with a clear diagnostic. No cohort is selected silently.
- An envelope marker alone never classifies a fixture: the resolver always requires the sealed compatibility outcome computed from the stored snapshot.

## Safe validation completed (no database, no HTTP)

- PHP syntax (`-n -l`): all four new/modified test files PASS.
- `tests/hybrid-stage5-provenance.php`: PASS, 61 assertions — verified historical 675/225 cohort, verified current marked and unmarked 750/250 cohorts, approved mixed basket (Glucose 750, ordinary 450, eligible base 500, total 1200), equivalent-result blocking, unknown markers, marker/math mismatch, resealed revision downgrade/relabel rejection, eight tampering/incomplete-evidence mutations blocked, frozen bytes and digests unchanged after every resolution path, original unit credits 67.50/67.50/90 versus 75/75/100 and residual voids 450 versus 500 replayed from frozen snapshots.
- `tests/hybrid-snapshot-compatibility.php`: PASS, 122 assertions (unchanged, including source-pin hashes and fixed golden digests).
- `tests/hybrid-promotion-policy.php`: PASS, 26 cases / 15,348 assertions.
- `tests/hybrid-invoice-foundations.php`: PASS, 19 cases / 2,647 assertions.
- `tests/hybrid-return-domain.php`: PASS, 34 checks.
- `tests/run.php`: PASS, 12 foundation checks.
- `git diff --check`: PASS.

PHP ran as a standalone CLI binary with `-n` (no database driver loaded); the DB-backed Stage 5 document/reporting suites were lint-checked only, never executed.

## Provenance evidence and unresolved assumptions

- Cohort evidence for the DB suites is the stored sealed snapshot's full-calculation compatibility outcome (review fingerprint, allocation digest, seal digest/counts, complete calculation and unit-run comparison), never the envelope marker alone and never amounts or dates.
- Digests remain unkeyed SHA-256, not signatures: a wholly rewritten packet with every trusted reference replaced cannot be authenticated by self-hashes. Authorization, append-only storage and independently retained evidence remain essential.
- Which genuine historical cohorts exist, their issuance source/helper versions and independent seal evidence remain uninspected; establishing them needs an authorized database/artifact inspection and is unchanged by this task. No issuance date is inferred anywhere.
- Unmarked current-compatible artifacts (generated under the current policy before the marker existed) resolve to the current cohort by mathematics; no historical claim is made for them, and no existing artifact was stamped or relabeled.

## Stop point

Source-only implementation and safe validation are complete. The DB-backed Stage 5 document/reporting suites now select expectations from verified sealed outcomes and block unresolved provenance, but they still require separate authorization before any integration run. Production activation remains NO-GO. No database connection on any port, HTTP/browser request, migration, seeding, fixture preparation, real financial posting, main-branch edit, commit, merge, push, deployment or hold change occurred.
