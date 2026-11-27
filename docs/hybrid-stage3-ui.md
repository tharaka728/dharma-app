# Hybrid Promotion V1 — Stage 3 representative UI

> **Superseded discount behavior - 27 November 2026:** This historical stage record retains its original examples, test counts and evidence. Any instruction or example applying invoice discount to a line with free entitlement or nonzero product/replacement discount is superseded by the [approved eligibility correction](hybrid-stage6e2-original-invoice.md#approved-eligibility-correction-and-owner-verification---27-november-2026). Such lines now receive invoice discount 0.00; a 0% product selection alone does not exclude a line. Glucose 8 at 100 with remainder discount 50 remains payable 750 even at invoice rate 10%; adding 5 ordinary unpromoted units at 100 gives eligible subtotal 500, invoice discount 50 and total 1,200. Existing immutable snapshots and historical results are not rewritten; the strict combined 50% cap remains. Old screenshots and PASS counts do not verify the new rule.

Completed 26 November 2026 in the existing isolated worktree
`storage/hybrid-stage1`, branch `hybrid-promotion-v1-stage1`. The business policy,
Stage 2C report, actual source and uncommitted work were inspected first. Stage 1
and foundation tests were reproduced. No work was merged, committed, deployed or
activated in the operational application.

## Exact files changed in Stage 3

New product UI files:

- `app/Views/hybrid-invoice.php`
- `public/assets/js/hybrid-invoice.js`
- `public/assets/css/hybrid-invoice.css`

Modified UI/controller files:

- `app/Controllers/InvoiceController.php`: explicit representative Hybrid screen
  and read-only eligibility action, fresh role checks, availability and CSRF.
- `app/Views/invoices.php`: a separate Hybrid entry link only when authorized and
  available. Existing `PROMO_CHOICE_V1` controls are unchanged.

Modified service integration files:

- `app/Services/Invoices.php`: read-only capability and eligibility facade;
  unavailable Hybrid readiness hides its entry without breaking legacy checkout.
- `app/Services/HybridInvoiceCheckout.php`: separate preview/posting availability
  and read-only eligibility discovery using the existing isolated context.
- `app/Services/HybridInvoiceResolver.php`: an optional quantity/shortage discovery
  result, after the same authoritative authorization, product, offer and stock
  resolution. Its default financial calculation path is unchanged.

New isolated tests and fixtures:

- `tests/hybrid-ui-domain.js`
- `tests/hybrid-ui-controllers.php`
- `tests/hybrid-ui-prepare.php`
- `tests/hybrid-ui-bridge.php`
- `tests/hybrid-ui-router.php`
- `tests/hybrid-ui-browser.php`
- `tests/hybrid-ui-browser.js`

New report: `docs/hybrid-stage3-ui.md`.

Existing uncommitted work was preserved, including all Stage 1–2C files. The
earlier `OfflineSales.php` rejection changes remain intact; that file was not
edited in Stage 3. No policy calculator, posting writer, migration, operational
configuration, existing POS JavaScript, offline shell, service worker, encrypted
draft, IndexedDB structure, passphrase or queue was changed.

## Screens and interaction

Entry from the existing representative **New Invoice** screen is an explicit
**Hybrid Promotion V1 — online checkout** link. It is absent under the default
disabled configuration. The separate screen is:

```text
GET /rep/invoices/hybrid/new
```

Only a fresh active `SALES_REP` actor may render it. It does not consult the old
single-representative pilot allowlist. Both synthetic representative accounts
passed; administrator and anonymous requests did not receive Hybrid controls.
Customer options remain restricted to the current representative's assignment.
There is no stock-source selector: the server resolves the representative's own
stock and rejects a forged source.

The screen provides customer selection, product search/addition, one aggregate
line per product, paid quantities, independent product discounts, sale type,
received cash, optional invoice discount, replacement selection and a distinct
server review/confirmation section. A separate Legacy checkout link does not
convert or submit a Hybrid cart.

Product and invoice discount controls initially select **No Discount (0%)**.
Product choices are 0/25/30/35/Other; Other accepts 0–50 with at most two decimals.
Product discount applies only to server-identified eligible remainder units.
The whole-invoice selection accepts the same 0–50 range and is sequential. No
selection is automatically changed to 25%, rounded to fit, or clamped.

The server's line display includes paid/qualified/remainder quantities, original
free entitlement, actual free delivery, substituted entitlement, product discount,
replacement discount, allocated invoice discount and final line payable. Offer
threshold/reward information comes from approved stable-ID bindings/revisions:
Glucose 6/1 and Suwada Kudu 12/1; no product-name matching is used.

For a genuine complete free-stock shortage, discovery can show an initially
unchecked replacement checkbox. Partial shortages and remainder-bearing shortage
cases are explicitly labelled unsupported. The representative must select both
the affected entitlement and the replacement rate. The initial rate is blank;
existing 25/30/35/custom choices are retained. The replacement custom range remains
the existing 0–100 policy range, separately from the 0–50 product/invoice controls;
the full replacement amount still counts in the strict combined cap. Product
discount must be explicitly zero on a replaced line. There is no automatic
replacement, partial fulfillment conversion or guessed free-unit-value formula.

## Authoritative discovery, preview and confirmation

New side-effect-free discovery endpoint:

```text
POST /api/v1/rep/invoices/hybrid/eligibility
```

It runs under the same authoritative locks and scoped context as Stage 2C, verifies
current product prices/revisions, promotion bindings/conflicts and reconciled stock,
and returns quantities plus replacement-support status. It rolls back its read
transaction. It returns `financial_review: false` and **no review hash or financial
totals**. It does not claim a request, allocate a number or authorize posting.

Financial previews and issue use the existing Stage 2C routes:

```text
POST /api/v1/rep/invoices/preview
POST /api/v1/rep/invoices
```

The UI sends the explicit Hybrid envelope, stable server-rendered request UUID,
customer, paid quantities, selected rates and settlement intent. It sends no
selling price, stock claim, promotion revision, free quantity or calculated total.
The server's normalized request always constructs the online channel. CSRF is
required for discovery, preview and issue. The new screen/discovery responses use
private `no-store`; the existing protected entry point also sets `no-store`.

JavaScript validates syntax and percentage/quantity ranges using exact strings and
bounded integers. It contains no invoice/pricing calculator. All displayed money
comes from a successful server preview: original subtotal, P, R, sequential I,
combined monetary discount, final payable, applied cash, change and outstanding.
Successful preview is the server's strict cap pass; the UI displays its exact
permitted ceiling. A rejected cap produces an actionable error, not a usable
partial quote or an automatically lowered rate.

Every financial edit aborts old discovery/preview requests, advances the request
generation, clears displayed financial authority and disables confirmation.
Late responses cannot restore an invalidated review. Confirmation sends the
server's exact `review_hash`; stale price/revision/stock/review rejections clear
the confirmation and require a new preview. Selection, customer, quantity, rate
and cash edits are all included in this behavior.

Confirmation is explicit. While its outcome is unresolved, the editor is locked
and the original request body/hash stay only in memory. The button changes to
**Check / retry this confirmation**. A lost network receipt can be checked using
the identical idempotency identity; it never creates a new cart ID automatically.
A before-unload warning protects an unresolved attempt. Successful acceptance
shows the official server invoice link and disables further confirmation. Going
offline afterward retains that issued acknowledgement instead of implying the
sale became unposted.

Preview availability and posting availability are separate. A held synthetic
posting gate permits review but disables confirmation; a direct issue attempt is
also rejected by the server. The operational config, allowlists and posting holds
remain unchanged and disabled.

## Online-only and compatibility

Offline state immediately disables preview/confirmation and invalidates an
unconfirmed review. Reconnection requires a new preview. An unresolved issue can
only be manually retried with its original identity. No automatic retry/post occurs
on the online event.

The module has no localStorage, sessionStorage, IndexedDB, service-worker or queue
integration. It is loaded only on its separate screen, and its assets were not
added to the PWA cache allowlist. The existing server-side offline Hybrid rejection
remains intact. A real-browser test confirmed no offline database was created.

Legacy forms keep their existing IDs, business fields, POS adapter, discount modes
and behavior. `PROMO_CHOICE_V1` is neither redefined nor extended. The browser suite
rendered the unchanged pilot controls under an isolated legacy test override;
real legacy invoice and policy regressions passed separately. No operational
pilot allowlist or hold was widened or lifted.

## Isolation and browser test method

Stage 3 reused the preserved, synthetic-only Stage 2C instance after restarting it
hidden and proving its identity/grants again:

```text
Database: dharma_sales_hybrid_50b64bc4a06f471ebcc14f17294f395e_test
Server:   127.0.0.1:58171
Runtime:  hybrid_rt_6b257c5e0f@127.0.0.1, scoped DML only
Data:     storage/stage2c-d4329bccf5bc4c1db6d44f4c5dfad8d8/data
Stage 3 database launcher PID: 23448
```

The harness checks the exact manifest, port, physical `@@datadir`, generated
identity row, grants and native prepares. Ports **3306 and 3309 are rejected**.
There was no operational connection, account/password file access, restored real
data, Apache edit, Windows service installation, public endpoint or deployment.
Each browser run appended fresh synthetic actors/customers/products/stock;
earlier test data was retained, never reset.

The Stage 2C application adapter still deliberately permits only physically
verified CLI test execution. Its guard was **not weakened for browser testing**.
The test-only PHP loopback router parses HTTP and forwards the authenticated
synthetic session, CSRF and equivalent request object over stdin to a CLI worker.
That worker executes the actual `InvoiceController`, views, authoritative preview
and writer against the verified instance. The router has no app-bootstrap/PDO
fallback and serves only local static assets and its explicit test routes. Its
synthetic session/fixture controls require a generated test key. It is not in
`public/` and was stopped after each browser run.

Thus these are real Edge UI/controller/service/database tests through an explicit
test bridge, **not** a claim that operational HTTP activation is supported. The
existing production HTTP/CLI restriction remains a release blocker. Edge's sandbox
child processes initially failed with access-denied/GPU errors; the same isolated
suite then ran with authorized elevated execution. Early incomplete harness runs
were rejected rather than counted as passes.

No migration was applied or source migration edited in Stage 3. The standard
database regression verified the already-applied checksums/repeatability on this
existing disposable fixture. Migration 007's previously documented empty-fixture
collation amendment was not rerun or applied to any operational schema. The pristine
fresh-install 007 blocker remains unresolved. Migration 025 retains SHA-256:

```text
662054eb3025354eff03c9da928e3b618fa433a4ca69a8b6632342924d45f2c1
```

## Results

| Verification | Result |
| --- | --- |
| Real Edge UI flow | 56 checks |
| Browser database backstop: invoice/seal/stock exactly once | 3 checks |
| Actual controller integration, authorization, CSRF and held gates | 28 checks |
| New JavaScript input/envelope/online-module tests | 5 tests |
| Stage 2C real database regression | 17 cases / 140 checks |
| Real legacy invoice / PROMO_CHOICE_V1 regressions | 20 / 30 checks |
| Existing pure calculation engine | 20 cases / 13,533 assertions |
| Existing Hybrid foundation suite | 17 cases / 2,635 assertions |
| Foundation / standard database checks | 12 / 16 checks |
| Existing invoice UI / invoice discount / mixed oil / offline JS | 2 / 4 / 3 / 8 tests |
| Changed/new PHP and new JavaScript syntax; whitespace | Passed |

Browser checks cover 320, 375, 768, 1024 and 1440 px without page overflow; defaults,
independent discounts, 6/1 and 12/1 repeating free quantities, remainder eligibility,
manual 0 and 50 boundaries, invalid/unclamped 50.01, sequential 25 then 25, strict
combined cap, explicit replacement, partial shortages, conflicting automatic
offers, mixed-oil exclusion, multiple reps, disabled gates, late preview races,
stale server reviews, offline/reconnect behavior and lost-receipt idempotent replay.

The lost-receipt test accepted the first synthetic invoice while discarding its
network response, then retried the exact request and hash. Database checks found
exactly one invoice, one seal and one paid-stock decrement. The response was an
accepted replay. No genuine invoices were created.

Mobile screenshot, captured from synthetic data and visually inspected:

```text
storage/stage2c-d4329bccf5bc4c1db6d44f4c5dfad8d8/
  stage3-ui-9bce7faf12c5/hybrid-mobile-review.png
```

The layout uses stacked cards and readable component grids on phones, two panels
on wider screens, labelled inputs and 44px controls. Long synthetic product codes
wrap; removal controls remain readable. The shared fixed header/quick navigation
is unchanged. The full-page screenshot includes fixed navigation at the capture
viewport position; viewport overflow checks were performed separately.

The temporary database was shut down after verification. Browser/server profiles,
screenshots, synthetic fixtures, accounts and logs remain in ignored storage.
No original file, backup or unresolved offline record was deleted.

## Remaining blockers and recommendation

1. Operational configuration remains disabled. The Stage 2C CLI/physical-isolation
   guard intentionally prevents operational HTTP posting, even if a UI is present.
   A separately authorized runtime/deployment preflight and approved stable-ID
   bindings are required before considering activation.
2. Migration 007 still prevents a pristine fresh install. Existing migration bytes
   and the separate disposable compatibility fixture were preserved.
3. New Hybrid copy is English; shared workspace language behavior is unchanged.
   Sinhala financial-copy review and physical-device usability acceptance should
   precede representative rollout. Automated mobile viewport checks do not claim
   Android/iPhone installation or printer acceptance.
4. Version-aware invoice printing/PDF, detailed P/R/I reporting and wider acceptance
   remain separate work. No print/report definitions were changed in this stage.
5. Linked returns, custody, retained-cap exceptions and residual voids remain
   outside Stage 3 and unavailable. Existing approved fail-closed boundaries remain.

Recommended next stage: separately authorized document/report integration and
broader acceptance, followed by an explicit operational readiness decision. Linked
returns/custody require their own authorized phase. Do not automatically proceed
to migrations, deployment, all-user activation or genuine posting.

Work stops at isolated Stage 3 UI implementation and verification.
