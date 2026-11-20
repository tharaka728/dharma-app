# Phase 12 completion verification — 9 November 2026

Decision: **READY FOR CONTROLLED PILOT ONLY**, on the existing trusted localhost computer. This is not production or Android rollout approval. Verification was rerun against the actual project. No application behavior, financial rules, historical identifiers or deployed migrations were changed during this audit.

Application: http://127.0.0.1:8088/ · Customers: /admin/customers · Inventory: /admin/inventory · Reports: /admin/reports.

## Requested checks

| # | Item | Result and evidence | Remaining limits |
| --- | --- | --- | --- |
| 1 | Mobile responsiveness | PASS — real headless Edge matrix, eight widths: 320, 360, 375, 390, 414, 768, 1024, 1440; 572 combined screen/layout/navigation assertions. Covers login, both roles, CRUD forms/details, stock, promotions, invoice review/details, reports, offline shell and document views. Mobile dashboard screenshot visually inspected. | Physical Android keyboard, touch, TalkBack and installed-PWA layouts NOT TESTED. This is the enumerated route/state matrix, not every possible data/state combination. |
| 2 | Independently scrolling tables | PASS — bounded table wrappers, keyboard focus, scroll hints; browser verifies table scroll position changes without document overflow. | Physical swipe and installed-PWA behavior NOT TESTED. The browser scroll test is programmatic, not a touchscreen gesture. |
| 3 | Collapsible sidebar | PASS — desktop resize/toggle; mobile closed default, overlay, Escape/outside close, focus restoration/trap, background lock and breakpoint race covered. | Physical device acceptance pending. |
| 4 | Dashboard icons/labels | PASS — local SVG module icons and clickable cards; browser asserts icon count and absence of Available labels. | None found in tested dashboards. |
| 5 | Reports cleanup | PASS — specified technical definitions are in closed help; permanent CSV-limit paragraph removed. Current-balance, whole-invoice filtering, stock valuation and actual-refunds-unavailable disclosures remain where relevant; documentation retained. | Actual refund posting remains intentionally unavailable. |
| 6 | Automatic references | PASS — new blank product/customer codes generated server-side from stable submission tokens; duplicate and competing requests tested. Existing stock/transfer/payment references and database internal IDs already automatic. Manual meaningful usernames/SKUs remain supported. | Generated product/customer references are long UUID-based codes, not sequential invoice numbers. |
| 7 | Invoice/offline identifiers | PASS — lifetime numbering, immutable snapshots, UUID idempotency and counter checks passed; no renumbering/migration changes. | No historical identifiers changed by this audit. |
| 8 | Phases 1–11 | PASS — foundation, constraints, auth, products, customers/locations, legacy and product stock, representatives, promotions, invoices, offline, reports and documents regression suites rerun. | Coverage is automated regression evidence, not a guarantee against every defect. |
| 9 | Financial/stock reconciliation | PASS — live invoice totals, payments/change, paid/free stock, void reversal, refund liabilities, counters, warehouse/rep ledger and transfer conservation checks. | Live data currently contains one invoice; richer scenarios use the isolated test database. |
| 10 | PWA security/synchronization | PASS — isolated service, concurrent synchronization, actual encrypted IndexedDB/offline restart/response-loss browser tests; static-only service worker allowlist. | Live server has zero registered offline devices/submissions. Real Android installation, device loss/storage eviction and phone network interruptions NOT TESTED. |
| 11 | Thermal/A4/PDF | PASS — document authorization, immutable read-only rendering, local PDF generation and browser pagination/font checks. | Actual printer, cutter/margins, Android print service and native sharing NOT TESTED. No universal Bluetooth/ESC-POS support claimed. |
| 12 | Backup/isolated restore | PASS — new private SQL dump restored into a fresh verification schema; all table rows/schema, triggers, migration checksums and four negative constraint tests passed. | This is a same-server isolated-schema restore, not disaster recovery on a second machine. Scheduling/encrypted off-device backup and recovery rehearsal remain pending. |

## Fresh automated results

All commands completed with exit code zero. Synthetic writes were confined to `dharma_sales_phase9_test`; no operational fixture/reset commands were used.

| Suite | Passed checks |
| --- | ---: |
| Foundation / database constraints | 12 / 16 |
| Products / customers / locations | 69 / 91 / 91 |
| Legacy inventory / simplified product stock | 124 / 59 |
| Representatives / promotions / invoices | 227 / 121 / 167 |
| Inventory / representative / promotion / invoice contention | 7 / 11 / 11 / 56 |
| Report foundations / calculations / browser | 48 / 80 / 42 |
| Phase 12 workflow and code races / browser layout | 32 / 572 |
| Offline service / successful concurrent synchronization / PWA browser | 97 / 7 / 25 |
| Authentication, sessions, permissions and route protection | 305 |
| Documents / document browser | 31 / 33 |
| JavaScript unit tests | 34 |

