# Hybrid V1 Stage 1 calculation engine

This worktree contains only a new pure calculation service, its exception and
synthetic unit tests. It does not connect to any database, read operational
configuration, modify feature gates, post invoices, store snapshots or integrate
the PWA. It is not an activated financial workflow.

## API and trust boundary

`App\Services\HybridPromotionPolicy::calculate(array $request, array $products): array`

The request contains representative selections. The separate `$products` list
must come from an authoritative, independently authorized server resolver, not
from browser claims. Stage 1 receives these resolved values as pure inputs;
database lookup, active-date/revision selection, scope, gates and posting-time
locking are later integration responsibilities. No actual product IDs or current
operational offers are assumed or changed.

```php
$request = [
    'policy_version' => 'HYBRID_PROMOTION_V1',
    'channel' => 'ONLINE',
    'lines' => [
        [
            'product_id' => '101',
            'paid_quantity' => '8',
            'product_discount' => ['choice' => '25'],
        ],
    ],
    'invoice_discount_percent' => '10',
];
$products = [[
    'product_id' => '101',
    'price_version' => '71',
    'unit_price' => '100.00',
    'available_quantity' => '1000',
    'promotion' => [
        'id' => '501', 'revision_id' => '901', 'product_id' => '101',
        'type' => 'BUY_X_GET_Y', 'buy_quantity' => '6', 'free_quantity' => '1',
    ],
]];
$result = HybridPromotionPolicy::calculate($request, $products);
// Qualifying paid 6, remainder 2, actual free 1; P=50, I=75, payable=675.
```

All IDs, quantities, prices and rates are decimal strings. Paid quantities must
be positive canonical whole-unit strings within the existing signed integer
limit. Available quantities allow zero. Prices/totals must fit DECIMAL(15,2).
Money outputs have exactly two decimal places. Arrays are passed by value and
the service never mutates request, configuration or legacy result arrays.

Request fields are strictly allowlisted. `policy_version`, `channel` and an
ordered list of 1-100 distinct product lines are required. Product discount
omission means the initial No Discount selection; an explicit null is invalid.
Product `choice` is `0`, `25`, `30`, `35` or `other`. Only `other` takes `percent`,
validated from 0 through 50 with at most two decimal places. Nonzero discounts
require explicit selections. Invoice rate omission means 0; explicit invalid
values are rejected, and its maximum is 50. No browser monetary totals or free
quantity claims are accepted. Stock and authoritative revisions have to be
provided explicitly; no implicit no-promotion fallback is used when the
`promotion` field is missing. Set it to null when no active offer applies.

Promotion input is the minimal normalized revision shown above. Generic
Buy-X-Get-Y arithmetic uses that revision, never a product name or hardcoded
operational ID. Corrected Glucose and Suwada Kudu quantities are represented by
approved 6/1 and 12/1 configurations respectively. The engine rejects applicable
PERCENTAGE/FIXED promotions, pooled mixed markers, ambiguous rule lists and
revision/product mismatches. Full lookup and overlap resolution stay server-side
in a later adapter.

## Limited stock replacement

An affected request line must explicitly contain
`replace_free_entitlement => true`. Top-level `replacement_discount` is then
required, for example `['choice' => '25']` or
`['choice' => 'custom', 'percent' => '12.50']`.

This uses the unchanged `BenefitPolicy::validate()` source: choices 25/30/35/custom,
existing custom 0-100 validation, no default replacement rate. The distinct
product/invoice 50% limits do not change the legacy replacement custom-rate
validator. Every replacement cent is included in the strict combined cap.

The line must be entirely complete qualifying groups, with no positive selected
manual product rate, paid stock fully available and no residual stock available
for its positive free entitlement. Remainder-bearing lines and partial free
shortages are rejected. Replacement when stock can fulfill the entitlement is
also rejected. No automatic fallback occurs.

The engine passes only the explicitly replaced cohort, in original input order,
to unchanged `BenefitPolicy::apply(PROMOTION_DISCOUNT_INSTEAD)`. That preserves
full paid-line bases, one explicitly selected rate, aggregate half-up rounding
and largest-remainder allocation. It does not apply the suppress-all mode to
unrelated Hybrid lines. No actual free units are delivered for the replaced
entitlement. Unsupported independently rated replacement cohorts fail strict
input validation rather than inventing a new rounding scope.

## Result, rejection and rounding

