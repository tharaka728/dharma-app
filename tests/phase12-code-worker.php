<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='dharma_sales_phase9_test')exit(1);
$in=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
try{$service=($in['kind']??'P')==='C'?new App\Services\Customers(App\Support\Database::connect()):new App\Services\Products(App\Support\Database::connect());$service->save($in['actor'],$in['input']);echo 'CREATED';}catch(App\Support\ProductException|App\Support\CustomerException $e){if($e->status!==422)throw $e;echo 'REJECTED';}
