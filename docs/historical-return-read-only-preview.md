# Historical return read-only verification

The normal historical-return posting switch is `enabled` in
`config/historical-returns.php`. It remains **false** during this preview.
Do not change it without explicit owner approval and the activation checks.

The separate `preview` setting is for one authenticated, active representative
on the named database and port 3309. The temporary approved account is
`nissanka` (representative ID 156). This permits a non-submitting preview of
product search, year choices, and quantity controls. It does not create an
intent, post a return, apply a settlement, or change stock or payments.

To end the preview, change only `preview.enabled` to `false`; leave the
financial `enabled` switch unchanged. This does not alter existing records.
The preview is not an exception to authentication, customer scope, CSRF, or
the return-intent and checkout server guards. It uses the existing approved
price lookup, which is read-only. No approved operational historical prices
existed at the time of verification.

The preview's product picker searches active products by code or Sinhala or
English name. A user must select a result before the selected year's approved
price or missing-price message is shown. The restricted endpoint returns no
cost prices and cannot create an intent or invoice.

Verification: `tests/historical-return-preview.php` temporarily points preview
configuration at the explicitly named isolated restore and synthetic users,
then restores the original configuration in its shutdown handler. It must
start with both switches disabled. Do not run it against an operational schema.

## Owner-verified mobile read-only preview — 19 November 2026

The owner reports **OWNER-VERIFIED MOBILE READ-ONLY PREVIEW TESTS: PASS** on a Redmi Note 15 using the authorized `nissanka` account:

1. P012 product suggestions appear and a product can be selected by touch.
2. Both 2024 and 2025 pricing years can be selected.
3. Missing historical prices display the correct message.
4. Search finds saved Sinhala and English product names.
5. No overlapping fields or horizontal page overflow was observed.

These results cover seven owner-reported checks of the preview UI only; they do not verify financial return checkout or settlement posting.

## Entering genuine approved prices

1. Sign in as an administrator and open `http://127.0.0.1:8088/admin/historical-prices`.
2. Choose 2024 or 2025 and search for the product by code or name. Confirm the product and year against the company's approved historical price records.
3. Enter the genuine unit return price in LKR with at most two decimal places, select **APPROVED**, and enter the reason or source in **Reason**. Select **Save revision**. Repeat separately for each product and year. Do not enter an estimated or synthetic price.
4. Check the displayed amount and status. Use **History** to review prior revisions, actor, time and reason. To change an approved price, enter the corrected amount and reason, select the confirmation checkbox and save; if the page says the price changed, reload and review the latest revision before trying again. Choosing **DISABLED** makes the latest revision unavailable for lookup; the earlier history remains.

Historical return prices are stored as separate revisions and do not change the product's current selling price. The price service writes an audit record with before/after information. The isolated `tests/historical-prices-integration.php` test verified these properties and immutable revision protection; its synthetic changes were rolled back.

## Read-only check after genuine prices are entered

1. Keep `enabled` false in `config/historical-returns.php` and keep the existing restricted preview for `nissanka`.
2. Sign in as `nissanka` and open `http://127.0.0.1:8088/rep/invoices/new`. Open Historical Product Return, search and explicitly select a product for which a genuine approved price was entered.
3. Select 2024, then 2025, and compare each displayed price or missing-price message with the administrator's approved record. A year without an approved latest revision should show the missing-price message. Do not create an intent, submit a return or issue an invoice as part of this check.
4. Record product codes, years and pass/fail results without copying private data into public files. This is a visual and read-only lookup check, not a posting test. Successful populated lookup remains unverified until genuine prices exist and this check is performed.

## Financial activation prerequisites

Owner explicitly authorizes financial activation separately; genuine approved prices have been entered and their read-only lookup checked; reconfirm the migration checksums/schema, database integrity and current verified backup, disabled test overrides, server-side authorization/CSRF and offline block, and financial/stock/idempotency/reversal readiness before enabling the reversible financial switch. Do not post synthetic operational transactions or expose the application publicly. The preview can be disabled independently at any time.

## Controlled localhost activation plan (prepared, not authorized)

Owner-reported real-device read-only verification confirms that both approved 2024 and 2025 prices displayed for `nissanka`. Financial posting is still disabled. The financial switch is additionally restricted by the separate pilot policy described below. Do not change it without separate explicit owner approval.

Immediately before any approved activation:

1. Reconfirm a recent restorable backup, operational migration checksums and reconciliation; confirm approved product/year prices, the server's loopback binding, current role/customer authorization and disabled test-only mechanisms. Do not create synthetic operational records.
2. After explicit owner approval, change only `enabled` from `false` to `true` in `config/historical-returns.php`; retain the existing authorization, CSRF, online-only and transaction checks. Confirm the running application has picked up the setting and that it remains reachable only on authorized localhost interfaces.
3. Using authenticated authorized accounts, verify the admin prices page, return panel, exact-year lookups, missing-price rejection and normal checkout without posting synthetic transactions. Verify offline return finalization stays blocked. A real return must be a genuine owner-authorized business transaction; reconcile its invoice, settlement, cash, stock, customer history and receipt after posting.
4. Recheck migration/financial/stock integrity and monitor failures; preserve audit evidence. A review-only check does not establish successful live posting.

Rollback of new return activity: set `enabled` back to `false` in the same configuration and verify return-intent creation and return checkout are blocked server-side while normal invoices remain available. Keep completed transactions, immutable history and physical goods records; do not restore the database or delete/reverse transactions as a toggle rollback. Handle any already posted return through the existing authorized void and reconciliation process. The separate preview setting can remain restricted or be disabled independently.

## Prepared financial pilot — not activated

The financial pilot is independent of read-only preview. `config/historical-returns.php` specifies pilot `enabled`, stable representative ID `156`, and canonical username `nissanka`; the service checks the current database account is active and has the `SALES_REP` role. The global financial `enabled` remains **false**. Returning an authorized preview result never grants financial permission.

Intent creation/recovery, invoice review and issue, replay, and the return posting service enforce the policy on the server; customer ownership, CSRF and online-only validation remain in force. The existing test-only mode is constrained to the specifically named isolated restore and requires an isolated synthetic representative ID; it cannot enable operational posting.

Emergency stop: set global `enabled` to `false`. To revoke just the pilot, set `pilot.enabled` to `false` (or remove its ID); malformed or mismatched policy fails closed. Both controls block new return requests and leave completed transactions, audit, encrypted offline drafts and ordinary invoicing intact. Do not delete or silently reverse existing returns. Existing authorized invoice void/refund controls remain separate.

Future activation requires explicit owner approval, a current verified backup, migration and reconciliation checks, and confirmation the 8088 listener is localhost-only. Then enable only the global financial `enabled` switch while retaining the pilot binding; verify authenticated pilot/nonpilot access and the offline block without synthetic operational postings. The pilot grants access only to the bound representative; administrator pricing and reporting permissions remain unchanged. Genuine transactions must be reconciled through the existing process.
