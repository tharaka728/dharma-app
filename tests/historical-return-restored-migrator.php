<?php
declare(strict_types=1);
// Only the explicitly verified isolated restore may be altered by this test.
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$schema='dharma_sales_phase12_restore_20261118154343';
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname='.$schema.';charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);unset($ini,$auth);
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$schema||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected target.');
\App\Support\Migrator::run($pdo);\App\Support\Migrator::run($pdo);
echo "Migration runner passed twice on isolated restore $schema.\n";
