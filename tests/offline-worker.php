<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='dharma_sales_phase9_test')exit(2);
$input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$pdo=App\Support\Database::connect();
try{$r=(new App\Services\OfflineSales($pdo))->submit(['id'=>$input['actor']],$input['envelope']);echo json_encode(['status'=>200,'receipt'=>$r],JSON_THROW_ON_ERROR);}
catch(App\Support\InventoryException $e){echo json_encode(['status'=>$e->status,'errors'=>$e->errors],JSON_THROW_ON_ERROR);}
