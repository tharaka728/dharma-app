# Hybrid Promotion V1 — owner requirements

Status: **design requirements only**, corrected by the owner on 24 November 2026, including the three financial decisions, five core partial-return principles and final A1-E1 edge-case decisions below. No implementation, activation, live posting, removal of financial holds or operational data changes are authorized by this correction.

## Invoice eligibility amendment - 27 November 2026

This amendment supersedes the previous permission to stack invoice discounts on promoted lines. Any free entitlement (including replaced entitlement), nonzero product discount amount or nonzero replacement discount amount excludes the entire line from invoice discount. A 0% product selection alone does not exclude a line. Excluded lines and segments receive invoice discount `0.00` while retaining their product/replacement discounts and payable. Eligible payables alone form the invoice discount base. Shared legacy policies, immutable historical snapshots, exact allocation and the strict combined 50% cap are unchanged.

Owner-reported Original Invoice browser verification: Glucose 8 x 100, free 1, product discount 50, invoice discount 0, payable 750; Ordinary 5 x 100 without product benefits, invoice discount 50 at 10%, payable 450. Eligible subtotal 500 and final payable 1,200 match the server and UI. No new browser/database run is claimed here.

## Final product promotion quantities

| Product | Paid units per qualifying group | Free units per complete group |
| --- | ---: | ---: |
| Glucose | 6 | 1 |
| Suwada Kudu | 12 | 1 |

Free quantities repeat for every complete qualifying group:

`free = floor(qualifying_paid_quantity / buy_quantity) × free_quantity_per_group`

Incomplete groups earn no additional free units. Free units do not qualify for further rewards.

| Product | Qualifying paid quantity | Free quantity |
| --- | ---: | ---: |
| Glucose | 6 | 1 |
| Glucose | 12 | 2 |
| Glucose | 18 | 3 |
| Suwada Kudu | 12 | 1 |
| Suwada Kudu | 24 | 2 |
| Suwada Kudu | 36 | 3 |

These quantities replace every earlier Hybrid example or specification stating **Glucose Buy 12 Free 1** or **Suwada Kudu Buy 12 Free 2**. Other conflicting quantities in older documents are not Hybrid V1 requirements. Historical descriptions of operational rules remain historical evidence, not instructions to update those rules.

## Other owner-approved requirements retained

- Available to **all authorized sales representatives when separately activated**. Preserve server-side role, customer and stock-source scope. The older single-representative pilot restriction is not the Hybrid V1 availability requirement.
- Each discount-eligible product line or remainder segment has an independent dropdown: **No Discount (0%)**, **25%**, **30%**, **35%**, **Other Discount**. Initial selection and initial applied discount are **0%**. Never automatically apply 25%. The fixed V1 dropdown supersedes the earlier default-25% wording.
- Other Discount requires an explicitly entered decimal percentage from **0% through 50%**, inclusive, with at most two decimal places under existing rate-format conventions. Client and server independently reject invalid, negative, nonnumeric, nonfinite and above-50% values; reject unsupported choices and mismatched choice/rate fields. Browser-submitted totals are never authoritative.
- The Hybrid whole-invoice percentage is also limited to **0% through 50%**. This is a Hybrid-specific validation requirement, not a change to historical `INVOICE_PERCENT_V1` or `PROMO_CHOICE_V1` rate semantics.
- Free promotion, product discount and whole-invoice discount may coexist on an invoice.
- Apply the invoice percentage only to lines without free entitlement or nonzero product/replacement discounts. The former LKR 100 less product 25% then invoice 25% = 56.25 example is superseded: that product-discounted line now remains payable 75.00 with invoice discount 0.00.
- Preserve **no double benefit on the same paid quantity**. Paid quantity qualifying for free promotion cannot receive the alternative product discount. Product discounts may apply to distinct remainder quantities, but any such benefit or free entitlement excludes the entire line from invoice discount.
- **ONLINE ONLY initially.** Do not convert legacy signed offline sales to Hybrid V1, rewrite signed bodies or add Hybrid offline posting through this amendment.
- Preserve historical financial records and existing operational data: immutable invoice/line/promotion snapshots, official invoice/payment/stock/sync identities, append-only history and exactly-once posting remain unchanged.

The final owner policy and subsequent decisions supersede earlier unresolved quantity-allocation wording: complete groups qualify for free entitlements, and below-threshold or remainder paid quantities are discount-eligible. Hybrid excludes pooled mixed-oil promotions. The sale rules, five core return principles and A1-E1 are settled design requirements. Noncash linked returns and exceptional retained-cap/free-recovery resolutions remain explicitly blocked pending separate policies; these exclusions do not reopen A1-E1.

## Deterministic quantities, eligibility and rounding

Resolve active, versioned offers through stable product IDs, never product-name matching. For a single-product Buy-X-Get-Y rule with paid quantity Q, threshold X and reward Y:

- Complete groups = floor(Q / X).
- Promotion-qualified paid quantity = complete groups multiplied by X.
- Free entitlement = complete groups multiplied by Y.
- Discount-eligible remainder = Q minus promotion-qualified paid quantity.

Glucose 5 has zero free units and 5 discount-eligible paid units; Glucose 8 has 1 free unit and a remainder of 2. Suwada Kudu 11 has zero free units and 11 eligible paid units; Suwada Kudu 18 has 1 free unit and a remainder of 6. For products with no applicable free offer, paid quantities may receive the independently selected product discount under the approved eligibility rules. Existing percentage/fixed promotion conflicts block Hybrid checkout as specified below.

