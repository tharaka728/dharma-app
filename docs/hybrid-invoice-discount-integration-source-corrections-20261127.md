# Hybrid invoice eligibility: source-only integration corrections

27 November 2026, Asia/Colombo. Follow-up to [the approval package](hybrid-invoice-discount-eligibility-approval-20261127.md). No integration test execution or activation approval is implied. Work is confined to `storage/hybrid-stage6e2-original`.

## Exact files changed in this task

- `tests/hybrid-database.php`
- `tests/hybrid-returns-database.php`
- `tests/hybrid-stage5-reporting.php`
- `tests/hybrid-stage6b-http.php`
- `tests/hybrid-stage6c-http.php`
- `tests/hybrid-stage6b-http.js`
- `tests/hybrid-stage6c-http.js`
- `tests/hybrid-stage5-fixtures.php`
- This new report only; previous approval records and documentation are preserved.

The application policy, schemas, migrations, configuration, primary worktree, stored fixtures and actual financial records were not edited. `tests/hybrid-fixtures.php` was inspected, not changed. Its generated prices are 100.00, default Glucose quantity 8/product 25%/invoice 10%, cash tender 10000, Glucose 6/1, Suwada 12/1, and replacement paid stock 24 with rule 12/1. No fixture function was invoked.

## Source provenance and revised assertions

`hybrid-database.php` calls `hybridFixtures()` and `hybridInput()` for fresh sales. Its later immutable-reader assertion reads the invoice issued earlier in that same run; it is not a pre-correction historical invoice. `hybrid-returns-database.php::invoice4()` likewise reviews and issues new sales. Both HTTP JavaScript `sale()` functions construct the same fresh Glucose input. Those expectations must follow the new policy.

For these inputs: Glucose gross 800, product discount 50, free entitlement 1, invoice discount 0, net 750. Remainder-first unit values are 75,75,100,100,100,100,100,100. Paid/free stock quantities remain unchanged.

| File / assertion or fixture | Previous | Revised | Calculation rationale |
| --- | --- | --- | --- |
| `hybrid-database.php`: review payable, persisted grand total, later immutable-reader payable | 675.00 each | 750.00 each | 800 - 50; invoice allocation zero. Later master edits still must not change the newly sealed snapshot. |
| Same: receipt change | 9325.00 | 9250.00 | Tender 10000 - applied 750. |
| Same: initial credit outstanding | 575.00 | 650.00 | 750 - initial collection 100. |
| Same: post-payment invoice/customer outstanding and unchanged outstanding after rejected legacy void | 375.00 each | 450.00 each | 650 - later payment 200. Refund Due remains 0; physical refunds remain unavailable. |
| Same: combined-cap rejection fixture | Ordinary 100 with product 50% then invoice 50%, expected rejection | Rep2 replacement 24 x 100, explicitly selected replacement 75%, expected cap-specific rejection | Ordinary is now excluded from invoice discount and has only 50 discount. New fixture gives R=1800, I=0 on S=2400; 1800 exceeds cap 1200. Rep2 retains the required replacement stock after rep1's earlier replacement sale. Exception text must identify combined monetary discount, not a stock/rate failure. |
| `hybrid-returns-database.php`: first preview credit, first refund increment, second paid-unit credit | 67.50 each | 75.00 each | Original remainder positions retain product 25% only. |
| Same: third paid-unit credit | 90.00 | 100.00 | First qualified paid position has neither product nor invoice money deducted; free recovery requirement stays. |
| Same: residual five-unit void | 450.00 | 500.00 | Five original qualified positions x 100. |
| Same: cumulative Refund Due after complete return/void | 675.00 | 750.00 | 75 + 75 + 100 + 500; same physical restoration of nine units. |
| Same: debt-first fixture cash input and refund assertion | Cash 575.00, debt reduction 100.00, refund 35.00 | Cash 650.00, debt reduction 100.00, refund 50.00 | Preserve a test of both debt reduction and refund: debt=750-650=100; two-unit credit=150; refund=50. Outstanding still 0. |
| Same: two replacement-paid-unit credit | 135.00 | 150.00 | Replacement 25% on each 100 paid unit leaves 75; no invoice stacking. |
| Same: retained-cap fixture replacement rate | 50% | 60% | Initial Glucose 800 + replacement 2400: P=50, R=1440, I=0, D=1490 <= cap 1600. Returning Glucose leaves gross 2400, discount 1440 > cap 1200. Both finding and posting rejection remain asserted. |
| Same: both three-paid-unit custody conversion credits | 225.00 each | 250.00 each | 75 + 75 + 100. Receipt conditions, free recovery, manager gates and rollback assertions unchanged. |
| Same: competing two-unit return accepted credit | 135.00 | 150.00 | Two remainder positions; exactly one competing command still accepted. |
| Same: concurrent identical return credit; listing credit/refund; refunds report liability; returns report credit | 67.50 each | 75.00 each | Same one-unit original credit propagated to each projection; replay counts preserved. |
| `hybrid-stage6b-http.php` and `hybrid-stage6c-http.php`: issued grand total and paid amount | 675.00 each | 750.00 each | Fresh default Glucose issue, fully covered by tender 10000. |
| Same: one-unit return Refund Due | 67.50 | 75.00 | Fully paid original remainder position. One/two invoice counts, zero debt, stock -8, immutable reconciliation unchanged. Stage 6B's diagnostic text now states the revised values. |
| Both HTTP `.js` companions: former cap case | Product 50% plus invoice 1% expected error | Explicit success: invoice discount 0.00, payable 50.00; plus replacement 75% cap rejection | Keep the original inputs as a positive exclusion assertion. A distinct valid replacement case checks HTTP 409 and combined-cap error text; no broad error can satisfy it. |
| Same: promoted quantity/rate matrix | Quantity/free/product component checks | Existing checks plus line invoice discount 0.00 | All matrix lines receive actual free entitlement. |
| Same: replacement 25% preview | Replacement amount 600.00 | Same 600.00 plus I=0.00 and payable 1800.00 | 24 x 100 - 600; no invoice allocation. |
| Same: owner basket preview (added) | No assertion | Base 500.00, I=50.00, Glucose I=0/net=750.00, ordinary net=450.00, total=1200.00 | Ordinary 5 at product 0% is the sole eligible line. Preview only; no additional issue is added. |

