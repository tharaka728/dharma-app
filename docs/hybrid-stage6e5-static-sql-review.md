# Stage 6E-5 - static SQL safety and schema compatibility review

Baseline: `362d36983939ffb53a637861941343d372e2cd51`; branch `hybrid-promotion-v1-stage6e2-original`; worktree `storage/hybrid-stage6e2-original`. Input: `hybrid-stage6e4-operational-preflight-plan.md`, unchanged. Reviewed all **22 proposed SQL statements**, their subqueries, and Q11's local-analysis step. **No SQL/EXPLAIN, database connection, operational API/HTTP request, migration, export or application bootstrap was executed.** Corrections are text only. No permission to execute them is implied.

## Findings and evidence limits

All proposed application table/column names exist in the checked-in migration chain. No business-column renaming is justified. Nevertheless:

- Q09b can omit a broken current pointer and assumes string-typed mixed members; LIMIT 25 does not establish absence of conflicts.
- Q12a relies on the caller supplying a matching representative/product/location instead of tying them together in SQL.
- Q13 omits payment-allocation correspondence and returns indistinguishable zero counts for absent/out-of-scope invoices versus empty receipt cohorts.
- Q13/Q14 omit current customer assignment required by both `Invoices::scope` and `HybridInvoiceReader::read` for representative reads.
- Q14 is classification, not debt/refund/seal/return reconciliation. Q15 does not expose an application financial-posting-hold column.
- Trigger visibility, metadata dialect and all actual data volumes/plans/costs remain unverified.

Source evidence: migrations 001, 006-014, 017, 021, 025-027; `app/Support/Migrator.php`, `HybridFreshInstaller.php`; the fresh-install and Stage 6B test contracts; `PdoHybridInvoiceContext::{source,balance,promotionGate,schemaReady}`; `Promotions::{select,quote,save}`; `HybridInvoiceResolver::resolve`; `Invoices::{scope,receipt,select,get}`; `HybridInvoiceReader::read`; `HybridInvoiceBalances::{sql,read}`. Files were read, never invoked.

PASS means static source agreement only. UNKNOWN means evidence cannot be established statically. BLOCKED means the proposed verification/access cannot presently proceed. **Every statement remains operationally BLOCKED by lack of access approval**, including PASS rows. No live-volume estimate or optimizer plan is claimed.

Types/syntax:

- IDs/versions are BIGINT UNSIGNED; UUIDs CHAR(36) ASCII binary; singleton IDs differ. Bind canonical decimal IDs as strings, never floats or JavaScript Number. Native prepares need distinct placeholder names at each position; no array bound to one IN parameter.
- Money is DECIMAL(15,2), rates DECIMAL(5,2), quantities signed BIGINT with appropriate checks. Preserve exact SQL arithmetic/decimal strings; do not cast amounts to FLOAT/DOUBLE. Hybrid product/invoice bounds stay 0-50; replacement/legacy bounds retain their approved contracts.
- Current authority is `promotions.current_version -> promotion_versions(promotion_id,version_number)`. Immutable revision `id`, its product binding, and mutable master `version` are separate identities.
- Migrator requires MariaDB >=10.4 or MySQL >=8.0.16 with enforced CHECKs. Prior packaging reports cover MariaDB 10.11.16, not all versions. Common joins/CASE/EXISTS/SUM/LIMIT and JSON_CONTAINS/JSON_QUOTE agree with source SQL. Actual metadata columns/visibility, function behavior and CHECK enforcement on the target remain UNKNOWN. MariaDB JSON may appear as text plus JSON validity checks; MySQL is not interchangeable with Q04e's MariaDB metadata shape.
- Autocommit statements are separate observations, not one coherent financial snapshot. Stop on drift. Plain SELECT can still take metadata locks and consume resources. LIMIT limits output, not scanned rows; a client timeout does not prove server cancellation.

## Query-by-query verdict and column permissions

