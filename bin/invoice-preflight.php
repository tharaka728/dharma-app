<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/inventory-preflight.php';
foreach(['invoices','invoice_items','payments','payment_allocations','invoice_sequence_counters','invoice_number_allocations','offline_policy_grants','representative_stock_balances','promotions','promotion_versions'] as $table){
 $rows=$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();
 echo $table.': '.count($rows).' rows; SHA256 '.hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)).PHP_EOL;
}
foreach(['invoices','invoice_items','payments','payment_allocations'] as $table)echo $pdo->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1].PHP_EOL;
