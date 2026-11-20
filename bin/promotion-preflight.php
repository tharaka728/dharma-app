<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);require dirname(__DIR__).'/app/bootstrap.php';$pdo=App\Support\Database::connect();
if(getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||$pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1')throw new RuntimeException('Wrong promotion target.');
foreach(['promotions','promotion_versions'] as $table){echo $table.': '.$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()." rows\n";$row=$pdo->query('SHOW CREATE TABLE '.$table)->fetch();echo array_values($row)[1]."\n";}