M = restricted infrastructure/schema metadata; B = confidential business identifiers/rules/stock; F = restricted financial flags. Listed SELECT columns include predicates, joins, ordering and internal calculations. Corrected-query additions are explicit. Expected indexes are source expectations, not verified deployed index names or actual plans. **All actual scan costs and cardinalities are UNKNOWN** and require separately scoped DBA review; no operational EXPLAIN was used.

| Query | Static verdict | Required SELECT columns / metadata visibility | Expected path, result and suitability |
|---|---|---|---|
| Q01 | PASS; target identity UNKNOWN | No application columns; proposed system identity/version/time/SQL-mode expressions | One M row; candidate first read after separate connection approval. server-id is not unique identity; session settings do not prove all application connections |
| Q02 | PASS; visibility UNKNOWN | I_S.TABLES: TABLE_SCHEMA,TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION,TABLE_ROWS | Schema metadata <=200 M rows; estimates not exact counts. Hidden tables/truncation block completeness; metadata cost UNKNOWN |
| Q03 | PASS; deployed journal UNKNOWN | schema_migrations: version,checksum,status,applied_at_utc | PK(version), ordered <=100 M rows. Candidate bounded journal walk; filename ordering does not prove DDL execution order or actual schema |
| Q04a | UNKNOWN target dialect/version | I_S.COLUMNS: TABLE_SCHEMA,TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA,GENERATION_EXPRESSION | One table, <=100 M rows; needed for 007's generated key. Conditional, no automatic dialect fallback |
| Q04b | PASS shape; deployed indexes UNKNOWN | I_S.STATISTICS: TABLE_SCHEMA,TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME | One table, <=100 M rows; verify full key sequence. Metadata is not an optimizer plan |
| Q04c | NEEDS CORRECTION | TABLE_CONSTRAINTS: TABLE_SCHEMA,CONSTRAINT_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE; KEY_COLUMN_USAGE: CONSTRAINT_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,ORDINAL_POSITION,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME; correction adds POSITION_IN_UNIQUE_CONSTRAINT and REFERENTIAL_CONSTRAINTS(CONSTRAINT_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,DELETE_RULE,UPDATE_RULE) | Existing composite-name join sound; omits FK order/actions. <=100 M rows; corrected metadata check conditional on visibility |
| Q04d | BLOCKED for complete proof under minimal account | I_S.TRIGGERS: TRIGGER_SCHEMA,TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_STATEMENT | <=200 M rows; NULL/hidden hash/empty set cannot prove absence. DBA definition attestation required; do not grant write-capable TRIGGER just for visibility |
| Q04e | UNKNOWN vendor/version/visibility | I_S.CHECK_CONSTRAINTS: CONSTRAINT_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,CHECK_CLAUSE | MariaDB-style shape, <=100 M rows. Conditional on independent metadata-shape/enforcement evidence; no live INSERT test |
| Q05 | PASS | users: id,role,is_active,version | PK(id), <=1 B row. Require active SALES_REP, not existence alone; point-lookup cost still target-UNKNOWN |
| Q06 | PASS, partial sample | customers: id,assigned_rep_id,is_active,allow_credit_sales,credit_limit_enabled,credit_terms_days,version | FK-supporting assigned_rep_id index plus PK id range expected; <=25 B rows. Actual ordering/range plan UNKNOWN; no full customer/credit-limit audit |
| Q07 | PASS | products: id,sku,name,is_active,selling_price,version | UNIQUE(sku), <=1 B row if intact (LIMIT 2 detects ambiguity). SKU collation may be case-insensitive. No costs; candidate exact owner SKU only |
| Q08a | PASS | inventory_settings: id,stock_model,warehouse_location_id,version; stock_locations: id,type,rep_id,is_active | Singleton/PK join, <=1 B row. Require PRODUCT, active WAREHOUSE; NULL location is not PASS |
| Q08b | PASS, incomplete source prerequisites | stock_locations: id,type,code,rep_id,is_active | UNIQUE(rep_id), <=1 B row (LIMIT 2). Interpret type/active with Q05; original inventory omits required rep_operating_state |
| Q08c | PASS, missing-row caveat | representative_stock_balances: rep_id,product_id,quantity,version | Composite PK(rep_id,product_id), <=1 B row. Missing balance is UNKNOWN, not PASS; runtime zero-default requires matching zero-ledger/location evidence |
| Q09a | PASS | promotions: id,name,is_active,current_version,version | PK(id), <=1 B row. Selected master check, not all conflicting masters; version != assumed current_version |
| Q09b | NEEDS CORRECTION; scan UNKNOWN | Q09a columns plus promotion_versions: id,promotion_id,version_number,product_id,type,percent_rate,fixed_discount,buy_quantity,free_quantity,start_date,end_date,valid_from_utc,valid_until_utc,is_active_snapshot,mixed_product_ids,published_at_utc | UNIQUE(promotion_id,version_number) joins each current revision; OR/JSON membership can scan all masters despite <=25 B rows. Conditional diagnostic, no absence proof after truncation/broken unrelated pointers |
| Q10 | PASS, incomplete history | promotion_versions: id,promotion_id,version_number,product_id,type,buy_quantity,free_quantity,percent_rate,fixed_discount,start_date,end_date,is_active_snapshot,mixed_product_ids | Unique promotion/version index, descending range <=25 B rows. Candidate bounded history, not full-history/immutability proof |
| Q11 (no SQL) | NEEDS CORRECTION to interpretation | Q09/Q10 results, no extra grant | Local B interval analysis: current calendar authority, not combined retained UTC/calendar policy; historical versions are not simultaneous offers |
| Q12a | NEEDS CORRECTION | representative_stock_balances: rep_id,product_id,quantity; inventory_movements: location_id,product_id,batch_id,quantity_delta. Correction adds stock_locations(id,rep_id,type,is_active), users(id,role,is_active), rep_operating_state(rep_id) | Balance PK; corrected location PK/UNIQUE(rep_id); explicit ledger index(location_id,product_id,batch_id,id). One B output but full pair history may be aggregated; cost/fan-out UNKNOWN |
| Q12b | PASS engine-guard meaning; UNKNOWN cost | inventory_balances: quantity; inventory_movements: batch_id | Global quantity<>0 and batch_id IS NOT NULL lack guaranteed selective leading indexes; may scan entire retained tables. One B row/two flags; BLOCKED from default batch without separate global-scan approval/attestation |
| Q13 | NEEDS CORRECTION | invoices: id,rep_id,customer_id,posting_channel; invoice_receipts: request_id,invoice_id,payment_id,received_amount,applied_amount,change_amount; payments: id,status,amount,customer_id. Correction adds payments.method, payment_allocations(payment_id,invoice_id,customer_id,amount), customers(id,assigned_rep_id), users(id,role,is_active) | Invoice PK; receipts FK invoice_id index; payments PK; corrected allocation PK(payment_id,invoice_id). One F aggregate row, child fan-out UNKNOWN. Receipt-cohort check only, not full payment-capacity proof |
| Q14 | NEEDS CORRECTION to scope; classification only | invoices: id,rep_id,currency,posting_channel,payment_type,grand_total; payment_allocations: invoice_id,payment_id,amount; payments: id,status; invoice_voids/historical_customer_returns/hybrid_returns/hybrid_voids: invoice_id. Correction adds invoices.customer_id, customers(id,assigned_rep_id), users(id,role,is_active) | Invoice PK; allocation FK(invoice_id,customer_id) prefix; payments PK; void/historical PK(invoice_id); hybrid_returns UNIQUE(invoice_id,revision). One F row, child sums UNKNOWN; missing Hybrid schema blocks it |
| Q15 | PASS; operational state UNKNOWN | hybrid_operating_state: id,enabled,sale_posting,return_posting,custody,version | Singleton PK, <=1 M/B row after 025 checks. No financial_posting_hold column here: source/private hold policy is separate |

