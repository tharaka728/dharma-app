# Hybrid Promotion V1 invoice-discount eligibility: review and approval package

27 November 2026, Asia/Colombo. Review recommendation only; no execution or activation approval is granted by this document.

**Recommendation: conditional GO for a separately authorized isolated integration follow-up, beginning with test/fixture review. NO-GO for running the existing integration suites unchanged, and NO-GO for production activation.** No application-code correction is identified by this review. Integration evidence remains incomplete.

## Scope and exact change inventory

Reviewed worktree: `C:/xampp/htdocs/dharma chemical sales app/storage/hybrid-stage6e2-original`; branch `hybrid-promotion-v1-stage6e2-original`; HEAD `ed147d41894b109fae277e8e69b40e8a3dfecb57`. Git reports 19 modified tracked files before this report, with nothing staged. The package reviews the cumulative uncommitted change, not only the latest documentation turn.

Application and synthetic tests:

- `app/Services/HybridPromotionPolicy.php` — eligible-line filtering and excluded-line preservation.
- `tests/hybrid-promotion-policy.php` — eligibility, exact example, rounding/cap and generic offer regressions.
- `tests/hybrid-invoice-foundations.php` — revised synthetic expectations and in-memory preview/revalidation/snapshot agreement.
- `tests/hybrid-return-domain.php` — revised new-calculation return expectations and retained-cap fixture.

Documentation:

- `docs/hybrid-promotion-v1.md`
- `docs/hybrid-stage1-engine.md`
- `docs/hybrid-stage2b-foundations.md`
- `docs/hybrid-stage2c-integration.md`
- `docs/hybrid-stage3-ui.md`
- `docs/hybrid-stage4-returns.md`
- `docs/hybrid-stage5-readiness.md`
- `docs/hybrid-stage6b-readiness.md`
- `docs/hybrid-stage6c-acceptance.md`
- `docs/hybrid-stage6c-readiness.md`
- `docs/hybrid-stage6d-ui-readiness.md`
- `docs/hybrid-stage6d-visual-correction.md`
- `docs/hybrid-stage6e1-audit-integration-plan.md`
- `docs/hybrid-stage6e2-original-invoice.md`
- `docs/hybrid-stage6e3-release-readiness.md`

Current guidance is corrected; affected historical stage evidence has explicit supersession notices. Old numerical examples remain evidence of the old rule, not current expected behavior. No shared allocator, schema, migration, operational configuration or launcher is changed by this diff.

This review creates only this new report. Existing untracked `docs/hybrid-stage6e7-access-authorization-request.md` and `docs/hybrid-stage6e8a-offline-evidence-checklist.md` are outside the change package and must remain untouched. Existing approval records are not overwritten.

## Approved behavior and consistency assessment

A line is invoice-discount eligible only when its free entitlement is zero and both product and replacement monetary discount amounts are zero. Any entitlement excludes the entire line, including entitlement replaced rather than delivered. A 0% product selection alone does not exclude an otherwise eligible line. Excluded lines keep their product/replacement discount and payable; line and segment invoice allocations are exactly `0.00`.

The policy retains original indexes when passing the eligible cohort to unchanged `InvoiceDiscount::apply()`. Original output line numbers, half-up invoice cents, proportional largest remainders with original-order ties and segment reconciliation are preserved. Invoice base is the eligible cohort's payable sum, not all post-promotion merchandise. The cap remains `2 * (P + R + I)_cents <= S_cents`, with S the original paid subtotal; actual free value is excluded and replacement money included. Odd-cent excess is rejected without clamping.

| Owner-verified synthetic basket | Product discount | Invoice discount | Payable |
| --- | ---: | ---: | ---: |
| Glucose 8 x 100, free entitlement 1 | 50.00 | 0.00 | 750.00 |
| Ordinary 5 x 100, no product benefits | 0.00 | 50.00 at 10% | 450.00 |
| Total; invoice-eligible subtotal 500.00 | 50.00 | 50.00 | **1,200.00** |

