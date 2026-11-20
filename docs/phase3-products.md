# Phase 3: Product Management

## Open the module

Sign in as an administrator at **http://127.0.0.1:8088/login**, then choose Products in the admin dashboard.

- List/search/filter: **http://127.0.0.1:8088/admin/products**
- Add: **http://127.0.0.1:8088/admin/products/new**
- View: `/admin/products/{id}`
- Edit: `/admin/products/{id}/edit`

The list supports SKU/name search, category and active/inactive filters, and 20-item pagination. No products or categories are preloaded. On Add product, expand **Create a category** if needed; create the category before filling out the product form. This creates only the name you enter. Choose it in the product form, supply product code, name, unit label and selling price, then save. The detail page provides editing and an explicit deactivation/reactivation form. Inactive products remain visible with the inactive/all filter.

The responsive Bootstrap layout stacks form fields and filters on mobile and places the list inside a horizontally scrollable table. All assets are local. Other dashboard modules remain placeholders; the representative dashboard and its data access are unchanged.

## Validation and data preservation

- Required SKU: 1–100 ASCII letters/numbers/dots/slashes/hyphens/underscores, beginning with a letter or number. Codes are trimmed and stored uppercase. Existing case-insensitive unique constraints remain authoritative, including concurrent requests and inactive products.
- Name: required, up to 200 Unicode characters. Names are not unique because distinct SKUs can describe legitimate variants.
- Category: an existing active category is required for new assignments. An edit may retain its existing inactive category. The inline category creator accepts a real operator-entered name up to 100 characters and rejects duplicates.
- Unit of measure: required text label, up to 40 characters. Default label `unit` preserves compatibility with pre-existing rows. Labels do not enable fractional quantities or any stock operations.
- Description: optional plain text, up to 5,000 characters; output is escaped.
- LKR prices: nonnegative, up to 13 whole digits and 2 decimal places. `1`, `1.2`, `1.20` normalize to `1.00`, `1.20`, `1.20` using string operations. Exponents, separators, binary floats, negatives, extra decimal places and overflow are rejected. No money uses floating-point arithmetic. Database money remains DECIMAL(15,2); API prices are decimal strings.
- A blank cost is NULL/unknown; an explicit `0.00` is known zero. No profit or billing calculations are added.
- SKU history retains every used code against its original product. Editing/deactivation never frees a SKU for another product. Unique database keys prevent double-submit duplicate products; HTML success uses POST/redirect/GET.
- Edits and status changes require the last-seen version. Stale submissions return 409 instead of overwriting another edit. Activation/deactivation uses an explicit target state, not a blind toggle.
- Product/category mutations and their append-only audit records are atomic. No product deletion endpoint exists. SKU history is immutable and its FK also prevents destructive deletion of products with reserved codes. No historical invoice snapshots, batch revisions or stock balances are rewritten.

## Authorization and APIs

Every product/category page/API requires a freshly authenticated active ADMIN, checked by the router and service. Representatives receive 403 before product/cost payloads are built. No representative read-only product access was added. State changes require CSRF. Forms use hidden tokens; API requests may use `csrf_token` in the body or `X-CSRF-Token`. Authentication remains cookie based; there is no bearer-token/offline authentication feature.

Admin APIs:

| Method | Path | Behavior |
| --- | --- | --- |
| GET | `/api/v1/admin/products` | Paginated list; `q`, `category_id`, `status`, `page` filters |
| POST | `/api/v1/admin/products` | Create product |
| GET | `/api/v1/admin/products/{id}` | View product |
| POST | `/api/v1/admin/products/{id}` | Full edit with `version` |
| POST | `/api/v1/admin/products/{id}/status` | Explicit `is_active` value `"0"`/`"1"` and `version` |
| POST | `/api/v1/admin/products/categories` | Create category using `category_name` |

