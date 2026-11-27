<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-stage6b-installer.php';
use App\Services\{Invoices,Inventory,ProductStock,RepresentativeStock,HistoricalReturnPrices,Reports,InvoiceDocuments};
$m=HybridIsolationHarness::manifest($argv[1]??'');
Stage6bFreshInstaller::bindScope($m);
// Manifest rejects forbidden ports BEFORE connecting; physical proof follows.
$admin=HybridIsolationHarness::connection($m,'admin');
$schema='dharma_sales_phase12_restore_20261118154343'; // Existing policy's test-only name, on this NEW physical server.
$exists=$admin->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');$exists->execute([$schema]);$existing=(bool)$exists->fetchColumn();
if(!$existing)$admin->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$admin->exec('USE `'.$schema.'`');
if($existing&&($admin->query('SELECT run_id FROM hybrid_test_identity')->fetchColumn()!==$m['run_id']||(int)$admin->query('SELECT (SELECT COUNT(*) FROM users)+(SELECT COUNT(*) FROM invoices)+(SELECT COUNT(*) FROM products)+(SELECT COUNT(*) FROM audit_log)')->fetchColumn()!==0))throw new RuntimeException('Existing historical fixture is not this run\'s rolled-back empty schema.');
Stage6bFreshInstaller::run($admin);
$a=['user'=>'hist_rt_'.bin2hex(random_bytes(5)),'password'=>bin2hex(random_bytes(24))];$account=$admin->quote($a['user'])."@'127.0.0.1'";$admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($a['password']));$admin->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.$schema.'`.* TO '.$account);
if(!$existing){$admin->exec('CREATE TABLE hybrid_test_identity(run_id VARCHAR(50) PRIMARY KEY) ENGINE=InnoDB');$admin->prepare('INSERT INTO hybrid_test_identity VALUES (?)')->execute([$m['run_id']]);}
$pdo=HybridIsolationHarness::pdo($m,$a['user'],$a['password'],false);$pdo->exec('USE `'.$schema.'`');
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$schema||$pdo->query('SELECT run_id FROM hybrid_test_identity')->fetchColumn()!==$m['run_id'])throw new RuntimeException('Historical fixture identity mismatch.');
$grants=$pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);if(count($grants)!==2)throw new RuntimeException('Unexpected historical grants.');foreach($grants as$g)if(!str_starts_with($g,'GRANT USAGE ON *.* TO ')&&!str_starts_with($g,'GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$schema.'`.* TO '))throw new RuntimeException('Excessive historical grants.');
$digest=static function()use($pdo):string{$rows=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)as$t){$r=$pdo->query('SELECT * FROM `'.$t.'`')->fetchAll();usort($r,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$t]=$r;}return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));};$before=$digest();
$source=file_get_contents(__DIR__.'/historical-return-posting.php');
if(!hash_equals('2a01c2454c94a0091c75e53d25f55de315f733fba7fa15419d96eede3cde5c89',hash('sha256',$source)))throw new RuntimeException('Legacy suite changed: review adapter boundary again.');
$offset=strpos($source,'$count=0;');if($offset===false)throw new RuntimeException('Legacy suite boundary missing.');$body=substr($source,$offset);
if(str_contains($body,'new PDO')||str_contains($body,'parse_ini_file')||str_contains($body,'Database::connect'))throw new RuntimeException('Unexpected legacy connection in test body.');
// Preserve all business assertions. Main's approved receipt calls the same snapshot
// "Return credit"; the older test's label is stale. Record this adaptation explicitly.
$adapted=str_replace("str_contains($"."html,'Noncash return adjustment')","str_contains($"."html,'Return credit')",$body,$labelCount);
if($labelCount!==1)throw new RuntimeException('Unexpected legacy document assertion shape.');
eval('use App\\Services\\{Invoices,Inventory,ProductStock,RepresentativeStock,HistoricalReturnPrices,Reports,InvoiceDocuments};'.$adapted);
if($pdo->inTransaction()||$before!==$digest())throw new RuntimeException('Historical suite failed complete business-row rollback.');
file_put_contents($m['directory'].'/stage6b-historical.json',json_encode(['checks'=>$count,'port'=>$m['port'],'schema'=>$schema,'identity'=>$m['run_id'],'restricted_grants'=>true,'legacy_body_sha256'=>hash('sha256',$body),'adaptation'=>'Document text only: Noncash return adjustment -> Return credit; strict original label assertion reproduced FAIL','all_table_data_rollback'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "Historical adapter: exact physical isolation and schema-only grants verified; complete all-table rollback verified. Original suite unchanged.\n";
