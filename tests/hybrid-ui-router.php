<?php
declare(strict_types=1);
// Test-only loopback server: no app bootstrap, PDO, .env, global config or public/index fallback.
if(PHP_SAPI!=='cli-server')exit(1);
ini_set('zend.exception_ignore_args','1');
$root=dirname(__DIR__);$manifest=getenv('HYBRID_UI_MANIFEST');$key=getenv('HYBRID_UI_TEST_KEY');
if(!$manifest||!$key){http_response_code(503);exit;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/';
$previewOnly=getenv('HYBRID_UI_PREVIEW_ONLY')==='1';
if($previewOnly){
    if(($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(403);exit;}
    $method=$_SERVER['REQUEST_METHOD']??'';
    $allowed=['/'=>['GET'],'/rep/invoices/hybrid/new'=>['GET'],'/api/v1/rep/invoices/options'=>['GET'],'/api/v1/rep/invoices/preview'=>['POST'],'/api/v1/rep/invoices/hybrid/eligibility'=>['POST']];
    if(strpos($path,'/assets/')===0){
        $file=realpath($root.'/public'.$path);$assets=realpath($root.'/public/assets');
        if(!in_array($method,['GET','HEAD'],true)||!$file||strpos($file,$assets.DIRECTORY_SEPARATOR)!==0||!is_file($file)||!in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),['css','js','json','woff','woff2','svg','png','ico'],true)){http_response_code(403);exit;}
        return false;
    }
    if(!isset($allowed[$path])||!in_array($method,$allowed[$path],true)){
        http_response_code(403);header('Cache-Control: no-store');header('Content-Type: application/json');echo '{"errors":{"preview":"Preview only: this route or method is disabled."}}';exit;
    }
}
if(strpos($path,'/assets/')===0){$file=realpath($root.'/public'.$path);$public=realpath($root.'/public/assets');if($file&&strpos($file,$public.DIRECTORY_SEPARATOR)===0&&is_file($file))return false;http_response_code(404);exit;}
header('Cache-Control: private, no-store');session_name('hybrid_stage3_test');session_start();
if($previewOnly){
    $_SESSION['actor']='rep1';$_SESSION['mode']='posting-held';$_SESSION['csrf']=$_SESSION['csrf']??bin2hex(random_bytes(32));
    if($path==='/'){session_regenerate_id(true);header('Location: /rep/invoices/hybrid/new',true,303);exit;}
}
if($path==='/test/session'){
    if(!hash_equals($key,$_SERVER['HTTP_X_ISOLATION_TEST_TOKEN']??'')){http_response_code(403);exit;}
    $p=json_decode(file_get_contents('php://input'),true)??[];
    if(!in_array($p['actor']??'',['rep1','rep2','admin','anonymous'],true)||!in_array($p['mode']??'enabled',['enabled','posting-held','feature-disabled','legacy-pilot'],true)){http_response_code(400);exit;}
    session_regenerate_id(true);setcookie('dharma_language','en',['path'=>'/','samesite'=>'Lax']);$_SESSION=['actor'=>$p['actor'],'mode'=>$p['mode']??'enabled','csrf'=>bin2hex(random_bytes(32))];header('Content-Type: application/json');echo json_encode(['csrf'=>$_SESSION['csrf']]);exit;
}
if(($_SESSION['actor']??'anonymous')==='anonymous'){http_response_code(401);header('Content-Type: application/json');echo '{"errors":{"auth":"Sign in required."}}';exit;}
$action=null;
if($path==='/test/change-price'){
    if(!hash_equals($key,$_SERVER['HTTP_X_ISOLATION_TEST_TOKEN']??'')){http_response_code(403);exit;}$action='change-price';
}elseif(!(getenv('HYBRID_UI_STAGE6A')==='1'&&preg_match('~^/(?:admin|rep)(?:/customers/[1-9][0-9]*|/invoices/[0-9a-f-]{36})?$~D',$path))&&!preg_match('~^/(?:api/v1/)?rep/invoices(?:/new|/preview|/options|/hybrid/new|/hybrid/eligibility)?$~D',$path)&&!(getenv('HYBRID_UI_STAGE4')==='1'&&preg_match('~^/(?:api/v1/)?(?:rep|admin)/invoices/hybrid/returns(?:/(?:options|details|custody|preview|post|receive|supplement|manage))?$~D',$path))&&!(getenv('HYBRID_UI_STAGE5')==='1'&&preg_match('~^/(?:rep|admin)/(?:invoices/[0-9a-f-]{36}(?:/hybrid-returns/[0-9a-f-]{36})?/(?:receipt|a4|pdf)|reports/[a-z]+(?:/csv)?)$~D',$path))) {http_response_code(404);exit;}
$payload=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{$payload=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){http_response_code(400);exit;}
    if(!is_array($payload)){http_response_code(400);exit;}
    $payload['csrf_token']=$payload['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??'');
}
$input=['actor'=>$_SESSION['actor'],'mode'=>$_SESSION['mode'],'csrf'=>$_SESSION['csrf'],'path'=>$path,'method'=>$_SERVER['REQUEST_METHOD'],'payload'=>$payload,'query'=>$_GET,'action'=>$action];
session_write_close();
$pipes=[];$process=proc_open([PHP_BINARY,$root.'/tests/hybrid-ui-bridge.php',$manifest],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',dirname($manifest).'/stage3-bridge-errors.log','a']],$pipes,$root,null,['bypass_shell'=>true,'create_new_console'=>false]);
fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($process);
$result=json_decode($out,true);if(!$result){http_response_code(500);header('Content-Type: application/json');echo '{"errors":{"test":"Invalid isolated bridge response."}}';exit;}
http_response_code($result['status']);header('Content-Type: '.(($result['binary']??false)?'application/pdf':($result['api']?'application/json':'text/html; charset=utf-8')));echo ($result['binary']??false)?base64_decode($result['body'],true):$result['body'];
