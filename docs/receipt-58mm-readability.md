# 58mm receipt readability audit and change

## Scope and printing path

Audited repository main at `f35714d`. Contrary to the request's starting assumption, this version has no dedicated 58mm receipt. `/admin/invoices/ID/receipt` and `/rep/invoices/ID/receipt` render the 80mm receipt, with 72mm printable content. Selecting a smaller printer page can shrink that content, but the Redmi's actual scaling settings have not been inspected.

The browser path is authorized read-only snapshot -> `invoice-document.php` -> CSS -> explicit `window.print()` -> browser/Android print service. The installed PWA does not have a separate thermal renderer. There is no receipt canvas/image or raw Bluetooth/ESC/POS path, and therefore no app-side ESC/POS feed commands to remove. Download/Share PDF is a separate A4-only local Edge renderer and remains unchanged. Pending/offline previews retain their existing provisional markings; no offline official receipt or private caching was added.

## What changed

Open an existing receipt and choose **58 mm receipt**. This sets `paper=58`, preserved when switching the document language. The default receipt is still 80mm. A4 and downloaded/shared A4 PDFs are unchanged.

The opt-in stylesheet uses 58mm paper and a conservative 48mm content area. That content width is an assumption, NOT a measured printer specification. It is centered with 5mm side margins. Long names wrap; full monetary strings stay together, with a new line when space is tight rather than clipping. Existing invoice, free quantity, discounts, payments and totals are rendered from the same snapshots. Locally served regular/bold Noto Sans Sinhala WOFF2 fonts avoid dependence on a network font provider. The 58mm Print button waits for fonts before invoking the dialog.

| Text | Previous 80mm CSS, before any driver scaling | New 58mm CSS |
| --- | --- | --- |
| Company name | 17pt = 22.67px | 16px bold |
| Invoice number heading | 11pt = 14.67px | 13px bold |
| Other metadata | 9pt = 12px | 12px |
| Product names | 9pt = 12px bold | 14px bold |
| Quantities and prices | 9pt = 12px | 12px |
| Final total | 11pt = 14.67px | 16px bold |
| Footer and SKU | 8pt = 10.67px | 12px |

This is not a claim that every nominal font size increased. The original company heading belonged to a wider 80mm layout. The 58mm version fits its own page without relying on shrink-to-fit; products, total and footer are larger in CSS, and numeric/metadata text is retained at readable 12px.

## Top blank space: established facts and limits

- Previous app-controlled print top margin: `@page margin:4mm`. The main receipt already had zero print padding/margin and no leading empty elements/line breaks. Its screen-only outer margin (20px) and padding (4mm) were not the print cause.
- New 58mm print margin: `0 5mm`, meaning **0mm at top and bottom**. HTML/body/main top padding and margin are explicitly zero. The company heading's box starts at print coordinate y=0; normal font ascenders still leave a small ink offset.
- Chromium PDF raster inspection placed first company-name ink at about 0.9mm from the top, within the requested 0–2mm app-side target. This is not a paper measurement.
- The 4mm page margin is a confirmed app-side contributor, not a proven full explanation for the reported physical gap. Browser-added headers, the Android print service's margins/rasterization/fit, and mechanical feed cannot be diagnosed from the repository or desktop preview. No claim of a fully established hardware root cause is made.

`58mm auto` is not used because it is not a supported custom page-size pair in Chromium. The stylesheet specifies a finite 58mm x 297mm page, consistent with the existing receipt's pagination approach. Long receipts paginate without blank-only pages in the fixture tests. A short receipt PDF still has unused paper below its content; physical feed/cutting depends on the print service/driver and may not follow CSS. This change does not promise continuous-roll auto-cut length.

## Verification

No database connection, migration, operational record or financial posting was used for the new tests.

