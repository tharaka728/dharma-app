<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}require dirname(__DIR__).'/app/bootstrap.php';
$pdo=App\Support\Database::connect();
if(getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||$pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1'||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309){throw new RuntimeException('Inventory target mismatch.');}
foreach($pdo->query('SELECT version,checksum,status FROM schema_migrations') as $m){if($m['status']!=='applied'||!hash_equals($m['checksum'],hash_file('sha256',APP_ROOT.'/database/migrations/'.$m['version']))){throw new RuntimeException('Existing migration state/checksum mismatch.');}}
echo "Exact target: dharma_sales_phase1 at 127.0.0.1:3309; existing migrations verified.\n";
$tables=['products','customers','product_batches','product_batch_versions','stock_locations','inventory_balances','inventory_transactions','inventory_movements'];
if($pdo->query("SHOW TABLES LIKE 'product_stock_balances'")->fetchColumn()){$tables[]='product_stock_balances';}
foreach($tables as $table){
    $rows=$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();
    echo $table.': '.count($rows).' records; content SHA256 '.hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))."\n";
}
