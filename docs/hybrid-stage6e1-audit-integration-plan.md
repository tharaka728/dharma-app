# Stage 6E-1: post-removal audit and original New Invoice integration plan

26 November 2026. Audit and planning only, in isolated worktree `storage/hybrid-stage6d-ui`, branch `hybrid-promotion-v1-stage6d-ui`, HEAD `8477ddac2271114a1f74ff539924541eb4974df5`. This report is the only file added by Stage 6E-1. No application/test/configuration changes, database connections, migrations, posting, activation, deployment, commit, push or merge. No local-main files were written.

## Audit result

PASS for source removal and preservation, with the limitations below. All 16 files listed in [the removal report](hybrid-stage6d-ui-removal.md) are absent. Seven were tracked deletions; nine were untracked additions removed before staging. The discarded editor route/alias is absent from application routing and the worker. The owner-preview launcher and UI-only browser/issue/visual runners are absent. Retained API eligibility/review services are business capabilities, not a second editor.

The original `app/Views/invoices.php` matches checkpoint bytes except for removal of the link to the discarded editor. `public/assets/js/pos.js` matches checkpoint bytes exactly; original `pos.css` remains unchanged. Existing Hybrid readers/return links already present at the checkpoint remain in invoice details; they are not checkout editors.

All 83 pre-removal preservation hashes pass. A separate comparison against Git HEAD confirms 89 critical service/config/migration/offline/Dashboard/Profile files match checkpoint bytes. This includes the Hybrid calculator, resolver, writer, snapshots, linked returns, financial guards, PROMO_CHOICE_V1 policy/configuration, signed offline contracts, Dashboard and Customer Profile. Their reusable financial tests remain. This is source preservation evidence, not fresh database/browser acceptance.

Source configuration confirms Hybrid enabled/sale_posting/return_posting/custody false, financial_posting_hold true, empty database allowlist and bindings. PROMO_CHOICE_V1 posting_hold remains true. Persisted database controls and running process state were not inspected: the audit deliberately made no database/network connection.

### Why HTTP 409 is intentional

`InvoiceController::handle()` intercepts non-pilot representative New Invoice GETs (including the API-shaped /new route) and calls `unavailableCheckout()`: current actor validation, private/no-store response, HTTP 409, no editor. Eligible HTML checkout POSTs are also blocked. Unversioned new-online-sale API requests are rejected rather than dispatched to the legacy writer. Explicit historical-return and approved pilot contracts keep their existing separate authorization; signed offline synchronization stays in its existing service.

The owner discarded the only Hybrid editor while requiring eligible new online sales to remain Hybrid, including zero-benefit sales. Returning the legacy editor would violate that boundary. Versioned financial API requests still use Hybrid normalization/resolver/writer and their holds. Removing the 409 requires separately approved integration into the original form and successful runtime availability; it does not authorize activation.

### Checks rerun in this audit

- Removal/routes/safety: 24 checks PASS, fake PDO reads only, zero connections.
- Foundation suite: 12 checks PASS.
- Hybrid foundations: 17 cases / 2,635 assertions PASS, including financial snapshots, failure rollback, replay, source holds and offline rejection.
- JS invoice-discount/offline suites: 12 tests PASS, including exact allocation, coherent reconciliation and AES-GCM.
- Changed/new PHP lint: 9 files PASS; changed JS syntax: 7 files PASS; CRLF-aware whitespace PASS.
- Original template/POS comparison, 16 absent files, 83 preservation hashes and 89 critical checkpoint-byte comparisons PASS.

Database-backed tests (including tests/database.php), HTTP/browser acceptance and physical printing were not run. Prior UI screenshot results are historical and do not accept a future integration.

## Git status assessment

Before this new report: 13 tracked modifications, 7 tracked deletions, 9 untracked files, nothing staged. This report adds one untracked documentation file; HEAD is unchanged.

Intentional source changes: `InvoiceController.php` removes the editor route/render and preserves fail-closed new-sale routing; `HybridWorkerClient.php` drops the editor action while retaining guarded original-route GET; `invoices.php` loses the obsolete link; `hybrid-localization.js` targets retained returns only. The seven tracked deletions are exclusively discarded editor assets/runners.

Intentional test changes: the five retained returns/documents/readers/locale browser suites remove discarded-editor helper/journey coverage; locale inventory covers retained sources; shared router/bridge discontinue owner-preview mode before dispatch; controller tests expect absent editor/409 and retain financial assertions. New routing, read-only safety and removal tests preserve scope/financial coverage.

