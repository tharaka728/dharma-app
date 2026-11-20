<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/offline-fixtures.php';
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='dharma_sales_phase9_test')throw new RuntimeException('Isolated browser test database required.');
$pdo=App\Support\Database::connect();$f=offlineFixtures($pdo);
$listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$listener)throw new RuntimeException('Cannot allocate loopback test port.');$address=stream_socket_get_name($listener,false);fclose($listener);$base='http://'.$address;
$pipes=[];$server=proc_open([PHP_BINARY,'-S',$address,'-t',APP_ROOT.'/public',APP_ROOT.'/tests/http-router.php'],[0=>['pipe','r'],1=>['file',APP_ROOT.'/storage/phase9-test-server.log','a'],2=>['file',APP_ROOT.'/storage/phase9-test-server.log','a']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);if(!is_resource($server))throw new RuntimeException('Cannot launch isolated test server.');fclose($pipes[0]);
try{
 $ready=false;for($i=0;$i<30;$i++){usleep(100000);$curl=curl_init($base.'/api/v1/health');curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>1]);$response=curl_exec($curl);curl_close($curl);if($response&&json_decode($response,true)['status']==='ready'){$ready=true;break;}}if(!$ready)throw new RuntimeException('Isolated server unavailable.');
 if(isset($phase9AuthOnly)){
  putenv('TEST_BASE_URL='.$base);$authPipes=[];$auth=proc_open([PHP_BINARY,APP_ROOT.'/tests/authentication.php','--existing-app'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$authPipes,APP_ROOT,null,['bypass_shell'=>true]);fclose($authPipes[0]);echo stream_get_contents($authPipes[1]);$error=stream_get_contents($authPipes[2]);fclose($authPipes[1]);fclose($authPipes[2]);if(proc_close($auth)!==0)throw new RuntimeException('Isolated authentication regression failed: '.$error);return;
 }
 $pipes=[];$node=proc_open(['C:/nvm4w/nodejs/node.exe',APP_ROOT.'/tests/offline-browser.js'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);if(!is_resource($node))throw new RuntimeException('Cannot launch browser test runner.');fwrite($pipes[0],json_encode(['base'=>$base,'username'=>$f['actors']['rep']['username'],'password'=>$f['password'],'rep_id'=>$f['actors']['rep']['id'],'customer_id'=>$f['customer'],'product_id'=>$f['products'][0]],JSON_THROW_ON_ERROR));fclose($pipes[0]);echo stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($node);if($status!==0){echo $stderr;throw new RuntimeException('Isolated browser verification failed.');}
 if((new App\Services\RepresentativeStock($pdo))->reconciliation($f['actors']['admin'])!==[])throw new RuntimeException('Browser posting stock reconciliation failed.');
 echo "Isolated browser-posted invoices and representative ledger reconcile. Synthetic records remain only in dharma_sales_phase9_test.\n";
}finally{if(is_resource($server)){proc_terminate($server);proc_close($server);}unset($f['password']);}
