<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');
if (is_file($m['directory'].'/accounts.json')) { throw new RuntimeException('Refusing to reprovision existing isolated accounts.'); }
$pdo=HybridIsolationHarness::pdo($m,'root','',false);
if ($pdo->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name NOT IN ('mysql','sys','performance_schema','information_schema','test')")->fetchColumn()!=0
    || $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='test'")->fetchColumn()!=0) { throw new RuntimeException('New empty isolated instance required.'); }
$accounts=['admin'=>['user'=>'root','password'=>bin2hex(random_bytes(24))]];
foreach (['runtime'=>'hybrid_rt_','migration'=>'hybrid_ddl_'] as $role=>$prefix) { $accounts[$role]=['user'=>$prefix.bin2hex(random_bytes(5)),'password'=>bin2hex(random_bytes(24))]; }
// No existing schema is dropped, restored, changed or granted to these accounts.
$pdo->exec('CREATE DATABASE `'.$m['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
foreach (['runtime'=>'SELECT, INSERT, UPDATE, DELETE','migration'=>'ALL PRIVILEGES'] as $role=>$privileges) {
    $account=$pdo->quote($accounts[$role]['user'])."@'127.0.0.1'";
    $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($accounts[$role]['password']));
    $pdo->exec('GRANT '.$privileges.' ON `'.$m['database'].'`.* TO '.$account);
}
$pdo->exec('CREATE TABLE `'.$m['database'].'`.hybrid_test_identity(run_id VARCHAR(50) PRIMARY KEY) ENGINE=InnoDB');
$pdo->prepare('INSERT INTO `'.$m['database'].'`.hybrid_test_identity VALUES (?)')->execute([$m['run_id']]);
HybridIsolationHarness::harden($pdo,$accounts['admin']['password']);
file_put_contents($m['directory'].'/accounts.json',json_encode($accounts,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$runtime=HybridIsolationHarness::connection($m);
$rejected=false; try { $runtime->exec('USE dharma_sales_phase1'); } catch (PDOException $e) { $rejected=in_array((int)$e->errorInfo[1],[1044,1049],true); }
if (!$rejected) { throw new RuntimeException('Operational schema access was not rejected.'); }
echo json_encode(['database'=>$m['database'],'host'=>'127.0.0.1','port'=>$m['port'],'datadir'=>realpath($m['datadir']),'runtime_user'=>$accounts['runtime']['user'],
    'runtime_privileges'=>array_map(static fn($s)=>explode(' TO ',$s,2)[0],$runtime->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN)),'operational_schema_denied'=>true,'operational_ports_excluded'=>[3306,3309]],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