The stale-review fixture cash 575.00 near the end of the return suite remains unchanged: it tests a review fingerprint invalidated by a subsequent payment, not a fixed debt/refund amount. New debt there is 175; payment 1 still changes the reviewed state. Security, concurrent acceptance counts, exactly-once replay, stock conservation, timeout recovery, receipt conditions, report scope and immutable guards were not removed or relaxed.

## Stage 5: fresh sales versus retained artifacts

`hybrid-stage5-reporting.php` always issues a new rep2 credit sale, but separately loads rep1 invoice/return/void IDs from `fixtures-stage5.json`. That file can originate from `stage5Prepare()` via `hybrid-stage5-documents.php`, including its `--reuse` path. No retained artifact was opened, generated, rewritten or relabeled here.

The source-defined five-line basket is:

| Product fixture | Gross | Product discount | Replacement discount | Invoice discount | Net |
| --- | ---: | ---: | ---: | ---: | ---: |
| Glucose 8 | 800.00 | 50.00 | 0.00 | 0.00 | 750.00 |
| Suwada 18 | 1800.00 | 210.00 | 0.00 | 0.00 | 1590.00 |
| Replacement 24 | 2400.00 | 0.00 | 600.00 | 0.00 | 1800.00 |
| Ordinary 3 at product 30% | 300.00 | 90.00 | 0.00 | 0.00 | 210.00 |
| Partial 5 at product 12.50% | 500.00 | 62.50 | 0.00 | 0.00 | 437.50 |
| Total | 5800.00 | 412.50 | 600.00 | 0.00 | 4787.50 |

| Reporting assertion | Previous | Revised | Rationale |
| --- | --- | --- | --- |
| Fresh rep2 invoice-discount component | 478.75 | 0.00 | Every line has an excluding benefit. |
| Fresh combined discount | 1491.25 | 1012.50 | P 412.50 + R 600 + I 0. |
| Fresh original payable and issued activity payable | 4308.75 each | 4787.50 each | 5800 - 1012.50. |
| Fresh debt after collection 100 | 4208.75 | 4687.50 | Net minus collection. |
| Fresh product-filtered Glucose net | 675.00 | 750.00 | Its original product discount only. Whole-invoice financial cohort gross remains 5800. |
| Fresh invoice/report/customer debt after payment 1 | 4207.75 each | 4686.50 each | 4687.50 - 1. |
| Loaded rep1 total credits | Fixed 4308.75 | **4308.75 retained for unmarked historical expectation**; 4787.50 only for explicitly marked current artifacts | Current full return plus residual void must equal current original net; never overwrite an old artifact's totals. |
| Loaded rep1 residual-void credit | Fixed 4083.75 | **4083.75 retained for unmarked historical expectation**; 4537.50 only for explicitly marked current artifacts | Current first three-unit return is 250; 4787.50 - 250 = 4537.50. Historical difference remains 225. |

