<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php'; require __DIR__.'/hybrid-fixtures.php';
try {
    $request=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
    $previewOnly=getenv('HYBRID_UI_PREVIEW_ONLY')==='1';
    if($previewOnly){
        $routes=['/rep/invoices/hybrid/new'=>'GET','/api/v1/rep/invoices/options'=>'GET','/api/v1/rep/invoices/preview'=>'POST','/api/v1/rep/invoices/hybrid/eligibility'=>'POST'];
        if(isset($request['action'])||($routes[$request['path']??'']??null)!==($request['method']??''))throw new RuntimeException('Preview boundary denied bridge action.');
        if($request['method']==='POST'&&(($request['payload']['hybrid']['policy_version']??null)!=='HYBRID_PROMOTION_V1'))throw new RuntimeException('Only explicit Hybrid previews are allowed.');
        $request['actor']='rep1';$request['mode']='posting-held';
    }
    $m=HybridIsolationHarness::manifest($argv[1]??''); $pdo=HybridIsolationHarness::connection($m);
    App\Services\HybridDatabaseScope::verify($pdo,$m);
    $f=json_decode(file_get_contents($m['directory'].'/fixtures-stage3.json'),true,512,JSON_THROW_ON_ERROR);
    $actor=$request['actor']??''; if(!in_array($actor,['rep1','rep2','admin'],true))throw new RuntimeException('Synthetic actor required.');
    if(($request['action']??'')==='change-price'){
        $pdo->prepare('UPDATE products SET selling_price=100.01,version=version+1 WHERE id=?')->execute([$f['products']['glucose']]);
        echo json_encode(['status'=>200,'api'=>true,'body'=>'{"changed":true}'],JSON_THROW_ON_ERROR);exit;
    }
    $config=hybridTestConfig($m,$f);$mode=$request['mode']??'enabled';
    $stage4=!$previewOnly&&getenv('HYBRID_UI_STAGE4')==='1';if($stage4)$config['return_posting']=$config['custody']=true;
    if($mode==='feature-disabled')$config['enabled']=false;
    if($mode==='posting-held'){$config['sale_posting']=false;$config['financial_posting_hold']=true;}
    if($mode==='legacy-pilot'){
        putenv('DHARMA_BENEFIT_TEST=1');putenv('DHARMA_BENEFIT_TEST_DB='.$m['database']);putenv('DHARMA_BENEFIT_TEST_PILOT_IDS='.$f['users']['rep1']);putenv('DHARMA_BENEFIT_TEST_POSTING_HOLD=1');
    }
    $store=new App\Services\PdoHybridCheckoutStore($pdo,$m,$mode==='posting-held');
    $service=new App\Services\Invoices($pdo,new App\Services\HybridInvoiceCheckout($store,$config),$stage4?new App\Services\HybridReturns($pdo,$config,$m,$mode==='posting-held'):null);
    $path=$request['path']??'';$combined=getenv('HYBRID_UI_STAGE6A')==='1';$dashboard=$combined&&in_array($path,['/admin','/rep'],true);$customer=$combined?App\Controllers\CustomerController::route($path):null;
    $document=getenv('HYBRID_UI_STAGE5')==='1'?App\Controllers\InvoiceDocumentController::route($path):null;$report=getenv('HYBRID_UI_STAGE5')==='1'?App\Controllers\ReportController::route($path):null;$route=$dashboard?['api'=>false,'role'=>$path==='/admin'?'ADMIN':'SALES_REP']:($customer??$document??$report??App\Controllers\InvoiceController::route($path));
    if(!$route)throw new RuntimeException('Unsupported isolated invoice route.');
    $_SERVER['REQUEST_METHOD']=$request['method']??'GET';$_SERVER['REQUEST_URI']=$path;
    // The test HTTP router parses JSON and forwards an equivalent form object over stdin.
    // Real controller CSRF, authorization, request normalization and services still execute.
    $_SERVER['CONTENT_TYPE']='application/x-www-form-urlencoded';$_POST=$request['payload']??[];$_GET=$request['query']??[];
    $_SESSION=['csrf_token'=>$request['csrf']??''];$_COOKIE['dharma_language']='en';App\Support\I18n::set('en');http_response_code(200);
    $user=$service->actor(['id'=>$f['users'][$actor]]);
    if(!App\Middleware\Authorization::allows($user,$route['role']??'ADMIN')){echo json_encode(['status'=>403,'api'=>true,'binary'=>false,'body'=>'{"errors":{"access":"Unavailable workspace."}}']);exit;}
    ob_start();if($dashboard)(new App\Controllers\AuthController(new App\Services\Authentication($pdo)))->dashboard($user,$route['role'],$pdo);elseif($customer)(new App\Controllers\CustomerController(new App\Services\Customers($pdo),new App\Services\Locations($pdo),$pdo))->handle($route,$user);elseif($document)(new App\Controllers\InvoiceDocumentController(new App\Services\InvoiceDocuments($pdo)))->handle($route,$user);elseif($report)(new App\Controllers\ReportController(new App\Services\Reports($pdo)))->handle($route,$user);else(new App\Controllers\InvoiceController($service))->handle($route,$user);$body=ob_get_clean();
    // Owner demo: the restored schema also retains older synthetic fixture batches.
    // Show only this fixture's stable IDs; authoritative preview resolution is unchanged.
    if($previewOnly&&http_response_code()===200){
        $fixtureProducts=array_map('strval',array_values($f['products']));
        if($path==='/rep/invoices/hybrid/new'){
            $body=preg_replace_callback('~(<select id="hybrid-product"[^>]*>)(.*?)(</select>)~s',static function(array $select)use($fixtureProducts):string{
                $options=preg_replace_callback('~<option value="([^"]*)"[^>]*>.*?</option>~s',static fn(array $option):string=>$option[1]===''||in_array($option[1],$fixtureProducts,true)?$option[0]:'',$select[2]);
                return $select[1].$options.$select[3];
            },$body,-1,$matched);
            if($matched!==1)throw new RuntimeException('Synthetic fixture product picker was not found.');
        }elseif($path==='/api/v1/rep/invoices/options'){
            $options=json_decode($body,true,512,JSON_THROW_ON_ERROR);
            $options['products']=array_values(array_filter($options['products'],static fn(array $product):bool=>in_array((string)$product['id'],$fixtureProducts,true)));
            $body=json_encode($options,JSON_THROW_ON_ERROR);
        }
    }
    $binary=$document&&$route['format']==='pdf'&&http_response_code()===200;
    echo json_encode(['status'=>http_response_code(),'api'=>($route['api']??false)||http_response_code()>=400,'binary'=>$binary,'body'=>$binary?base64_encode($body):$body],JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(ob_get_level())ob_end_clean();fwrite(STDERR,get_class($e).': '.$e->getMessage());echo json_encode(['status'=>500,'api'=>true,'body'=>'{"errors":{"test":"Isolated controller bridge failed."}}']);exit(1);}
