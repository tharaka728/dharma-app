# Stage 6E-6 - final operational preflight approval package

Specification: **DHARMA_PREFLIGHT_V1 / 6E6.1**, 26 November 2026. Status: **SOURCE REVIEW COMPLETE; OPERATIONAL ACCESS NOT APPROVED**. Application baseline: `362d36983939ffb53a637861941343d372e2cd51`; branch `hybrid-promotion-v1-stage6e2-original`; isolated worktree `storage/hybrid-stage6e2-original`. This document is the sole authoritative SQL approval specification for a future request. It is not execution authorization, deployment readiness or financial acceptance.

Stage 6E-4 is a historical planning draft, **not execution-ready**; Stage 6E-5 is its static critique. Their original text/status descriptions remain historical and unchanged. Where they differ, use only the versioned statements and restrictions here. Any SQL/parameter/scope change requires a new specification version and review. No approval may cite only an unversioned Q ID or the old draft.

Input raw SHA-256 (unchanged before this checkpoint):

- Stage 6E-4: `5450aa916e79d5f4abd20dee4168ad4db088b014cc6c229f59851f25826c866a`.
- Stage 6E-5: `eaf2f92cc706223c4fdb043593eaba7d685099ea59df198fd5fe7bc48703b86d`.

## Authority, default scope and evidence limits

No database connection, SQL/EXPLAIN execution, HTTP/API access, application bootstrap, target inspection, backup/export or migration occurred in this stage. The local commit authorizes none of those activities. Ports **3306 and 3309 remain prohibited**. Do not read operational credentials or invoke an application service to inspect its environment. Source Hybrid activation/posting flags remain OFF, financial holds ON; disposable database/CLI/physical guards remain intact.

Static PASS means a statement agrees with the checked-in definitions, not that its target dialect, effective grants, data, indexes, cost or result will pass. All operational identities, plans/cardinalities, metadata completeness and compatibility are **UNKNOWN**, and all execution is currently **BLOCKED** pending separate approval. No estimated cost is asserted from source alone.

Default means a candidate for a future explicitly enumerated, bounded approval; it never means automatic execution. Mandatory preconditions for each selected statement: independently attested identity/effective grants, supported dialect, required schema/journal evidence, exact owner parameters, DBA cost/cancellation evidence and approved budgets. Stop before a dependent statement when these are absent. Do not silently widen scope, repair records, switch dialect or fall back to legacy posting.

| Scope class | Exact query versions | Approval rule |
|---|---|---|
| Default bounded candidates | Q01 v1, Q03 v1, Q04a v1, Q04b v1, Q04c v2, Q04e v1, Q05 v1, Q07 v1, Q08a v1, Q08b v1, Q08c v1, Q09a v1, Q10 v1, Q12a v2, Q13 v2, Q14 v2, Q15 v1 | Select each individually, with parameters, dependencies and DBA scan limits. Q12a/Q13/Q14 aggregates are bounded by a selected pair/invoice, not by child history size. No cost evidence means no execution. Q04e additionally requires attested MariaDB metadata dialect. |
| Optional metadata/assignment discovery | Q02 v1, Q06 v1 | OUT of default: schema-wide table inventory or representative customer page needs separately selected scope/output/cost approval. |
| Optional definition visibility | Q04d v1 | OUT of default; minimal account may not see trigger bodies. Prefer independently attested reviewed definitions; never grant write-capable TRIGGER to obtain visibility. |
| Optional broad/global work | Q09b v2, Q09c v1, Q12b v1 | OUT of default; separate explicit approval plus DBA cost evidence. Q09b's OR/JSON membership may scan all current masters; Q09c is global pointer integrity; Q12b probes global retained history. LIMIT is not a scan bound. |
| Local analysis only | Q11 v2 | No SQL or additional access. Cannot conclude absence of conflicts without complete Q09 evidence and DBA pointer-integrity attestation. |

Default scope can therefore establish only partial compatibility. With optional conflict/global-guard evidence absent, promotion/engine readiness remains UNKNOWN/BLOCKED. Do not narrow a global guard to one product and claim it passed. Other broad scans, payment-capacity/orphan searches, warehouse reconciliation, full customer/history discovery, repeated pages, EXPLAIN and any added query are also OUT of default and require separate review/approval.

## Source verification, permissions and expected paths

Read-only source verification: migrations 001, 006-014, 017, 021 and 025-027; `app/Support/Migrator.php`, `HybridFreshInstaller.php`; `PdoHybridInvoiceContext::{source,balance,promotionGate,schemaReady}`; `Promotions::{select,quote,save}`; `HybridInvoiceResolver::resolve`; `Invoices::{scope,receipt}`; `PdoHybridCheckoutStore::postOfficial`; `HybridInvoiceReader::read`; `HybridInvoiceBalances::{sql,read}`. These files were read, never executed. Relevant supporting indexes are declared in 001/011/013 and the Hybrid migrations; FK-supporting indexes are expected from InnoDB, not proven deployed plans.

The six source-supported SQL areas are Q04c v2 (composite FK ordinal/actions), Q09b v2 (selected master/LEFT JOIN and string/numeric membership diagnostic), optional Q09c v1 (global dangling pointer evidence), Q12a v2 (rep/product/location and operating-state linkage), Q13 v2 (current scope, cash/allocation matching and empty cohorts), Q14 v2 (current scope, classification only). Q11 v2 corrects interpretation, not SQL. Monetary DECIMAL(15,2), rates DECIMAL(5,2), BIGINT IDs/quantities and UUID identity contracts are unchanged.

Required SELECT columns below include joins, predicates and ordering, not only returned fields. I_S means information_schema metadata visibility, not new blanket grants. M = restricted infrastructure metadata; B = confidential business identity/rules/stock; F = restricted financial flags. The verdicts below apply to this versioned catalog. Corrections/additional columns are explicit; Stage 6E-4 draft verdicts are superseded.

