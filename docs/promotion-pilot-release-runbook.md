# Promotion benefit pilot release runbook

Status: implementation complete, feature DISABLED everywhere. Prepared 2026-11-22. Nothing in this document has been applied to the operational installation. Every activation step requires the owner's explicit approval first.

Approved policy (owner decisions, 2026-11-21):

- Decision A: authorized legacy signed offline invoices are preserved unchanged. The three new benefit modes are online only.
- Decision B: `ORDINARY_INVOICE_DISCOUNT` at preset 0% is the official no-benefit representation ("0% — No Promotion or Discount").
- Decision C: one representative runs a controlled pilot first; everyone else keeps the legacy checkout.

## 1. Implementation status

- Policy version `PROMO_CHOICE_V1`, migration `024_promotion_benefit_modes.sql`, immutable invoice and line benefit snapshots.
- Server-side enforcement in `app/Services/BenefitPolicy.php` (`pilotRepIds()`, `pilotMember()`, `checkoutAuthorized()`) and `app/Services/Invoices.php` (`normalize()` pilot checks, `issue()` offline-context benefit rejection).
- Global gate: `config/benefit-modes.php` `enabled => false`. Pilot allowlist: `pilot_representatives => []` (empty). Both unchanged from delivery.
- Localization: English and Sinhala strings for all new messages; `public/assets/i18n/catalogue.js` rebuilt.

## 2. Feature-gate behavior

| Gate | Pilot allowlist | Result |
| --- | --- | --- |
| OFF | any | No benefit UI, no benefit posting; legacy checkout only. Verified by the gate-off suite (4 checks) on a migrated database. |
| ON | account not listed | 403 "Promotion benefit modes are not available for this account." on review and issue; benefit UI hidden; legacy checkout preserved. |
| ON | account listed | Benefit checkout required online; plain online checkout rejected with 409; legacy signed offline sync still accepted. |

Allowlist membership is re-checked at issue time: removing an account between review and issue blocks the issue with 403; restoring it allows issuing again.

## 3. Required migration

`024_promotion_benefit_modes.sql`: creates `invoice_benefit_snapshots` and `invoice_benefit_line_snapshots` with foreign keys, CHECK constraints, a unique key per invoice line, and four immutability triggers (no UPDATE, no DELETE on either table). Additive only; no DROP or TRUNCATE; no changes to existing tables.

Apply with `php bin/migrate.php` (uses `App\Support\Migrator`): records name, SHA-256 checksum and status in `schema_migrations`, refuses to rerun a changed or partially applied migration, and takes the named lock `dharma_schema_migrations` against concurrent runs.

## 4. Migration rehearsal result (isolated, 2026-11-22)

- Rehearsed against a separate database copied from the migrated synthetic test set, then reset to the pre-024 state (23 migrations, 82 synthetic invoices, 65 tables).
- Migration 024 applied alone in 0.025 s in this environment.
- After applying, all 64 pre-existing data tables were byte-identical to the original copy (per-table SHA-256 over primary-key-ordered dumps); only `schema_migrations` changed (the 024 row, status `applied`).
- All 82 legacy invoices remained readable and financially unchanged.
- Triggers verified live: UPDATE and DELETE on the snapshot tables are rejected with SQLSTATE 45000; the row survives. Foreign keys reject invalid actor references.
- Feature-disabled behavior after migration verified: with 024 applied and the gate off, `BenefitPolicy::enabled()` is false and the gate-off suite passes.
- Locking: DDL is brief (two empty tables, four triggers). MariaDB DDL causes implicit commits, so the migration is NOT fully transactional; a mid-migration failure can leave partial DDL. Recovery: the Migrator then refuses a blind rerun ("Migration changed or partially applied"). Restore the pre-migration backup into place (or reconcile the DDL manually) before re-applying.
- The rehearsal database was dropped after verification.

## 5. Service worker and cache safety (section 6.4 audit)

