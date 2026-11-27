# Stage 6E-4 - operational preflight plan (NOT EXECUTED)

Baseline: `362d36983939ffb53a637861941343d372e2cd51`, branch `hybrid-promotion-v1-stage6e2-original`, worktree `storage/hybrid-stage6e2-original`. Planning only. No database connection, HTTP application request, migration execution, backup operation or operational configuration access occurred; only development source definitions were read. The approved original Invoice UI and financial policy remain unchanged.

## Authorization and execution boundaries

Every statement below is proposed text, not executable authorization. Operational connections, including ports **3306 and 3309**, remain prohibited. Do not load operational credentials, resolve/connect a DSN, use a browser/API against operational data, or invoke an application bootstrap/service during this task. No query has been executed, including against disposable infrastructure.

Future inventory needs a separate written approval naming: operator; exact host/IP/port/schema; expected server/version/server-id/data-directory identity supplied independently by the DBA; named account; approved query IDs and parameter values; permitted representatives/products/invoices; time window; scan/time/output budgets; output recipients and retention. An explicit later authorization would have to supersede the current connection prohibition for that one target. It must not relax the existing disposable guards or imply any other access.

Use a named, host-restricted **inventory account**, not root, the application's writer, or a migration account. A DBA must separately provision/attest the account; no account or GRANT operation is part of this plan. Permit only SELECT on the columns used below in the named schema, plus visible information_schema metadata. In particular exclude user passwords, customer contact/shop/address fields, product costs, invoice/customer snapshots, payment references and private/offline payload JSON. No schema-wide `SELECT *`, mysql.* access, FILE, PROCESS, TRIGGER, EXECUTE, LOCK TABLES, DML, DDL, administrative privilege, writer role or GRANT OPTION. Obtain a DBA attestation covering effective/inherited grants; metadata visibility alone does not prove absence of excess privileges. Column grants do not enforce row scope: operator query allowlisting and approved IDs are also required; use already-approved scoped views if database-enforced row isolation is required. Do not create views here.

After future authorization, bind every value using native prepared statements. `:schema`, `:table_name`, `:rep_id`, `:product_id`, `:location_id`, `:invoice_id`, `:exact_sku` and `:after_id` are unpopulated owner-approved inputs, not inferred identifiers. No synthetic IDs or guessed schema names. Use fresh autocommit plain SELECTs only, never transactions or row/table/advisory locks. Ordinary reads can still acquire brief metadata locks and consume resources; "non-locking" means no explicit business/row/table locking, not zero server impact. No FOR UPDATE, LOCK IN SHARE MODE, GET_LOCK, SLEEP, OUTFILE, DUMPFILE or stored routine invocation.

Metadata must pass before dependent business queries. Missing/hidden columns or definitions mean STOP/UNKNOWN, never dynamic fallback SQL. Each SELECT is a separate observation, not a coherent financial snapshot. Record UTC observation time and versions; drift invalidates comparisons. Prefer short client deadlines (proposed 2 seconds per statement, 30 seconds total initial batch; DBA must approve or reduce), one statement at a time, no automatic pagination/retry or account escalation. The DBA must preconfigure approved server-side statement/resource limits where supported; a client timeout alone does not prove server cancellation. If execution cannot be bounded without new privileges/session changes, stop before running it. LIMIT bounds output, not scanned rows; aggregates, JSON membership and no-match searches can scan substantial history. Obtain index-based scan estimates before approval; cancel on budget exceedance, do not obtain KILL/PROCESS privileges.

## Proposed query inventory

Sensitivity: **M** = restricted infrastructure/schema metadata; **B** = confidential business identifiers/rules/stock; **F** = restricted financial compatibility flags. Expected output is a shape, not a claim about operational contents. Empty, hidden or truncated results cannot establish compatibility.