Keep one aggregate paid product line, with explicit benefit segments; do not duplicate invoice lines or count stock twice. Representatives cannot manually increase free quantities. Round each selected segment's product discount half-up to two LKR decimal places using exact arithmetic. Qualifying paid quantities cannot also receive the representative-selected product discount. Quantity edits invalidate review and recalculate all benefits.

Reject pooled mixed-oil rules/rewards in Hybrid rather than silently suppressing or stacking them. Existing mixed-oil legacy workflows remain unchanged. Preserve admin offer enable/disable controls, immutable revisions and non-overlapping active free offers. Apply the same configured eligibility to eligible customers; do not create customer-specific exceptions.

## Eligible-line invoice discount and mandatory monetary cap

Let S be the original paid merchandise subtotal before any discounts, P the sum of rounded product-segment discounts, R the compatible approved shortage-replacement monetary discount, and I the whole-invoice monetary discount. Actual free units have zero discountable selling amount; their notional value is excluded from this monetary cap.

1. Resolve free entitlements, stock feasibility and any compatible replacement benefit; reject ambiguous cases.
2. Compute P only on discount-eligible paid segments. Keep P and R separate and prevent overlapping product benefits.
3. Invoice base B is the sum of post-product/replacement payables only for lines with no free entitlement and zero product and replacement discount amounts. It is not the whole invoice's S - P - R when excluded lines exist. Require nonnegative payables.
4. Calculate I once on B using the explicitly selected Hybrid invoice rate, limited to 50%, with half-up invoice-cent rounding. Allocate I proportionally using largest remainders and original input order for ties, preserving the existing approved invoice allocation behavior.
5. Combined monetary discount D = P + R + I. In exact integer cents, accept only when **2 × D_cents <= S_cents**.
6. Merchandise payable = S - D. Preserve separately authorized settlement, cash/change, credit and reversal rules afterward.

**Owner-approved strict cap algorithm:** use exact integer-cent/string arithmetic and validate authoritative amounts server-side in both preview and issue. Calculate and round the discount components under their approved policies first, then compare `2 × D_cents <= S_cents`. Do not round the ceiling upward or compare rounded display percentages. The maximum representable discount is `floor(S_cents / 2)` cents. Compute without floating point or integer overflow; the existing exact string helpers are reusable.

For LKR 100.00, combined discounts 49.99 and exactly 50.00 pass; 50.01 fails. For LKR 100.01, 50.00 passes and 50.01 fails because the exact half is 50.005. A requested nominal 50% discount that rounds to 50.01 must therefore be rejected, not clamped to 50.00. The owner has settled this odd-cent boundary.

Keep rounding and allocation distinct: segment product discounts are rounded half-up individually; replacement uses its approved aggregate rounding; the sequential invoice discount is rounded once and allocated by largest remainders. Require sum of invoice allocations = I, sum of separately stored product/replacement/invoice components = D, and sum of final line values = S - D. Allocation must not create or remove a cent to make the cap pass.

Excess discounts **reject the financial preview/posting request** with the component amounts, combined requested amount, permitted ceiling and a clear instruction to reduce selections. Do not silently reduce selected rates or automatically choose a compliant combination. Representatives must explicitly adjust their selections and obtain a new review.

Actual free units remain separate benefits, with no additional notional-value ceiling introduced. Replacement money is never exempt from the cap merely because it replaces free units. A line with product discount 50% receives invoice discount 0.00 even if invoice rate 50% is selected; its monetary reduction remains 50%. Mixed cohorts must still pass the combined cap, including replacement rates retained from the existing policy.

The following legacy cap-only illustrations are retained as historical arithmetic, not valid current benefit-cohort examples. Current calculations must first establish eligible invoice lines under the 27 November amendment; the cap test itself is unchanged.

| S | P | R | I | Combined monetary discount | Outcome |
| ---: | ---: | ---: | ---: | ---: | --- |
| 1,000.00 | 300.00 | 0.00 | 280.00 | 580.00 | Block: ceiling 500.00 |
| 2,000.00 | 400.00 | 200.00 | 300.00 | 900.00 | Cap passes: payable 1,100.00, subject to valid replacement and all other checks |
| 2,000.00 | 500.00 | 200.00 | 400.00 | 1,100.00 | Block: ceiling 1,000.00 |

These are monetary illustrations, not approval of an unspecified replacement rate or entitlement mapping. The superseded interpretation allowing 75% effective monetary discounts is invalid.

## Shortage replacement: owner-approved limited substitution

Owner selected Option A: reuse the approved `PROMOTION_DISCOUNT_INSTEAD` calculation **only where compatible**. Count its monetary amount in R and in the combined 50% ceiling. Never deliver free units and replacement money for the same entitlement. Keep replacement benefits separate from representative-selected product discounts and whole-invoice discounts. Paid stock must still be available; replacement money cannot cure insufficient paid stock. Never permit negative stock.

Read-only source inspection on 24 November 2026 found:

