# Phase 4: Customer Management

The address form now uses normalized district/town lookups. See [the location enhancement report](customer-location-lookups.md) for selection, permanent new-town entry, legacy address review, migrations 007/008 and current test results.

Completed for the existing local Ayurveda application on 2026-11-09. This phase manages customer master data only. Products, authentication, dashboards and public health remain operational.

## Screens

- Sign in: **http://127.0.0.1:8088/login**
- Customer list: **http://127.0.0.1:8088/admin/customers**
- Add customer: **http://127.0.0.1:8088/admin/customers/new**
- Detail: `/admin/customers/{id}`
- Edit: `/admin/customers/{id}/edit`

The admin dashboard now links Customers in navigation and its module card. List filters cover code/shop/contact/phone search, customer type, status, city/town and district, with 20-item pagination. Forms use responsive Bootstrap columns, the list scrolls horizontally on narrow screens, and detail text wraps. All assets remain locally hosted. No example customers are seeded.

Required fields: unique customer code, business/shop name, contact person, primary phone, address, city/town, district, controlled customer type and credit terms in days. Alternate phone, notes and LKR credit limit are optional. Customer types: Pharmacy, Ayurveda shop, General retail shop, Distributor, Other. Text is server-validated and HTML-escaped. Names/locations support Unicode. Code syntax is 1–64 ASCII letters/numbers/dots/slashes/underscores/hyphens, beginning with a letter or number; codes are trimmed and uppercased.

Credit terms accept 0–3650 whole days. Credit limits accept nonnegative decimal strings with at most 13 whole digits and 2 decimal places. No binary floating-point arithmetic is used. Whole/one-decimal values are padded using string operations. Blank limits mean **not configured**, distinct from an explicit **0.00**; no unlimited-credit rule is implied. These are settings only: there are no balances, due invoices, credit decisions, collections or partial ledgers.

## Duplicate phone procedure

Customer codes are unique identities. Phones are contact information and are deliberately not unique: shops may share a switchboard or owner number.

1. Enter the customer's actual primary/alternate phone. Formatting may include spaces, dots, parentheses and hyphens. Normalization removes those characters, accepts an international `+` or `00` prefix, and maps 10-digit Sri Lankan numbers beginning `0` to country code `94`. It validates 7–15 international digits, not phone ownership/reachability; no calls or messages are sent. Extensions should not be entered in the phone fields.
2. Save. Matching primary/alternate numbers on any other customer, including inactive customers, produce a 409 review response. The same number cannot be repeated as both phones on one customer.
3. Review linked matching customer records in the warning. If it is the same business, cancel creation and edit that existing record instead. Never merge, overwrite, delete or reassign financial history based solely on phone matches.
4. If separate customers legitimately share the number, check the explicit shared-contact confirmation and enter a 10–500-character reason, then save again. The actor, matching customer IDs and reason are stored in the immutable audit record. Confirmation is required on later edits while a shared contact remains; it is not silently carried forward.

Normalized phone hashes are locked in deterministic order inside the same transaction as the duplicate check/save/audit. Current locking reads recheck matching rows after waiting, so simultaneous requests cannot both silently create the same phone without review. Legacy rows with missing normalized fields are compared using their original phones; invalid legacy formats are left untouched for owner review. Formatting-equivalent valid numbers still match legacy rows. No automatic merges or phone-number uniqueness constraint is introduced.

## Historical safety and permissions

All customer routes/APIs are admin-only, with fresh database-backed authorization in the router and service. Representatives have no new customer read/edit access yet; their Customers navigation remains planned. Requests cannot set `assigned_rep_id` or legacy `area`; existing assignments and areas are preserved on edit. New records have no representative assignment; their required legacy `area` compatibility value is initialized from city/town. Full allocation is not implemented.

Customer edits/status changes require a last-seen version and reject stale edits with 409. Deactivation/reactivation uses explicit target state and preserves IDs, code ownership and relationships. Codes remain reserved after editing or deactivation, backed by immutable code history and restrictive FKs. There is no DELETE route. Successful forms use POST/redirect/GET. Creation, edits, status and shared-phone acknowledgments are atomically audited. No existing invoice snapshots or financial records are rewritten.

State changes require CSRF; cookie session/authentication/no-store policies from Phase 2 remain. Validation returns 422; duplicate-phone review and stale/concurrent write conflicts return 409; permission/CSRF failures 403; missing records 404; unsupported methods 405. Anonymous APIs return 401; anonymous pages redirect to login. Database deadlocks/timeouts receive bounded retries, then a retryable conflict instead of partial writes.