- Checkout scripts (`invoice-benefit.js`, `invoice-discount.js`, `pos.js`) load with content-hash `?v=` URLs and are never in the service worker allowlist, so updated checkout code reaches clients on the next page load. Authenticated HTML and APIs are never cached.
- `public/sw.js` caches only a static allowlist under cache name `dharma-static-ui-v15`. It never calls `skipWaiting`: an update activates after existing tabs close. It never touches IndexedDB, encryption keys, device enrollment, or offline drafts.
- Phase 3 finding and fix: the compiled Sinhala catalogue shipped under a fixed `?v=si-v2` URL in the allowlist, so installed PWAs would have kept the stale cached catalogue after the Phase 3 string additions. The asset version was bumped to `si-v4` (in `sw.js`, `app/Views/localization-head.php`, `public/offline/index.html`) and the cache name to `dharma-static-ui-v15`. New assets install on the next natural activation; unsynchronized drafts are preserved.
- No silent fallback to older financial rules: all financial computation is server-side and re-authorized at issue time; client scripts are presentation only. A stale client posting a stale policy version is rejected.
- Rule for every future asset change that affects allowlisted files: bump the file's version string and the SW cache name in the same commit. Never clear browser storage to force an update; never uninstall/reinstall the PWA.

## 6. Operational database backup procedure (prepared, not executed)

Target: MariaDB on 127.0.0.1 port 3309, database `dharma_sales_phase1` ONLY. Never touch port 3306. The operational install is the owner's Windows XAMPP; the MariaDB client lives at `C:/xampp/mysql/bin/` there. Confirm the exact binary and the configured authentication on the operational machine before running anything.

Prepared command (run on the operational machine, credentials via a protected option file, never on the command line, never in this report):

```
C:/xampp/mysql/bin/mariadb-dump.exe --defaults-extra-file=<protected-option-file> \
  --protocol=tcp --host=127.0.0.1 --port=3309 \
  --single-transaction --routines --events \
  dharma_sales_phase1 > D:\dharma-backups\dharma_sales_phase1-<UTC timestamp>.sql
```

Then record `certutil -hashfile <file> SHA256` (or equivalent) next to the backup.

- Storage space: verified locally (20 GB free in this environment); verify free space on the operational drive before running.
- Engines: all financial tables are InnoDB, so `--single-transaction` gives a consistent snapshot without locking reads; DDL during the dump window is the remaining hazard, so run it while nobody migrates.
- Routines/triggers/events: covered by `--routines --events`; triggers are dumped with their tables (73 triggers verified in the drill).
- Permissions: the backup account needs SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT, RELOAD/PROCESS as applicable; restore needs full privileges on the target database only.
- Protection: keep backups outside the Git repository and outside the web root, on a drive the owner controls. Never commit a dump.

Isolated restore drill result (2026-11-22): dumped the synthetic test database (882,592 bytes, SHA-256 recorded), restored into a separate drill database, compared all 67 tables by primary-key-ordered SHA-256: 67/67 identical, 73 triggers present, all 82 invoices readable. Drill database dropped afterwards. A dump alone was not treated as proof; the restore is the proof.

Restore procedure (only ever into an isolated database unless the owner approves a reconciliation-based recovery):

```
C:/xampp/mysql/bin/mysql.exe --defaults-extra-file=<protected-option-file> \
  --protocol=tcp --host=127.0.0.1 --port=3309 \
  --default-character-set=utf8mb4 --binary-mode <restored-database-name> < <backup-file>
```

Application configuration after an isolated restore: point `DB_NAME` at the restored database; never restore over `dharma_sales_phase1` while it holds newer invoices, payments or stock movements.

## 7. GitHub recovery checkpoint (prepared, nothing pushed)

- Branch `main`, remote verified during Phase 2. Local Phase 2 + Phase 3 commits sit on baseline `7d82253`; local/remote divergence and `git fetch` must be checked on the owner's machine before any push.
- Backup tag `backup-before-promotion-2026-11-21` exists from Phase 2.
- Pre-release recovery tag: `pre-release-promo-pilot-2026-11-22` pointing at baseline commit `7d82253` (the last tested version before the promotion work). This is a recovery marker, not a stable-release tag.
- Proposed push sequence (owner approval required first): `git fetch origin`, confirm divergence, `git push origin main`, `git push origin backup-before-promotion-2026-11-21 pre-release-promo-pilot-2026-11-22`.
- Never `git reset --hard`, `git clean -fd`, or force push. Review every commit for secrets before pushing: no dumps, passwords, private config, customer data, real invoice PDFs, tokens, device passphrases, keys, private logs, or encrypted draft exports.
- Git restores source code only. It does not restore database records or Android PWA storage; database recovery is the backup/restore procedure above, and PWA storage must never be cleared as a recovery action.

