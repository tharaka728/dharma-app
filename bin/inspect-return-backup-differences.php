<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$source='dharma_sales_phase1';$restore=$argv[1]??'dharma_sales_phase12_restore_20261119033438';
if(!preg_match('/^dharma_sales_phase12_restore_[0-9]{14}$/D',$restore))throw new RuntimeException('Unexpected restore name');
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);unset($ini,$auth);
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Wrong server');
$engines=$pdo->query("SELECT ENGINE,COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA='$source' AND TABLE_TYPE='BASE TABLE' GROUP BY ENGINE")->fetchAll();
$columns=$pdo->query("SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$source' AND TABLE_NAME='auth_login_limits' ORDER BY ORDINAL_POSITION")->fetchAll();
$tables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$source' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);$differences=[];
foreach($tables as $table){
 if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Unexpected table name');
 $counts=[];$hashes=[];
 foreach([$source,$restore] as $schema){
  $q=$pdo->query("SELECT * FROM `$schema`.`$table`");$rows=[];
  while($row=$q->fetch())$rows[]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));sort($rows,SORT_STRING);$counts[]=count($rows);$hashes[]=hash('sha256',implode('', $rows));
 }
 if($counts[0]!==$counts[1]||$hashes[0]!==$hashes[1])$differences[]=['table'=>$table,'source_count'=>$counts[0],'restore_count'=>$counts[1]];
}
echo json_encode(['server_version'=>$pdo->query('SELECT VERSION()')->fetchColumn(),'engines'=>$engines,'login_columns'=>$columns,'tables_checked'=>count($tables),'differing_tables'=>$differences],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
