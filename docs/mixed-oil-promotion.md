# Mixed oil promotion — 15 November 2026

Implemented only the requested mixed-oil offer. Existing single-product settings could not combine quantities or select a different free product, so the existing Promotions, invoice and offline engines were extended.

## Live configuration

- Promotions: http://127.0.0.1:8088/admin/promotions
- Offer **M003**, internal ID 62: http://127.0.0.1:8088/admin/promotions/62
- **Disabled, pending explicit activation approval.** Automatic approval review rejected the attempted activation because it considers enabling live sales/offline eligibility to require separate approval. No activation command executed.
- Buy any combination of **6 paid eligible units**, choose **1 eligible oil free**, repeated for each complete group. No date limits configured.
- Owner confirmed all products in the existing `oil` category. Explicit product IDs are stored in immutable revisions; later category changes do not add or remove eligibility.

| Code | Product | Internal ID | Current authorized price |
| --- | --- | --- | --- |
| P006 | Siddartha oil | 161 | LKR 220.00 |
| P007 | Sarwa Vishadi oil | 162 | LKR 220.00 |
| P008 | Batu oil | 163 | LKR 220.00 |
| P009 | Watha Viduranga oil | 164 | LKR 220.00 |
| P010 | Kola selesma oil | 165 | LKR 220.00 |

Suwada Kudu (promotion 60, Buy 12 Get 1) and Glucose (promotion 61, Buy 12 Get 2), including their names, status, existing revisions and values, remain unchanged. They were not recreated. One enabled automatic offer per product/date period is enforced across both single-product rules and all selected mixed members.

## Deterministic policy: MIXED_OIL_V1

1. Only paid quantities of explicitly selected members qualify. Combine paid units, sort by exact authorized unit price ascending, then numeric product ID ascending for ties. Input line order does not affect oil grouping.
2. Split that order into complete consecutive groups of six. Ignore the incomplete final group for rewards. Each complete group's first (cheapest) paid unit sets its reward price ceiling. Five paid earns zero, six earns one, twelve earns two, eighteen earns three.
3. The representative/customer chooses reward products and quantities. Sort selected rewards by authorized price ascending, then numeric product ID. Assign each to the earliest remaining group whose ceiling admits that reward. Reject any unmatched reward or excess count. Quantity bands implement this without expanding large quantities into individual units.
4. Example: six paid at LKR 100 and six at LKR 220 create ceilings 100 and 220. One 100 reward plus one 220 reward is valid; two 220 rewards are invalid. Three paid at 100 and three at 220 create one ceiling of 100.
5. POS shows each eligible product, its price, selected free quantity, and additional quantity permitted by both remaining price ceilings and stock. Administrator selects membership explicitly in the existing promotion form. Review is read-only; issuance requires all earned rewards to be selected.
6. Paid and free quantities remain separate. Free quantities never qualify or enter discountable amounts. A selected reward not otherwise purchased becomes an invoice line with zero paid quantity, positive free quantity and zero payable. Invoice percentage policy INVOICE_PERCENT_V1 remains unchanged, including original paid-line order for remainder ties.
7. Both paid and free stock are validated and deducted from the selected warehouse or current authorized representative source by the existing atomic posting path. Insufficient or zero reward stock blocks posting. The existing immutable invoice/ledger and idempotent void path reverses both quantities exactly once. Cash, credit, numbering and payment identities retain existing behavior.

Invoice details and existing thermal/A4/PDF templates display the reward product and separate paid/free quantities using finalized immutable lines. No remote rendering or new document storage was added.

## Offline compatibility and updating

The downloaded immutable promotion rule contains `mixed_product_ids` and `mixed_policy`. Updated clients declare MIXED_OIL_V1 support in signed bootstrap requests. An older client cannot download a mixed-oil grant; the server explicitly requests an app update. PHP and JavaScript independently produce matching grouping, choices, free quantities and invoice percentage amounts.