Metadata visibility follows effective/inherited privileges; generic SELECT does not guarantee complete definitions. Column grants do not enforce representative rows. Use approved query/parameter allowlists or already-approved scoped views. Never grant passwords, customer PII, costs, snapshot/payload JSON, schema-wide SELECT, FILE, PROCESS, TRIGGER, EXECUTE, DML/DDL/admin privileges or writer roles for this inventory. An absence of visible grants/definitions is not proof of safe privilege or missing structure.

## Source-supported corrections - TEXT ONLY

The Stage 6E-4 input is not edited. Corrected/additional queries require revised explicit approval of their columns, parameters, row scope and budgets. No IDs are populated. Distinct placeholders must be bound separately, even when their validated values must agree.

### Q04c - composite FK order and referential actions

Constraint names alone cannot verify composite identity or preservation of immutable parents. Retain the existing scoped joins and add ordinal/action metadata. Hidden metadata still blocks proof.

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

### Q09b - preserve selected-master evidence and expose JSON-type ambiguity

An inner join/product predicate can omit an explicitly selected master with a missing revision or wrong product. This LEFT JOIN retains that master. It is an **inventory diagnostic**, not the pricing eligibility filter. The application publishes mixed IDs as strings and uses JSON_QUOTE; migration 017 checks an array/6+1 rule but does not constrain every member's scalar type. Numeric-only membership is a data-shape blocker, not a new approved offer.