PHP lint: 114 files passed. JavaScript syntax: 27 files passed. Sanitized database-unavailable check passed. Performance run repeated the 80 reporting checks, added 1,000 products/500 customers/500 invoices inside a rolled-back test transaction, and verified preservation. Timings in milliseconds: overview 89, products 57, customers 58, inventory 49, representatives 135, product list 11, customer list 9. These are local measurements, not production guarantees. Representative suite count includes accumulated isolated fixture cohorts.

No code fix or migration was necessary for this verification. The only project change is this evidence report; generated test artifacts and the new private backup/isolated restore are retained.

## Fresh backup evidence

- Source: `dharma_sales_phase1` at `127.0.0.1:3309`.
- Backup: `C:\mariadb-clean\ayurveda-phase1-local\private\phase12-20261109115701\application.sql`.
- SHA-256: `fc4471042e6a9478f9459e82d4b6ae053f3368e5689687d85af45cb072499011` (146,585 bytes).
- Fresh restore: `dharma_sales_phase12_restore_20261109115701`; 53 tables, 141 rows, 49 triggers, 14 checksum-verified migrations; data and schema match.
- Four restore-only negative checks: immutable invoice update/item deletion, negative stock and duplicate SKU rejected; transaction rolled back.
- Original dump unchanged. A separate import copy rebinds 49 trigger definers to the restore administrator; deployment requires deliberate target-specific definer/grant setup. No source account privileges broadened.
- New private backup folder ACL permits only the owner, SYSTEM and Administrators. Old backups and restore schemas preserved.

## Security and operational checks

Live health returned ready. Runtime connection verified exact schema/port, UTC, native PDO prepares and DML-only privileges. All fourteen migration checksums passed; no migration applied. Apache 8088 and MariaDB 3309 listen only on 127.0.0.1. Private app/config/storage/tests/bin and .env HTTP probes returned 404. Database-unavailable simulation returns sanitized 503 without stopping MariaDB.

Production blockers: installed PHP 8.0.30 and MariaDB 10.4.32 are outside community support; no approved phone-accessible HTTPS environment, hardware UAT, operational off-device backup/monitoring or recovery sign-off. See [PHP unsupported branches](https://www.php.net/eol.php) and [MariaDB maintenance](https://mariadb.org/about/). These require separate deployment work; this audit does not modify global XAMPP or expose any service.

## Android installation — next actions

1. Prepare a separate, access-controlled staging host with supported PHP/MariaDB, trusted HTTPS, only public/ served, private database and least-privilege credentials. Keep the current localhost setup unchanged. Choose an owner-approved private access method/domain; no phone URL exists yet. A phone's 127.0.0.1 is the phone itself.
2. Restore a fresh application copy with deliberate trigger-definer mapping; run regression, reconciliation and restore checks on that environment. Validate TLS, Secure cookies, proxy/firewall settings and local PDF renderer dependencies. Reconcile/export existing pending device records before changing origins; never clear unresolved browser data.
3. On the actual Android phone, open the approved HTTPS URL in current Chrome or Edge. Sign in as the real representative, register the single authorized device, set its separate device passphrase, and refresh the authorized snapshot. Use the browser's Install app/Add to Home screen option when offered. Installation behavior varies by browser; no APK is required for this PWA. [Installation reference](https://web.dev/learn/pwa/installation); [service-worker secure-context requirement](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API).
4. On isolated staging, verify installed launch, table swipes, keyboard/navigation, airplane-mode restart/unlock, 15-minute lock, stale snapshots, pending cash preservation, reconnect/retry without duplicate posting, conflicts/revocation, update behavior and recovery. Test the intended physical printer, PDF download and share chooser. Owner signs off before real mobile sales.

Stop at Phase 12 verification. No production deployment, public network exposure or unrelated system changes authorized or performed.

Sinhala localization verification is documented in [sinhala-localization.md](sinhala-localization.md). Historical readiness and hardware-UAT limitations above remain applicable.

POS checkout layout repair: see [pos-checkout-layout-fix.md](pos-checkout-layout-fix.md) for the reproduced root cause, bilingual eight-width checkout tests, screenshots and safe PWA update instructions.
