<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/offline-fixtures.php';
use App\Services\Reports;use App\Support\{Database,InventoryException};use App\Controllers\ReportController;
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='dharma_sales_phase9_test')throw new RuntimeException('Dedicated isolated test database required.');
$pdo=Database::connect();$f=offlineFixtures($pdo);$service=new Reports($pdo);$count=0;
$check=static function($ok,$label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$reject=static function(callable $fn,int $status)use($check){try{$fn();throw new RuntimeException('Invalid filter accepted');}catch(InventoryException $e){$check($e->status===$status,'Expected filter rejection');}};
$hashes=static function()use($pdo){$out=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table)$out[$table]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$table.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));return $out;};$before=$hashes();
$admin=$f['actors']['admin'];$rep=$f['actors']['rep'];$other=$f['actors']['other'];
$filters=$service->filters($rep,[]);$check($filters['rep_id']===(string)$rep['id'],'Server forces own representative scope');
$reject(fn()=>$service->filters($rep,['rep_id'=>(string)$other['id']]),403);
$reject(fn()=>$service->filters($other,['customer_id'=>(string)$f['customer']]),403);
foreach(['rep_id','customer_id','product_id','district_id','town_id','category_id'] as $key){$reject(fn()=>$service->filters($admin,[$key=>'1 OR 1=1']),422);$reject(fn()=>$service->filters($admin,[$key=>['1']]),422);}
$reject(fn()=>$service->filters($admin,['payment_status'=>'x']),422);$reject(fn()=>$service->filters($admin,['q'=>str_repeat('a',101)]),422);$reject(fn()=>$service->filters($admin,['page'=>'0']),422);
$districts=$pdo->query('SELECT id FROM location_districts ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);$town=$pdo->query('SELECT id,district_id FROM location_towns ORDER BY id LIMIT 1')->fetch();
$different=(string)$districts[0]===(string)$town['district_id']?(string)$districts[1]:(string)$districts[0];
$reject(fn()=>$service->filters($admin,['district_id'=>$different,'town_id'=>(string)$town['id']]),422);
$filtered=$service->filters($admin,['town_id'=>(string)$town['id']]);$check($filtered['town_id']===(string)$town['id'],'Stable town ID without district');
$towns=$service->towns($rep,['district_id'=>(string)$town['district_id']]);$check(array_reduce($towns['towns'],fn($ok,$row)=>$ok&&$row['district_id']===(string)$town['district_id'],true),'Town options stay in district for representative');
$options=$service->options($rep,$filters);$check(count($options['locations']['districts'])===25,'Existing 25 districts reused');$check(count($options['customers'])===1&&(string)$options['customers'][0]['id']===(string)$f['customer'],'Options expose only assigned customers');$check(count($options['representatives'])===1,'Options expose only own representative');
foreach(['=1+1',' +SUM(A1)','-2','@SUM(A1)',"\t=SUM(A1)","\r=1"] as $value)$check(str_starts_with(Reports::csvCell($value),"'"),'CSV formula prefix protected');
$check(Reports::csvCell('සිංහල தமிழ்')==='සිංහල தமிழ்','UTF-8 CSV values preserved');
foreach(Reports::TYPES as $type=>$label){$route=ReportController::route('/admin/reports/'.$type.'/csv');$check($route['role']==='ADMIN'&&$route['methods']===['GET']&&$route['csv'],'Read-only export route '.$type);}
$check(ReportController::route('/rep/reports/towns')['role']==='SALES_REP','Authenticated representative lookup route');
$check(ReportController::route('/admin/reports/../../private')===null,'No arbitrary report path');
$check(Reports::day(new DateTimeImmutable('2026-11-08T18:29:59Z'))==='2026-11-08'&&Reports::day(new DateTimeImmutable('2026-11-08T18:30:00Z'))==='2026-11-09','Colombo midnight boundary');
$check($hashes()===$before,'Foundations and lookups preserve every table');
echo "$count reporting filter, authorization, lookup, CSV and no-write foundation checks passed.\n";