- `BenefitPolicy::eligibility()` qualifies a non-mixed Buy-X-Get-Y line when its full paid quantity meets the threshold; mixed-oil, below-threshold and nonqualifying lines are excluded from this replacement policy.
- `BenefitPolicy::apply()` uses the **entire qualifying line's gross paid amount**, including any remainder, as the eligible base. It totals those bases, rounds one discount half-up to invoice cents and allocates it proportionally by largest remainders with original-order ties.
- The rate is explicitly supplied through existing 25/30/35/custom choices and existing 0-100 validation. The implementation does not derive a replacement rate from stock shortage or free-unit value. Reuse this existing rate selection without inventing a new rate or default; the independent 0-50 product/invoice limits do not silently redefine the legacy replacement validator. Every selected replacement amount still counts toward the strict combined cap.
- This policy is invoice-wide: it suppresses all free rewards and monetary product promotions, sets stock demand to paid units, and does not model partial free fulfillment or simultaneous independent remainder discounts. The current invoice path blocks separate whole-invoice discount fields alongside the benefit mode and blocks insufficient stock rather than automatically substituting money.

The owner now approves adapting this calculation to an explicit cohort of affected qualifying product lines, subject to all of these restrictions:

1. Paid stock must be available. Each affected line must consist entirely of complete qualifying groups: no remainder quantity and no overlapping manual product discount. Below-threshold, remainder-bearing, ambiguously eligible or incompatible lines cannot use this initial substitution path. A discounted remainder must fail clearly; do not silently clear its selection.
2. Substitute the **complete free entitlement** of the affected line. Deliver zero actual free units for that replaced entitlement. Do not mix free units and replacement money within it or substitute individual groups. A partial-entitlement shortage is rejected, not converted into a partial or guessed monetary replacement.
3. For same-product stock, evaluate available stock after reserving paid demand: insufficient paid demand blocks checkout; no residual stock for a positive free entitlement is a complete free-stock shortage; residual stock sufficient for only part of the entitlement is a partial shortage and blocks this path. Revalidate under the existing posting locks; never permit negative balances.
4. The representative explicitly selects the existing replacement rate. Preserve the full gross paid-line base, original non-mixed Buy-X-Get-Y eligibility and approved half-up aggregate discount/largest-remainder allocation. With multiple affected lines, retain the existing single selected replacement rate and aggregate calculation across that cohort; independently rated replacement cohorts or different rounding scopes are not implicitly authorized.
5. Do not call the invoice-wide suppress-all mode on the whole Hybrid basket. Scope the approved arithmetic to the replaced lines, preserving unrelated free entitlements and separate manual discounts on other eligible lines. Any implementation that cannot preserve this scope must fail closed. Keep the existing `PROMO_CHOICE_V1` policy and operational offers unchanged.
6. Compute invoice discount only on eligible other lines after resolving P and R; exclude every replaced-entitlement line. Count the full R in the strict combined cap. Exceeding it rejects preview/posting; no automatic rate reduction, guessed replacement rate or fallback into legacy posting is allowed.

Example: Suwada Kudu 12 paid at LKR 100 has full-line gross 1,200 and entitlement 1. With paid stock available but no residual free stock, an explicitly selected existing 25% replacement gives R = 300, zero free delivered and paid-only stock demand 12. With P = 0 and invoice rate 10%, I = 0, D = 300 and payable = 900; the 600 cap is satisfied. The former I=90/payable=810 illustration is superseded. This illustration does not preselect 25%. Suwada Kudu 18 is not eligible for this initial replacement path because it contains a remainder. For Glucose 12 with entitlement 2 and residual free stock 1, the partial-entitlement shortage blocks substitution.

Preview and immutable snapshots must show free entitlement, actual free units, fully substituted entitlement, stock-shortage reason, explicitly selected replacement rate, full-line eligible base, original calculation policy, Hybrid scope/policy version, amount and allocations separately from P and I. Unsupported cases remain blocked by the confirmed rules rather than awaiting an invented alternative. This approval resolves the limited forward-sale design; it does not resolve return/recovery of replacement benefits or authorize implementation/activation.

## Existing monetary promotion conflicts: approved blocking policy

When an applicable automatic `PERCENTAGE` or `FIXED` monetary promotion conflicts with Hybrid V1, reject Hybrid checkout with a clear product/promotion conflict message. Do not suppress, replace, stack, edit or deactivate the offer. In initial Hybrid V1, applicable automatic monetary offers on a requested product are conflicts; never guess that a zero manual selection permits an unmodeled automatic benefit. Mixed-oil remains separately excluded.

Preserve `Promotions::quote()` authoritative active-rule resolution, `Promotions::save()` overlap protection and legacy `PromotionCalculator::calculate()` percentage/fixed arithmetic. Historical policy snapshots and legacy financial behavior remain unchanged. Revalidate current offer revisions at preview and issue, and reject stale review fingerprints after an eligibility change.

## Earlier designs and operational rules

The [earlier benefit design](promotion-benefit-design-conflict.md) and [pilot release runbook](promotion-pilot-release-runbook.md) describe the separate `PROMO_CHOICE_V1` three-mode policy. Its invoice-wide exclusivity and single-representative pilot do not define the new Hybrid V1 requirements. Preserve old policy versions and snapshots rather than reinterpreting existing transactions.

The [mixed-oil report](mixed-oil-promotion.md) contains historical operational product quantities. Preserve that evidence and existing promotion names, status, revisions, quantities and dates. Do not update active rules merely to align examples with this specification. Future publication of corrected rules requires separate operational authorization and immutable revisions under existing safeguards.

## Financial holds and activation boundary

Leave current configuration unchanged, including the disabled feature switch, operational database allowlist, representative pilot configuration, posting hold and independent historical-return switches. Eventual all-representative availability does not authorize widening existing allowlists now.

