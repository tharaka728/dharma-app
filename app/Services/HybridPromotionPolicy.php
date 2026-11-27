<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\HybridCalculationException;
use App\Support\InventoryException;
use App\Support\ProductException;

/**
 * Pure Stage 1 arithmetic. No PDO, configuration files, clocks, gates or posting.
 * The caller must independently resolve/authorize the authoritative products;
 * never populate $products from a browser's financial claims.
 */
final class HybridPromotionPolicy
{
    public const POLICY = 'HYBRID_PROMOTION_V1';
    public const ROUNDING = 'HYBRID_CENTS_V1';

    public static function calculate(array $request, array $products): array
    {
        self::keys($request, ['policy_version', 'channel', 'lines', 'invoice_discount_percent', 'replacement_discount'], 'request');
        if (($request['policy_version'] ?? null) !== self::POLICY) {
            self::reject('UNSUPPORTED_POLICY', 'policy_version', 'A recognized Hybrid policy version is required.');
        }
        if (($request['channel'] ?? null) !== 'ONLINE') {
            self::reject('ONLINE_ONLY', 'channel', 'Hybrid V1 calculation requires the online workflow.');
        }
        $items = $request['lines'] ?? null;
        if (!is_array($items) || $items !== array_values($items) || count($items) < 1 || count($items) > 100) {
            self::reject('INVALID_LINES', 'lines', 'Supply an ordered list of 1-100 different paid products.');
        }
        $catalogue = [];
        foreach ($products as $product) {
            if (!is_array($product)) {
                self::reject('INVALID_CONFIGURATION', 'products', 'Authoritative product entries must be arrays.');
            }
            $id = self::id($product['product_id'] ?? null, 'products.product_id');
            if (isset($catalogue[$id])) {
                self::reject('DUPLICATE_CONFIGURATION', 'products', 'Duplicate authoritative product identity.');
            }
            $catalogue[$id] = $product;
        }
        $invoiceRate = self::rate(array_key_exists('invoice_discount_percent', $request) ? $request['invoice_discount_percent'] : '0', 'invoice_discount_percent', '50.00');
        $lines = []; $seen = []; $replacementQuotes = []; $replacementIndexes = [];
        $subtotal = '0.00'; $productTotal = '0.00'; $findings = [];
        foreach ($items as $index => $item) {
            $field = 'lines.'.$index;
            if (!is_array($item)) {
                self::reject('INVALID_LINE', $field, 'Each paid line must be an array.');
            }
            self::keys($item, ['product_id', 'paid_quantity', 'product_discount', 'replace_free_entitlement'], $field);
            $id = self::id($item['product_id'] ?? null, $field.'.product_id');
            if (isset($seen[$id])) {
                self::reject('DUPLICATE_PRODUCT', $field.'.product_id', 'Use one aggregate paid line per product.');
            }
            $seen[$id] = true;
            if (!isset($catalogue[$id])) {
                self::reject('UNKNOWN_PRODUCT', $field.'.product_id', 'No authoritative configuration exists for this product.');
            }
            $product = $catalogue[$id];
            self::keys($product, ['product_id', 'unit_price', 'price_version', 'available_quantity', 'promotion'], 'products.'.$id);
            $priceVersion = self::id($product['price_version'] ?? null, 'products.'.$id.'.price_version');
            $price = self::money($product['unit_price'] ?? null, 'products.'.$id.'.unit_price');
            $paid = self::quantity($item['paid_quantity'] ?? null, $field.'.paid_quantity');
            $available = self::quantity($product['available_quantity'] ?? null, 'products.'.$id.'.available_quantity', true);
            if (!array_key_exists('promotion', $product)) {
                self::reject('INVALID_CONFIGURATION', 'products.'.$id.'.promotion', 'Resolve the applicable promotion explicitly, including null when none applies.');
            }
            $rule = self::rule($product['promotion'], $id);
            $selection = self::productSelection(array_key_exists('product_discount', $item) ? $item['product_discount'] : ['choice' => '0'], $field.'.product_discount');
            $replace = array_key_exists('replace_free_entitlement', $item) ? $item['replace_free_entitlement'] : false;
            if (!is_bool($replace)) {
                self::reject('INVALID_REPLACEMENT_SELECTION', $field.'.replace_free_entitlement', 'Replacement selection must be an explicit boolean.');
            }
            try {
                $quote = PromotionCalculator::calculate($price, (string)$paid, $rule);
            } catch (InventoryException $e) {
                self::reject('QUANTITY_OR_AMOUNT_LIMIT', $field, implode(' ', $e->errors));
            }
            $groups = $rule === null ? 0 : intdiv($paid, (int)$rule['buy_quantity']);
            $qualifying = $rule === null ? 0 : $groups * (int)$rule['buy_quantity'];
            $remainder = $paid - $qualifying;
            if ($available < $paid) {
                self::reject('INSUFFICIENT_PAID_STOCK', $field, 'Available stock cannot fulfill the paid quantity.');
            }
            $entitlement = (int)$quote['free_quantity'];
            $residual = $available - $paid;
            $shortage = $residual < $entitlement;
            if ($replace) {
                if (!$shortage || $entitlement === 0) {
                    self::reject('REPLACEMENT_NOT_REQUIRED', $field, 'Replacement requires a genuine full free-entitlement shortage.');
                }
                if ($remainder !== 0 || $qualifying === 0) {
                    self::reject('REPLACEMENT_REMAINDER_UNSUPPORTED', $field, 'Replacement supports only complete qualifying groups without a remainder.');
                }
                if ($selection['percent'] !== '0.00') {
                    self::reject('REPLACEMENT_PRODUCT_OVERLAP', $field, 'Replacement cannot overlap a selected product discount.');
                }
                if ($residual !== 0) {
                    self::reject('PARTIAL_ENTITLEMENT_SHORTAGE', $field, 'A partially fulfillable free entitlement cannot be substituted.');
                }
            } elseif ($shortage) {
                self::reject('FREE_STOCK_SHORTAGE', $field, 'Free stock is insufficient. An eligible complete replacement requires explicit selection.');
            }
            $eligibleBase = self::times($price, $remainder);
            $productDiscount = self::percent($eligibleBase, $selection['percent']);
            $quote += ['product_id' => $id, 'promotion_id' => $rule['id'] ?? null,
                'promotion_version_id' => $rule['revision_id'] ?? null, 'rule_snapshot' => $rule];
            if ($replace) {
                $replacementQuotes[] = $quote;
                $replacementIndexes[] = $index;
                $findings[] = ['code' => 'FULL_ENTITLEMENT_REPLACED', 'field' => $field,
                    'message' => 'The complete free entitlement is replaced; no actual free units are delivered.'];
            }
            $actualFree = $replace ? 0 : $entitlement;
            $segments = [];
            if ($qualifying > 0) {
                $segments[] = self::segment('PROMOTION_QUALIFIED', $qualifying, self::times($price, $qualifying), '0.00', '0.00');
            }
            if ($remainder > 0) {
                $segments[] = self::segment('DISCOUNT_ELIGIBLE', $remainder, $eligibleBase, $selection['percent'], $productDiscount);
            }
            $lines[] = [
                'line_number' => $index + 1, 'product_id' => $id, 'price_version' => $priceVersion,
                'promotion_id' => $rule['id'] ?? null, 'promotion_revision_id' => $rule['revision_id'] ?? null,
                'promotion_snapshot' => $rule, 'unit_price' => $price, 'paid_quantity' => (string)$paid,
                'complete_groups' => (string)$groups, 'qualifying_paid_quantity' => (string)$qualifying,
                'discount_eligible_quantity' => (string)$remainder, 'free_entitlement' => (string)$entitlement,
                'actual_free_quantity' => (string)$actualFree, 'substituted_free_quantity' => $replace ? (string)$entitlement : '0',
                'replacement_reason' => $replace ? 'NO_STOCK_AFTER_PAID_DEMAND' : null,
                'required_stock' => (string)($paid + $actualFree), 'gross_amount' => $quote['gross_amount'],
                'product_discount_choice' => $selection['choice'], 'product_discount_percent' => $selection['percent'],
                'product_discount_base' => $eligibleBase, 'product_discount_amount' => $productDiscount,
                'replacement_policy' => null, 'replacement_mode' => null, 'replacement_discount_choice' => null,
                'replacement_discount_percent' => null, 'replacement_discount_base' => '0.00',
                'replacement_discount_amount' => '0.00', 'segments' => $segments,
            ];
            $subtotal = self::sum($subtotal, $quote['gross_amount']);
            $productTotal = self::sum($productTotal, $productDiscount);
        }
        $replacementTotal = '0.00'; $replacementRate = null; $replacementChoice = null;
        if ($replacementQuotes) {
            $replacement = $request['replacement_discount'] ?? null;
            if (!is_array($replacement)) {
                self::reject('REPLACEMENT_RATE_REQUIRED', 'replacement_discount', 'Explicitly select the existing replacement discount rate; no default applies.');
            }
            self::keys($replacement, ['choice', 'percent'], 'replacement_discount');
            $replacementChoice = $replacement['choice'] ?? null;
            if (!is_string($replacementChoice) || !in_array($replacementChoice, BenefitPolicy::CHOICES_PROMO, true)) {
                self::reject('INVALID_REPLACEMENT_RATE', 'replacement_discount.choice', 'Select an existing PROMOTION_DISCOUNT_INSTEAD rate option.');
            }
            if ($replacementChoice !== 'custom' && array_key_exists('percent', $replacement)) {
                self::reject('INVALID_REPLACEMENT_RATE', 'replacement_discount.percent', 'A preset replacement option cannot include a second rate.');
            }
            try {
                $validated = BenefitPolicy::validate([
                    'benefit_policy' => BenefitPolicy::POLICY, 'benefit_mode' => BenefitPolicy::MODE_PROMO_DISCOUNT,
                    'promotion_discount_choice' => $replacementChoice,
                    'promotion_discount_percent' => $replacement['percent'] ?? null,
                ], false);
                $replacementRate = $validated['benefit_rate'];
                // Reuse unchanged full-line eligibility, aggregate rounding and allocation
                // only on the explicitly approved complete-entitlement cohort.
                $result = BenefitPolicy::apply($replacementQuotes, BenefitPolicy::MODE_PROMO_DISCOUNT, $replacementRate);
            } catch (InventoryException $e) {
                self::reject('INVALID_REPLACEMENT_RATE', 'replacement_discount', implode(' ', $e->errors));
            }
            $replacementTotal = $result['benefit_discount_amount'];
            foreach ($replacementIndexes as $position => $lineIndex) {
                $amount = $result['lines'][$position]['invoice_discount_amount'];
                $lines[$lineIndex]['replacement_policy'] = BenefitPolicy::POLICY;
                $lines[$lineIndex]['replacement_mode'] = BenefitPolicy::MODE_PROMO_DISCOUNT;
                $lines[$lineIndex]['replacement_discount_choice'] = $replacementChoice;
                $lines[$lineIndex]['replacement_discount_percent'] = $replacementRate;
                $lines[$lineIndex]['replacement_discount_base'] = $lines[$lineIndex]['gross_amount'];
                $lines[$lineIndex]['replacement_discount_amount'] = $amount;
                $lines[$lineIndex]['segments'][0]['replacement_discount_amount'] = $amount;
            }
        } elseif (array_key_exists('replacement_discount', $request)) {
            self::reject('UNUSED_REPLACEMENT_SELECTION', 'replacement_discount', 'No affected line supports a replacement selection.');
        }
        $discountLines = []; $invoiceEligibleLines = [];
        foreach ($lines as $index => $line) {
            $beforeInvoice = self::sum($line['product_discount_amount'], $line['replacement_discount_amount']);
            $discountLines[$index] = ['discount_amount' => $beforeInvoice,
                'payable_amount' => PromotionCalculator::subtractMoney($line['gross_amount'], $beforeInvoice)];
            // Entitlement excludes the entire line even when replaced rather than delivered.
            // A zero-rate selection alone does not exclude an otherwise unpromoted line.
            if ($line['free_entitlement'] === '0' && $beforeInvoice === '0.00') {
                $invoiceEligibleLines[$index] = $discountLines[$index];
            }
        }
        // Keep original indexes for largest-remainder ties; leave the shared policy unchanged.
        $invoice = InvoiceDiscount::apply($invoiceEligibleLines, $invoiceRate);
        $combined = self::sum($productTotal, $replacementTotal, $invoice['invoice_discount_amount']);
        if (PromotionCalculator::compare(PromotionCalculator::multiply(PromotionCalculator::cents($combined), '2'), PromotionCalculator::cents($subtotal)) > 0) {
            [$maximum] = InvoiceDiscount::divide(PromotionCalculator::cents($subtotal), '2');
            self::reject('COMBINED_DISCOUNT_CAP_EXCEEDED', 'discounts',
                'Combined monetary discount LKR '.$combined.' exceeds the strict 50% limit LKR '.PromotionCalculator::money($maximum).'. Explicitly reduce discount selections; rates were not changed.');
        }
        foreach ($lines as $index => &$line) {
            $line['post_product_replacement_subtotal'] = $discountLines[$index]['payable_amount'];
            $allocated = $invoice['lines'][$index] ?? ($discountLines[$index] + ['invoice_discount_amount' => '0.00']);
            $line['invoice_discount_amount'] = $allocated['invoice_discount_amount'];
            $line['discount_amount'] = $allocated['discount_amount'];
            $line['payable_amount'] = $allocated['payable_amount'];
            $weights = [];
            foreach ($line['segments'] as $segment) {
                $weights[] = PromotionCalculator::subtractMoney($segment['gross_amount'], self::sum($segment['product_discount_amount'], $segment['replacement_discount_amount']));
            }
            $allocations = self::allocate($line['invoice_discount_amount'], $weights);
            foreach ($line['segments'] as $segmentIndex => &$segment) {
                $segment['invoice_discount_amount'] = $allocations[$segmentIndex];
                $segment['payable_amount'] = PromotionCalculator::subtractMoney($weights[$segmentIndex], $allocations[$segmentIndex]);
            }
            unset($segment);
        }
        unset($line);
        [$limit] = InvoiceDiscount::divide(PromotionCalculator::cents($subtotal), '2');
        return [
            'policy_version' => self::POLICY, 'currency' => 'LKR', 'channel' => 'ONLINE',
            'rounding_version' => self::ROUNDING, 'invoice_allocation_policy' => InvoiceDiscount::POLICY,
            'lines' => $lines, 'original_paid_subtotal' => $subtotal, 'product_discount_total' => $productTotal,
            'replacement_policy' => $replacementQuotes ? BenefitPolicy::POLICY : null,
            'replacement_mode' => $replacementQuotes ? BenefitPolicy::MODE_PROMO_DISCOUNT : null,
            'replacement_discount_choice' => $replacementChoice, 'replacement_discount_percent' => $replacementRate,
            'replacement_discount_total' => $replacementTotal,
            'invoice_discount_percent' => $invoiceRate, 'invoice_discount_base' => $invoice['discountable_subtotal'],
            'invoice_discount_amount' => $invoice['invoice_discount_amount'], 'combined_monetary_discount' => $combined,
            'monetary_cap_limit' => PromotionCalculator::money($limit), 'monetary_cap_check' => '2*D_CENTS<=S_CENTS',
            'final_payable_total' => PromotionCalculator::subtractMoney($subtotal, $combined),
            'validation_findings' => $findings,
        ];
    }

