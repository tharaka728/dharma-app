# Customer district and town enhancement — verification report

The customer list filters have subsequently been upgraded to ID-based searchable dropdowns. See [the current list-filter report](customer-list-filters.md); its list-filter behavior supersedes the original text-filter compatibility notes below.

Implemented 2026-11-09 in the existing Phase 4 module. No Phase 5 work is included.

## Use

- Customers: http://127.0.0.1:8088/admin/customers
- Add customer: http://127.0.0.1:8088/admin/customers/new
- Edit: `/admin/customers/{id}/edit`

Sign in as an admin. Search or select one of the 25 districts, then search/select a town. Town search is server-side, debounced, and paginated in groups of 50; Load more extends the options. Native selects support keyboard navigation and mobile selection. Pressing Enter in either search moves focus to its select. Changing district clears the old town immediately. Saving requires a valid district/town pair.

Use **+ Add New Town** for a real missing place, check its district, enter its name and save. The server commits it to MariaDB with an audit entry; it becomes available to all subsequent forms and sessions. No browser storage is involved. Leading/trailing whitespace is removed; names must be 1–100 characters without control characters. A database-generated lower/trim key and unique district/name index reject duplicates even under concurrent requests. Identical names in different districts are allowed. There is no public or sales-representative town-creation permission.

Initial data is deliberately a **30-town starter list, not a complete national town list**. District-centre names are supported by the [Department of Census and Statistics district office directory](https://www.statistics.gov.lk/Contact_Us/index). Monaragala is the town spelling under Moneragala district. Additional directory-backed towns are Maharagama (Colombo), Gampola (Kandy), and Beliatta, Tangalla and Ambalantota (Hambantota), supported by the [government RTI directory](https://rti.gov.lk/io-list/). Source URLs are stored with initial towns. User-created towns are not represented as government-verified geography.

## Schema, compatibility and safety

Migration 007 adds `location_districts`, `location_towns`, nullable customer `district_id`/`town_id`, district foreign keys and a composite town/district foreign key. District IDs are stable; town IDs are generated. Existing address strings are retained. Exact case-insensitive, trimmed matches can map to known lookups only within an identified district. Unknown or ambiguous legacy text remains intact and unselected, with an explicit review warning on edit; saving requires the admin to resolve the location. No town is inferred from its name alone. Customer creation/edit writes canonical location names alongside lookup IDs for existing list/detail compatibility. No customers are seeded.

The API accepts `district_id` and `town_id`. Legacy text-only clients can resolve an exact known district/town pair; arbitrary free text is rejected. List filters remain compatible with stored text. Admin-only GET endpoints: `/api/v1/admin/locations/districts` and `/api/v1/admin/locations/towns?district_id=5&q=&page=1`. POST to the latter accepts `district_id` and `town_name` with the existing CSRF token. Validation returns 422, duplicate names 409, missing/invalid CSRF 403; existing router authentication and role checks apply before handlers. New-town writes and audit entries are atomic. Existing customer optimistic concurrency/version checks remain in place.

## Migration incident and verified resolution

The application target was checked as `dharma_sales_phase1` on `127.0.0.1:3309`. A private SQL dump was created and SHA-256 verified before migration. Migration 007 completed its additive DDL and seeds but stopped at the legacy town mapping because old customer strings and new lookup strings use different collations. MySQL DDL was already committed. No DDL was blindly rerun and the deployed 007 file was not altered.

Migration 008 makes the mapping comparison explicitly Unicode/binary after lower/trim normalization. `bin/reconcile-location-mapping.php` verifies the exact known 007 checksum, prior migrations, lookup seed state, schema constraints and exact database target before applying only 008's mapping statements and recording both versions as applied in one DML transaction with an audit entry. The original 007 SHA-256 remains `161effa594c58f45223bf68161ba3a6f3b623dc34bafff0a33080008a337fa9c`. All eight migrations now have verified applied status. Normal migration repeats are successful no-ops.

For this local credential wrapper, inspection commands are:

```powershell
Set-Location 'C:\xampp\htdocs\dharma chemical sales app'
& C:\xampp\php\php.exe storage/phase1-local/command.php verify
& C:\xampp\php\php.exe storage/phase1-local/command.php locations-inspect
```

For a future replay that encounters that exact partial-007 state, first create/verify a new application-only backup, inspect the state, then explicitly use `locations-reconcile`; it refuses unknown states. Do not reset migrations, edit 007, or assume a new mixed-collation database can apply 007 without this repair step. No repair command is needed on the now-verified running application.

Private backup directory: `C:\mariadb-clean\ayurveda-phase1-local\private\customer-locations-backup` (outside served files).

- `before-locations.sql`: 91,080 bytes; SHA-256 `7372E72A9E2C1A477CC7C6F243A01339B8B3A2961203692C2FC09EF3535D4A70`.
- `before-mapping-reconciliation.sql`: additional partial-state backup; SHA-256 `19C3B89B56D038101AB7D74C64D4C3FF7C337FE8FBAAEAAB412A5E202A423B9D`.

## Validation

- 38 PHP files passed lint; JavaScript syntax passed.
- 71 location checks: all districts, district filtering, persistent SQL readback, duplicate constraints, cross-district names, legacy mapping, edit preselection, mismatched foreign keys, admin permissions, endpoint creation and pagination. Fixtures rolled back.
- 5 Node interaction checks: immediate district clearing/submission guard, stale-response rejection, CSRF-protected new-town selection, pagination/edit selection, keyboard/search behavior. Run `node --test tests/customer-locations-ui.js`.
- 91 customer, 69 product, 135 authentication/session/authorization/route, 12 foundation and 16 database constraint checks passed. Business fixtures rolled back or existing auth-suite users cleaned up.
- Eight applied migration checksums verified; migration repeats passed; 38 tables and 30 triggers. Runtime account remains DML-only with native PDO prepares and UTC session storage.
- Health endpoint reports ready. HTTP 8088 and MariaDB 3309 remain loopback-only. Existing credentials and global Apache configuration were not changed. No other database, WordPress files or recovery backups were modified.

The UI tests use a lightweight DOM harness, not a graphical browser. A final human mobile/browser check remains useful for native select appearance. Legacy unmapped addresses deliberately require review when edited. No geographic completeness or automatic address verification is claimed.
