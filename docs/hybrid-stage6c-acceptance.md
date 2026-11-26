# Stage 6C native-language and physical-print acceptance

Status: **software verification only; native-speaker and physical-printer approval NOT PERFORMED.**

Use synthetic records in a verified disposable environment. Do not connect a printer to operational posting, change drivers/network/Apache configuration, activate Hybrid or create genuine financial transactions under this checklist's authority.

## Sinhala wording review

Reviewer name: __________  Date: __________  Device/browser: __________
Role tested: representative / manager. Viewports tested: desktop / 390px phone / actual phone.

The scoped draft catalogue is `public/assets/i18n/hybrid-si-LK.json`. Review every entry in that file, including the longer policy messages and report headings. Existing `public/assets/i18n/si-LK.json`, shared catalogue.js and Dashboard/Customer Profile translations are unchanged. Sinhala financial documents retain their existing strict `HybridDocuments` translation dictionary.

The source candidate inventory and rendered residual inventory are recorded in the Stage 6C readiness report/evidence. Candidates include dynamic templates and server error messages; dictionary-entry counts are not evidence of complete coverage. Technical IDs, enum values, audit JSON, product/customer names, policy versions, percentage values and stored financial data must retain their identities. Missing translations fall back to readable English.

- [ ] Confirm the wording distinguishes paid units, promotion-qualified units, discount-eligible remainder, free entitlement and actual free delivery.
- [ ] Confirm “Paid quantity” describes units charged for; it must not imply that cash has already been collected.
- [ ] Glucose 8 at LKR 100, product discount 25%, invoice discount 0%: qualified 6, remainder 2, free 1, product discount 50.00, payable 750.00. Repeat with invoice discount 10%: invoice discount 75.00, payable 675.00.
- [ ] Glucose 6/12: remainder 0, free 1/2. Suwada Kudu 12: qualified 12, remainder 0, free 1. No product discount on qualified units.
- [ ] Confirm independent 25%, 30%, 35% and Other labels and two-decimal custom percentages.
- [ ] Confirm strict combined 50% cap wording includes product, replacement and invoice money, but excludes the notional value of actual free units.
- [ ] Confirm replacement is an explicit complete-entitlement selection, not an automatic substitute. Replacement uses the existing approved rate contract; its 0–100% custom-rate wording must not be confused with the product/invoice 0–50% limits or the combined cap.
- [ ] Confirm “original-net credit” means the original immutable unit allocations, not today's price or a newly calculated refund rate.
- [ ] Confirm debt reduction precedes Refund Due; Refund Due is a liability and does **not** mean cash has been refunded or transferable customer credit created.
- [ ] Confirm actual cash refund “Unavailable” is not translated or interpreted as zero.
- [ ] Confirm Saleable / Quarantine / Unsaleable remain distinct. A representative's condition entry is not permission for manager release, reclassification or disposal.
- [ ] Confirm pending custody records physical receipt only: no consumed financial positions, debt reduction, Refund Due or saleable stock increase.
- [ ] Confirm missing mandatory free recovery and retained-cap failures remain blocked; manager review/rejection cannot waive those conditions.
- [ ] Confirm original invoice / linked return / residual void are distinct and prior returns are not reversed again.
- [ ] Review all ambiguous-outcome/retry messages: retry the **same UUID and payload**; do not start a replacement command or navigate away while unresolved.
- [ ] Review checkout, return, custody, manager disposition, report and document text, including validation errors and dynamically generated messages that remain English.
- [ ] Confirm mobile Sinhala wrapping, button readability, keyboard/input focus, numeric amounts and table scrolling. English and Sinhala language preferences must not change IDs, rates, quantities, permissions or outcomes.
- [ ] Review existing Sinhala document text and bilingual amounts on 58mm, 80mm and A4/PDF. Do not infer native acceptance from glyph rendering or a software screenshot.
- [ ] Record remaining untranslated terms, wording corrections and screenshots, with explicit accept/reject decisions. Re-run affected software checks after wording changes.

