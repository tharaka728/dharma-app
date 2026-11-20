# Phase 10 — Invoice printing and local PDF

Open **http://127.0.0.1:8088/admin/invoices** (admin) or **http://127.0.0.1:8088/rep/invoices** (representative). History rows and invoice details now offer Print Receipt, Print A4, Download PDF and Share Invoice. Share Invoice opens the A4 document and exposes file sharing only when supported.

## Document URLs

Replace `INVOICE_UUID` with the existing invoice ID from its details URL; do not use the printed invoice number in its place.

| Document | Admin URL |
| --- | --- |
| 80 mm receipt | `http://127.0.0.1:8088/admin/invoices/INVOICE_UUID/receipt` |
| A4 browser print | `http://127.0.0.1:8088/admin/invoices/INVOICE_UUID/a4` |
| A4 PDF download | `http://127.0.0.1:8088/admin/invoices/INVOICE_UUID/pdf` |

Representatives use the same suffixes under `/rep/invoices/INVOICE_UUID`. Every request requires an active authenticated account and the existing invoice access policy: representatives require both their invoice ownership and current customer assignment. Admin routes require ADMIN. All three endpoints accept GET only. There are no public invoice links, credentials in URLs or arbitrary file-path parameters.

## Desktop and Android

1. Sign in, open invoice history, and choose the existing invoice's document action.
2. On the document page click **Print Receipt** or **Print A4**. Select 80 mm paper for receipts or A4 for office invoices. Use actual size/100% where supported, disable browser headers/footers, and preview before printing. Thermal CSS uses a 72 mm content area on an 80 mm page; a finite 297 mm page length provides predictable pagination. The printer driver controls roll cutting and actual printable margins.
3. Alternatively click **Download PDF**, or use A4 browser printing and **Save as PDF**.

On Android, use the browser/installed PWA's print dialog and an installed compatible Android print service. This Windows localhost address is reachable only on this computer. No phone-accessible HTTPS deployment or network binding was added. Actual Android, paper output, printer margins/cutting, and Bluetooth hardware have **not** been verified. A generic PWA cannot universally send raw ESC/POS commands to Bluetooth printers. A future device-specific print-service/native integration would need separate authorization, printer model testing and security review; no bridge was added.

Where secure-context file sharing is supported, **Prepare PDF to Share** downloads a private PDF into memory; tap **Share PDF now** as a separate user gesture to open the native chooser. Unsupported browsers retain Download PDF. The app does not automatically upload invoices. Shared/downloaded files contain customer data and are outside the encrypted device vault; choose recipients and storage carefully. No session tokens or application URLs are included in the share payload.

## Data and security

Documents use finalized invoice/item/customer/name/SKU/unit-price/free-quantity/discount snapshots. They never query current catalogue prices for calculations. Stored decimal strings are formatted without floating-point arithmetic. Current payments, outstanding amounts, void status and refund liabilities are read in one consistent **read-only** database transaction. Received/applied/change receipt history distinguishes cash handed over from revenue. VOID documents retain original amounts and explicitly identify Refund Due without claiming a physical refund.

No document route calls posting, payments, stock adjustment or invoice review. Private no-store headers apply; the service worker retains its exact nine-file public static allowlist and never caches invoices, APIs or PDFs. Browser back/forward restoration reauthorizes document pages. Company name is Dharma Chemical. Optional contacts appear only when `INVOICE_COMPANY_ADDRESS`, `INVOICE_COMPANY_PHONE` or `INVOICE_COMPANY_EMAIL` is explicitly configured in the existing private application environment; none was invented or configured.

### Local PDF mechanism

