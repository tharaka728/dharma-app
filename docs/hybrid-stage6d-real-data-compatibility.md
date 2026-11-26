> Historical architecture review from before UI removal; not current UI or release readiness. The separate editor/preview is discarded. Operational-data compatibility remains unverified. See [Stage 6E-1 audit](hybrid-stage6e1-audit-integration-plan.md).

# Unified New Invoice — real business data compatibility review

26 November 2026. Owner clarification reviewed in the isolated `storage/hybrid-stage6d-ui` worktree, branch `hybrid-promotion-v1-stage6d-ui`.

**The owner preview contains synthetic customers, products, prices, promotions and stock only. It does not contain operational Dharma Chemical data.** This review used source inspection only: no database connection, data copy/import, migration, business posting, activation or runtime configuration change was performed.

## Findings

The intended integrated application uses the existing business records and their original primary keys. No replacement master or operational-data import is needed or authorized. Existing customer/product/stock records must not be recreated, renumbered or overwritten to make Hybrid work.

| Information | Existing authoritative source and path |
| --- | --- |
| Customers | `Invoices::options()` reads `customers`, restricting representatives to active customers assigned to the current actor. `InvoiceController::hybridHandle()` uses these options for the unified page. |
| Product cards, search and categories | The unified adapter uses the existing options API with `catalogue=1`; `Invoices::catalogue()` reads active `products` and `categories`, using the existing bounded search and pagination. |
| Displayed selling prices | `products.selling_price`, read by the existing options/catalogue service. Representative catalogue fields exclude cost prices. These display values do not authorize checkout totals. |
| Assigned stock | Existing `representative_stock_balances`, scoped by the authenticated representative's ID. A representative cannot choose another source by changing browser fields. Active master products may appear with zero available stock; the card disables adding unavailable stock. |
| Authoritative review prices | `PdoHybridInvoiceContext::product()` reloads the existing product ID, activity, selling price and version under lock. `HybridInvoiceResolver` checks the existing `Promotions::quote()` price/version against that product record. Browser prices are not accepted as authoritative input. |
| Authoritative review stock | `PdoHybridInvoiceContext::balance()` uses existing representative/warehouse balances, stock locations and `inventory_movements`; it checks balance-versus-ledger reconciliation. Paid and actual free demand are validated by the existing Hybrid policy. |
| Promotions | Existing `promotions` and immutable `promotion_versions`, resolved by the existing `Promotions` service. The Hybrid resolver verifies explicit stable product/offer IDs and approved quantity expectations. It does not identify Glucose/Suwada by product-name comparison. |
| Customer and financial terms | Review reloads the existing customer and assignment, and uses existing credit permissions/limits and linked-credit-aware outstanding calculations. |

The existing New Invoice product-card component (`createPOS` in `public/assets/js/pos.js`) and its CSS are reused. Search, categories, cards, customer selection, cart and quantity controls remain part of that experience. The unified view adds Hybrid controls and expandable server-verified details; it is not a claim that its template is byte-identical to the old checkout. This clarification makes no further layout changes. Dashboard/Profile and shared translations remain unchanged.

## Synthetic data boundary

- `tests/hybrid-fixtures.php` creates synthetic rows in the same application schema shape, **only inside the separately proved disposable test database**. It supplies generated IDs to the isolated test configuration.
- `tests/hybrid-ui-bridge.php` loads the fixture JSON, injects test-only configuration and restricts the owner preview to that fixture's product IDs before pagination. This restriction is demo behavior, not a production catalogue rule.
- Source inspection found no `SYNTHETIC`, `fixtures-stage`, `hybridTestConfig` or `hybrid-fixtures` references under `app/`, `public/` or `config/`.
- `config/hybrid-promotions.php` still has empty operational database and binding lists, availability/posting/custody disabled, and `financial_posting_hold=true`. No actual product or promotion IDs were inspected, guessed or added.
- The immutable Hybrid invoice/line/segment/allocation tables are financial evidence linked to existing identities; they do not form a second customer/product master.

## Current limitations

This is **source-path compatibility**, not verified compatibility with current operational records. Actual customer assignments, product prices/versions, offer identities, schema/checksums, account grants and stock-ledger consistency have not been inspected.

Normal application routing constructs `Invoices` with the existing application database connection. However, current Hybrid source defaults fail closed, and `HybridDatabaseScope` requires a verified disposable schema, physical data directory, grants and CLI execution. `PdoHybridInvoiceContext::transaction()` applies that scope check to review as well as posting. The isolated HTTP runtime also proves a disposable identity. Therefore **changing a connection string or filling bindings is not sufficient to make the current Hybrid review safe or approved for operational data**. These guards must not be removed or bypassed to demonstrate real data.

