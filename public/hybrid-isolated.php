<?php
declare(strict_types=1);
// Separate opt-in runtime. Normal index.php, Apache and the owner demo are untouched.
require dirname(__DIR__).'/app/isolated-bootstrap.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header("Content-Security-Policy: default-src 'self'; style-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
try{
    if(($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1')throw new RuntimeException('ISOLATED_CONTEXT_REQUIRED');
    $profile=App\Support\HybridIsolatedRuntime::profile(getenv('HYBRID_ISOLATED_PROFILE')?:'');$p=App\Support\HybridIsolatedRuntime::connect($profile['scope'],$profile['account']);
    if((int)($_SERVER['CONTENT_LENGTH']??0)>262144){App\Support\Http::error('REQUEST_TOO_LARGE','Request is too large.',413);exit;}
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/';$method=$_SERVER['REQUEST_METHOD'];
    if($method==='GET'&&($locale=App\Support\I18n::valid($_GET['language']??null))){App\Support\I18n::set($locale);$_COOKIE['dharma_language']=$locale;setcookie('dharma_language',$locale,['path'=>'/','samesite'=>'Lax']);}
    App\Middleware\Session::start(false);$auth=new App\Services\Authentication($p);$controller=new App\Controllers\AuthController($auth);
    if($path==='/login'&&in_array($method,['GET','POST'],true)){$controller->login();exit;}
    if($path==='/'){App\Controllers\AuthController::redirect('/login');exit;}
    $user=$auth->currentUser();if(!$user){App\Support\Http::error('UNAUTHENTICATED','Sign in to continue.',401);exit;}
    if($path==='/api/v1/me'&&$method==='GET'){App\Support\Http::json(['user'=>$user,'csrf'=>$_SESSION['csrf_token']]);exit;}
    if($path==='/logout'&&$method==='POST'){$controller->logout();exit;}
    if(in_array($path,['/rep','/admin'],true)&&$method==='GET'&&$user['role']===($path==='/admin'?'ADMIN':'SALES_REP')){$controller->dashboard($user,$user['role'],$p);exit;}
    // Existing authenticated readers remain usable after synthetic issue/return.
    $document=App\Controllers\InvoiceDocumentController::route($path);$report=App\Controllers\ReportController::route($path);
    if($method==='GET'&&($document||$report)){$read=$document??$report;if(!App\Middleware\Authorization::allows($user,$read['role'])){App\Support\Http::error('FORBIDDEN','This route is unavailable for your role.',403);exit;}if($document)(new App\Controllers\InvoiceDocumentController(new App\Services\InvoiceDocuments($p)))->handle($read,$user);else(new App\Controllers\ReportController(new App\Services\Reports($p)))->handle($read,$user);exit;}
    $payload=$_POST;if($method==='POST'&&str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){$raw=file_get_contents('php://input',false,null,0,262145);if(strlen($raw)>262144){App\Support\Http::error('REQUEST_TOO_LARGE','Request is too large.',413);exit;}$payload=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($payload))throw new RuntimeException('INVALID_REQUEST');}
    $route=App\Support\HybridWorkerClient::route($path,$method,$payload);
    if(!App\Middleware\Authorization::allows($user,$route['role'])){App\Support\Http::error('FORBIDDEN','This route is unavailable for your role.',403);exit;}
    if($method==='POST'){$token=$payload['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);if(!is_string($token)||!App\Middleware\Csrf::valid($token,$_SESSION['csrf_token']??null)){App\Support\Http::error('CSRF_FAILED','Refresh the page and try again.',403);exit;}$payload['csrf_token']=$token;}
    $context=['path'=>$path,'method'=>$method,'payload'=>$payload,'query'=>$_GET,'session'=>$_SESSION,'locale'=>App\Support\I18n::locale()];session_write_close();
    $out=App\Support\HybridWorkerClient::call($profile,$context);http_response_code($out['status']);header('Content-Type: '.$out['type']);echo$out['body'];
}catch(Throwable$e){$code=in_array($e->getMessage(),['ROUTE_DENIED','ONLINE_HYBRID_REQUIRED'],true)?'ROUTE_DENIED':'OUTCOME_UNKNOWN';App\Support\Http::error($code,$code==='ROUTE_DENIED'?'This route is unavailable.':'Outcome unavailable. Retry the same request UUID; do not create a replacement command.',$code==='ROUTE_DENIED'?403:503);}
