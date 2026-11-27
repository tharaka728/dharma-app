# Stage 6E-3: verified original invoice integration checkpoint

> **Superseded discount behavior - 27 November 2026:** This historical stage record retains its original examples, test counts and evidence. Any instruction or example applying invoice discount to a line with free entitlement or nonzero product/replacement discount is superseded by the [approved eligibility correction](hybrid-stage6e2-original-invoice.md#approved-eligibility-correction-and-owner-verification---27-november-2026). Such lines now receive invoice discount 0.00; a 0% product selection alone does not exclude a line. Glucose 8 at 100 with remainder discount 50 remains payable 750 even at invoice rate 10%; adding 5 ordinary unpromoted units at 100 gives eligible subtotal 500, invoice discount 50 and total 1,200. Existing immutable snapshots and historical results are not rewritten; the strict combined 50% cap remains. Old screenshots and PASS counts do not verify the new rule.

Owner-approved local checkpoint only, 26 November 2026. Branch: hybrid-promotion-v1-stage6e2-original. Worktree: C:/xampp/htdocs/dharma chemical sales app/storage/hybrid-stage6e2-original. Parent: 83228b1f79694be1721101cc0b45a58e14cb5667. The owner confirmed Glucose paid 12 gives free 2 with no product discount on qualified units. No UI redesign or promotion-policy change was made during this checkpoint review; this report is its only new source artifact. The Stage 6E-2 handoff/inventory remain historical implementation records; their pre-commit status statements are superseded by this checkpoint. Final commit SHA and Git status are reported after commit, outside this self-referential report.

## Exact integration scope

The existing /rep/invoices/new route renders the existing app/Views/invoices.php invoice-form through InvoiceController. Original invoice-line.php and invoice-discount-fields.php add policy-specific controls inside that same form. invoices.js coordinates strict intent, server eligibility/review, fingerprint invalidation and locked same-UUID retries; invoice-discount.js yields only for that Hybrid branch; pos.js retains original cards/search/categories/customer/cart/payment/summary layout and supplies optional row/verified-total presentation hooks. Existing pos.css, navigation, Dashboard/Profile and offline source are unchanged. Public-static localization/cache changes add draft labels without changing existing translation values.

No new checkout route, separate invoice template/editor or JavaScript financial calculator exists. The discarded editor/alias and all 16 discarded UI files remain absent. API-shaped new GET, disabled/unavailable Hybrid and unsupported eligible HTML checkout remain fail-closed. Existing versioned APIs, authoritative services, engine, writer, immutable snapshots, allocations, linked returns, pilot/historical/offline contracts and migrations are preserved. No application code depends on test files or private runtime paths/configuration.

## SQL / source / private-data review

The four files below compare byte-for-byte equal to their HEAD blobs. Their SHA-256 values match the Stage 6E-2 inventory. All have empty Git content diffs. Git initially reports M because of checkout/index line-ending behavior after restoration of pinned installer bytes; this is not permission to normalize or change deployed migration/checksum history. Their status artifacts were cleared by refreshing the byte-identical index entries. Their object IDs remained unchanged and the staged SQL diff is empty: no SQL file is included in this commit.

- database/fresh-install/006a_customer_locations_compat_v1.sql ? 1ec24563527db3162cf7630390a911389151cc765039a59df2d4880f2fd599c0
- database/migrations/025_hybrid_invoice_snapshots.sql ? 662054eb3025354eff03c9da928e3b618fa433a4ca69a8b6632342924d45f2c1
- database/migrations/026_hybrid_linked_returns.sql ? 912b831ea2d3674bee50ef7b6dfd3852683fec2b00c921c5f419f5457a137d5f
- database/migrations/027_hybrid_custody_dispositions.sql ? 1e00ad4388f60c4b130d560d6cbcebb83489e8d43c9033bfc4d6e54faca24fac

Reviewed the complete modified/new source scope, application dependency paths, shared test boundaries, generated public dictionary additions and private-data patterns. No embedded credentials, account/profile/session/database/IPC data, external PDF/network dependency, operational configuration shortcut or accidental financial-engine change was found. Test runtime/fixture dependencies remain exclusively under tests/ and are never referenced by application code. 89 protected service/config/migration/offline/Dashboard/Profile files retain identical source text and no Git content diff; checkout line-ending differences are not represented as universal raw-byte equality.

Private storage, databases, accounts, credentials, browser profiles, logs, screenshots, IPC and runtime evidence are excluded. Exact selected source/documentation paths (25):

- `app/Controllers/InvoiceController.php`
- `app/Views/invoice-discount-fields.php`
- `app/Views/invoice-line.php`
- `app/Views/invoices.php`
- `app/Views/localization-head.php`
- `docs/hybrid-stage6e2-original-invoice.md`
- `docs/hybrid-stage6e2-source-inventory.json`
- `docs/hybrid-stage6e3-release-readiness.md`
- `public/assets/i18n/catalogue.js`
- `public/assets/i18n/si-LK.json`
- `public/assets/js/invoice-discount.js`
- `public/assets/js/invoices.js`
- `public/assets/js/pos.js`
- `tests/hybrid-stage6d-routing.php`
- `tests/hybrid-stage6d-visual-readers.php`
- `tests/hybrid-ui-bridge.php`
- `tests/hybrid-ui-controllers.php`
- `tests/hybrid-ui-removal.php`
- `tests/hybrid-ui-router.php`
- `tests/stage6e2-cart-browser.js`
- `tests/stage6e2-confirm-backstop.php`
- `tests/stage6e2-confirm-browser.js`
- `tests/stage6e2-original-browser.js`
- `tests/stage6e2-runtime-prepare.php`
- `tests/stage6e2-safety.php`

## Focused checks rerun for this checkpoint

- Actual original-form Edge browser: 72 checks PASS, including controls, Glucose/Suwada/ordinary/zero-benefit/remainder/replacement/cap/shortage/conflicts, original search/stepper/removal, fingerprint invalidation, offline rejection and preview write denials; English/Sinhala at desktop, 390 and 360px.
- Original-form scoped safety: 15 checks PASS; source holds, wrong stable-ID bindings, assignment/stock scope, pilot form, admin rejection, no alternate API editor, Dashboard/Profile controller rendering.
- Removal/routes: 24 checks PASS. Retained controller/read-only safety: 18 + 9 checks PASS.
- Read-only financial audit: 12 checks PASS, including exact Glucose 12 with product 25% => qualified 12 / remainder 0 / free 2 / product discount 0.00 / payable 1200.00; Glucose 8 => 750.00, invoice 10% => 675.00; legacy review; four retained sealed invoice/payment/linked-return reconciliations; unchanged controls.
- Pilot financial review PASS in a separate process with posting hold ON (avoids the legacy feature-gate cache from the preceding legacy review). No application change was required by this harness separation.
- Foundation: 12 checks PASS. Hybrid foundations: 17 cases / 2,635 assertions PASS, including atomic-writer/idempotency/rollback and hold guards using in-memory/SQL-recorder fixtures only.
- Returns pure policy: 34 checks PASS. JS invoice-discount/offline: 12 tests PASS, including AES-GCM and coherent reservation/receipt reconciliation.
- Changed/new PHP lint: 14 files PASS; JS syntax: 7 files PASS. CRLF-aware whitespace and source-inventory hashes PASS.
- All 90 primary disposable application table digests remain unchanged across the checkpoint review and browser/negative probes. Review transactions roll back; no new synthetic/genuine invoice, payment, stock movement, control event or migration was needed for Stage 6E-3.

The disposable manifest/physical server proof was checked before service use; its endpoint is 63842 with data under this worktree. No connection to operational 3306/3309 occurred. Owner preview 63843 remains write-denying and financially held. Source Hybrid activation remains OFF, database/binding allowlists empty and posting flags OFF with financial hold ON. Private financial runtime remains disabled/held/stopped. No posting hold or persisted feature control was lifted in Stage 6E-3.

Stage 6E-2's existing 12 actual-form cash/credit/retry checks, 40 authenticated API checks, 141 financial DB checks, 157 returns checks and legacy/pilot/historical posting acceptance are prior evidence. They were deliberately not rerun by enabling posting in this checkpoint. No operational, full Phase 9 browser, native-language or physical-printer acceptance is claimed.

## Remaining blockers and requirements

1. Migration 007 still has the original MariaDB 1267 collation mismatch (customer city General versus generated location key Unicode); later 008 cannot repair a chain stopped at 007. The separately journaled preparation was rehearsed only on verified new empty disposable installations. No pristine-chain PASS, populated-target remediation or deployed-migration rewrite is approved.
2. Canonical release artifacts and installer expectations still require resolution of LF/CRLF raw-checksum packaging. Compare actual deployed file bytes/journal and required 025-027 schema under separate authorization. Never rewrite applied checksum history, normalize deployed SQL silently or treat the supported prepared Windows fixture as operational readiness.
3. Actual bindings remain unverified: owner-reviewed product ID/SKU, active promotion master ID, immutable revision/version, product binding, type/quantities and inclusive Colombo dates (Glucose 6+1, Suwada Kudu 12+1). Check every applicable/overlapping monetary/mixed-oil rule, current price/version, representative/customer assignments and existing stock-location/ledger consistency. Synthetic IDs and display names are not operational authority. Do not seed/import replacement masters or repair live records to fit tests.
4. Operational runtime is not approved. Require target HTTP/CLI service identity, exact instance/schema proof, least-privilege grants, existing auth/roles/CSRF/no-store scope, fixed executable/profile paths, protected IPC MAC/expiry/ACLs, bounded workers/locks/timeouts, supervision and uncertain-outcome same-command recovery. Current disposable CLI/physical guards must remain intact; any distinct operational adapter requires separate review. Review takes FOR UPDATE locks and rolls back, so lock-taking approval is distinct from non-locking metadata reads.
5. Rollback/cutover must preserve immutable identities and accepted work: freeze new dispatch, drain/resolve IPC and ambiguous commands using existing UUID/result receipts, verify journal/stock/payment reconciliation, and retain signed offline/unresolved records. A code rollback must not erase accepted invoices or reuse official numbers. Backup/restore rehearsal belongs in a newly named isolated schema. Any business correction follows approved append-only void/return mechanisms; do not restore a live database snapshot over later financial activity as a routine code rollback.
6. Named native Sinhala review must approve qualified/remainder/free/replacement distinctions, sequential discounts/cap, cash/change/debt/Refund Due and uncertain-outcome/errors at narrow widths. Actual target 58/80mm receipt, A4/PDF workflow, Sinhala glyph/font/wrapping, Bluetooth/driver/feed/cutter and repeated-print acceptance require physical evidence. Existing software screenshots are not these approvals.

## Safe future sequence / next approval gate

1. Review this local checkpoint and approve a narrowly scoped operational preflight proposal: named target/identity, permitted non-locking metadata reads, minimal output/secret handling and forbidden actions. Operational ports remain forbidden until that distinct authorization.
2. Under that approval, verify deployed raw checksums/schema/install state, stable IDs/revisions, scopes/prices/stock ledgers, backup/recovery prerequisites and target runtime assumptions. Report mismatches; make no repairs, DDL, posting or activation.
3. Separately approve packaging/schema remediation and target-adapter/code integration, rehearse on an isolated target-equivalent environment, and review rollback/reconciliation and security evidence. Any merge/deployment requires its own approval; keep feature gates OFF and holds ON.
4. Obtain native Sinhala and hardware-print sign-offs, then approve a bounded target read/review-only enablement (including explicit lock-taking scope). Keep writers denied and verify real-data scope/review without genuine issuing.
5. Only after those results, seek separate explicit authorization for limited activation/posting and lifting specific holds with approved bindings/allowlists, monitoring, reconciliation and rollback/stop conditions. Expand only after the owner accepts that limited result. A local checkpoint is not deployment, migration or activation approval.

Stop after this local checkpoint and readiness report. No push, merge, deployment, operational migration, activation or genuine posting is performed.
