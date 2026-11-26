<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-fixtures.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');$pdo=HybridIsolationHarness::connection($m);App\Services\HybridDatabaseScope::verify($pdo,$m);
$f=hybridFixtures($pdo,$m);file_put_contents($m['directory'].'/fixtures-stage3.json',json_encode($f,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$run=$m['directory'].'/stage3-ui-'.bin2hex(random_bytes(6));mkdir($run);mkdir($run.'/sessions');
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);
$env=getenv();$env['HYBRID_UI_MANIFEST']=$m['directory'].'/manifest.json';$env['HYBRID_UI_TEST_KEY']=bin2hex(random_bytes(24));
$pipes=[];$server=proc_open([PHP_BINARY,'-d','zend.exception_ignore_args=1','-d','session.save_path='.$run.'/sessions','-S',$address,'-t',APP_ROOT.'/public',__DIR__.'/hybrid-ui-router.php'],
    [0=>['pipe','r'],1=>['file',$run.'/http-stdout.log','a'],2=>['file',$run.'/http-stderr.log','a']],$pipes,APP_ROOT,$env,['bypass_shell'=>true,'create_new_console'=>false]);fclose($pipes[0]);
try{
    usleep(300000);$nodePipes=[];$node=proc_open(['node',__DIR__.'/hybrid-ui-browser.js'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$nodePipes,APP_ROOT,null,['bypass_shell'=>true,'create_new_console'=>false]);
    fwrite($nodePipes[0],json_encode(['base'=>'http://'.$address,'key'=>$env['HYBRID_UI_TEST_KEY'],'fixtures'=>$f,'directory'=>$run],JSON_THROW_ON_ERROR));fclose($nodePipes[0]);
    $out=stream_get_contents($nodePipes[1]);echo $out;$err=stream_get_contents($nodePipes[2]);fclose($nodePipes[1]);fclose($nodePipes[2]);$exit=proc_close($node);if($exit!==0||strpos($out,'Stage 3 real Edge:')===false)throw new RuntimeException($err?:'Browser suite did not complete.');
    $q=$pdo->prepare('SELECT COUNT(*) FROM invoices WHERE customer_id IN (?,?)');$q->execute([$f['customers']['rep1'],$f['customers']['rep2']]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Browser flow did not issue exactly once.');
    $q=$pdo->prepare('SELECT COUNT(*) FROM hybrid_invoice_seals h JOIN invoices i ON i.id=h.invoice_id WHERE i.customer_id IN (?,?)');$q->execute([$f['customers']['rep1'],$f['customers']['rep2']]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Browser invoice seal count mismatch.');
    $q=$pdo->prepare('SELECT quantity FROM representative_stock_balances WHERE rep_id=? AND product_id=?');$q->execute([$f['users']['rep1'],$f['products']['ordinary']]);if((int)$q->fetchColumn()!==499)throw new RuntimeException('Browser stock posting duplicated.');
    echo 'Stage 3 database backstop: 3 exactly-once checks passed.'.PHP_EOL;
}finally{proc_terminate($server);proc_close($server);}