No live checkout, API posting, migration, invoice rewrite, stock/payment adjustment, public deployment or network change is authorized. Separate owner authorization is required before activating live posting or lifting any existing financial hold.

## Acceptance criteria for later authorized implementation

Verify all six free-quantity examples above plus Glucose 5/8 and Suwada Kudu 11/18, incomplete groups, independent remainder selections and free units excluded from qualification. Verify initial applied 0%, explicit 25/30/35 selections, Other Discount at 0%/50%, invalid/negative/nonnumeric/above-50 rejection on client and server, and no automatic 25% application. Verify segment half-up rounding, whole-invoice sequential allocation, exactly-50% cap acceptance and over-cap rejection without automatic reduction. Verify actual free value is excluded and replacement money included in the cap, including mixed baskets and forged totals.

Test the approved complete-entitlement substitution, explicit existing rate selection, full-line bases, multi-line aggregate rounding and unrelated free benefits preserved. Reject remainder-bearing lines, discounted remainders, partial-entitlement shortages, absent replacement selections, ambiguous eligibility and incompatible calculations. Test paid-stock shortage, no free/replacement double award, percentage/fixed promotion conflict errors, mixed-oil exclusion, stale reviews, concurrent stock changes, duplicate/replayed requests, transaction rollback, payments/credit/voids and return reconciliation. Include strict odd-cent cap checks: subtotal 100.01 accepts discount 50.00 and rejects 50.01, without clamping. Use multiple authorized representatives, inactive/unauthorized accounts and cross-customer/stock-source attempts. Verify online-only rejection preserves legacy signed bodies, queues and encrypted storage. Compare immutable historical snapshots and active rules before/after isolated rehearsals.

Verify mobile dropdowns and errors in Sinhala/English, component-rich preview, receipt and invoice summary; render 58mm/80mm/A4/PDF from snapshots. Physical Bluetooth printing needs separate hardware acceptance. Run PHP lint, foundation/database tests and relevant existing promotion, invoice, report, offline and document regressions in authorized isolated test environments. These are future tests, not results claimed by this design update.

Linked-return acceptance must cover Glucose 6+1 returning 2 paid plus mandatory free recovery; block missing free recovery; repeated threshold crossings without double recovery; original-net values despite changed current prices/offers; direct access for multiple authorized representatives and denial after reassignment/deactivation; separate Saleable/Quarantine/Unsaleable counts for paid and free receipts; only Saleable quantities increasing available stock; debt-first settlement, actual-money-backed Refund Due, no cash payout and no transferable credit. Verify identical replay, changed-payload rejection, concurrent returns/payments/voids, rollback, cumulative quantity/cent exhaustion, immutable originals and unchanged historical-return flags/intents. Apply A1-E1 acceptance checks below; noncash and exceptional unresolved accounting cases fail closed. These are future isolated tests; none is executed by this documentation update.

## Requirement-to-code mapping and proposed changes

Source inspection confirms reusable generic Buy-X-Get-Y calculations in `app/Services/PromotionCalculator.php`, decimal allocation in `app/Services/InvoiceDiscount.php`, atomic paid/free stock posting in `app/Services/Invoices.php`, and immutable read-only document loading in `app/Services/InvoiceDocuments.php`. Hybrid itself is not implemented. Proposed changes, not authorization:

| Requirement | Existing files requiring integration or review | Proposed addition |
| --- | --- | --- |
| Segments, independent rates, sequential stages and cap | `app/Services/Invoices.php`, `app/Services/BenefitPolicy.php`, `app/Services/InvoiceDiscount.php`; reuse `PromotionCalculator.php` helpers | Separate `app/Services/HybridPromotionPolicy.php`, versioned `HYBRID_PROMOTION_V1` calculation |
| Offer resolution and mixed-oil rejection | `app/Services/Promotions.php`, `app/Services/MixedOil.php` integration boundary | Explicit Hybrid compatibility validation; retain legacy rule behavior |
| All-representative access and separate gate | `app/Controllers/InvoiceController.php`, `app/Services/Invoices.php`; preserve `config/benefit-modes.php` | Separate `config/hybrid-promotions.php` with activation and posting authorization off initially |
| Independent dropdowns, previews and errors | `app/Views/invoices.php`, `invoice-line.php`, `invoice-benefit-fields.php`, `invoice-discount-fields.php`, `invoice-review-lines.php`, `invoice-reviewed-totals.php`; `public/assets/js/invoices.js`, `invoice-benefit.js`, `invoice-discount.js`, `pos.js`, `benefit-simulation.js` | Hybrid controls and preview integration, clearly separate from the nonfinancial simulator |
| Historical display, receipts and PDF | `app/Services/Invoices.php`, `app/Services/InvoiceDocuments.php`, `app/Views/invoice-document.php`, invoice detail in `app/Views/invoices.php` | Snapshot-based Hybrid component rendering; preserve old-policy rendering |
| Reconciliation, exports and offline boundary | `app/Services/Reports.php`, `bin/verify-invoices.php`, `app/Services/OfflineSales.php` | Policy-specific component/cap reconciliation and explicit unsupported-Hybrid rejection |
| Language, styles and public assets | Existing localization catalogue/build inputs, `public/assets/i18n/catalogue.js`, `public/assets/css/pos.css` and asset-version references as needed | English/Sinhala labels and responsive controls; no private service-worker caching |