The six pre-existing untracked Stage 6D documentation files are removal report plus real-data compatibility, UI readiness/inventory and visual correction/inventory records. The last four describe discarded work and must be treated as historical; the UI/visual reports have superseded notices. Inventories intentionally remain old evidence, not current manifests. They must not be presented as current readiness.

No unexplained business-source or migration drift was found. Two review nits remain: partial CRLF normalization creates avoidable locale-test diff noise, and five retained browser suites still contain unused `total()`/`lineFact()` helpers referencing old Hybrid DOM selectors. These helpers do not navigate, mount or expose an editor. No cleanup was performed in this audit; review their removal/diff normalization before checkpoint approval. No operational pass is inferred from source preservation.

## Original flow and minimal future file map

All paths below are relative to this worktree. This is a proposed implementation scope, not code authorization.

| Existing file / route | Current responsibility and minimal future change |
| --- | --- |
| `public/index.php` | Existing authentication, role/CSRF dispatch to InvoiceController. Preserve it; no new checkout ingress or route. |
| `/rep/invoices/new`; `/admin/invoices/new` | Original user-facing New Invoice URLs. Integrate eligible representative sales at the first URL. Preserve admin/pilot routing; do not extend eligibility to admin without approval. Never restore /rep/invoices/hybrid/new. |
| `app/Controllers/InvoiceController.php` | Replace only the eligible checkout-unavailable branch with original-template setup after approved Hybrid availability/runtime checks. Keep disabled/unavailable 409, pilot/historical distinctions and no legacy financial fallback. Use existing /api/v1/rep/invoices/options GET, /api/v1/rep/invoices/hybrid/eligibility POST, /api/v1/rep/invoices/preview POST and /api/v1/rep/invoices POST. |
| `app/Views/invoices.php` | Keep the single invoice-form, page shell, customer/source controls, cart, payments and confirmation placement. Select policy-specific controls within this form; suppress legacy benefit simulation only for Hybrid checkout so two discount engines cannot compete. |
| `app/Views/invoice-line.php` | Keep original line identity and paid quantity. Add Hybrid intent controls to existing lines, with default product discount 0 and explicit custom/eligible replacement choices. Do not submit calculated money/free quantities as authority. |
| `app/Views/invoice-discount-fields.php` | Use the existing invoice-discount location. Branch by policy: Hybrid 0?50% and sequential policy; legacy INVOICE_PERCENT_V1 0?100% stays unchanged. Never submit the legacy hidden discount_policy field in a strict Hybrid request. |
| `public/assets/js/invoices.js` | Bind Hybrid intent serialization and Review/confirmation lifecycle to the existing invoice-form. Preserve stable UUID, current server review_hash, same-command retry after uncertain outcome, and freeze all editable controls while confirmation outcome is unresolved. No independent editor or local financial calculator. |
| `public/assets/js/invoice-discount.js` | Keep existing legacy/pilot behavior. For Hybrid delegate to the one authoritative review coordinator in invoices.js; do not run simultaneous legacy auto-preview and Hybrid review. Invalidate review on every financial input change and ignore stale responses. |
| `public/assets/js/pos.js` | Preserve product cards/search/categories/images, Add, quantity steppers, cart/remove, customer selection, payment placement and responsiveness. Add only optional, default-neutral row/detail presentation and verified-response mapping needed by the original form; retain offline caller behavior and existing totals events. |
| `app/Views/invoice-reviewed-totals.php`, `app/Views/invoice-review-lines.php` | Render server Hybrid quantity/components in the original review area, without changing final-document/historical snapshot semantics or treating a display mapping as authority. |
| `public/assets/css/pos.css` | Keep current layout/palette/sticky/mobile behavior; only narrowly necessary rules for in-cart controls/details after actual overflow/accessibility checks. No redesign or separate Hybrid stylesheet. |
| `public/assets/i18n/si-LK.json`, retained `hybrid-si-LK.json` where appropriate | Reuse localization and add only required original-form labels; native approval remains separate. Do not change protocol enums, money or IDs through translation. |
| `tests/hybrid-stage6d-routing.php`, `tests/hybrid-stage6d-visual-readers.php`, `tests/hybrid-ui-removal.php`, existing financial/domain suites | Update original-form route expectations only after approval; continue testing permanently absent alias/editor assets, source holds, pilot/offline isolation, financial review and exactly-once behavior. Targeted browser acceptance should exercise the original form, not resurrect removed runners or a gallery. |