    private static function segment(string $kind, int $quantity, string $gross, string $rate, string $discount): array
    {
        return ['kind' => $kind, 'paid_quantity' => (string)$quantity, 'gross_amount' => $gross,
            'product_discount_percent' => $rate, 'product_discount_amount' => $discount, 'replacement_discount_amount' => '0.00'];
    }

    private static function rule(mixed $rule, string $product): ?array
    {
        if ($rule === null) { return null; }
        if (!is_array($rule)) { self::reject('INVALID_PROMOTION', 'products.'.$product.'.promotion', 'Resolve a single applicable promotion or null.'); }
        if (array_key_exists('mixed_product_ids', $rule) || array_key_exists('mixed_policy', $rule)) {
            self::reject('MIXED_OIL_EXCLUDED', 'products.'.$product.'.promotion', 'Pooled mixed-oil promotions are excluded from Hybrid V1.');
        }
        if (in_array($rule['type'] ?? null, ['PERCENTAGE', 'FIXED'], true)) {
            self::reject('AUTOMATIC_MONETARY_PROMOTION_CONFLICT', 'products.'.$product.'.promotion', 'An applicable automatic monetary promotion conflicts with Hybrid. The existing offer was not changed or suppressed.');
        }
        self::keys($rule, ['id', 'revision_id', 'product_id', 'type', 'buy_quantity', 'free_quantity'], 'products.'.$product.'.promotion');
        if (($rule['type'] ?? null) !== 'BUY_X_GET_Y') { self::reject('UNSUPPORTED_PROMOTION', 'products.'.$product.'.promotion', 'Unsupported or ambiguous promotion type.'); }
        if (self::id($rule['product_id'] ?? null, 'promotion.product_id') !== $product) { self::reject('PROMOTION_PRODUCT_MISMATCH', 'promotion.product_id', 'The approved revision belongs to a different product.'); }
        return ['id' => self::id($rule['id'] ?? null, 'promotion.id'), 'revision_id' => self::id($rule['revision_id'] ?? null, 'promotion.revision_id'),
            'product_id' => $product, 'type' => 'BUY_X_GET_Y',
            'buy_quantity' => (string)self::quantity($rule['buy_quantity'] ?? null, 'promotion.buy_quantity'),
            'free_quantity' => (string)self::quantity($rule['free_quantity'] ?? null, 'promotion.free_quantity')];
    }