`stage5Prepare()` now returns metadata `invoice_discount_eligibility=UNPROMOTED_LINES_V1` for future artifacts it creates. Reporting chooses current loaded-artifact assertions only with this marker; unknown non-null markers fail closed. Missing markers retain the original fixed legacy assertions, not dynamic totals copied from the actual report. This preserves historical expectation values without asserting that any uninspected artifact is genuine old history. An unmarked artifact's provenance remains an execution blocker: it may have been generated under the current policy before this marker existed. Do not add the marker to existing files to make assertions pass.

## Safe validation completed

- PHP syntax only: all six edited PHP files passed using `C:/xampp/php/php.exe -n -l <file>`. Lint does not execute their database/fixture code.
- JavaScript syntax only: both edited `.js` files passed `C:/Program Files/nodejs/node.exe --check <file>`. Their fetch/issue code was not run.
- Pure policy suite: 26 cases / 15,348 assertions PASS with PHP `-n`.
- In-memory checkout/SQL-recorder suite: 18 cases / 2,642 assertions PASS with `-n` and only mbstring loaded. Existing non-failing PHP 8.5 reflection deprecation remains.
- Pure return-domain suite: 34 checks PASS with the same restricted PHP runtime.
- Additional stdin-only pure source-chain oracle: 7 assertions PASS for Stage 5 S/P/R/I/net, first credit 250, residual void 4537.50, cumulative credit 4787.50, and the revised retained-cap case. It used manually constructed synthetic arrays and pure policy/snapshot/return functions, not database fixture builders.
- `git diff --check` PASS. Existing Git LF/CRLF normalization notices are not whitespace failures.

No database connection on any port, HTTP request, browser automation, database-backed test, migration, provisioning, seeding, fixture preparation or financial posting was executed. No commit, merge, push, deployment, live activation, hold change or primary-worktree edit occurred.

## Remaining blockers and recommendation

1. **Historical snapshot compatibility is a source-level application blocker.** `app/Services/HybridInvoiceSnapshots.php::reconcile()` rebuilds the saved request using the current `HybridPromotionPolicy::calculate()` and compares the entire calculation. A pre-correction stacked snapshot (for example stored net 675) would rebuild to 750 and fail component reconciliation. This conflicts with the intended historical compatibility in the earlier approval narrative. No genuine snapshot was loaded and no application fix is made under this task's restriction. Separate review/authorization is needed for version-aware historical reconciliation and explicit frozen-snapshot regressions; never rewrite saved amounts or seals.
2. **Unmarked Stage 5 artifact provenance is unresolved.** Old loaded-artifact monetary assertions are preserved as described above. Actual target/schema/artifact contents remain uninspected; no historical or current database evidence is claimed.
3. **Out-of-scope Stage 5 document test still has old 225.00 first-return/refund expectations** in `tests/hybrid-stage5-documents.php`. With a newly prepared current fixture those should be reviewed against 250.00; with a genuine pre-correction artifact they must remain 225.00. This file is not one of the requested integration expectation files or a fixture builder and was left unchanged. It can prevent the normal preparation/test chain from completing. Do not run it unchanged on new fixtures; do not bypass its assertions to produce artifacts.
4. Persistence, concurrency, grants, actual HTTP/worker timeout and historical reporting are unverified: only source and pure calculations were validated. Prior approval and readiness records remain historical, not retroactively changed by this report.

The eight requested source files are corrected where source evidence supports the change. **GO for review of this patch; NO-GO for a complete integration acceptance claim or historical-fixture run until the blockers above are resolved under separate authorization.** A bounded fresh-fixture integration run would still need explicit server/schema/manifest and permitted-operation approval. No execution is authorized by this report and production activation remains NO-GO.
