<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');
$accounts=json_decode(file_get_contents($m['directory'].'/accounts.json'),true,512,JSON_THROW_ON_ERROR);
$pdo=HybridIsolationHarness::pdo($m,$accounts['runtime']['user'],$accounts['runtime']['password']);
$grants=array_map(static fn($s)=>explode(' TO ',$s,2)[0],$pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
if (in_array('--inspect-bootstrap-grants',$argv,true)) {
    $admin=in_array('--finish-initial-bootstrap',$argv,true) ? HybridIsolationHarness::pdo($m,'root','') : HybridIsolationHarness::connection($m,'admin');
    echo json_encode($admin->query('SELECT Host,Db,User FROM mysql.db')->fetchAll(),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}
echo json_encode(['database'=>$m['database'],'port'=>$m['port'],'datadir'=>realpath($m['datadir']),'runtime_user'=>$accounts['runtime']['user'],'privileges'=>$grants],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
