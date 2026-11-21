# Promotion Benefit Modes — Design and Conflict Report

**Status: design update only.** Owner decisions: preserve the ordinary whole-invoice percentage discount as a separate, mutually exclusive third benefit mode; exclude MIXED_OIL_V1 from alternative promotion discounts; suppress monetary product promotions in both discount modes. No implementation, migration, activation, operational data change, or historical-return configuration change is authorized by this document. The earlier Design & Conflict Report was delivered in the conversation; this file records its updated policy and unresolved decisions.

## Confirmed invoice-level modes

| Mode | Free promotional units | Percentage scope | Stock deduction |
| --- | --- | --- | --- |
| `FREE_PROMOTION` | Apply qualifying existing free-product promotions. | Invoice-level percentage must be 0%; no alternative promotion discount. | Paid plus actual free units. |
| `PROMOTION_DISCOUNT_INSTEAD` | Suppress all free promotional units. | 25%, 30%, 35% or Custom only on paid lines that meet their original qualifying free-promotion threshold. No additional ordinary invoice discount. | Paid units only. |
| `ORDINARY_INVOICE_DISCOUNT` | Suppress all free promotional units. | Existing whole-invoice percentage basis and 0%, 25%, 30%, 35% or Custom validation. No additional alternative promotion discount. | Paid units only. |

Each new invoice must have one valid mode. The mode is invoice-level and does not depend on client-supplied eligibility or promotion identifiers. **Owner-confirmed Custom policy:** retain the existing 0% to 100% range and maximum two decimal places for both `PROMOTION_DISCOUNT_INSTEAD` and `ORDINARY_INVOICE_DISCOUNT`; offer Custom alongside the existing presets. Do not add a representative-specific cap or mandatory administrator approval. The server must apply the existing rate validation. `FREE_PROMOTION` requires a 0% invoice percentage.

**Resolved conflict:** current `INVOICE_PERCENT_V1` applies after automatic monetary promotions and can coexist with free units. For newly issued invoices under the proposed exclusive policy, both discount modes suppress free rewards and percentage/fixed monetary promotions before calculating the invoice discount. Ordinary mode retains the existing whole-invoice percentage algorithm, rounding and proportional allocation; because monetary promotions are suppressed, its base reflects the paid merchandise amounts after that suppression. Existing posted invoices and signed offline payloads retain their original meaning.

## Owner decision: mixed-oil treatment (resolved)

For the initial three-mode policy, **MIXED_OIL_V1 is excluded from promotion-discount eligibility**. Pooled mixed-oil quantities never establish eligibility or receive alternative-discount allocations. Independently qualifying ordinary Buy-X-Get-Y product lines on the same invoice may receive the alternative discount. Both discount modes suppress every mixed-oil reward. No ordinary invoice discount is added to the alternative, and no free reward is stacked with either discount mode.

| Invoice | FREE_PROMOTION | PROMOTION_DISCOUNT_INSTEAD | ORDINARY_INVOICE_DISCOUNT |
| --- | --- | --- | --- |
| Mixed-oil products only | Use existing pooled qualification and reward selection; deduct paid plus selected free units; invoice percentage 0%. | Suppress mixed-oil rewards; mixed-oil-only lines receive zero alternative discount, regardless of pooled quantity; deduct paid units only. | Suppress rewards; apply existing whole-invoice percentage basis and validated rate to paid lines; deduct paid units only. |
| Mixed-oil, independent qualifying Buy-X-Get-Y and nonpromotional products | Apply existing eligible rewards and monetary promotions under existing conflict rules; deduct paid plus actual free units; invoice percentage 0%. | Suppress all rewards and monetary product promotions. Only independently qualifying ordinary Buy-X-Get-Y paid lines enter the alternative-discount base; mixed-oil-only and nonpromotional lines receive zero alternative discount; deduct paid units only. | Suppress all rewards and monetary product promotions. Apply the ordinary whole-invoice percentage on its existing basis, including applicable paid lines; no alternative discount; deduct paid units only. |

A product simultaneously participating in an active mixed-oil campaign and an ordinary Buy-X-Get-Y campaign is a **conflict**, not an independently qualifying line to select automatically. Existing promotion publication rejects overlapping enabled offers for the same product/date, and checkout blocks multiple eligible rules. Preserve that fail-closed behavior; do not choose a campaign, combine pooled quantities or stack benefits silently. An explicit owner decision and reviewed policy would be required to support such overlapping campaigns.

