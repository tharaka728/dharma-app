<?php
declare(strict_types=1);
// Read-only diagnostics for the explicitly named failed isolated restore.
if(PHP_SAPI!=='cli')exit(1);
$source='dharma_sales_phase1';$restore='dharma_sales_phase12_restore_20261119033438';
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($ini,$auth);
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Wrong server');
$sql="SELECT (SELECT COUNT(*) FROM `$source`.auth_login_limits) source_rows,(SELECT COUNT(*) FROM `$restore`.auth_login_limits) restored_rows,(SELECT COUNT(*) FROM `$source`.auth_login_limits a LEFT JOIN `$restore`.auth_login_limits b ON a.bucket_hash=b.bucket_hash WHERE b.bucket_hash IS NULL OR a.window_started_utc<>b.window_started_utc OR a.attempts<>b.attempts) source_differences,(SELECT COUNT(*) FROM `$restore`.auth_login_limits b LEFT JOIN `$source`.auth_login_limits a ON a.bucket_hash=b.bucket_hash WHERE a.bucket_hash IS NULL) restore_only";
echo json_encode($pdo->query($sql)->fetch(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
