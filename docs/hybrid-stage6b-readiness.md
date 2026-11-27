# Stage 6B isolated remediation and readiness

> **Superseded discount behavior - 27 November 2026:** This historical stage record retains its original examples, test counts and evidence. Any instruction or example applying invoice discount to a line with free entitlement or nonzero product/replacement discount is superseded by the [approved eligibility correction](hybrid-stage6e2-original-invoice.md#approved-eligibility-correction-and-owner-verification---27-november-2026). Such lines now receive invoice discount 0.00; a 0% product selection alone does not exclude a line. Glucose 8 at 100 with remainder discount 50 remains payable 750 even at invoice rate 10%; adding 5 ordinary unpromoted units at 100 gives eligible subtotal 500, invoice discount 50 and total 1,200. Existing immutable snapshots and historical results are not rewritten; the strict combined 50% cap remains. Old screenshots and PASS counts do not verify the new rule.

26 November 2026. Isolated work completed. **No operational release, migration, activation or posting approval is implied.**

## Worktree and preservation

Worktree: `C:\xampp\htdocs\dharma chemical sales app\storage\hybrid-stage6a`.
Branch: `hybrid-promotion-v1-stage6a-integration`; HEAD remains `1f6385c`.
Main remains at that commit with its two pre-existing modified documentation files and untracked Hybrid policy document. Nothing was committed, staged, pushed, merged, reset, cleaned or deployed.

All application/configuration/assets and original migrations remain unchanged from Stage 6A. The non-test entries in `hybrid-stage6a-inventory.json` have zero hash differences. The combined reader suite additionally verified all 12 protected baseline files, including Dashboard, Customer Profile, shared CSS/translations and the original invoice document. The functioning preview picker correction and default-deny router/bridge were preserved. No New Invoice visual redesign was resumed.

Stage 6B adds exactly these ten source files:

- `database/fresh-install/006a_customer_locations_compat_v1.sql`
- `tests/hybrid-stage6b-install-contract.json`
- `tests/hybrid-stage6b-installer.php`
- `tests/hybrid-stage6b-migrations.php`
- `tests/hybrid-stage6b-foundation.php`
- `tests/hybrid-stage6b-historical.php`
- `tests/hybrid-stage6b-http-router.php`
- `tests/hybrid-stage6b-http-worker.php`
- `tests/hybrid-stage6b-http.php`
- `tests/hybrid-stage6b-http.js`

The two reporting additions are this document and `docs/hybrid-stage6b-inventory.json`. The inventory records exact SHA-256 values. No existing source file was modified in this stage. At closeout, Git shows 11 pre-existing tracked modifications and 95 untracked entries, including the 12 Stage 6B additions and preserved prior-stage files. Full porcelain status is retained in ignored evidence storage; untracked directories are represented according to normal Git status rules.

## Migration 007: cause, proposal and byte preservation

Original 006 creates `customers.city_town VARCHAR(100) NOT NULL DEFAULT ''` using `utf8mb4_general_ci`. Original 007 creates `location_towns.name_key` using `utf8mb4_unicode_ci`, then compares that generated expression with `LOWER(TRIM(customers.city_town))`. MariaDB raises 1267 even for an empty customer table. Original 008 cannot repair an installation that never completes 007.

The separate versioned preparation SQL changes only the empty installation's `city_town` collation to Unicode before original 007 executes. The district column remains unchanged because 007's district lookup uses the General collation. No original migration is rewritten, normalized, skipped, replaced or falsely marked applied.

The proposal is intentionally outside `database/migrations/` and is **not wired into `bin/migrate.php` or the application Migrator**. Its isolated runner requires explicit physical manifest binding and a newly empty installation before it creates an installation authorization record. Before the compatibility DDL it checks original raw file hashes, the exact completed 001-006 journal, complete table definitions and seed-data fingerprints. Two pinned metadata states cover General/Unicode schema defaults: an inherited default on `customer_phone_locks` differs between them. No unknown schema shape, populated pre-007 installation, changed checksum or unjournaled preparation is accepted.

The preparation has its own checksummed journal entry. An interruption after its sole DDL leaves `applying`; recovery is permitted only from an exact pinned pre/post state. The original 007 statements then run from their unchanged source, and completion is journaled only after every statement succeeds. An interrupted original 007 remains `applying` and cannot be blindly resumed. Recovery for that case is a new installation/restoration, not automatic destructive cleanup. Complete reruns preserve all journal rows and timestamps; the unchanged original Migrator also verifies all 27 original applied checksums afterward.

Raw SHA-256 values remain:

```text
Integration 007: f46167f2b163ed69b351e61cb0bebfc30804db0713d1e22973f6e96d7a94800a
Main 007:        161effa594c58f45223bf68161ba3a6f3b623dc34bafff0a33080008a337fa9c
```

The pre-existing line-ending difference was preserved. Operational/deployed journal bytes were not inspected because operational access is forbidden. Adopting an installation entry point, verifying deployed byte expectations, and adding contracts for other server versions remain release work. The pinned metadata was verified on MariaDB 10.11.16; MySQL execution was not tested.

**Results: 77 migration checks passed.** New installations with the explicit preparation execute original 001-027 successfully under both `utf8mb4_general_ci` and `utf8mb4_unicode_ci` schema defaults. Interrupt/recovery, repeatability, malformed/populated schemas, checksum changes and unjournaled changes were tested. This is a **compatibility-assisted clean installation PASS**, never an unassisted pristine-chain PASS. The unchanged original chain was separately reproduced as **FAIL 1267 under both defaults**, with 007 correctly left `applying`.

## Separate physical test environment

```text
Host:       127.0.0.1
Port:       63826
Run ID:     stage2c-af62b57e249943d08acc851d75ca18cc
Schema:     dharma_sales_hybrid_d08f59fdc9bf437ebb5c28ba45b5ad7f_test
Data dir:   <integration>/storage/stage2c-af62b57e249943d08acc851d75ca18cc/data
Runtime:    SELECT, INSERT, UPDATE, DELETE on that schema only
```

A newly initialized server and new application schema were used; no earlier backup or application database was imported. Additional newly named schemas hold migration/foundation cases on this same proved server. Test connections reject 3306/3309 before opening PDO and verify actual port, data directory, run identity and grants. DDL accounts are used only for disposable provisioning/migrations; financial runtime connections have schema-only DML grants. Source gates remain disabled and the financial posting hold remains true.

The separate historical fixture uses the existing policy's literal test schema name `dharma_sales_phase12_restore_20261118154343`, created empty on **63826**, with its own exact schema-only DML account and run identity. This is not a connection to the historical server or an import of historical data. All its customers, invoices, accounts and stock are synthetic.

Final closeout disabled all Stage 6B disposable controls, verified source holds/checksums and shut down only this instance. Generated credentials, databases, profiles and evidence remain ignored/unserved. No operational connection, backup, data, port 3306 or port 3309 was used. The owner's separate 63824/63825 preview remains running.

## Historical returns

The existing hardcoded posting suite was neither executed as an entry point nor retargeted. The adapter verifies its entire source SHA-256 and executes only the reviewed assertion body after excluding all original bootstrap/connection code. It refuses an unexpected connection call in that body. The existing application's historical-return test policy and account authorization remain unchanged.

Strict original assertions reproduced one stale document-text failure: the old test expects `Noncash return adjustment`, whereas the preserved main template displays `Return credit` for the same immutable applied-value snapshot. The adapter explicitly adjusts that one wording expectation; no price, settlement, refund, stock, authorization or idempotency assertion changes. The original suite file remains byte-identical. Do not describe the strict original wording assertion as passing.

**55 adapted legacy posting checks passed**, including permitted/denied actors, inactive accounts, missing/year-specific prices, forged prices, offline rejection, issue/replay, cash/noncash/forfeiture, later payments, credit limits, immutable records, stock, void liability and report reconciliation. Every table's business-row digest matched its pre-test value after rollback. Generated auto-increment counters are not transactional business evidence and were not asserted to rewind. The adapter can reuse only its own identity-proved empty rolled-back fixture; it never resets a populated schema.

## HTTP financial architecture

The new test-only prototype separates an authenticated HTTP ingress from the existing guarded CLI financial worker. Both use fixed server-side disposable configuration. HTTP uses the real Authentication, Session, CSRF and role checks; no synthetic `/test/session` login is used. A bounded route allowlist permits explicit Hybrid preview/issue and return preview/post only. Other financial/legacy/offline routes are denied.

The ingress signs a short-lived server-derived session/request context with a per-run key and sends it over a private stdin pipe to a fixed CLI script. No client supplies an actor identity, executable path, database target or policy switch. The worker verifies MAC/expiry, physically proves the database/grants, reloads the current account/password stamp and role, checks the allowed route, and invokes the actual invoice/return controllers and authoritative services. The real controllers retain CSRF checks. Product/offer resolution, fingerprints, gates, holds, immutable snapshots, transactions, command replay and audits are unchanged. Duplicate authenticated deliveries use existing command idempotency.

**36 HTTP checks + 2 invalid/expired handoff checks passed**, with 3 database backstops. Tests cover actual login, anonymous/CSRF/body-token rejection, representative scope, Glucose 6/8/12, Suwada 12, 25/30/35/Other, remainder discounts, strict cap, replacement, stale review, holds/disablement, changed commands, accepted replay after disabling, rollback after stock/payment, linked-return posting/replay/scope and unsupported routes.

The backstop independently confirms one invoice despite failed/duplicate deliveries, original payable/cash 675.00, outstanding 0.00, return Refund Due 67.50, net stock decrement 8 after one returned paid unit, and successful immutable invoice reconciliation. Refund Due is not a cash refund. The CLI guard was not removed or altered. Worker/config/context files are private test artifacts; their secrets are never returned through HTTP.

**This is an isolated production-architecture prototype, not production HTTP readiness.** `public/index.php` still has no production worker adapter. A deployment-compatible identity policy, worker supervision/timeouts, private IPC/key permissions, service configuration and explicit release review are needed before wiring it into the real entry point. The owner's default-deny demo was not used for financial tests or changed to permit posting.

## Targeted verification ledger

Final successful runs (counts are per suite, not a deduplicated grand total):

| Verification | Result |
| --- | --- |
| Compatibility-assisted migration / recovery | 77 checks |
| Original unassisted migrations | Expected FAIL 1267, both defaults; no false journal completion |
| Historical-return adapter | 55 checks; all-table row rollback; document-label adaptation disclosed above |
| HTTP financial prototype | 36 HTTP + 2 handoff checks; 3 database backstops |
| Checkout database | 17 cases / 141 checks, including concurrency and every posting-boundary rollback |
| Linked returns/custody database | 157 checks, A1-E1, concurrent consumption, 11 rollback stages and residual voids |
| Legacy invoice behavior | 26 checks |
| PROMO_CHOICE_V1 | 36 checks; original pilot config hash preserved |
| Stored documents | 64 checks / 18 bilingual immutable HTML cases |
| Financial/report oracle | 35 checks; S/P/R/I/net, payments, credits, residual void, stock and whole-invoice cohorts |
| Combined readers/controllers | 30 checks; zero business writes and protected hashes |
| Dashboard/Customer Profile Edge | 28 checks, including phone layouts and authorization |
| Actual front-controller HTTP Edge | 12 checks plus no-posting backstop; real login, documents/PDF and private reports |
| Existing owner preview smoke | 51 assertions, actual Review actions for Glucose 6/8/12 and Suwada 12, wrong binding rejected and six write-route probes denied |
| Pure promotion policy | 20 cases / 13,533 assertions |
| Integration foundations/offline rejection | 17 cases / 2,635 assertions |
| Pure return policy | 34 checks |
| General foundations | 12 checks |
| Original database foundation on newly installed isolated schema | 16 constraint checks, rollback, unchanged Migrator repeated, UTC/native prepares |
| Hybrid JavaScript domain | 5 tests |
| Syntax and whitespace | Seven new PHP files, new JS syntax and tracked diff checks passed |

Initial adapter development exposed test-harness mistakes in import scope, schema-default fingerprint coverage and reader return-shape assumptions; those were corrected and affected checks rerun successfully. No application calculation or validation change resulted. The two deliberately reproduced legacy/migration failures above remain separately reported.

Ignored evidence lives under `<integration>/storage/stage2c-af62b57e249943d08acc851d75ca18cc`, including `stage6b-migrations.json`, `stage6b-historical.json`, `stage6b-http.json`, `stage6b-foundation.log`, browser evidence and final Git status. Demo smoke evidence is referenced by `stage6b-preview-path.txt` and remains in the existing demo run directory. No generated evidence or secret was added to source inventory.

## Readiness matrix and next stage

| Area | Status | Remaining condition |
| --- | --- | --- |
| Stage 6B isolated implementation/evidence | PASS | Stop at this stage |
| Explicit fresh-install compatibility proposal through 027 | PASS | MariaDB 10.11.16, both tested schema defaults |
| Original unassisted migration chain | FAIL | Original 007 still fails 1267; unchanged by design |
| Operational installer adoption/deployed checksum match | BLOCKED | Verify deployed raw bytes and approve release integration; no operational inspection performed |
| Historical financial policy regressions | PASS | Synthetic adapter; document wording adaptation disclosed |
| Strict original historical document-label assertion | FAIL | Stale expected wording, not a financial policy failure |
| Authenticated HTTP/CLI isolated architecture | PASS | Test-only authenticated private-pipe prototype |
| Production HTTP financial readiness | BLOCKED | No real entry-point adapter/approved production identity policy; operational holds retained |
| Dashboard/Profile/Hybrid/legacy/offline compatibility | PASS | Tested scope described above |
| Existing owner preview | PASS | `http://127.0.0.1:63825/`; actual UI Review succeeds, all posting remains denied |
| Sinhala native/full-interface review | NOT TESTED | Bilingual HTML checks do not replace native review |
| Physical Bluetooth/58mm/80mm/A4 printing | NOT TESTED | Hardware acceptance remains outstanding; no new physical output claimed |
| MySQL and other MariaDB versions | NOT TESTED | Exact schema contracts deliberately fail closed outside tested shapes |
| Deployment, live activation and genuine posting | BLOCKED / NOT AUTHORIZED | Separate owner approval and operational preflight required |

Recommended next stage: review the isolated installer/IPC proposals and their release integration, complete native Sinhala and physical printer acceptance, then prepare a separately authorized release preflight. Do not automatically perform any of those steps, merge into main, activate a gate or post a genuine financial record.
