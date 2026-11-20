# Supermarket POS invoice interface — 14 November 2026

The existing invoice form now has a teal header, product-card catalogue, invoice cart, quantity controls, invoice discounts, large verified totals and a Pay Now / Review button. Desktop uses two panels; mobile stacks customer selection, catalogue and checkout. The supplied mobile and desktop images guide the styling; their example products, customer, numbers, barcode scanner, mixed payments and Hold/Draft buttons were not copied into the application.

## Open the application

- Admin: http://127.0.0.1:8088/admin/invoices/new
- Representative: http://127.0.0.1:8088/rep/invoices/new
- Existing offline workspace: http://127.0.0.1:8088/offline/index.html#new
- On the owner's already configured USB-forwarded phone, use `http://localhost:8088/login`, then New Invoice for the signed-in role. This session did not verify the physical phone or its ADB forwarding.

Admin first selects the explicit warehouse/representative stock source. Choose an authorized customer, search/browse products and tap Add. Repeated Add taps increase the existing line. Minus stops at one; Remove and confirmed Clear Cart affect only this unposted form. Enter in product search focuses an Add button instead of submitting the invoice. Add Customer is admin-only and opens a separate tab so the cart stays open.

Select an existing discount and payment type, enter cash actually received, then choose Pay Now / Review. The existing server review and **Confirm & Issue Invoice** step remain mandatory. Only authoritative successful posting redirects to the numbered invoice and its existing receipt, A4/PDF and new-invoice actions. Stock does not change when a card is added. Server checks include free-unit demand, current prices/promotions, credit permission/limits, authorization, CSRF and idempotency.

## Implementation and preserved rules

`createPOS` is a shared vanilla-JavaScript presentation adapter around the original controls. It does not calculate invoice money or post sales. Online totals come from the existing authenticated POST preview; offline totals come from `OfflineDomain.preview`. Pending and accepted offline records continue through the existing encrypted vault, signing and synchronization functions.

The existing options endpoint supports a bounded, read-only catalogue query: `catalogue=1`, `q`, `category`, `page`, `stock_source`. Pages contain at most 12 products. Queries use native prepared parameters; representative stock source is forced to the authenticated representative even if another source is submitted. No cost prices enter catalogue results. Search waits 300 ms and aborts/ignores stale responses. Category buttons use real catalogue data and scroll independently when numerous. Product images are not stored in the current catalogue, so cards use lightweight geometric placeholders. New invoice HTML includes only already-selected products, rather than the whole catalogue. An explicit no-JavaScript fallback can load the original product selectors.

Fresh authorized offline snapshots include display-only category names from the existing category table. Existing grants and signed drafts are not rewritten. Older snapshots without category metadata continue to work with All Products until an authorized refresh. Offline catalogue rendering is paged locally over the existing authorized snapshot. Offline cash-only restrictions, seven-day validity, encrypted storage, 15-minute locking, one-device registration, provisional cash retention and coherent stock/receipt reconciliation remain in force. Locking clears the new product/cart/total presentation as well as the existing private DOM.

**No migration or live business write was needed.** Invoice numbering, UUIDs, payment posting, approved percentage calculation/allocation, price versions, immutable financial snapshots, void/refund liability rules, stock accounting and historical records were not changed. `InvoiceDiscount`, `PromotionCalculator`, the offline calculation domain and encrypted store remain the authoritative existing implementations. The only backend additions are read-only catalogue selection and offline category display metadata.

## Modified source and tests

- `app/Controllers/InvoiceController.php`: catalogue dispatch and selected-only create options.
- `app/Services/Invoices.php`: bounded catalogue query and selected-product options; posting/calculation logic unchanged.
- `app/Services/OfflineSales.php`: real category display metadata in newly issued snapshots.
- `app/Views/invoices.php`, `app/Views/invoice-line.php`: versioned POS assets, catalogue metadata and no-JavaScript fallback.
- `public/assets/css/pos.css`, `public/assets/js/pos.js`: responsive shared POS presentation and controls.
- `public/assets/js/invoice-discount.js`: forwards authoritative preview results to the totals presentation and invalidates stale totals.
- `public/assets/js/offline-app.js`, `public/offline/index.html`: connect the existing offline workflow to the POS adapter; retain signing, save and sync actions.
- `public/assets/js/workspace.js`: avoid adding a second customer-search control when the POS manages it.
- `public/sw.js`: new static-only asset cache version; preserve waiting activation and all old caches/IndexedDB.
- `tests/invoices.php`, `tests/offline.php`: bounded catalogue, scope, filtering, selected-product payload and category metadata checks.
- `tests/phase12-browser.php`, `tests/phase12-browser.js`: exercise POS controls, totals, search, keyboard behavior, layout and screenshots using isolated fixtures.
- `tests/offline-browser.js`: offline POS taps, totals, removal and locked-DOM privacy, alongside existing restart/sync/recovery checks.
- `tests/mobile-installation.php`: verify new public assets and waiting-update version.

