<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||!in_array(getenv('DB_NAME'),['dharma_sales_phase1','dharma_sales_phase9_test'],true)||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309')exit(2);
require dirname(__DIR__).'/app/bootstrap.php';$pdo=App\Support\Database::connect();$actor=$pdo->query("SELECT id FROM users WHERE role='ADMIN' AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();if(!$actor)throw new RuntimeException('Existing admin required.');
$before=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t)$before[$t]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));
$workers=[];$count=0;$pdo->beginTransaction();
try{
 $pdo->query('SELECT id FROM inventory_settings WHERE id=1 FOR UPDATE');$request=App\Services\Inventory::uuid();$start=microtime(true);
 for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,APP_ROOT.'/tests/invoice-lock-worker.php',(string)$actor,$request],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);if(!is_resource($proc))throw new RuntimeException('Cannot start invoice lock worker.');fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 foreach($workers as [$proc,$pipes]){$body=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);$result=json_decode($body,true);if($code!==0||$stderr!==''||($result['status']??0)!==409||!($result['lock_conflict']??false))throw new RuntimeException('Invoice worker bypassed shared stock/credit posting lock.');$count++;}
 if(microtime(true)-$start<2.5)throw new RuntimeException('Workers did not wait/retry.');$count++;
}finally{if($pdo->inTransaction())$pdo->rollBack();foreach($workers as [$proc])if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}}
foreach($before as $t=>$hash){if(hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR))!==$hash)throw new RuntimeException('Concurrency test retained changes: '.$t);$count++;}
echo "$count multi-process invoice lock contention/retry and preservation checks passed. Successful competing committed sales are deliberately not created in the operational database.\n";
