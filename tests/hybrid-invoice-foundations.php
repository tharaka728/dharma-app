<?php
declare(strict_types=1);
// No application bootstrap, environment, credentials, network, sessions or real PDO construction.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
spl_autoload_register(static function (string $class): void {
    if ($class === 'App\\Support\\Database') { throw new RuntimeException('Database connections forbidden.'); }
    if (strpos($class,'App\\') === 0) { $path = dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php'; if (is_file($path)) { require $path; } }
});
use App\Services\{HybridInvoiceContext,HybridCheckoutStore,HybridInvoiceCheckout,HybridInvoiceResolver,HybridInvoiceRequest as Request,HybridInvoiceSnapshots as Snapshots,HybridUnitAllocations as Units,HybridFeatureGate,PromotionCalculator as M,Invoices,OfflineSales};
use App\Support\InventoryException;

$checks = 0; $cases = 0;
function eq(mixed $got, mixed $expected, string $label = ''): void { global $checks; $checks++; if ($got !== $expected) { throw new RuntimeException($label.' expected '.json_encode($expected).' got '.json_encode($got)); } }
function rejects(callable $f, string $contains = ''): void { global $checks; $checks++; try { $f(); } catch (Throwable $e) { if ($contains !== '' && strpos($e->getMessage(),$contains) === false && (!$e instanceof InventoryException || strpos(json_encode($e->errors),$contains) === false)) { throw $e; } return; } throw new RuntimeException('Expected rejection: '.$contains); }
function scenario(string $name, callable $f): void { global $cases; try { $f(); $cases++; echo 'PASS '.$name.PHP_EOL; } catch (Throwable $e) { fwrite(STDERR,'FAIL '.$name.': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().PHP_EOL); exit(1); } }

final class MemoryHybridStore implements HybridCheckoutStore
{
    public array $state = ['commands'=>[], 'results'=>[], 'invoices'=>[], 'snapshots'=>[], 'stock'=>['101'=>'100','102'=>'100'], 'payments'=>[], 'number'=>0, 'events'=>[]];
    public array $users = ['7'=>['id'=>'7','role'=>'SALES_REP','name'=>'Rep A','version'=>'1','is_active'=>'1'], '8'=>['id'=>'8','role'=>'SALES_REP','name'=>'Rep B','version'=>'1','is_active'=>'1'], '1'=>['id'=>'1','role'=>'ADMIN','name'=>'Owner','version'=>'1','is_active'=>'1']];
    public array $customers = ['20'=>['id'=>'20','version'=>'1','assigned_rep_id'=>'7','is_active'=>'1','shop_name'=>'Synthetic shop','allow_credit_sales'=>'0','credit_limit_enabled'=>'1','credit_limit'=>'1000.00','credit_terms_days'=>'7']];
    public array $products = ['101'=>['id'=>'101','sku'=>'SYNTHETIC-A','name'=>'Unrelated name','selling_price'=>'100.00','version'=>'1','is_active'=>'1','cost_price'=>'SECRET-COST'], '102'=>['id'=>'102','sku'=>'SYNTHETIC-B','name'=>'Other name','selling_price'=>'100.00','version'=>'1','is_active'=>'1']];
    public array $offers = ['101'=>['id'=>'501','revision_id'=>'901','product_id'=>'101','type'=>'BUY_X_GET_Y','buy_quantity'=>'6','free_quantity'=>'1','name'=>'Extra quote metadata'], '102'=>['id'=>'502','revision_id'=>'902','product_id'=>'102','type'=>'BUY_X_GET_Y','buy_quantity'=>'12','free_quantity'=>'1']];
    public array $control = ['id'=>'1','version'=>'1','enabled'=>'1','sale_posting'=>'1','return_posting'=>'0','custody'=>'0'];
    public array $locks = []; public string $debt = '0.00'; public bool $ready = true; public bool $held = false; public ?string $fail = null; public bool $tx = false; public bool $reconciles = true;
    public function database(): string { return 'dharma_sales_hybrid_stage2b_test'; }
    public function schemaReady(): bool { return $this->ready; }
    public function transaction(callable $work, bool $readOnly): array {
        if ($this->tx) { throw new LogicException('Unexpected nested transaction.'); } $before = $this->state; $this->tx = true;
        try { $result = $work(); if ($readOnly) { $this->state = $before; } return $result; }
        catch (Throwable $e) { $this->state = $before; throw $e; } finally { $this->tx = false; }
    }
    private function locked(string $name): void { if (!$this->tx) { throw new LogicException('Missing transaction.'); } $this->locks[] = $name; }
    public function inventoryGate(): array { $this->locked('inventory'); return ['warehouse_location_id'=>'2']; }
    public function controls(): ?array { $this->locked('hybrid'); return $this->control; }
    public function actor(array $identity): array { $this->locked('actor'); $a = $this->users[$identity['id']??'0']??null; if (!$a || $a['is_active'] !== '1') { throw new InventoryException(['access'=>'Inactive actor.'],403); } return $a; }
    public function source(string $source): void { $this->locked('source'); if ($source !== 'warehouse' && (!isset($this->users[$source]) || $this->users[$source]['is_active'] !== '1')) { throw new InventoryException(['source'=>'Inactive source.'],403); } }
    public function customer(string $id): ?array { $this->locked('customer'); return $this->customers[$id]??null; }
    public function promotionGate(): void { $this->locked('promotions'); }
    public function product(string $id): ?array { $this->locked('product:'.$id); return $this->products[$id]??null; }
    public function quote(array $actor,string $id,string $quantity,DateTimeImmutable $instant): array { return ['unit_price'=>$this->products[$id]['selling_price'],'price_version'=>$this->products[$id]['version'],'rule_snapshot'=>$this->offers[$id]??null]; }
    public function balance(array $settings,string $source,string $product): array { $this->locked('balance:'.$product); if (!$this->reconciles) { throw new InventoryException(['stock'=>'Stock ledger mismatch.'],409); } return ['2',$this->state['stock'][$product]??'0']; }
    public function outstanding(array $actor,string $customer): string { return $this->debt; }
    public function command(string $uuid): ?array { $this->locked('command'); return $this->state['commands'][$uuid]??null; }
    public function replayResult(array $actor,array $command): array {
        $r = $this->state['results'][$command['request_id']]??throw new LogicException('Incomplete command.'); $customer = $this->customers[$command['customer_id']];
        if ($actor['role'] === 'SALES_REP' && $customer['assigned_rep_id'] !== $actor['id']) { throw new InventoryException(['access'=>'Replay scope revoked.'],403); }
        Snapshots::reconcile($this->state['snapshots'][$r['invoice_id']]); return $r;
    }
    private function step(string $name): void { $this->state['events'][] = $name; if ($this->fail === $name) { throw new RuntimeException('Injected failure: '.$name); } }
    public function claim(array $request,string $actor,string $payloadHash): void {
        if (isset($this->state['commands'][$request['request_id']])) { throw new LogicException('Duplicate claim.'); }
        $this->state['commands'][$request['request_id']] = ['request_id'=>$request['request_id'],'customer_id'=>$request['customer_id'],'actor'=>$actor,'operation'=>'HYBRID_SALE_V1','payload_hash'=>$payloadHash]; $this->step('claim');
    }
    public function financialPostingHeld(): bool { return $this->held; }
    public function postOfficial(array $request,array $prepared,array $mapping): array {
        $id = $request['request_id']; $this->state['number']++; $this->step('number');
        $this->state['invoices'][$id] = $mapping; $this->step('invoice');
        $this->step('items');
        foreach ($prepared['review']['calculation']['lines'] as $line) { $this->state['stock'][$line['product_id']] = (string)((int)$this->state['stock'][$line['product_id']] - (int)$line['required_stock']); }
        $this->step('stock'); $this->state['payments'][$id] = $prepared['review']['applied_amount']; $this->step('payment');
        return ['invoice_id'=>$id,'invoice_number'=>'SYNTHETIC-'.$this->state['number']];
    }
    public function persistSnapshot(string $invoice,array $snapshot): void { Snapshots::reconcile($snapshot); $this->step('snapshot_parent'); $this->step('snapshot_lines'); $this->step('snapshot_segments'); $this->step('snapshot_runs'); $this->state['snapshots'][$invoice] = $snapshot; $this->step('seal'); }
    public function complete(string $uuid,array $result): void { $this->step('streams'); $this->step('audit'); $this->state['results'][$uuid] = $result; $this->step('result'); }
}
function config(): array { return ['enabled'=>true,'sale_posting'=>true,'return_posting'=>false,'custody'=>false,'financial_posting_hold'=>false,'databases'=>['dharma_sales_hybrid_stage2b_test'],'configuration_version'=>'SYNTHETIC_CONFIG_V1','bindings'=>['101'=>['promotion_id'=>'501','buy_quantity'=>'6'],'102'=>['promotion_id'=>'502','buy_quantity'=>'12']]]; }
function input(): array { return ['request_id'=>'11111111-1111-4111-8111-111111111111','customer_id'=>'20','payment_type'=>'CASH','amount_received'=>'700.00','hybrid'=>['policy_version'=>'HYBRID_PROMOTION_V1','lines'=>[['product_id'=>'101','paid_quantity'=>'8','product_discount'=>['choice'=>'25']]],'invoice_discount_percent'=>'10']]; }
function checkout(MemoryHybridStore $s,?array $c=null): HybridInvoiceCheckout { return new HybridInvoiceCheckout($s,$c??config()); }
function reviewed(MemoryHybridStore $s,array $in): array { $in['review_hash'] = checkout($s)->review(['id'=>'7'],$in)['review_hash']; return $in; }

scenario('authoritative prices, paid/free stock, default selections, safe metadata and lock order',function () {
    $s = new MemoryHybridStore; $before = $s->state; $r = checkout($s)->review(['id'=>'7','role'=>'ADMIN'],input());
    eq($r['calculation_revision'],Snapshots::CURRENT_REVISION);
    eq($r['calculation']['final_payable_total'],'750.00'); eq($r['calculation']['lines'][0]['required_stock'],'9'); eq($r['applied_amount'],'700.00'); eq($r['change_amount'],'0.00'); eq($s->state,$before);
    eq(strpos(Request::json($r),'SECRET-COST'),false); eq($s->locks,['inventory','hybrid','actor','source','customer','promotions','product:101','balance:101']);
    $in = input(); unset($in['hybrid']['lines'][0]['product_discount'],$in['hybrid']['invoice_discount_percent']); eq(checkout($s)->review(['id'=>'7'],$in)['calculation']['combined_monetary_discount'],'0.00');
});
scenario('mixed UI preview calculation matches server revalidation and sealed in-memory snapshot',function () {
    $s=new MemoryHybridStore; unset($s->offers['102']); $c=config(); unset($c['bindings']['102']);
    $in=input(); $in['amount_received']='1200.00'; $in['hybrid']['lines'][]=['product_id'=>'102','paid_quantity'=>'5'];
    $before=$s->state; $preview=checkout($s,$c)->review(['id'=>'7'],$in); eq($s->state,$before);
    eq($preview['calculation']['final_payable_total'],'1200.00');
    eq(array_column($preview['calculation']['lines'],'invoice_discount_amount'),['0.00','50.00']);
    $in['review_hash']=$preview['review_hash']; $out=checkout($s,$c)->issue(['id'=>'7'],$in);
    $snapshot=$s->state['snapshots'][$out['invoice_id']]; Snapshots::reconcile($snapshot);
    eq($snapshot['review']['calculation'],$preview['calculation']);
    eq($s->state['invoices'][$out['invoice_id']]['grand_total'],'1200.00');
    // Browser renders server-returned amounts rather than calculating a separate discount.
    $js=file_get_contents(dirname(__DIR__).'/public/assets/js/invoices.js');
    eq(str_contains($js,"request('/preview',body,aborter.signal)"),true);
    eq(str_contains($js,"['Invoice discount',line.invoice_discount_amount]"),true);
});
scenario('accepted historical sealed command replays original result while posting remains held',function () {
    $s=new MemoryHybridStore; $snap=json_decode(file_get_contents(__DIR__.'/fixtures/hybrid-snapshot-compatibility/legacy-675.json'),true,64,JSON_THROW_ON_ERROR);
    $in=input(); $in['amount_received']='675.00'; $in['review_hash']=$snap['review']['review_hash']; $n=Request::normalize($in,true); $id=$in['request_id'];
    $s->state['commands'][$id]=['request_id'=>$id,'customer_id'=>'20','actor'=>'7','operation'=>'HYBRID_SALE_V1','payload_hash'=>Request::digest(['operation'=>'HYBRID_SALE_V1','request'=>$n])];
    $s->state['results'][$id]=['invoice_id'=>$id,'invoice_number'=>'SYNTHETIC-HISTORICAL']; $s->state['snapshots'][$id]=$snap;
    $c=config(); $c['enabled']=false; $c['financial_posting_hold']=true; $s->held=true; $s->control['enabled']='0'; $before=$s->state;
    eq(checkout($s,$c)->issue(['id'=>'7'],$in),$s->state['results'][$id]+['replayed'=>true]); eq($s->state,$before);
    eq($s->state['snapshots'][$id]['review']['calculation']['final_payable_total'],'675.00');
    $forged=input(); $forged['hybrid']['calculation_revision']='LEGACY'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$forged),'Unsupported');
});
scenario('different active representatives, reassignment, source spoofing and inactive accounts',function () {
    $s = new MemoryHybridStore; rejects(fn()=>checkout($s)->review(['id'=>'8'],input()),'Customer');
    $s->customers['20']['assigned_rep_id']='8'; eq(checkout($s)->review(['id'=>'8'],input())['stock_source'],'8');
    $in = input(); $in['stock_source']='7'; rejects(fn()=>checkout($s)->review(['id'=>'8'],$in),'own stock');
    $s->users['8']['is_active']='0'; rejects(fn()=>checkout($s)->review(['id'=>'8'],input()),'Inactive');
    $s->users['7']['role']='OTHER'; rejects(fn()=>checkout($s)->review(['id'=>'7'],input()),'authorized');
});
scenario('strict versioned input rejects forged financial fields and JSON normalizes objects',function () {
    $s = new MemoryHybridStore;
    foreach (['unit_price','available_quantity','free_quantity','grand_total','promotion_revision_id'] as $key) { $in=input(); $in['hybrid']['lines'][0][$key]='1'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'Unsupported'); }
    $in=input(); $in['benefit_policy']='PROMO_CHOICE_V1'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'Unsupported');
    $in=input(); $in['hybrid']['channel']='ONLINE'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'Unsupported');
    $in=input(); $in['hybrid']['policy_version']='UNKNOWN'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'version');
    $json = (array)json_decode(json_encode(input())); eq(checkout($s)->review(['id'=>'7'],$json)['calculation']['final_payable_total'],'750.00');
    $in=input(); $in['hybrid']['lines'][]=$in['hybrid']['lines'][0]; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'aggregate');
});
scenario('stable offer IDs, quantity expectations, monetary conflicts and mixed-oil exclusion',function () {
    foreach (['PERCENTAGE','FIXED'] as $type) { $s=new MemoryHybridStore; $s->offers['101']['type']=$type; rejects(fn()=>checkout($s)->review(['id'=>'7'],input()),'conflicts'); }
    foreach (['mixed_product_ids','mixed_policy'] as $key) { $s=new MemoryHybridStore; $s->offers['101'][$key]=null; rejects(fn()=>checkout($s)->review(['id'=>'7'],input()),'mixed-oil'); }
    foreach (['id'=>'999','product_id'=>'102','buy_quantity'=>'12','free_quantity'=>'2'] as $key=>$value) { $s=new MemoryHybridStore; $s->offers['101'][$key]=$value; rejects(fn()=>checkout($s)->review(['id'=>'7'],input()),'binding'); }
    $s=new MemoryHybridStore; unset($s->offers['101']); rejects(fn()=>checkout($s)->review(['id'=>'7'],input()),'unavailable');
    $s=new MemoryHybridStore; unset($s->products['101']); rejects(fn()=>checkout($s)->review(['id'=>'7'],input()),'official');
    $s=new MemoryHybridStore; $s->reconciles=false; rejects(fn()=>checkout($s)->review(['id'=>'7'],input()),'ledger');
});
scenario('replacement restrictions, multi-product independent benefits and combined cap',function () {
    $s=new MemoryHybridStore; $s->state['stock']['102']='12'; $in=input(); $in['hybrid']['lines'][]=['product_id'=>'102','paid_quantity'=>'12','replace_free_entitlement'=>true]; $in['hybrid']['replacement_discount']=['choice'=>'25'];
    $r=checkout($s)->review(['id'=>'7'],$in); eq($r['calculation']['replacement_discount_total'],'300.00'); eq($r['calculation']['lines'][0]['actual_free_quantity'],'1'); eq($r['calculation']['lines'][1]['actual_free_quantity'],'0');
    $in['hybrid']['lines'][1]['paid_quantity']='18'; $s->state['stock']['102']='18'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'remainder');
    $in=input(); $in['hybrid']['lines'][0]=['product_id'=>'101','paid_quantity'=>'12','replace_free_entitlement'=>true]; $in['hybrid']['replacement_discount']=['choice'=>'25']; $s->state['stock']['101']='13'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'partially');
    $s->state['stock']['101']='11'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'paid');
    $s=new MemoryHybridStore; unset($s->offers['101']); $c=config(); unset($c['bindings']['101']); $in=input(); $in['hybrid']['lines'][0]['product_discount']=['choice'=>'other','percent'=>'50']; $in['hybrid']['invoice_discount_percent']='50'; eq(checkout($s,$c)->review(['id'=>'7'],$in)['calculation']['combined_monetary_discount'],'400.00');
    $s->products['101']['selling_price']='100.01'; $in['hybrid']['lines'][0]['paid_quantity']='1'; unset($in['hybrid']['lines'][0]['product_discount']); rejects(fn()=>checkout($s,$c)->review(['id'=>'7'],$in),'50%');
});
scenario('credit permissions and configured limits retain partial-cash behavior',function () {
    $s=new MemoryHybridStore; $in=input(); $in['payment_type']='CREDIT'; $in['amount_received']='100.00'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'credit is disabled');
    $s->customers['20']['allow_credit_sales']='1'; $s->debt='500.00'; rejects(fn()=>checkout($s)->review(['id'=>'7'],$in),'limit');
    $s->debt='350.00'; eq(checkout($s)->review(['id'=>'7'],$in)['outstanding'],'650.00');
    $in['payment_type']='CASH'; eq(checkout($s)->review(['id'=>'7'],$in)['outstanding'],'650.00');
});
scenario('snapshot mapping, sealing and tamper detection',function () {
    $s=new MemoryHybridStore; $n=Request::normalize(input()); $p=$s->transaction(fn()=>(new HybridInvoiceResolver($s,config()))->resolve(['id'=>'7'],$n),true); $snap=Snapshots::build($n,$p); Snapshots::reconcile($snap);
    eq(Snapshots::legacyMapping($snap),['subtotal'=>'800.00','automatic_discount_total'=>'0.00','manual_discount_total'=>'50.00','discount_total'=>'50.00','grand_total'=>'750.00']);
    foreach (['review','allocations','seal'] as $target) { $bad=$snap; if ($target==='review') { $bad['review']['calculation']['final_payable_total']='674.99'; } elseif ($target==='allocations') { $bad['allocations']['segments'][0]['runs'][0]['cents']['net']='0'; } else { $bad['seal']['counts']['runs']++; } rejects(fn()=>Snapshots::reconcile($bad)); }
    eq($snap['seal']['counts']['lines'],1); eq($snap['seal']['counts']['segments'],2);
    Snapshots::reconcile(json_decode(Request::json($snap),true,512,JSON_THROW_ON_ERROR));
});
scenario('B1 discounted remainder first, original cents and no repeated positions',function () {
    $s=new MemoryHybridStore; $n=Request::normalize(input()); $p=$s->transaction(fn()=>(new HybridInvoiceResolver($s,config()))->resolve(['id'=>'7'],$n),true);
    $a=$p['allocations']; $first=Units::consume($a,1,1,[]); $second=Units::consume($a,1,1,$first['next_cursors']); $third=Units::consume($a,1,1,$second['next_cursors']);
    eq($first['net_credit'],'75.00'); eq($second['net_credit'],'75.00'); eq($third['net_credit'],'100.00'); eq($first['ranges'][0]['position_start'],0); eq($second['ranges'][0]['position_start'],1);
    eq(Units::consumeAfter($a,1,1,[$first['ranges'],$second['ranges']]),$third);
    rejects(fn()=>Units::consumeAfter($a,1,1,[$first['ranges'],$first['ranges']]),'overlap');
    rejects(fn()=>Units::consumeAfter($a,1,1,[$second['ranges']]),'skip');
    eq(Units::consume($a,1,3,[])['net_credit'],M::sumMoney($first['net_credit'],$second['net_credit'],$third['net_credit']));
    $all=Units::consume($a,1,8,[]); rejects(fn()=>Units::consume($a,1,1,$all['next_cursors']),'exceeds'); rejects(fn()=>Units::consume($a,99,1,[]),'exceeds');
});
scenario('independent per-unit oracle including residual ties and bounded capacities',function () {
    for ($n=1;$n<=24;$n++) { for ($gross=0;$gross<=19;$gross++) { for ($variant=0;$variant<5;$variant++) {
        $p=$variant===4 ? 0 : intdiv($n*$gross*$variant,10); $r=$variant===4 ? intdiv($n*$gross*3,10) : 0; $base=$n*$gross-$p-$r; $invoice=intdiv($base*($variant+1),6);
        $segment=['paid_quantity'=>(string)$n,'gross_amount'=>M::money((string)($n*$gross)),'product_discount_amount'=>M::money((string)$p),'replacement_discount_amount'=>M::money((string)$r),'invoice_discount_amount'=>M::money((string)$invoice),'payable_amount'=>M::money((string)($base-$invoice))];
        $actual=[]; foreach (Units::segment($segment,M::money((string)$gross)) as $run) { for ($i=0;$i<$run['position_count'];$i++) { $actual[]=$run['cents']; } }
        $expected=[]; $shares=[]; $used=0;
        for ($i=0;$i<$n;$i++) { $pd=intdiv($p,$n)+($i<$p%$n?1:0); $rd=intdiv($r,$n)+($i<$r%$n?1:0); $capacity=$gross-$pd-$rd; $share=$base===0?0:intdiv($invoice*$capacity,$base); $shares[]=['index'=>$i,'rem'=>$base===0?0:($invoice*$capacity)%$base]; $used+=$share; $expected[]=['gross'=>(string)$gross,'product_discount_amount'=>(string)$pd,'replacement_discount_amount'=>(string)$rd,'invoice_discount_amount'=>(string)$share,'net'=>(string)($capacity-$share)]; }
        usort($shares,fn($a,$b)=>$b['rem']<=>$a['rem']?:$a['index']<=>$b['index']);
        for ($i=0;$i<$invoice-$used;$i++) { $pos=$shares[$i]['index']; $expected[$pos]['invoice_discount_amount']=(string)((int)$expected[$pos]['invoice_discount_amount']+1); $expected[$pos]['net']=(string)((int)$expected[$pos]['net']-1); }
        eq($actual,$expected,'oracle');
    } } }
});
scenario('compact large quantities, huge exact cents and odd-cent credits',function () {
    $segment=['paid_quantity'=>'1000000000','gross_amount'=>'1000000000.00','product_discount_amount'=>'123456789.01','replacement_discount_amount'=>'0.00','invoice_discount_amount'=>'234567890.03','payable_amount'=>'641975320.96'];
    $runs=Units::segment($segment,'1.00'); eq(count($runs)<=8,true); eq(array_sum(array_column($runs,'position_count')),1000000000);
    $segment=['paid_quantity'=>'2','gross_amount'=>'200.02','product_discount_amount'=>'50.01','replacement_discount_amount'=>'0.00','invoice_discount_amount'=>'15.00','payable_amount'=>'135.01'];
    $runs=Units::segment($segment,'100.01'); eq(array_map(fn($r)=>$r['cents']['net'],$runs),['6750','6751']);
    // First gross/product residuals consume original-position ties before invoice allocation.
    // Original component cents, rather than re-rounded segment net, determine each position.
    $huge=['paid_quantity'=>'3','gross_amount'=>'9999999999999.99','product_discount_amount'=>'3333333333333.33','replacement_discount_amount'=>'0.00','invoice_discount_amount'=>'1234567890123.45','payable_amount'=>'5432098776543.21'];
    eq(count(Units::segment($huge,'3333333333333.33'))<=8,true);
});
scenario('idempotent replay after disable, payload/actor/operation collision and scope revocation',function () {
    $s=new MemoryHybridStore; $in=reviewed($s,input()); $out=checkout($s)->issue(['id'=>'7'],$in); eq($out['replayed'],false); $once=$s->state;
    $c=config(); $c['enabled']=false; $s->control['enabled']='0'; $s->held=true; eq(checkout($s,$c)->issue(['id'=>'7'],$in)['replayed'],true); eq($s->state,$once);
    $bad=$in; $bad['amount_received']='701.00'; rejects(fn()=>checkout($s,$c)->issue(['id'=>'7'],$bad),'different'); rejects(fn()=>checkout($s,$c)->issue(['id'=>'8'],$in),'different');
    $s->state['commands'][$in['request_id']]['operation']='OTHER'; rejects(fn()=>checkout($s,$c)->issue(['id'=>'7'],$in),'different'); $s->state['commands'][$in['request_id']]['operation']='HYBRID_SALE_V1';
    $s->customers['20']['assigned_rep_id']='8'; rejects(fn()=>checkout($s,$c)->issue(['id'=>'7'],$in),'scope');
    $s->users['7']['role']='OTHER'; rejects(fn()=>checkout($s,$c)->issue(['id'=>'7'],$in),'authorized');
});
scenario('stale price, promotion, customer, date, stock feasibility and selections reject before posting',function () {
    foreach (['price','revision','customer','selection','shortage','control','debt'] as $change) {
        $s=new MemoryHybridStore; $in=reviewed($s,input());
        if ($change==='price') { $s->products['101']['selling_price']='100.01'; $s->products['101']['version']='2'; }
        elseif ($change==='revision') { $s->offers['101']['revision_id']='999'; }
        elseif ($change==='customer') { $s->customers['20']['version']='2'; }
        elseif ($change==='selection') { $in['hybrid']['lines'][0]['product_discount']=['choice'=>'30']; }
        elseif ($change==='shortage') { $s->state['stock']['101']='8'; }
        elseif ($change==='control') { $s->control['version']='2'; }
        else { $s->debt='0.01'; }
        $before=$s->state; rejects(fn()=>checkout($s)->issue(['id'=>'7'],$in)); eq($s->state,$before);
    }
    $s=new MemoryHybridStore; $n=Request::normalize(input());
    $a=$s->transaction(fn()=>(new HybridInvoiceResolver($s,config()))->resolve(['id'=>'7'],$n,new DateTimeImmutable('2026-11-25T18:29:59Z')),true);
    $b=$s->transaction(fn()=>(new HybridInvoiceResolver($s,config()))->resolve(['id'=>'7'],$n,new DateTimeImmutable('2026-11-25T18:30:00Z')),true);
    eq($a['review']['business_date'],'2026-11-25'); eq($b['review']['business_date'],'2026-11-26'); eq($a['review']['review_hash']!==$b['review']['review_hash'],true);
});
scenario('failure injection rolls back numbering, command, stock, payment, snapshots and results',function () {
    foreach (['claim','number','invoice','items','stock','payment','snapshot_parent','snapshot_lines','snapshot_segments','snapshot_runs','seal','streams','audit','result'] as $step) {
        $s=new MemoryHybridStore; $in=reviewed($s,input()); $before=$s->state; $s->fail=$step; rejects(fn()=>checkout($s)->issue(['id'=>'7'],$in),'Injected failure'); eq($s->state,$before,$step);
        $s->fail=null; eq(checkout($s)->issue(['id'=>'7'],$in)['replayed'],false); eq($s->state['number'],1); eq($s->state['stock']['101'],'91');
    }
});
scenario('separate preview, financial, schema and existing posting holds fail closed',function () {
    foreach (['enabled','sale_posting','financial_posting_hold','database','schema','control','legacy_hold'] as $change) {
        $s=new MemoryHybridStore; $in=reviewed($s,input()); $c=config();
        if ($change==='enabled') { $c['enabled']=false; } elseif ($change==='sale_posting') { $c['sale_posting']=false; } elseif ($change==='financial_posting_hold') { $c['financial_posting_hold']=true; }
        elseif ($change==='database') { $c['databases']=[]; } elseif ($change==='schema') { $s->ready=false; } elseif ($change==='control') { $s->control=[]; } else { $s->held=true; }
        $before=$s->state; rejects(fn()=>checkout($s,$c)->issue(['id'=>'7'],$in)); eq($s->state,$before);
    }
    $s=new MemoryHybridStore; $c=config(); $c['sale_posting']=false; eq(checkout($s,$c)->review(['id'=>'7'],input())['calculation']['final_payable_total'],'750.00');
    $default=require dirname(__DIR__).'/config/hybrid-promotions.php'; rejects(fn()=>HybridFeatureGate::configured($default),'disabled'); eq($default['bindings'],[]);
    rejects(fn()=>HybridFeatureGate::require(config(),'dharma_sales_hybrid_stage2b_test',['id'=>'1','enabled'=>'1','version'=>'1'],true,'custody'));
});
scenario('live entry points reject Hybrid without any PDO query and offline fields cannot disappear',function () {
    $pdo=new class extends PDO { public function __construct() {} public function prepare(string $query,array $options=[]): PDOStatement|false { throw new LogicException('Unexpected PDO query.'); } public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false { throw new LogicException('Unexpected PDO query.'); } };
    $in=input(); rejects(fn()=>(new Invoices($pdo))->review(['id'=>'7'],$in),'disabled'); $in['review_hash']=str_repeat('a',64); rejects(fn()=>(new Invoices($pdo))->issue(['id'=>'7'],$in),'disabled');
    rejects(fn()=>(new Invoices($pdo))->review(['id'=>'7'],$in,true),'online only'); rejects(fn()=>(new Invoices($pdo))->issue(['id'=>'7'],$in,['device_id'=>'X']),'online only');
    foreach ([['hybrid'=>null],['items'=>[['product_discount'=>['choice'=>'25']]]],['sale'=>['policy_version'=>'HYBRID_PROMOTION_V1']]] as $payload) { rejects(fn()=>Request::rejectOffline($payload),'online only'); }
    $legacy=['schema_version'=>1,'items'=>[['product_id'=>'101','quantity'=>'8','price_version'=>'1','unit_price'=>'100.00']]]; Request::rejectOffline($legacy); eq(Request::json($legacy),Request::json(json_decode(json_encode($legacy),true)));
    $method=new ReflectionMethod(OfflineSales::class,'envelope'); $method->setAccessible(true); rejects(fn()=>$method->invoke(new OfflineSales($pdo),[],['body'=>json_encode(['hybrid'=>[]])]),'online only');
});
/** SQL contract recorder, not a database or a substitute for MySQL constraint tests. */
final class RecordingHybridPdo extends PDO
{
    public bool $active = true; public string $db = 'dharma_sales_hybrid_stage2b_test'; public int $port = 3309; public array $inserted = []; public array $official = []; public array $items = [];
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false {
        if ($query==='SELECT DATABASE()') { return new RecordingHybridStatement($this,[[ $this->db ]]); }
        if ($query==='SELECT @@port') { return new RecordingHybridStatement($this,[[ $this->port ]]); }
        throw new LogicException('Unexpected recorded query: '.$query);
    }
    public function prepare(string $query,array $options=[]): PDOStatement|false { return new RecordingHybridStatement($this,[],$query); }
}
final class RecordingHybridStatement extends PDOStatement
{
    public function __construct(private RecordingHybridPdo $owner,private array $rows,private string $sql='') {}
    public function execute(?array $params=null): bool {
        if (preg_match('/^INSERT INTO ([a-z_]+) \(([^)]+)\) VALUES /',$this->sql,$m)) { $this->owner->inserted[]=['table'=>$m[1],'row'=>array_combine(explode(',',$m[2]),$params)]; return true; }
        if (strpos($this->sql,'SELECT i.*,d.source_location_id')===0) { $this->rows=[$this->owner->official]; return true; }
        if (strpos($this->sql,'SELECT * FROM invoice_items')===0) { $this->rows=$this->owner->items; return true; }
        if (preg_match('/^SELECT COUNT\(\*\) FROM ([a-z_]+)/',$this->sql,$m)) { $this->rows=[[count(array_filter($this->owner->inserted,fn($r)=>$r['table']===$m[1]))]]; return true; }
        throw new LogicException('Unexpected recorded statement: '.$this->sql);
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return array_shift($this->rows)??false; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column=0): mixed { return array_values($this->rows[0]??[])[$column]??false; }
}
scenario('PDO primitives reject missing physical-server proof before any write',function () {
    $s=new MemoryHybridStore; $n=Request::normalize(input()); $prepared=$s->transaction(fn()=>(new HybridInvoiceResolver($s,config()))->resolve(['id'=>'7'],$n),true); $snapshot=Snapshots::build($n,$prepared); $r=$snapshot['review'];
    $pdo=new RecordingHybridPdo;
    $pdo->official=Snapshots::legacyMapping($snapshot)+['posting_channel'=>'ONLINE','customer_id'=>'20','issued_by_user_id'=>'7','source_location_id'=>'2','review_hash'=>$r['review_hash']];
    foreach ($r['calculation']['lines'] as $line) { $pdo->items[]=['line_number'=>$line['line_number'],'product_id'=>$line['product_id'],'quantity'=>$line['paid_quantity'],'free_quantity'=>$line['actual_free_quantity'],
        'unit_price'=>$line['unit_price'],'gross_amount'=>$line['gross_amount'],'automatic_discount_amount'=>'0.00','manual_discount_amount'=>$line['discount_amount'],'discount_amount'=>$line['discount_amount'],
        'line_total'=>$line['payable_amount'],'price_version'=>$line['price_version'],'promotion_version_id'=>$line['promotion_revision_id']]; }
    $writer=new App\Services\PdoHybridInvoiceSnapshots($pdo); rejects(fn()=>$writer->insertSealed($n['request_id'],$snapshot),'disposable');
    eq($pdo->inserted,[]); eq($pdo->active,true);
    $pdo->active=false; rejects(fn()=>$writer->insertSealed($n['request_id'],$snapshot),'owning'); $pdo->active=true;
    $pdo->db='dharma_sales_phase1'; rejects(fn()=>$writer->insertSealed($n['request_id'],$snapshot),'disposable');
    $context=new App\Services\PdoHybridInvoiceContext($pdo); rejects(fn()=>$context->transaction(fn()=>[],true),'disposable'); $pdo->db='dharma_sales_hybrid_stage2b_test';
    $pdo->port=3306; rejects(fn()=>$writer->insertSealed($n['request_id'],$snapshot),'disposable'); rejects(fn()=>$context->transaction(fn()=>[],true),'disposable'); $pdo->port=3309;
    $pdo->items[0]['unit_price']='0.01'; rejects(fn()=>$writer->insertSealed($n['request_id'],$snapshot),'disposable'); eq($pdo->inserted,[]);
});
scenario('prepared migration is additive, disabled, parser-compatible and contains immutable/seal guards',function () {
    $sql=file_get_contents(dirname(__DIR__).'/database/migrations/025_hybrid_invoice_snapshots.sql');
    eq((bool)preg_match('/\b(DROP|ALTER|TRUNCATE|DELIMITER)\b/i',$sql),false);
    eq(substr_count($sql,'CREATE TABLE '),9); eq(substr_count($sql,'CREATE TRIGGER '),20);
    eq(strpos($sql,'DEFAULT FALSE')!==false,true); eq(strpos($sql,'CHECK(2*combined_discount<=original_subtotal)')!==false,true);
    foreach (explode(';',$sql) as $statement) {
        $statement=trim(preg_replace('/^--[^\r\n]*(?:\r?\n|$)/m','',$statement));
        if ($statement!=='') { eq((bool)preg_match('/^(CREATE TABLE |CREATE TRIGGER |INSERT INTO hybrid_operating_state\()/',$statement),true); }
    }
    eq(substr_count($sql,'s.invoice_id IS NULL FOR UPDATE'),3);
});
echo 'Stage 2B: '.$cases.' cases, '.$checks.' assertions passed. In-memory and SQL-recorder integration only, zero database connections.'.PHP_EOL;
