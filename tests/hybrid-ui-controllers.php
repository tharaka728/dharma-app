<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-fixtures.php';
use App\Controllers\InvoiceController;use App\Services\{Invoices,HybridInvoiceCheckout,PdoHybridCheckoutStore,HybridDatabaseScope};
$m=HybridIsolationHarness::manifest($argv[1]??'');$pdo=HybridIsolationHarness::connection($m);HybridDatabaseScope::verify($pdo,$m);
$f=json_decode(file_get_contents($m['directory'].'/fixtures-stage3.json'),true,512,JSON_THROW_ON_ERROR);$config=hybridTestConfig($m,$f);$count=0;
$_SESSION=['csrf_token'=>str_repeat('a',64)];App\Support\I18n::set('en');putenv('DHARMA_BENEFIT_TEST=0');ob_start();
$check=static function(bool $value,string $label)use(&$count){if(!$value)throw new RuntimeException($label);$count++;};
$service=static fn($c)=>new Invoices($pdo,new HybridInvoiceCheckout(new PdoHybridCheckoutStore($pdo,$m,!$c['sale_posting']),$c));
$invoke=static function(Invoices $svc,string $actor,string $path,string $method='GET',array $payload=[])use($f):array{
    $_SERVER['REQUEST_METHOD']=$method;$_SERVER['REQUEST_URI']=$path;$_SERVER['CONTENT_TYPE']='application/x-www-form-urlencoded';$_POST=$payload;$_GET=[];http_response_code(200);
    $user=$svc->actor(['id'=>$f['users'][$actor]]);$route=InvoiceController::route($path);ob_start();(new InvoiceController($svc))->handle($route,$user);$body=ob_get_clean();return ['status'=>http_response_code(),'body'=>$body];
};
$initial=(int)$pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();$svc=$service($config);
foreach(['rep1','rep2']as$rep){$r=$invoke($svc,$rep,'/rep/invoices/hybrid/new');$check($r['status']===200,'Both active representatives can render Hybrid');$check(strpos($r['body'],'id="hybrid-invoice-form"')!==false,'Hybrid form present');$check(strpos($r['body'],'data-posting-enabled="true"')!==false,'Synthetic posting capability');}
$r=$invoke($svc,'admin','/rep/invoices/hybrid/new');$check($r['status']===403,'Administrator is not a representative UI actor');$check(strpos($r['body'],'id="hybrid-invoice-form"')===false,'No unauthorized controls');
$r=$invoke($svc,'rep1','/rep/invoices/hybrid/new','POST');$check($r['status']===405,'New screen is GET only');
$disabled=$config;$disabled['enabled']=false;$r=$invoke($service($disabled),'rep1','/rep/invoices/hybrid/new');$check($r['status']===409,'Disabled feature fails closed');
$r=$invoke($service($disabled),'rep1','/rep/invoices/new');$check($r['status']===200&&strpos($r['body'],'id="invoice-form"')!==false,'Legacy create still works');$check(strpos($r['body'],'href="/rep/invoices/hybrid/new"')===false,'Disabled Hybrid entry absent');
$input=hybridInput($f,'replacement','24');$input['hybrid']['lines'][0]['product_discount']=['choice'=>'0'];$input['csrf_token']=$_SESSION['csrf_token'];
$bad=$input;$bad['csrf_token']=str_repeat('b',64);$r=$invoke($svc,'rep1','/api/v1/rep/invoices/hybrid/eligibility','POST',$bad);$check($r['status']===403,'Eligibility checks CSRF');
$r=$invoke($svc,'rep1','/api/v1/rep/invoices/hybrid/eligibility','POST',$input);$data=json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);$check($r['status']===200,'Eligibility succeeds');$check($data['lines'][0]['replacement_supported']===true,'Genuine complete-shortage discovery');$check($data['financial_review']===false&&!isset($data['review_hash']),'Eligibility is never a financial confirmation');
$input=hybridInput($f,'partial','12');$input['csrf_token']=$_SESSION['csrf_token'];$r=$invoke($svc,'rep1','/api/v1/rep/invoices/hybrid/eligibility','POST',$input);$data=json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);$check($data['lines'][0]['stock_status']==='PARTIAL_FREE_SHORTAGE'&&!$data['lines'][0]['replacement_supported'],'Partial shortage is not substituted');
foreach(['monetary','oil1']as$product){$input=hybridInput($f,$product,'6');$input['csrf_token']=$_SESSION['csrf_token'];$r=$invoke($svc,'rep1','/api/v1/rep/invoices/hybrid/eligibility','POST',$input);$check($r['status']===409,'Offer conflict '.$product);}
$input=hybridInput($f);$input['csrf_token']=$_SESSION['csrf_token'];$r=$invoke($svc,'rep1','/api/v1/rep/invoices/preview','POST',$input);$review=json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);$check($r['status']===200&&$review['policy_version']==='HYBRID_PROMOTION_V1','Existing authoritative preview route handles Hybrid');$check((bool)preg_match('/^[a-f0-9]{64}$/D',$review['review_hash']),'Server review hash supplied');
$bad=$input;$bad['hybrid']['lines'][0]['product_discount']=['choice'=>'other','percent'=>'50.01'];$r=$invoke($svc,'rep1','/api/v1/rep/invoices/preview','POST',$bad);$check($r['status']===409,'Server rejects forged above-limit product rate');
$bad=$input;$bad['stock_source']=$f['users']['rep2'];$r=$invoke($svc,'rep1','/api/v1/rep/invoices/preview','POST',$bad);$check($r['status']===403,'Server rejects forged source');
$held=$config;$held['sale_posting']=false;$held['financial_posting_hold']=true;$heldSvc=$service($held);$r=$invoke($heldSvc,'rep1','/rep/invoices/hybrid/new');$check($r['status']===200&&strpos($r['body'],'data-posting-enabled="false"')!==false,'Preview UI separate from posting');
$input['review_hash']=$review['review_hash'];$r=$invoke($heldSvc,'rep1','/api/v1/rep/invoices','POST',$input);$check($r['status']===403,'Direct issue cannot bypass the posting gate');
$pdo->beginTransaction();try{$pdo->exec('UPDATE hybrid_operating_state SET enabled=0 WHERE id=1');$r=$invoke($svc,'rep1','/rep/invoices/hybrid/new');$check($r['status']===409,'Database gate hides Hybrid');$r=$invoke($svc,'rep1','/rep/invoices/new');$check($r['status']===200,'Closed Hybrid readiness does not break legacy form');}finally{$pdo->rollBack();}
$check((int)$pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn()===$initial,'All rendering/discovery/preview/rejections are read only');
ob_end_clean();echo 'Stage 3 controller integration: '.$count.' checks passed; synthetic isolated database only.'.PHP_EOL;
