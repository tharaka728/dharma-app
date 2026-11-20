<?php
declare(strict_types=1);
// Run only through a private, external administrator wrapper. Never reset a database.
if(PHP_SAPI!=='cli'||!isset($phase9Admin,$phase9TestConfig))exit(1);
$name='dharma_sales_phase9_test';
$q=$phase9Admin->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');$q->execute([$name]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Test database already exists. No reset is permitted.');
$phase9Admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
$phase9Admin->exec("CREATE USER '".$phase9TestConfig['user']."'@'127.0.0.1' IDENTIFIED BY ".$phase9Admin->quote($phase9TestConfig['password']));
$phase9Admin->exec("GRANT ALL PRIVILEGES ON `".$name."`.* TO '".$phase9TestConfig['user']."'@'127.0.0.1'");
echo "Created NEW isolated application test database: $name. No existing database reset.\n";