| ID | Purpose / expected output | Sensitivity | Proposed scope / cost |
|---|---|---|---|
| Q01 | Connection identity, vendor/version, clock/session settings | M | One row; first statement after an independently approved connection |
| Q02 | Tables, engine/collation and approximate size estimates | M | Named schema, <=200 rows; estimates are not exact counts |
| Q03 | Installed migration names, status, raw checksums | M | <=100 journal rows; only after journal table/columns are visible |
| Q04a-e | Required columns, indexes, constraints/check expressions and trigger fingerprints | M | One approved table per call; 100 columns/index/constraint rows or 200 trigger rows; no trigger bodies/business rows |
| Q05 | Representative identity/status without names or credentials | B | One owner-selected rep primary key |
| Q06 | Customer assignments and credit-policy structure | B | One rep, first <=25 ID-only rows; no contact details or credit amounts; no automatic continuation |
| Q07 | Existing product identity, selling-price/version | B | One exact owner-provided SKU; never costs; unknown SKU requires new discovery scope |
| Q08a-c | PRODUCT mode, warehouse/rep locations and assigned balance | B | One settings row, one rep location and one rep/product pair; repeat only for approved pairs |
| Q09 | Selected master pointer plus current revisions/potential membership conflicts | B | One approved product; <=25 rows; mixed-product membership may scan current rules |
| Q10 | Immutable history for a selected promotion master | B | <=25 revisions descending; truncation requires explicit further approval |
| Q11 | Current enabled offer overlap candidates | B | Same Q09 result: compare dates/IDs locally, no extra query or repricing |
| Q12a-b | Product ledger reconciliation and legacy-batch blockers | B | One location/product aggregate; global existence probes separately budgeted and optional |
| Q13 | Posted payment/receipt compatibility | F | One approved ONLINE invoice/rep cohort; child scans estimated beforehand; flags/counts only |
| Q14 | Receivable settlement classification | F | One approved invoice/rep, scalar aggregates/flags; no monetary records returned |
| Q15 | Existing Hybrid controls | M/B | Singleton, only if 025 exists; read only, absence blocks enablement |

Q04 table allowlist: core tables named in Q05-Q15, `product_stock_balances`, `promotion_versions`, `invoice_items`, `online_invoice_details`, `invoice_voids`, `historical_customer_returns`, `hybrid_returns`, `hybrid_voids`, and every table explicitly created by reviewed migrations 025-027. Derive that exact Hybrid list from the checkpoint's SQL files before execution, not arbitrary discovered table names. Q02 may list names of other application tables; do not read their rows. Metadata visibility gaps require DBA attestation or a revised approval, not broad grants.