The deterministic result includes policy/currency/channel/rounding identities;
original ordered lines and normalized promotion revisions; paid, qualifying,
remainder, entitlement, actual free and substituted quantities; required stock;
product choice/rate/base/amount; replacement choice/rate/full-line base/amount
and original policy/mode; post-product/replacement bases; invoice allocation;
segment amounts; original subtotal, component totals, strict cap limit and final
payable. `validation_findings` contains successful replacement explanations, or
is empty. No time, random ID, persistence or mutable system state enters the result.

Product discount is rounded half-up once per eligible remainder segment.
Replacement uses the original aggregate legacy calculation. `InvoiceDiscount::apply()`
calculates the sequential invoice discount and line allocation without modifying
that service. A fixed line allocation is further distributed to its segments by
largest remainders (original segment order for ties), without creating/removing a
cent. Segment/line/header components reconcile exactly. The return-position and
bounded unit-component algorithm from B1 is not implemented in this forward-sale
Stage 1 engine; it remains a later integration deliverable.

Let S/P/R/I be original subtotal, product, replacement and invoice discount cents.
Calculate I on S-P-R, then reject unless `2*(P+R+I) <= S`. The diagnostic maximum
is floor(S/2) cents. Actual free value does not enter the monetary cap. A 50%
request that rounds upward past an odd-cent ceiling is rejected, not clamped.

Rejections throw `App\Support\HybridCalculationException` with ordered `findings`
containing `code`, `field` and `message`. No usable partial result is returned.
Errors include invalid selections/identities/amounts, shortage/scope conflicts,
unsupported fields/policies/channels, promotion conflicts and cap violations.

## Isolated verification and remaining integration

Run from this worktree:

```powershell
& C:\xampp\php\php.exe tests/hybrid-promotion-policy.php
& C:\xampp\php\php.exe tests/run.php
```

The Hybrid test runner deliberately does not load application bootstrap or .env;
loading `App\Support\Database` is forbidden. Fixtures use synthetic IDs/revisions,
fake prices and supplied stock counts. It never creates operational invoices,
stock, payments, accounts or balances. `tests/database.php` is deliberately not
run: Stage 1 explicitly prohibits database connections and migrations.

No existing application/calculator/configuration file is changed. Unit coverage
checks legacy input/result immutability and source hashes. Later stages still
need the authorized resolver, transaction/scoping/gate integration, versioned
payloads and immutable persistence, original unit-position allocation, A1-E1
linked returns/custody/residual voids, UI/localization/printing/reporting,
concurrency/restore tests and separately approved activation. No new financial
policy assumption or automatic all-user rollout is introduced here.

## Recorded Stage 1 results

- `tests/hybrid-promotion-policy.php`: **20 cases / 13,533 assertions passed**.
  Coverage includes 360 independent single-line oracle combinations and 120
  multi-line/cohort oracle combinations, plus named boundary/conflict/legacy cases.
- `tests/run.php`: **12 foundation checks passed**.
- `node --test tests/invoice-discount-domain.js`: **4 tests passed**.
- PHP lint: **3 new PHP files passed**.
- Original checkout retains the same three pre-existing documentation changes;
  no operational source/configuration/migration file changed. Protected legacy
  source content matches the isolated branch; two existing files differ only in
  Git checkout line endings. The legacy calculators remain unmodified in Git.
- The worktree has no `.env`; Hybrid tests use a standalone pure autoloader and
  never load application database configuration or create posting objects.

Financial examples verified by the tests:

| Case | Original subtotal | Product | Replacement | Invoice | Payable/result |
| --- | ---: | ---: | ---: | ---: | --- |
| 8 paid at 100, configured 6/1, remainder at 25%, invoice at 10% | 800.00 | 50.00 | 0.00 | 75.00 | 675.00; actual free 1 |
| 12 paid at 100, configured 12/1, full replacement selected at 25%, invoice at 10% | 1,200.00 | 0.00 | 300.00 | 90.00 | 810.00; actual free 0 |
| 1 nonpromotional unit at 251, product at 25%, invoice at 10% | 251.00 | 62.75 | 0.00 | 18.83 | 169.42; half-up invoice rounding |
| 100.00 discounted at 50%, plus 0.01 undiscounted | 100.01 | 50.00 | 0.00 | 0.00 | 50.01; cap passes |
| 100.01 discounted at 50% | 100.01 | 50.01 requested | 0.00 | 0.00 | Reject; no clamping or partial result |

Stop after Stage 1. No database verification, migration, posting integration,
activation, return posting, live financial test or rollout has been performed.
