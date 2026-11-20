# Running Phase 1 locally

Open **http://127.0.0.1:8088/**. The existing `public/index.php` renders the foundation status page with **Database foundation ready**. Its status button opens **http://127.0.0.1:8088/api/v1/health**, returning `{"status":"ready","database":"ready"}`.

This is the complete current interface. Login, dashboard, products/customers editing, stock posting, billing, offline selling, synchronization and printing are later work. No admin was created because no login interface exists.

The app runs using the installed XAMPP Apache/PHP binaries in a separate Apache process bound to `127.0.0.1:8088`. Only the project's `public/` is served. No global XAMPP configuration or hosts-file edit is needed. The original Apache is left running. `public/.htaccess` now requires local access, including when reached through the original Apache's htdocs path.

The clean MariaDB uses its existing `C:\mariadb-clean\server.ini`, `C:\mariadb-clean\data` and `127.0.0.1:3309`. This setup did not start or change the database on port 3306, use its files, or modify WordPress or recovery backups.

New schemas:

- `dharma_sales_phase1`: application schema, 31 foundation tables plus migration journal; 26 immutable-history triggers.
- `dharma_sales_phase1_local_test`: separate schema used for the existing database tests; fixtures rolled back.

Both schemas were confirmed absent before creation and empty before their first migrations. The original `001_foundation.sql` and `002_immutability.sql` were reviewed and applied using the existing migrator. The repeat run reported Already applied; applied checksums match unchanged migration files. No reset/down operation was used.

Runtime account `dharma_phase1_app` has only SELECT/INSERT/UPDATE/DELETE on the app schema. Separate `dharma_phase1_migrator` and `dharma_phase1_tester` accounts have schema-scoped DML and the DDL privileges needed for initial migration/testing, without global or grant privileges. The runtime has no DDL/TRIGGER/DROP privileges. Generated credentials and the separate Apache configuration are in the ACL-restricted `C:\mariadb-clean\ayurveda-phase1-local\private` directory, outside htdocs. Do not publish or share this directory. The app's Apache configuration contains only runtime credentials; administrator credentials remain in the existing MariaDB private option file.

After a reboot, run in PowerShell under the owner account:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File 'C:\xampp\htdocs\dharma chemical sales app\storage\phase1-local\Start-Phase1.ps1'
```

This starts the existing clean MariaDB if needed, checks runtime connectivity, starts the separate Apache if needed, checks health, and opens the browser. It never creates databases, runs migrations or resets existing data. Do not start damaged XAMPP MySQL through its control panel.

Validation completed:

- All 18 application and local setup PHP files passed lint.
- Existing `tests/run.php`: 12 checks passed.
- Existing `tests/database.php`: 16 constraint checks passed; fixtures rolled back; migration repeatability, UTC and native prepares passed.
- Runtime PDO connection and DML-only grants verified against port 3309.
- Separate Apache syntax valid; listener confirmed only `127.0.0.1:8088`.
- Page, health endpoint and local Bootstrap CSS: HTTP 200.
- Missing route and private paths (`.env`, configuration, storage, migrations): HTTP 404 on the dedicated app server.
- POST to the GET-only health route: HTTP 405.

Private credentials are intentionally absent from the commands below. To rerun the non-destructive foundation suite using the stored runtime configuration:

```powershell
Set-Location 'C:\xampp\htdocs\dharma chemical sales app'
& C:\xampp\php\php.exe storage/phase1-local/command.php foundation-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php verify-runtime
```

Do not run initial provisioning or `migrate-initial` again: their targets now exist. Database tests were run against a newly created empty test schema for this setup. Future fresh verification should use a new dedicated test schema, never the application or WordPress database. MySQL/MariaDB DDL is not transactional; any partial migration must be investigated rather than retried blindly.