No stock-ledger identity or numbering redesign is proposed. Stock services remain reusable if each product's aggregate paid plus actual free demand is computed once. Existing duplicate-product rejection makes internal segments preferable to duplicate invoice lines. Later implementation must reflect the approved sale and return rules, with unresolved linked-return cases blocked.

## Proposed payload and immutable schema

Use a new versioned request containing stable product IDs, total paid quantities, an independent product/remainder discount choice and optional custom rate per product, the whole-invoice selection, request identity and review fingerprint. Replacement requires an explicit existing rate selection and affected-line identification, independently validated by the server against the approved limited scope. Server derives eligibility, quantities, current price/rule revisions, all amounts and the strict cap result. Reject unsupported policy fields, duplicate products, forged allocations/totals and offline Hybrid requests. Bind all accepted financial terms to review and exactly-once posting identities.

Propose additive invoice and line/segment snapshot tables for Hybrid, rather than relabeling old benefit records. Migration 024 constrains policy to `PROMO_CHOICE_V1` and its three exclusive modes; migration 016 constrains its policy to `INVOICE_PERCENT_V1`. Preserve both deployed migrations unchanged.

Snapshot fields must reproduce: original paid subtotal; policy and rounding versions; actor; price/promotion revisions; total and allocated paid quantities; complete groups; free entitlement and actual free quantity; remainder quantities; each selected product rate/base/amount; replacement entitlement/rate/base/amount/policy and allocations where approved; sequential invoice rate/base/amount/allocations; combined monetary discount and cap basis; final merchandise payable. Add foreign keys, uniqueness, nonnegative/rate/allocation constraints and immutable update/delete protection. Validate cross-row sums, stock and cap in the server transaction and reconciliation. Reports must not count actual free value as monetary discount or lose replacement components.

Linked returns additionally need append-only references to original Hybrid invoice/line/benefit allocations, original and return stock movements, prior return quantities and value, paid/free unit attribution, recovered entitlement, physical receipt and disposition, condition reason, actor, policy version, request identity/payload hash, debt reduction and Refund Due. This is a schema proposal only. Preserve original invoice totals, payments and numbering; do not reuse or modify historical-return flags, intents or snapshots.

## Owner-approved linked partial-return policy

**Historical numerical examples below:** examples that stack invoice discount onto free/product/replacement-benefit lines describe the pre-27-November policy and are superseded for new invoices. Preserve their original-net amounts only when consuming existing immutable snapshots; never recalculate historical invoices. Under the current rule, Glucose 8 with remainder discount 50 has net 750 (first two remainder positions 75 each; qualifying positions 100 each). Replacement gross 1,200 at 25% has net 900 (two returned units credit 150, retained discount 250). The old retained-cap example relying on invoice allocation to a replacement line is superseded; the retained-cap rule itself remains mandatory. A synthetic replacement rate 60% gives retained R=720 on gross 1,200 and still demonstrates that failure without stacking. Return, custody and noncash safeguards below remain unchanged.

The following five decisions are confirmed. They authorize design only; linked Hybrid returns are not implemented or activated by this document.

### 1. Recover excess actual free units

After a paid return, recalculate retained free entitlement from the **original promotion revision** and the cumulative remaining paid quantity. Recover actual free units that exceed that entitlement. Do not use the current promotion offer, price or stock availability.

For an actual-free-only line, let Q be original paid units less cumulative accepted paid returns, F the original actual free units less cumulative physically accepted free returns, and X/Y the original threshold/reward. Required recovery is `max(0, F - floor(Q / X) × Y)`. Check this against original quantities, prior returns and the current physical receipt. Replacement entitlements are not actual free goods; their monetary allocations follow A1, not a current-stock or additional-clawback calculation.

Example: Glucose 6 paid plus 1 free; returning 2 paid leaves 4 paid and zero retained entitlement. The free unit must also be physically returned and classified. If it cannot be recovered, block financial posting and use controlled pending custody/evidence under E1. Manager review may investigate or reject; it cannot waive recovery, set a recovery price, write off the requirement or impose a cash charge. A physically recovered unit may be saleable, quarantine or unsaleable; condition does not erase the receipt or recovery record.

### 2. Value returned units from original net allocations

Use immutable original paid-unit/segment amounts, including original product, replacement and allocated sequential whole-invoice discounts. Never use current prices or current offers. A1 reverses only the returned units' original net allocations and imposes no additional clawback of replacement money allocated to retained units.

Original invoice gross and discount totals remain unchanged. Record a linked monetary reversal/credit with separately reversed components. Free units have no standalone cash refund value. Do not reclassify retained units into newly discounted remainder quantities or reapply invoice percentages to invent a new return value.

Example: Glucose 8 paid at LKR 100, with 6 qualifying units and 2 remainder units explicitly discounted 25%. Product discount is 50; post-product subtotal 750; invoice discount 10% is 75; original payable is 675. B1 consumes the two original discounted-remainder positions first: their original gross is 200, product discount 50 and allocated invoice discount 15, giving credit 135. Six paid units remain, so the original 1 free unit remains entitled. Representatives cannot select a different attribution to increase credit.

### 3. Authorized representatives may post eligible linked returns

All active, authorized Sales Representatives are intended direct users of eligible Hybrid invoice returns, subject to server-side authorization, current customer assignment, original-invoice scope and validity, cumulative quantity/value limits, stock rules and transactional integrity. No single-representative allowlist or routine manager approval is implied. Recheck access at review and posting; reject deactivated users, unassigned customers, unrelated invoices, forged quantities and stale reviews.