| Query | Static verdict | Required SELECT columns / metadata visibility | Expected path, result and suitability |
|---|---|---|---|
| Q01 | PASS; target identity UNKNOWN | No application columns; proposed system identity/version/time/SQL-mode expressions | One M row; candidate first read after separate connection approval. server-id is not unique identity; session settings do not prove all application connections |
| Q02 | PASS; visibility UNKNOWN | I_S.TABLES: TABLE_SCHEMA,TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION,TABLE_ROWS | Schema metadata <=200 M rows; estimates not exact counts. Hidden tables/truncation block completeness; metadata cost UNKNOWN |
| Q03 | PASS; deployed journal UNKNOWN | schema_migrations: version,checksum,status,applied_at_utc | PK(version), ordered <=100 M rows. Candidate bounded journal walk; filename ordering does not prove DDL execution order or actual schema |
| Q04a | UNKNOWN target dialect/version | I_S.COLUMNS: TABLE_SCHEMA,TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA,GENERATION_EXPRESSION | One table, <=100 M rows; needed for 007's generated key. Conditional, no automatic dialect fallback |
| Q04b | PASS shape; deployed indexes UNKNOWN | I_S.STATISTICS: TABLE_SCHEMA,TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME | One table, <=100 M rows; verify full key sequence. Metadata is not an optimizer plan |
| Q04c v2 | PASS source shape; metadata dialect/visibility UNKNOWN | TABLE_CONSTRAINTS: TABLE_SCHEMA,CONSTRAINT_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE; KEY_COLUMN_USAGE: CONSTRAINT_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,ORDINAL_POSITION,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME; correction adds POSITION_IN_UNIQUE_CONSTRAINT and REFERENTIAL_CONSTRAINTS(CONSTRAINT_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,DELETE_RULE,UPDATE_RULE) | Composite-name joins include FK order/actions. <=100 M rows; corrected metadata check conditional on visibility |
| Q04d | BLOCKED for complete proof under minimal account | I_S.TRIGGERS: TRIGGER_SCHEMA,TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_STATEMENT | <=200 M rows; NULL/hidden hash/empty set cannot prove absence. DBA definition attestation required; do not grant write-capable TRIGGER just for visibility |
| Q04e | UNKNOWN vendor/version/visibility | I_S.CHECK_CONSTRAINTS: CONSTRAINT_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,CHECK_CLAUSE | MariaDB-style shape, <=100 M rows. Conditional on independent metadata-shape/enforcement evidence; no live INSERT test |
| Q05 | PASS | users: id,role,is_active,version | PK(id), <=1 B row. Require active SALES_REP, not existence alone; point-lookup cost still target-UNKNOWN |
| Q06 | PASS, partial sample | customers: id,assigned_rep_id,is_active,allow_credit_sales,credit_limit_enabled,credit_terms_days,version | FK-supporting assigned_rep_id index plus PK id range expected; <=25 B rows. Actual ordering/range plan UNKNOWN; no full customer/credit-limit audit |
| Q07 | PASS | products: id,sku,name,is_active,selling_price,version | UNIQUE(sku), <=1 B row if intact (LIMIT 2 detects ambiguity). SKU collation may be case-insensitive. No costs; candidate exact owner SKU only |
| Q08a | PASS | inventory_settings: id,stock_model,warehouse_location_id,version; stock_locations: id,type,rep_id,is_active | Singleton/PK join, <=1 B row. Require PRODUCT, active WAREHOUSE; NULL location is not PASS |
| Q08b | PASS, incomplete source prerequisites | stock_locations: id,type,code,rep_id,is_active | UNIQUE(rep_id), <=1 B row (LIMIT 2). Interpret type/active with Q05; original inventory omits required rep_operating_state |
| Q08c | PASS, missing-row caveat | representative_stock_balances: rep_id,product_id,quantity,version | Composite PK(rep_id,product_id), <=1 B row. Missing balance is UNKNOWN, not PASS; runtime zero-default requires matching zero-ledger/location evidence |
| Q09a | PASS | promotions: id,name,is_active,current_version,version | PK(id), <=1 B row. Selected master check, not all conflicting masters; version != assumed current_version |
| Q09b v2 OPTIONAL | PASS source relationships; numeric diagnostic/dialect and scan UNKNOWN | Q09a columns plus promotion_versions: id,promotion_id,version_number,product_id,type,percent_rate,fixed_discount,buy_quantity,free_quantity,start_date,end_date,valid_from_utc,valid_until_utc,is_active_snapshot,mixed_product_ids,published_at_utc | UNIQUE(promotion_id,version_number) joins each current revision; OR/JSON membership can scan all masters despite <=25 B rows. Conditional diagnostic, no absence proof after truncation/broken unrelated pointers |
| Q10 | PASS, incomplete history | promotion_versions: id,promotion_id,version_number,product_id,type,buy_quantity,free_quantity,percent_rate,fixed_discount,start_date,end_date,is_active_snapshot,mixed_product_ids | Unique promotion/version index, descending range <=25 B rows. Candidate bounded history, not full-history/immutability proof |
| Q11 v2 (no SQL) | PASS source interpretation; complete evidence UNKNOWN | Q09/Q10 results, no extra grant | Local B interval analysis: current calendar authority, not combined retained UTC/calendar policy; historical versions are not simultaneous offers |
| Q12a v2 | PASS source relationships; scan UNKNOWN | representative_stock_balances: rep_id,product_id,quantity; inventory_movements: location_id,product_id,batch_id,quantity_delta. Correction adds stock_locations(id,rep_id,type,is_active), users(id,role,is_active), rep_operating_state(rep_id) | Balance PK; corrected location PK/UNIQUE(rep_id); explicit ledger index(location_id,product_id,batch_id,id). One B output but full pair history may be aggregated; cost/fan-out UNKNOWN |
| Q12b | PASS engine-guard meaning; UNKNOWN cost | inventory_balances: quantity; inventory_movements: batch_id | Global quantity<>0 and batch_id IS NOT NULL lack guaranteed selective leading indexes; may scan entire retained tables. One B row/two flags; BLOCKED from default batch without separate global-scan approval/attestation |
| Q13 v2 | PASS source relationships; cohort/cost UNKNOWN | invoices: id,rep_id,customer_id,posting_channel; invoice_receipts: request_id,invoice_id,payment_id,received_amount,applied_amount,change_amount; payments: id,status,amount,customer_id. Correction adds payments.method, payment_allocations(payment_id,invoice_id,customer_id,amount), customers(id,assigned_rep_id), users(id,role,is_active) | Invoice PK; receipts FK invoice_id index; payments PK; corrected allocation PK(payment_id,invoice_id). One F aggregate row, child fan-out UNKNOWN. Receipt-cohort check only, not full payment-capacity proof |
| Q14 v2 | PASS source scope; classification only, reconciliation UNKNOWN | invoices: id,rep_id,currency,posting_channel,payment_type,grand_total; payment_allocations: invoice_id,payment_id,amount; payments: id,status; invoice_voids/historical_customer_returns/hybrid_returns/hybrid_voids: invoice_id. Correction adds invoices.customer_id, customers(id,assigned_rep_id), users(id,role,is_active) | Invoice PK; allocation FK(invoice_id,customer_id) prefix; payments PK; void/historical PK(invoice_id); hybrid_returns UNIQUE(invoice_id,revision). One F row, child sums UNKNOWN; missing Hybrid schema blocks it |
| Q15 | PASS; operational state UNKNOWN | hybrid_operating_state: id,enabled,sale_posting,return_posting,custody,version | Singleton PK, <=1 M/B row after 025 checks. No financial_posting_hold column here: source/private hold policy is separate |

