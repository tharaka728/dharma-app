<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';require_once __DIR__.'/offline-fixtures.php';
use App\Services\{Inventory,Promotions};use App\Support\Database;
$pdo=Database::connect();if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase9_test')throw new RuntimeException('Disposable sales database required');
$f=offlineFixtures($pdo);$svc=new Promotions($pdo);$old=$svc->get($f['actors']['admin'],$f['promotion']);$svc->save($f['actors']['admin'],['request_id'=>Inventory::uuid(),'version'=>$old['version'],'is_active'=>'0'],$old['id'],true);
$save=$svc->save($f['actors']['admin'],['request_id'=>Inventory::uuid(),'name'=>'Browser mixed oil','description'=>'Disposable browser fixture','mixed_oil'=>'1','mixed_product_ids'=>$f['products'],'type'=>'BUY_X_GET_Y','buy_quantity'=>'6','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
$f['promotion']=$save['promotion_id'];$f['products_detail']=$pdo->query('SELECT id,sku FROM products WHERE id IN ('.implode(',',$f['products']).') ORDER BY id')->fetchAll();
$listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($listener,false);fclose($listener);$f['base']='http://'.$address;
$server=proc_open([PHP_BINARY,'-S',$address,'-t',APP_ROOT.'/public',APP_ROOT.'/tests/http-router.php'],[0=>['pipe','r'],1=>['file',APP_ROOT.'/storage/mixed-oil-test-server.log','a'],2=>['file',APP_ROOT.'/storage/mixed-oil-test-server.log','a']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);fclose($pipes[0]);
try{usleep(400000);$node=proc_open(['node',__DIR__.'/mixed-oil-browser.js'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);fwrite($pipes[0],json_encode($f,JSON_THROW_ON_ERROR));fclose($pipes[0]);echo stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($node)!==0)throw new RuntimeException('Mixed oil browser: '.$error);}finally{proc_terminate($server);proc_close($server);}
