<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||!in_array(getenv('DB_NAME'),['dharma_sales_phase1','dharma_sales_phase9_test'],true)||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'){exit(2);}
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=App\Support\Database::connect();$actor=$pdo->query("SELECT id FROM users WHERE role='ADMIN' AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();if(!$actor){throw new RuntimeException('An existing real admin is required for the lock test.');}
$before=[];foreach(['inventory_balances','inventory_transactions','inventory_movements','audit_log'] as $table){$before[$table]=hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));}
$processes=[];$count=0;$pdo->beginTransaction();
try{
    $pdo->query('SELECT id FROM inventory_settings WHERE id=1 FOR UPDATE')->fetchColumn();
    $uuid=App\Services\Inventory::uuid();$start=microtime(true);
    for($i=0;$i<2;$i++){
        $pipes=[];$proc=proc_open([PHP_BINARY,APP_ROOT.'/tests/inventory-lock-worker.php',(string)$actor,$uuid],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);
        if(!is_resource($proc)){throw new RuntimeException('Cannot start inventory contention worker.');}fclose($pipes[0]);$processes[]=[$proc,$pipes];
    }
    foreach($processes as [$proc,$pipes]){$body=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);$data=json_decode($body,true);if($code!==0||$stderr!==''||($data['status']??0)!==409||!($data['lock_conflict']??false)){throw new RuntimeException('Concurrent stock probe did not respect the posting lock.');}$count++;}
    if(microtime(true)-$start<2.5){throw new RuntimeException('Posting workers did not wait/retry on the database lock.');}$count++;
}finally{if($pdo->inTransaction())$pdo->rollBack();foreach($processes as [$proc]){if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}}}
foreach($before as $table=>$hash){if(hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR))!==$hash){throw new RuntimeException('Unexpected retained stock data.');}$count++;}
echo "$count multi-process inventory lock contention/retry and no-write checks passed; no stock fixtures committed.\n";
