<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/isolated-bootstrap.php';
$id=$argv[2]??'';$profile=null;$financialDispatched=false;
try{
    if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Invalid job identity.');
    $profile=App\Support\HybridIsolatedRuntime::profile($argv[1]??'');$dir=$profile['directory'].'/ipc';$file=$dir.'/'.$id.'.request.json';
    if(!is_file($file)||filesize($file)>524288)throw new RuntimeException('Invalid job.');$packet=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
    if(!is_string($packet['context']??null)||!hash_equals(hash_hmac('sha256',$packet['context'],$profile['key']),$packet['mac']??''))throw new RuntimeException('Invalid job authentication.');
    $r=json_decode($packet['context'],true,32,JSON_THROW_ON_ERROR);if(($r['job']??null)!==$id||!is_int($r['time']??null)||abs(time()-$r['time'])>30)throw new RuntimeException('Expired job.');
    $p=App\Support\HybridIsolatedRuntime::connect($profile['scope'],$profile['account']);App\Services\HybridDatabaseScope::verify($p,$profile['scope']);
    $p->exec('SET SESSION innodb_lock_wait_timeout=5');$p->exec('SET SESSION max_statement_time=10');
    $_SESSION=$r['session'];$q=$p->prepare('SELECT id,name,role,is_active,password_hash FROM users WHERE id=?');$q->execute([$_SESSION['user_id']??0]);$user=$q->fetch();
    if(!$user||!(int)$user['is_active']||App\Middleware\Session::expired($_SESSION,time())||!hash_equals(hash('sha256',$user['password_hash']),$_SESSION['auth_stamp']??''))throw new RuntimeException('Account session no longer valid.');unset($user['password_hash']);
    $route=App\Support\HybridWorkerClient::route($r['path'],$r['method'],$r['payload']);if(!App\Middleware\Authorization::allows($user,$route['role']))throw new RuntimeException('Role denied.');
    $cfg=$profile['config'];$fault=static function($step)use($cfg){if(($cfg['_test_fault']??null)===$step)throw new RuntimeException('Injected isolated transaction failure.');};
    $svc=new App\Services\Invoices($p,new App\Services\HybridInvoiceCheckout(new App\Services\PdoHybridCheckoutStore($p,$profile['scope'],$cfg['financial_posting_hold'],$fault),$cfg));
    $_SERVER['REQUEST_METHOD']=$r['method'];$_SERVER['REQUEST_URI']=$r['path'];$_SERVER['CONTENT_TYPE']='application/x-www-form-urlencoded';$_POST=$r['payload'];$_GET=$r['query'];$_COOKIE['dharma_language']=$r['locale'];App\Support\I18n::set($r['locale']);http_response_code(200);ob_start();
    $financialDispatched=$r['method']==='POST'&&in_array($route['action'],['index','hybrid-return-post','hybrid-return-receive','hybrid-return-supplement','hybrid-return-manage'],true);
    if(str_starts_with($route['action'],'hybrid-return-'))(new App\Controllers\HybridReturnController(new App\Services\HybridReturns($p,$cfg,$profile['scope'],$cfg['financial_posting_hold'],$fault)))->handle($route,$user);else(new App\Controllers\InvoiceController($svc))->handle($route,$user);
    $body=ob_get_clean();$result=['status'=>http_response_code(),'type'=>($route['api']??false)||http_response_code()>=400?'application/json':'text/html; charset=utf-8','body'=>$body];
    // Isolated acceptance fault: response loss AFTER commit must remain exactly-once.
    if(($cfg['_test_reply_delay_ms']??0)>0)usleep(min(20000,(int)$cfg['_test_reply_delay_ms'])*1000);
}catch(Throwable$e){if(ob_get_level())ob_end_clean();$result=['status'=>$financialDispatched?503:409,'type'=>'application/json','body'=>$financialDispatched?'{"errors":{"worker":"Outcome unknown. Retry the same request UUID; do not create a replacement command."}}':'{"errors":{"worker":"Request could not be completed. Review or retry the same request identity."}}'];}
if($profile){$dir=$profile['directory'].'/ipc';$json=json_encode($result,JSON_THROW_ON_ERROR);if(strlen($json)>2097152)exit(1);file_put_contents($dir.'/'.$id.'.tmp',$json,LOCK_EX);rename($dir.'/'.$id.'.tmp',$dir.'/'.$id.'.response.json');}
