<?php
declare(strict_types=1);
// Frozen synthetic arrays only: no bootstrap, environment, network, sessions or PDO.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
spl_autoload_register(static function (string $class): void {
    if ($class === 'App\\Support\\Database') { throw new RuntimeException('Database forbidden.'); }
    if (str_starts_with($class, 'App\\')) { require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php'; }
});
set_error_handler(static function (int $level, string $message): never { throw new ErrorException($message,0,$level); });
use App\Services\{HybridInvoiceSnapshots as S, HybridInvoiceRequest as J, HybridReturnPolicy as R, HybridUnitAllocations as U};
$checks=0;
function equalSnapshot(mixed $a,mixed $b):void { global $checks; $checks++; if ($a!==$b) { throw new RuntimeException('Mismatch '.json_encode([$a,$b])); } }
function rejectSnapshot(callable $fn):void { global $checks; $checks++; try { $fn(); } catch (LogicException|App\Support\InventoryException $e) { return; } throw new RuntimeException('Expected rejection.'); }
function resealSnapshot(array $s):array {
    unset($s['review']['review_hash']); $s['review']['unit_allocation_digest']=J::digest($s['allocations']);
    $s['review']['review_hash']=J::digest($s['review']); $seal=$s['seal']; unset($s['seal']); $seal['digest']=J::digest($s); $s['seal']=$seal; return $s;
}
function receiptSnapshot(string $paid,string $free='0'):array {
    $r=['line_number'=>'1','paid_quantity'=>$paid,'free_quantity'=>$free,'condition_reason'=>'Synthetic inspection','holding_reference'=>'Synthetic bin'];
    foreach (['paid'=>$paid,'free'=>$free] as $kind=>$quantity) { $r[$kind.'_conditions']=['SALEABLE'=>$quantity,'QUARANTINE'=>'0','UNSALEABLE'=>'0']; } return $r;
}
function planSnapshot(array $s,array $history,array $lines,string $cash,string $operation='RETURN'):array {
    return R::plan($s,$history,['operation'=>$operation,'lines'=>$lines],['paid_amount'=>$cash,'cash_collected'=>$cash,'noncash'=>false,'legacy_void'=>false]);
}
function eventSnapshot(array $plan):array { return array_diff_key($plan,['next_state'=>true]); }
$sources=json_decode(file_get_contents(__DIR__.'/fixtures/hybrid-snapshot-compatibility/sources.json'),true,32,JSON_THROW_ON_ERROR);
equalSnapshot($sources['pinned_commit'],'ed147d41894b109fae277e8e69b40e8a3dfecb57');
foreach ($sources['files'] as $file=>$pin) {
    $source=str_replace("\r\n","\n",file_get_contents(dirname(__DIR__).'/app/Services/HistoricalHybridV1/'.$file));
    equalSnapshot(hash('sha256',$source),$pin['frozen_sha256_lf']);
    $code=''; foreach (token_get_all($source) as $token) { if (is_array($token)) { if (!in_array($token[0],[T_COMMENT,T_DOC_COMMENT],true)) { $code.=$token[1]; } } else { $code.=$token; } }
    equalSnapshot((bool)preg_match('/\bPDO\b|\bgetenv\s*\(|\bAPP_ROOT\b|\beval\s*\(/',$code),false);
}
$goldens=[
    'legacy-675'=>['a856036ba1d9f8779683cd7589490e7cfbb91b713b8abb5a3cf9227d70a29e13',S::HISTORICAL,'675.00'],
    'current-unmarked-750'=>['86fc9ff378fe444bac07a2a6b4e6df2600ade52ba9bc4ee51dec15304d6eb137',S::CURRENT,'750.00'],
    'current-marked-750'=>['35ed095555f928e791697ce660fa3d9e507a2055e673c2c56d3108d4c4567d7e',S::CURRENT,'750.00'],
    'mixed-marked-1200'=>['e701bea743a5c98e5473c60e5af9720e84a4c144107b9f2672a739c5fd9f3eb7',S::CURRENT,'1200.00'],
    'equivalent-unmarked'=>['bee0e70526c129dfd056d7e3abf36bb89eff661e6124e5e6f50a0d2b3e934ae7',S::EQUIVALENT,'450.00'],
    'legacy-replacement-810'=>['bcbd759def8ed7c29c1bc428619cbd10eb988e32f1e48f0fa83eaa42f1b2b04d',S::HISTORICAL,'810.00'],
];
$snapshots=[];
foreach ($goldens as $name=>[$digest,$kind,$net]) {
    $path=__DIR__.'/fixtures/hybrid-snapshot-compatibility/'.$name.'.json'; $raw=file_get_contents($path); $before=hash('sha256',$raw);
    $s=json_decode($raw,true,64,JSON_THROW_ON_ERROR); $saved=serialize($s); equalSnapshot($s['seal']['digest'],$digest);
    $unsigned=$s; unset($unsigned['seal']); equalSnapshot(J::digest($unsigned),$digest);
    equalSnapshot(S::interpretation($s),$kind); S::reconcile($s); S::reconcile($s);
    equalSnapshot(S::legacyMapping($s)['grand_total'],$net); equalSnapshot(serialize($s),$saved); equalSnapshot(hash_file('sha256',$path),$before); $snapshots[$name]=$s;
}
$old=$snapshots['legacy-675']; $current=$snapshots['current-marked-750']; $mixed=$snapshots['mixed-marked-1200'];
equalSnapshot(array_column($old['review']['calculation']['lines'][0]['segments'],'invoice_discount_amount'),['60.00','15.00']);
equalSnapshot(array_column($current['review']['calculation']['lines'][0]['segments'],'invoice_discount_amount'),['0.00','0.00']);
equalSnapshot($mixed['review']['calculation']['invoice_discount_base'],'500.00');
equalSnapshot(array_column($mixed['review']['calculation']['lines'],'payable_amount'),['750.00','450.00']);
// Stored original cents, debt-first settlement, history replay, residual void and free recovery.
foreach ([[$old,['67.50','67.50','90.00'],'225.00','450.00','675.00','575.00','35.00'],[$current,['75.00','75.00','100.00'],'250.00','500.00','750.00','650.00','50.00']] as [$s,$units,$three,$residual,$cash,$partial,$refund]) {
    $history=[];
    foreach ($units as $index=>$unit) { $p=planSnapshot($s,$history,[receiptSnapshot('1',$index===2?'1':'0')],$cash); equalSnapshot($p['totals']['net_credit'],$unit); equalSnapshot($p['findings'],[]); $history[]=eventSnapshot($p); }
    $state=R::state($s,$history); equalSnapshot($state['credit'],$three);
    $p=planSnapshot($s,$history,[receiptSnapshot('5')],$cash,'VOID'); equalSnapshot($p['totals']['net_credit'],$residual); equalSnapshot($p['next_state']['credit'],$cash); equalSnapshot($p['next_state']['refund_due'],$cash);
    $p=planSnapshot($s,[],[receiptSnapshot('2')],$partial); equalSnapshot($p['debt_reduction'],'100.00'); equalSnapshot($p['refund_increment'],$refund); equalSnapshot(R::state($s,[])['credit'],'0.00');
    $p=planSnapshot($s,[],[receiptSnapshot('3')],$cash); equalSnapshot(in_array('MISSING_FREE_RECOVERY',array_column($p['findings'],'code'),true),true);
    rejectSnapshot(fn()=>R::state($s,[$history[0],$history[0]]));
    equalSnapshot(U::consume($s['allocations'],1,8,[])['net_credit'],$cash);
}
equalSnapshot(planSnapshot($snapshots['legacy-replacement-810'],[],[receiptSnapshot('2')],'810.00')['totals']['net_credit'],'135.00');
// Only current marked reviews can construct/persist NEW snapshots; browser keys stay forbidden.
S::requireCurrent($current);
$built=S::build(['hybrid'=>$current['request']],['review'=>$current['review'],'allocations'=>$current['allocations']]); equalSnapshot($built,$current);
rejectSnapshot(fn()=>S::requireCurrent($old)); rejectSnapshot(fn()=>S::requireCurrent($snapshots['current-unmarked-750']));
rejectSnapshot(fn()=>S::build(['hybrid'=>$old['request']],['review'=>$old['review'],'allocations'=>$old['allocations']]));
foreach (['hybrid','outer','line'] as $where) {
    $input=['request_id'=>'11111111-1111-4111-8111-111111111111','customer_id'=>'20','payment_type'=>'CASH','amount_received'=>'750.00','hybrid'=>$current['request']]; unset($input['hybrid']['channel']);
    if ($where==='hybrid') { $input['hybrid']['calculation_revision']='LEGACY'; } elseif ($where==='outer') { $input['calculation_revision']='LEGACY'; } else { $input['hybrid']['lines'][0]['calculation_revision']='LEGACY'; }
    rejectSnapshot(fn()=>J::normalize($input));
}
// Reject tampering even after self-hashes are recomputed; a trusted external digest is separate.
$mutations=[
    fn($s)=>array_replace($s,['snapshot_version'=>'UNKNOWN']),
    function($s){unset($s['seal']);return $s;},
    function($s){$s['seal']['allocation_version']='UNKNOWN';return $s;},
    function($s){$s['seal']['counts']['runs']++;return $s;},
    function($s){$s['review']['calculation']['final_payable_total']='674.99';return $s;},
    function($s){$s['allocations']['segments'][0]['runs'][0]['cents']['net']='0';return $s;},
];
foreach ($mutations as $mutate) { rejectSnapshot(fn()=>S::reconcile($mutate($old))); }
foreach (['UNKNOWN',null,'HYBRID_STACKING_V1',S::CURRENT_REVISION] as $revision) { $bad=$old; $bad['review']['calculation_revision']=$revision; $bad=resealSnapshot($bad); rejectSnapshot(fn()=>S::reconcile($bad)); }
foreach (['net','segment','request','ordering','counts','unit_version'] as $target) {
    $bad=$old;
    if ($target==='net') { $bad['review']['calculation']['final_payable_total']='675.01'; }
    elseif ($target==='segment') { $bad['review']['calculation']['lines'][0]['segments'][0]['payable_amount']='539.99'; }
    elseif ($target==='request') { $bad['request']['invoice_discount_percent']='11'; }
    elseif ($target==='ordering') { $bad['allocations']['segments']=array_reverse($bad['allocations']['segments']); }
    elseif ($target==='counts') { $bad['seal']['counts']['segments']++; }
    else { $bad['review']['unit_allocation_version']='UNKNOWN'; }
    $bad=resealSnapshot($bad); rejectSnapshot(fn()=>S::reconcile($bad));
}
$bad=$old; unset($bad['review']['calculation_revision']); $bad['allocations']['segments'][0]['runs'][0]['cents']['invoice_discount_amount']='5999'; $bad=resealSnapshot($bad); rejectSnapshot(fn()=>S::reconcile($bad));
$saved=serialize($old); equalSnapshot(S::interpretation($old),S::HISTORICAL); equalSnapshot(serialize($old),$saved);
// Frozen forward arithmetic must retain the original strict cap, including odd cents.
$capProducts=[['product_id'=>'101','unit_price'=>'100.00','price_version'=>'71','available_quantity'=>'1000','promotion'=>null]];
$capRequest=['policy_version'=>'HYBRID_PROMOTION_V1','channel'=>'ONLINE','lines'=>[['product_id'=>'101','paid_quantity'=>'1','product_discount'=>['choice'=>'other','percent'=>'50']]],'invoice_discount_percent'=>'0'];
equalSnapshot(App\Services\HistoricalHybridV1\HybridPromotionPolicy::calculate($capRequest,$capProducts)['combined_monetary_discount'],'50.00');
foreach (['odd','stacked'] as $case) {
    $p=$capProducts; $q=$capRequest;
    if ($case==='odd') { $p[0]['unit_price']='100.01'; } else { $q['invoice_discount_percent']='1'; }
    try { App\Services\HistoricalHybridV1\HybridPromotionPolicy::calculate($q,$p); throw new RuntimeException('Historical cap was weakened.'); }
    catch (App\Support\HybridCalculationException $e) { equalSnapshot($e->findings[0]['code'],'COMBINED_DISCOUNT_CAP_EXCEEDED'); }
}
// A fully self-consistent different snapshot needs an independently retained digest.
rejectSnapshot(static function()use($old,$current){if(!hash_equals($old['seal']['digest'],$current['seal']['digest']))throw new LogicException('Trusted original digest mismatch.');});
echo "Snapshot compatibility: $checks assertions PASS; frozen synthetic originals, no database or HTTP.".PHP_EOL;
