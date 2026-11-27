<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-stage6b-installer.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');$admin=HybridIsolationHarness::connection($m,'admin');
Stage6bFreshInstaller::bindScope($m);
$checks=0;$evidence=[];
function mc(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function newInstall(string $collation='utf8mb4_unicode_ci'):PDO{global $admin,$m;
    $name='dharma_stage6b_'.bin2hex(random_bytes(10)).'_test';$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE '.$collation);
    $a=json_decode(file_get_contents($m['directory'].'/accounts.json'),true,512,JSON_THROW_ON_ERROR)['admin'];$p=HybridIsolationHarness::pdo($m,$a['user'],$a['password'],false);$p->exec('USE `'.$name.'`');return $p;
}
function rejected(callable $fn,string $label):void{try{$fn();}catch(Throwable $e){mc(true,$label);echo 'Rejected: '.$label.' - '.$e->getMessage().PHP_EOL;return;}throw new RuntimeException('Accepted: '.$label);}
foreach(['utf8mb4_general_ci','utf8mb4_unicode_ci']as$collation){
    // Reproduce the unassisted original chain without modifying it.
    $original=newInstall($collation);try{ob_start();App\Support\Migrator::run($original);ob_end_clean();throw new RuntimeException('Expected original 1267');}catch(PDOException $e){if(ob_get_level())ob_end_clean();mc((int)$e->errorInfo[1]===1267,'Unassisted original chain reproduces 1267');mc($original->query("SELECT status FROM schema_migrations WHERE version='007_customer_locations.sql'")->fetchColumn()==='applying','Failed 007 not applied');}
    $p=newInstall($collation);Stage6bFreshInstaller::run($p);$journal=$p->query('SELECT * FROM schema_migrations ORDER BY version')->fetchAll();
    mc(count($journal)===28,'27 original migrations + separate preparation');foreach($journal as$row)mc($row['status']==='applied'&&$row['applied_at_utc']!==null,'Journal completed');
    Stage6bFreshInstaller::run($p);ob_start();App\Support\Migrator::run($p);ob_end_clean();mc($journal===$p->query('SELECT * FROM schema_migrations ORDER BY version')->fetchAll(),'Original runner verifies unchanged checksums; rerun unchanged');
    $evidence[]=['collation_default'=>$collation,'database'=>$p->query('SELECT DATABASE()')->fetchColumn(),'result'=>'PASS: new install + explicit compatibility step + original 001-027; NOT unassisted pristine chain'];
}
$p=newInstall();Stage6bFreshInstaller::run($p,'006');Stage6bFreshInstaller::run($p);mc(true,'Resume after completed 006/preparation');
$p=newInstall();rejected(fn()=>Stage6bFreshInstaller::run($p,'027',static function($step){if($step==='compat-ddl')throw new RuntimeException('Injected process interruption after compatibility DDL');}),'compatibility DDL interruption');mc($p->query("SELECT status FROM schema_migrations WHERE version='006a_customer_locations_compat_v1.sql'")->fetchColumn()==='applying','Interrupted compatibility not applied');Stage6bFreshInstaller::run($p);mc(true,'Recover only exact verified compatibility post-state');
$p=newInstall();rejected(fn()=>Stage6bFreshInstaller::run($p,'027',static function($step){if($step==='007_customer_locations.sql:1')throw new RuntimeException('Injected original 007 interruption');}),'original 007 interruption');rejected(fn()=>Stage6bFreshInstaller::run($p),'partial original DDL blocked on rerun');mc($p->query("SELECT status FROM schema_migrations WHERE version='007_customer_locations.sql'")->fetchColumn()==='applying','Partial original 007 never marked applied');
$p=newInstall();$p->exec('CREATE TABLE unexpected (id INT)');rejected(fn()=>Stage6bFreshInstaller::run($p),'unverified existing schema');
$p=newInstall();Stage6bFreshInstaller::run($p,'006');$p->exec("INSERT INTO users(name,username,password_hash,role) VALUES ('Synthetic','synthetic','synthetic','ADMIN')");rejected(fn()=>Stage6bFreshInstaller::run($p),'populated pre-007 installation');
$p=newInstall();Stage6bFreshInstaller::run($p,'006');$p->exec("ALTER TABLE customers MODIFY city_town VARCHAR(101) NOT NULL DEFAULT ''");rejected(fn()=>Stage6bFreshInstaller::run($p),'unexpected column definition');
$p=newInstall();Stage6bFreshInstaller::run($p,'006');$p->exec("UPDATE schema_migrations SET checksum=REPEAT('0',64) WHERE version='006_customer_management.sql'");rejected(fn()=>Stage6bFreshInstaller::run($p),'journal checksum mismatch');
$p=newInstall();Stage6bFreshInstaller::run($p,'006');$p->exec(file_get_contents(APP_ROOT.'/database/fresh-install/006a_customer_locations_compat_v1.sql'));rejected(fn()=>Stage6bFreshInstaller::run($p),'unjournaled compatibility change');
// Install the separate runtime fixture schema from zero, never from a repaired dump.
$p=HybridIsolationHarness::connection($m,'migration');Stage6bFreshInstaller::run($p);$runtime=HybridIsolationHarness::connection($m);App\Services\HybridDatabaseScope::verify($runtime,$m);mc(true,'Runtime schema fresh chain physically verified');
file_put_contents($m['directory'].'/stage6b-migrations.json',json_encode(['checks'=>$checks,'chains'=>$evidence,'raw_007_sha256'=>hash_file('sha256',APP_ROOT.'/database/migrations/007_customer_locations.sql'),'unassisted_original_chain'=>'FAIL 1267, both defaults','proposal_only'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "Stage 6B migration: $checks checks passed. Compatibility-assisted clean installations through 027; unassisted chain remains FAIL 1267.\n";
