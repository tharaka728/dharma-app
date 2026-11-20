<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array(getenv('DB_NAME'),['dharma_sales_phase1','dharma_sales_phase9_test'],true)||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309')exit(2);
require dirname(__DIR__).'/app/bootstrap.php';$pdo=App\Support\Database::connect();$pdo->exec('SET SESSION innodb_lock_wait_timeout=1');
try{
 (new App\Services\Invoices($pdo))->issue(['id'=>$argv[1]??'0'],['request_id'=>$argv[2]??'','review_hash'=>str_repeat('0',64),'customer_id'=>'999999999999999999','stock_source'=>'warehouse','payment_type'=>'CASH','amount_received'=>'0.00','items'=>[['product_id'=>'999999999999999999','quantity'=>'1']]]);
 echo json_encode(['unexpected_success'=>true]);exit(1);
}catch(App\Support\InventoryException $e){echo json_encode(['status'=>$e->status,'lock_conflict'=>isset($e->errors['conflict'])]);}
catch(Throwable $e){echo json_encode(['unexpected_error'=>get_class($e)]);exit(1);}