    private static function productSelection(mixed $value, string $field): array
    {
        if (!is_array($value)) { self::reject('INVALID_PRODUCT_DISCOUNT', $field, 'Select an independent product discount option.'); }
        self::keys($value, ['choice', 'percent'], $field);
        $choice = $value['choice'] ?? null;
        if (!is_string($choice) || !in_array($choice, ['0', '25', '30', '35', 'other'], true)) { self::reject('INVALID_PRODUCT_DISCOUNT', $field, 'Choose No Discount, 25%, 30%, 35% or Other Discount.'); }
        if ($choice !== 'other' && array_key_exists('percent', $value)) { self::reject('INVALID_PRODUCT_DISCOUNT', $field, 'A preset choice cannot include a second manual rate.'); }
        return ['choice' => $choice, 'percent' => self::rate($choice === 'other' ? ($value['percent'] ?? null) : $choice, $field, '50.00')];
    }

    private static function rate(mixed $value, string $field, string $maximum): string
    {
        try { $rate = InvoiceDiscount::rate($value); }
        catch (InventoryException $e) { self::reject('INVALID_RATE', $field, 'Enter a decimal percentage from 0 through 50 with at most two decimal places.'); }
        if (PromotionCalculator::compareMoney($rate, $maximum) > 0) { self::reject('INVALID_RATE', $field, 'The selected percentage exceeds the 50% individual limit.'); }
        return $rate;
    }

