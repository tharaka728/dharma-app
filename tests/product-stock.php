<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\ProductStock;use App\Services\Inventory;use App\Support\Database;use App\Support\InventoryException;use App\Controllers\ProductStockController;
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||!in_array(getenv('DB_NAME'),['dharma_sales_phase1','dharma_sales_phase9_test'],true))throw new RuntimeException('Exact application-only test target required.');
$pdo=Database::connect();$stock=new ProductStock($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$reject=static function(callable $fn,int $status,string $label)use($check){try{$fn();}catch(InventoryException $e){$check($e->status===$status,$label);return;}throw new RuntimeException('Unexpectedly accepted: '.$label);};
$before=[];foreach(['users','categories','products','customers','product_batches','product_batch_versions','stock_locations','inventory_settings','product_stock_balances','inventory_balances','inventory_transactions','inventory_movements','audit_log'] as $table)$before[$table]=hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));
$pdo->beginTransaction();
try{
 $prefix='SIMPLE'.strtoupper(bin2hex(random_bytes(6)));foreach(['ADMIN','SALES_REP'] as $role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Rollback stock test',$prefix.$role,password_hash(bin2hex(random_bytes(8)),PASSWORD_DEFAULT),$role]);$actors[$role]=['id'=>$pdo->lastInsertId(),'name'=>'Rollback stock test'];}$admin=$actors['ADMIN'];$rep=$actors['SALES_REP'];
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$prefix]);$cat=$pdo->lastInsertId();$pdo->prepare('INSERT INTO products(sku,name,category_id,selling_price,cost_price) VALUES (?,?,?,0.10,NULL)')->execute([$prefix,'Rollback simple stock',$cat]);$product=$pdo->lastInsertId();
 $input=['request_id'=>Inventory::uuid(),'product_id'=>$product,'quantity'=>'3','selling_price'=>'0.10','product_version'=>'1'];
 $check($stock->listing($admin,['product_id'=>$product])['rows'][0]['stock_value']==='0.00','No-stock product value is exact zero');
 $posted=$stock->post($admin,$input);$check($posted['quantity']==='3','First stock add');$check($stock->post($admin,$input)['replayed'],'Duplicate submission does not add again');
 $row=$stock->listing($admin,['product_id'=>$product])['rows'][0];$check((string)$row['quantity']==='3'&&$row['stock_value']==='0.30','Decimal-safe quantity multiplied by product price');
 $next=$input;$next['request_id']=Inventory::uuid();$next['quantity']='2';$next['selling_price']='12.34';$check($stock->post($admin,$next)['quantity']==='5','Additional stock increments balance');
 $row=$pdo->query("SELECT selling_price,version FROM products WHERE id=$product")->fetch();$check($row['selling_price']==='12.34'&&(string)$row['version']==='2','Price explicitly updates authoritative Products field and version');
 $check($stock->listing($admin,['product_id'=>$product])['rows'][0]['stock_value']==='61.70','Stock value immediately uses new authoritative price');
 $check($stock->post($admin,$next)['replayed'],'Retry succeeds after product version changed by original save');
 $bad=$next;$bad['quantity']='4';$reject(fn()=>$stock->post($admin,$bad),409,'Changed retry payload conflict');
 $bad=$next;$bad['request_id']=Inventory::uuid();$reject(fn()=>$stock->post($admin,$bad),409,'Concurrent/stale product price version conflict');
 foreach(['0','-1','1.5','9223372036854775808'] as $qty){$bad=$input;$bad['quantity']=$qty;$reject(fn()=>$stock->post($admin,$bad),422,'Invalid received quantity');}
 foreach(['-1','1.001','1e3',1.2] as $price){$bad=$input;$bad['selling_price']=$price;$reject(fn()=>$stock->post($admin,$bad),422,'Money validation');}
 $reject(fn()=>$stock->post($rep,$input),403,'Rep stock add denied');$reject(fn()=>$stock->listing($rep,[]),403,'Rep stock listing denied');$reject(fn()=>$stock->history($rep,[]),403,'Rep history denied');
 $adjust=['request_id'=>Inventory::uuid(),'product_id'=>$product,'quantity'=>'2','direction'=>'OUT','reason'=>'Correct verified physical count'];$check($stock->post($admin,$adjust,true)['quantity']==='3','Reasoned reduction');$check($stock->post($admin,$adjust,true)['replayed'],'Reduction retry is idempotent');
 $bad=$adjust;$bad['request_id']=Inventory::uuid();$bad['quantity']='4';$reject(fn()=>$stock->post($admin,$bad,true),409,'Negative stock prevented');$bad['quantity']='1';$bad['reason']='';$reject(fn()=>$stock->post($admin,$bad,true),422,'Adjustment reason mandatory');
 $bad=$adjust;$bad['request_id']=Inventory::uuid();$bad['direction']='IN';$bad['quantity']='1';$check($stock->post($admin,$bad,true)['quantity']==='4','Positive correction');
 $check($stock->reconciliation($admin)===[],'Balances reconcile with existing immutable ledger');$history=$stock->history($admin,['product_id'=>$product]);$check($history['total']===4,'Basic history includes adds/removals');
 $pdo->exec('SAVEPOINT legacy_probe');$pdo->prepare('INSERT INTO product_batches(product_id,batch_number) VALUES (?,?)')->execute([$product,$prefix]);$legacyBatch=$pdo->lastInsertId();$location=$stock->state($admin)['warehouse_location_id'];$pdo->prepare('INSERT INTO inventory_balances(location_id,product_id,batch_id,quantity) VALUES (?,?,?,1)')->execute([$location,$product,$legacyBatch]);
 $check((int)$stock->state($admin)['legacy_stock_conflict']===1,'Unexpected batch quantities trigger a visible compatibility warning');
 $probe=$input;$probe['request_id']=Inventory::uuid();$probe['product_version']='2';$reject(fn()=>$stock->post($admin,$probe),409,'Legacy quantities are never silently aggregated or mixed');$pdo->exec('ROLLBACK TO SAVEPOINT legacy_probe');
 $check((int)$stock->state($admin)['legacy_stock_conflict']===0,'Compatibility probe leaves no legacy stock');
 $check($pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE product_id=$product AND batch_id IS NULL AND batch_version_id IS NULL")->fetchColumn()==4,'No synthetic batch IDs or dates');
 $pdo->exec("UPDATE product_stock_balances SET quantity=5 WHERE product_id=$product");$bad=$input;$bad['request_id']=Inventory::uuid();$bad['product_version']='2';$reject(fn()=>$stock->post($admin,$bad),409,'Unreconciled totals block stock posting');$pdo->exec("UPDATE product_stock_balances SET quantity=4 WHERE product_id=$product");
 $pdo->exec("UPDATE inventory_settings SET stock_model='BATCH' WHERE id=1");$reject(fn()=>$stock->post($admin,$bad),409,'Unsafe mode transition blocks product posting');$pdo->exec("UPDATE inventory_settings SET stock_model='PRODUCT' WHERE id=1");
 $reject(fn()=>(new Inventory($pdo))->saveBatch($admin,[]),422,'Old invalid batch input still validates');
 $legacyInput=['product_id'=>$product,'batch_number'=>$prefix,'manufacturing_date'=>'','expiry_date'=>'2099-01-01','status'=>'QUARANTINED','reason'=>'Retired path probe'];$reject(fn()=>(new Inventory($pdo))->saveBatch($admin,$legacyInput),409,'Old batch writers cannot create parallel stock');
 try{$pdo->prepare('UPDATE inventory_movements SET quantity_delta=quantity_delta WHERE transaction_id=?')->execute([$posted['transaction_id']]);throw new RuntimeException('Ledger edit accepted');}catch(PDOException $e){$check((int)($e->errorInfo[1]??0)===1644,'Movement immutability preserved');}
 $_SESSION=['csrf_token'=>bin2hex(random_bytes(32))];$_GET=['product_id'=>$product];$_POST=[];$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/admin/inventory';
 foreach(['/admin/inventory','/admin/inventory/add','/admin/inventory/adjustments','/admin/inventory/history'] as $path){$_SERVER['REQUEST_URI']=$path;ob_start();try{(new ProductStockController($stock))->handle(ProductStockController::route($path),$admin);$html=ob_get_contents();}finally{ob_end_clean();}$check(strpos($html,'+ Add Stock')!==false&&strpos($html,'DHARMA CHEMICAL')!==false,'Simple responsive page '.$path);if($path==='/admin/inventory/add'){$check(strpos($html,'Save Stock')!==false&&strpos($html,'name="selling_price"')!==false,'Simple quantity/price entry');$check(strpos($html,'name="batch_id"')===false&&strpos($html,'name="expiry_date"')===false&&strpos($html,'name="supplier"')===false,'No batch/supplier/date inputs');}}
 $_SERVER['REQUEST_METHOD']='POST';$_POST=$input;http_response_code(200);ob_start();try{(new ProductStockController($stock))->handle(ProductStockController::route('/api/v1/admin/inventory/add'),$admin);}finally{ob_end_clean();}$check(http_response_code()===403,'Stock entry CSRF required');
 $_POST=$next+['csrf_token'=>$_SESSION['csrf_token'],'price_product_id'=>$product];http_response_code(200);ob_start();try{(new ProductStockController($stock))->handle(ProductStockController::route('/admin/inventory/add'),$admin);}finally{ob_end_clean();}$check(http_response_code()===303&&isset($_SESSION['inventory_notice']),'Saved request redirects to updated inventory with notice');
 $check($stock->reconciliation($admin)===[],'Final ledger reconciliation');
}finally{if($pdo->inTransaction())$pdo->rollBack();}
foreach($before as $table=>$hash)$check(hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR))===$hash,'Original data preserved: '.$table);
echo "$count product-only stock, price, idempotency, security, history and preservation checks passed; fixtures rolled back.\n";