FREE_PROMOTION retains the current mixed-oil threshold, grouping, reward-choice and paid/free stock accounting. Discount modes do not grant mixed-oil free units even when pooled quantity exceeds the threshold. Eligibility exclusions and actual/suppressed quantities should be recorded in the immutable benefit snapshot proposed below.

## Owner decision: monetary promotions and anti-stacking (resolved)

In FREE_PROMOTION, retain existing eligible percentage/fixed product promotions, ordinary free-product promotions and MIXED_OIL_V1 rewards under existing precedence, eligibility and conflict rules; invoice percentage is 0%. Do not invent additional combinations between offers. In PROMOTION_DISCOUNT_INSTEAD and ORDINARY_INVOICE_DISCOUNT, suppress all free rewards and all percentage/fixed monetary product promotions before computing the selected invoice-level discount, including when the selected percentage is 0%. Only paid stock is deducted.

Alternative-discount eligibility comes only from an independently applicable ordinary Buy-X-Get-Y rule whose original paid threshold is met. A line qualifying solely for a percentage/fixed monetary promotion or through MIXED_OIL_V1 contributes zero to the alternative base. Ordinary mode applies the existing whole-invoice percentage calculation to the paid merchandise basis after monetary promotions have been suppressed; it does not add an alternative discount. Both discount modes derive results server-side and reject forged stacked amounts, scopes or free rewards, including through offline sync and direct APIs. Conflicting active rules for one product remain blocked under the existing fail-closed policy.

Illustrative mixed invoice: paid amounts of LKR 1,200 on a threshold-qualified Buy-X-Get-Y line, LKR 1,000 on a monetary-promotion-only line, LKR 800 on a mixed-oil-only line and LKR 500 on a nonpromotional line give a LKR 3,500 paid subtotal. In alternative mode at 25%, suppress all free and monetary benefits; only the LKR 1,200 qualifies, so the discount is LKR 300 and net merchandise is LKR 3,200. In ordinary mode at 25%, suppress all those product benefits and apply 25% to LKR 3,500: LKR 875 discount and LKR 2,625 net. In free mode the invoice percentage is 0%; apply only existing eligible free and monetary offers under current conflict rules, so the final amount depends on the actual monetary rule. These values are examples, not operational prices.

After merchandise pricing, any approved historical return remains a separate noncash settlement. Cash, credit, outstanding, line/report allocations, snapshots, receipts, PDF and voids use the resulting recorded amounts and preserve immutable history.

## Proposed server calculation

1. Authenticate the actor, authorize the customer and stock source, validate the selected mode/rate, and acquire existing shared gates and locks. Resolve current active promotion revisions using the authoritative promotion service, including its current fail-closed overlapping-rule handling.
2. In free mode, calculate existing eligible monetary promotions and actual qualifying free units under existing rules; the invoice percentage must be zero. In both discount modes, suppress all percentage/fixed monetary promotions and every free reward, including mixed-oil rewards, before calculating invoice discounts; require stock only for paid units.
3. For promotion-discount mode, exclude MIXED_OIL_V1 and identify each eligible paid line using an independently applicable, ordinary Buy-X-Get-Y promotion and its paid-quantity threshold; nonpromotional, below-threshold and unsupported/ambiguous promotions receive zero alternative discount or are rejected according to the final approved policy. Sum the eligible paid subtotals. Compute **one** invoice alternative discount as eligible subtotal times the validated rate, using exact cents and half-up rounding. Allocate that rounded amount proportionally across eligible lines using largest remainders; original input line order breaks ties. Noneligible lines have zero allocation. Never apply a second whole-invoice discount.
4. For ordinary mode, retain `INVOICE_PERCENT_V1`: apply the selected rate once to all paid merchandise amounts after suppressing monetary promotions and free rewards, using the existing whole-invoice basis and rounding algorithm; round half-up to invoice cents, and allocate proportionally using largest remainders and original-order ties. Do not apply an alternative discount. For free mode, no invoice-level percentage applies.
5. Derive merchandise net from the line results and verify header/line reconciliation. Apply any separately authorized historical-return settlement only afterward: applied = min(approved return, net merchandise); forfeited = approved minus applied; remaining payable = net merchandise minus applied. No return amount becomes a discount, reusable credit or cash refund.
6. Calculate actual cash applied/change and credit outstanding/limits using remaining payable; validate stock and confirmation fingerprint, then issue invoice, line snapshots, payment, inventory and authorized return atomically. Retry with the same identity must remain exactly once; reject changed payloads and stale promotion or price reviews.