```sql
-- Q01: compare all identity fields to the independently supplied allowlist.
SELECT DATABASE() AS schema_name, @@hostname AS server_host,
       @@port AS server_port, @@datadir AS data_directory,
       @@server_id AS server_id, VERSION() AS server_version,
       @@version_comment AS vendor, USER() AS connected_identity,
       CURRENT_USER() AS effective_account, UTC_TIMESTAMP() AS observed_utc,
       @@session.time_zone AS session_zone, @@session.sql_mode AS sql_mode;

-- Q02: if limit is reached or visibility is incomplete, stop.
SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME LIMIT 200;

-- Q03: do not run migration commands to discover status.
SELECT version, checksum, status, applied_at_utc
FROM schema_migrations ORDER BY version LIMIT 100;

-- Q04a: type/scale/nullability/collation, including 007's generated key.
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
       COLLATION_NAME, EXTRA, GENERATION_EXPRESSION
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name
ORDER BY ORDINAL_POSITION LIMIT 100;

-- Q04b
SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name
ORDER BY INDEX_NAME, SEQ_IN_INDEX LIMIT 100;

-- Q04c
SELECT tc.CONSTRAINT_NAME, tc.CONSTRAINT_TYPE, k.COLUMN_NAME,
       k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
FROM information_schema.TABLE_CONSTRAINTS tc
LEFT JOIN information_schema.KEY_COLUMN_USAGE k
  ON k.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
 AND k.TABLE_NAME = tc.TABLE_NAME AND k.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
WHERE tc.TABLE_SCHEMA = :schema AND tc.TABLE_NAME = :table_name
ORDER BY tc.CONSTRAINT_NAME, k.ORDINAL_POSITION LIMIT 100;

-- Q04d: NULL/hidden bodies or incomplete visibility are UNKNOWN, not absent.
SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION, EVENT_OBJECT_TABLE,
       SHA2(ACTION_STATEMENT, 256) AS body_digest
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = :schema AND EVENT_OBJECT_TABLE = :table_name
ORDER BY TRIGGER_NAME LIMIT 200;

-- Q04e: MariaDB-style CHECK metadata; vendor/version/visibility must pass first.
SELECT CONSTRAINT_NAME, CHECK_CLAUSE
FROM information_schema.CHECK_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = :schema AND TABLE_NAME = :table_name
ORDER BY CONSTRAINT_NAME LIMIT 100;

-- Q05
SELECT id, role, is_active, version FROM users WHERE id = :rep_id;

-- Q06: start after_id=0 only if that bounded page is approved.
SELECT id, assigned_rep_id, is_active, allow_credit_sales,
       credit_limit_enabled, credit_terms_days, version
FROM customers WHERE assigned_rep_id = :rep_id AND id > :after_id
ORDER BY id LIMIT 25;

-- Q07: owner supplies exact SKU; names are supporting evidence only.
SELECT id, sku, name, is_active, selling_price, version
FROM products WHERE sku = :exact_sku LIMIT 2;

-- Q08a
SELECT s.stock_model, s.warehouse_location_id, s.version,
       l.type, l.rep_id, l.is_active
FROM inventory_settings s LEFT JOIN stock_locations l
  ON l.id = s.warehouse_location_id WHERE s.id = 1;
-- Q08b
SELECT id, type, code, rep_id, is_active
FROM stock_locations WHERE rep_id = :rep_id LIMIT 2;
-- Q08c
SELECT rep_id, product_id, quantity, version
FROM representative_stock_balances
WHERE rep_id = :rep_id AND product_id = :product_id;

-- Q09a: independent selected-master lookup detects a broken current pointer.
SELECT id, name, is_active, current_version, version
FROM promotions WHERE id = :promotion_id;

-- Q09b: current pointer only, INCLUDING disabled/conflicting/monetary rules.
-- product_direct and product_member must be bound to the same approved ID.
SELECT h.id AS promotion_id, h.name, h.is_active, h.version,
       h.current_version, v.id AS revision_id, v.version_number,
       v.product_id, v.type, v.percent_rate, v.fixed_discount,
       v.buy_quantity, v.free_quantity, v.start_date, v.end_date,
       v.valid_from_utc, v.valid_until_utc, v.is_active_snapshot,
       v.mixed_product_ids, v.published_at_utc
FROM promotions h JOIN promotion_versions v
  ON v.promotion_id = h.id AND v.version_number = h.current_version
WHERE v.product_id = :product_direct
   OR JSON_CONTAINS(v.mixed_product_ids, JSON_QUOTE(:product_member))
ORDER BY h.id LIMIT 25;

-- Q10
SELECT id AS revision_id, promotion_id, version_number, product_id, type,
       buy_quantity, free_quantity, percent_rate, fixed_discount,
       start_date, end_date, is_active_snapshot, mixed_product_ids
FROM promotion_versions WHERE promotion_id = :promotion_id
ORDER BY version_number DESC LIMIT 25;

-- Q12a: location_id must be the active REP location verified in Q08b.
SELECT b.quantity >= 0 AS nonnegative,
       b.quantity = COALESCE((SELECT SUM(m.quantity_delta)
         FROM inventory_movements m
         WHERE m.location_id = :location_id AND m.product_id = :ledger_product
           AND m.batch_id IS NULL), 0) AS ledger_matches
FROM representative_stock_balances b
WHERE b.rep_id = :rep_id AND b.product_id = :balance_product;
-- ledger_product and balance_product must be the same approved product.

-- Q12b: OPTIONAL additional scan approval; these are GLOBAL engine guards.
SELECT EXISTS(SELECT 1 FROM inventory_balances WHERE quantity <> 0 LIMIT 1)
         AS nonzero_legacy_batch_balance,
       EXISTS(SELECT 1 FROM inventory_movements WHERE batch_id IS NOT NULL LIMIT 1)
         AS historical_batch_ledger_present;

-- Q13: flags only, using actual applied receipts, never change as collection.
SELECT COUNT(*) AS receipts_checked,
       COALESCE(SUM(CASE WHEN r.received_amount <> r.applied_amount+r.change_amount
         OR (r.applied_amount = 0 AND r.payment_id IS NOT NULL)
         OR (r.applied_amount > 0 AND (p.id IS NULL OR p.status <> 'POSTED'
             OR p.amount <> r.applied_amount OR p.customer_id <> i.customer_id))
         THEN 1 ELSE 0 END), 0) AS receipt_mismatches
FROM invoices i JOIN invoice_receipts r ON r.invoice_id = i.id
LEFT JOIN payments p ON p.id = r.payment_id
WHERE i.id = :invoice_id AND i.rep_id = :rep_id AND i.posting_channel = 'ONLINE';

-- Q14: classification/compatibility flags, NOT a new debt/refund calculator.
SELECT i.currency, i.posting_channel, i.payment_type,
       COALESCE((SELECT SUM(a.amount) FROM payment_allocations a
         JOIN payments p ON p.id = a.payment_id
         WHERE a.invoice_id = i.id AND p.status = 'POSTED'), 0.00)
           <= i.grand_total AS posted_allocations_within_original,
       EXISTS(SELECT 1 FROM invoice_voids v WHERE v.invoice_id = i.id) AS legacy_void,
       EXISTS(SELECT 1 FROM historical_customer_returns r
         WHERE r.invoice_id = i.id) AS historical_credit,
       EXISTS(SELECT 1 FROM hybrid_returns r WHERE r.invoice_id = i.id) AS linked_credit,
       EXISTS(SELECT 1 FROM hybrid_voids v WHERE v.invoice_id = i.id) AS terminal_hybrid_void
FROM invoices i WHERE i.id = :invoice_id AND i.rep_id = :rep_id;

-- Q15: NEVER insert a missing row or alter existing switches.
SELECT id, enabled, sale_posting, return_posting, custody, version
FROM hybrid_operating_state WHERE id = 1;
```

