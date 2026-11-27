<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\InventoryException;
use App\Services\PromotionCalculator as M;

/** Called inside the owning checkout transaction, with no writes during review. */
final class HybridInvoiceResolver
{
    public function __construct(private HybridInvoiceContext $context, private array $config) {}

    public function resolve(array $identity, array $request, ?\DateTimeImmutable $instant = null, bool $eligibilityOnly = false): array
    {
        HybridFeatureGate::configured($this->config);
        $s = $this->context->inventoryGate(); $controls = $this->context->controls();
        HybridFeatureGate::require($this->config, $this->context->database(), $controls, $this->context->schemaReady(), 'preview');
        $a = $this->context->actor($identity);
        if (!(int)($a['is_active'] ?? 0) || !in_array($a['role'] ?? '', ['ADMIN','SALES_REP'], true)) { throw new InventoryException(['access' => 'Active authorized actor required.'], 403); }
        $source = $request['stock_source'];
        if ($a['role'] === 'SALES_REP') {
            if ($source !== '' && $source !== (string)$a['id']) { throw new InventoryException(['stock_source' => 'Representatives may sell only their own stock.'], 403); }
            $source = (string)$a['id'];
        } elseif ($source !== 'warehouse') { Representatives::id($source, 'stock_source'); }
        $this->context->source($source);
        $sourceActor=($source==='warehouse' || $source===(string)$a['id']) ? $a : $this->context->actor(['id'=>$source]);
        $customer = $this->context->customer($request['customer_id']);
        if (!$customer || !(int)$customer['is_active'] || ($a['role'] === 'SALES_REP' && (string)$customer['assigned_rep_id'] !== (string)$a['id'])) {
            throw new InventoryException(['customer' => 'Customer is not available to you.'], 403);
        }
        $this->context->promotionGate();
        $lines = $request['hybrid']['lines']; $ids = array_column($lines, 'product_id');
        if (count(array_unique($ids)) !== count($ids)) { throw new InventoryException(['lines' => 'One aggregate line per product required.']); }
        usort($ids, static fn($a, $b) => M::compare($a, $b)); $products = [];
        foreach ($ids as $id) {
            $p = $this->context->product($id);
            if (!$p || !(int)$p['is_active'] || (string)$p['id'] !== $id) { throw new InventoryException(['product' => 'Active official product required.'], 409); }
            $products[$id] = $p;
        }
        $instant = $instant ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $resolved = []; $display = []; $locations = [];
        // Lock stock in stable product order too. Preserve request order for all monetary ties.
        $balances = [];
        foreach ($ids as $id) { $balances[$id] = $this->context->balance($s, $source, $id); }
        foreach ($lines as $line) {
            $id = $line['product_id']; $p = $products[$id];
            $quote = $this->context->quote($a, $id, $line['paid_quantity'], $instant); $rule = $quote['rule_snapshot'];
            if ($quote['unit_price'] !== $p['selling_price'] || $quote['price_version'] !== (string)$p['version']) { throw new InventoryException(['price' => 'Price changed during resolution.'], 409); }
            $binding = $this->config['bindings'][$id] ?? null;
            if ($rule !== null) {
                if (array_key_exists('mixed_product_ids', $rule) || array_key_exists('mixed_policy', $rule)) { throw new InventoryException(['promotion' => 'Pooled mixed-oil offers are excluded from Hybrid.'], 409); }
                if (($rule['type'] ?? null) !== 'BUY_X_GET_Y') { throw new InventoryException(['promotion' => 'Automatic monetary promotion conflicts with Hybrid for product '.$id.'.'], 409); }
                if (!$binding || (string)$rule['id'] !== ($binding['promotion_id'] ?? null) || (string)$rule['product_id'] !== $id
                    || (string)$rule['buy_quantity'] !== ($binding['buy_quantity'] ?? null) || (string)$rule['free_quantity'] !== '1'
                    || !in_array($binding['buy_quantity'] ?? null, ['6','12'], true)) {
                    throw new InventoryException(['promotion' => 'Approved stable-ID offer binding or quantity does not match.'], 409);
                }
            } elseif ($binding !== null) { throw new InventoryException(['promotion' => 'Approved bound offer is unavailable.'], 409); }
            if ($rule !== null) { $rule = array_intersect_key($rule, array_flip(['id','revision_id','product_id','type','buy_quantity','free_quantity'])); }
            [$location, $available] = $balances[$id]; $locations[] = $location;
            $resolved[] = ['product_id' => $id, 'unit_price' => $p['selling_price'], 'price_version' => (string)$p['version'], 'available_quantity' => $available, 'promotion' => $rule];
            $display[] = ['product_id' => $id, 'sku' => $p['sku'], 'name' => $p['name'], 'name_si' => $p['name_si'] ?? null];
        }
        if (count(array_unique($locations)) !== 1) { throw new InventoryException(['stock' => 'A single authorized stock location is required.'], 409); }
        if ($eligibilityOnly) {
            $eligibility=[];
            foreach ($resolved as $index=>$product) {
                $paid=Inventory::quantity($lines[$index]['paid_quantity']); $available=Inventory::quantity($product['available_quantity'],true); $rule=$product['promotion'];
                $buy=$rule===null?0:Inventory::quantity($rule['buy_quantity']); $groups=$buy===0?0:intdiv($paid,$buy);
                $qualified=$groups*$buy; $remainder=$paid-$qualified; $free=$groups; // Approved bound offers always reward exactly one.
                $residual=$available>=$paid?$available-$paid:0;
                $status=$available<$paid?'INSUFFICIENT_PAID_STOCK':($residual>=$free?'AVAILABLE':($residual>0?'PARTIAL_FREE_SHORTAGE':($remainder===0?'REPLACEMENT_ELIGIBLE':'REPLACEMENT_UNSUPPORTED')));
                $eligibility[]=['product_id'=>$product['product_id'],'paid_quantity'=>(string)$paid,'promotion_revision_id'=>$rule['revision_id']??null,
                    'buy_quantity'=>$rule['buy_quantity']??null,'free_per_group'=>$rule['free_quantity']??null,'qualifying_paid_quantity'=>(string)$qualified,
                    'discount_eligible_quantity'=>(string)$remainder,'free_entitlement'=>(string)$free,'stock_status'=>$status,
                    'replacement_supported'=>$status==='REPLACEMENT_ELIGIBLE','product_discount_must_be_zero_for_replacement'=>true];
            }
            return ['policy_version'=>HybridPromotionPolicy::POLICY,'lines'=>$eligibility,'financial_review'=>false];
        }
        try { $calculation = HybridPromotionPolicy::calculate($request['hybrid'], $resolved); }
        catch (\App\Support\HybridCalculationException $e) { throw new InventoryException(['hybrid' => $e->getMessage()], 409); }
        $due = $calculation['final_payable_total']; $received = $request['amount_received'];
        $applied = M::compareMoney($received, $due) > 0 ? $due : $received;
        $outstanding = M::subtractMoney($due, $applied);
        $existing = $this->context->outstanding($a, $request['customer_id']);
        if ($request['payment_type'] === 'CREDIT') {
            if (!(int)$customer['allow_credit_sales']) { throw new InventoryException(['credit' => 'Customer credit is disabled.'], 409); }
            if ((int)$customer['credit_limit_enabled'] && (M::compare(M::cents($existing), M::cents($customer['credit_limit'])) > 0
                || M::compareMoney($outstanding, M::subtractMoney($customer['credit_limit'], $existing)) > 0)) { throw new InventoryException(['credit' => 'Configured credit limit would be exceeded.'], 409); }
        }
        $allocation = HybridUnitAllocations::invoice($calculation);
        $customerSnapshot = array_intersect_key($customer, array_flip(['id','version','assigned_rep_id','customer_code','shop_name','contact_person','phone','address','city_town','district','allow_credit_sales','credit_limit_enabled','credit_limit','credit_terms_days']));
        $terms=$request['payment_type']==='CREDIT' ? (int)$customer['credit_terms_days'] : 0;
        $date=Inventory::today($instant);
        $review = ['policy_version' => HybridPromotionPolicy::POLICY, 'calculation_revision' => HybridInvoiceSnapshots::CURRENT_REVISION,
            'configuration_version' => $this->config['configuration_version'],
            'control_version' => (string)$controls['version'], 'actor' => ['id' => (string)$a['id'], 'role' => $a['role'], 'name'=>$a['name'], 'version'=>(string)($a['version']??'1')],
            'number_owner'=>$source==='warehouse' ? (string)$a['id'] : $source,
            'rep_name'=>$source==='warehouse' ? mb_substr('Central warehouse — '.$a['name'],0,150,'UTF-8') : $sourceActor['name'],
            'credit_terms_days'=>$terms,'due_date'=>(new \DateTimeImmutable($date,new \DateTimeZone('Asia/Colombo')))->modify('+'.$terms.' days')->format('Y-m-d'),
            'request_id' => $request['request_id'], 'customer' => $customerSnapshot, 'stock_source' => $source, 'source_location_id' => $locations[0],
            'business_date' => Inventory::today($instant), 'payment_type' => $request['payment_type'], 'amount_received' => $received,
            'applied_amount' => $applied, 'change_amount' => M::subtractMoney($received, $applied), 'outstanding' => $outstanding,
            'existing_customer_outstanding' => $existing, 'calculation' => $calculation, 'products' => $display,
            'unit_allocation_version' => HybridUnitAllocations::VERSION, 'unit_allocation_digest' => HybridInvoiceRequest::digest($allocation)];
        $review['review_hash'] = HybridInvoiceRequest::digest($review);
        return ['review' => $review, 'allocations' => $allocation];
    }
}
