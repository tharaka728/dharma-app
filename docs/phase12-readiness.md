# Phase 12 — UI improvements, final testing and readiness

**Readiness: READY FOR CONTROLLED PILOT ONLY.** This applies to the existing localhost development setup on a trusted computer, with supervised use and owner-approved real entries. It is **not production approval** or approval to expose XAMPP. Runtime upgrades, HTTPS deployment validation, real-device/printer acceptance and operational backup/recovery sign-off remain prerequisites for production.

Application: http://127.0.0.1:8088/ · MariaDB: `127.0.0.1:3309` · application database: `dharma_sales_phase1`.

## UI changes completed

- Shared role-aware header/sidebar across admin and representative pages; desktop toggle resizes content, mobile starts closed, outside/link clicks and Escape close, background scrolling is locked and focus returns to the menu. A breakpoint/menu timing race found by tests was fixed and covered by unit tests. No admin links are rendered for representatives.
- Dashboard cards are whole-card links, use lightweight local SVG icons, wrap naturally and no longer show Available labels. Visible keyboard/hover/active states and reduced-motion support are retained.
- Responsive grid/flex minimum sizing, long-text wrapping, larger touch controls, consistent spacing, accessible search inputs for large native selects and explicit field-error text. Existing customer/product/location search controls and duplicate-phone review remain intact.
- Wide tables use bounded keyboard-focusable scrolling regions with a sideways-scroll hint, preserving full invoice numbers, prices, quantities and headers. They scroll inside the region rather than widening the page. Invoice paper layouts remain separate from dashboard styling.
- Reports move technical definitions into collapsed **How reports are calculated** help. Material whole-invoice, current-balance and selling-price valuation disclosures remain visible when relevant. Actual refunds retain the unavailable label. CSV limits are enforced with a concise error on encounter, not a permanent main-page paragraph.
- Offline shell uses the same drawer behavior and responsive styles. Two additional **public static** files (`workspace.css`, `workspace.js`) enter the allowlist; eleven static files are cached. No private page, report, CSV, PDF, customer record or session was added to service-worker caches. Waiting-worker update behavior and encrypted local drafts remain preserved.
- Online navigation assets use content-versioned URLs so an older service worker cannot serve stale navigation CSS/JavaScript. The final public offline cache version is v12-2. After saving any draft, close all PWA tabs and reopen to activate the waiting update; **do not clear browser/site storage** to refresh the UI.

## Automatic identifiers

Inspected schema and existing create forms before changes. Database primary keys, representative internal IDs and promotion IDs were already automatic. Existing invoice lifetime numbering, offline UUIDs, payment idempotency keys and stock transaction/transfer references were already generated; their semantics and code paths were not changed.

Only **new blank-code product/customer creates** gain PRD-/CUS- codes derived server-side from a stable server-rendered UUID submission token. The hidden token is retained through validation failures; existing UNIQUE and permanent code-history constraints reject duplicate/concurrent submissions rather than creating another record. A rejected duplicate is reported for review, not silently interpreted as success with different data. Generated codes appear on saved detail pages. They are nonsequential master-data references, not official invoice numbers.

Authorized manual SKU/customer-code overrides and edits remain compatible. Existing records, code ownership histories, auto-increment primary keys and relationships are never renumbered. API clients using blank automatic codes must preserve a valid `create_token` across retries; requests without it are rejected to avoid duplicate automatic creates. There is no manual token entry in the UI. Human names and login usernames remain user-managed identities, not arbitrary technical IDs.

**No migration was necessary.** Migrations 001–014 retain verified checksums; live schema remains 53 tables and 49 triggers. No real products, customers, accounts, stock or financial transactions were created/edited by this phase.

## Other safe defects fixed

- Health previously checked only migrations 001–012 and could claim readiness without invoicing/offline migrations. It now requires all fourteen deployed migrations; database-unavailable responses remain sanitized HTTP 503.
- The current-customer report repeated expensive cohort scans. An equivalent single-pass aggregate preserves financial definitions, current company credit usage, authorization and CSV columns. Under an additional 1,000 products, 500 customers and 500 invoices in a rolled-back test transaction, customer-report time improved from **6,425 ms to 54 ms**. Other measured queries: overview 81 ms, product report 56 ms, stock 26 ms, representative report 105 ms, product/customer lists 9/8 ms. These are local measurements, not production latency guarantees.
- Existing indexes were reviewed (invoices 12, payments 5, stock movements 6, customers 7, products 3); no unmeasured index migration was added.

## Screen and browser verification

Real headless Edge tested login; both dashboards; product list/create/detail/edit; customer list/create/detail/edit; inventory/add/adjust/history; representative list/create/detail/edit/password; allocation/returns/corrections/summary/history; promotion list/create; invoice list/create/settings/detail; reports; offline administration; own representative stock/customers/promotions/invoices/reports; receipt/A4 documents; and offline new/status/pending shell views.

