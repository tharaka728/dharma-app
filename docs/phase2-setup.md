# Phase 2 local setup

Current URLs:

- Login: **http://127.0.0.1:8088/login**
- Admin dashboard: **http://127.0.0.1:8088/admin**
- Sales representative dashboard: **http://127.0.0.1:8088/rep**
- Public health: **http://127.0.0.1:8088/api/v1/health**
- Protected identity API: `/api/v1/me`.
- Protected workspace status APIs: `/api/v1/admin/dashboard`, `/api/v1/rep/dashboard`.

Root redirects to login; an authenticated visitor to login is redirected to their role's workspace. Admin retains the existing all-role authorization policy. Representatives cannot open admin pages/APIs. No role or identity supplied by a browser is trusted.

Only secure access and the initial dashboards are functional. Products, Customers, Inventory, Sales Representatives, Promotions, Invoices, Reports, Assigned Stock, New Invoice, Sales History and Sync Status are visibly marked as planned. No business totals or sample transactions were seeded. Offline invoicing, PWA storage/sync, printing, password recovery and user-management screens are not implemented.

## Create the first admin

Run this yourself in a private PowerShell terminal under the owner account:

```powershell
Set-Location 'C:\xampp\htdocs\dharma chemical sales app'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\bin\create-admin-local.ps1 -Username owner -DisplayName 'Local Owner'
```

The script asks for a password twice using hidden input. Choose a unique 12–72-byte password and keep it in your password manager. It uses the existing restricted runtime configuration from `C:\mariadb-clean\ayurveda-phase1-local\private\runtime.json`, passes the password through a private stdin pipe to the CLI-only provisioner, and stores only `password_hash()`. Passwords/hashes are never printed or placed in command arguments, code or log files. Duplicate usernames fail without overwriting accounts. No default admin or password has been created.

The underlying CLI is `bin/create-admin.php username "Display Name" --stdin-password` with privately configured DB environment. Never pipe a literal password from shell history. The PowerShell helper is the supported local Windows procedure. No signup can grant administrator access through HTTP. Representative provisioning remains an owner-controlled administrative task; its user-management screen is a placeholder.

## Session and login policy

- CSRF required for login/logout; POST-only logout. Tokens rotate on login.
- Prepared statements, `password_verify()`, and password rehashing when needed.
- Strict cookie-only PHP sessions, HttpOnly, SameSite=Lax; Secure on HTTPS. This HTTP loopback installation intentionally cannot use Secure cookies. Any future network deployment requires HTTPS.
- Session ID regeneration on login and every 15 minutes on protected requests. Idle expiry 30 minutes; absolute expiry 8 hours. Online sessions do not grant seven-day offline access.
- Every protected request reloads active state and role from the database. Deactivation/password changes revoke existing sessions on the next protected request.
- Atomic 15-minute rate limits: 5 attempts per normalized username/client-IP pair and 30 per client IP, including successful attempts. Unknown/inactive identities have generic failure messages. Only `REMOTE_ADDR` is trusted; forwarded-IP headers cannot bypass the limiter. Throttled requests receive HTTP 429/Retry-After. Buckets store hashes, timestamps and counts, not credentials.
- Authentication pages/APIs use `no-store`; no authenticated caching/service worker is added. Identity payloads contain an explicit allowlist, never password hashes or cost prices.

## Local server and credentials

Apache still uses `127.0.0.1:8088` and serves only `public/`. The application database is `dharma_sales_phase1` on `127.0.0.1:3309`, data directory `C:\mariadb-clean\data`. Existing runtime credentials are unchanged and DML-only. Administrator/migration credentials stay outside the served directory. No global XAMPP changes, port 3306 changes, WordPress access or recovery work is needed.

After reboot:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File 'C:\xampp\htdocs\dharma chemical sales app\storage\phase1-local\Start-Phase1.ps1'
```

Do not start damaged XAMPP MySQL through its control panel. Earlier Phase 1 configuration/provisioning instructions are historical.

## Migrations and tests

Migrations 001/002 are unchanged. Additive migration 003 creates only `auth_login_limits`. Its deployment was preceded by checking the exact app target and existing applied checksums, and taking an application-only backup including triggers and journal. The backup remains in the restricted private directory; see [verification results](phase2-verification.md).

```powershell
Set-Location 'C:\xampp\htdocs\dharma chemical sales app'
& C:\xampp\php\php.exe storage/phase1-local/command.php foundation-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php database-regression
& C:\xampp\php\php.exe storage/phase1-local/command.php authentication-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php verify-runtime
```

The wrapper loads existing private credentials without displaying them. Direct commands with privately configured DB environment are `php tests/run.php`, `php tests/database.php --existing-app` and `php tests/authentication.php --existing-app`.

The owner specifically authorized migration/tests only on the existing application database. `--existing-app` is an explicit CLI opt-in limited to `dharma_sales_phase1`; the existing database test's normal `_test` safety gate remains unchanged for other uses. Constraint fixtures are rolled back. HTTP tests use source address `127.0.0.2`, create uniquely named temporary users and remove only their own fixtures/cookie jars/buckets. They preserve any pre-existing source-IP bucket and serialize suite runs with an advisory lock. Ordinary browser traffic on `127.0.0.1` is separate. Synthetic aggregate-limit tests also clean their own buckets.

No reset/down operation exists. Never point tests/migrations at WordPress or recovery schemas. MariaDB DDL is not transactional: preserve and investigate an applying migration journal row rather than blindly retrying it or editing a deployed migration.
