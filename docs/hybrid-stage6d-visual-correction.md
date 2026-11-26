> Superseded by the owner's final UI removal decision on 26 November 2026. The separate editor and owner preview have been removed; the URLs and UI acceptance below are historical. Current state: [UI removal](hybrid-stage6d-ui-removal.md).

# Stage 6D-UI — original New Invoice visual correction

26 November 2026. Isolated visual correction completed on `hybrid-promotion-v1-stage6d-ui`, worktree `C:\xampp\htdocs\dharma chemical sales app\storage\hybrid-stage6d-ui`. HEAD remains `8477ddac2271114a1f74ff539924541eb4974df5`. No commit, push, merge, migration, operational access, activation or financial posting occurred in this correction.

## What changed

The earlier implementation reused product cards but retained a separate Hybrid page shell and moved the invoice summary/payment arrangement. This correction uses **the actual original `app/Views/invoices.php` shell** for eligible New Invoice requests. Its navigation, heading, product browser, customer panel and original POS presentation remain in use. There is one visible invoice editor.

`app/Views/hybrid-invoice.php` is now a thin compatibility entry into that original view. The original view selects a Hybrid fields partial only for the explicit Hybrid branch; original legacy/pilot markup and scripts remain on their existing branch. Both eligible New Invoice URLs resolve to the same original shell, not two editors.

The existing `createPOS` component and **unchanged `pos.css`** supply search/categories, product cards and Add buttons, image handling, prices/stock, customer selection, cart and quantity steppers. The original invoice-discount fieldset style, totals block, payment row and Review placement are restored. Server-provided Hybrid values populate the existing POS summary; this mapping does not calculate or apply discounts in JavaScript.

The editable cart and payment controls remain locked during unresolved confirmation. Confirmation/retry stays outside that disabled fieldset. Customer/catalogue panels become inert while acceptance is unresolved. Existing request identity, review invalidation and pending-command handling remain in use. The source financial engines, resolver, snapshots, writer, returns, readers and routing policy were not changed.

### Intentional differences from the original legacy screen

- Product rows add the mandatory-default 0% discount selector, Other/custom input, explicit eligible replacement and expandable original paid/qualified/remainder/free and monetary components.
- One whole-invoice selector uses the approved Hybrid 0–50% range and sequential policy. Its verified monetary amount appears in the original summary. This does not change the separate legacy invoice-percent contract.
- The legacy **benefit simulation** panel is omitted from Hybrid checkout: actual authoritative Hybrid controls/review replace that simulation. It remains on the original legacy branch. There is no second benefit editor or duplicate calculator.
- Hybrid validation, online-only and posting-hold messages appear within the existing checkout column; the earlier separate ONLINE CHECKOUT header, large introductory banners and page-wide review panel are removed.
- Review includes expandable Hybrid evidence inside the original confirmation position. The current owner preview keeps confirmation disabled.
- A representative's source remains their server-authorized assigned stock; no extra representative source picker is introduced. Existing administrator source selection remains on the unchanged legacy branch.

## Exact files changed in this correction

Seven existing files changed relative to the preserved pre-correction Stage 6D state:

1. `app/Views/invoices.php` — narrow Hybrid branch and conditional scoped assets inside the original page shell.
2. `app/Views/hybrid-invoice.php` — compatibility entry into that shell.
3. `public/assets/js/hybrid-invoice.js` — original POS placement/summary mapping, original connection indicator, disabled empty Review state and editable-panel locking.
4. `public/assets/css/hybrid-unified.css` — only scoped Hybrid additions; original POS colours, cards, responsive grid and sticky Review behaviour come from unchanged original CSS.
5. `public/assets/i18n/hybrid-si-LK.json` — three draft labels: verified components and two previously untranslated Hybrid status messages.
6. `tests/hybrid-ui-bridge.php` — test-only original-branch rendering for the temporary screenshot comparison server and matching read-only options alias.
7. `tests/hybrid-ui-router.php` — comparison-only GET options allowance; consistent public demo locale selection. The normal owner preview does not enable the comparison mode.