For the revised catalog, Q04c v2/Q09b v2/Q09c v1/Q12a v2/Q13 v2/Q14 v2 are **static PASS against the source relationships**, with operational cost/dialect/data UNKNOWN. Q04c metadata shape is conditional; Q09b numeric JSON behavior on an unknown version/large IDs remains UNKNOWN. Q11 v2 interpretation is statically aligned. Q04d remains BLOCKED for complete definition proof under minimal visibility; Q04a/e remain target-dialect UNKNOWN. No SQL text here resolves operational evidence gaps. Optional Q09c SELECT columns: promotions(id,current_version), promotion_versions(id,promotion_id,version_number); expected master scan plus unique promotion/version probes, one B count, real cost UNKNOWN.

No privileges are granted here. A DBA must attest a named host-restricted account with only column SELECT for individually approved queries and visible schema metadata. No schema-wide SELECT, mysql.* reads, passwords, customer PII, costs, snapshots/payloads, FILE, PROCESS, EXECUTE, TRIGGER, LOCK TABLES, DML/DDL/admin privileges, writer roles or GRANT OPTION. Include effective/inherited roles in the attestation. Column grants do not enforce row scope; use operator query/parameter allowlisting or already-approved scoped views, without creating anything in this stage. Hidden definitions/NULL fingerprints mean UNKNOWN, not absence.

## Versioned SQL catalog - TEXT ONLY, DO NOT RUN

All placeholders are unpopulated owner-approved values; canonical decimal IDs are strings, UUIDs lowercase canonical strings, never floats/JavaScript Number. Use native prepares with a distinct bind per placeholder occurrence. Schema/table values are comparison parameters, not dynamic identifiers. Query text hashes below are SHA-256 of the displayed SQL string excluding the fence/terminal newline; extract no scripts from this document for automatic execution.

### Q01 v1

Parameters: none (fixed singleton IDs, if any, are part of the reviewed statement). SQL SHA-256: `354104ae839995ecc083c416d1f7e38d91f2f0aecc628abd0d12378309a56384`.

```sql
SELECT DATABASE() AS schema_name, @@hostname AS server_host,
       @@port AS server_port, @@datadir AS data_directory,
       @@server_id AS server_id, VERSION() AS server_version,
       @@version_comment AS vendor, USER() AS connected_identity,
       CURRENT_USER() AS effective_account, UTC_TIMESTAMP() AS observed_utc,
       @@session.time_zone AS session_zone, @@session.sql_mode AS sql_mode;
```

### Q02 v1 - OPTIONAL, excluded from default

Parameters: `:schema`. SQL SHA-256: `34675225fa11b76868ed92271aee26dc12711e941b9ff888afe0cdb382314fe2`.

```sql
SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME LIMIT 200;
```

### Q03 v1

Parameters: none (fixed singleton IDs, if any, are part of the reviewed statement). SQL SHA-256: `8224a3e39a431ffdc5a8a43e5e3b0b36131b1b676a69414660618f5f0be83299`.

```sql
SELECT version, checksum, status, applied_at_utc
FROM schema_migrations ORDER BY version LIMIT 100;
```

### Q04a v1

Parameters: `:schema`, `:table_name`. SQL SHA-256: `aa680d228dbc1f43802d503c0839bb5c55a03ade773d816f2bbeb52e1d91f6c4`.

```sql
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
       COLLATION_NAME, EXTRA, GENERATION_EXPRESSION
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name
ORDER BY ORDINAL_POSITION LIMIT 100;
```

### Q04b v1

Parameters: `:schema`, `:table_name`. SQL SHA-256: `d6d668cd086f2fc98eaa39d3a72bb70bc64500ad0d337e810b904e7b00717489`.

```sql
SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name
ORDER BY INDEX_NAME, SEQ_IN_INDEX LIMIT 100;
```

### Q04c v2

Parameters: `:schema`, `:table_name`. SQL SHA-256: `5fe5c9cc1bb1c8f824070992616dc3648cbd30f311c9d179193814b24d124383`.

```sql
SELECT tc.CONSTRAINT_NAME,tc.CONSTRAINT_TYPE,
       k.ORDINAL_POSITION,k.POSITION_IN_UNIQUE_CONSTRAINT,k.COLUMN_NAME,
       k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,
       rc.DELETE_RULE,rc.UPDATE_RULE
FROM information_schema.TABLE_CONSTRAINTS tc
LEFT JOIN information_schema.KEY_COLUMN_USAGE k
  ON k.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA
 AND k.TABLE_NAME=tc.TABLE_NAME AND k.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA
 AND rc.TABLE_NAME=tc.TABLE_NAME AND rc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
WHERE tc.TABLE_SCHEMA=:schema AND tc.TABLE_NAME=:table_name
ORDER BY tc.CONSTRAINT_NAME,k.ORDINAL_POSITION LIMIT 100;
```

### Q04d v1 - OPTIONAL, excluded from default

Parameters: `:schema`, `:table_name`. SQL SHA-256: `679e56057c8cd6db515e1e35c89fab9bb745e9449d7a20a58cc27f4b675921f4`.

```sql
SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION, EVENT_OBJECT_TABLE,
       SHA2(ACTION_STATEMENT, 256) AS body_digest
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = :schema AND EVENT_OBJECT_TABLE = :table_name
ORDER BY TRIGGER_NAME LIMIT 200;
```

### Q04e v1

Parameters: `:schema`, `:table_name`. SQL SHA-256: `453ff5ea82970bf39f649685cf4ab1d1edd9361dbf68ca63d8020ef3840a8919`.

```sql
SELECT CONSTRAINT_NAME, CHECK_CLAUSE
FROM information_schema.CHECK_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = :schema AND TABLE_NAME = :table_name
ORDER BY CONSTRAINT_NAME LIMIT 100;
```

### Q05 v1

Parameters: `:rep_id`. SQL SHA-256: `32c33763e756175e2afd943000fb8e6aa298e6f1232d29a76ed30f1af2e9d2fe`.

```sql
SELECT id, role, is_active, version FROM users WHERE id = :rep_id;
```

### Q06 v1 - OPTIONAL, excluded from default

Parameters: `:rep_id`, `:after_id`. SQL SHA-256: `05e6ea31794fb5307e808cad7fb8c95a2b63e65f7a4fa861c931d7488e288e29`.

```sql
SELECT id, assigned_rep_id, is_active, allow_credit_sales,
       credit_limit_enabled, credit_terms_days, version
FROM customers WHERE assigned_rep_id = :rep_id AND id > :after_id
ORDER BY id LIMIT 25;
```