Bind direct_product and both member_string parameters to the same canonical string ID. Both member_numeric parameters contain the same digits as JSON numeric text; never pass through a float. selected_master is the actual owner-approved master ID. Numeric JSON comparison precision on an unknown server is itself UNKNOWN for large IDs: the numeric diagnostic is not stable-ID proof and cannot authorize binding. String identities remain authoritative.

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

Q09a/Q10 remain independent selected-master/history checks. Validate revision ID, product binding and version; h.version must not be assumed equal to current_version. The LEFT JOIN exposes the selected broken pointer, not unrelated broken pointers whose product membership is unknowable. A complete no-conflict conclusion therefore also needs DBA pointer-integrity evidence or separately approved optional Q09c:

```sql
-- OPTIONAL NEW GLOBAL SCOPE; not automatically part of Q09b approval.
SELECT COUNT(*) AS dangling_published_pointers
FROM promotions h LEFT JOIN promotion_versions v
  ON v.promotion_id=h.id AND v.version_number=h.current_version
WHERE h.current_version>0 AND v.id IS NULL;
```

Q09c reads promotions(id,current_version) and promotion_versions(id,promotion_id,version_number), may scan every master, and returns one confidential B count. It needs explicit new query-ID/global-scan approval. current_version=0 is unpublished, not proof that a required offer exists. No repair is proposed.

For Q11, `Promotions::quote` uses active product/current master and inclusive start_date/end_date in Asia/Colombo. Retained valid_from_utc/valid_until_utc are not current authority after the calendar-date amendment: do not invent a combined UTC/calendar policy or fail solely because legacy UTC fields differ. NULL bounds require explicit owner acceptance. Pairwise overlap is `(endA NULL or startB NULL or endA>=startB)` AND its converse. Assess the approved date/activation window, not server CURDATE; do not sum historical revisions as simultaneous offers. Reaching LIMIT 25 is incomplete evidence. The unchanged Hybrid resolver blocks multiple eligible rules, monetary/mixed offers, wrong stable master/product/6-or-12 threshold/free!=1, and inactive/unavailable bound offers.

### Q12a - tie representative, product and physical location together

This preserves the application's product-only SUM and nonnegative comparison, prevents cross-representative location substitution, and exposes required operating-state presence. A missing balance gives no row and remains UNKNOWN, not an invented zero/PASS. Runtime zero-default semantics require matching location/ledger evidence.

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

No warehouse inference: Q08a verifies settings/location, not warehouse balance conservation. A warehouse balance/ledger audit needs a separate approved cohort. Q12b mirrors the two GLOBAL legacy-batch guards in PdoHybridInvoiceContext::promotionGate; narrowing it to selected products would not verify that guard. Its two predicates may scan entire histories. Do not delete/reactivate historical batch structures or invent batches to obtain a PASS. Ledger consistency, missing balance/zero ledger and adequacy for a particular sale are different claims.

