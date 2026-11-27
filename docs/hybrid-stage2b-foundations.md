# Hybrid Promotion V1 — Stage 2B source foundations

> **Superseded discount behavior - 27 November 2026:** This historical stage record retains its original examples, test counts and evidence. Any instruction or example applying invoice discount to a line with free entitlement or nonzero product/replacement discount is superseded by the [approved eligibility correction](hybrid-stage6e2-original-invoice.md#approved-eligibility-correction-and-owner-verification---27-november-2026). Such lines now receive invoice discount 0.00; a 0% product selection alone does not exclude a line. Glucose 8 at 100 with remainder discount 50 remains payable 750 even at invoice rate 10%; adding 5 ordinary unpromoted units at 100 gives eligible subtotal 500, invoice discount 50 and total 1,200. Existing immutable snapshots and historical results are not rewritten; the strict combined 50% cap remains. Old screenshots and PASS counts do not verify the new rule.

Completed 26 November 2026 in the existing `hybrid-promotion-v1-stage1` branch and
isolated worktree `storage/hybrid-stage1`, based on commit `80331eb` and the
owner-supplied Stage 2A handoff. **Development foundations only; no activation or
production posting adapter.** No operational database was accessed. No database
was connected to, created, migrated, reset or used for these tests.

## Exact file inventory

Paths below are relative to the isolated worktree, not the main checkout.

Created for Stage 2B:

- `app/Services/HybridInvoiceRequest.php`
- `app/Services/HybridFeatureGate.php`
- `app/Services/HybridInvoiceContext.php`
- `app/Services/PdoHybridInvoiceContext.php`
- `app/Services/HybridInvoiceResolver.php`
- `app/Services/HybridUnitAllocations.php`
- `app/Services/HybridInvoiceSnapshots.php`
- `app/Services/PdoHybridInvoiceSnapshots.php`
- `app/Services/HybridCheckoutStore.php`
- `app/Services/HybridInvoiceCheckout.php`
- `config/hybrid-promotions.php`
- `database/migrations/025_hybrid_invoice_snapshots.sql`
- `tests/hybrid-invoice-foundations.php`
- `docs/hybrid-promotion-v1.md` (byte-preserving reference copy of the owner's
  existing untracked specification in the main checkout)
- `docs/hybrid-stage2b-foundations.md` (this report)

Modified for Stage 2B:

- `app/Services/Invoices.php`: explicit Hybrid dispatch before legacy normalization,
  plus rejection in synchronized review/issue and the legacy normalizer.
- `app/Services/OfflineSales.php`: reject Hybrid markers before signed-body fields
  can be discarded, including reprocessing of stored submissions.

Stage 1 inputs retained unchanged, still untracked rather than assumed merged:

- `app/Services/HybridPromotionPolicy.php`
- `app/Support/HybridCalculationException.php`
- `tests/hybrid-promotion-policy.php`
- `docs/hybrid-stage1-engine.md`

No files were staged or committed. Main-checkout changes to
`docs/promotion-benefit-design-conflict.md`, `docs/promotion-pilot-release-runbook.md`
and `docs/hybrid-promotion-v1.md` were preserved. Existing migrations 001–024,
including 007, 016 and 024, and all existing pilot/return configuration were left
unchanged. No passwords, credentials or private login files were read or copied.

## Source integration architecture

`Invoices::review()` and `issue()` recognize Hybrid-only fields recursively and
dispatch before `PROMO_CHOICE_V1` normalization. Disabled, malformed or unsupported
Hybrid requests cannot become legacy invoices. Existing controller authentication
and CSRF validation remain in place. No UI, new route or feature activation was
added. Signed offline bodies and synchronized invoice calls explicitly reject
Hybrid; no PWA asset, encrypted draft, device grant or queue was modified.

`HybridInvoiceRequest` validates the versioned envelope and constructs the online
channel on the server. Browser price, stock, promotion revision, free quantity and
calculated-total fields are rejected. Canonical hashes sort object keys while
preserving original line order for monetary allocation ties. The stable UUID,
current actor, selections, settlement and exact review hash bind command identity.

`HybridInvoiceResolver` uses the server-authority context to reload the actor,
customer assignment, stock source, official product IDs/prices/versions and current
offer revisions. The PDO read adapter reuses `Promotions::quote()` and its Colombo
dates/overlap detection; it reconciles paid-plus-free stock against the location
ledger. Cost fields never enter the resolver payload. New config bindings require
stable product and promotion IDs with the approved 6/1 or 12/1 expectations. They
are deliberately empty: operational IDs were not inspected or guessed. Mismatching,
missing bound offers, monetary offers and pooled mixed-oil offers block checkout.
No operational offer is rewritten to make it match.

Locks follow inventory gate → Hybrid control → actor/source operating state →
customer → promotion gate → products in numeric ID order → source/balances/ledger.
Request order remains separate from lock order. Preview rolls back its transaction
or savepoint and allocates no number or permanent command. Current credit settings,
credit limits, cash, partial cash and change are checked using exact money helpers.

`HybridFeatureGate` separates preview availability, sale posting, return posting
and custody. Master enable and all action switches are false, financial hold true,
and database/binding allowlists empty. Missing schema/control/version fails closed.
The PDO context and snapshot persistence primitive additionally refuse operational
schemas and port 3306; they accept only explicitly named disposable schemas matching
`dharma_sales_hybrid_*test` or `dharma_sales_hybrid_*test_<suffix>`. There is no
environment-variable activation bypass. A future writer must preserve the existing
financial hold independently of Hybrid switches.

`HybridInvoiceCheckout` prepares transaction/idempotency integration through the
`HybridCheckoutStore` port. It checks a scoped accepted replay before testing new
posting gates, rejects actor/operation/payload collisions, claims the same UUID,
recalculates under locks, verifies review, and creates snapshots and allocations.
The adapter contract requires official numbering/items/stock/payment writes,
snapshot persistence and seal, streams/audit/result to share one transaction. Tests
implement this port in memory and inject failures after each step. **No production
implementation of this writer port exists.** The PDO read context is not a writer;
the live issue entry point always rejects Hybrid. Source defaults also reject live
preview before any PDO query.

## B1 original unit positions

`HYBRID_UNITS_B1_V1` allocates product **or** replacement cents by integer quotient
and residual, with original-position ties. It then distributes the fixed segment
invoice-discount amount proportionally to remaining unit capacities using largest
remainders. Net is derived from the components, never independently rounded. Every
component sum is checked and every position has nonnegative net. Product and
replacement benefits cannot overlap a segment.

The implementation operates on capacity bands and compact runs, without expanding
quantity into individual units. A one-billion-unit test uses at most eight runs.
String arithmetic handles multiplication beyond native integer money limits.
Positions are zero-based within the immutable original line/segment; line and
segment identities are part of their keys. Returns select a particular original
line, consume remainder segments first and qualified segments second, and cannot
select a higher-value position. `consumeAfter()` rebuilds cursors from accepted
range evidence and rejects duplicated, skipped or altered positions. The lower
level `consume()` requires an already locked and validated cursor projection.
Neither method posts a return. A future return writer must lock append-only events
and projections and persist acceptance atomically; custody must not consume them.

For the approved 8 × 100.00 example, product discount is 50.00, invoice discount
75.00 and payable 675.00. First/second returned remainder units are each 67.50;
the third qualified unit is 90.00. Repeated and aggregate consumption agree.
For gross 200.02, P 50.01 and I 15.00 over two units, the component tie rules produce
original nets 67.50 then 67.51. An odd net cent is not separately redistributed
after assigning product and invoice cents. Original vectors remain unchanged on
subsequent consumption.

## Snapshots and prepared migration

`HybridInvoiceSnapshots` rebuilds the pure calculation using immutable original
inputs, regenerates unit runs, verifies the review fingerprint, and reconciles the
seal, digest and expected child counts. It does not consult current catalogue or
offers for historical arithmetic. The distinct `HYBRID_PROMOTION_V1` policy never
reinterprets existing `PROMO_CHOICE_V1` or `INVOICE_PERCENT_V1` snapshots.

Compatibility mapping: official subtotal is original paid gross; automatic monetary
discount is zero; manual/total discount is P + R + I; grand total is original gross
less that sum. Product/replacement/invoice details remain separate in Hybrid rows.
The detailed request, review, monetary fields, price/offer revisions, selections,
entitlements, substituted units/reason, policy versions and unit allocation runs
are preserved in the snapshot representation.

`PdoHybridInvoiceSnapshots::insertSealed()` is a prepared persistence primitive.
It requires an existing owning transaction and an isolated schema, reconciles the
source snapshot, checks official header/item identities and money mapping, inserts
the snapshot/lines/segments/runs, checks physical child counts and inserts the seal
last. It neither allocates official identities nor commits independently. It is
not called by a production writer or route.

**Only `025_hybrid_invoice_snapshots.sql` was prepared, never executed.** It adds:

- Disabled `hybrid_operating_state` and immutable `hybrid_control_events`.
- Immutable `hybrid_commands` and `hybrid_command_results`; results reference a
  sealed invoice and a unique command.
- Immutable `hybrid_invoice_snapshots`, `hybrid_invoice_line_snapshots`,
  `hybrid_invoice_segments`, `hybrid_unit_allocation_runs` and `hybrid_invoice_seals`.

Nine InnoDB tables, existing official identity foreign keys, DECIMAL(15,2) money,
rates, UTC timestamps and Colombo business date fields; row checks for quantities,
component conservation, replacement exclusivity and the strict cap; update/delete
rejection and child-insert guards using the locked parent/seal relationship.
The file uses the existing simple-statement migrator format. Cross-row allocation
sums, interval overlap and counts still require application reconciliation and
locking. SQL-recorder checks are **not** evidence that MySQL accepted this DDL or
that its triggers/concurrency behavior passed real tests.

Migrations 026/027 for linked returns/custody were not created: implementing those
services is outside the authorized Stage 2B sale foundations. Migration 007's
reported fresh-install issue is untouched and remains a rehearsal blocker.

## Isolated verification

Commands run from the isolated worktree using `C:\xampp\php\php.exe`:

```text
php tests/hybrid-invoice-foundations.php
php tests/hybrid-promotion-policy.php
php tests/run.php
node tests/invoice-discount-domain.js
node tests/mixed-oil-domain.js
```

- Stage 2B: 17 cases, 2,637 assertions, including 2,400 independent expanded-unit
  oracle comparisons, zero-price/odd-cent/huge-money/large-quantity boundaries,
  strict cap, authorization/source/assignment checks, stable offer bindings,
  conflicts, replacement restrictions, credit, seal tampering, stale reviews,
  duplicate/colliding requests and replay after disabling new actions.
- Full in-memory rollback at 14 failure points: claim, number, invoice, items,
  stock, payment, snapshot parent/lines/segments/runs, seal, streams, audit, result.
  Retrying the same UUID after rollback posts once in the synthetic adapter.
- PDO snapshot SQL-contract recording verifies official money/identity mapping,
  seal-last order and guards without constructing a real PDO connection.
- Stage 1 unchanged: 20 cases and 13,533 assertions, including legacy calculator
  regressions and independent exact-cent/cap/replacement oracles.
- Foundation: 12 checks. Legacy invoice-discount JavaScript: 4 tests. Legacy
  mixed-oil JavaScript: 3 tests.
- PHP lint passed for the changed/new PHP files and retained Stage 1 PHP files.
  `git diff --check` passed. All 24 existing migration files and six legacy
  calculator/promotion/configuration files have no Git changes and match main
  after newline normalization. The worktree checkout has CRLF/LF byte differences
  for migrations 001–019 and `PromotionCalculator.php`; these files were not edited
  or normalized. Future checksum preflight must use the actual deployed bytes and
  journal, not assume cross-worktree byte identity. The business-specification
  copy matches its source byte-for-byte.

No `tests/database.php`, operational regression wrapper, migration command,
financial preflight or database integration suite was run. No disposable database
environment was supplied/verified. There are **no claims of SQL execution, actual
multi-connection concurrency, real rollback, schema readiness or deployment
acceptance**. SQL syntax/constraints/seal-race behavior and the real authoritative
lookup must be verified during a separately authorized disposable-schema rehearsal.

## Remaining blockers and next recommended stage

1. Review and track the isolated source baseline, including existing Stage 1 files.
2. Review the prepared SQL and establish a separately authorized disposable-schema
   rehearsal, including migration 007 resolution, immutable constraints, late-child
   races, lock contention, ledger reconciliation and actual PDO reads/writes.
3. Implement a production Hybrid writer adapter using official invoice/stock/payment
   identities and shared transactions, transient-conflict retry and global UUID
   collision checks. The current synthetic port test does not complete that adapter.
4. Implement sealed Hybrid readers and shared balances before connecting payments,
   reports, documents, voids or linked credits. Existing readers/voids are not ready
   for linked Hybrid returns; returns, custody and residual voids remain unavailable.
5. Complete version-aware online UI, documents/report/reconciliation integration,
   isolated end-to-end tests and later device/printer acceptance. There is no new
   Hybrid frontend or production API response contract acceptance in this stage.
6. Separately authorize an operational preflight to verify stable product/offer IDs
   and deployment bindings. Do not silently change mismatching offers. Feature
   activation, financial posting, pilot changes and deployment remain prohibited.

Recommended next work is a separately authorized **Stage 2C integration and
disposable-database verification** addressing these adapters/readers and database
proofs. This report does not authorize it. Noncash linked returns, retained-cap
exceptions and missing-free recovery waivers remain fail-closed under the approved
policy. Work stops at Stage 2B source development and isolated test reporting.