### Q07 v1

Parameters: `:exact_sku`. SQL SHA-256: `a1440e8f467c6f8fbb401dcfe9124bc06f5894cb1ae509cda3cc4771fa90a7f2`.

```sql
SELECT id, sku, name, is_active, selling_price, version
FROM products WHERE sku = :exact_sku LIMIT 2;
```

### Q08a v1

Parameters: none (fixed singleton IDs, if any, are part of the reviewed statement). SQL SHA-256: `912a1114b8e61dc4fb429251672ad0df8eaf232ac4b406fc9f027ac440da50ad`.

```sql
SELECT s.stock_model, s.warehouse_location_id, s.version,
       l.type, l.rep_id, l.is_active
FROM inventory_settings s LEFT JOIN stock_locations l
  ON l.id = s.warehouse_location_id WHERE s.id = 1;
```

### Q08b v1

Parameters: `:rep_id`. SQL SHA-256: `2888f686533a4789e52e010e5f633ee6b8b6a5393457cab6c881a95cc9ce800a`.

```sql
SELECT id, type, code, rep_id, is_active
FROM stock_locations WHERE rep_id = :rep_id LIMIT 2;
```

### Q08c v1

Parameters: `:rep_id`, `:product_id`. SQL SHA-256: `6969f62ec7d22869ec92d428d9238ecff2466cb7273e53e05a4b3f3b36915f41`.

```sql
SELECT rep_id, product_id, quantity, version
FROM representative_stock_balances
WHERE rep_id = :rep_id AND product_id = :product_id;
```

### Q09a v1

Parameters: `:promotion_id`. SQL SHA-256: `e820f4f64518036bf94c9bc7d5c285455dd37524a46d35703a44ded855ec9c3b`.

```sql
SELECT id, name, is_active, current_version, version
FROM promotions WHERE id = :promotion_id;
```

### Q09b v2 - OPTIONAL, excluded from default

Parameters: `:member_string_probe`, `:member_numeric_probe`, `:selected_master`, `:direct_product`, `:member_string_filter`, `:member_numeric_filter`. SQL SHA-256: `5c04d2383fe4cf5a5923a04b93cfb6d8f3a451e599d27a55633a0383b7df3948`.

```sql
SELECT h.id AS promotion_id,h.name,h.is_active,h.version,h.current_version,
       v.id AS revision_id,v.version_number,v.product_id,v.type,
       v.percent_rate,v.fixed_discount,v.buy_quantity,v.free_quantity,
       v.start_date,v.end_date,v.valid_from_utc,v.valid_until_utc,
       v.is_active_snapshot,v.mixed_product_ids,v.published_at_utc,
       CASE WHEN JSON_CONTAINS(v.mixed_product_ids,
                 JSON_QUOTE(:member_string_probe)) THEN 1 ELSE 0 END AS string_member,
       CASE WHEN JSON_CONTAINS(v.mixed_product_ids,
                 :member_numeric_probe) THEN 1 ELSE 0 END AS numeric_member
FROM promotions h LEFT JOIN promotion_versions v
  ON v.promotion_id=h.id AND v.version_number=h.current_version
WHERE h.id=:selected_master OR v.product_id=:direct_product
   OR JSON_CONTAINS(v.mixed_product_ids,JSON_QUOTE(:member_string_filter))
   OR JSON_CONTAINS(v.mixed_product_ids,:member_numeric_filter)
ORDER BY h.id LIMIT 25;
```

### Q09c v1 - OPTIONAL, excluded from default

Parameters: none (fixed singleton IDs are part of the reviewed statement). SQL SHA-256: `fcc4618af6e22d47d7ba069eadb75e7c3e327c06d1b339e2f139b10cebcaac95`.

```sql
SELECT COUNT(*) AS dangling_published_pointers
FROM promotions h LEFT JOIN promotion_versions v
  ON v.promotion_id=h.id AND v.version_number=h.current_version
WHERE h.current_version>0 AND v.id IS NULL;
```

### Q10 v1

Parameters: `:promotion_id`. SQL SHA-256: `93ac24d035550b986b5e54c7368a567c6a28129d29130941b89cd5098de0f59a`.

```sql
SELECT id AS revision_id, promotion_id, version_number, product_id, type,
       buy_quantity, free_quantity, percent_rate, fixed_discount,
       start_date, end_date, is_active_snapshot, mixed_product_ids
FROM promotion_versions WHERE promotion_id = :promotion_id
ORDER BY version_number DESC LIMIT 25;
```

### Q12a v2

Parameters: `:rep_id`, `:product_id`, `:location_id`. SQL SHA-256: `3a9c0e994e58630bba260a63e363f482c2afa699d3ae0a8391006d40e618c1fe`.

```sql
SELECT l.id AS location_id,b.rep_id,b.product_id,
       b.quantity>=0 AS nonnegative,
       EXISTS(SELECT 1 FROM rep_operating_state os
              WHERE os.rep_id=b.rep_id) AS operating_state_present,
       b.quantity=COALESCE((SELECT SUM(m.quantity_delta)
         FROM inventory_movements m
         WHERE m.location_id=l.id AND m.product_id=b.product_id
           AND m.batch_id IS NULL),0) AS ledger_matches
FROM representative_stock_balances b
JOIN stock_locations l ON l.rep_id=b.rep_id AND l.type='REP'
JOIN users u ON u.id=b.rep_id
WHERE b.rep_id=:rep_id AND b.product_id=:product_id AND l.id=:location_id
  AND l.is_active=1 AND u.role='SALES_REP' AND u.is_active=1;
```

### Q12b v1 - OPTIONAL, excluded from default

Parameters: none (fixed singleton IDs, if any, are part of the reviewed statement). SQL SHA-256: `e742442e138b87044db151907bff39423727c0fc34cea29a11fde73af7317afe`.

```sql
SELECT EXISTS(SELECT 1 FROM inventory_balances WHERE quantity <> 0 LIMIT 1)
         AS nonzero_legacy_batch_balance,
       EXISTS(SELECT 1 FROM inventory_movements WHERE batch_id IS NOT NULL LIMIT 1)
         AS historical_batch_ledger_present;
```

### Q13 v2

Parameters: `:invoice_id`, `:rep_id`. SQL SHA-256: `b85bbfd94a31acc328fe7f2eac52345175d885e9dec951c82795d1b64c1fa285`.