Preview uses locking reads (`SELECT ... FOR UPDATE`) and rolls back its review transaction. A future review runtime must support the established locking path; simply forcing a server/transaction into a read-only mode that rejects those locks is not an approved substitute. Any operational lock-taking acceptance requires explicit authorization, timing and bounded execution. A preliminary operational inventory can instead be narrowly scoped to approved non-locking reads.

## Requirements for a separately authorized operational preflight

The following is a future checklist, not authorization to perform it now:

1. **Define the permitted target and actions.** Explicitly authorize the operational instance/schema, named account, permitted read queries, metadata access, output handling and any lock-taking review. Until then, operational ports 3306/3309 remain forbidden. Do not point the owner demo or its test bootstrap at operational data.
2. **Verify release packaging and schema compatibility.** Compare deployed raw migration bytes and journal checksums, including the known LF/CRLF contract discrepancy and Migration 007 issue. Check required Hybrid schema/readers through an approved release plan. Do not run compatibility preparation on an existing populated installation, rewrite deployed migrations or treat the prepared synthetic fixture as proof of operational readiness.
3. **Verify authenticated business scope.** Confirm active representative accounts and customer assignments, current customer terms, stock-location identities and existing permissions. Preserve the separately authorized pilot contract and supported legacy offline contracts. Confirm the unified New Invoice journey uses the existing records without a second master or import.
4. **Review actual product and offer identities with the owner.** Produce a minimal mapping for each intended eligible product: existing product ID/SKU, active promotion master ID, current immutable revision ID/version, product binding, offer type and inclusive Colombo validity dates. Confirm Glucose Buy 6 Free 1 and Suwada Kudu Buy 12 Free 1 against those identities. Names/SKUs assist human review; the resolver continues to enforce stable IDs. Do not guess IDs from the synthetic fixtures.
5. **Check every applicable offer, not just the two examples.** Identify absent/inactive bound offers, overlaps, monetary offers, pooled mixed-oil rules and mismatched quantities. The current resolver rejects unsupported/conflicting offers. Report mismatches for owner resolution; do not silently modify offers, remove checks, recreate products or route failed Hybrid transactions through legacy posting.
6. **Validate prices and stock without replacing records.** Confirm current selling-price/version reads, authorized representative balances and their existing ledger/location identities. Report discrepancies and shortages; do not repair stock or seed demo quantities. Catalogue display is indicative; authoritative review must recheck current values.
7. **Approve a target-specific runtime design separately.** Review identity/grants, read/review versus posting separation, existing authentication/roles/CSRF, no-store/private responses, bounded locks/timeouts, IPC/ACLs where applicable and default-deny financial routes. The disposable guard must remain intact; any distinct operational adapter needs its own source review and acceptance. Neither real-data display nor calculation preview implies invoice/payment/return/stock-write authorization.
8. **Approve display/review enablement explicitly.** Integration alone does not enable availability. Any future real-data display or Hybrid preview requires its own approved configuration/control process. Keep sale/return/custody posting disabled and financial holds on unless the owner separately authorizes them. Never retarget the synthetic preview to operational data or cache private records in the service worker.
9. **Perform scoped acceptance after authorization.** Check real assigned-customer/product reads, pagination/search, prices, images where applicable and stock display; then perform only specifically authorized authoritative reviews. Include zero-benefit Hybrid routing, correct stable-ID bindings, wrong-binding rejection, price/offer changes and cross-representative rejection. Do not issue genuine invoices as a display test.

## Review outcome and evidence

**PASS — source architecture reuses existing authoritative masters and services.**

**NOT VERIFIED — current operational data compatibility or actual stable-ID mappings.** Those require the separate preflight above. No operational-data or production-readiness PASS is claimed.

Reviewed files: `app/Controllers/InvoiceController.php`, `app/Services/Invoices.php`, `HybridInvoiceResolver.php`, `PdoHybridInvoiceContext.php`, `PdoHybridCheckoutStore.php`, `HybridDatabaseScope.php`, `Promotions.php`, `config/hybrid-promotions.php`, `public/index.php`, unified view/JavaScript and the isolated fixture/bridge sources. The existing Stage 6D 18-file hash inventory was checked without changing its historical contents.

Only this additive compatibility report was created for this clarification. Application source, prior reports/inventory, configuration, fixtures, preview processes and all business records were left unchanged. No tests requiring a database were run. No commit, push, merge, deployment or activation was performed. **Stop after compatibility review and reporting.**