Every matrix screen was checked at **320, 360, 375, 390, 414, 768, 1024 and 1440 px** for page overflow. The browser suite passed **572** screen/viewport/navigation checks, including table-only horizontal scroll, automatic-code form requirements, collapsed report help, role visibility, mobile drawer background lock, Escape/outside close and focus restoration. The source uses the same CSS in the PWA; actual installed-PWA/physical touchscreen behavior remains a hardware UAT item. No actual Android/iPhone or printer testing is claimed.

Complete isolated workflow: automatic product/customer create, receipt, allocation, promotion, cash invoice/change/free units, authorized credit, partial payment, exact stock balances, admin void/reversal/refund liability, reports and a valid PDF. **32 checks passed**, including actual competing product/customer create-token processes and duplicate submission rejection. Synthetic records exist only in `dharma_sales_phase9_test`.

## Regression and reconciliation results

| Group | Passed |
| --- | --- |
| Phase 12 workflow / auto-code races | 32 |
| Responsive screen/drawer/table matrix | 572 |
| New navigation keyboard/resize-race unit checks | 4 |
| Existing JavaScript regressions | 30 |
| Foundation / database constraints | 12 / 16 |
| Products / Customers / locations | 69 / 91 / 91 |
| Historical inventory / simplified product stock | 124 / 59 |
| Representatives / promotions / invoicing | 186 / 121 / 167 |
| Inventory / allocation / promotion / invoice contention | 7 / 11 / 11 / 56 |
| Report foundations / calculations / real-browser exports | 48 / 80 / 42 |
| Authentication, sessions and route protection | 305 |
| Offline service / competing sync / encrypted real-browser PWA | 97 / 7 / 25 |
| Document/PDF / real print/browser authorization | 31 / 33 |

All required regressions passed; representative audit-count assertions vary with accumulated isolated fixtures. All 112 PHP application/test files plus both local CLI wrappers passed lint; all 27 JavaScript files passed syntax checks. The database-unavailable test returned sanitized 503 without credentials or SQL details. Existing migration repeatability/UTC/native-prepare checks passed. HTTP reports and document tests preserve financial/stock hashes; read-only report/PDF services preserve all relevant test-table data. Historical/volume fixtures are rolled back, never committed into production.

Live app-only checks passed invoice totals, posted allocation totals, change separation, no overpayment, paid+free deductions, exact void reversals, refund liabilities, lifetime invoice counters and both warehouse/representative movement ledgers. All twelve report reconciliation checks passed; accepted offline records match authoritative invoices and revoked-device/registration protections passed. No financial posting, credit, offline acceptance or stock-accounting rule was changed.

## Backup and isolated restoration

Fresh verified backup:

```text
C:\mariadb-clean\ayurveda-phase1-local\private\phase12-20261109111954\application.sql
SHA-256: 657ad1304fbb41dec76b5d0f41ddfe825a523056845891e84c2b3c4dfec2bafb
Size: 146,585 bytes
Restore schema: dharma_sales_phase12_restore_20261109111954
```

All **53 tables / 141 rows**, table definitions/constraints, **49 trigger bodies** and **14 migration checksums** matched. Four isolated negative tests rejected invoice/item mutation, negative warehouse stock and duplicate SKU insertion. Source data and grants were unchanged. Folder ACL grants access only to the current Windows owner, SYSTEM and Administrators; files are outside the served directory.

The first restoration (`...restore_20261109111908`) exposed trigger-definer portability: restored triggers referenced a migration account scoped only to the original database, so DML in the new schema failed with TRIGGER permission denial. That attempted copy and its backup were preserved. The successful restore uses a **separate private import file** rebinding all 49 definers to CURRENT_USER, while the original backup remains byte-for-byte intact. Bodies/data/constraints still match. A future production restore must establish the intended narrowly privileged definer account for its target; do not grant the runtime global/DDL privileges or blindly run a source-schema dump on another target.

Reproducible explicit CLI maintenance: `C:\xampp\php\php.exe bin/phase12-backup-restore.php`. It creates a new timestamped backup and fresh restore schema, refuses an existing target, never restores over the application and never deletes existing copies. The backup contains real private data; never place it in public/, a Sandbox mapping or a public repository. Both local restore copies remain admin-only for reviewed cleanup.

Proposed operational policy, **not installed automatically**: nightly verified app backups and a backup before each migration/release; 30 daily and 12 monthly encrypted copies, with owner-approved retention; at least one encrypted off-device copy with separately managed keys; monthly isolated restore rehearsal and alerts on backup failure. No off-device destination or scheduler was configured because no destination/operational owner was supplied. Preserve existing backups until a reviewed retention process exists.

## Security findings and deployment blockers