The P012/P013 examples assume a live applicable Buy 12 rule. For a 12-unit threshold, quantities 6, 12 and 24 are respectively ineligible, eligible and eligible. Resolve the real rule rather than hard-code product codes. Example A gives eligible subtotal LKR 2,400, 25% alternative discount LKR 600 and net merchandise LKR 3,640. Example B gives LKR 4,080, LKR 1,020 and LKR 4,060 respectively. These are illustrative, not operational prices.

## Minimal versioned persistence and API plan

Preserve deployed migrations and all existing invoice and discount snapshots. Add a checksum-verified forward migration only after separate implementation authorization and isolated rehearsal:

- Add an immutable invoice benefit snapshot keyed by invoice ID, containing the new policy version, one of the three modes, validated rate, eligible base, alternative/ordinary discount amounts, actor and timestamp, and the applicable existing rate-validation policy snapshot.
- Add immutable line-level benefit snapshots keyed to the invoice line, with product and promotion revision references, original threshold, paid quantity, eligibility/reason, eligible amount, allocated alternative discount, suppressed free units and actual free units. Record explicit zero alternative amounts for excluded lines. Keep existing invoice amounts and promotion snapshots authoritative for the sale.
- Keep ordinary-mode `INVOICE_PERCENT_V1` snapshots and existing proportional allocation semantics. A new mode snapshot distinguishes its free-suppression behavior without reinterpreting old `INVOICE_PERCENT_V1` invoices. The new alternative mode must not masquerade as an old whole-invoice snapshot: migration 016 constrains `policy_version` and current report reconciliation assumes its base covers all post-promotion payable lines. Extend reporting/reconciliation by version while retaining the old checks for legacy records.
- Extend online review/issue payloads with a recognized benefit policy/version and mode, rate and existing inputs. Server derives eligible lines, rewards and prices. Bind mode and all derived figures to the review fingerprint, signed/offline payload and immutable posting audit. Reject unknown modes, forged scopes, duplicate discount fields and unsupported combinations. Keep the transaction and existing lock order unchanged.

No new public or unauthenticated endpoint is required. Existing APIs, price/promotion revisions, invoice IDs, payment identities, stock ledgers and CSRF/customer/role checks remain authoritative. Unsupported old online clients must not silently default into a legacy stacking path when the new policy is mandatory; deployment and client version compatibility need an explicit approved cutover.

## Receipts, stock and reports

Add explicit benefit-mode and rate labels, eligible/excluded line indications, allocated alternative discount and ordinary discount to invoice review, immutable invoice details, receipts, A4/PDF and CSV as applicable. Continue to show noncash historical-return settlement separately from both discounts. Product report net values use actual line allocations, while invoice reports reconcile header totals; cash collections remain actual posted cash, credit remains outstanding after permitted settlements, and forfeited return value is never cash or a reusable customer credit. Explain management net sales after applied returns separately from statutory revenue/tax accounting.

Void and authorized refund controls use the original stored paid/free quantities and financial amounts, reverse only the original stock once, preserve snapshots and return disposition, and do not invent a cash refund for noncash settlement. Existing reports/reconciliation must distinguish old ordinary snapshots, new ordinary mode and new alternative mode without flagging valid legacy records.

## Offline compatibility and rollout

Keep encrypted IndexedDB, device registration and unsynced drafts intact. Introduce a versioned mode and capability in new grants/clients; calculate identical amounts locally using exact cents and include the chosen mode and authoritative inputs in the signed body. The server rechecks mode, grant, rules, promotion versions, quantities, prices, rate, allocations and stock before posting. Changed or unknown policies must be held for review without silently rewriting signed data or producing a second invoice.

Previously signed payloads cannot safely be reinterpreted as one of the new exclusive modes: preserve their original policy version, body and idempotency keys. Whether to accept unchanged legacy payloads under their original agreed rules or hold them for explicit review at cutover is an owner decision. Old online requests also need an explicit cutoff/version policy; do not create a bypass around mandatory benefit selection. Historical returns remain online-only.

## UX design

Place the three choices in an invoice-level control with clear Sinhala and English labels. Keep customer, selected products and paid quantities when changing modes. Show eligible versus excluded products, qualifying thresholds, chosen rate, line savings, actual/suppressed free units and the invoice total. Show the existing rate choices only where applicable; clearly display ordinary 0% where selected. Changing mode, rate or quantity invalidates review and requests a fresh server calculation. If a mode change invalidates free-reward selections, explain and preserve them only as non-posting UI state or ask the user to reselect; never silently post them. Do not clear offline saved drafts.

