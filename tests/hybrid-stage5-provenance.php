<?php
declare(strict_types=1);
// Frozen synthetic arrays only: no bootstrap, environment, network, sessions or PDO.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
spl_autoload_register(static function (string $class): void {
    if ($class === 'App\\Support\\Database') { throw new RuntimeException('Database forbidden.'); }
    if (str_starts_with($class, 'App\\')) { require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php'; }
});
set_error_handler(static function (int $level, string $message): never { throw new ErrorException($message,0,$level); });
require __DIR__.'/hybrid-stage5-cohort.php';
use App\Services\{HybridInvoiceSnapshots as S, HybridInvoiceRequest as J, HybridReturnPolicy as R};
$checks=0;
function sameProv(mixed $a,mixed $b):void { global $checks; $checks++; if ($a!==$b) { throw new RuntimeException('Mismatch '.json_encode([$a,$b])); } }
function blockProv(callable $fn,string $needle):void { global $checks; $checks++; try { $fn(); } catch (RuntimeException|LogicException $e) { if ($needle!=='' && !str_contains($e->getMessage(),$needle)) { throw new RuntimeException('Unexpected diagnostic: '.$e->getMessage()); } return; } throw new RuntimeException('Expected provenance block: '.$needle); }
function resealProv(array $s):array {
    unset($s['review']['review_hash']); $s['review']['unit_allocation_digest']=J::digest($s['allocations']);
    $s['review']['review_hash']=J::digest($s['review']); $seal=$s['seal']; unset($s['seal']); $seal['digest']=J::digest($s); $s['seal']=$seal; return $s;
}
function receiptProv(string $paid,string $free='0'):array {
    $r=['line_number'=>'1','paid_quantity'=>$paid,'free_quantity'=>$free,'condition_reason'=>'Synthetic inspection','holding_reference'=>'Synthetic bin'];
    foreach (['paid'=>$paid,'free'=>$free] as $kind=>$quantity) { $r[$kind.'_conditions']=['SALEABLE'=>$quantity,'QUARANTINE'=>'0','UNSALEABLE'=>'0']; } return $r;
}
function planProv(array $s,array $history,array $lines,string $cash,string $operation='RETURN'):array {
    return R::plan($s,$history,['operation'=>$operation,'lines'=>$lines],['paid_amount'=>$cash,'cash_collected'=>$cash,'noncash'=>false,'legacy_void'=>false]);
}
function eventProv(array $plan):array { return array_diff_key($plan,['next_state'=>true]); }
function resolveProv(array $snapshot,?string $marker):array { return stage5ResolveCohort($marker,S::interpretation($snapshot)); }

$digests=[
    'legacy-675'=>'a856036ba1d9f8779683cd7589490e7cfbb91b713b8abb5a3cf9227d70a29e13',
    'current-unmarked-750'=>'86fc9ff378fe444bac07a2a6b4e6df2600ade52ba9bc4ee51dec15304d6eb137',
    'current-marked-750'=>'35ed095555f928e791697ce660fa3d9e507a2055e673c2c56d3108d4c4567d7e',
    'mixed-marked-1200'=>'e701bea743a5c98e5473c60e5af9720e84a4c144107b9f2672a739c5fd9f3eb7',
    'equivalent-unmarked'=>'bee0e70526c129dfd056d7e3abf36bb89eff661e6124e5e6f50a0d2b3e934ae7',
    'legacy-replacement-810'=>'bcbd759def8ed7c29c1bc428619cbd10eb988e32f1e48f0fa83eaa42f1b2b04d',
];
$snapshots=[]; $hashes=[];
foreach ($digests as $name=>$digest) {
    $path=__DIR__.'/fixtures/hybrid-snapshot-compatibility/'.$name.'.json';
    $hashes[$name]=hash_file('sha256',$path);
    $s=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
    sameProv($s['seal']['digest'],$digest); $snapshots[$name]=$s;
}
$old=$snapshots['legacy-675']; $marked=$snapshots['current-marked-750']; $unmarked=$snapshots['current-unmarked-750'];
$mixed=$snapshots['mixed-marked-1200']; $equivalent=$snapshots['equivalent-unmarked'];

// Verified historical fixture: the sealed full-calculation match selects the historical cohort; the missing marker is never the basis.
$saved=serialize($old);
$resolution=resolveProv($old,null); sameProv($resolution['cohort'],'HISTORICAL');
$expect=stage5CohortExpectations($resolution['cohort']);
sameProv([$expect['first_return_credit'],$expect['first_refund_increment'],$expect['total_credits'],$expect['residual_void_credit']],['225.00','225.00','4308.75','4083.75']);
sameProv(serialize($old),$saved);
$history=[]; foreach (['67.50','67.50','90.00'] as $index=>$unit) { $p=planProv($old,$history,[receiptProv('1',$index===2?'1':'0')],'675.00'); sameProv($p['totals']['net_credit'],$unit); $history[]=eventProv($p); }
sameProv(R::state($old,$history)['credit'],$expect['first_return_credit']);
sameProv(planProv($old,$history,[receiptProv('5')],'675.00','VOID')['totals']['net_credit'],'450.00');
sameProv(serialize($old),$saved);

// Verified current fixtures, marked and already-issued unmarked.
foreach ([[$marked,'UNPROMOTED_LINES_V1'],[$unmarked,null]] as [$s,$marker]) {
    $resolution=resolveProv($s,$marker); sameProv($resolution['cohort'],'CURRENT');
    $expect=stage5CohortExpectations($resolution['cohort']);
    sameProv([$expect['first_return_credit'],$expect['first_refund_increment'],$expect['total_credits'],$expect['residual_void_credit']],['250.00','250.00','4787.50','4537.50']);
    $history=[]; foreach (['75.00','75.00','100.00'] as $index=>$unit) { $p=planProv($s,$history,[receiptProv('1',$index===2?'1':'0')],'750.00'); sameProv($p['totals']['net_credit'],$unit); $history[]=eventProv($p); }
    sameProv(R::state($s,$history)['credit'],'250.00');
    sameProv(planProv($s,$history,[receiptProv('5')],'750.00','VOID')['totals']['net_credit'],'500.00');
}

// Approved current mixed basket: Glucose 750 with no invoice money, ordinary 450 carrying the 50 invoice allocation, total 1200.
$resolution=resolveProv($mixed,'UNPROMOTED_LINES_V1'); sameProv($resolution['cohort'],'CURRENT');
sameProv(array_column($mixed['review']['calculation']['lines'],'payable_amount'),['750.00','450.00']);
sameProv($mixed['review']['calculation']['final_payable_total'],'1200.00');
sameProv($mixed['review']['calculation']['invoice_discount_base'],'500.00');

// Mathematically equivalent unmarked results: verification passes, but cohort selection blocks instead of inventing an era.
S::reconcile($equivalent); sameProv(S::interpretation($equivalent),S::EQUIVALENT);
blockProv(fn()=>resolveProv($equivalent,null),'both revisions');
blockProv(fn()=>resolveProv($equivalent,'UNPROMOTED_LINES_V1'),'both revisions');

// Unknown markers fail closed even when the sealed calculation verifies.
blockProv(fn()=>resolveProv($marked,'LEGACY_STACKING_V1'),'unknown');
blockProv(fn()=>resolveProv($marked,'UNPROMOTED_LINES_V2'),'unknown');

// Marker/mathematics mismatch: a current envelope marker cannot reclassify a legacy-compatible snapshot.
blockProv(fn()=>resolveProv($old,'UNPROMOTED_LINES_V1'),'legacy stacking');

// Mismatched revisions: resealed revision markers can never downgrade or relabel a saved calculation.
$bad=$old; $bad['review']['calculation_revision']=S::CURRENT_REVISION;
blockProv(fn()=>S::interpretation(resealProv($bad)),'no supported revision');
$bad=$marked; $bad['review']['calculation_revision']='HYBRID_STACKING_V1';
blockProv(fn()=>S::interpretation(resealProv($bad)),'Unknown Hybrid calculation revision');

// Tampered or incomplete snapshot evidence blocks before any cohort is chosen.
$mutations=[
    fn($s)=>array_replace($s,['snapshot_version'=>'UNKNOWN']),
    function($s){unset($s['seal']);return $s;},
    function($s){$s['seal']['digest']=str_repeat('0',64);return $s;},
    function($s){$s['seal']['counts']['runs']++;return $s;},
    function($s){$s['review']['calculation']['final_payable_total']='674.99';return $s;},
    function($s){$s['review']['calculation']['lines'][0]['segments'][0]['payable_amount']='539.99';return $s;},
    function($s){$s['allocations']['segments']=array_reverse($s['allocations']['segments']);return $s;},
    function($s){$s['review']['calculation']['final_payable_total']='750.00';return resealProv($s);},
];
foreach ($mutations as $mutate) { blockProv(fn()=>resolveProv($mutate($old),null),''); }

// Original frozen bytes, hashes and allocations stay unchanged after every resolution path.
foreach ($digests as $name=>$digest) {
    $path=__DIR__.'/fixtures/hybrid-snapshot-compatibility/'.$name.'.json';
    sameProv(hash_file('sha256',$path),$hashes[$name]);
    $s=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR); sameProv($s['seal']['digest'],$digest);
}
echo "Stage 5 provenance: $checks assertions PASS; cohorts resolved from verified sealed calculations only; no database or HTTP.".PHP_EOL;
