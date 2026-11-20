<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=App\Support\Database::connect();
$target=$pdo->query('SELECT DATABASE()')->fetchColumn();
$phase9Test=in_array('--phase9-test',$argv,true)&&$target==='dharma_sales_phase9_test'&&(int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn()===0;
if(getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||($target!=='dharma_sales_phase1'&&!$phase9Test)){throw new RuntimeException('Exact app-only target required.');}
if((int)$pdo->query("SELECT GET_LOCK('dharma_schema_migrations',10)")->fetchColumn()!==1){throw new RuntimeException('Migration lock unavailable.');}
try{
 $files=glob(APP_ROOT.'/database/migrations/*.sql');sort($files,SORT_STRING);
 $journal=$pdo->query('SELECT version,checksum,status FROM schema_migrations')->fetchAll(PDO::FETCH_UNIQUE);
 foreach($files as $file){$name=basename($file);if(isset($journal[$name])&&!hash_equals($journal[$name]['checksum'],hash_file('sha256',$file))){throw new RuntimeException('Migration checksum mismatch.');}}
 $name='007_customer_locations.sql';$fix='008_location_mapping_collation.sql';
 if(($journal[$name]['status']??'')==='applied'&&($journal[$fix]['status']??'')==='applied'){echo "Location mapping already reconciled; checksums verified.\n";return;}
 if(($journal[$name]['status']??'')!=='applying'||($journal[$name]['checksum']??'')!=='161effa594c58f45223bf68161ba3a6f3b623dc34bafff0a33080008a337fa9c'||isset($journal[$fix])){throw new RuntimeException('Not the known partial 007 state; manual investigation required.');}
 foreach(['001_foundation.sql','002_immutability.sql','003_authentication.sql','004_product_management.sql','005_product_cost_constraint.sql','006_customer_management.sql'] as $prior){if(($journal[$prior]['status']??'')!=='applied'){throw new RuntimeException('Earlier migration is incomplete.');}}
 $districts=$pdo->query('SELECT id,name FROM location_districts ORDER BY id')->fetchAll();
 $expected=['Ampara','Anuradhapura','Badulla','Batticaloa','Colombo','Galle','Gampaha','Hambantota','Jaffna','Kalutara','Kandy','Kegalle','Kilinochchi','Kurunegala','Mannar','Matale','Matara','Moneragala','Mullaitivu','Nuwara Eliya','Polonnaruwa','Puttalam','Ratnapura','Trincomalee','Vavuniya'];
 if(array_column($districts,'name')!==$expected){throw new RuntimeException('Unexpected district seed state.');}
 $seed=$pdo->query('SELECT COUNT(*) FROM location_towns WHERE source IS NOT NULL')->fetchColumn();if((int)$seed!==30){throw new RuntimeException('Seed statements did not complete as expected.');}
 $ddl=$pdo->query('SHOW CREATE TABLE customers')->fetch(PDO::FETCH_NUM)[1];
 if(strpos($ddl,'customers_town_requires_district')===false||!preg_match('/FOREIGN KEY \(`town_id`, `district_id`\) REFERENCES `location_towns` \(`id`, `district_id`\)/',$ddl)){throw new RuntimeException('Location DDL did not finish; do not rerun blindly.');}
 echo "Verified known partial migration: 25 districts, 30 source towns, customer columns/composite FK/check present; previous checksums intact.\n";
 if(!in_array('--complete-known-mapping',$argv,true)){echo "Inspection only. No rows/journal changed.\n";return;}
 // Only the documented DML mapping correction is executed, never original DDL.
 $pdo->beginTransaction();
 try{
  $pdo->prepare("INSERT INTO schema_migrations(version,checksum,status) VALUES (?,?,'applying')")->execute([$fix,hash_file('sha256',APP_ROOT.'/database/migrations/'.$fix)]);
  foreach(explode(';',file_get_contents(APP_ROOT.'/database/migrations/'.$fix)) as $sql){if(trim($sql)!==''){$pdo->exec($sql);}}
  $pdo->prepare("UPDATE schema_migrations SET status='applied',applied_at_utc=UTC_TIMESTAMP(6) WHERE version IN (?,?)")->execute([$name,$fix]);
  $pdo->prepare("INSERT INTO audit_log(action,entity_type,entity_key,reason) VALUES ('MIGRATION_RECONCILE','schema_migrations',?,'Verified partial 007 DDL and seeds; completed exact location mapping through forward migration 008 with explicit collation')")->execute([$name]);
  $pdo->commit();echo "Completed mapping and reconciled 007/008 atomically. Original 007 file preserved; no table/seeds recreated.\n";
 }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
}finally{$pdo->query("SELECT RELEASE_LOCK('dharma_schema_migrations')");}
