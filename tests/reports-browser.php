<?php
declare(strict_types=1);
require __DIR__.'/reports.php';
$listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($listener,false);fclose($listener);$base='http://'.$address;$pipes=[];
$server=proc_open([PHP_BINARY,'-S',$address,'-t',APP_ROOT.'/public',APP_ROOT.'/tests/http-router.php'],[0=>['pipe','r'],1=>['file',APP_ROOT.'/storage/phase11-test-server.log','a'],2=>['file',APP_ROOT.'/storage/phase11-test-server.log','a']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);fclose($pipes[0]);
try{
 usleep(500000);$pipes=[];$node=proc_open(['C:/nvm4w/nodejs/node.exe',APP_ROOT.'/tests/reports-browser.js'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);
 fwrite($pipes[0],json_encode(['base'=>$base,'actors'=>$f['actors'],'password'=>$f['password'],'rep_id'=>(string)$rep['id'],'other_id'=>(string)$other['id'],'customer_id'=>(string)$f['customer'],'product_id'=>(string)$f['products'][0]],JSON_THROW_ON_ERROR));fclose($pipes[0]);echo stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($node)!==0)throw new RuntimeException('Report browser verification failed: '.$error);
 $after=$hashes();foreach(['invoices','invoice_items','invoice_receipts','invoice_voids','payments','payment_allocations','inventory_transactions','inventory_movements','product_stock_balances','representative_stock_balances'] as $table)if($before[$table]!==$after[$table])throw new RuntimeException('HTTP report changed '.$table);
 echo "All financial and stock hashes preserved by HTTP reports and exports.\n";
}finally{proc_terminate($server);proc_close($server);}