Use separate responsive cart/summary areas and test desktop and Redmi mobile in Sinhala and English, including long names, large amounts, keyboard navigation and touch targets.

## Isolated test matrix for future implementation

| Area | Required cases |
| --- | --- |
| Three-mode exclusivity | Each mode with 0/25/30/35/Custom as applicable; reject free plus percentage, alternative plus ordinary and forged mode/scope combinations. |
| Thresholds | P012/P013 at 6, 12 and 24; mixed eligible, below-threshold and nonpromotional lines; changed, disabled and expired promotions; conflicting rules. |
| Mixed-oil modes | Mixed-oil-only in all three modes; pooled quantities below/above threshold; independent Buy-X-Get-Y plus mixed oil and nonpromotional products; zero alternative allocation to mixed-oil-only lines; no mixed-oil rewards in either discount mode; conflicting overlapping campaigns fail closed. |
| Monetary anti-stacking | Fixed and percentage product promotions in free mode; suppression in both discount modes including 0%; mixed invoices; forged monetary/free/ordinary/alternative stacking through online and offline requests; conflicting rules fail closed. |
| Exact money | Eligible-only allocation, zero excluded allocations, half-up cent rounding, largest-remainder ties in original order, zero amounts, bounds and overflow; PHP/JS parity. |
| Authorization and rate validation | Existing 0% to 100% Custom range and up to two decimal places in both discount modes; boundary, invalid and malformed rates; no new representative cap or mandatory admin approval; inactive actor, customer scope, CSRF, forged product/promotion/quantity/rate and unexpected legacy-online payloads. |
| Inventory and accounting | Paid plus free in free mode, paid only in both discount modes, insufficient stock, cash/change, partial and credit limits, reports and financial/stock reconciliation. |
| Durability | Review staleness, rollback at each posting stage, same/different concurrent IDs, exactly-once retry, immutable snapshots and void/reversal. |
| Offline | Old encrypted and signed drafts unchanged; all new modes, changed rule/grant, signature tampering, retry, app updates and no vault clearing. |
| Documents and history | Receipts, Sinhala/English PDF, product and invoice reports/CSV, legacy invoices and mixed old/new reporting. |
| Historical returns | Separately permitted online return after each merchandise calculation; applied/forfeited/cash/credit, void and no carryforward credit; existing pilot and preview guards. |
| Responsive UI | Sinhala and English, 320–1920px including 393px Redmi, narrow cart containers, keyboard/touch, mode switching and draft preservation. |

## Owner decisions — RESOLVED 2026-11-21

1. **Previously signed offline invoices — RESOLVED (Decision A).** Unchanged valid legacy signed offline bodies post under their original agreed version after all existing checks; they are never silently recalculated. New benefit modes are online only: any offline synchronization payload carrying benefit fields is rejected (`409 Benefit modes require an online transaction.`), and a pilot representative's ordinary online checkout without a benefit selection is rejected (`409 This account must use the promotion benefit checkout.`). The pilot's legacy signed offline sync path is explicitly preserved.
2. **No benefit — RESOLVED (Decision B).** `ORDINARY_INVOICE_DISCOUNT` at preset 0% is the official no-benefit representation, labeled `0% — No Promotion or Discount`. At 0% all free units and monetary product promotions are suppressed, the snapshot records 0.00, and stock demand is paid quantities only. Preset 0% is rejected for `PROMOTION_DISCOUNT_INSTEAD`; custom 0 remains valid in both discount modes.
3. **Online cutover — RESOLVED (Decision C).** Single-representative controlled pilot first. Benefit modes require both the global `PROMO_CHOICE_V1` gate and membership in the `pilot_representatives` allowlist; membership is re-authorized server-side at issue time. Non-pilot representatives keep the legacy checkout unchanged and never see the benefit UI.

## Migration, rollback and safety

Any future implementation requires an additive versioned migration, verified checksums, a fresh verified backup and isolated restore, negative constraint tests, reconciliation and an independently reversible feature switch. Do not rewrite deployed migrations or alter old invoice snapshots. Rollback disables new mode selection/posting and retains all completed invoices, benefit snapshots, audit and physical return history; handling outstanding signed drafts requires the approved version policy. Do not reverse or delete financial records as a switch rollback.

The current historical-return financial switch, independent `nissanka` pilot setting, read-only preview, approved prices and existing return intents are not changed by this design. No public deployment is authorized. Stop until the owner separately approves implementation and the unresolved business choices needed for it.