Six new files:

- `app/Views/hybrid-invoice-fields.php`
- `tests/hybrid-stage6d-visual.js`
- `tests/hybrid-stage6d-visual.php`
- `tests/hybrid-stage6d-visual-readers.php`
- This report.
- `docs/hybrid-stage6d-visual-inventory.json`.

The additive JSON inventory records before/after SHA-256 values for these **13 files**. The earlier 18-file inventory, readiness report and real-data compatibility report remain unchanged historical records; the new inventory supersedes hashes only for the deliberately corrected files. Before editing, 20 reviewed source/document files were preserved byte-for-byte in ignored isolated storage, with their hash ledger referenced by `storage/stage6d-visual-before-current.txt`. Those private preservation copies are not source-checkpoint candidates.

Cumulative worktree status after this correction: **10 tracked modified files and 16 untracked source/document files**, including all previous Stage 6D work. Nothing is staged. Local main, the checkpointed Stage 6C worktree and original Stage 1 worktree were not modified.

## Original-versus-corrected evidence

No original owner screenshot was attached. The baseline renders the **original legacy branch of the actual New Invoice source**, associated scripts and unchanged POS stylesheet, against the same retained synthetic fixture. It is not an operational screenshot and contains no real customer/product/price/stock records.

Both captures use paid quantity 8 on the same synthetic Glucose card with customer unselected, so no legacy financial preview is needed. The baseline comparison server permits only its required GET views/options/assets and the existing Hybrid preview allowlist; every invoice/payment/return/stock writer remains denied. It is closed after screenshots. The normal owner preview never switches to this baseline branch.

**108 comparison checks passed** across 1440px, 390px and 360px, in English and Sinhala. Checks cover identical heading/navigation text, responsive grid areas, shared header/customer/catalogue/card/Add/quantity widths and palette, no horizontal overflow, categories/search and exactly one editor. These checks establish preserved structure and component geometry, not pixel-identical page height: the Hybrid additions deliberately change cart height.

Final evidence directory: `storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640`.

| Viewport / language | Original source | Corrected |
| --- | --- | --- |
| 1440 / English | [Original](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/original-1440-en.png) | [Corrected](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/corrected-1440-en.png) |
| 1440 / Sinhala | [Original](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/original-1440-si-LK.png) | [Corrected](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/corrected-1440-si-LK.png) |
| 390 / English | [Original](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/original-390-en.png) | [Corrected](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/corrected-390-en.png) |
| 390 / Sinhala | [Original](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/original-390-si-LK.png) | [Corrected](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/corrected-390-si-LK.png) |
| 360 / English | [Original](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/original-360-en.png) | [Corrected](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/corrected-360-en.png) |
| 360 / Sinhala | [Original](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/original-360-si-LK.png) | [Corrected](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/corrected-360-si-LK.png) |

[Side-by-side comparison gallery](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-d4aefc610640/comparison.html). Synthetic products have no photographs; captures show the original placeholders. Existing production image URL/rendering paths are retained in source, not claimed tested against real photographs. Full-page captures retain the original sticky Review/mobile navigation behaviour and can show those controls at their capture positions.

## Targeted acceptance

| Check | Result |
| --- | --- |
| Original/corrected desktop and mobile, English/Sinhala | 108 checks PASS; 12 screenshots |
| Real corrected Hybrid browser journey | 45 checks PASS: Glucose 6/8/12, Suwada 12, ordinary zero-benefit, default/custom/product/invoice discounts, multiline, combined cap, conflicts, stock shortages, replacement and offline rejection |
| Read-only safety on retained fixture | 9 checks PASS: wrong Glucose and Suwada stable-ID bindings rejected, customer/source scope, enabled/disabled unified routing, one legacy pilot editor and unchanged source controls |
| Dashboard/Profile browser | Existing 28-check suite PASS; same stored synthetic fixtures, no fixture replacement, temporary GET-only reader boundary with keyed synthetic session setup |
| Scoped controllers/readers/protected files | Existing 30 checks PASS; no business writes and default connection factory forbidden |
| Browser/domain helpers | Hybrid intent 5 and offline reconciliation/AES-GCM 8 tests PASS |
| Owner-preview route boundary | Six denied-write probes in the functional browser suite plus 13 final direct probes returned 403, including invoice/payment/void/return/stock/offline writers, private files and the comparison-only options alias |
| Database side effects | All 90 complete table digests unchanged throughout visual, preview and reader checks; no new fixtures or financial records were created |
| Source preservation | 23 Hybrid services, existing invoice/financial readers, source gates, original POS stylesheet, shared designs/translations and prior documentation unchanged; protected 12-file baseline verified |
| Syntax / whitespace | Seven changed/new PHP files linted, application/test JS syntax and final diff checks PASS |