New signed sales include explicit `oil_rewards` and the final paid/free lines. Advisory offer/stock choice lists are excluded from signed sale bodies and financial confirmation fingerprints. Synchronization checks the grant, exact quantities, membership, current prices/revisions, chosen rewards and current stock before posting. Changed rules/prices and invalid selections are held for review; original signed bodies are retained byte-for-byte. Legacy signed zero-discount bodies continue through their existing validation path. No queue or vault rewrite was introduced.

The public-static worker and script URLs use `mixed-oil-v1`; its cache version is `dharma-static-mixed-oil-v1`. No skipWaiting, private cache entries, storage clearing or automatic unresolved-record deletion was added. After activation, close all app tabs, reopen, and refresh the authorized offline snapshot. Existing signed drafts retain their agreed contents and may need review if their approved prices/rules differ after activation. Reconcile existing pending sales before switching rules when possible. Never clear browser storage to update.

## Migration and preservation

Forward migration **017_mixed_oil_promotions.sql** adds immutable group membership to promotion revisions and permits zero-paid invoice lines only when they have positive free quantity and a MIXED_OIL_V1 promotion snapshot. It does not rewrite existing invoice, payment, stock or promotion rows. No older migration was changed.

The first disposable-schema rehearsal exposed MariaDB's handling of inline quantity CHECK replacement. That rehearsal was explicitly reconciled without resetting the schema. The finalized migration uses MODIFY to replace the inline check, passed repeated migration verification, and applied cleanly to the live application. Its deployed contents are now frozen.

Before live DDL, the application-only backup was written outside public/:

`C:/mariadb-clean/ayurveda-phase1-local/private/mixed-oil-20261115151203/application.sql`

Size: **205,786 bytes**. SHA-256: `df030e88429191b7fc1872c3a817efe0b99b1322137bcb2a8b8e7fd66c1468b9`.

All existing table rows, projected using their original columns, were compared before/after migration. Creating M003 changed only its intended promotion/revision/command/code-reservation/audit records. **49 protected business tables** remained byte-identical, as did all pre-existing promotion headers and revisions. No synthetic transactions were written live; no unrelated databases or global XAMPP/network configuration were changed.

## Verification

All synthetic business tests used `dharma_sales_phase9_test`. Service integration fixtures rolled back; browser fixtures/sales remain only in that disposable schema.

| Verification | Result |
| --- | --- |
| Mixed-oil integration, including signed sync and byte preservation | 96 checks passed |
| Real headless Edge online/offline selector, encrypted save, foreground sync, admin editor, eight viewport widths | 31 checks passed |
| Foundation / database constraints and migration repeatability | 12 / 16 passed |
| Existing promotion regression | 121 passed |
| Invoice/payment/credit/stock/void regression | 181 passed |
| Existing signed offline regression | 106 passed |
| Short-code and invoice percentage enhancement regression | 100 passed |
| JS domain and form checks | 19 tests passed |
| PHP lint | 123 app/public/tests/bin files passed at the verification point |

Coverage includes independent expanded-unit grouping oracles for 100 mixed baskets, input permutations, repeated price bands, huge exact quantities, mixed and free-only products, invalid expensive/extra rewards, zero/short stock, warehouse and representative deductions, percentage discounts on paid amounts, idempotent issuance and voids, independent other offers, immutable membership revisions, changed price/rule holds, old-client bootstrap rejection, thermal/A4 rendering and actual local PDF generation. Mobile verification is headless viewport testing; physical phone/printer acceptance was not performed.

Re-run using the existing protected local configuration:

```powershell
& C:/xampp/php/php.exe storage/mixed-oil-maintenance.php test
& C:/xampp/php/php.exe storage/mixed-oil-maintenance.php browser
& C:/xampp/php/php.exe storage/phase1-local/phase9-test.php suite promotions
& C:/xampp/php/php.exe storage/phase1-local/phase9-test.php suite invoices
& C:/xampp/php/php.exe storage/phase1-local/phase9-test.php offline
node --test tests/mixed-oil-domain.js tests/offline-domain.js tests/invoice-discount-domain.js tests/promotions-ui.js tests/invoices-ui.js
```

Remaining step: explicit approval to activate M003. The tested activation uses the existing audited promotion status service and rechecks the five product IDs. There is no public deployment step.