```sql
SELECT COUNT(r.request_id) AS receipts_checked,
       COALESCE(SUM(CASE
         WHEN r.request_id IS NULL THEN 0
         WHEN r.received_amount<>r.applied_amount+r.change_amount
           OR r.received_amount<0 OR r.applied_amount<0 OR r.change_amount<0
           OR (r.applied_amount=0 AND r.payment_id IS NOT NULL)
           OR (r.applied_amount>0 AND
              (p.id IS NULL OR p.status<>'POSTED' OR p.method<>'CASH'
               OR p.amount<>r.applied_amount OR p.customer_id<>i.customer_id
               OR a.payment_id IS NULL OR a.amount<>r.applied_amount
               OR a.customer_id<>i.customer_id))
         THEN 1 ELSE 0 END),0) AS receipt_mismatches
FROM invoices i
JOIN customers c ON c.id=i.customer_id AND c.assigned_rep_id=i.rep_id
JOIN users u ON u.id=i.rep_id AND u.role='SALES_REP' AND u.is_active=1
LEFT JOIN invoice_receipts r ON r.invoice_id=i.id
LEFT JOIN payments p ON p.id=r.payment_id
LEFT JOIN payment_allocations a ON a.payment_id=p.id AND a.invoice_id=i.id
WHERE i.id=:invoice_id AND i.rep_id=:rep_id AND i.posting_channel='ONLINE'
GROUP BY i.id;
```

### Q14 v2

Parameters: `:invoice_id`, `:rep_id`. SQL SHA-256: `79401245382b0d92bd564b4c54330f4d4ccaa747321714ee62248b53b195c1ce`.

```sql
SELECT i.currency,i.posting_channel,i.payment_type,
       COALESCE((SELECT SUM(a.amount) FROM payment_allocations a
         JOIN payments p ON p.id=a.payment_id
         WHERE a.invoice_id=i.id AND p.status='POSTED'),0.00)
         <=i.grand_total AS posted_allocations_within_original,
       EXISTS(SELECT 1 FROM invoice_voids v WHERE v.invoice_id=i.id) AS legacy_void,
       EXISTS(SELECT 1 FROM historical_customer_returns r
              WHERE r.invoice_id=i.id) AS historical_credit,
       EXISTS(SELECT 1 FROM hybrid_returns r WHERE r.invoice_id=i.id) AS linked_credit,
       EXISTS(SELECT 1 FROM hybrid_voids v WHERE v.invoice_id=i.id) AS terminal_hybrid_void
FROM invoices i
JOIN customers c ON c.id=i.customer_id AND c.assigned_rep_id=i.rep_id
JOIN users u ON u.id=i.rep_id AND u.role='SALES_REP' AND u.is_active=1
WHERE i.id=:invoice_id AND i.rep_id=:rep_id;
```

### Q15 v1

Parameters: none (fixed singleton IDs, if any, are part of the reviewed statement). SQL SHA-256: `f409d5c81083e95a1752a936cab91aac2d1fda83255d3a7db7232934200998bb`.

```sql
SELECT id, enabled, sale_posting, return_posting, custody, version
FROM hybrid_operating_state WHERE id = 1;
```

## Parameter, result and stop-condition contract

Every row inherits: no missing/unknown parameter, no native placeholder reuse, no automatic retry/page expansion; stop on hidden/truncated results, drift, unsupported dialect or absent cost/grant evidence. Maximum output is a ceiling, not a claim about scanned rows. Reaching a multirow LIMIT requires STOP/UNKNOWN for completeness, not a silent next page. All access paths and observed cardinalities remain operational UNKNOWN.

| Version | Parameter/cohort contract | Maximum output / specific fail condition |
|---|---|---|
| Q01 v1 | Independently approved endpoint/account before connection; no business IDs | 1 M row. Any mismatch in schema/host/port/server-id/datadir/vendor/version/account stops all business reads. USER and CURRENT_USER differ in meaning; both must match attestations. |
| Q02 v1 OPTIONAL | Exact schema only | 200 M rows; >=200 or incomplete visibility blocks completeness; schema-wide metadata scan needs separate approval. TABLE_ROWS is approximate. |
| Q03 v1 | Verified named-schema journal exists with expected columns first | 100 M rows; >=100, unexpected filename, non-applied status or unmatched raw checksum stops dependent reads. Ordered filenames do not prove execution order or actual DDL. |
| Q04a/b/c v1/v1/v2 | schema and one exact owner-approved table per call; no wildcard/discovery | 100 M rows per call; >=100, missing types/scales/generation/collation/key order/actions or hidden definitions blocks acceptance. |
| Q04d v1 OPTIONAL | schema and one approved table; DBA visibility attestation | 200 M fingerprints; >=200, missing/NULL/hidden body or representation mismatch means UNKNOWN. Hashes are not behavioral proof. |
| Q04e v1 | schema/table and attested MariaDB CHECK metadata shape | 100 M rows; absent/hidden checks or unsupported dialect STOP. No fallback SQL or test INSERT to establish enforcement. |
| Q05 v1 | One approved rep_id | 1 B row; require active SALES_REP, not existence alone. |
| Q06 v1 OPTIONAL | Approved rep_id and exact after_id; even 0 requires explicit page approval | 25 B rows; assignment sample only, never customer credit-amount/full-cohort approval. |
| Q07 v1 | One exact owner-provided exact_sku; compare returned ID/SKU to mapping | <=2 B rows by limit, exactly 1 expected via UNIQUE(sku). No row, ambiguity/inactive product or version drift STOP. Case-insensitive SKU collation needs owner confirmation. |
| Q08a v1 | Fixed settings id=1 | 1 B row; require PRODUCT plus active WAREHOUSE location; missing/null/wrong type STOP. Not warehouse conservation evidence. |
| Q08b v1 | One approved rep_id matching Q05 | <=2 B rows, exactly 1 active REP location expected under UNIQUE(rep_id); ownership/type mismatch STOP. |
| Q08c v1 | Same rep_id and approved product_id from Q07 | 1 B row; negative balance STOP; missing row UNKNOWN. No invented zero balance. |
| Q09a v1 | One owner-approved promotion_id | 1 B row; missing/inactive/unpublished or unexpected master/current_version is a binding blocker. Master version is not revision identity. |
| Q09b v2 OPTIONAL | selected_master = Q09a master. direct_product, member_string_probe/filter = same canonical product ID string. member_numeric_probe/filter = same digit string as JSON numeric text, never float | 25 B rows; global OR/JSON scan may be expensive. Missing selected revision/wrong product, numeric-only member, unproven numeric precision, truncation or conflicting rules blocks acceptance. |
| Q09c v1 OPTIONAL | Explicit GLOBAL scope, no IDs | 1 B count; scans masters/unique revision probes. Any dangling published pointer blocks completeness; no approval by implication from Q09b. |
| Q10 v1 | Same approved promotion_id as Q09a | 25 B revisions; descending unique-index range expected. Missing bound revision/truncation/version disagreement blocks full history proof. |
| Q11 v2 local | Approved date/window in Asia/Colombo and complete authorized Q09 candidates | No SQL/output growth. Inclusive calendar dates and NULL open bounds require owner acceptance. Unrelated broken pointers/unperformed conflict scan mean UNKNOWN. |
| Q12a v2 | Same rep_id, product_id and location_id established by Q05/Q07/Q08b/c | <=1 B row, but all pair-ledger rows may be summed. Missing state, wrong/inactive scope, negative or unequal balance STOP. Missing balance yields no row/UNKNOWN. |
| Q12b v1 OPTIONAL | Explicit GLOBAL legacy guard scope | 1 B row/2 flags; no-match probes can scan all retained tables. Any positive flag blocks current engine; absent scan approval/evidence means guard UNKNOWN. |
| Q13 v2 | One approved canonical invoice_id UUID and owning rep_id with current customer assignment, ONLINE cohort only | <=1 F aggregate row; receipt fan-out UNKNOWN. No row = unavailable/out-of-scope; zero receipts = no evidence, not PASS. Any mismatch blocks compatibility. |
| Q14 v2 | Same independently approved invoice_id/rep_id/current customer assignment; required Hybrid tables/journal verified first | <=1 F row; per-invoice allocation sum UNKNOWN cost. Flags/classification only; unsupported settlement or unreconciled history escalates, not automatic balance approval. |
| Q15 v1 | Fixed id=1 only after 025 metadata/journal verification | 1 M/B row; missing controls or any enabled operational flag STOP. No financial_posting_hold column exists here. Source/private hold proof is a separate future exact-file attestation. |

