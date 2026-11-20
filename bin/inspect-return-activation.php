<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$source=new PDO('mysql:host=127.0.0.1;port=3309;dbname=dharma_sales_phase1;charset=utf8mb4',$auth['user'],$auth['password'],$options);
$test=new PDO('mysql:host=127.0.0.1;port=3309;dbname=dharma_sales_phase12_restore_20261118154343;charset=utf8mb4',$auth['user'],$auth['password'],$options);unset($auth,$ini);
if($source->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1'||(int)$source->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected database target');
$files=['020_historical_return_prices.sql','021_historical_customer_returns.sql','022_historical_return_identity.sql','023_historical_return_intents.sql'];$results=[];
foreach($files as $file){$q=$test->prepare('SELECT checksum,status FROM schema_migrations WHERE version=?');$q->execute([$file]);$r=$q->fetch();$q=$source->prepare('SELECT status FROM schema_migrations WHERE version=?');$q->execute([$file]);$status=$q->fetchColumn();$results[]=['migration'=>$file,'matches_isolated_test'=>$r&&$r['status']==='applied'&&hash_equals($r['checksum'],hash_file('sha256',dirname(__DIR__).'/database/migrations/'.$file)),'operational_status'=>$status===false?'not applied':$status];}
$q=$source->query("SELECT id FROM users WHERE role='ADMIN' AND is_active=1 ORDER BY id LIMIT 1");$id=$q->fetchColumn();if($id===false)throw new RuntimeException('No active administrator exists.');
$report=(new \App\Services\Reports($source))->report(['id'=>(string)$id],'reconciliation',[]);
echo json_encode(['server_version'=>$source->query('SELECT VERSION()')->fetchColumn(),'migrations'=>$results,'reconciliation'=>$report['rows'],'guard_enabled'=>(require dirname(__DIR__).'/config/historical-returns.php')['enabled']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
