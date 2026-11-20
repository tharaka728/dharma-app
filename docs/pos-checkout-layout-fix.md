# POS checkout layout repair — 15 November 2026

## Confirmed cause

The old `.pos-cart-row` used two auto-placed grid columns: `minmax(0,1fr) auto`. Its four children were product information, quantity controls, Remove, and the long promotion/review message. The last child occupied the automatic-width second column. That message could consume almost the whole card width, reducing the first column to a few pixels. `overflow-wrap:anywhere` on product text then split codes and LKR values character by character; the Remove label inherited the same narrow column. The result reproduced the supplied screenshots after the real Review/payment form POST, especially in Sinhala desktop mode. This was a sizing/placement problem, not malformed Sinhala Unicode.

A second defect used the literal English `aria-label="Invoice confirmation"` as a JavaScript selector. Sinhala translated the accessible label, preventing the confirmation from moving into checkout or being removed when an edit invalidated it. Finally, the enhanced page did not populate its dynamic total panel from the server review after a form postback, leaving an empty total panel and placeholder item messages.

## Runtime files changed

| File | Change |
| --- | --- |
| `public/assets/css/pos.css` | Named information/quantity/remove/total grid areas; full-width information and totals; scoped natural wrapping; one-line codes/prices; stable quantity controls; stacked mobile controls; responsive review cards and totals. |
| `public/assets/js/pos.js` | Separate code and unit-price spans; stable confirmation selector; display existing server-reviewed amounts after postback. No pricing or posting calculations added. |
| `public/assets/js/invoice-discount.js` | Invalidate confirmation through a stable data attribute in both languages. |
| `app/Views/invoices.php` | Stable confirmation attribute and presentation-only reviewed-total include. |
| `app/Views/invoice-reviewed-totals.php` | Hex-escaped JSON containing only whitelisted server review display fields. Never submitted as financial input. |
| `app/Views/invoice-review-lines.php` | Translated cell labels for readable responsive confirmation cards; original values retained. |
| `public/offline/index.html` | Versioned checkout stylesheet/adapter references. |
| `public/sw.js` | New public-static cache revision `dharma-static-checkout-v3` with matching `pos-checkout-v2` assets. |

The existing Sinhala-capable font stack and translation wording remain unchanged. Layout rules are scoped to POS elements. Product names can wrap, codes and prices remain together, and the item name is no longer clamped to three lines. Review details stack into labeled cells instead of squeezing six table columns into a narrow checkout panel. Payment fields stack when the two-panel arrangement leaves insufficient space.

## Actual interaction and responsive evidence

`tests/pos-checkout-browser.php` and `tests/pos-checkout-browser.js` use only `dharma_sales_phase9_test` and an ephemeral loopback test server. They sign in, choose a customer, search/add two products, change quantity to six, apply 25%, enter partial cash, click the real green Review button, wait for the server-rendered confirmation, and inspect the resulting checkout. They repeat in Sinhala and English. No final Issue button is clicked by this layout workflow.

102 checks passed across **320, 360, 375, 390, 414, 768, 1024 and 1440 CSS pixels**, including:

- No page-level horizontal overflow.
- Readable product-information width and bounded card height.
- One-line product codes, unit prices and numeric total values.
- Usable quantity controls within their row.
- Confirmation inside the checkout panel and an accessible final Issue control.
- Every displayed total identical before and after the review POST.
- Quantity edits remove the stale confirmation in both languages.

Additional verification: 97 localization checks; 185 invoice/payment/stock/snapshot/void/numbering checks; 110 offline checks; 100 discount/financial checks; 57 focused bilingual/offline browser checks; 17 JavaScript domain/UI tests. Changed PHP files passed lint and JavaScript files passed syntax checks. The localization search test now follows catalogue pagination instead of assuming matching fixtures always fit on page one. Test browser profile cleanup waits through Windows file-lock retries.

Existing services, prices, promotions, discount policies, cash/credit posting, stock deduction, numbering, signed bodies and historical invoices were not changed. No schema migration or live database writes were required. Synthetic browser/financial fixtures were confined to the isolated test database.

## Screenshots

Captured from the real review postback with disposable products:

- [Sinhala before, desktop](../storage/checkout-before-si-LK-1440.png)
- [Sinhala after, desktop](../storage/checkout-after-si-LK-1440.png)
- [Sinhala after, checkout panel](../storage/checkout-after-si-LK-panel.png)
- [Sinhala before, mobile](../storage/checkout-before-si-LK-390.png)
- [Sinhala after, mobile](../storage/checkout-after-si-LK-390.png)
- [English before, desktop](../storage/checkout-before-en-1440.png)
- [English after, desktop](../storage/checkout-after-en-1440.png)
- [English after, checkout panel](../storage/checkout-after-en-panel.png)
- [English after, mobile](../storage/checkout-after-en-390.png)

Additional 1024px before/after screenshots for both languages use the same filename pattern in `storage/`.

## Redmi Note 15 verification and safe update

Real Redmi hardware and installed Android PWA rendering remain untested; browser emulation is the completed evidence. On the existing authorized application origin:

1. While online, retain an encrypted recovery export of unresolved offline work. Do not clear browser/app storage, uninstall the PWA or delete IndexedDB.
2. Open the application online so its browser can detect the updated worker. Close all application tabs and the installed PWA, then reopen online. The waiting worker activates through its normal lifecycle; no forced `skipWaiting` was added and no caches or pending records are deleted by this change.
3. In Sinhala and English, add two products to an unposted cart, change quantities, apply a discount, enter payment and tap Review. Confirm readable codes/prices, compact cards, usable quantity buttons, totals and final confirmation in portrait and landscape. Stop before Issue unless making an intended real sale.
4. For offline acceptance testing, use the isolated staging/test account and authorized device procedure. Confirm saved pending work remains present and unchanged before syncing. Do not replace the real representative's registered device merely for visual testing.

A phone cannot reach this PC through the phone's `127.0.0.1`. This repair does not change network exposure or deployment authorization. The PC application remains at http://127.0.0.1:8088/ .

Required baseline verification also passed: 12 foundation checks and 16 database constraint/repeatability checks on the isolated schema. Final screenshot run passed all 102 checkout checks after correcting the review-ready message.