Q04 table calls require an explicit per-table owner/DBA allowlist. Minimum candidate source tables are those referenced by the selected queries; no entire-schema business-row permission is implied. For 007 specifically include customers(city_town), location_towns(name,name_key,district_id), their character/collation/generation/FK metadata and actual 007/008 journal state. For Hybrid invariants the DBA must separately attest the complete tables/constraints/triggers defined in 025-027; a singleton control row or table-name count alone is insufficient. Do not add read access to whole snapshot JSON/financial rows for metadata verification.

## Interpretation and financial limits

Q09a/Q09b/Q10 bind a stable master plus its current immutable revision: promotion_versions.promotion_id=promotions.id AND version_number=current_version; revision.id and mutable master.version are distinct. Q09b LEFT JOIN preserves the selected broken pointer, not unknowable membership of unrelated broken pointers. Source uses string JSON IDs; numeric membership is a diagnostic data-shape blocker, never an eligibility policy change. Unknown-version large-numeric JSON precision is not stable-ID evidence. Without optional global evidence or independent DBA pointer-integrity/complete conflict attestation, no global no-conflict claim is possible.

Q11 v2 uses active masters/current revisions and inclusive start_date/end_date in Asia/Colombo. Retained valid_from_utc/valid_until_utc are not current authority after migration 012. Do not invent a combined UTC/calendar policy or reject solely for differing retained UTC fields. Pairwise intervals overlap when (endA IS NULL or startB IS NULL or endA >= startB) and its converse. Do not add historical revisions as simultaneous offers. Conflicting monetary/mixed rules, invalid binding/threshold/free quantities and multiple eligible rules remain blockers under the existing resolver.

Q12a ties active rep/location to the balance/product and compares product-only batch_id IS NULL ledger SUM. Missing balance/runtime zero-default, ledger consistency and stock sufficient for a particular sale are separate claims. Q12b mirrors GLOBAL source guards; narrowing it would not prove those guards. No repair, batch reactivation, deletion or synthetic batch is permitted.

Q13/Q14 preserve invoice ownership plus CURRENT customer representative assignment from Invoices::scope and HybridInvoiceReader; do not add a customer-active requirement to historical reads. Active SALES_REP identity is checked. This SQL is not authentication and does not confer an admin's broader scope. Q13 uses PK(payment_id,invoice_id) to prevent allocation join multiplication, checks POSTED CASH, exact same-customer allocation/applied amount and receipt received=applied+change. Zero applied requires no payment. An empty receipt cohort is not successful reconciliation. Extra cross-invoice allocations/orphan payments require separately approved broader checks; legacy BANK_TRANSFER/PENDING outside this ONLINE receipt convention is not automatically corruption.

Q14 is classification only: original payable comparison and existence flags do not validate debt, Refund Due, seals, original-unit returns, custody, net-credit projections or terminal-void correctness. Authoritative balances also consider historical applied_value, linked net_credit, legacy/terminal voids and actual posted cash/noncash capacity. Unsupported linked/noncash/historical/legacy-void combinations remain blockers. Change is not collection; later-voided collections remain refund liabilities. Physical refunds are unavailable, never reported as zero. Do not invoke locking review/balance/posting services as an inventory shortcut. Separate autocommit observations are not a coherent financial snapshot; any drift invalidates comparisons.

## Owner / DBA future-access checklist - NOT APPROVED

All operational identifiers and approvals below are intentionally blank. Expected financial policy is specified separately from observed data. No names/IDs from fixtures, preview, display labels or another environment may fill these fields.

| Required approval field | Owner/DBA supplied value |
|---|---|
| Owner approval reference, approver, signature/date | |
| Named operator and escalation/DBA contact | |
| Named host-restricted read-only inventory account / connection origin | |
| Approved server DNS name and independently verified IP | |
| Exact port / schema | |
| Independent vendor/version, hostname, server_id, datadir and effective-account attestation reference | |
| Endpoint/identity evidence source and signer; how identity is verified before business reads | |
| Effective direct/inherited grants, metadata visibility and CHECK/trigger-definition attestations | |
| Exact approved query IDs AND versions AND SQL hashes | |
| Exact parameter values per selected statement and permitted repetition count | |
| Approved representative IDs and current-customer assignment cohort | |
| Approved product IDs, exact SKUs, master IDs, revision IDs/version numbers | |
| Approved invoice UUIDs / allowed ONLINE receipt cohorts | |
| Approved metadata table allowlist; no wildcard business reads | |
| Business date/activation window in Asia/Colombo (analysis only) | |
| Optional queries separately selected with reason and cost evidence | |
| DBA index/volume/fan-out evidence and maximum estimated scanned rows per query | |
| Maximum result rows/bytes per statement and total output budget | |
| Statement deadline / total batch deadline / authorized window | |
| Server-side resource/cancellation evidence; named cancellation/escalation operator | |
| Output classification, approved recipients, private notes location | |
| Credential custody; retention deadline and deletion verification rule | |
| Explicit target-specific exception to 3306 OR 3309 prohibition, if required | |
| Stop-condition acknowledgement and escalation procedure | |