### Q13 - current scope, allocation correspondence and empty cohorts

`Invoices::scope` and `HybridInvoiceReader::read` require invoice rep ownership AND current customer assignment. Both original Q13/Q14 omit assignment. The correction does not add a customer-active condition to historical reads, which would change source behavior. The selected rep must be active. This query still requires independently approved IDs; it is not authentication.

`Invoices::receipt` and `PdoHybridCheckoutStore::postOfficial` create POSTED CASH payments for positive applied amounts and a matching same-invoice allocation. Zero applied has no payment ID. The allocation join has at most one row per receipt via PK(payment_id,invoice_id), avoiding multiplication of child sets. GROUP BY i.id distinguishes no accessible invoice (no result row) from an existing invoice with zero receipts (count 0, not PASS).

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

This checks the current ONLINE cash-receipt convention only. Legacy BANK_TRANSFER/PENDING collections outside it are not reclassified as corruption. It does not prove no additional allocations consume the same payment on other invoices, no orphan posted payment exists, or the history is complete. Broader payment-ID/cross-invoice scans need separate approval/DBA budgeting. Change is never collected revenue. Zero mismatches on an empty/incomplete cohort are not reconciliation.

### Q14 - preserve classification but enforce actual representative scope

Retain scalar aggregates/EXISTS to avoid multiplying allocations by receipt/return rows. All referenced Hybrid tables must pass schema/journal checks first; do not replace missing tables with fabricated zero or fall back to legacy posting.

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

This compares posted allocations with original payable. It does NOT validate outstanding debt, refund capacity, immutable seals, return projections or physical refunds. Source balances also consider historical applied_value, linked net_credit, legacy/terminal voids and actual POSTED CASH/noncash capacity. HybridInvoiceBalances::read rejects linked history coexisting with unsupported noncash/historical settlement or legacy full void, validates return projections and caps Refund Due by collected cash. Q14's existence flags do not establish those invariants. No physical-refund system exists; do not report it as zero. Full snapshot/original-net/allocation/custody reconciliation needs a separate approved scope, never invocation of locking review services during inventory.

## Remaining interpretation, performance and privacy risks

- Real owner mapping must establish product ID/SKU, active master ID, immutable revision ID/version, exact product binding, inclusive dates and Glucose 6+1/Suwada 12+1 quantities. Names, a version number alone, or synthetic fixture IDs are not authority or approval to modify bindings. No mapping is populated here.
- Repeated native named placeholders, mismatched product encodings, hidden current pointers, numeric JSON members, truncation, stale reassignment and concurrent price/rule changes can all invalidate an apparent match. Stop rather than widening queries or changing pricing policy.
- Q06 is assignment structure, not customer financial-limit validation: monetary credit_limit is intentionally omitted. Q08a is warehouse identity, not warehouse balance reconciliation. Q09/Q10 are bounded candidates/history, not a global integrity proof. Q13/Q14 are restricted flags, not full finance acceptance.
- Every real index/access path and child/history count remains UNKNOWN. Ask the DBA for actual index definitions, estimated scanned rows/aggregate fan-out and version-appropriate server-side resource/cancellation limits under a separate scope. Never infer low cost from output LIMIT 1/25/100. Q09 JSON OR searches, Q12 global probes and ledger sums, Q13 receipt fan-out and Q14 allocation sums need particular attention. No source-only review can establish their operational runtime.
- Keep identities/statuses/flags confidential even when no amounts/PII are returned. Do not expose credentials, customer personal fields, costs, references, snapshots, plan/result JSON or offline payloads. Do not dump/export/import/copy a database. Column grants alone do not enforce row scope or prove complete metadata visibility.
- ACTION_STATEMENT hashes are server-representation fingerprints, not raw migration hashes or reviewed behavior. Trigger names/counts/empty metadata do not prove invariants. Require independently reviewed vendor-specific definition/visibility attestation without giving the inventory account write-capable TRIGGER privileges. Metadata CHECK text does not prove runtime enforcement on an unverified version; no operational test INSERT is permitted.