## Verification

Synthetic writes were confined to `dharma_sales_phase9_test`. Existing test records were preserved; no database was reset. No fictional records were inserted into the live application database.

| Check | Passed |
| --- | ---: |
| Foundation / database constraints | 12 / 16 |
| Products / customers / locations | 69 / 91 / 91 |
| Legacy inventory / product stock | 124 / 59 |
| Representatives / promotions / invoices and catalogue | 295 / 121 / 181 |
| Inventory / representative / promotion / invoice contention | 7 / 11 / 11 / 59 |
| Discount, payment, credit, void/refund and immutable snapshot enhancements | 100 |
| Report foundations / calculations / browser | 48 / 81 / 42 |
| Offline service / concurrent legacy sync / discounted sync | 106 / 7 / 9 |
| Actual encrypted offline browser, POS taps, retry, recovery and lock | 29 |
| Authentication, sessions, authorization and route protection | 305 |
| Documents / actual receipt, A4 and PDF browser checks | 31 / 33 |
| JavaScript unit tests | 38 |
| Public PWA metadata, identity and assets | 24 |
| Phase 12 workflow | 33 |
| Final POS/layout/navigation browser matrix | 595 |
| Short-code concurrent creation | 2 |
| PHP lint / JavaScript syntax (files) | 120 / 31 |

The final browser matrix covers 320, 360, 375, 390, 414, 768, 1024 and 1440 px, including no document overflow. POS assertions cover product search, category selection, empty results, keyboard focus, ignoring delayed stale responses, Add/increment/minimum quantity, customer preservation, confirmed/cancelled Clear Cart, disabled empty checkout and authoritative totals. Both screenshots were visually inspected. They use deliberately long **isolated test records**, not real customer/product data: [mobile](../storage/pos-mobile.png), [desktop](../storage/pos-desktop.png). Detailed suite outputs are retained in `storage/pos-test-*.txt`.

One initial report-browser command failed while Windows was deleting its temporary Edge profile, after all 42 assertions had passed. A subsequent complete rerun exited successfully and confirmed financial/stock hashes were preserved. A new offline tap assertion also caught a total-panel refresh-order bug; it was corrected and the 29-check browser suite subsequently passed.

Read-only live checks passed: exact database `dharma_sales_phase1`, UTC, native prepared statements and DML-only runtime privileges; invoice/payment/discount/refund and warehouse/rep ledger reconciliation; offline invariants. Live data still has three issued invoices and no registered offline devices/submissions. Apache 8088 and MariaDB 3309 listen on `127.0.0.1`. No port, firewall, global XAMPP, WordPress, recovery or deployment settings were changed.

## Redmi update and manual acceptance — NOT TESTED

1. Keep the existing ADB USB forwarding active. Open the existing `http://localhost:8088/login` origin; do not switch between `localhost` and `127.0.0.1` on the phone, since their browser storage is separate.
2. Sign in and let the app check its service worker. Save any current provisional sale through the existing workflow and export encrypted recovery data if there is pending work. Close all tabs/windows of this app, including the installed PWA, then reopen it to allow the waiting update to activate. **Do not clear browser/app data, unregister workers, delete caches or erase IndexedDB.** Existing worker updates never call `skipWaiting` or delete caches/vaults.
3. Confirm the teal POS screen, real customer scope, category scrolling, product search, Add/plus/minus/remove, discount controls, payment fields, keyboard behavior and sidebar on the actual phone. Browse and review only; do not confirm a fictional sale in the live database.
4. Physical touch, Android keyboard/safe-area behavior, installed-PWA update acceptance, actual printer/share UI and phone airplane-mode/reconnect acceptance remain owner tests. Offline posting tests belong in an isolated environment with authorized test accounts/devices; do not register a fictional live representative or clear unresolved phone records.

No deployment or unrelated development was performed.