The existing local Microsoft Edge headless engine uses its [DevTools printToPDF mechanism](https://chromedevtools.github.io/devtools-protocol/tot/Page/#method-printToPDF), with the same A4 HTML/CSS template. No external PDF service or added PHP library is involved. Installed dependencies are `C:/nvm4w/nodejs/node.exe` (Node 24, native WebSocket) and `C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe`; the local Apache account must be able to run them and create private temporary files. Deployment to another machine requires checking these paths/capabilities. The PHP environment passes only necessary Windows paths to the child, excluding database credentials.

Escaped self-contained HTML travels over anonymous temporary streams and local loopback CDP. Renderer JavaScript is disabled; document HTTP/HTTPS/file/WebSocket resources are blocked, with a restrictive content policy. It receives no browser authentication cookies. The renderer uses a unique temporary browser profile and removes it after closing; PHP closes temporary streams in finally blocks. PDFs are returned directly, not written under public/. One renderer runs at a time, with bounded input/output and timeout; busy/unavailable rendering returns 503 and the user can retry or use browser Save as PDF.

Local Windows Nirmala UI supplies Sinhala/Tamil shaping. Tests verified Unicode font mapping and embedded Nirmala font data in actual PDFs, including Sinhala and Tamil sample text. This is not linguistic proof for every glyph/font/printer. Other machines without suitable fonts need font verification before claiming equivalent language coverage. Optional company contacts and customer fields are escaped as text; they cannot inject HTML resources.

### Offline limitation

**Final-invoice printing is disabled offline.** The Phase 9 encrypted vault lacks a complete verified final document/current financial-state bundle, so no private document caching or offline official receipt was introduced. Pending previews and unsynchronized cards visibly state **PROVISIONAL — NOT A FINAL INVOICE**; provisional cash is not server-confirmed payment and no offline official numbers are invented. Existing encrypted storage/passphrase/15-minute lock behavior remains intact. Ordinary browser controls may print an already displayed page; that does not turn a pending preview into an official invoice. Close older offline tabs to let the updated waiting service worker activate; unresolved vault records are preserved.

## Implementation files

- `app/Services/InvoiceDocuments.php`: consistent authorized snapshots, decimal formatting, safe filenames and rendering.
- `app/Controllers/InvoiceDocumentController.php`, `public/index.php`: protected read-only document endpoints.
- `app/Views/invoice-document.php`, `invoice-document-actions.php`, `invoices.php`: dedicated layouts and existing-history/detail integration.
- `public/assets/css/invoice-document.css`, `invoice-receipt.css`: responsive A4/80 mm print rules, repeating table headers, wrapping and pagination.
- `app/Support/LocalPdf.php`, `bin/render-invoice-pdf.js`: private local generation, limits and cleanup.
- `public/assets/js/invoice-documents.js`: user-initiated print/file sharing and download fallback.
- `public/offline/index.html`, `public/assets/js/offline-app.js`, `public/sw.js`: provisional labels and public-static-only cache version update.
- `tests/invoice-documents.php`, `invoice-documents-browser.js`, `invoice-documents-ui.js`: isolated persistence/permission/PDF/browser/sharing checks. Existing `storage/phase1-local/phase9-test.php` adds the documents command.
- `README.md`, `AGENTS.md`, `docs/v1-design.md`, this report: Phase 10 usage and authorization boundary.

## Database and verification

**No migrations or production data writes were required.** Existing migrations 001–014 retain verified deployed checksums; 53 tables and 49 triggers remain. No new backup or replacement of an existing verified backup was needed because there was no schema/data migration. All synthetic invoices, products and users used by tests exist only in the existing dedicated `dharma_sales_phase9_test` database. No real invoices were created for printing tests.

Run with the private test configuration already prepared:

```powershell
C:\xampp\php\php.exe storage/phase1-local/phase9-test.php documents
node --test tests/invoice-documents-ui.js
```

Verification results are recorded below after the final run. Live app integrity checks verified invoice totals, immutable payment allocation, paid/free stock, void reversals/refund liabilities, lifetime counters and warehouse/representative ledger reconciliation. Offline integrity checks passed. Listener checks confirmed Apache 8088 and MariaDB 3309 remain bound to 127.0.0.1. WordPress, recovery backups, other databases and global XAMPP configuration were not changed. Phase 11 is outside scope.

### Verified 2026-11-09

| Checks | Result |
| --- | --- |
| New document service/PDF/integrity suite | 31 passed, including its browser-runner assertion |
| Real Edge document browser checks | 33 passed |
| JavaScript unit regressions, including five new print/share tests | 28 passed |
| PHP lint | 96 application/test PHP files plus both local CLI wrappers passed |
| JavaScript syntax | 21 files passed |
| Foundation / database constraints | 12 / 16 passed; existing migration repeatability verified |
| Products / Customers / locations | 69 / 91 / 91 passed |
| Historical inventory / product-only inventory | 124 / 59 passed |
| Representatives / promotions / invoices | 129 / 121 / 167 passed |
| Inventory / representative / promotion / invoice contention | 7 / 11 / 11 / 56 passed |
| Authentication / route protection | 303 passed |
| Offline service / successful multi-process sync | 97 / 7 passed |
| Real encrypted PWA/offline browser regressions | 25 passed |

PDF checks verified the `%PDF-` envelope, catalog, cross-reference trailer/EOF, A4 MediaBox, embedded fonts and ToUnicode mappings. Actual browser-generated thermal PDFs verified 80 mm width; 100-item A4 and thermal documents produced multiple pages. Synthetic long Unicode names, literal script/image markup escaping, cash/credit/partial/void/refund display, posted decimal discounts, current-customer reassignment, cross-representative denial, safe filenames and private headers passed. Repeated service/HTML/PDF generation preserved hashes of **all** test tables; HTTP document runs preserved invoice/payment/stock hashes while legitimate authentication changed session/audit data. Synthetic print-media screenshots were inspected at `storage/phase10-receipt-test.png` and `storage/phase10-a4-test.png`. No renderer temporary profiles remained after the run.

Outstanding verification is device-specific: no physical thermal/A4 printer, Android print service, native share chooser or live phone HTTPS deployment was tested. These are not claimed as verified capabilities. The local Windows Edge PDF and browser document paths passed; no migrations, real-data seeding or Phase 11 work was performed.