The future UI must construct only the established strict Hybrid intent: request_id, customer_id, authorized stock_source, payment_type, amount_received, csrf_token, hybrid.policy_version, ordered product/paid-quantity/product-discount selections, invoice_discount_percent, optional explicit replacement selection, and review_hash at confirmation. It must omit legacy items/discount/benefit/oil-calculator fields from that policy payload. Confirm exact keys against HybridInvoiceRequest; do not relax its whitelist to accommodate form serialization.

### Authoritative business-data services: reuse without modification

- `app/Services/Invoices.php`: actor/customers/options/catalogue, current assignment, representative stock display, payment terms/outstanding; review()/issue() dispatch versioned Hybrid requests.
- `HybridInvoiceRequest.php`, `HybridInvoiceCheckout.php`, `HybridInvoiceResolver.php`, `HybridPromotionPolicy.php`: strict intent, authorization/gates, current reads, eligibility and exact server calculation.
- `PdoHybridInvoiceContext.php` + `Promotions.php`: existing products/categories/customer records and current price/version; current published promotion revisions; warehouse product_stock_balances or authorized representative_stock_balances, location identity and inventory_movements reconciliation. No fixture import or duplicate business masters.
- `PdoHybridCheckoutStore.php`, `HybridInvoiceSnapshots.php`, `PdoHybridInvoiceSnapshots.php`, `HybridUnitAllocations.php`: atomic financial writer, immutable sealed snapshots and original-unit cents. Preserve these, linked returns, readers and migrations 025?027.
- `HybridDatabaseScope.php`, `HybridFeatureGate.php`, `app/Support/HybridIsolatedRuntime.php`, `HybridWorkerClient.php`, `bin/hybrid-worker.php`, `public/hybrid-isolated.php`: current physical identity/CLI/IPC boundaries. Preserve them; operational runtime acceptance is a separate project gate.

### Where the business features belong

Product discount intent belongs inside each existing cart/line row. Server-returned paid, qualified, eligible remainder and actual free quantities belong in that row's details and original review area. Automatic free goods are read-only derived output; paid plus free demand remains stock-checked. Remainder discounts must never spill onto qualified units. Eligible complete-shortage replacement remains explicit, never inferred from a short/partial stock display.

Whole-invoice discount belongs in the existing discount fieldset; server calculation applies it sequentially after the approved line/replacement components and enforces the existing combined cap. Show P/R/I and payable from authoritative review. Never monetize free goods into a discount total. Customer selection and current cash/credit/change/outstanding controls retain their original locations and permission/credit-limit checks.

### Acceptance checklist for a separately approved implementation

- One original form/editor at the existing route, no discarded alias/preview/editor assets, unchanged cards/cart/customer/payment layout at desktop and narrow mobile widths.
- All eligible online representative sales, including 0%/no-free sales, use Hybrid. Disabled/unavailable runtime, malformed requests, stale review and scope violations fail closed; no legacy fallback. Pilot/admin/historical/signed-offline contracts remain distinct and unchanged.
- Defaults 0%, exact custom bounds, Glucose 6/8/12, Suwada 12, ordinary products, eligible remainders, multiline sequential invoice discount, cap/tie/rounding and explicit replacement cases match retained financial oracles. Wrong IDs, overlapping/monetary/mixed-oil conflicts and paid/free shortages reject.
- Stable UUID/review fingerprint, invalidation on all changes, stale-response suppression, controls locked during unresolved confirmation, replay/timeout retry and no duplicate stock/payment posting.
- Current assignments/products/prices/revisions/stock rechecked server-side, cost excluded from representative payloads; private/no-store responses and no service-worker caching or offline queuing of Hybrid intents.
- Confirmation remains held. Only separately authorized disposable synthetic tests may test posting/rollback/concurrency; no genuine issue as a display test.
- Focused route/syntax/domain tests plus bounded original-form browser checks; pilot/offline/Dashboard/Profile regressions and immutable-return readers preserved. Native language and physical hardware acceptance remain distinct.

## Release blockers

