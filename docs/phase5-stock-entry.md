# Phase 5 stock-entry usability update

Goods Receipts and Batch Management already existed. The Inventory screen exposed them as small navigation links, and receipts began with a combined product/batch selector. This update connects those existing services into a visible product-first flow. It does not create another inventory system.

## Enter your first real receipt

1. Sign in as an admin and open **http://127.0.0.1:8088/admin/inventory/receipts**, or click **+ Add Stock** on Inventory.
2. If the warehouse is unconfigured, follow the visible **configure your real central warehouse** link to Inventory Settings. Save your actual warehouse code or select its existing location, then return to Add Stock. No stock is created by setup.
3. Select an existing catalogue product and **Goods receipt — incoming delivery**, then click **Continue to batch and quantity**.
4. Select that product's existing batch. If missing, click **+ Create New Batch**; the existing batch form is preselected to the product. Enter the real printed batch number, expiry, optional manufacturing date, status/acquisition information and reason. Save; the application returns to stock entry with the new batch selected. Creating the batch alone adds no quantity. Quarantined stock remains unavailable until an authorized review marks it saleable.
5. Enter received whole-unit quantity, receipt date, optional supplier/reference/unit cost/notes and a mandatory supporting reason. Confirm the physical batch and quantity, then press **Save Goods Receipt**.
6. Inventory opens automatically, filtered to the affected product, with a success message and updated batch/product quantities. **Movement History** retains the transaction, type, quantity, batch revision, date, user, references and cost metadata.

No receipt is silently posted. Negative/zero received quantities are rejected. Save retries retain their UUID and do not duplicate stock; reuse the same form/request ID if a response is interrupted.

## Existing opening stock

In step 3 choose **Opening stock — existing physical stock** instead of Goods receipt. Enter the verified count already on hand and its as-of date, supporting count/reference and reason. Save using **Save Opening Stock**. This creates a distinct immutable `OPENING` transaction and stock movement, not a purchase receipt or direct balance edit.

Opening stock is allowed only before that central-warehouse batch has any movement history. A retry with the same UUID is harmless; another opening submission with a new UUID is rejected after the first posting. Existing receipt/adjustment history cannot be overwritten or reset. Later deliveries use Goods Receipt; corrections use documented adjustments or exact reversals. An opening entry can itself be explicitly reversed through the existing adjustment workflow, but its history is preserved and cannot be reopened as a new opening balance.

## Implementation and validation

Modified files:

- `app/Views/inventory.php`: prominent Add Stock button and batch-to-receipt continuation.
- `app/Views/inventory-receipt.php` (new): product-first receipt/opening form, product-specific batch selection/search/paging and new-batch link.
- `app/Controllers/InventoryController.php`: selected product/batch context, safe continuation and success redirect to updated Inventory.
- `app/Services/Inventory.php`: explicit OPENING support using the existing transaction/movement model, one-time history guard, product/batch validation and opening reversal support.
- `tests/inventory.php`: opening stock, replay/conflict, permissions, mismatched products, connected forms and successful HTML posting/dashboard display.
- Documentation and README: current entry instructions.

No migration was necessary: the existing enum already supports `OPENING`, and existing transaction columns hold the count date, references and optional cost. No deployed SQL migration was changed. All nine migration checksums remain verified.

The service retains admin checks, CSRF protection at the controller, integer/decimal validation, locked transactions, balance/ledger reconciliation, atomic audit writes and UUID/payload idempotency. Batch selection is scoped to the chosen product and the server rejects mismatched submitted product/batch IDs. Both product selection and batch creation work without JavaScript.

Verification: **124 inventory checks**, **7 multi-process contention/no-write checks**, **170 authentication/authorization/HTTP checks**, **12 foundation**, **69 Products**, **91 Customers**, and **91 location/filter** checks passed. **48 PHP files** passed lint. Public health reports ready. All business fixtures were rolled back and original table contents checked; no fictional stock was retained. No WordPress, other databases, configuration or recovery backup changes were made.

Native form appearance still warrants a human desktop/mobile review. No Phase 6 work is included.
