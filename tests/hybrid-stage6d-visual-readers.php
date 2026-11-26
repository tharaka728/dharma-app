<?php
declare(strict_types=1);
// Reuse the existing preview fixture. No fixture creation, control updates or financial posting.
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-fixtures.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');$p=HybridIsolationHarness::connection($m);App\Services\HybridDatabaseScope::verify($p,$m);
$f=json_decode(file_get_contents($m['directory'].'/fixtures-stage3.json'),true,512,JSON_THROW_ON_ERROR);$checks=0;
$cfg=hybridTestConfig($m,$f);$cfg['sale_posting']=false;$cfg['financial_posting_hold']=true;
$service=static fn(array $config)=>new App\Services\Invoices($p,new App\Services\HybridInvoiceCheckout(new App\Services\PdoHybridCheckoutStore($p,$m,true),$config));
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$reject=static function(callable $work,int $status,string $text='')use($check):void{try{$work();throw new RuntimeException('Expected rejection');}catch(App\Support\InventoryException $e){$check($e->status===$status&&($text===''||str_contains(json_encode($e->errors),$text)),'Authoritative rejection');}};
$actor=['id'=>$f['users']['rep1']];
foreach(['glucose','suwada']as$key){$wrong=$cfg;$wrong['bindings'][$f['products'][$key]]['promotion_id']='999999999';$reject(static fn()=>$service($wrong)->review($actor,hybridInput($f,$key,$key==='glucose'?'8':'12')),409,'stable-ID');}
$input=hybridInput($f);$input['stock_source']=$f['users']['rep2'];$reject(static fn()=>$service($cfg)->review($actor,$input),403);
$input=hybridInput($f);$input['customer_id']=$f['customers']['rep2'];$reject(static fn()=>$service($cfg)->review($actor,$input),403);
$render=static function(array $config)use($service,$actor):array{
    $_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/rep/invoices/new';$_GET=$_POST=[];$_SESSION=['csrf_token'=>str_repeat('a',64)];App\Support\I18n::set('en');http_response_code(200);
    ob_start();(new App\Controllers\InvoiceController($service($config)))->handle(App\Controllers\InvoiceController::route('/rep/invoices/new'),$service($config)->actor($actor));return [http_response_code(),ob_get_clean()];
};
[$status,$html]=$render($cfg);$check($status===200&&str_contains($html,'data-template="app/Views/invoices.php"'),'Available checkout uses original invoice template');$check(!str_contains($html,'ONLINE CHECKOUT'),'Separate Hybrid page heading absent');
$off=$cfg;$off['enabled']=false;[$status]=$render($off);$check($status===409,'No legacy fallback when disabled');
try{
    putenv('DHARMA_BENEFIT_TEST=1');putenv('DHARMA_BENEFIT_TEST_DB='.$m['database']);putenv('DHARMA_BENEFIT_TEST_PILOT_IDS='.$f['users']['rep1']);putenv('DHARMA_BENEFIT_TEST_POSTING_HOLD=1');
    [$status,$html]=$render($cfg);$check($status===200&&str_contains($html,'id="invoice-form"')&&!str_contains($html,'id="hybrid-invoice-form"'),'Existing pilot retains exactly one original editor');
}finally{foreach(['DHARMA_BENEFIT_TEST','DHARMA_BENEFIT_TEST_DB','DHARMA_BENEFIT_TEST_PILOT_IDS','DHARMA_BENEFIT_TEST_POSTING_HOLD']as$key)putenv($key);}
$source=require APP_ROOT.'/config/hybrid-promotions.php';$check($source['enabled']===false&&$source['financial_posting_hold']===true&&$source['bindings']===[],'Source safety controls unchanged');
echo "Stage 6D visual read-only safety: $checks checks passed; both wrong stable-ID bindings, scope, pilot and fail-closed routing.\n";