## 8. Pre-existing test failures — individual investigations

Baseline comparison: each failure was reproduced on the unmodified baseline (via `git stash` of all Phase 2/3 work), so none is caused by `PROMO_CHOICE_V1` or the Phase 3 decisions. None was skipped, weakened, or hidden.

1. `tests/customers.php` — "Inventory request rejected." from `Invoices::actor()` (app/Services/Invoices.php:65).
   Expected: customer + invoice permission checks pass against the synthetic fixture actors. Actual: an actor permission lookup rejects the operation. Present on the baseline commit before PROMO_CHOICE_V1. Cause: the synthetic fixture account state in this isolated Linux database does not match the test's expected actor (environment/data-dependent, not a logic change). Impact: fails closed (permission denied); no financial, authorization, stock or idempotency path is weakened. Does not affect the pilot: pilot authorization uses the verified server-side allowlist, exercised by 24 passing pilot checks. Recommendation: outside this release; refresh the synthetic fixtures on the operational Windows environment where this suite was green.

2. `tests/inventory.php` — "Dashboard immediately shows success, new quantity and Add Stock button" (tests/inventory.php:7).
   Expected: browser check of the inventory dashboard after adding stock. Actual: dashboard assertion fails in this sandbox. Present on the baseline. Cause: environment-dependent browser/dashboard behavior on the Linux sandbox, not calculation logic; the domain-level inventory suites (`inventory-concurrency`, stock conservation inside `representatives` 205 checks and `offline` 122 checks) all pass. Impact: no financial or stock-correctness impact. Does not affect the pilot. Recommendation: outside this release; verify on the operational Windows environment.

3. `tests/invoice-documents.php` — "Sinhala/Tamil Windows font embedded" (tests/invoice-documents.php:7).
   Expected: generated PDF embeds the Windows Sinhala/Tamil font (Nirmala). Actual: font unavailable in this Linux sandbox. Present on the baseline. Cause: missing operating-system font, by design a Windows-environment check. Impact: none on financial correctness; receipt/PDF amounts and layout checks pass. Does not affect the pilot; physical print remains NOT VERIFIED regardless (see section 10). Recommendation: outside this release; run on the owner's Windows machine.

4. `tests/mixed-oil-browser.php` — "Timed out: combined six unit reward" (browser driver).
   Expected: headless-browser mixed-oil checkout preview reaches the combined six-unit reward state. Actual: page loads (Sinhala title renders) but the preview assertion times out in this sandbox. Reproduced on the unmodified Phase 2 commit 899da3e with all Phase 3 changes stashed, so it predates Phase 3; the domain suite `mixed-oil` (107 checks, including PHP/JS parity and signed sync) passes. Found during Phase 3 regression (it is a fourth pre-existing failure, beyond the three reported earlier). Impact: no financial impact; mixed-oil suppression under benefit modes is covered by passing domain and pilot checks. Recommendation: outside this release; investigate browser timing/locale on the operational environment.

5. `tests/mobile-installation.php` — "Waiting update preserves active tabs" (tests/mobile-installation.php:29).
   Expected: `public/sw.js` cache identity `dharma-static-pos-v1` and no `skipWaiting()`. Actual: sw.js has carried `dharma-static-ui-v14` since the first commit; the literal `pos-v1` never existed in any committed sw.js, so the assertion was stale from introduction. Phase 3 bumps the cache to `dharma-static-ui-v15` for the catalogue fix (section 5). The guarded property still holds: no `skipWaiting()`, no IndexedDB clearing, waiting updates. Impact: none. Recommendation: outside this release; correct the stale literal in the test during maintenance rather than freezing asset versions.