Use a separate linked-Hybrid return authorization path. Do not alter `HistoricalCustomerReturns`, historical-return flags/allowlists, approved prices or pending return intents. Missing-free or unresolved-accounting cases remain exceptions requiring separate resolution, not bypasses of ordinary checks.

### 4. Record receipt and controlled stock disposition

For every physically received paid or free unit, the representative records a mandatory condition: **Saleable**, **Quarantine** or **Unsaleable**. The proposed form records product and original line, paid/free quantities, quantity in each condition and a condition reason. The server derives authorized products/source from the original invoice; it never trusts submitted stock balances or location ownership. Counts in all three conditions must sum exactly to physically received paid/free quantities, with no duplicate unit attribution.

Only the accepted Saleable quantity may add to available-for-sale stock, through the existing controlled stock-posting transaction and ledger. Quarantine/Unsaleable quantities require separately identified non-saleable custody/disposition records; they must not enter representative or warehouse saleable balances. Do not implement them as a saleable receipt followed by a future adjustment. Link all movements/receipt records to the return and original movement, preserving paid/free traceability and actual physical location.

For the Glucose 2-paid-plus-1-free recovery example, receiving 1 paid Saleable, 1 paid Quarantine and 1 free Unsaleable increases available-for-sale stock by **1**, not 3. All 3 received units remain traceable. E1 requires manager authorization, documented reasons and auditable movements for quarantine release, reclassification and disposal. Representatives' classification/direct-return permission does not grant those manager actions. Physical routing and storage controls must enforce this separation; they are implementation requirements, not permission to expand existing roles or stock availability.

### 5. Settle against original debt, then record Refund Due

Apply approved return credit first against the original invoice's current outstanding receivable under transaction locks. Record any eligible excess as **Refund Due**, reconciled against actual collected money and amounts already reserved/settled by earlier returns. Never double-count a previous Refund Due or payment allocation. Do not automatically issue cash, rewrite original payments or create transferable customer credit.

Example: approved linked credit 135 against original outstanding 100 cancels 100 of debt and records 35 Refund Due, provided 35 is supported by reconciled collected-money capacity. If the invoice is fully cash-paid and sufficient capacity remains, record 135 Refund Due. If the accounting cannot reconcile, block rather than invent cash or reusable credit. Existing noncash-settlement cases remain blocked until their treatment is approved.

### Transaction and exactly-once safeguards

Each return must link to its original Hybrid invoice, benefit snapshots, stock movements and previous returns. Lock the relevant invoice/customer, return-consumption state and stock under the approved shared lock order; revalidate quantities, recovered free goods, condition totals, available refund capacity and current access before committing. Commit linked credit, controlled stock/custody handling, refund liability and audit together. Concurrent return/payment/void requests must serialize against that same state.

Use a stable return request UUID and immutable payload hash. A replay of identical committed terms returns the same result without consuming quantities or changing stock/money again; a changed payload with the same identity is rejected. Cumulative accepted paid and free returns cannot exceed original delivered quantities. Track entitlement recovery once across repeated returns. Never use current free-stock availability to reconsider an original replacement benefit. Any unresolved benefit-recovery or accounting conflict must fail closed.

## Final owner-approved return edge cases: A1-E1

These decisions supersede the earlier open-choice wording. They apply only to invoices issued under the new Hybrid policy; do not silently extend them to legacy invoices. Preserve the five core principles above and all original invoice totals, numbering, payments and immutable benefit snapshots.

### A1 — Original-net replacement reversal, no retained-unit clawback

Reverse only the returned paid units' original immutable net monetary allocations, including their allocated replacement and sequential invoice discounts. Do not impose an additional clawback of replacement discount belonging to retained units, invent a charge, or re-evaluate original replacement eligibility using current free-stock availability. Retained replacement allocations remain unchanged. The resulting retained basket must independently pass C1; a cap failure blocks the return rather than authorizing a clawback.

Example: Suwada Kudu 12 paid at 100, complete free entitlement replaced at explicitly selected 25%, then invoice discount 10%: gross 1,200, replacement 300, invoice discount 90, net 810. Returning 2 original paid positions reverses gross 200, allocated replacement 50 and invoice discount 15, for net credit 135. Retained gross 1,000 and monetary discount 325 satisfy C1. No additional retained-unit replacement recovery applies. If fully cash-paid with reconciled capacity, record 135 Refund Due; 2 saleable physically received units add 2 to saleable stock. No free stock is restored because none was delivered.

### B1 — Remainder-first positions and exact original cents

Consume original **discounted-remainder positions first**, then original **promotion-qualified positions**, in a stable deterministic original position order. Positions with a selected 0% remainder rate still retain their original remainder classification. Representatives cannot select a higher-valued segment instead. Preserve the original classification: do not turn retained qualifying units into newly discounted remainder units after a return.

Allocate and persist original gross, product, replacement, invoice-discount and net component cents to unit positions once under a versioned deterministic algorithm at issuance. Use integer quotient/residual-cent allocation with a fixed original-position tie order and bounded component allocations, so each unit has nonnegative net and `gross - product - replacement - invoice = net`. Segment, line and invoice component totals must reconcile exactly. Do not independently redistribute or re-round the same component for each return. Physical persistence may use compact deterministic ranges for large quantities, provided each logical position and its original cents are immutable and reproducible.

