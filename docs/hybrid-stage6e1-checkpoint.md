# Stage 6E-1 reviewed removal checkpoint

Owner-authorized local checkpoint, 26 November 2026. Branch: hybrid-promotion-v1-stage6d-ui. Parent: 8477ddac2271114a1f74ff539924541eb4974df5. This checkpoint removes the discarded separate Hybrid Invoice editor, retains the original New Invoice shell and preserves eligible-sale fail-closed routing. It does not implement Stage 6E-2.

## Review and cleanup

Reviewed the complete source/test diff and selected documentation. Removed ten genuinely unused total()/lineFact() test helpers across five retained browser suites, with no retained assertion/call-path changes. Restored checkpoint line endings on unchanged locale-test lines. Superseded UI reports/inventories and pre-removal compatibility evidence are explicitly historical, not release readiness.

The Hybrid calculation/writer/snapshot/linked-return services, migrations, feature configuration, PROMO_CHOICE_V1, offline contracts, Dashboard/Profile and original POS remain unchanged. Activation stays OFF and financial holds ON. Eligible new online checkout remains intentionally unavailable (HTTP 409); no legacy fallback or separate checkout route is introduced.

## Exact selected files (31)

Paths include seven tracked deletions; files removed while untracked have no Git deletion record and remain listed in the removal report. Every other selected file is a reviewed modification or addition.

- `app/Controllers/InvoiceController.php`
- `app/Support/HybridWorkerClient.php`
- `app/Views/hybrid-invoice.php`
- `app/Views/invoices.php`
- `docs/hybrid-stage6d-real-data-compatibility.md`
- `docs/hybrid-stage6d-ui-inventory.json`
- `docs/hybrid-stage6d-ui-readiness.md`
- `docs/hybrid-stage6d-ui-removal.md`
- `docs/hybrid-stage6d-visual-correction.md`
- `docs/hybrid-stage6d-visual-inventory.json`
- `docs/hybrid-stage6e1-audit-integration-plan.md`
- `docs/hybrid-stage6e1-checkpoint.md`
- `public/assets/css/hybrid-invoice.css`
- `public/assets/js/hybrid-invoice.js`
- `public/assets/js/hybrid-localization.js`
- `tests/hybrid-return-browser.js`
- `tests/hybrid-stage5-browser.js`
- `tests/hybrid-stage6a-browser.js`
- `tests/hybrid-stage6a-http.js`
- `tests/hybrid-stage6c-locale-browser.js`
- `tests/hybrid-stage6c-locale-inventory.js`
- `tests/hybrid-stage6d-routing.php`
- `tests/hybrid-stage6d-visual-readers.php`
- `tests/hybrid-ui-bridge.php`
- `tests/hybrid-ui-browser.js`
- `tests/hybrid-ui-browser.php`
- `tests/hybrid-ui-controllers.php`
- `tests/hybrid-ui-domain.js`
- `tests/hybrid-ui-preview.ps1`
- `tests/hybrid-ui-removal.php`
- `tests/hybrid-ui-router.php`

## Verification before commit

Focused validation uses no database connection: removal/routes 24 checks; foundation 12 checks; Hybrid foundations 17 cases / 2,635 assertions; policy 20 cases / 13,533 assertions; returns domain 34 checks; invoice-discount/offline JavaScript 12 tests. Changed/new PHP and JS syntax, CRLF-aware whitespace, 16 absent discarded files, original invoice/POS restoration, 83 preservation hashes and 89 critical checkpoint-byte comparisons are checked before staging/commit. Database-backed/browser/hardware suites are not rerun; no operational-readiness claim is made.

Private storage, screenshots, database/account/session files, credentials, logs, IPC and runtime artifacts are excluded. No operational database/3306/3309 connection, main change, migration, posting, activation, deployment, push or merge. The commit SHA and final worktree status are reported after commit, outside this self-referential manifest.
