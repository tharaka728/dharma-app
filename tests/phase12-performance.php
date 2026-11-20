<?php
declare(strict_types=1);
require __DIR__.'/reports.php';
$pdo->beginTransaction();$metrics=[];
try{
 $tag='PERF'.bin2hex(random_bytes(5));$cat=$pdo->query('SELECT category_id FROM products WHERE id='.(int)$f['products'][0])->fetchColumn();
 $q=$pdo->prepare('INSERT INTO products(sku,name,category_id,selling_price) VALUES (?,?,?,125.50)');for($i=0;$i<1000;$i++)$q->execute([$tag.$i,'Isolated catalogue volume '.$i,$cat]);
 $c=$pdo->query('SELECT * FROM customers WHERE id='.(int)$f['customer'])->fetch();unset($c['id']);$ids=[];
 for($i=0;$i<500;$i++){$c['customer_code']=$tag.$i;$c['shop_name']='Isolated volume shop '.$i;$insert('customers',$c);$ids[]=$pdo->lastInsertId();}
 for($i=0;$i<500;$i++){$h=$header;$h['id']=\App\Services\Inventory::uuid();$h['invoice_number']=$tag.'-'.$i;$h['sequence_number']=(string)(5000+$i);$h['customer_id']=$ids[$i];$insert('invoices',$h);$d=$detail;$d['invoice_id']=$h['id'];$insert('online_invoice_details',$d);foreach($items as $item){unset($item['id']);$item['invoice_id']=$h['id'];$insert('invoice_items',$item);}}
 foreach(['overview','products','customers','inventory','representatives'] as $type){$start=hrtime(true);$r=$svc->report($admin,$type,['period'=>'today','rep_id'=>(string)$rep['id']]);$metrics[$type.'_milliseconds']=intdiv(hrtime(true)-$start,1000000);}
 $start=hrtime(true);(new \App\Services\Products($pdo))->listing($admin,['q'=>$tag]);$metrics['product_list_milliseconds']=intdiv(hrtime(true)-$start,1000000);
 $start=hrtime(true);(new \App\Services\Customers($pdo))->listing($admin,['q'=>$tag]);$metrics['customer_list_milliseconds']=intdiv(hrtime(true)-$start,1000000);
 foreach(['invoices','payments','inventory_movements','customers','products'] as $table)$metrics[$table.'_indexes']=count(array_unique(array_column($pdo->query('SHOW INDEX FROM `'.$table.'`')->fetchAll(),'Key_name')));
}finally{$pdo->rollBack();}
if($hashes()!==$before)throw new RuntimeException('Volume fixtures were not completely rolled back');
echo json_encode(['additional_products'=>1000,'additional_customers'=>500,'additional_invoices'=>500,'query_measurements'=>$metrics,'all_volume_fixtures_rolled_back'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
