<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);require dirname(__DIR__).'/app/bootstrap.php';
$pdo=App\Support\Database::connect();if(getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||$pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1')throw new RuntimeException('Wrong verification target.');
$actor=$pdo->query("SELECT id FROM users WHERE role='ADMIN' AND is_active=1 ORDER BY id LIMIT 1")->fetch();if(!$actor)throw new RuntimeException('Existing admin required.');
$warehouse=new App\Services\ProductStock($pdo);$reps=new App\Services\RepresentativeStock($pdo);
if($warehouse->reconciliation($actor)!==[]||$reps->reconciliation($actor)!==[])throw new RuntimeException('Live stock reconciliation failed.');
echo "Live warehouse and representative ledger reconciliation passed.\n";
foreach(['representative_stock_balances','product_stock_transfer_items','stock_transfers'] as $table)echo $table.': '.$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()." rows\n";
$bad=$pdo->query("SELECT COUNT(*) FROM (SELECT t.id FROM stock_transfers t JOIN product_stock_transfer_items i ON i.transfer_id=t.id LEFT JOIN inventory_movements m ON m.transaction_id=t.inventory_transaction_id AND m.product_id=i.product_id GROUP BY t.id,i.product_id,i.quantity,t.from_location_id,t.to_location_id HAVING COUNT(m.id)<>2 OR SUM(m.quantity_delta)<>0 OR SUM(CASE WHEN m.location_id=t.from_location_id AND m.quantity_delta=-i.quantity THEN 1 ELSE 0 END)<>1 OR SUM(CASE WHEN m.location_id=t.to_location_id AND m.quantity_delta=i.quantity THEN 1 ELSE 0 END)<>1) bad")->fetchColumn();
if((int)$bad!==0)throw new RuntimeException('Transfer conservation/locations failed.');echo "Every product transfer conserves stock with matching source/destination legs.\n";