Expected results: Q05 is an active SALES_REP; Q06 exposes only the selected rep's assignments; Q07 yields exactly one active product with exact DECIMAL selling price/version; Q08 is PRODUCT mode, an active WAREHOUSE and the rep's unique active REP location; balances are nonnegative/reconcile. Q09a/Q09b/Q10 must have a matching valid current revision pointer; a master returned by Q09a without its selected current revision is a blocker, never "no offer". If the owner cannot supply a master ID/SKU, seek a separately bounded discovery scope rather than inventing IDs or scanning all masters. Q11 compares all returned enabled periods using inclusive Colombo dates and NULL open bounds; overlapping current master periods, monetary/mixed-oil membership or mismatched quantities are blockers. Do not sum historical revisions as simultaneous offers. Q12b positive results block the current engine; do not invent batches or rewrite history. Q13 mismatches block compatibility; zero sampled receipts prove nothing. Q14 flags require interpretation against approved legacy/Hybrid rules, not a new posting/receivable decision.

Receivables remain the approved source formula: original payable less actual POSTED allocations, applicable historical noncash credit and linked net credits, with terminal void handling. Collections exclude change; later-voided collections remain refund liabilities. Physical refunds are unavailable, never inferred as zero. Q13/Q14 are deliberately limited structural/settlement checks: full authoritative reconciliation, immutable snapshot verification, broader financial scans and any lock-taking review need a separately reviewed scope. If 025-027 are missing, do not execute Q14's Hybrid subqueries or Q15; record the dependency failure, not a fabricated zero/automatic fallback.

Permission checklist for the DBA: allow SELECT only on the explicit columns in approved query IDs, including columns used internally by joins, predicates and aggregate flags. Q13 additionally needs invoices.customer_id; Q14 needs invoices.grand_total and payment_allocations.amount, not stored snapshot JSON. Q04e uses MariaDB metadata columns and must not be sent to an unverified/different metadata dialect; unsupported shape is UNKNOWN/BLOCKED. Metadata/trigger visibility may be restricted without TRIGGER privilege; do not grant a privilege capable of changing triggers merely to inspect them. Obtain independently signed definition/constraint/trigger attestations instead, or stop UNKNOWN. Fingerprints alone do not establish that trigger behavior is correct; compare against reviewed, version-specific expected definitions. Validate CHECK enforcement for the verified vendor/version without operational test INSERTs.

## Owner-review binding template (leave operational fields blank)