Glucose 8 at synthetic LKR 100.00, product 25%, invoice 0% still reviews as qualified 6, remainder 2, free 1, product discount 50.00, payable 750.00. With invoice 10%, payable is 675.00. Glucose 6/12 delivers free 1/2 without product discount on qualified units; Suwada 12 delivers free 1. Ordinary zero-benefit invoices retain the Hybrid contract. No monetary values are inferred from the notional value of free goods.

The first baseline capture exposed the original template's non-API GET options URL being denied by the existing preview boundary. It was permitted only in the temporary comparison mode; the owner preview still returns 403 for that alias. Visual inspection also found two untranslated status messages, now scoped draft translations. No stable-ID validation or financial policy was relaxed. Full financial/posting or migration suites were not repeated for this visual correction.

Reviewed-state evidence from the actual corrected browser journey: [desktop totals](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-functional-9bac00d663604b2287691e9f82d15e14/unified-desktop.png), [mobile totals](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-functional-9bac00d663604b2287691e9f82d15e14/unified-mobile.png), [Sinhala mobile review](../storage/stage2c-92bd1c8ae6a24f74aa5e39003e184d42/stage6d-visual-functional-9bac00d663604b2287691e9f82d15e14/unified-sinhala-mobile.png). These use selected synthetic customers and authoritative Hybrid previews, unlike the unselected-customer baseline comparison.

## Safe owner preview and remaining limits

**http://127.0.0.1:63833/rep/invoices/new?language=en**

Sinhala: **http://127.0.0.1:63833/rep/invoices/new?language=si-LK**. Root URL still redirects to the corrected screen. Reload an already open preview tab to obtain the new hashed assets. The existing start/stop script and commands in the Stage 6D readiness report are unchanged.

Select the synthetic customer, add Glucose, enter paid 8, product 25%, cash 750.00 and Review. Expand quantities/components. Posting remains disabled, and direct financial requests remain denied. All shown business records remain synthetic.

The same physically verified disposable database on loopback port **63832** and existing fixture were reused. Schema: `dharma_sales_hybrid_16d02288876344d887c6d9dd767a0cb1_test`. No new database, migration, account provisioning, operational connection or stock/payment/invoice/return write was performed. Temporary baseline/reader servers and Edge processes were closed; the owner preview remains running. No source feature gate, database allowlist, operational configuration or financial hold changed.

Native Sinhala wording is **not approved**. Software captures/checks cover glyph rendering and wrapping at the requested widths; the three added labels and existing financial wording still need a named native reviewer. No physical printer acceptance is claimed.

Real business data integration remains blocked pending separately authorized operational ID/offer/schema/checksum/price/assignment/stock-ledger preflight and an approved target-specific runtime. The disposable CLI/database guard remains intact, including review. See [real-data compatibility report](hybrid-stage6d-real-data-compatibility.md). Migration 007/raw-byte packaging, operational runtime acceptance and existing native/physical-printer blockers remain unchanged. No real-data or production-readiness PASS is claimed.

Recommended next step: owner visual review of these paired captures and the running corrected screen, followed by native wording review. Any release integration, operational preflight, commit/push or activation requires separate authorization. **Stop after this visual correction and preview handoff.**
