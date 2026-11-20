<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname=dharma_sales_phase1;charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($ini,$auth);
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1'||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected target');
$configuration=require APP_ROOT.'/config/historical-returns.php';$preview=$configuration['preview']??[];$id=(string)($preview['representative_id']??'');
$allowed=\App\Services\HistoricalReturnPreview::allowed($pdo,['id'=>$id],$configuration);
$other=\App\Services\HistoricalReturnPreview::allowed($pdo,['id'=>'0'],$configuration);
$counts=[];foreach(['historical_return_price_revisions','historical_customer_returns','historical_customer_return_lines','historical_return_intents','historical_return_identities'] as $table)$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
echo json_encode(['preview_enabled'=>$preview['enabled']??false,'selected_representative_allowed'=>$allowed,'unselected_identity_allowed'=>$other,'financial_posting_enabled'=>$configuration['enabled']??null,'return_record_counts'=>$counts],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