Proposed ceilings from earlier planning (2 seconds/statement, 30 seconds/batch) are not proven guarantees or owner approval. The DBA must approve actual limits, scan/fan-out budgets and version-compatible server-side cancellation/resource controls before any query. Client timeout alone cannot prove termination. No PROCESS/KILL privileges, session changes or helper installation are assumed. No automatic pagination/retry, concurrent batches, query discovery, EXPLAIN, transactions, row/table/advisory locks or stored routines. Plain autocommit SELECTs can acquire metadata locks and consume resources; any unexpected impact stops the batch.

Minimal separate request, to be completed and signed later: "Approve [named operator/account] on [exact independently attested IP/port/schema/account] only for [IDs/versions/hashes/parameters/cohorts] during [window], within [scan/time/output budgets], using [cancellation/privacy/retention controls]. Explicitly approve a narrow exception to the current port prohibition for [one exact endpoint on 3306 OR 3309, if applicable]. All writes, explicit locks, copies/exports, migrations, repairs, binding/config changes, adapters, deployment and activation remain excluded." Blank fields, this local checkpoint, a DBA conversation or elapsed time are not approval. Both ports remain forbidden unless that later explicit target-specific exception is granted.

No customer personal fields, payment references, product costs, credentials, snapshot/offline payloads or raw financial records may enter these documents. Minimal necessary IDs/counts/flags remain confidential. No database/result dumps, exports/imports/copies, screenshots, browser profiles or public/shared logs. Owner must explicitly define allowed minimal finding notes, recipients, retention and verified deletion. This stage contains source text and blank templates only.

### Real product/promotion binding approval template

| Field | Glucose Buy 6 Free 1 | Suwada Kudu Buy 12 Free 1 |
|---|---|---|
| Actual product ID / exact SKU / active status / price and product version | | |
| Actual active promotion master ID / current_version / master.version | | |
| Actual immutable revision ID / version_number / active snapshot | | |
| Assigned revision.product_id matches selected product | | |
| Expected type / qualifying paid quantity / free quantity | BUY_X_GET_Y / 6 / 1 | BUY_X_GET_Y / 12 / 1 |
| Observed type / qualifying paid quantity / free quantity | | |
| Approved inclusive start_date / end_date / NULL-bound decision (Asia/Colombo) | | |
| Applicable conflicting/overlapping master/revision IDs, monetary/mixed membership | | |
| Current price/version, representative/location and assignment evidence | | |
| Complete conflict/pointer evidence reference / reviewer / owner sign-off | | |

Owner approval of a mapping is evidence only; writing bindings or modifying/publishing promotions requires separate authorization. Approved discounts, qualifying/remainder/free/replacement rules and original Invoice UI must remain unchanged.

## Unresolved migration / packaging gates - no remediation

Static development-file comparisons only; no deployed files or installed journal were accessed. `Migrator::run` defines schema_migrations with version VARCHAR(150) PK, checksum CHAR(64), status VARCHAR(10), nullable applied_at_utc DATETIME(6). It hashes raw migration-file bytes and refuses mismatched/non-applied entries. DDL is not transactional. Q03 cannot prove execution order, trustworthy journal contents, complete trigger definitions or absence of partially applied DDL. Never run Migrator/fresh installation as an inventory probe.

007 compares generated location_towns.name_key under Unicode collation with LOWER/TRIM of customers.city_town in the older charset/table context. The checked-in preparation changes only customers.city_town to utf8mb4_unicode_ci BEFORE 007 in a verified new empty installation. Later 008 binary-collation joins cannot repair a chain stopped inside 007. Prior source reports reproduce error 1267 under tested defaults; actual target collation/status is UNKNOWN. Never apply this fresh-install preparation to a populated operational database, rewrite original SQL/journal rows, skip failed statements or propose a live repair here.

| Artifact | Git blob raw SHA-256 | Current development bytes / pinned expectation |
|---|---|---|
| 007_customer_locations.sql | 161effa594c58f45223bf68161ba3a6f3b623dc34bafff0a33080008a337fa9c | f46167f2b163ed69b351e61cb0bebfc30804db0713d1e22973f6e96d7a94800a |
| 025_hybrid_invoice_snapshots.sql | 662054eb3025354eff03c9da928e3b618fa433a4ca69a8b6632342924d45f2c1 | same as blob and both contracts |
| 026_hybrid_linked_returns.sql | 912b831ea2d3674bee50ef7b6dfd3852683fec2b00c921c5f419f5457a137d5f | same as blob and both contracts |
| 027_hybrid_custody_dispositions.sql | 1e00ad4388f60c4b130d560d6cbcebb83489e8d43c9033bfc4d6e54faca24fac | same as blob and both contracts |
| 006a_customer_locations_compat_v1.sql | 1ec24563527db3162cf7630390a911389151cc765039a59df2d4880f2fd599c0 | preparation expectation matches current source |

All 27 current working migration hashes match `database/fresh-install/contract-v1.json`; Git blob hashes for 001-024 differ from those pinned Windows working bytes. This is a packaging blocker, not proof a clean checkout/deployment reproduces the package. `tests/hybrid-stage6b-install-contract.json` agrees on the checked 007/025-027 hashes. The installation contract hashes to `a9e8f370fc2719d6e4476467d9e218e6edead003f96ce5399927bb959c9eb251`, matching `HybridFreshInstaller::CONTRACT_SHA256`. No line endings, SQL bytes, contracts or history were changed. Never replace an installed raw checksum by a normalized-content hash.

025 creates 9 tables/20 triggers for controls, commands, immutable invoice/line/segment/unit snapshots, seals and results. 026 creates 8 tables/14 triggers for linked commands, original-unit returns/allocations, voids, projections/cursors/results. 027 creates 4 tables/7 triggers for custody receipts/events/lines/state. Counts are static expectations, not target evidence. Verify types/scales/composite FKs, uniqueness, late-child/seal/immutability guards and custody event/projection rules, not merely names. PdoHybridInvoiceContext::schemaReady checks 025's raw hash; HybridInvoiceBalances::schema checks 026; custody support depends on 027. Missing/renamed/mismatched journal/schema is a blocker, never authorization to install anything or export JSON snapshots to inspect it.

Q04a/c/e and 007 checks need customers.city_town, location_towns.name/name_key generation/collation, applicable district metadata and actual 007/008 journal states. Defaults, database collation or the presence of a later migration alone do not establish a healthy 007. Use independently authorized deployed-file hashing and metadata evidence later; do not rewrite applied checksums or run preparation on populated data.


## Unresolved runtime, language, printing and recovery gates