- Synthetic PHP rendering passes for 58mm, 80mm and A4 in English/Sinhala. Snapshot values remain unchanged; invalid paper options are rejected.
- 26 Chromium checks pass: phone-sized screen width, print overflow, locally loaded Sinhala fonts, physical PDF width (~57.8mm due to Chromium rounding), 48mm content, zero heading-box top offset, 30-item pagination and maximum supported DECIMAL money strings without digit wrapping/clipping.
- The synthetic examples contain three long English/Sinhala product names, free units, line discounts and an invoice discount. A cash-partial status and outstanding amount remain visible.
- Before/after print-media screenshots and all main-content geometry were compared for both 80mm and A4, in both languages: pixel-identical on the same Chromium installation. Their CSS files are unchanged.
- Print/share JavaScript tests: 6 pass, including the new 58mm font-wait test and existing offline guard.
- Broader UI/domain tests: 38 pass, 5 fail in customer-locations fixtures due to missing `nameEn` test data. All five failures also reproduce on unchanged main. They are unrelated and were not changed.
- Changed PHP files lint successfully. Database-backed integration tests were not run; no database is available or required for these layout fixtures.

### Reproduce fixture checks

```sh
php tests/receipt-58-fixture.php /tmp/dharma-receipt-fixtures
node --test tests/invoice-documents-ui.js
# Optional: provide an installed playwright-core via NODE_PATH, and installed
# PHP/Chrome paths via PHP_BINARY and BROWSER_BINARY. No package/runtime
# dependency is added to the app itself.
node tests/receipt-58-browser.js storage/receipt58-previews
```

## Remaining device verification

**Real Redmi Android Chrome/PWA print preview: NOT VERIFIED.** A phone-sized Chromium viewport and Chromium print/PDF output were tested, not Android's system print dialog.

**Actual 58mm Bluetooth printer output and printable width: NOT VERIFIED.** Printer model, Android print-service name/version and device feed/margin behavior are unknown. Select 58mm paper, 100%/actual size where supported, turn browser headers/footers off, and inspect the device preview. Confirm both left and right edges and the first company-name line on paper before normal use. If the service only accepts A4 or adds mandatory margins/fit-to-page, CSS cannot force it to behave as a thermal driver. This change adds no Bluetooth bridge or new permissions.

## Files

- `app/Controllers/InvoiceDocumentController.php`: passes the explicit paper selection only to browser documents.
- `app/Services/InvoiceDocuments.php`: optional validated rendering parameter; snapshot/calculation code unchanged.
- `app/Views/invoice-document.php`: 58/80 selector, isolated 58mm stylesheet/class, amount span and language-link preservation.
- `public/assets/css/invoice-receipt-58.css`: new opt-in thermal rules.
- `public/assets/fonts/NotoSansSinhala-{Regular,Bold}.woff2` and `NotoSansSinhala-LICENSE.txt`: self-hosted fonts/license.
- `public/assets/js/invoice-documents.js`: font-ready wait only for 58mm.
- `tests/invoice-documents-ui.js`: new font-wait assertion.
- `tests/receipt-58-fixture.php`, `tests/receipt-58-browser.js`: synthetic-only rendering/browser checks.
- This report.

No financial rules, prices, rates, stock, return flags, pilot permissions, stored invoices, encryption, IndexedDB, offline drafts, database migrations or deployment settings changed.

## Preview correction after review

The initial print-media viewport screenshots misleadingly showed content against the left image edge with unused desktop viewport space on the right. They were not physical-page renders. The real PDF has balanced 5mm side margins. Across both Sinhala PDF pages all extracted text runs sit between x=5.03mm and x=53.02mm on a ~57.83mm-wide page. No left-side text clipping was found in that PDF.

Updated review images are rasterized from every actual PDF page, including the separate totals page, not screenshots of a desktop print-media viewport. The fixture browser test now labels viewport screenshots accordingly and, when Poppler is installed, checks every PDF text run against the side bounds and exports actual-paper PNGs. This corrects the preview delivery, not the financial content or already centered page geometry. Physical device output remains NOT VERIFIED.