| Field | Glucose Buy 6 Free 1 | Suwada Kudu Buy 12 Free 1 |
|---|---|---|
| Existing product ID / exact SKU / active status / version | [owner/DBA evidence] | [owner/DBA evidence] |
| Product display name, supporting only | [unverified] | [unverified] |
| Promotion master ID / active status / current_version | [unverified] | [unverified] |
| Immutable revision ID / version_number / snapshot-active | [unverified] | [unverified] |
| Revision product_id equals selected existing product ID | [unverified] | [unverified] |
| Expected type / paid threshold / free units | BUY_X_GET_Y / 6 / 1 | BUY_X_GET_Y / 12 / 1 |
| Observed type / threshold / free units | [unverified] | [unverified] |
| Approved start_date / end_date (Asia/Colombo, inclusive; NULL meaning explicit) | [owner decision] | [owner decision] |
| Legacy UTC bounds, if populated; agreement with calendar-date policy | [unverified] | [unverified] |
| Other master/revision IDs and overlapping periods, monetary/mixed membership | [unverified] | [unverified] |
| Selling-price version / representative-location scope evidence reference | [unverified] | [unverified] |
| Query IDs/time, reviewer/date and owner acceptance | [not approved] | [not approved] |

Do not infer IDs from names, synthetic fixtures or another environment. NULL calendar bounds are explicit unbounded dates only after owner confirmation. A table completed/accepted by the owner is evidence, not permission to write config bindings or publish/modify promotions. The approved financial policy, original Invoice UI, zero-benefit Hybrid routing and legacy pilot/offline contracts must remain unchanged.

## Migration/checksum verification - non-destructive only

1. Verify target identity and metadata visibility, then inspect Q03. Compare journal entries/statuses with the exact reviewed migration manifest. Missing, unexpected, duplicate, applying/failed, changed-order or unmatched checksum entries STOP; never rerun a migration to diagnose, mark it applied, skip it, alter history or normalize deployed bytes.
2. After separate filesystem-read approval, the DBA/operator hashes deployed migration files in place without reading secrets or copying operational data. Record exact path, byte length and raw SHA-256; compare independently with the installed journal, approved Git blobs, packaged artifact and installer contract. LF/CRLF variants are distinct artifacts. Git status/diff alone is not proof of raw-byte equality; do not "fix" hashes by changing line endings or journal rows. No application Migrator/fresh-installer command is permitted.
3. For 007, compare customers.city_town versus location_towns.name/name_key metadata, generated expression, relevant district fields and installed 007/008 journal states. Known failure 1267 involves incompatible General/Unicode collations. A later 008 entry is not proof of a completed healthy 007. Never propose the fresh-install compatibility preparation for an existing populated operational database. Any populated-target remedy requires a separate forward, reviewed recovery/migration design and authorization; original files stay immutable.
4. Verify 025-027 individually: applied journal/raw checksums, expected tables/DECIMAL scales/keys/constraints, immutable/seal/parent-child/custody triggers and current control row. Table presence alone is insufficient. Missing Hybrid schema means operational compatibility FAIL/BLOCKED, not authorization to install it. Metadata visibility uncertainty means UNKNOWN/BLOCKED. The current supported compatibility-assisted disposable installation is not a pristine-chain or deployed-target PASS.

## Operational HTTP/CLI security acceptance (later, separately authorized)

