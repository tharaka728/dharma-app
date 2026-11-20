<?php
declare(strict_types=1);
// Creates a newly named, isolated synthetic schema; never touches existing schemas.
if(PHP_SAPI!=='cli')exit(1);
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($ini,$auth);
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected server.');
$name='dharma_return_test_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3));
$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');$q->execute([$name]);if($q->fetchColumn())throw new RuntimeException('Schema already exists.');
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");
$pdo->exec('CREATE TABLE products(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/020_historical_return_prices.sql');
$sql=preg_replace('/^\s*--[^\r\n]*$/m','',$sql);
foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$pdo->exec($statement);
$pdo->exec('INSERT INTO products VALUES (1)');$pdo->exec('INSERT INTO users VALUES (1)');
$pdo->exec("INSERT INTO historical_return_price_revisions(product_id,price_year,revision,unit_price,status,reason,actor_user_id) VALUES (1,2024,1,100.00,'APPROVED','synthetic test',1),(1,2025,1,125.00,'APPROVED','synthetic test',1),(1,2024,2,110.00,'APPROVED','correction',1)");
$checks=0;
foreach([
 "UPDATE historical_return_price_revisions SET unit_price=1 WHERE id=1",
 "DELETE FROM historical_return_price_revisions WHERE id=1",
 "INSERT INTO historical_return_price_revisions(product_id,price_year,revision,unit_price,status,reason,actor_user_id) VALUES (1,2024,1,1,'APPROVED','duplicate',1)",
 "INSERT INTO historical_return_price_revisions(product_id,price_year,revision,unit_price,status,reason,actor_user_id) VALUES (1,2024,3,-1,'APPROVED','negative',1)"
] as $sql){try{$pdo->exec($sql);}catch(PDOException $e){$checks++;continue;}throw new RuntimeException('Expected constraint was not enforced.');}
echo "Synthetic isolated schema: $name; migration applied; $checks constraint checks passed. Schema retained for inspection.\n";
