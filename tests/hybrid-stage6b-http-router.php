<?php
declare(strict_types=1);
// Separate financial architecture prototype. Never used by the owner's preview.
if(PHP_SAPI!=='cli-server'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(403);exit;}
if(is_file(dirname(__DIR__).'/.env')){http_response_code(503);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: application/json');header('Cache-Control: private, no-store');
try{
    $manifest=realpath(getenv('HYBRID6B_MANIFEST')?:'');$storage=realpath(APP_ROOT.'/storage');$key=getenv('HYBRID6B_KEY');
    if(!$manifest||!$storage||!preg_match('~^'.preg_quote(str_replace('\\','/',$storage),'~').'/stage2c-[a-f0-9]{32}/manifest.json$~Di',str_replace('\\','/',$manifest))||!is_string($key)||strlen($key)!==64)throw new RuntimeException('Missing isolated server context.');
    $m=json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
    if(!is_int($m['port'])||$m['port']<1024||in_array($m['port'],[3306,3309],true)||!preg_match('/^dharma_sales_hybrid_[a-f0-9]{32}_test$/D',$m['database'])||realpath($m['directory'])!==dirname($manifest)||realpath($m['datadir'])!==realpath(dirname($manifest).'/data'))throw new RuntimeException('Invalid isolated endpoint.');
    $a=json_decode(file_get_contents($m['directory'].'/accounts.json'),true,512,JSON_THROW_ON_ERROR)['runtime'];
    $p=new PDO('mysql:host=127.0.0.1;port='.$m['port'].';dbname='.$m['database'].';charset=utf8mb4',$a['user'],$a['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$p->exec("SET time_zone='+00:00'");
    $r=$p->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();$norm=static fn($x)=>strtolower(rtrim(str_replace('\\','/',$x),'/'));
    if($r['db']!==$m['database']||(int)$r['port']!==$m['port']||$norm($r['datadir'])!==$norm(realpath($m['datadir']))||$p->query('SELECT run_id FROM hybrid_test_identity')->fetchColumn()!==$m['run_id'])throw new RuntimeException('Physical identity mismatch.');
    $grants=$p->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);if(count($grants)!==2)throw new RuntimeException('Unexpected grants.');foreach($grants as$g)if(!str_starts_with($g,'GRANT USAGE ON *.* TO ')&&!str_starts_with($g,'GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$m['database'].'`.* TO '))throw new RuntimeException('Excessive grants.');
    App\Middleware\Session::start(false);$auth=new App\Services\Authentication($p);$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$method=$_SERVER['REQUEST_METHOD'];
    if($method==='GET'&&$path==='/session'){echo json_encode(['csrf'=>$_SESSION['csrf_token'],'user'=>$auth->currentUser()]);exit;}
    $payload=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($payload)||$method!=='POST'){http_response_code(403);echo '{}';exit;}
    if(!App\Middleware\Csrf::valid($payload['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null),$_SESSION['csrf_token']??null)){http_response_code(403);echo '{"error":"csrf"}';exit;}
    if($path==='/login'){$result=$auth->login($payload['username']??'',$payload['password']??'','127.0.0.1');http_response_code(in_array($result,['ADMIN','SALES_REP'],true)?200:403);echo json_encode(['result'=>$result,'csrf'=>$_SESSION['csrf_token']]);exit;}
    $user=$auth->currentUser();if(!$user){http_response_code(401);echo '{}';exit;}
    $route=App\Controllers\HybridReturnController::route($path)??App\Controllers\InvoiceController::route($path);
    if(!$route||!($route['api']??false)||!in_array($method,$route['methods'],true)||!in_array($route['action'],['preview','index','hybrid-return-preview','hybrid-return-post'],true)||!App\Middleware\Authorization::allows($user,$route['role'])){http_response_code(403);echo '{}';exit;}
    if(in_array($route['action'],['preview','index'],true)&&($payload['hybrid']['policy_version']??null)!=='HYBRID_PROMOTION_V1'){http_response_code(409);echo '{}';exit;}
    $context=json_encode(['time'=>time(),'path'=>$path,'payload'=>$payload,'session'=>$_SESSION],JSON_THROW_ON_ERROR);session_write_close();
    $pipes=[];$worker=proc_open([PHP_BINARY,__DIR__.'/hybrid-stage6b-http-worker.php',$manifest],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$m['directory'].'/stage6b-worker-errors.log','a']],$pipes,APP_ROOT,null,['bypass_shell'=>true,'create_new_console'=>false]);
    fwrite($pipes[0],json_encode(['context'=>$context,'mac'=>hash_hmac('sha256',$context,$key)],JSON_THROW_ON_ERROR));fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$exit=proc_close($worker);$result=json_decode($out,true,32,JSON_THROW_ON_ERROR);
    http_response_code($result['status']);echo $result['body'];
}catch(Throwable$e){http_response_code(503);echo '{"error":"Isolated HTTP context unavailable"}';error_log($e->getMessage());}
