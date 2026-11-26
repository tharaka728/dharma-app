<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-stage5-fixtures.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');$p=HybridIsolationHarness::connection($m);App\Services\HybridDatabaseScope::verify($p,$m);
$f=hybridFixtures($p,$m);stage5Controls($p,$f['users']['admin'],1);$checks=0;
function rc(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function request6d(array $cfg,string $path,string $method,array $input=[]):array{
    global $p,$m,$f;
    $store=new App\Services\PdoHybridCheckoutStore($p,$m,true);
    $service=new App\Services\Invoices($p,new App\Services\HybridInvoiceCheckout($store,$cfg));
    $_SERVER['REQUEST_METHOD']=$method;$_SERVER['CONTENT_TYPE']='application/x-www-form-urlencoded';$_SERVER['REQUEST_URI']=$path;
    $_SESSION=['csrf_token'=>str_repeat('a',64)];$_GET=[];$_POST=$input+['csrf_token'=>$_SESSION['csrf_token']];http_response_code(200);
    ob_start();(new App\Controllers\InvoiceController($service))->handle(App\Controllers\InvoiceController::route($path),$service->actor(['id'=>$f['users']['rep1']]));$body=ob_get_clean();return [http_response_code(),$body];
}
try{
    $cfg=hybridTestConfig($m,$f);$cfg['financial_posting_hold']=true;$cfg['sale_posting']=false;
    [$status,$body]=request6d($cfg,'/rep/invoices/new','GET');rc($status===200&&str_contains($body,'data-template="app/Views/invoices.php"'),'Eligible New Invoice uses the original form');rc(App\Controllers\InvoiceController::route('/rep/invoices/hybrid/new')===null,'Discarded alias removed');
    $off=$cfg;$off['enabled']=false;[$status]=request6d($off,'/rep/invoices/new','GET');rc($status===409,'Disabled Hybrid never opens legacy screen');
    [$status]=request6d($cfg,'/api/v1/rep/invoices/preview','POST',['customer_id'=>$f['customers']['rep1']]);rc($status===409,'Unversioned new sale denied');
    $input=hybridInput($f);$input['hybrid']['lines']=[['product_id'=>$f['products']['ordinary'],'paid_quantity'=>'1','product_discount'=>['choice'=>'0']]];$input['hybrid']['invoice_discount_percent']='0';
    [$status,$body]=request6d($cfg,'/api/v1/rep/invoices/preview','POST',$input);rc($status===200&&json_decode($body,true)['calculation']['final_payable_total']==='100.00','Zero-benefit stays Hybrid');
    $object=$input;$object['hybrid']=(object)$object['hybrid'];[$status]=request6d($cfg,'/api/v1/rep/invoices/preview','POST',$object);rc($status===200,'JSON object envelope supported');
    $spoof=$input;$spoof['stock_source']='warehouse';[$status]=request6d($cfg,'/api/v1/rep/invoices/preview','POST',$spoof);rc($status===403,'Stock-source spoof rejected');
    $other=$input;$other['customer_id']=$f['customers']['rep2'];[$status]=request6d($cfg,'/api/v1/rep/invoices/preview','POST',$other);rc($status===403,'Other representative customer rejected');
    $wrong=$cfg;$wrong['bindings'][$f['products']['glucose']]['promotion_id']='999999';
    [$status,$body]=request6d($wrong,'/api/v1/rep/invoices/preview','POST',hybridInput($f));rc($status===409&&str_contains($body,'stable-ID'),'Incorrect stable-ID binding rejected');
    putenv('DHARMA_BENEFIT_TEST=1');putenv('DHARMA_BENEFIT_TEST_DB='.$m['database']);putenv('DHARMA_BENEFIT_TEST_PILOT_IDS='.$f['users']['rep1']);putenv('DHARMA_BENEFIT_TEST_POSTING_HOLD=1');
    [$status,$body]=request6d($cfg,'/rep/invoices/new','GET');rc($status===200&&str_contains($body,'id="invoice-form"'),'Pilot retains original contract screen');
    rc(App\Support\HybridWorkerClient::route('/rep/invoices/new','GET',[])['action']==='create','Worker accepts original-form GET');
    try{App\Support\HybridWorkerClient::route('/rep/invoices/new','POST',$input);throw new RuntimeException('Unexpected HTML writer');}catch(RuntimeException $e){rc($e->getMessage()==='ROUTE_DENIED','Worker adds no HTML financial route');}
    echo "Stage 6D routing: $checks checks passed; no fallback, scoped zero-benefit review, pilot preservation.\n";
}finally{
    foreach(['DHARMA_BENEFIT_TEST','DHARMA_BENEFIT_TEST_DB','DHARMA_BENEFIT_TEST_PILOT_IDS','DHARMA_BENEFIT_TEST_POSTING_HOLD']as$key)putenv($key);
    stage5Controls($p,$f['users']['admin'],0);
}
