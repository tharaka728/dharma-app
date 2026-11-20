# Customer list location filters

Updated the existing Phase 4 customer directory only. Open **http://127.0.0.1:8088/admin/customers** as an administrator.

The District control uses all 25 existing lookup rows plus **All districts**. Type in its search box, then select a district. City / Town uses the existing town lookup API and database with **All cities / towns**, server-side search and 50-result pages with Load more. Each town displays its district, so same-name towns remain distinguishable across districts. Admin-added towns are immediately eligible for search, including after refresh or a new login. Press Enter in a search box to focus its native select; native controls support keyboard and touch selection.

Changing district immediately removes incompatible towns and clears an incompatible selection. A compatible town remains selected; returning to All districts preserves it. Press the existing Filter button to apply selections. Selecting only a district filters the entire district; selecting only a town filters by its globally unique town ID. Selecting both enforces the district/town relationship. Blank IDs mean all locations. Other search, type and status filters are preserved. Refresh and pagination retain selected IDs, and Clear filters navigates to the query-free customer list. Empty results show a helpful message.

Backend list filtering now uses `district_id` and `town_id`, not the former `district`/`city_town` substring parameters. Invalid, unknown or mismatched IDs return validation errors (422); values are bound parameters. Existing admin checks remain enforced in services and HTTP routing. Unmapped legacy customers remain visible in unfiltered results but cannot match an ID-based location filter until their address is explicitly reviewed and mapped in the existing edit form. No inference or data migration occurs here.

The existing `/api/v1/admin/locations/towns` API accepts `scope=all` with an absent/blank district ID to search across districts. Its default behavior still requires a district, preserving registration/edit validation and existing clients. Results now include `district_name`; all results remain paginated and admin-only. Initial filter HTML includes the selected town even if it lies beyond the initial page. Stale network responses cannot overwrite a newer district/query. Failed requests are announced; submission waits for outstanding searches, and users can clear the town to filter by district alone after an error.

## Modified files

- `app/Services/Customers.php`: validated ID-based customer filtering.
- `app/Services/Locations.php`: shared filter selection/options and optional cross-district lookup search.
- `app/Controllers/CustomerController.php`: load list options through the shared service.
- `app/Views/customers.php`: dropdown partial, filter pagination and empty state.
- `app/Views/customer-location-filters.php` (new): accessible responsive filter controls.
- `public/assets/js/customer-location-filters.js` (new): dependent search, paging, selection retention and request ordering.
- `tests/customers.php`, `tests/locations.php`, `tests/authentication.php`: filter, persistence, permissions, rendering and HTTP regression checks.
- `tests/customer-location-filters-ui.js` (new): six dropdown interaction checks.
- `README.md`, `docs/customer-location-lookups.md`, this report: current usage and verification.

## Verification

All tests target only the existing Ayurveda application. Database business fixtures are wrapped in transactions and rolled back; no fictional customer records are retained. Pagination-link rendering is tested without creating extra customers.

- 91 location/filter checks, including district-only, town-only, combined/all-location queries, matching names across districts, new-town visibility, invalid combinations, refresh selections, clear link, empty results and pagination.
- 91 customer, 69 product, 139 authentication/authorization/session/HTTP, 12 foundation and 16 database constraint checks passed: **418 PHP/database checks** total.
- 11 JavaScript interaction checks passed (6 list filters and 5 existing registration controls).
- 39 PHP files passed lint; new JavaScript passed syntax checking.
- All eight existing migration checksums verified; no new migration or schema change. Runtime access remains DML-only with native PDO prepares and UTC sessions. Health remains ready.

No XAMPP/MariaDB configuration, ports, unrelated databases, backups, WordPress or future-phase functionality were changed. Automated UI checks use a DOM harness; native dropdown appearance still warrants a human desktop/mobile browser review.
