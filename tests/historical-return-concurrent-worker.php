<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$payload=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$schema='dharma_sales_phase12_restore_20261118154343';
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname='.$schema.';charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);unset($ini,$auth);$pdo->exec("SET time_zone='+00:00'");
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$schema||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected target');
while(microtime(true)<$payload['start'])usleep(1000);
if(($payload['mode']??'')==='count'){$q=$pdo->prepare('SELECT (SELECT COUNT(*) FROM invoices WHERE id=?)+(SELECT COUNT(*) FROM historical_customer_returns WHERE invoice_id=?)+(SELECT COUNT(*) FROM inventory_movements WHERE transaction_id=?)+(SELECT COUNT(*) FROM payments WHERE id=?)');$id=$payload['invoice_id'];$q->execute([$id,$id,$id,$id]);echo json_encode(['ok'=>true,'count'=>(int)$q->fetchColumn()],JSON_THROW_ON_ERROR);exit;}
try{$result=($payload['mode']??'issue')==='price'?(new \App\Services\HistoricalReturnPrices($pdo))->save(['id'=>$payload['actor']],$payload['input']):(new \App\Services\Invoices($pdo))->issue(['id'=>$payload['actor']],$payload['input']);echo json_encode(['ok'=>true,'result'=>$result],JSON_THROW_ON_ERROR);}
catch(\App\Support\InventoryException $e){echo json_encode(['ok'=>false,'status'=>$e->status],JSON_THROW_ON_ERROR);}