Track consumed positions and cumulative component reversals transactionally. Equivalent combined and repeated returns must consume the same positions and total cents. Never consume a paid/free quantity twice or reverse more of any component than originally allocated. In a complete accepted reversal, no original reversible cent may be left behind or duplicated.

Example: Glucose 8 at 100 with 25% on the 2 remainder units and 10% invoice discount has original net 675. First and second paid-unit returns consume remainder positions worth 67.50 each. A third consumes a qualifying position worth 90 and requires recovery of the free unit because 5 paid units remain. With all received goods saleable, stock increases are respectively 1, 1 and 2; cumulative credit is 225, settled debt-first. For residual-cent illustration, an original segment net of 135.01 allocated over 2 equal positions records 67.51 and 67.50 once, rather than rounding each repeated return to the same amount. Component allocation must also satisfy the nonnegative unit-level reconciliation above.

### C1 — Strict retained monetary cap, no manager override

Before linked-return financial posting, derive retained original paid gross S_retained and retained product, replacement and invoice monetary allocations D_retained from unreturned original positions. Require exact integer-cent `2 × D_retained <= S_retained`. Actual free-unit notional value is excluded. Where no paid units remain, both retained gross and retained monetary allocations must be zero; outstanding physical free recovery still applies.

If this fails, block the linked financial return and refer it for manager review. Do not silently drop requested lines, reprice retained units, reduce original-net return credit, introduce a recovery charge, or override the cap. Any exceptional resolution requires a separately approved policy. A representative may submit a different explicitly reviewed request, but the server must never choose one automatically. Pending physical custody under E1 does not bypass the financial block.

Example: Glucose 8 at 100 with actual free 1 and remainder discount 50, plus Suwada Kudu 12 at 100 with approved replacement 600, followed by invoice discount 135, gives original gross 2,000 and combined discount 785. Returning all Glucose paid/free goods would leave Suwada gross 1,200 with retained replacement 600 plus invoice allocation 60. Since `2 × 660 > 1,200`, block: no financial credit/refund or saleable return-stock posting; controlled custody may record receipt. The otherwise original-net Glucose credit 675 must not be reduced to manufacture cap compliance.

### D1 — Block existing noncash settlements

Block Hybrid linked returns involving an existing noncash settlement until the owner separately approves settlement-allocation and residual-void accounting. Do not convert historical/noncash settlement into cash Refund Due or transferable customer credit, revive forfeited value, or mutate historical records/intents/permissions. The block itself is the approved V1 behavior; implementing a new noncash allocation is not a prerequisite to safely rejecting such cases.

Example: Glucose 6 paid at 100 plus free 1, invoice discount 10%, net 540, settled by historical noncash 200 and cash 340. A request to return 2 paid plus required free 1 has an original-net calculation of 180 but must be blocked: no posted credit, Refund Due or saleable stock movement. Do not assume either cash-first or proportional noncash allocation. Controlled receipt evidence/custody may be recorded under E1 without financial acceptance.

### E1 — Missing free goods, controlled custody and manager stock actions

Recover excess actual free units under the original revision. Missing required free goods block financial return posting. Authorized representatives may record the exception, physically received quantities, condition and receipt evidence in controlled **pending custody**, linked to the original invoice and request. Pending custody does not consume financial return positions, reduce debt, create Refund Due or increase available-for-sale stock. It must reserve/track the physical receipt so later resolution cannot count it twice. Keep any custody identity separate from the immutable financial posting request if receipt evidence changes before a newly reviewed posting.

Managers may investigate or reject the exception, but cannot silently waive free recovery, issue an unapproved refund or invent a charge. Approval of manager review is not approval of a new exception settlement. Once missing goods are recovered and all validations pass, representatives may directly post the eligible return under normal authorization. Record only the transition from custody to the accepted disposition; do not receive the same goods again.

Every received paid/free unit is classified Saleable, Quarantine or Unsaleable. Only accepted, approved Saleable returns increase available-for-sale stock. Quarantine release, reclassification and disposal require **manager authorization**, documented reasons and auditable stock movements. Separate custody/stock permissions, verified physical routing and server-controlled transitions must prevent a representative's classification entry from becoming a bypass. Rejected or unresolved goods remain in controlled custody until an authorized recorded handover/disposition; rejection does not make them saleable automatically.

Example: Glucose 6 paid at 100 plus free 1, fully paid 600. Presenting 2 paid units without the required free unit gives pending custody only: zero posted credit/refund and zero saleable-stock increase. After the free unit is recovered, receiving 1 paid Saleable, 1 paid Quarantine and 1 free Unsaleable yields net credit 200 and, with cash-backed capacity, Refund Due 200; only 1 unit enters saleable stock. The other 2 stay under their controlled dispositions, with no new cash payout.

### Residual full void and final-return safeguards

A later full void must operate on **remaining unreturned original positions**, remaining monetary entitlements and reconciled cash liability. Do not restore stock, cancel debt or create Refund Due a second time for quantities/value already accepted in linked returns. Preserve prior returns, original invoices/payments and all request identities. Coordinate return, payment and void locks/idempotency, and distinguish physically received Saleable versus non-saleable goods; voiding must not release quarantined/unsaleable custody into saleable stock. Existing full-void code needs a Hybrid-specific cumulative-return integration, not a silent change to legacy void semantics. Noncash/accounting conflicts remain blocked under D1 pending the separate residual-void policy.