| Severity | Finding / disposition |
| --- | --- |
| High — production blocker | Installed PHP 8.0.30 and MariaDB 10.4.32 are outside community support. PHP lists 8.0 EOL as 2023-11-26; MariaDB lists 10.4 maintenance ending 2024-06-18. Prepare supported isolated runtimes and regression/restore migration before production; do not upgrade global XAMPP in place under this phase. [PHP EOL](https://www.php.net/eol.php), [MariaDB maintenance policy](https://mariadb.org/about/). |
| High — rollout blocker | No authorized HTTPS representative endpoint, hardened production host or real-device acceptance exists. Current HTTP loopback cookies intentionally cannot claim HTTPS Secure transport. Never expose development XAMPP/phpMyAdmin to the internet. |
| Medium — recovery prerequisite | Trigger-definer portability is now tested and documented, but target-specific definer/grant preparation is required for any deployment restore. |
| Medium — operational prerequisite | Backup scheduling, encrypted off-device copies, monitoring, owner UAT and administrator recovery rehearsal remain operational sign-offs. No email/self-service password recovery was added. Representative admin password reset remains protected and tested. |
| Medium — retention/monitoring | Workspace storage measured about 599 MB: mainly 14 historical synthetic browser profiles (~412 MB) and existing verification-db artifacts (~187 MB). No existing artifacts/backups were deleted. Define reviewed test-artifact cleanup, private log rotation, retention and disk-space alerts before unattended use. |
| Medium — device acceptance | Physical device loss/passphrase loss, iOS/Android browser storage/installation behavior, native share chooser and printer margins/cutting require real-device UAT. Automated lost/revoked-device/conflict flows passed; no hardware protection or universal Bluetooth support is claimed. |
| Low — deployment dependency | PDF generation depends on local Node/Edge paths/fonts and allows one renderer at a time; busy/unavailable rendering safely returns 503 with browser-print fallback. Provision/test equivalent dependencies on the target host. |

Reviewed controls: password hashing, generic login failures and DB-backed throttling; rotated/expiring sessions, HttpOnly/SameSite cookies and HTTPS-aware Secure flag; CSRF on mutations; fresh server-side roles and current customer assignment; native PDO parameters and output escaping; encrypted passphrase vault, locking and signed device submissions; revocation/reconciliation controls; private invoice/PDF/CSV no-store responses; restricted runtime DML grants; credentials outside public/; sanitized application error messages. Practical negative suites passed in isolation. Live probes for `.env`, app/config/storage/tests/bin and traversal paths returned 404; anonymous report export redirected to login without source/secret leakage. This is a scoped code/automated review, not a third-party penetration-test certification.

## HTTPS deployment proposal — no deployment performed

1. Build a separate staging/production host with supported patched PHP/MariaDB, pinned/tested application dependencies, non-root service identities and only public/ as document root. Keep DB on a private interface/network, separate schema/runtime credentials from migration/backup credentials, deny public DB/phpMyAdmin access and protect private files with OS ACLs.
2. Establish owner-approved DNS and trusted HTTPS through a hardened reverse proxy/web server. Validate TLS/certificate renewal, trusted proxy handling, Secure cookies, rate limiting, request limits, no directory listing, private error logs, firewall and monitoring. Add HSTS only after HTTPS is correctly established; do not change local development global settings.
3. Restore into a fresh target using the tested definer procedure, compare all data/checksums/constraints, run reconciliations and full tests, then perform role-based owner UAT. Keep immutable financial/offline identifiers unchanged.
4. Before any origin/device migration, synchronize/reconcile outstanding cash and stock, retain encrypted exports and test a controlled registration/recovery plan. Never clear pending drafts or assume unsubmitted records exist on the server.
5. Test actual Android/iPhone installation, offline restart, waiting updates with pending drafts, storage recovery, printing and manual file sharing on the approved HTTPS staging origin. Maintain explicit conflict/revocation review; no automatic refunds or external payment integrations.
6. Deploy only after separate owner approval, supported-runtime validation, successful restore/recovery rehearsal, monitored backups and completed UAT. Current `127.0.0.1:8088` is not reachable from a phone; do not change localhost bindings as a shortcut.

## Changed files and scope

- New: `app/Support/BusinessCode.php`, `Workspace.php`; `app/Views/workspace-header.php`, `workspace-footer.php`; `public/assets/css/workspace.css`, `public/assets/js/workspace.js`.
- Updated: Products/Customers services and create forms; shared-header/footer integration in dashboard, products, customers, product stock, representatives, promotions, invoices, offline admin, reports and retained legacy inventory views; Reports customer aggregate and presentation; HealthController migration readiness.
- Updated: app.css import, offline shell, service-worker public allowlist/version, isolated CLI test command mapping and PWA cache-count expectation.
- New tests: `phase12.php`, `phase12-code-worker.php`, `phase12-browser.php/.js`, `phase12-performance.php`, `phase12-failure.php`, `workspace-ui.js`.
- New maintenance: `bin/phase12-backup-restore.php`; documentation/README/AGENTS/design amendments.
- **No SQL migration, primary-key change, historical renumbering, financial posting change, global XAMPP change, port change, WordPress/recovery modification or public deployment.**

[Owner acceptance checklist and exact URLs](phase12-uat.md). Immediate priorities: verify the updated UI with the owner on real devices; maintain secure daily/off-device backups; prepare supported HTTPS staging; rehearse account/device recovery and physical printing. Stop before public deployment or additional development phases.
