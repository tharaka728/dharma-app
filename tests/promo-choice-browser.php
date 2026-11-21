<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';require_once __DIR__.'/offline-fixtures.php';
use App\Support\Database;
if(getenv('DHARMA_BENEFIT_TEST')!=='1')throw new RuntimeException('Gate-on browser verification requires DHARMA_BENEFIT_TEST=1.');
$pdo=Database::connect();if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase9_test')throw new RuntimeException('Disposable database required');
$f=offlineFixtures($pdo);
$f['checkout_skus']=[];foreach($f['products'] as $pid){$st=$pdo->prepare('SELECT sku FROM products WHERE id=?');$st->execute([$pid]);$f['checkout_skus'][]=$st->fetchColumn();}
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);$f['base']='http://'.$address;
$server=proc_open([PHP_BINARY,'-S',$address,'-t',APP_ROOT.'/public',APP_ROOT.'/tests/http-router.php'],[0=>['pipe','r'],1=>['file',APP_ROOT.'/storage/promo-choice-browser-server.log','a'],2=>['file',APP_ROOT.'/storage/promo-choice-browser-server.log','a']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);fclose($pipes[0]);
try{usleep(400000);$node=proc_open(['node',__DIR__.'/promo-choice-browser.js'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);fwrite($pipes[0],json_encode($f,JSON_THROW_ON_ERROR));fclose($pipes[0]);echo stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($node)!==0)throw new RuntimeException('Benefit browser: '.$err);}finally{proc_terminate($server);proc_close($server);}
