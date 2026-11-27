# Hybrid Promotion V1 — Stage 2C isolated integration

> **Superseded discount behavior - 27 November 2026:** This historical stage record retains its original examples, test counts and evidence. Any instruction or example applying invoice discount to a line with free entitlement or nonzero product/replacement discount is superseded by the [approved eligibility correction](hybrid-stage6e2-original-invoice.md#approved-eligibility-correction-and-owner-verification---27-november-2026). Such lines now receive invoice discount 0.00; a 0% product selection alone does not exclude a line. Glucose 8 at 100 with remainder discount 50 remains payable 750 even at invoice rate 10%; adding 5 ordinary unpromoted units at 100 gives eligible subtotal 500, invoice discount 50 and total 1,200. Existing immutable snapshots and historical results are not rewritten; the strict combined 50% cap remains. Old screenshots and PASS counts do not verify the new rule.

Completed 26 November 2026 in `storage/hybrid-stage1`, branch
`hybrid-promotion-v1-stage1`, baseline commit `80331eb`. Stage 2B source and its
reported tests were inspected and reproduced before edits. Existing uncommitted
work in both checkouts was preserved. No files were staged, committed, merged or
deployed. **This is isolated verification, not live activation or production
readiness.**

## Exact source file changes relative to Stage 2B

New application files:

- `app/Services/HybridDatabaseScope.php`
- `app/Services/PdoHybridCheckoutStore.php`
- `app/Services/HybridInvoiceReader.php`
- `app/Services/HybridInvoiceReconciliation.php`

Modified application files:

- `app/Services/Invoices.php`: optional server-side coordinator injection for
  isolated integration; real writer selection behind the unchanged disabled
  master config; scoped Hybrid details and current financial projections.
- `app/Services/HybridInvoiceResolver.php`: authoritative actor/source names and
  versions, number owner, due date and credit-term snapshots.
- `app/Services/HybridInvoiceSnapshots.php`: canonical comparison of seal child
  counts after JSON serialization.
- `app/Services/PdoHybridInvoiceContext.php`: shared context for the writer,
  mandatory physical-server proof and preserved transaction/savepoint behavior.
- `app/Services/PdoHybridInvoiceSnapshots.php`: mandatory physical proof and
  after-insert failure-injection hook used only by the isolated test adapter.
- `app/Services/HybridCheckoutStore.php`: updated adapter contract comment.
- `app/Services/HybridInvoiceCheckout.php`: updated isolated-development errors.

New isolated test/lifecycle files:

- `tests/hybrid-isolation.php`
- `tests/hybrid-bootstrap-isolated.php`
- `tests/hybrid-isolation-evidence.php`
- `tests/hybrid-harden-isolated.php`
- `tests/hybrid-migrate-isolated.php`
- `tests/hybrid-prepare-disposable-baseline.php`
- `tests/hybrid-fixtures.php`
- `tests/hybrid-database.php`
- `tests/hybrid-post-worker.php`
- `tests/hybrid-legacy-regression.php`
- `tests/hybrid-foundation-database.php`
- `tests/hybrid-stop-isolated.php`

Modified test: `tests/hybrid-invoice-foundations.php`, adding a JSON round-trip
regression and replacing the unverified PDO recorder's successful-write assumption
with rejection tests for missing physical-server proof. That write contract is now
covered by real isolated database tests.

New report: `docs/hybrid-stage2c-integration.md`.

Stage 1 arithmetic, B1 allocation arithmetic, legacy calculators, existing offline
code, all configuration files and migration files were unchanged during Stage 2C.
In particular, migrations 007, 016, 024 and the Stage 2B draft 025 were not edited.

## Isolation evidence

The ordinary `config/database.php` defaults to port 3306 and the old regression
wrappers target port 3309. Neither connection path was used for the new database
suite. The harness uses no `.env`, operational private configuration, login file,
backup, restored operational records or existing application database connection.

A new MariaDB **10.11.16** instance was initialized without installing a Windows
service and started hidden, bound only to `127.0.0.1`:

```text
Port:          58171
Launcher PID:  25124
Database:      dharma_sales_hybrid_50b64bc4a06f471ebcc14f17294f395e_test
Runtime user:  hybrid_rt_6b257c5e0f@127.0.0.1
DDL user:      hybrid_ddl_94808e6a4f@127.0.0.1
Run directory: storage/stage2c-d4329bccf5bc4c1db6d44f4c5dfad8d8
Data directory: <isolated worktree>/<run directory>/data
```

Only this new server's account/grant setup was changed. Initial MariaDB bootstrap
included PUBLIC grants on `test` and `test\_%`; the preflight rejected those grants
before migrations/tests. They were revoked on this newly created instance, and
the runtime account was reverified. Loopback root passwords on the new instance
were set to generated test-only values. No original/operational grants were read
or changed.

Runtime grants, with account authentication information omitted:

```text
GRANT USAGE ON *.*
GRANT SELECT, INSERT, UPDATE, DELETE
  ON `dharma_sales_hybrid_50b64bc4a06f471ebcc14f17294f395e_test`.*
```

Before database execution, the harness checked explicit manifest path, generated
schema identity, exact `@@port`, physical `@@datadir`, a run-identity row, native
PDO prepares and runtime grants. Access to `dharma_sales_phase1`, `mysql` and
default `test` schemas was denied; runtime CREATE DATABASE was denied. The newly
initialized server had no operational schema or operational data files. There is
no DSN fallback: host is fixed to loopback and both 3306 and 3309 are rejected
before connection. The isolated migration account has schema-scoped DDL rights;
only the new server's bootstrap/shutdown lifecycle uses its new admin account.

`HybridDatabaseScope` also makes physical proof mandatory in application context
transactions and all real writer/persistence operations. A schema name containing
`test` alone is insufficient. The current writer requires CLI execution, an exact
manifest/schema/port/data-directory identity, the generated run marker and scoped
DML grants. It refuses an operational PDO connection even if a feature config is
accidentally edited. No PHP HTTP server, public deployment, firewall, global
Apache configuration or operational MariaDB service was changed.

The temporary server was shut down after verification. Its data, manifest,
synthetic fixtures, configuration and logs are retained under the run directory.
`accounts.json` contains generated test-only credentials and stays under ignored,
unserved `storage/`; do not commit it. Nothing was deleted or reset.

## Migration results and the migration 007 blocker

The first fresh-schema run applied migrations 001–006 and reproduced the known
007 error: **1267, illegal mix of `utf8mb4_unicode_ci` and
`utf8mb4_general_ci`**. Its partial journal was preserved and investigated. The
unmodified 007 joins the unicode `location_towns.name_key` to the general-ci
`customers.city_town` column.

To prepare an explicitly documented **empty disposable compatibility fixture**,
`hybrid-prepare-disposable-baseline.php` verified:

- The exact isolated server/schema and original 007 checksum.
- Journal status `applying`.
- Zero customers and exactly 30 seeded lookup towns.
- The expected `customers.city_town` general-ci collation.

It changed that column to unicode-ci **only on this disposable schema**, replayed
only the diagnosed UPDATE tail, recorded the fixture amendment in
`hybrid_fixture_amendments`, and completed that isolated journal entry. It did not
rewrite 007 or propose this as a production/fresh-install fix. No blind rerun or
database reset was used. **A pristine full fresh-install migration pass remains
blocked by 007.**

Migrations 008–025 then applied successfully on the documented fixture. Migration
025 was executed exactly as prepared in Stage 2B:

```text
025_hybrid_invoice_snapshots.sql
SHA-256: 662054eb3025354eff03c9da928e3b618fa433a4ca69a8b6632342924d45f2c1
```

Its nine InnoDB tables and twenty triggers were accepted by MariaDB. Tests verified
foreign keys, policy/rate/quantity checks, exact odd-cent cap rejection, unit
component conservation, update/delete immutability, seal-last writing and rejection
of late child inserts. Actual error codes were checked (FK 1452, CHECK 4025,
immutable trigger 1644 and sealed-parent NOT NULL guard 1048), so syntax errors
cannot masquerade as successful constraint tests.

Interval overlap is an application invariant, not a false claim about ordinary
UNIQUE keys. A test injected a distinct-start overlapping unit run while the
invoice was unsealed; the writer's physical child-count/reconciliation check
rejected it and the entire posting rolled back. Readers independently compare
every stored line, segment and run against the regenerated sealed originals.

The standard `tests/database.php` was also run through an explicitly scoped child
environment using the new schema's migration account. It passed **16 constraint
checks**, two migration repeatability passes, UTC and native-prepare checks. No
`--existing-app`/3309 regression wrapper was used.

## Real writer integration

`PdoHybridCheckoutStore` implements the existing coordinator port using actual
official invoice, item, numbering, stock, payment, receipt, language/company
snapshot, change-stream, audit and command-result tables. It does not mint a new
invoice-number system or reinterpret legacy policy snapshots.

The coordinator performs current authorization, scope checks, review verification
and exact Hybrid recalculation inside one transaction. The writer preserves the
existing relative lock order and shared inventory/promotion gates, uses the
existing lifetime sequence counter and configured business prefix, and posts
paid plus actual free stock only. Monetary mapping is original gross subtotal,
automatic monetary discount zero, manual/total P + R + I and final net payable.
Replacement remains a separate approved cohort; actual free value never enters
the monetary cap.

Payments use posted applied cash only; change stays in receipt fields. All official
rows, snapshots, the final seal and command result commit together. Recognized
1205/1213 transient conflicts retry only an owning transaction, using the same
request identity; nested callers keep savepoint behavior. Existing financial and
offline UUID collisions are checked before claiming a Hybrid command. Accepted
scoped replay is checked before new-posting gates and cannot reissue stock, money
or numbers. New commands fail closed under either the Hybrid gates or independent
isolated posting hold.

The default application constructor still loads the unchanged disabled Hybrid
configuration. Only the isolated CLI tests inject a coordinator with test-scoped
configuration and physical-server proof. Operational master/action switches,
database/binding allowlists, nissanka pilot and existing financial holds remain
unchanged. This writer cannot currently be used for operational HTTP posting.

## Financial readers and reconciliation

`HybridInvoiceReader` reloads the active actor and applies the existing invoice
owner/customer-assignment scope. It does not depend on a feature being enabled to
read an already-issued record. Its repeatable-read snapshot covers the seal,
original inputs, every physical monetary projection and current payment/void state.
Standalone reads roll back their own read transaction; calls inside an owning
financial transaction reuse it.

`Invoices::get()` exposes a display-safe Hybrid calculation, seal digest and
financial projection without sending all internal allocation runs. Its Hybrid
paid/outstanding/Refund Due/payment status and receipt fields use the same
consistent reader projection. Existing payment posting and customer debt aggregate
continue using original official totals and actual posted allocations; no
hypothetical linked-return credits are invented.

Linked returns and residual Hybrid voids are **not implemented**. Readers explicitly
label linked returns `NOT_IMPLEMENTED` and fail closed if future Hybrid adjustment
tables appear without a supported reader. A Hybrid/historical-noncash combination
is rejected. An original full void with no linked returns retains existing admin
void semantics; its existing reversal and Refund Due are tested. This does not
implement or authorize residual full voids after partial returns.

`HybridInvoiceReconciliation` checks the sealed originals and stored physical
children, official monetary mapping, SALE movements, current stock/ledger equality,
initial receipt/change, later receipt/payment/allocation identity and posted
collections. An allocation without an authoritative receipt is detected.
Refund Due is a liability, and physical cash refunds remain `UNAVAILABLE`.

The database round-trip caught and fixed a Stage 2B issue: seal child counts used
strict associative-array ordering even though canonical JSON sorted object keys.
Canonical comparison now preserves the digest/count check across persistence; a
pure JSON round-trip regression covers it.

## Verification results

| Group | Result |
| --- | --- |
| Real isolated database integration | 17 cases / 140 checks |
| Updated pure Stage 2B foundation suite | 17 cases / 2,635 assertions |
| Unchanged Stage 1 calculator | 20 cases / 13,533 assertions |
| Pure foundation | 12 checks |
| Standard database constraints/repeatability | 16 checks |
| Real legacy invoice posting | 20 checks |
| Real PROMO_CHOICE_V1 posting | 30 checks |
| Existing JavaScript invoice discount / mixed oil / offline | 4 / 3 / 8 tests |
| PHP syntax / whitespace checks | Passed |

The updated foundation count differs because the permissive PDO recorder write
test was replaced by mandatory physical-proof rejection tests; the real database
suite now verifies those writes. Stage 1 arithmetic and its test file were not
changed.

Real database coverage includes corrected 6/1 and 12/1 repeating offers, remainder
discounts, initial zero selection, 25/30/35/Other to 50, sequential invoice discount,
strict odd-cent cap, complete replacement and partial-shortage rejection,
monetary-promotion and mixed-oil conflicts, two authorized representatives, source
spoofing, assignment changes, deactivation, stale prices/customer terms, sealed
historical reads after catalogue changes, duplicate replay, disabled gates and
full original void/liability reconciliation.

Actual rollback was injected after fourteen write boundaries: claim, numbering,
invoice, items, stock, payment, snapshot parent/lines/segments/runs, seal, streams,
audit and command result. Hashes of all relevant pre-existing business rows
remained unchanged after each failure. Transactional official sequence counters
did not advance; ordinary auto-increment implementation gaps are not renumbered.

Two separate PHP processes contended on the actual shared inventory gate. For the
same UUID, exactly one invoice/stock/payment/sequence effect occurred and the other
process replayed it. For different UUIDs competing for exactly nine paid-plus-free
units, exactly one command succeeded, the rejected command left no claim, and
stock reached zero without becoming negative.

Legacy regressions use the original legacy services in separate child processes.
They cover ordinary invoices, `INVOICE_PERCENT_V1` including its unchanged 100%
limit, and all three `PROMO_CHOICE_V1` modes including an unchanged legacy 75%
custom rate. The pre-existing legacy test overrides were confined to a verified
isolated child process and its exact schema; configuration files and operational
pilot/hold settings were not edited. Browser/hardware/production behavior is not
claimed by these service tests.

## Reproduction and lifecycle

Use a newly initialized isolated instance and a manifest under an ignored
`storage/stage2c-<32 hex>` run directory. Initialization in this session used the
installed `mariadb-install-db.exe --datadir=<new data directory> --port=<new port>
--skip-networking`, followed by a separate private loopback server config. No
Windows service was installed. Root bootstrap and account passwords are never
command-line arguments. Do not substitute an operational manifest or copy real
application data into the fixture.

The CLI sequence, with `<manifest>` naming the exact run manifest, is:

```text
php tests/hybrid-bootstrap-isolated.php <manifest>
php tests/hybrid-isolation-evidence.php <manifest>
php tests/hybrid-migrate-isolated.php <manifest>
# If and only if the documented empty 007 collation failure is reproduced:
php tests/hybrid-prepare-disposable-baseline.php <manifest>
php tests/hybrid-migrate-isolated.php <manifest>
php tests/hybrid-database.php <manifest>
php tests/hybrid-legacy-regression.php <manifest>
php tests/hybrid-legacy-regression.php <manifest> --benefit
php tests/hybrid-foundation-database.php <manifest>
php tests/hybrid-stop-isolated.php <manifest>
```

Database-suite reruns append uniquely named synthetic fixture sets and retain
earlier data. They never reset a database. The bootstrap refuses existing account
files and unexpected existing application schemas. `hybrid-harden-isolated.php`
is a recovery/preflight helper for the newly generated instance; its explicit
initial-bootstrap option is not an operational admin recovery tool.

## Remaining blockers and next recommended stage

1. Migration 007 still prevents a pristine fresh installation. A separately
   reviewed forward/bootstrap compatibility strategy is required; the empty
   fixture amendment is not a production fix. Existing cross-worktree newline
   differences also require deployed-byte checksum preflight before any migration.
2. The writer is intentionally CLI/physically isolated only. Operational bindings,
   trusted runtime scope, deployment preflight, feature activation and all-user
   release require separate authorization. No actual operational IDs were inspected.
3. Linked returns, custody, retained-cap acceptance and residual full voids remain
   unavailable. Noncash returns and unsupported exceptions continue to fail closed.
4. Version-aware invoice UI, printed documents/PDF, detailed P/R/I reporting and
   operational reconciliation tooling still need separately authorized completion
   and end-to-end acceptance. Existing Reports definitions were not changed.
5. Review/track the isolated uncommitted source baseline. Run broader target-host,
   backup/restore, browser/device/printer acceptance before any later rollout.

Recommended next stage: a separately authorized Stage 2D for the remaining online
UI/document/report integration and acceptance, with an explicit choice on the
separate linked-return/custody phase. No next stage, deployment, migration on an
operational server, pilot expansion or financial activation is authorized here.
Work stops after Stage 2C implementation and isolated verification.