The updated requirements, engine guide, Original Invoice guide and synthetic tests agree on this rule. Browser source calls `/preview` and renders returned calculations; `Invoices::review()` routes Hybrid requests through `HybridInvoiceCheckout` and `HybridInvoiceResolver` to the updated policy. Owner-supplied browser evidence confirms matching server payable/UI total. This review does not claim a new browser run or inspect the running service.

Buy 12 Get 2 coverage is a generic pure-policy fixture, not authorization to change Suwada Kudu's approved 12/1 binding. Glucose remains 6/1. Existing immutable documents, invoice/payment/stock identities and signed offline bodies are not recalculated.

## Completed evidence

These are completed results from the preceding safe verification turn, also recorded in the [Original Invoice guide](hybrid-stage6e2-original-invoice.md#focused-acceptance). They were not rerun in this review.

| Check | Result |
| --- | --- |
| `tests/hybrid-promotion-policy.php` | PASS: 26 cases, 15,348 assertions |
| `tests/hybrid-invoice-foundations.php` | PASS: 18 cases, 2,642 assertions; memory/SQL-recorder only |
| `tests/hybrid-return-domain.php` | PASS: 34 checks |
| `tests/run.php` | PASS: 12 foundation checks |
| PHP lint | PASS: policy and the three modified Hybrid PHP test files |
| `git diff --check` | PASS in preceding verification and this review; Git reports existing LF/CRLF normalization notices |

PHP verification used `-n`, with only mbstring explicitly loaded where required and no database drivers. Coverage includes free-only, product-only, both benefits, no benefits, mixed invoices, replacement exclusions, zero selections, sparse indexes, ties, currency rounding, odd-cent cap rejection, exact-cap acceptance and Buy 6 Get 1 / Buy 12 Get 2 thresholds/repetition. The existing PHP 8.5 `ReflectionMethod::setAccessible()` deprecation is non-failing and unchanged.

## Five historical-stacking files: classification and required review

These are five files, not five immutable fixtures. All contain active regression requirements; old monetary literals are not automatically historical snapshots merely because they were once tested.

| File and source location | Classification | Follow-up required before execution |
| --- | --- | --- |
| `tests/hybrid-database.php:53-56,164` | **Obsolete new-calculation expectations**, plus a **current snapshot-preservation regression**. The suite issues a new default Glucose sale and expects 675.00/change 9325.00, then reads that newly sealed invoice after master changes. | New default sale should be 750.00 with change 9250.00 at tender 10000. Keep the assertion that master changes cannot rewrite its snapshot. A separately retained pre-correction snapshot may legitimately remain 675.00; do not convert stored history to the new amount. |
| `tests/hybrid-returns-database.php:12,20-32,57-63` | **Obsolete new-fixture amounts and cap scenario**, supporting **current return, idempotency, concurrency and reconciliation requirements**. `invoice4()` reviews/issues through the current policy, rather than loading a frozen historical invoice. | Review 67.50/90.00/450.00/675.00 and dependent 135.00 credits, debt/refund/report amounts. New default unit credits are 75,75,100; five remaining qualified units total 500. The old 50% replacement retained-cap scenario no longer fails merely from stacked invoice money: redesign the synthetic case without weakening the cap. Explicit historical snapshots must retain original cents if separately tested. |
| `tests/hybrid-stage5-reporting.php:5,9-14` | **Mixed provenance**: it reads stored Stage 5 fixture metadata, but also **issues a fresh credit invoice**. Fresh-sale 478.75 invoice discount, 4308.75 payable and 675 Glucose net are **obsolete expectations**. Stored return/void aggregates can be **historical-snapshot expectations**, depending on fixture origin. | Trace `fixtures-stage5.json` to `stage5Prepare()` in `tests/hybrid-stage5-fixtures.php`, which can generate fresh sales/returns/voids. Do not assume a frozen artifact. For the fresh five-line basket all lines have benefits: I=0, payable=4787.50, initial debt=4687.50 at cash 100, then 4686.50 after payment 1. Independently reconcile loaded historical aggregates; preserve whole-invoice cohorts versus matching product rows. |
| `tests/hybrid-stage6b-http.php:17-20` | **Obsolete expectations for a newly issued HTTP sale**, not archived snapshot evidence. Authentication, rollback/retry, exactly-once posting and stock checks remain **current requirements**. | Review new-sale/paid 675 and refund 67.50 backstops (new default should be 750 and one-remainder-unit refund 75). Inspect companion `tests/hybrid-stage6b-http.js:25`: product 50% plus invoice 1% now excludes the line and is not a combined-cap rejection case. Retain a valid independent cap-rejection scenario. |
| `tests/hybrid-stage6c-http.php:19` | **Obsolete fresh-sale monetary backstops** within **current authenticated runtime/timeout/conservation regressions**. The suite creates fresh invoices, including an uncertain-response case; it is not reading only old fixtures. | Review new-sale/paid/refund figures as above. Inspect companion `tests/hybrid-stage6c-http.js:29` for the same obsolete cap assertion. Preserve two-invoice timeout accounting, replay, physical stock, sealed invoice/return reconciliation and IPC/ACL/security checks. |

No file in this table, its companions or fixture builders was edited or executed in this review. Proposed numbers are source-derived expectations, not database-verified results; inspect the complete fixture/input chain before changing assertions. Stored historical snapshots remain a separate required compatibility cohort.

## Separate authorization needed for integration

Authorize test/fixture updates explicitly before invoking these suites. They are not read-only: database suites create synthetic records, issue invoices, move synthetic stock, record payments/returns/voids, inject failures and race workers; HTTP suites also alter disposable login credentials and controls and start local HTTP workers. Stage 5 fixture preparation performs synthetic custody/disposition work. Do not treat permission for a preview as permission for all these operations.

Any later execution approval must name the exact disposable schema, existing physical server/port/datadir, manifest, synthetic cohort, allowed operations and suites. No target is selected or approved here. If the existing 63842 instance is proposed, verify its approved manifest/physical identity under that later authorization; never connect to 3306/3309 or use `dharma_sales_phase1`. Do not infer provisioning, migration, seeding or permission to start another database. Preserve global/live holds; disposable test-control changes require explicit isolated scope.

After fixture review, separately authorized checks should cover new eligible/excluded mixed baskets and 1200 total; fresh versus historical snapshots; persisted segment/unit allocations; atomic stock/payment reconciliation; return/debt/Refund Due/void accounting; cap failures; replay/concurrent/uncertain outcomes; server scope and legacy shared-policy preservation. Read-only review/preview checks and mutating integration checks require distinct stated scopes. Existing historical PASS counts are not acceptance evidence for the new policy.

## Rollback and remaining risks

No rollback is performed or needed for this documentation-only review. The change is uncommitted: preserve the exact current diff and untracked files before any later reversal. If rejected before integration, review an inverse patch limited to the eligibility change and its corresponding tests/docs; never use a broad reset/clean or overwrite unrelated work. Reverting the new rule itself requires owner direction. Do not roll back invoice/payment/stock data by recalculation, deletion or renumbering; keep existing snapshots and identities. Any disposable cleanup would require separate scope and must not reset an existing schema.

Remaining risks are incomplete database/HTTP integration proof, stale coupled fixture assertions and uncertain provenance of retained Stage 5 artifacts. The policy identifier remains `HYBRID_PROMOTION_V1`; historical reads must continue using stored immutable calculation/allocation snapshots, not the revised engine. Deployment packaging, runtime/security/recovery, native Sinhala and physical-print gates from prior readiness records are not closed by this discount fix.

The recommendation supports requesting a bounded isolated integration review/test authorization. It does not authorize execution now, establish release readiness or permit live promotion activation. No database/HTTP access, migration, provisioning, seeding, posting, commit, merge, push, deployment, hold removal or main-worktree modification occurred in this review.