6. `tests/localization-browser.php`, `tests/phase12-browser.php` — browser driver evaluation `TypeError: Cannot read properties of null (reading 'lang')` in this sandbox. Verified identical on the baseline commit 7d82253 in a clean worktree, so pre-existing and environment-related (headless-browser page-load timing in this Linux sandbox); the domain suites they visualize (`localization` 111 checks, `phase12` 35 checks) pass. Impact: none on financials or authorization. Recommendation: outside this release; run on the owner's Windows/browser environment.

7. `tests/reports-browser.php` — "Report browser verification failed" in this sandbox; also fails on the baseline commit (the exact sub-assertion differs by environment, and on baseline it fails earlier). The underlying `reports` domain suite (85 checks, including CSV and reconciliation) passes. Impact: none on financial correctness. Recommendation: outside this release; verify in the operational browser environment.

8. `tests/historical-return-migrator.php` — `Illegal mix of collations (utf8mb4_unicode_ci,IMPLICIT) and (utf8mb4_general_ci,IMPLICIT)` while building its own scratch schema. Cause: this sandbox's MariaDB server default collation differs from the test database collation the harness creates; nothing in PROMO_CHOICE_V1 touches those paths (migration 006 predates it). The companion `historical-return-restored-migrator` (migration runner passed twice, including 024) and `historical-return-http` (101 checks) pass. Impact: none on the pilot; historical-return migrations 020-023 are already applied and verified in the operational schema history. Recommendation: outside this release; collation-default difference between sandbox and the owner's XAMPP.

None of the eight touches financial integrity, authorization, stock, invoice correctness, idempotency or legacy offline synchronization, so none is a release blocker per the owner's criterion.

## 9. Controlled operational pilot scenarios (for owner approval; isolated data first)

Run every scenario against isolated test data before any genuine transaction. Do not create fake operational invoices to exercise the scenarios. Preview verification does not authorize financial posting. The first real financial transaction requires the owner's confirmation at that moment.

1. Free Promotion: eligible active Buy-X-Get-Y product. Verify correct free quantity, no separate invoice discount, paid+free stock demand, correct receipt.
2. Promotion Discount Instead: one threshold-qualified product and one ineligible product. Verify discount only on qualifying lines, no free products, correct payable, paid-only stock demand.
3. Ordinary Invoice Discount: whole-invoice eligible discount, no free products, no monetary product promotions, correct payable.
4. No Benefit: `ORDINARY_INVOICE_DISCOUNT` at 0%. Verify no discounts, no free products, no monetary promotions, normal merchandise payable. (Verified exactly in isolated testing: 4 x LKR 125.50 = 502.00 eligible, 3 free units and LKR 100.00 monetary benefit suppressed, snapshot 0.00, paid-only stock.)
5. Connectivity Loss: disconnect before final checkout. Verify no unauthorized invoice, no automatic legacy fallback, no duplicate invoice, cart and nonfinancial selection preserved where supported, and the exact message "This promotion benefit mode requires an online connection to complete checkout." (Sinhala equivalent verified). Reconnect: a renewed server review is required before issuing.

## 10. Receipts and printing

Sample receipts for all three benefit modes and the 0% no-benefit case were produced from isolated synthetic fixtures and are clearly identified as test receipts; each shows the selected benefit mode, paid quantities, actual awarded free quantities, the discount rate and amount where applicable, the final payable and cash/credit status. Suppressed free quantities are never printed as awarded. The owner's 58mm improvements (larger fonts, reduced leading blank space, Sinhala readability, wrapping, amount alignment) are unchanged. Physical Bluetooth printing is NOT VERIFIED: no printer exists in this environment, and browser rendering does not prove physical printing. No paid Android print service was introduced.

## 11. Redmi Note 15 mobile preparation

Use the existing localhost-only environment. On the owner's machine:

```
cd C:\platform-tools
.\adb devices
.\adb reverse tcp:8088 tcp:8088
```

