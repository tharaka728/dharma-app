<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname=dharma_sales_phase1;charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);unset($ini,$auth);
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1'||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected target');
$q=$pdo->query("SELECT COUNT(*) total,SUM(CASE WHEN name_si IS NULL OR TRIM(name_si)='' THEN 1 ELSE 0 END) missing_sinhala,SUM(CASE WHEN is_active=1 AND (name_si IS NULL OR TRIM(name_si)='') THEN 1 ELSE 0 END) active_missing_sinhala FROM products");
echo json_encode($q->fetch(),JSON_THROW_ON_ERROR).PHP_EOL;