1. **Migration 007:** documented collation mismatch causes MariaDB 1267 (customers.city_town General versus location_towns.name_key Unicode). Later 008 cannot fix a chain stopped at 007. The separately checksummed compatibility preparation is only for an explicitly verified new empty installation; do not apply it casually to populated operational data. Existing prepared-fixture success is not pristine-chain success.
2. **Raw-byte packaging:** checkpoint LF 001?024 versus installer contract's Windows CRLF hashes. For 007: checkpoint 161effa594c58f45223bf68161ba3a6f3b623dc34bafff0a33080008a337fa9c; rehearsal f46167f2b163ed69b351e61cb0bebfc30804db0713d1e22973f6e96d7a94800a. Resolve canonical artifact/installer agreement and verify deployed raw bytes/journal under a separately approved preflight. Never rewrite deployed migrations, normalize their bytes or falsify checksums.
3. **Real product/offer bindings:** operational identities remain unverified. Owner-reviewed mapping must include product ID/SKU, promotion master ID, immutable revision/version, product binding, type/quantities, enabled status and inclusive Colombo dates (Glucose 6+1 and Suwada Kudu 12+1). Check all other applicable/conflicting rules, current prices/versions, rep/customer assignments and stock-location/ledger consistency. Names and synthetic IDs are not authority; do not seed, rename or repair live records to fit fixtures.
4. **HTTP/runtime:** normal public/index.php instantiates Invoices with the default PDO; Hybrid transactional context requires a physically proved disposable CLI manifest, and writer enforces isolated database identity. Source guards and holds intentionally prevent operational use. A target-specific identity/account/grant/auth/CSRF/CLI/IPC/ACL/supervision/deadline/concurrency/uncertain-outcome/recovery design and acceptance are required. Review uses FOR UPDATE and rollback; a generic read-only transaction is not a substitute. Non-locking inventory and lock-taking review need separate explicit scopes.
5. **Sinhala:** named native reviewer must approve financial distinctions (qualified/remainder/free/replacement, sequential product/invoice discount and cap, change/debt/Refund Due), errors and narrow-screen wrapping. Prior software renders are not native sign-off.
6. **Physical printers:** actual target 58mm/80mm receipts and A4/PDF workflow, Sinhala glyphs/fonts, totals/columns/wrapping, Bluetooth/driver path, feed/cutter and repeated prints need printed evidence. Preserve immutable read-only document semantics; no physical acceptance is claimed.

## Recommended safe checkpoint and approvals

Recommend an explicit removal-only checkpoint, after owner review, on this isolated branch: the current 20 tracked source/test modifications/deletions; three retained untracked routing/read-only/removal tests; docs/hybrid-stage6d-ui-removal.md; and this audit/plan. Include the superseded UI-readiness/visual-correction reports and their historical inventories only as an explicitly labeled documentation archive, together with the real-data compatibility report. No blanket git add: inspect every selected path and review the locale-test line-ending noise/unused helper nits first.

Exclude storage/**, raw galleries/screenshots, database files, manifests, IPC, keys, account/profile/session data and all runtime-generated evidence. No engine, config, migration, Dashboard/Profile, offline or restored POS change belongs in this removal checkpoint. The completed Hybrid business engine is already in parent HEAD 8477dda and is not being reimplemented. No files are staged and no checkpoint was created.

Separate explicit owner approvals are required for: (a) committing this reviewed checkpoint and any later push/merge; (b) implementing the original-form integration and its bounded synthetic acceptance scope; (c) migration packaging remediation and any migration/install rehearsal; (d) named operational metadata/non-locking reads and stable-ID preflight, with distinct permission for lock-taking review; (e) operational runtime/deployment; and (f) enabling display/review, lifting posting holds, activation or genuine financial posting. Approval of a UI task does not imply any of the release/financial actions.

Stop after this audit and plan. The separate Hybrid Invoice UI remains discarded.

## Reviewed removal checkpoint follow-up

The owner subsequently authorized one local removal/audit checkpoint. Before staging, ten unused total()/lineFact() helpers were removed from the five retained browser suites; unchanged locale-test lines regained their checkpoint line endings. Retained assertions and execution paths were preserved. Historical inventories and the pre-removal compatibility review now explicitly identify their archival status. The earlier Git-status counts and approval discussion above describe the audit-time state, not the later checkpoint. See [the reviewed checkpoint manifest](hybrid-stage6e1-checkpoint.md) for the selected file list and final validation scope. No Stage 6E-2 implementation, database connection, activation, push, merge or deployment is authorized or performed.
