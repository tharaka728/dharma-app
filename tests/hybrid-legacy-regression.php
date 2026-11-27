<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php'; require __DIR__.'/hybrid-fixtures.php';
use App\Services\{Invoices,HybridDatabaseScope,BenefitPolicy};
$m=HybridIsolationHarness::manifest($argv[1]??''); $pdo=HybridIsolationHarness::connection($m); HybridDatabaseScope::verify($pdo,$m);
$f=json_decode(file_get_contents($m['directory'].'/fixtures.json'),true,512,JSON_THROW_ON_ERROR); $rep=['id'=>$f['users']['rep1']]; $benefit=in_array('--benefit',$argv,true); $checks=0;
$originalHash=hash_file('sha256',APP_ROOT.'/config/benefit-modes.php');
putenv('DHARMA_BENEFIT_TEST='.($benefit?'1':'0'));
if ($benefit) {
    // Child-process overrides only, scoped by the existing legacy gate to this proven schema.
    putenv('DHARMA_BENEFIT_TEST_DB='.$m['database']); putenv('DHARMA_BENEFIT_TEST_PILOT_IDS='.$rep['id']); putenv('DHARMA_BENEFIT_TEST_POSTING_HOLD=0');
}
$service=new Invoices($pdo);
$same=static function ($a,$b) use (&$checks) { $checks++; if ($a!==$b) { throw new RuntimeException('Legacy regression mismatch: '.json_encode([$a,$b])); } };
$cases=$benefit ? [
    ['glucose','8','FREE_PROMOTION',null,'800.00','1'],
    ['glucose','12','PROMOTION_DISCOUNT_INSTEAD','25','900.00','0'],
    ['ordinary','1','ORDINARY_INVOICE_DISCOUNT','35','65.00','0'],
    ['ordinary','1','ORDINARY_INVOICE_DISCOUNT','75','25.00','0'],
] : [['ordinary','3',null,null,'300.00','0'],['ordinary','3',null,'25','225.00','0'],['ordinary','1',null,'100','0.00','0']];
foreach ($cases as [$product,$quantity,$mode,$rate,$payable,$free]) {
    $input=['request_id'=>hybridUuid(),'customer_id'=>$f['customers']['rep1'],'payment_type'=>'CASH','amount_received'=>'10000.00','items'=>[['product_id'=>$f['products'][$product],'quantity'=>$quantity]]];
    if ($benefit) { $input['benefit_policy']=BenefitPolicy::POLICY; $input['benefit_mode']=$mode; if ($rate!==null) { $input['promotion_discount_choice']=$rate==='75'?'custom':$rate; if ($rate==='75') { $input['promotion_discount_percent']='75'; } } }
    elseif ($rate!==null) { $input['discount_policy']='INVOICE_PERCENT_V1'; $input['invoice_discount_choice']='custom'; $input['invoice_discount_percent']=$rate; }
    $review=$service->review($rep,$input); $same($review['grand_total'],$payable); $input['review_hash']=$review['review_hash']; $out=$service->issue($rep,$input); $record=$service->get($rep,$out['invoice_id']);
    $same($record['grand_total'],$payable); $same($record['items'][0]['free_quantity'],$free); $same(isset($record['hybrid']),false); $same($service->issue($rep,$input)['replayed'],true);
    $q=$pdo->prepare('SELECT COUNT(*) FROM hybrid_invoice_snapshots WHERE invoice_id=?'); $q->execute([$out['invoice_id']]); $same((int)$q->fetchColumn(),0);
    if ($benefit) { $same($record['benefit_mode'],$mode); }
}
$q=$pdo->prepare('SELECT quantity FROM representative_stock_balances WHERE rep_id=? AND product_id=?');$q->execute([$rep['id'],$f['products'][$product]]);$beforeStock=(int)$q->fetchColumn();$voidInput=['request_id'=>hybridUuid(),'reason'=>'Synthetic legacy compatibility full void'];$admin=['id'=>$f['users']['admin']];$void=$service->void($admin,$out['invoice_id'],$voidInput);$same($void['refund_due'],$payable);$afterVoid=$service->get($rep,$out['invoice_id']);$same($afterVoid['validity'],'VOIDED');$same($afterVoid['outstanding'],'0.00');$same($afterVoid['refund_due'],$payable);$same($service->void($admin,$out['invoice_id'],$voidInput)['replayed'],true);$q->execute([$rep['id'],$f['products'][$product]]);$same((int)$q->fetchColumn(),$beforeStock+(int)$quantity+(int)$free);
$same(hash_file('sha256',APP_ROOT.'/config/benefit-modes.php'),$originalHash);
$same((require APP_ROOT.'/config/benefit-modes.php')['posting_hold'],true);
echo ($benefit?'PROMO_CHOICE_V1':'Legacy invoice').' isolated regressions: '.$checks.' checks passed; operational config unchanged.'.PHP_EOL;
