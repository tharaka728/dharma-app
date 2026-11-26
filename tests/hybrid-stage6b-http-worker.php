<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-fixtures.php';
try{
    $envelope=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$key=getenv('HYBRID6B_KEY');
    if(!is_string($key)||strlen($key)!==64||!is_string($envelope['context']??null)||!hash_equals(hash_hmac('sha256',$envelope['context'],$key),$envelope['mac']??''))throw new RuntimeException('Untrusted handoff.');
    $r=json_decode($envelope['context'],true,32,JSON_THROW_ON_ERROR);if(!is_int($r['time'])||abs(time()-$r['time'])>30)throw new RuntimeException('Expired handoff.');
    $m=HybridIsolationHarness::manifest($argv[1]??'');$p=HybridIsolationHarness::connection($m);App\Services\HybridDatabaseScope::verify($p,$m);
    $_SESSION=$r['session'];$q=$p->prepare('SELECT id,name,username,role,is_active,password_hash FROM users WHERE id=?');$q->execute([$_SESSION['user_id']??0]);$user=$q->fetch();
    if(!$user||!(int)$user['is_active']||!hash_equals(hash('sha256',$user['password_hash']),$_SESSION['auth_stamp']??'')||App\Middleware\Session::expired($_SESSION,time()))throw new RuntimeException('Authenticated account is no longer valid.');unset($user['password_hash']);
    $route=App\Controllers\HybridReturnController::route($r['path'])??App\Controllers\InvoiceController::route($r['path']);
    if(!$route||!($route['api']??false)||!in_array('POST',$route['methods'],true)||!in_array($route['action'],['preview','index','hybrid-return-preview','hybrid-return-post'],true)||!App\Middleware\Authorization::allows($user,$route['role']))throw new RuntimeException('Handoff route denied.');
    $f=json_decode(file_get_contents($m['directory'].'/fixtures-stage6b-http.json'),true,512,JSON_THROW_ON_ERROR);$policy=json_decode(file_get_contents($m['directory'].'/stage6b-http-policy.json'),true,32,JSON_THROW_ON_ERROR);
    $cfg=hybridTestConfig($m,$f);$cfg['return_posting']=true;$cfg['custody']=true;$cfg['enabled']=!($policy['disabled']??false);$cfg['financial_posting_hold']=($policy['hold']??true);
    $fault=static function($step)use($policy){if(($policy['fault']??null)===$step)throw new RuntimeException('Injected isolated worker fault.');};
    $service=new App\Services\Invoices($p,new App\Services\HybridInvoiceCheckout(new App\Services\PdoHybridCheckoutStore($p,$m,$cfg['financial_posting_hold'],$fault),$cfg));
    $_SERVER['REQUEST_METHOD']='POST';$_SERVER['CONTENT_TYPE']='application/x-www-form-urlencoded';$_SERVER['REQUEST_URI']=$r['path'];$_POST=$r['payload'];$_POST['csrf_token']=$_POST['csrf_token']??$_SESSION['csrf_token'];$_GET=[];http_response_code(200);ob_start();
    if(str_starts_with($route['action'],'hybrid-return-'))(new App\Controllers\HybridReturnController(new App\Services\HybridReturns($p,$cfg,$m,$cfg['financial_posting_hold'],$fault)))->handle($route,$user);
    else(new App\Controllers\InvoiceController($service))->handle($route,$user);
    $body=ob_get_clean();echo json_encode(['status'=>http_response_code(),'body'=>$body],JSON_THROW_ON_ERROR);
}catch(Throwable$e){if(ob_get_level())ob_end_clean();echo json_encode(['status'=>409,'body'=>json_encode(['error'=>$e->getMessage()])]);}
