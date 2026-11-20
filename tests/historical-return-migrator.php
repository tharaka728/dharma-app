<?php
declare(strict_types=1);
// Fresh synthetic schema only; never reset or reuse an existing database.
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);unset($ini,$auth);
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected server.');
$name='dharma_return_migration_test_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3));
$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');$q->execute([$name]);if($q->fetchColumn())throw new RuntimeException('Schema exists.');
// Older migrations create some utf8mb4 tables without an explicit collation.
// Match MariaDB's utf8mb4 default used by those migrations for a fresh install.
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");$pdo->exec("USE `$name`");
\App\Support\Migrator::run($pdo);\App\Support\Migrator::run($pdo);
echo "Fresh synthetic migration schema: $name. No existing database was modified.\n";