    private static function quantity(mixed $value, string $field, bool $zero = false): int
    {
        try { return Inventory::quantity($value, $zero); }
        catch (InventoryException $e) { self::reject('INVALID_QUANTITY', $field, 'Supply a canonical whole-unit decimal string within the supported integer range.'); }
    }

    private static function id(mixed $value, string $field): string
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,18}$/D', $value)) { self::reject('INVALID_ID', $field, 'Supply a stable positive record identifier as a decimal string.'); }
        return $value;
    }

    private static function money(mixed $value, string $field): string
    {
        try { return Products::money($value); }
        catch (ProductException $e) { self::reject('INVALID_MONEY', $field, 'Supply a nonnegative exact decimal price within DECIMAL(15,2).'); }
    }

    private static function times(string $price, int $quantity): string
    {
        return PromotionCalculator::money(PromotionCalculator::multiply(PromotionCalculator::cents($price), (string)$quantity));
    }

    private static function sum(string ...$amounts): string
    {
        try { return PromotionCalculator::sumMoney(...$amounts); }
        catch (InventoryException $e) { self::reject('AMOUNT_LIMIT', 'amounts', 'The invoice exceeds the supported DECIMAL(15,2) monetary range.'); }
    }

    private static function percent(string $base, string $rate): string
    {
        [$cents] = InvoiceDiscount::divide(PromotionCalculator::add(PromotionCalculator::multiply(PromotionCalculator::cents($base), PromotionCalculator::cents($rate)), '5000'), '10000');
        return PromotionCalculator::money($cents);
    }

    /** Allocate a fixed rounded line amount to its segments without changing it. */
    private static function allocate(string $amount, array $weights): array
    {
        $base = PromotionCalculator::cents(self::sum(...$weights));
        $discount = PromotionCalculator::cents($amount); $sum = '0'; $shares = []; $remainders = [];
        foreach ($weights as $index => $weight) {
            [$share, $remainder] = $base === '0' ? ['0', '0'] : InvoiceDiscount::divide(PromotionCalculator::multiply($discount, PromotionCalculator::cents($weight)), $base);
            $shares[$index] = $share; $sum = PromotionCalculator::add($sum, $share);
            $remainders[] = ['index' => $index, 'remainder' => $remainder];
        }
        usort($remainders, static fn(array $a, array $b): int => -PromotionCalculator::compare($a['remainder'], $b['remainder']) ?: ($a['index'] <=> $b['index']));
        $left = (int)PromotionCalculator::subtract($discount, $sum);
        for ($i = 0; $i < $left; $i++) { $index = $remainders[$i]['index']; $shares[$index] = PromotionCalculator::add($shares[$index], '1'); }
        return array_map([PromotionCalculator::class, 'money'], $shares);
    }

    private static function keys(array $value, array $allowed, string $field): void
    {
        foreach ($value as $key => $_) {
            if (!is_string($key) || !in_array($key, $allowed, true)) { self::reject('UNSUPPORTED_FIELD', $field, 'Unsupported field; supplied financial claims are not accepted.'); }
        }
    }

    private static function reject(string $code, string $field, string $message): void
    {
        throw new HybridCalculationException([['code' => $code, 'field' => $field, 'message' => $message]]);
    }
}