## Admin APIs

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/v1/admin/customers` | Filtered/paginated list |
| POST | `/api/v1/admin/customers` | Create customer |
| GET | `/api/v1/admin/customers/{id}` | Full master-data detail |
| POST | `/api/v1/admin/customers/{id}` | Full edit with `version` |
| POST | `/api/v1/admin/customers/{id}/status` | `version` and explicit `is_active` string `"0"`/`"1"` |

POST supports form encoding or JSON objects. Editable fields are `customer_code`, `shop_name`, `contact_person`, `phone`, `alternate_phone`, `address`, `city_town`, `district`, `customer_type`, `notes`, `credit_terms_days`, `credit_limit`. Use strings for money, terms, versions and target status; optional limit may be JSON null. JSON numeric money is rejected. Supply `csrf_token` or `X-CSRF-Token`. A duplicate-review response includes admin-only `duplicates` IDs/codes/shop names. Intentional sharing requires `duplicate_phone_confirmed="1"` and `duplicate_phone_reason`.

Detail returns a decimal-string or null `credit_limit` and LKR currency metadata, not any financial balance. For backward-compatible database storage, existing `credit_limit DECIMAL(15,2) NOT NULL` and its nonnegative CHECK are preserved. New `credit_limit_enabled` distinguishes unconfigured (`0`, stored numeric value `0.00`, API null) from configured (`1`, including explicit zero). Future financial code must respect this flag; no financial code is added now. Existing numeric limits remain enabled and unchanged.

## Schema and backup

The exact existing target `dharma_sales_phase1` at `127.0.0.1:3309` and migrations 001–005 were checked before changes. A new private app-only backup was created, including triggers and migration journal:

`C:\mariadb-clean\ayurveda-phase1-local\private\phase4-backup\before-phase4.sql`

SHA256: `67F127321B7C86F786E1EEC6843BF0FC29A647EF4264D8413F82CDB2714A46AC`.

Migration `006_customer_management.sql` only adds customer fields/indexes, retained code history and a phone-lock table. Existing customers receive deterministic `LEGACY-{id}` codes for identity only; no customer/contact/location/financial records are invented. Original contact data, representative assignments, area, credit values, IDs and historical relationships are preserved. Existing credit constraints are not modified. New credit-term bounds and code uniqueness are enforced by the database. Code history is backfilled for existing rows and protected by update/delete-denying triggers.

All six migrations applied and checksums verified; repeat application reports Already applied. The schema has 36 tables and 30 triggers. Original migration files and existing private credentials are unchanged. This new backup does not replace or modify prior backups. No WordPress database/files, other schemas, recovery backups, global XAMPP configuration or port 3306 settings were touched. Apache and MariaDB remain bound to localhost. No restore rehearsal against another database was performed because that is outside scope.

## Verification

- PHP lint: 33 application/test/local setup PHP files passed.
- Customer suite: 91 checks passed for required fields/lengths/types, phones, code uniqueness/history, permissions, optional/zero/maximum credit-limit precision, terms bounds, preserved assignment, stale edit/status conflicts, deactivation/reactivation, duplicate primary/alternate/legacy/inactive phone handling, audited confirmation reasons, searches/filters, server constraints, rendered screens and successful create/edit/status controller paths.
- Independent second PDO connection could not bypass a held phone reservation: its session-scoped one-second lock wait timed out as expected. No global database configuration was changed.
- Products: 69 checks passed.
- HTTP authentication/route suite: 122 checks passed, covering prior sessions/Products plus customer list/add/API delivery, dashboard links, anonymous/representative denial, CSRF, required-field errors, invalid filters, missing IDs, malformed JSON and rejection of JSON numeric credit limits.
- Foundation: 12 checks passed.
- Database regression: 16 constraint checks passed; fixtures rolled back, UTC/native prepares/migration repeatability passed.

Customer/code/phone-lock/audit/user test data runs inside a transaction and is rolled back. Before/after row counts verify no fictional customer or audit records remain. Successful write controller paths are tested inside the same rollback-only transaction, while HTTP tests exercise delivery and rejected requests with temporary user fixtures. No sample financial data or balances are created. The explicit app-only test opt-in is retained because the owner forbids using other databases.

Run from the project directory under the owner account, using existing private credential loading:

```powershell
& C:\xampp\php\php.exe storage/phase1-local/command.php customer-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php product-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php authentication-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php foundation-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php database-regression
& C:\xampp\php\php.exe storage/phase1-local/command.php verify
& C:\xampp\php\php.exe storage/phase1-local/command.php verify-runtime
```

No default owner account is added; use the Phase 2 hidden-password helper if the first real admin has not yet been created. Successful writes were verified at the real-database/service/controller layers; no interactive visual/mobile-device review was performed. Old hash-lock rows are small persistent synchronization metadata; do not delete them while customer writes are running. Customer/contact values must be entered by the owner.

**Stop boundary:** Phase 4 is complete. No Phase 5 inventory/batch management, invoice creation, collections, ledger balances or offline synchronization is enabled.
