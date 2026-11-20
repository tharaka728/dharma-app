<?php
declare(strict_types=1);
// Explicit CLI maintenance only. Never HTTP; never restore over an existing schema.
if(PHP_SAPI!=='cli')exit(1);
$source='dharma_sales_phase1';$stamp=gmdate('YmdHis');$target='dharma_sales_phase12_restore_'.$stamp;
$optionFile='C:/mariadb-clean/private/admin.ini';$ini=parse_ini_file($optionFile,true,INI_SCANNER_RAW);$credentials=$ini['client']??$ini;
$settings=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO('mysql:host=127.0.0.1;port=3309;charset=utf8mb4',$credentials['user'],$credentials['password'],$settings);$admin->exec("SET time_zone='+00:00'");if((int)$admin->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Server mismatch');
$exists=$admin->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$exists->execute([$target]);if($exists->fetch())throw new RuntimeException('Restore target exists; preserved.');
$private='C:/mariadb-clean/ayurveda-phase1-local/private/phase12-'.$stamp;if(is_dir($private)||!mkdir($private,0700))throw new RuntimeException('Cannot create new private backup directory.');
$backup=$private.'/application.sql';$log=$private.'/restore-tool.log';
$run=static function(array $args,$input=null)use($log){$pipes=[];$descriptors=[0=>$input??['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']];$p=proc_open($args,$descriptors,$pipes,null,null,['bypass_shell'=>true,'create_no_window'=>true]);if(!is_resource($p))throw new RuntimeException('Maintenance tool unavailable');if(isset($pipes[0]))fclose($pipes[0]);if(proc_close($p)!==0)throw new RuntimeException('Maintenance tool failed; see private maintenance log.');};
$run(['C:/xampp/mysql/bin/mysqldump.exe','--defaults-extra-file='.$optionFile,'--protocol=tcp','--host=127.0.0.1','--port=3309','--single-transaction','--routines','--triggers','--hex-blob','--default-character-set=utf8mb4','--skip-add-drop-table','--result-file='.$backup,$source]);
if(!is_file($backup)||filesize($backup)<1000)throw new RuntimeException('Backup is incomplete');$hash=hash_file('sha256',$backup);
// Restore-only portability adjustment: source definers have privileges only on
// their original schema. Do not broaden those accounts or alter the backup.
$import=$private.'/isolated-import.sql';$dump=file_get_contents($backup);$rebound=0;
$dump=preg_replace('/^(\/\*![0-9]{5} CREATE\*\/\s+\/\*![0-9]{5}\s+)DEFINER=`[^`]+`@`[^`]+`/m','$1DEFINER=CURRENT_USER',$dump,-1,$rebound);
if($rebound===0)throw new RuntimeException('Expected trigger definer declarations not found');file_put_contents($import,$dump,LOCK_EX);unset($dump);
$admin->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$input=fopen($import,'rb');try{$run(['C:/xampp/mysql/bin/mysql.exe','--defaults-extra-file='.$optionFile,'--protocol=tcp','--host=127.0.0.1','--port=3309','--default-character-set=utf8mb4','--binary-mode',$target],$input);}finally{fclose($input);}
$db=[];foreach([$source,$target] as $schema){$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname='.$schema.';charset=utf8mb4',$credentials['user'],$credentials['password'],$settings);$pdo->exec("SET time_zone='+00:00'");$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');$db[$schema]=$pdo;}
$tableCount=0;$rowsTotal=0;
foreach($db[$source]->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
 $keys=$db[$source]->query("SHOW KEYS FROM `$table` WHERE Key_name='PRIMARY'")->fetchAll();usort($keys,fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);$order=$keys?' ORDER BY '.implode(',',array_map(fn($r)=>'`'.$r['Column_name'].'`',$keys)):'';$data=[];
 foreach([$source,$target] as $schema){$q=$db[$schema]->query('SELECT * FROM `'.$table.'`'.$order);$ctx=hash_init('sha256');$n=0;while($row=$q->fetch()){hash_update($ctx,json_encode($row,JSON_THROW_ON_ERROR)."\n");$n++;}$data[$schema]=[$n,hash_final($ctx)];}
 if($data[$source]!==$data[$target])throw new RuntimeException('Restored data mismatch: '.$table);$rowsTotal+=$data[$source][0];$tableCount++;
 $creates=[];foreach([$source,$target] as $schema)$creates[$schema]=$db[$schema]->query('SHOW CREATE TABLE `'.$table.'`')->fetch()['Create Table'];if($creates[$source]!==$creates[$target])throw new RuntimeException('Restored table constraints mismatch: '.$table);
}
$triggers=[];foreach([$source,$target] as $schema){$q=$admin->prepare('SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_STATEMENT,ACTION_TIMING FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=? ORDER BY TRIGGER_NAME');$q->execute([$schema]);$triggers[$schema]=$q->fetchAll();}
if($triggers[$source]!==$triggers[$target])throw new RuntimeException('Trigger definitions mismatch');
$migrations=$db[$target]->query('SELECT version,checksum,status FROM schema_migrations ORDER BY version')->fetchAll();foreach($migrations as $row)if($row['status']!=='applied'||!hash_equals($row['checksum'],hash_file('sha256',dirname(__DIR__).'/database/migrations/'.$row['version'])))throw new RuntimeException('Migration checksum mismatch');
foreach($db as $pdo)$pdo->commit();$restore=$db[$target];$constraintChecks=0;$restore->beginTransaction();
try{
 $tests=[['UPDATE invoices SET grand_total=0 LIMIT 1',1644],['DELETE FROM invoice_items LIMIT 1',1644],['UPDATE product_stock_balances SET quantity=-1 LIMIT 1',4025],['INSERT INTO products(sku,name,category_id,selling_price) SELECT sku,name,category_id,selling_price FROM products LIMIT 1',1062]];
 foreach($tests as [$sql,$code]){try{$affected=$restore->exec($sql);throw new RuntimeException('Constraint did not reject mutation: '.$sql);}catch(PDOException $e){if((int)$e->errorInfo[1]!==$code)throw $e;$constraintChecks++;}}
}finally{$restore->rollBack();}
$report=['source'=>$source,'isolated_restore'=>$target,'backup'=>$backup,'sha256'=>$hash,'bytes'=>filesize($backup),'tables'=>$tableCount,'rows_verified'=>$rowsTotal,'triggers'=>count($triggers[$target]),'restore_only_definers_rebound'=>$rebound,'migrations'=>count($migrations),'constraint_negative_checks'=>$constraintChecks,'data_and_schema_match'=>true];
file_put_contents($private.'/verification.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