A1-E1 acceptance requirements: test no additional replacement clawback; current-stock independence; remainder-first order including 0% segments; unit-cent/component reconciliation over repeated and equivalent combined returns; duplicate/concurrent position consumption; retained-cap equality, excess, odd cents and zero retained gross; noncash rejection; missing-free custody without financial/saleable effects; receipt-to-posting transitions without double counting; unauthorized manager actions; controlled release/reclassification/disposal; and subsequent full void after returns without repeated stock or money reversal. All future verification uses authorized isolated fixtures. No test is executed by this documentation approval.

## Ordered implementation plan, pending separate authorization

| Stage | Proposed work and exit requirement |
| --- | --- |
| A — Server calculation | After separate implementation authorization, implement approved sale rules, five core return principles and A1-E1, including original unit allocations, retained cap and residual void safeguards. Explicitly excluded cases fail closed. Independent decimal oracles must reconcile every component. |
| B — Payloads and snapshots | Review versioned inputs and additive schema; rehearse migrations and constraints only in a newly named isolated schema. Preserve existing migration checksums, snapshots, identities and legacy rendering. |
| C — Interface | Implement independent dropdowns initially at 0%, manual custom validation, server previews, clear excess-cap messages and separate all-authorized-representative visibility. No financial activation. |
| D — Printing and reports | Display paid/qualified/remainder/free quantities, all rates and amounts, replacement basis, combined amount/percentage and payable in preview and Sinhala/English documents. Reconcile reports to stored components. |
| E — Isolated testing | Execute the acceptance matrix above plus legacy regressions, multiple-user scope, concurrency, rollback, offline rejection and document rendering. Physical printing remains a separately recorded hardware check. |
| F — Readiness only | Review migrations, verified backup and newly named isolated restore, checksum/constraint and financial reconciliation, rollback procedure and device/client compatibility. Present a concrete activation request; do not enable or post automatically. |

## Implementation readiness and genuine remaining exclusions

**Resolved by the three latest owner decisions:** strict cents-based cap including odd-cent boundaries; blocking applicable existing automatic percentage/fixed promotion conflicts; and the limited complete-group, complete-entitlement replacement using existing explicit rate selection and full-line calculation. Do not reopen those decisions or interpret them as activation approval. Outside that replacement scope, fail closed.

**Resolved by the five latest return decisions:** mandatory recovery of excess actual free units, original-net valuation, direct authorized-representative access, mandatory Saleable/Quarantine/Unsaleable classification, and original-debt-first settlement with reconciled Refund Due. These are confirmed rules, not questions to reopen. No cash payout or transferable credit is authorized.

**A1-E1 are now resolved:** original-net replacement reversal without retained-unit clawback; deterministic remainder-first positions and original cents; strict retained cap with blocked exceptions; noncash linked-return blocking; and missing-free custody plus manager-controlled disposition. No further decision on these rules or the five core principles is required. They are ready to serve as implementation requirements once implementation is separately authorized.

**Only these exceptional cases still need a separate business policy before they can post:**

1. **Noncash settlement allocation and residual-void accounting** if support is requested beyond D1 rejection. Neither cash-first nor proportional settlement allocation is approved. Existing historical noncash records, approved prices, permissions, allowlists and pending return intents remain unchanged.
2. **Exceptional resolution of retained-cap failures or unrecovered free goods** if anything beyond investigation/rejection/custody is requested. Managers cannot waive C1/E1, invent a charge or change original-net return credit. Such financial resolutions need explicit separate approval; they are not part of initial Hybrid V1.

These are deliberate fail-closed exclusions, not blockers to implementing the approved ordinary path and its rejection behavior. Do not extend the rules to legacy invoices. No other unresolved financial-policy decision is inferred by this documentation update.

**Engineering/readiness work remains:** specify and verify the bounded per-unit component-cent algorithm and immutable ranges/consumption constraints; design atomic linked-return/refund/custody ledgers and residual full-void integration; enforce representative versus manager permissions and actual custody routing; implement version-aware payloads, reports and documents; rehearse additive migrations/restore and execute isolated acceptance/regression tests. Those are technical deliverables, not requests to decide approved business principles again. Existing `Invoices::void()` restores full original paid/free stock and must not be used unchanged after Hybrid partial returns. Existing `HistoricalCustomerReturns` remains a separate workflow, not the linked-Hybrid return engine. No implementation, migration or release authorization is given here.

Pooled mixed-oil exclusion, initial 0% product selection, all-authorized-representative intent, free-value cap exclusion and replacement-money cap inclusion remain settled requirements. Separate implementation, migration, isolated verification, activation and posting authorization are still outstanding readiness gates, not new financial policy choices.

Keep Hybrid separate from the existing pilot gate and temporary posting hold. Authorize active representatives through existing role/customer/own-stock/credit checks in form, preview and issue; do not hardcode names, user IDs or a single-user allowlist. Require explicit separate owner approval for activation and posting authorization after readiness checks. No gate change is made by this document.

Rollback planning: disable new Hybrid selection/posting while retaining completed snapshots, audits and financial records; never remove tables or reverse business entries merely to roll back code. Retain version-aware historical reads and exact idempotent replays. Back up before any later authorized DDL, test restores only in newly named schemas, and preserve original backup bytes. No port-3306 access, deployment/network change, vault reset, queued-payload rewrite or unrelated migration repair is included. Migration 007 remains outside this specification's work; do not rewrite or bypass deployed migrations.
