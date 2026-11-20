<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);require dirname(__DIR__).'/app/bootstrap.php';$pdo=App\Support\Database::connect();
if(getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||$pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1')throw new RuntimeException('Exact live application target required.');
$file=APP_ROOT.'/storage/phase9-live-preservation.json';$capture=in_array('--capture',$argv,true);$skip=['schema_migrations','offline_device_controls','offline_sale_payloads','offline_sale_reviews'];$rows=[];
foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table)if(!in_array($table,$skip,true)){$data=$pdo->query('SELECT * FROM `'.$table.'` ORDER BY 1')->fetchAll();$rows[$table]=['count'=>count($data),'sha256'=>hash('sha256',json_encode($data,JSON_THROW_ON_ERROR))];}
if($capture){if(file_exists($file))throw new RuntimeException('Existing preservation capture will not be overwritten.');file_put_contents($file,json_encode($rows,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo count($rows)." live table hashes captured without exposing contents.\n";}
else{$before=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);foreach($before as $table=>$expected)if(($rows[$table]??null)!==$expected)throw new RuntimeException('Live table changed during deployment: '.$table);echo count($before)." existing live table counts/content hashes preserved across Phase 9 migration.\n";}
