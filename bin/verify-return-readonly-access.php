<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$db=new PDO('mysql:host=127.0.0.1;port=3309;dbname=dharma_sales_phase1;charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);unset($auth,$ini);
if($db->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1'||(int)$db->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected target');
$roles=[];foreach(['ADMIN','SALES_REP'] as $role){$q=$db->prepare('SELECT id FROM users WHERE role=? AND is_active=1 ORDER BY id LIMIT 1');$q->execute([$role]);$id=$q->fetchColumn();if($id===false)throw new RuntimeException('An active '.$role.' account is required.');$roles[$role]=['id'=>(string)$id];}
$service=new \App\Services\HistoricalReturnPrices($db);$lookups=[];
foreach(['2024','2025'] as $year){foreach($roles as $role=>$actor){$rows=$service->approved($actor,['year'=>$year]);$lookups[]=['year'=>$year,'role'=>$role,'accessible'=>true,'approved_prices'=>count($rows)];}}
$unauthorized=false;try{$service->listing($roles['SALES_REP'],[]);}catch(\App\Support\InventoryException $e){$unauthorized=$e->status===403;}
echo json_encode(['approved_price_lookups'=>$lookups,'representative_admin_price_listing_denied'=>$unauthorized,'guard_enabled'=>(require dirname(__DIR__).'/config/historical-returns.php')['enabled']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