Then on the phone: `http://localhost:8088/login`, POS at `http://localhost:8088/rep/invoices/new`. Verify: active server connection, correct representative login, correct customer assignment, updated POS assets (hard-refresh once after activation; the service worker updates without clearing storage), feature availability for the pilot account only, three-mode selector, discount percentage selection, custom discount input, product eligibility indicators, server review, final payable, stock and payment behavior.

Preserve the phone's existing encrypted offline workspace. The owner previously forgot the device passphrase and the draft contents are unverified: do not clear or reset the vault, and do not make ordinary online login depend on destructive offline recovery. Physical mobile verification is NOT VERIFIED here (no device in this environment).

## 12. Activation runbook (owner approval required at each gate)

1. Confirm the source recovery checkpoint: baseline `7d82253`, tags `backup-before-promotion-2026-11-21` and `pre-release-promo-pilot-2026-11-22`.
2. Take the operational backup (section 6) and verify its SHA-256. Confirm free disk space first.
3. Push the reviewed commits to GitHub (section 7 sequence).
4. Deploy the code to the operational machine.
5. Apply migrations: `php bin/migrate.php`. Post-migration schema verification: 24 rows in `schema_migrations` all `applied` with matching checksums; the two snapshot tables and four triggers exist; invoice counts unchanged.
6. Feature-disabled smoke test: with the gate still off, an ordinary checkout works and no benefit UI appears.
7. Owner confirms the pilot representative. Candidate from the historical-return configuration: username `nissanka`, user ID 156. This candidate is NOT verified: the operational database was not inspected during this task, so the owner must confirm the account exists, is active, and is the intended person before it is listed.
8. Set `pilot_representatives` in `config/benefit-modes.php` to the confirmed user ID, exactly `[156]`.
9. Enable the policy for the operational database only: `enabled => true` and `databases => ['dharma_sales_phase1']` in `config/benefit-modes.php`.
10. Keep the first-posting hold ON: `posting_hold => true` must remain. With the hold active the pilot can select benefit modes and get server-authoritative reviewed totals, but no PROMO_CHOICE_V1 invoice can post - the hold is enforced inside `Invoices::issue`, so direct API submissions are blocked too, and replays of previously issued invoices stay safe. Verified in isolation (6 hold checks in `tests/promo-choice-pilot.php`).
11. Verify a non-pilot representative is unaffected: no benefit UI, legacy checkout works, benefit API calls return 403.
12. Read-only mobile preview checks on the Redmi (section 11).
13. FIRST FINANCIAL INVOICE - MANDATORY STOP: the owner has NOT authorized the first real posting. Only after the owner's separate, explicit first-posting approval: set `posting_hold => false`, refresh and revalidate the review, then post and reconcile the first genuine invoice, stock movements, payment and receipt against the review. Never treat an old reviewed total as permanently authorized.
14. Disable the pilot immediately (section 13) if a critical issue appears.

## 13. Emergency feature disable

Set `enabled => false` in `config/benefit-modes.php`. No records are deleted. This immediately blocks all new PROMO_CHOICE_V1 postings for every account. Already-issued invoices and their immutable snapshots remain readable on receipts, reports and invoice history; legacy offline synchronization, historical-return configuration, and encrypted offline data are untouched.

## 14. Code rollback

Safe rollback: `git revert` of the promotion commits, or redeploy from the verified backup tag. Before rolling code back, confirm the older code tolerates migration 024 (it is additive; the pre-promotion code never references the new tables, and was verified running against a migrated database during Phase 2 isolation testing). If new-policy invoices were already posted, prefer disabling the feature (section 13) and keeping the current readers so benefit snapshots, receipts and reports keep rendering. Never restore an old database backup over newer invoices, payments or stock movements; any database restore is a separate reconciliation-based recovery plan requiring explicit owner approval.

## 15. Remaining release blockers

- Owner confirmation of the pilot representative (candidate `nissanka` / ID 156 unverified against the operational database).
- Physical Redmi Note 15 verification: NOT VERIFIED (no device here).
- Physical 58mm Bluetooth printing: NOT VERIFIED (no printer here).
- Operational backup not yet taken (requires owner authorization and the operational machine).
- GitHub push not done (requires owner approval).