- Independently bind exact target host/IP/port/schema, vendor/version/server-id/data path and effective account; identity values must agree with the approved inventory. A server-id alone is not a unique physical proof. No browser-supplied DSN, profile, executable or target path. Keep HybridDatabaseScope/HybridIsolatedRuntime disposable restrictions unchanged; a distinct operational adapter needs source/security review, not a bypass switch.
- Keep inventory, read/review, runtime financial writer and migration identities separate. Review may use FOR UPDATE and rollback; SELECT-only inventory privileges are not an operational review identity. Approve exact grants and lock/time budgets later. No grants/global roles beyond the explicit target matrix; no weakening immutable guards to satisfy an adapter.
- Preserve real authentication/session freshness, CSRF, current actor/customer/rep-stock scope, credit permissions, no cost leakage, strict versioned payloads, no-store/private responses and no private service-worker cache. Same original /rep/invoices/new UI only; disabled/unsupported paths fail closed, including eligible zero-benefit sales.
- Fixed supervised HTTP/CLI service identities and executable/script/profile paths; owner/SYSTEM/Administrators or approved equivalent ACLs; protected credentials/IPC keys/session directories outside public. Verify IPC MAC, job identity, expiry, ownership, bounded request/reply size, independent worker reauthorization, worker slots, deadlines, lock/statement limits, cleanup and restart behavior on the actual target.
- Default-deny posting/return/custody/payment/stock/offline writer routes during inventory and read/review acceptance. Source activation OFF, binding/DB allowlists unchanged, posting holds ON. Separate configuration/control approval is required for each later enablement; no automatic legacy fallback.
- Ambiguous confirmation must retain the same UUID and immutable body; an unavailable reply does not mean no posting. Resolve accepted command/result/receipt evidence before retry or replacement. Verify exactly-once behavior in approved synthetic rehearsal; no genuine issuing here. Browser state is in-memory only: unresolved reload/device loss remains a documented recovery acceptance requirement before operational posting.
- Named native Sinhala review and actual 58/80mm/A4/PDF/driver/Bluetooth/glyph/feed/cutter acceptance remain required. Current software/owner preview approval does not replace language, hardware or operational security acceptance.

## Backup/recovery and pass/fail gates

Inventory may review an owner's existing backup/recovery attestation only: coverage of immutable invoices/payments/stock/commands/journal/offline identities, encryption/key custody, retention, last successful verification and restore/reconciliation procedure. **Do not create/export/import/copy any operational database or backup as part of this inventory.** Actual backup access, transfer or restore needs distinct approval. Any future restore rehearsal must use a newly named isolated schema; preserve source backup bytes and original grants, rebinding portable definers only in a separate import copy.

Before later cutover approval: rehearse stopping new dispatch, draining workers, resolving uncertain UUIDs and reconciling stock/cash/debt/refund/number counters; preserve unresolved signed offline records and accepted receipts. Code rollback must not erase later accepted financial activity, reset IDs/numbers or restore a stale snapshot over live records. Corrections remain approved append-only business operations. Document stop/rollback ownership, access, timing and evidence before deployment.

A future **inventory PASS** requires verified identity/least privilege/visibility, complete approved metadata and journal/raw-checksum agreement, owner-accepted real mappings, bounded assignment/stock/financial checks without mismatches and no unapproved scope. It is not release/activation approval. Any missing prerequisite, unsupported schema/vendor, hidden metadata, truncation, drift, excess grants, timeout, conflicting offer, unreconciled ledger, active unexpected posting control, need for broader data, or request to repair/lock/write is FAIL or UNKNOWN/BLOCKED and STOP. Do not call an empty sample a PASS.

Strictly prohibited now and during the proposed non-locking inventory: operational 3306/3309 connections absent a separately superseding authorization; exports/imports/copies; UPDATE/INSERT/DELETE/DDL/migrations; explicit locks or operational review transactions; binding/promotion changes; feature activation or lifting holds; financial posting; changes to original UI/policy; main-worktree changes; merging/pushing/deployment. No commit is authorized in Stage 6E-4.

## Separate future-access approval request - NOT submitted or assumed

The owner must explicitly approve a completed request specifying: target host/IP/port/schema and independent identity attestation; named operator/read-only account/effective column grants; allowed Q IDs and exact ID/SKU/page parameters; query/window/resource/output budgets; metadata visibility/DBA attestation handling; privacy/retention recipients; allowed deployed-file hashing paths; and stop/contact procedure. State explicitly whether the named port prohibition is superseded for that bounded inventory only. Keep exports, copies, writes, locks, migrations, bindings, adapters, activation, hold changes and genuine posting excluded. Proposed separate request: "Do you approve only a non-locking SELECT inventory for [operator/account], [exact target and independent identity], [Q IDs/parameters], [window and resource/output limits], [recipients/retention], and [specifically superseded port restriction], with every listed write/lock/export/activation action excluded?" Do not submit this request for execution until every field is populated and reviewed. Blank fields or elapsed time are not approval.

After inventory results, obtain separate decisions for packaging/schema remediation, operational adapter integration/deployment, any backup/restore work, lock-taking read/review acceptance, and finally limited activation/posting. **Stop here: this document alone authorizes none of those actions.**