Review record:

| Area | Reviewer decision | Wording change / evidence |
| --- | --- | --- |
| Checkout / remainder / free goods | PENDING | |
| Replacement / strict cap | PENDING | |
| Returns / debt / Refund Due | PENDING | |
| Custody / manager decisions | PENDING | |
| Linked-credit report and CSV headers | PENDING | |
| Invoice and return documents | PENDING | |
| Validation / ambiguous outcomes | PENDING | |
| Desktop and actual mobile device | PENDING | |

## Physical printing checklist

Printer make/model/firmware: __________  Connection: __________  Driver/bridge: __________
Phone/PC OS/browser: __________  Paper roll width: __________  Actual printable width: __________
Tester/date: __________  Synthetic invoice/return references: __________

No physical printer, Bluetooth connection, paper feed or cutter was exercised in Stage 6C. Browser/PDF rendering is separate evidence.

1. **Connectivity:** with owner-approved hardware setup, verify pairing/USB/network/OS print queue as appropriate. Record reconnect, power-cycle, denied permission and disconnected-device behavior. A browser print dialog or Bluetooth pairing alone is not a successful print. Do not assume an ESC/POS/Bluetooth browser bridge exists.
2. **58mm thermal:** select the stored 58mm layout, disable browser headers/footers and unwanted scaling, and measure the physical printable region. Check margins, long Sinhala product/customer names, quantities, percentages and every P/R/I/net column; no clipped amount or missing free quantity is acceptable.
3. **80mm thermal:** repeat width/scale checks on the actual 80mm roll and driver. Record output scale; ensure a setting remembered from 58mm does not silently shrink or crop it.
4. **A4:** verify portrait paper, margins, complete multi-page output, repeated headings where supported, no split/cropped totals, correct original invoice identity and legible small text.
5. **Sinhala glyphs:** inspect conjuncts, vowel signs, digits, punctuation and mixed Sinhala/English product names on paper. No tofu boxes, detached marks, substitutions or raster clipping. Compare with the local PDF and approved wording, not just the screen font.
6. **Feeding/cutting:** check top/bottom feed, trailing totals, blank-feed amount, cutter clearance and safe tear-off. Repeat short and long jobs; no cut may remove a total, return reference or liability statement.
7. **Long invoice:** use an isolated synthetic document with many lines and long names, independent discounts, free goods and replacement benefits. Check all page/roll transitions, total conservation and time to complete. Do not create a genuine sale for printing acceptance.
8. **Long return/void:** print a synthetic linked return and residual void with multiple lines, component reversals, recovered free units, conditions, debt reduction and Refund Due. Confirm the original reference is clear and custody text never implies a cash refund or automatic stock release.
9. **PDF parity:** export locally with the existing Edge PDF path, keep the file private, and compare paper/PDF totals, Sinhala glyphs and pagination. No remote PDF service or service-worker document caching.
10. **Failure/reprint:** cancel, disconnect, exhaust paper and repeat the print. Printing/reprinting must not issue invoices, allocate a new number, take payment, return goods or move stock. Record before/after financial and stock counts.

| Output | Connectivity | Width/glyphs | Feed/cutter | Long document | Result |
| --- | --- | --- | --- | --- | --- |
| 58mm invoice + return | NOT TESTED | NOT TESTED | NOT TESTED | NOT TESTED | PENDING |
| 80mm invoice + return | NOT TESTED | NOT TESTED | NOT TESTED | NOT TESTED | PENDING |
| A4 invoice + return | NOT TESTED | NOT TESTED | N/A if no cutter | NOT TESTED | PENDING |
| Local PDF comparison | Software evidence only | Native/paper review pending | N/A | Hardware comparison pending | PENDING |

A hardware PASS requires named tester evidence and actual printed samples. This checklist does not approve device installation, operational changes or activation.
