<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$root=dirname(__DIR__);$backup='C:/mariadb-clean/ayurveda-phase1-local/private/phase12-20261119084755/application.sql';
$expected='648134bf0c392bc5e54af07befcfea18b337ed77dc1536edefad20a37a028dd9';
if(!is_file($backup)||!hash_equals($expected,hash_file('sha256',$backup)))throw new RuntimeException('Verified backup checksum mismatch');
$receipt=json_decode(file_get_contents(dirname($backup).'/verification.json'),true,32,JSON_THROW_ON_ERROR);
if(!($receipt['data_and_schema_match']??false)||($receipt['source']??'')!=='dharma_sales_phase1'||($receipt['sha256']??'')!==$expected)throw new RuntimeException('Backup verification receipt mismatch');
if((require $root.'/config/historical-returns.php')['enabled']!==false)throw new RuntimeException('Guard must remain enabled');
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname=dharma_sales_phase1;charset=utf8mb4',$auth['user'],$auth['password'],$options);
$test=new PDO('mysql:host=127.0.0.1;port=3309;dbname=dharma_sales_phase12_restore_20261118154343;charset=utf8mb4',$auth['user'],$auth['password'],$options);unset($ini,$auth);
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1'||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected database target');
$expectedFiles=['020_historical_return_prices.sql','021_historical_customer_returns.sql','022_historical_return_identity.sql','023_historical_return_intents.sql'];
$files=glob($root.'/database/migrations/*.sql');sort($files,SORT_STRING);$pending=[];
foreach($files as $file){$name=basename($file);$q=$pdo->prepare('SELECT checksum,status FROM schema_migrations WHERE version=?');$q->execute([$name]);$row=$q->fetch();if($row){if($row['status']!=='applied'||!hash_equals($row['checksum'],hash_file('sha256',$file)))throw new RuntimeException('Existing migration mismatch: '.$name);}else $pending[]=$name;}
if($pending!==$expectedFiles)throw new RuntimeException('Unexpected pending migration set');
foreach($expectedFiles as $name){$q=$test->prepare('SELECT checksum,status FROM schema_migrations WHERE version=?');$q->execute([$name]);$row=$q->fetch();if(!$row||$row['status']!=='applied'||!hash_equals($row['checksum'],hash_file('sha256',$root.'/database/migrations/'.$name)))throw new RuntimeException('Isolated migration checksum mismatch: '.$name);}
$gate=$root.'/storage/historical-backup-maintenance.lock';$handle=@fopen($gate,'x');if(!$handle)throw new RuntimeException('Maintenance gate unavailable or already active');fwrite($handle,"Historical return migration maintenance\n");fclose($handle);
$release=static function()use($gate){if(is_file($gate)&&!unlink($gate))fwrite(STDERR,"Maintenance gate could not be removed.\n");};register_shutdown_function($release);
try{\App\Support\Migrator::run($pdo);}finally{$release();}