POST accepts form fields or a JSON object. Send all editable fields for creation/edit: `sku`, `name`, `category_id`, `unit_of_measure`, `selling_price`, `cost_price`, `description`. Prices, IDs, versions and target status are submitted as strings; optional cost may also be JSON null. JSON numeric money is rejected instead of converted through floating point. Validation returns 422/errors, stale versions 409, CSRF/role failures 403, missing products 404, and unsupported methods 405. Lists/details are admin-only, no-store and contain no inventory data.

## Migrations and backups

Only `dharma_sales_phase1` on MariaDB `127.0.0.1:3309` was used. Before DDL, existing migration state/checksums were verified and a new complete app-only dump was created in the restricted private directory:

`C:\mariadb-clean\ayurveda-phase1-local\private\phase3-backup\before-phase3.sql`

SHA256: `A1C84DC4A18FF1F37B33B048EC3471CC762E04ED6CE09A645AFEB47F9BB91A59`.

Migration 004 adds unit/description, widens cost to nullable and introduces/backfills immutable SKU history for existing real products. It does not seed business records. During expanded verification, a raw negative-cost test found that MariaDB removed the old column-level cost CHECK when MODIFY made it nullable. The application validator still rejected negatives and the test write rolled back. The applied migration was preserved. A second app-only dump was created before the forward correction:

`C:\mariadb-clean\ayurveda-phase1-local\private\phase3-backup\before-005.sql`

SHA256: `BFB795DFBB59C2473DA8A35E9EC7BD49A4AAC025C285443E634F8C348D6ADD60`.

Migration 005 restores the database-level nullable/nonnegative cost check with a named constraint. Both nullable cost and rejection of negative cost/selling prices are now tested against MariaDB. All five migrations are applied, match their source checksums, and repeat without new DDL. Original migrations were not edited. There are 34 tables and 28 triggers; the 26 previous immutable-history triggers remain.

These are new private app backups. No WordPress data, prior recovery backups, other databases, global XAMPP settings, credentials or port 3306 configuration were changed. Apache/database remain localhost-only. No restore rehearsal was performed against another database, which was outside the authorized scope.

## Verification results

- PHP lint: all 28 application/test/local setup PHP files passed.
- Product suite: 69 checks passed for validation, permissions, creation/editing, duplicate/reserved SKUs, price precision/NULL vs zero, database price constraints, detail/edit rendering, category selection/version fields, successful API controller create/edit/status paths, search/filters, stale edits, activation/deactivation and append-only audit behavior.
- HTTP authentication/route suite: 98 checks passed, including admin list/add/API access, dashboard Products link, anonymous/rep denial, CSRF rejection, validation responses, missing products, numeric JSON money and malformed/non-object JSON rejection, plus prior session/authentication coverage.
- Foundation suite: 12 checks passed.
- Existing database regression: 16 constraint checks passed; fixtures rolled back, native PDO/UTC and migration repeatability passed.
- Runtime DML-only grants, health readiness and localhost listeners verified. App-only backup checksums preserved.

Product/category/SKU-history/audit test fixtures all run in a transaction and roll back; row counts are checked afterwards. Successful write controller paths run inside that same transaction so no fictional catalogue/audit data needs to be committed or removed from immutable history. HTTP tests exercise permissions and rejected writes with temporary users that are cleaned up. No actual Dharma Chemical product records were invented.

Commands under the owner account, from the project directory:

```powershell
& C:\xampp\php\php.exe storage/phase1-local/command.php product-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php authentication-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php foundation-tests
& C:\xampp\php\php.exe storage/phase1-local/command.php database-regression
& C:\xampp\php\php.exe storage/phase1-local/command.php verify
& C:\xampp\php\php.exe storage/phase1-local/command.php verify-runtime
```

The explicit existing-app regression opt-in is retained because the owner forbids touching other databases; no reset or other database was used. Successful writes were tested at the service/controller/real-database layers; authenticated HTTP tests cover page delivery and security. No interactive visual/mobile-device review was performed. Product/category data must be entered by the owner; initial admin setup remains documented in Phase 2 if no real admin exists yet.

Phase 3 stops here. No inventory posting, batch allocation, invoices, reports or offline synchronization was implemented.
