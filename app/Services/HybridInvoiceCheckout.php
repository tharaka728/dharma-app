<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\InventoryException;

/** Versioned coordinator. No legacy normalization or fallback, no autonomous connection. */
final class HybridInvoiceCheckout
{
    public function __construct(private HybridInvoiceContext $context, private array $config) {}
    public function availability(array $actor): array
    {
        if (($this->config['enabled']??false)!==true) { return ['enabled'=>false,'posting'=>false]; }
        return $this->context->transaction(function () use ($actor) {
            $this->context->inventoryGate(); $control=$this->context->controls(); $a=$this->context->actor($actor);
            if (($a['role']??null)!=='SALES_REP' || !(int)($a['is_active']??0)) { throw new InventoryException(['hybrid'=>'Hybrid representative checkout requires an active Sales Representative.'],403); }
            HybridFeatureGate::require($this->config,$this->context->database(),$control,$this->context->schemaReady(),'preview');
            $posting=($this->config['sale_posting']??false)===true && ($this->config['financial_posting_hold']??true)===false
                && (int)($control['sale_posting']??0)===1 && $this->context instanceof HybridCheckoutStore && !$this->context->financialPostingHeld();
            return ['enabled'=>true,'posting'=>$posting];
        },true);
    }
    public function eligibility(array $actor,array $input): array
    {
        HybridFeatureGate::configured($this->config); $n=HybridInvoiceRequest::normalize($input);
        return $this->context->transaction(function () use ($actor,$n) {
            return (new HybridInvoiceResolver($this->context,$this->config))->resolve($actor,$n,null,true);
        },true);
    }
    public function review(array $actor, array $input): array
    {
        HybridFeatureGate::configured($this->config); $n = HybridInvoiceRequest::normalize($input);
        return $this->context->transaction(function () use ($actor,$n) {
            return (new HybridInvoiceResolver($this->context,$this->config))->resolve($actor,$n)['review'];
        }, true);
    }
    public function issue(array $actor, array $input): array
    {
        $n = HybridInvoiceRequest::normalize($input, true);
        if (!$this->context instanceof HybridCheckoutStore) { throw new InventoryException(['hybrid' => 'Hybrid posting requires an explicit transactional writer adapter.'], 409); }
        $store = $this->context;
        // No environment test switch. Only an explicitly injected disposable test adapter may write.
        if (!preg_match('/^dharma_sales_hybrid_[a-z0-9_]*test(?:_[a-z0-9]+)?$/D', $store->database())) { throw new InventoryException(['hybrid' => 'Hybrid posting is restricted to isolated development.'], 409); }
        return $store->transaction(function () use ($store,$actor,$n) {
            $store->inventoryGate(); $controls = $store->controls(); $a = $store->actor($actor);
            if (!(int)($a['is_active'] ?? 0) || !in_array($a['role'] ?? '', ['ADMIN','SALES_REP'], true)) { throw new InventoryException(['access' => 'Active authorized actor required.'], 403); }
            $hash = HybridInvoiceRequest::digest(['operation' => 'HYBRID_SALE_V1','request' => $n]);
            $old = $store->command($n['request_id']);
            if ($old) {
                if (($old['actor'] ?? null) !== (string)$a['id'] || ($old['operation'] ?? null) !== 'HYBRID_SALE_V1' || !hash_equals($old['payload_hash'], $hash)) { throw new InventoryException(['request_id' => 'Command identity already used with different details.'], 409); }
                return array_merge($store->replayResult($a,$old), ['replayed' => true]);
            }
            HybridFeatureGate::require($this->config,$store->database(),$controls,$store->schemaReady(),'sale_posting');
            if ($store->financialPostingHeld()) { throw new InventoryException(['hybrid' => 'Existing financial posting hold remains active.'], 403); }
            $store->claim($n,(string)$a['id'],$hash);
            $prepared = (new HybridInvoiceResolver($store,$this->config))->resolve($a,$n);
            if (!hash_equals($prepared['review']['review_hash'],$n['review_hash'])) { throw new InventoryException(['review' => 'Hybrid review is stale. Review again.'], 409); }
            $snapshot = HybridInvoiceSnapshots::build($n,$prepared);
            $result = $store->postOfficial($n,$prepared,HybridInvoiceSnapshots::legacyMapping($snapshot));
            $store->persistSnapshot($result['invoice_id'],$snapshot);
            $store->complete($n['request_id'],$result);
            return array_merge($result, ['replayed' => false]);
        }, false);
    }
}