## Migration and checksum findings

Static development-file comparisons only; no deployed files or installed journal were accessed. `Migrator::run` defines schema_migrations with version VARCHAR(150) PK, checksum CHAR(64), status VARCHAR(10), nullable applied_at_utc DATETIME(6). It hashes raw filename bytes and refuses mismatched/non-applied entries. DDL is not transactional. Q03 cannot prove execution order, trustworthy journal contents, complete trigger definitions or absence of partially applied DDL. Never run Migrator/fresh installation as an inventory probe.

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

## Minimal future operational-access approval request

Not submitted, granted or executable. The owner must complete and separately approve:

1. Named operator, host-restricted inventory account, exact IP/host/port/schema and independently supplied vendor/version, hostname/server-id/data-directory and effective-account identity. Verify endpoint allowlist BEFORE connecting and server identity BEFORE business reads; server-id alone is not unique physical proof. Disposable physical/CLI guards stay intact.
2. Exact query IDs/versions and values: schema/table allowlist, approved representative/current-customer cohort, actual SKU/product/master IDs, invoice UUIDs, business date/window and page bounds. Corrected columns/queries need the revised permissions above. Optional Q09c, global Q12b, broader history/financial scans and discovery require explicit selection and budgets, not implication. No synthetic values or automated fallback.
3. Named-schema column SELECT only, effective/inherited-grant and metadata-visibility attestation, DBA trigger/check definition evidence. No blanket SELECT, writer/migration credentials, mysql.* reads, write-capable role or TRIGGER grant to cure visibility. Missing evidence is UNKNOWN/BLOCKED.
4. Approved single-statement/batch deadlines, maximum estimated scan/fan-out/result rows, one-at-a-time execution, no automatic pagination/retry, and DBA evidence of acceptable actual indexes/volume/server-side resource/cancellation control. The prior 2-second/30-second proposal is not a proven cost guarantee. No EXPLAIN is included without its own approval.
5. Restricted recipients, minimal pseudonymous ID/count/flag notes, private output location, credential custody and explicit retention/deletion period. No personal/financial records in the report, no costs/references/snapshots/payloads, dumps/exports/imports/copies. Any deployed-file hashing permission must list exact paths and avoid secrets.
6. Stop on identity mismatch, excess/unknown privileges, unsupported metadata/version, missing/mismatched journal/checksum/schema, hidden definitions, truncation/drift, invalid binding/conflict, absent scope, unbounded cost/timeout, unreconciled flags, unexpected enabled posting controls or any need to repair/write/lock/copy. Return findings, not fixes.

Proposed separate request: "Approve ONLY [operator/account] on [exact target/identity] for [explicit corrected Q IDs/parameters] during [window], within [scan/time/output bounds], with [DBA attestations/privacy/retention]? Does this explicitly supersede the port prohibition for ONLY [named 3306 OR 3309 endpoint] for those non-locking SELECTs? Every other target and all writes, locks, exports/copies, migrations, bindings/config changes, adapters, activation and posting remain excluded." Blank fields, this report or elapsed time are not approval.

Later lock-taking review, operational adapter integration, packaging/schema remediation, backup/restore, deployment and eventual activation/hold release require separate authorization. This review does not enable an adapter, extend the original Invoice UI or alter financial rules.

## Exact changes and Git status

Added this stage only: `docs/hybrid-stage6e5-static-sql-review.md` (the requested Markdown filename; no literal backslash directory). The pre-existing untracked Stage 6E-4 input remains unchanged. Final expected status:

```text
?? docs/hybrid-stage6e4-operational-preflight-plan.md
?? docs/hybrid-stage6e5-static-sql-review.md
```

HEAD remains `362d36983939ffb53a637861941343d372e2cd51`; no tracked changes, staged files or commit. Source Hybrid configuration still declares enabled/sale_posting/return_posting/custody false, empty allowlists/bindings and financial_posting_hold true. No database state was inspected. No migration, contract, application configuration, UI or business policy was modified; local main was not changed. Stop after this source-only review.