1. **007 collation mismatch:** operational journal/actual column/generation/collation compatibility unverified. No original-byte/history rewrite, no populated-database fresh-install preparation or assumed later-008 repair. Remediation needs a separate reviewed plan/rehearsal/approval.
2. **LF/CRLF packaging:** canonical release bytes, raw checksum manifest and installed-journal comparison unresolved. Do not normalize deployed SQL or replace raw checksums with normalized hashes. No installation contract changes here.
3. **025-027:** exact raw journal hashes/statuses plus complete schema/FK/CHECK/uniqueness/immutability/late-child/seal/custody trigger behavior must be independently verified. Their absence is a blocker, never install authorization.
4. **Actual bindings:** complete the blank owner mapping from actual target evidence; immutable revision/product/master identity, prices, inclusive Colombo dates and complete applicable-conflict evidence remain unverified. No synthetic identities, seed/import replacements or changes to fit tests.
5. **Operational runtime/security and rollback:** HTTP and CLI effective service account, exact instance/schema, least-privilege column/worker grants, executable/profile paths and existing role/auth/CSRF/customer/rep scopes require acceptance. Inventory-account grants are not writer/worker grants. IPC requires MAC/authentication, replay/expiry protection, restrictive ACLs, fixed command allowlists, bounded queue/process/lock/timeouts, supervision and no secret/payload logs. Current disposable guards stay intact; no operational adapter is enabled here. Read/review-only application services take FOR UPDATE locks and roll back: that later mode needs its own explicit lock-taking scope and writer denials, distinct from this non-locking inventory.
6. **Native Sinhala:** named native reviewer must sign off qualified/remainder/free/replacement, sequential discounts/cap, payment/change/debt/Refund Due, uncertainty and errors at narrow widths. Software translation/screenshots alone are not approval.
7. **Physical printing:** owner-approved actual 58mm, 80mm and A4 printer/PDF workflows must verify Sinhala fonts/glyphs/wrapping, exact finalized snapshot totals, Bluetooth/driver/feed/cutter where applicable, reprints without posting and provisional/offline markings. No hardware acceptance is claimed.

Backup/recovery evidence is a later gate, not an action in this stage or an implied addition to read inventory. The owner must separately approve backup handling, custody/access, verification and restore rehearsal only into a newly named isolated schema; no data copies/exports occur here. A populated operational restore, schema remediation, account/grant change, adapter, deployment or genuine posting needs its own explicit scope.

Before future cutover/rollback approval: freeze new dispatch, drain/resolve IPC and ambiguous commands with the SAME UUID and existing result/receipt reconciliation; do not retry as a new invoice or reallocate official numbers. Preserve immutable snapshots, accepted payments/stock, signed offline/unresolved records and append-only return/void history. Reconcile journal/stock/collections/receivables before resuming. Code rollback cannot erase accepted work; never restore an old live snapshot over subsequent financial activity as routine rollback. Owner/DBA must approve monitoring, recovery operator, thresholds, stop/hold mechanism and reconciliation evidence.

## Static validation and checkpoint boundaries

Validation is limited to local source/document inspection, SHA-256 comparisons, SQL text structure/placeholder checks and Git diffs/status. Pre-staging validation passed 194 assertions: 23 versioned SQL blocks/hashes, all six retained correction areas, 27 working migration/contract hashes, 16 discarded-file absences, source gates/route guards, unchanged input hashes, documentation hygiene and exact branch/HEAD/three-file scope. No database-backed tests, application bootstrap, mocked SQL service execution or HTTP/browser probes are part of this stage. Prior Stage 6E-3 runtime/financial tests are historical evidence, not rerun acceptance.

The original /rep/invoices/new route, InvoiceController, invoices.php, product cards/cart/customer/payment controls, Hybrid discount/free-goods components, invoices.js/pos.js and styles remain unchanged from the approved application baseline. No separate Hybrid Invoice editor/checkout alias is restored; retained eligibility APIs and linked-return screens are shared business capabilities, not a second invoice editor. All 16 discarded exclusive files remain absent. PROMO_CHOICE_V1 pilot and signed legacy/offline contracts, Dashboard/Profile, financial engine/writer/snapshots/returns and SQL migrations remain unchanged. Source config enabled/sale_posting/return_posting/custody=false, financial_posting_hold=true, databases/bindings empty. These are static source statements, not claims about live database/private runtime state. No operational or localhost-main files were modified.

Local checkpoint allowlist ONLY:

- docs/hybrid-stage6e4-operational-preflight-plan.md (historical planning draft; superseded for execution by this specification).
- docs/hybrid-stage6e5-static-sql-review.md (historical source-only review; status/HEAD text describes that earlier stage).
- docs/hybrid-stage6e6-preflight-approval-package.md (this authoritative versioned package/checklist/report).

No source, SQL, configuration, private storage, database/credentials, logs/screenshots, browser profiles, IPC/runtime evidence or incidental files may be staged. The two earlier documents remain byte-for-byte unchanged; their raw input hashes are pinned above. Complete three-document diffs, source safety checks and the exact staged allowlist must pass before one local commit. Commit SHA and actual post-commit status are reported separately after creation, avoiding a self-referential hash. Expected result is a clean worktree with exactly these three documents in the local checkpoint. No push/merge/deploy occurs.

## Pass/fail and next separate approval gate

Static package PASS requires source-aligned SQL/columns/joins, unique native placeholders, exact version/hash inventory, explicit optional scan exclusions, blank operational identities, no secrets and only the three documentation paths staged. Operational inventory remains BLOCKED until every required owner/DBA field is completed and separately approved; even a fully completed package is not approval by itself. Full release/activation remains BLOCKED by the seven gates and recovery acceptance above.

STOP and escalate to the named owner/DBA contact for: identity mismatch, missing/unknown/excess grants, unsupported vendor/metadata/enforcement, missing or mismatched migration/schema/raw checksum, hidden definitions, unknown/unbounded cost, scan/output/time budget reached, cancellation uncertainty, concurrent data/version/assignment drift, missing scope/binding, truncated results, conflicts, non-reconciling balances/receipt flags, unexpected enabled posting controls, or any need to repair/write/lock/copy/export. Do not run later queries to work around an earlier failed gate. No automatic legacy checkout fallback or activation.

Next step is ONLY a future separately signed bounded non-locking inventory request using this specification's exact default query versions and owner parameters, independent identity/grant/DBA cost attestations and a named target-specific port exception if required. Optional Q02/Q04d/Q06/Q09b/Q09c/Q12b and all other broad scans remain excluded unless separately selected and approved. Verify endpoint identity first, metadata/journal before dependent business reads, collect minimal approved findings, stop/report mismatches without changes. Any later lock-taking review, migration/package repair, operational integration, deployment and limited activation/hold release requires further separate authorization and accepted isolated rehearsals, rollback, native Sinhala and physical-print evidence.

STOP after this package and one local documentation checkpoint. No operational access, SQL execution, feature activation, posting, migrations, UI/financial changes or new development is authorized.
