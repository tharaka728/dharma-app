<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$name=$argv[1]??'';if(!is_string($name)||!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/D',$name))throw new RuntimeException('Invalid username');
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname=dharma_sales_phase1;charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($ini,$auth);
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1'||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected target');
$q=$pdo->prepare("SELECT id FROM users WHERE username=? AND role='SALES_REP' AND is_active=1");$q->execute([$name]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
if(count($ids)!==1)throw new RuntimeException('Username does not identify exactly one active representative');
echo json_encode(['representative_id'=>(string)$ids[0],'verified_active_representative'=>true],JSON_THROW_ON_ERROR).PHP_EOL;
