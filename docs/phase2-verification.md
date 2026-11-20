# Phase 2 implementation and verification

Authorized scope: secure authentication and initial role dashboards. No business workflows, totals, offline billing, PWA sync or reporting are implied by placeholder navigation.

## Delivered

Responsive local Bootstrap login and admin/representative dashboards, labelled form controls, generic failure messages and clearly planned module navigation. POST/CSRF logout is available in both dashboards. Root redirects to login. Public `/api/v1/health` retains its response format and reports ready after all three migrations are applied.

Every protected page/API reloads identity and checks active state, a password-derived session stamp and role. Sessions expire after 30 minutes idle/8 hours absolute and regenerate on login/every 15 minutes. Cookie-only strict sessions use HttpOnly/SameSite; Secure applies on HTTPS. Rate-limit reservations lock database buckets in a consistent order and cover account/IP pairs and aggregate client IP. Identity APIs never expose hashes or cost prices. Implementation follows PHP's [session security guidance](https://www.php.net/manual/en/features.session.security.management.php).

## Database safeguards

Verified exact server `127.0.0.1:3309`, MariaDB 10.4.32, application `dharma_sales_phase1` and both existing migration checksums before new DDL. Application-only backup completed successfully at:

`C:\mariadb-clean\ayurveda-phase1-local\private\phase2-backup\before-phase2.sql`

SHA256: `E019E55E99CCD78EABA5555EA183F489E84845F66894525F01DAFE5AA0D7FDA1`.

No restore rehearsal or additional schema was authorized. Migration 003 adds only the rate-limit table. All three migrations are applied and match source checksums; repeated application reports Already applied. Schema now has 33 tables and the same 26 immutable-history triggers. Existing runtime credentials remain unchanged/DML-only. No migration was run against the earlier test database or WordPress.

## Actual results

- PHP lint: 23 application/test/local setup files passed; subsequently changed PHP files re-linted.
- Existing foundation suite: 12 passed.
- Existing database regression: 16 constraint checks passed; fixtures rolled back; repeatability, UTC and native prepares passed, using explicit owner-authorized app-target opt-in.
- New suite: 73 authentication/authorization/session/route/rate-limit checks passed, using local HTTP sessions and removing its temporary users, including pre-login cookie and logged-out cookie replay rejection.
- Coverage: cookie flags, CSRF denial, wrong/unknown/inactive identities, role redirects, changed session ID, escaped display names, secret-free identity payloads, pre-login CSRF invalidation, logout invalidation, rep denial from admin page/API, live database role changes, password/deactivation revocation, pair/IP limits and expired windows, no bucket growth on blocked requests, idle/absolute/clock-rollback expiry, unknown-route protection and public health.
- Runtime PDO/grants verified; listeners remain `127.0.0.1:8088` and `127.0.0.1:3309`; login HTML and public health returned 200/ready.
- An initial test assertion inspected a response reusing an existing cookie rather than the response creating it. The assertion was corrected; final suite passed.
- Windows PowerShell lacks `ProcessStartInfo.StandardInputEncoding` here. The admin helper uses explicit UTF-8 bytes through the private pipe instead; the ASCII/Unicode transport check passed without database access or credential output.

No WordPress files/data, existing recovery backups, global XAMPP settings or port 3306 configuration were changed. The backup above is a new app-only artifact.

## Operator step and limits

Create the first real admin with the hidden-password helper in [setup instructions](phase2-setup.md). No default admin/password was installed. The interactive prompt was syntax checked but not run to create a real owner account during automated tests.

Browser behavior was tested through automated local HTTP sessions, not a visual browser/screen-reader review. Modules remain intentionally unavailable. No signup, password recovery, user-management UI, offline access, invoices, queue engine or public deployment was added. Final HTTP tests use the separate loopback source `127.0.0.2` so they do not consume the ordinary browser IP budget; attempts from earlier test revisions on `127.0.0.1` expire naturally after 15 minutes. Old limiter records can be pruned through future maintenance; no scheduled task/cron was installed.
