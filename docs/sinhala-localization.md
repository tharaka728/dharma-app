# Sinhala localization completion — 15 November 2026

The application defaults to Sinhala (`si-LK`) with a persistent English option. Display translation is separate from business identifiers, API data, signed drafts and financial calculations.

## Coverage

Sinhala labels and messages cover administrator and representative dashboards, navigation, products, customers, district selectors, inventory, allocations/returns, promotions, supermarket POS, invoices/payments, reports/CSV, encrypted offline PWA, A4 invoices and thermal receipts. Product search accepts English names, Sinhala names and existing codes. Dynamic validation, reward choices, discounts and payment labels use the shared catalogue. The catalogue contains 1,616 entries; the current static inventory covers 1,406 keys with no missing catalogue entries. This is an automated coverage inventory, not a guarantee against every possible future runtime message.

Use http://127.0.0.1:8088/ for the application. The administrator company-language settings page is http://127.0.0.1:8088/admin/settings/language . The language selector is available in the workspace and offline shell.

## Business wording and invoice preservation

Existing official company name, address, contacts and receipt footer remain the fallback. Editable Sinhala name, address and footer require explicit owner approval before use. Saving a draft does not approve it. A final read-only live check found an unapproved company-name draft, 13 products containing Sinhala names, and promotions 60, 61 and 62 active. This localization completion did not change those promotion states or supply official wording.

Verified owner-entered product Sinhala names display when available; English remains the fallback. Arbitrary customer names, towns, addresses, business descriptions and historical names are not automatically translated. Technical identities, codes, filenames and numeric amounts stay stable.

New invoices snapshot product-language and approved company details immutably. Historical invoices are not backfilled and retain English document defaults. Document language can be selected explicitly. Offline finalization takes the product-language name from the approved grant, preserving the signed draft and avoiding silent display-name changes after a later master edit. Existing stock, free-unit, cash/credit, discount, numbering and synchronization semantics are retained.

## Database safety

Checksum migrations 018 and 019 add optional product Sinhala names, invoice-language/company snapshots and editable approved company-language settings. The verified backup is held privately outside the served tree:

`C:/mariadb-clean/ayurveda-phase1-local/private/localization-20261115153904/application.sql`

SHA-256: `19a5cd318ee3aa1ee3757ddc6b4b2a3e4e97b68d86c650951a805562bc1bcc1e`.

Migration preservation checks verified existing rows across 55 pre-existing tables using their original columns. Synthetic transactions and browser fixtures were confined to `dharma_sales_phase9_test`. No global XAMPP or network configuration changes were made.

## Verification and samples

- 97 dedicated localization checks passed: Unicode storage/search, display isolation, exact financial invariance, company approval, immutable invoice names, offline-grant names, signed-body preservation, local PDF font embedding and rollback preservation.
- Full Sinhala browser matrix: 509 checks passed across 320, 360, 375, 390, 414, 768, 1024 and 1440 pixel widths. Includes administrator/representative pages, language persistence, POS totals, hidden identifiers, invoice layouts, encrypted offline queue and synchronization.
- Foundation 12; database constraints 16; products 69; customers 91; locations 91; inventory 124; product-stock 59; representatives 351; promotions 121; invoices 185; offline 110; reporting foundations 48; report calculations 81; billing enhancements 100 checks passed.
- Document suite: 31 checks and 33 real-browser authorization/layout/printing/download/cache checks passed.
- JavaScript domain/UI suites: 19 tests passed. PHP lint: 136 files passed.
- Installed Nirmala font inspection confirms Sinhala substitution and positioning tables and permitted editable embedding flags. PDFs contain embedded font data and Unicode mappings. Font files were not copied or redistributed.

Samples generated from disposable test data: `storage/sinhala-pos-mobile.png`, `storage/sinhala-pos-desktop.png`, `storage/sinhala-a4-sample.png`, `storage/sinhala-receipt-sample.png`, `storage/sinhala-a4-sample.pdf`, and `storage/sinhala-thermal-sample.html`.

## Remaining limits and operation

No official Sinhala business wording has been approved by this work. Untranslated business content intentionally retains its stored text; missing Sinhala product names use English. Browser-native share dialogs, operating-system dialogs and unexpected external/browser errors may follow the device language. A native Sinhala speaker should review terminology before operational sign-off.

Actual Android/Redmi hardware, its installed fonts and a physical thermal printer remain untested. Browser viewport emulation and local Edge PDF generation do not replace that hardware UAT. Existing deployment-readiness restrictions remain in effect; this work does not publish the application or provide a phone-accessible URL.

Static assets now use Sinhala cache revision v2. Close old application tabs and reopen to allow the normal service-worker lifecycle to complete. Never clear unresolved offline data; retain encrypted recovery exports. No forced service-worker activation, signed-draft mutation or vault deletion was added.

Final authentication regression: 305 authentication, authorization, session and route checks passed using the explicit English option. Default Sinhala is covered separately by the localization browser matrix.

Final v2 asset/browser rerun: 57 focused Sinhala/English route, role, language persistence, eight-width layout, Unicode POS, print, encrypted offline and synchronization checks passed. Login HTTP health returned 200.
