# Phase 1 verification — 2026-11-08

Completed foundation only. The original XAMPP database was not running. No normal database, Apache configuration, hosts file or real user credentials were changed. No Phase 2 workflow was implemented.

## Actual commands and results

From `C:\xampp\htdocs\dharma chemical sales app` in PowerShell:

```powershell
& C:\xampp\php\php.exe -v
& C:\xampp\mysql\bin\mysql.exe --version
& C:\xampp\mysql\bin\mysql.exe -u root -e 'SELECT VERSION() AS version, @@sql_mode AS sql_mode;'
```

PHP 8.0.30; installed MariaDB 10.4.32 client. Default server query failed with connection refused (server stopped). An isolated temporary database was then initialized inside ignored storage, without touching the XAMPP data directory:

```powershell
& C:\xampp\mysql\bin\mysql_install_db.exe --datadir='C:\xampp\htdocs\dharma chemical sales app\storage\verification-db' --port=3307
& C:\xampp\mysql\bin\mysqld.exe --no-defaults '--datadir=C:\xampp\htdocs\dharma chemical sales app\storage\verification-db' --port=3307 --bind-address=127.0.0.1 --console
```

In a separate shell:

```powershell
& C:\xampp\mysql\bin\mysql.exe --host=127.0.0.1 --port=3307 -u root -e 'CREATE DATABASE dharma_foundation_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; SELECT VERSION();'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3307'
$env:DB_USER='root'
$env:DB_NAME='dharma_foundation_test'
& C:\xampp\php\php.exe tests/database.php
& C:\xampp\php\php.exe tests/run.php
Get-ChildItem app,bin,config,public,tests -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

Live server reported **10.4.32-MariaDB**. Both migrations applied successfully on the fresh schema. Repeated runs reported Already applied. Final database suite: **16 constraint checks passed**, plus migration repeatability, UTC connection and native prepared statements. All fixtures rolled back. Tests cover product/batch uniqueness, nonnegative stock, batch/product matching, batch date ordering, immutable batch revisions and transactions, restrictive product deletion, location shape, UUID format, primary-device ownership, positive sequence counters, range shape/uniqueness/immutability, and maximum seven-day policy grants. Foundation suite: **12 checks passed** for role/scope denial, escaping, password hash/verify, UTC, UUID/money validation and CSRF. **All 16 PHP files passed lint**, with the final changed DB test re-linted separately.

HTTP verification used a temporary PHP development server with the isolated DB environment above:

```powershell
& C:\xampp\php\php.exe -S 127.0.0.1:8087 -t public public/index.php
```

PowerShell `Invoke-WebRequest -UseBasicParsing` checked the following methods/paths and asserted the exact status:

| Request | Result |
|---|---|
| GET / | 200 |
| GET /api/v1/health | 200 |
| GET /missing | 404 |
| POST /api/v1/health | 405 |
| GET /.env | 404 |
| GET /api/v1/health after DB shutdown | 503 |

The development-server command forces all requests through the front controller; use Apache's supplied configuration for static assets. This was a route/status check, not a visual browser or Apache isolation test.

Shutdown:

```powershell
& C:\xampp\mysql\bin\mysqladmin.exe --host=127.0.0.1 --port=3307 -u root shutdown
& C:\xampp\apache\bin\httpd.exe -t
```

Database shutdown completed normally; temporary PHP server was stopped with Ctrl+C. Existing Apache configuration syntax reported **Syntax OK**. The supplied vhost has not been installed or exercised; follow README and verify access restrictions after configuration. Bootstrap 5.3.3 CSS and its MIT license were downloaded and stored locally. No external assets are requested by the health page.

## Files created and modified

Modified: `README.md`, `docs/v1-design.md` (original architectural baseline preserved and amended).

Created:

```text
.env.example
.gitignore
.htaccess
AGENTS.md
app/bootstrap.php
app/Controllers/HealthController.php
app/Middleware/Authorization.php
app/Middleware/Csrf.php
app/Middleware/Session.php
app/Repositories/.gitkeep
app/Services/.gitkeep
app/Support/Database.php
app/Support/Http.php
app/Support/Migrator.php
app/Support/Validation.php
app/Views/health.php
bin/create-admin.php
bin/migrate.php
config/database.php
database/migrations/001_foundation.sql
database/migrations/002_immutability.sql
database/seeds/.gitkeep
database/seeds/README.md
docs/apache-vhost.conf.example
docs/phase1-verification.md
public/.htaccess
public/index.php
public/assets/css/.gitkeep
public/assets/vendor/bootstrap/bootstrap.min.css
public/assets/vendor/bootstrap/LICENSE
storage/.gitkeep
tests/database.php
tests/run.php
```

Ignored `storage/verification-db/` retains the stopped test instance for inspection. It contains disposable verification data only, not application credentials. No `.env` was generated and no admin was provisioned. The provisioning implementation passed lint; its end-to-end CLI behavior has not been tested. There was no Git repository in the initial workspace, so no commit was created.

Future transactional posting, authentication endpoints/throttling, offline policy issuance, seven-day expiry, invoice allocation concurrency, synchronization/reconciliation, print behavior, mobile rendering and MySQL 8 compatibility remain unimplemented or unverified as appropriate. Schema row constraints do not claim to enforce cross-row business rules. See design section 9 for pending owner decisions before Phase 2.
