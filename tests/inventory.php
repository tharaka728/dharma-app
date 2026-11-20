<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\Inventory;use App\Support\Database;use App\Support\InventoryException;use App\Controllers\InventoryController;
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||!in_array(getenv('DB_NAME'),['dharma_sales_phase1','dharma_sales_phase9_test'],true)){throw new RuntimeException('Exact application-only test target required.');}
$pdo=Database::connect();$service=new Inventory($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$reject=static function(callable $fn,int $status,string $label)use($check){try{$fn();}catch(InventoryException $e){$check($e->status===$status,$label);return;}throw new RuntimeException('Unexpectedly accepted: '.$label);};
$snapshot=[];$tables=['users','categories','products','customers','product_batches','product_batch_versions','stock_locations','inventory_balances','inventory_transactions','inventory_movements','inventory_batch_controls','inventory_product_settings','inventory_settings','audit_log','sync_stream_state','change_log'];
foreach($tables as $table){$snapshot[$table]=hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));}
$pdo->beginTransaction();
$pdo->exec("UPDATE inventory_settings SET stock_model='BATCH' WHERE id=1");
try{
 $prefix='INVTEST'.strtoupper(bin2hex(random_bytes(6)));
 foreach(['ADMIN','SALES_REP'] as $role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Rollback inventory test',$prefix.$role,password_hash(bin2hex(random_bytes(8)),PASSWORD_DEFAULT),$role]);$actors[$role]=['id'=>$pdo->lastInsertId(),'name'=>'Rollback inventory test'];}
 $admin=$actors['ADMIN'];$rep=$actors['SALES_REP'];
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$prefix]);$category=$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO products(sku,name,category_id,selling_price,cost_price) VALUES (?,?,?,0,NULL)')->execute([$prefix,'Rollback inventory product',$category]);$product=$pdo->lastInsertId();
 $settings=$service->settings($admin);$default=$pdo->query("SHOW COLUMNS FROM inventory_settings LIKE 'near_expiry_days'")->fetch();$check((int)$default['Default']===90,'Schema near-expiry default');
 $pdo->exec('UPDATE inventory_settings SET warehouse_location_id=NULL');
 $service->configure($admin,['version'=>(string)$settings['version'],'warehouse_code'=>$prefix,'near_expiry_days'=>'90']);$settings=$service->settings($admin);$location=(string)$settings['warehouse_location_id'];
 $check($settings['warehouse_code']===$prefix,'Explicit warehouse configuration');
 $reject(fn()=>$service->configure($admin,['version'=>'0','near_expiry_days'=>'90']),409,'Settings optimistic conflict');
 $reject(fn()=>$service->configure($rep,[]),403,'Rep settings denied');
 foreach(['0','-1','1.2','1e3','9223372036854775808',[],null] as $invalid){$reject(fn()=>Inventory::quantity($invalid),422,'Invalid quantity');}
 $check(Inventory::quantity('9223372036854775807')===PHP_INT_MAX,'Maximum integer quantity');
 foreach(['2026-02-30','2026-13-01','2026-1-01','0000-01-01',[]] as $invalid){$reject(fn()=>Inventory::date($invalid,'date'),422,'Invalid date');}
 $expiry='2026-11-09';
 $check(Inventory::saleable($expiry,'SALEABLE',true,new DateTimeImmutable('2026-11-09T18:29:59.999999Z')),'Saleable through last instant of Colombo expiry date');
 $check(!Inventory::saleable($expiry,'SALEABLE',true,new DateTimeImmutable('2026-11-09T18:30:00Z')),'Expired exactly at next Colombo midnight');
 $check(Inventory::today(new DateTimeImmutable('2026-11-08T18:30:00Z'))===$expiry,'Colombo expiry date begins at UTC offset');
 $check(Inventory::saleable($expiry,'SALEABLE',true,new DateTimeImmutable('2026-11-08T18:30:00Z')),'Saleable at start of expiry date');
 foreach(['QUARANTINED','BLOCKED','RECALLED'] as $status){$check(!Inventory::saleable($expiry,$status,true,new DateTimeImmutable('2026-11-09T00:00:00Z')),'Manual status blocks availability '.$status);}
 $check(!Inventory::saleable($expiry,'SALEABLE',false,new DateTimeImmutable('2026-11-09T00:00:00Z')),'Inactive product unavailable');
 $today=Inventory::today();$later=(new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');$yesterday=(new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
 $zero=$service->stocks($admin,['product_id'=>$product]);$check(count($zero['totals'])===1&&$zero['totals'][0]['physical']==='0','Existing product without batches has computed zero stock');
 $input=['product_id'=>$product,'batch_number'=>$prefix,'manufacturing_date'=>'','expiry_date'=>$later,'status'=>'SALEABLE','reason'=>'Rollback batch provenance','acquisition_info'=>'Rollback only'];
 $batch=$service->saveBatch($admin,$input);$row=$service->batch($admin,$batch);$check($row['manufacturing_date']===null,'Optional manufacturing date');
 $reject(fn()=>$service->saveBatch($admin,$input),409,'Product/batch uniqueness');
 $reject(fn()=>$service->saveBatch($rep,$input),403,'Rep batch create denied');
 $bad=$input;$bad['batch_number'].='BAD';$bad['manufacturing_date']=$later;$bad['expiry_date']=$yesterday;$reject(fn()=>$service->saveBatch($admin,$bad),422,'Manufacturing after expiry rejected');
 $bad=$input;$bad['expiry_date']=$yesterday;$reject(fn()=>$service->saveBatch($admin,$bad),422,'Expired batch cannot be marked saleable');
 $expired=$bad;$expired['batch_number'].='EXPIRED';$expired['status']='QUARANTINED';$expiredId=$service->saveBatch($admin,$expired);
 $receipt=['request_id'=>Inventory::uuid(),'batch_id'=>$batch,'quantity'=>'10','reason'=>'Rollback receipt','reference'=>'=UNSAFE()','notes'=>'Rollback only','supplier'=>'Rollback supplier','receipt_date'=>$today,'unit_cost'=>'123.45'];
 $posted=$service->post($admin,$receipt,'RECEIPT');$check($posted['quantity_after']==='10'&&!$posted['replayed'],'Receipt creates real ledger delta inside rollback test');
 $check($service->post($admin,$receipt,'RECEIPT')['replayed'],'Duplicate receipt returns original result');
 $check($pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE batch_id=$batch")->fetchColumn()==1,'Retry has one movement');
 $bad=$receipt;$bad['quantity']='11';$reject(fn()=>$service->post($admin,$bad,'RECEIPT'),409,'Idempotency rejects changed payload');
 $bad=$receipt;$bad['request_id']=Inventory::uuid();$bad['unit_cost']='1.001';$reject(fn()=>$service->post($admin,$bad,'RECEIPT'),422,'Cost decimal precision');
 $bad=$receipt;$bad['request_id']=Inventory::uuid();$bad['receipt_date']=$later;$reject(fn()=>$service->post($admin,$bad,'RECEIPT'),422,'Future receipt date rejected');
 $reject(fn()=>$service->post($rep,$receipt,'RECEIPT'),403,'Rep receipt denied');
 $adjust=['request_id'=>Inventory::uuid(),'batch_id'=>$batch,'direction'=>'OUT','quantity'=>'4','reason'=>'Rollback damaged units','reference'=>'Rollback'];
 $adjusted=$service->post($admin,$adjust,'ADJUSTMENT');$check($adjusted['quantity_after']==='6','Negative adjustment');
 $check($service->post($admin,$adjust,'ADJUSTMENT')['replayed'],'Duplicate adjustment does not deduct again');
 $bad=$adjust;$bad['request_id']=Inventory::uuid();$bad['quantity']='7';$reject(fn()=>$service->post($admin,$bad,'ADJUSTMENT'),409,'Insufficient physical stock');
 $bad=$adjust;$bad['reason']='';$reject(fn()=>$service->post($admin,$bad,'ADJUSTMENT'),422,'Mandatory adjustment reason');
 $overflow=$adjust;$overflow['request_id']=Inventory::uuid();$overflow['direction']='IN';$overflow['quantity']=(string)PHP_INT_MAX;$reject(fn()=>$service->post($admin,$overflow,'ADJUSTMENT'),422,'Overflow blocked before integer addition');
 $reject(fn()=>$service->post($rep,$adjust,'ADJUSTMENT'),403,'Rep adjustment denied');
 $increase=$adjust;$increase['request_id']=Inventory::uuid();$increase['direction']='IN';$increase['quantity']='2';$check($service->post($admin,$increase,'ADJUSTMENT')['quantity_after']==='8','Positive adjustment');
 $reverse=$adjust;$reverse['request_id']=Inventory::uuid();$reverse['direction']='IN';$reverse['quantity']='4';$reverse['reverses_transaction_id']=$adjusted['transaction_id'];$check($service->post($admin,$reverse,'ADJUSTMENT')['quantity_after']==='12','Explicit exact reversal');
 $reverse['request_id']=Inventory::uuid();$reject(fn()=>$service->post($admin,$reverse,'ADJUSTMENT'),409,'Duplicate reversal rejected');
 $q=$pdo->prepare('SELECT unit_cost,receipt_date FROM inventory_transactions WHERE id=?');$q->execute([$posted['transaction_id']]);$saved=$q->fetch();$check($saved['unit_cost']==='123.45'&&$saved['receipt_date']===$today,'Decimal-string receipt metadata persisted');
 $check($service->reconciliation($admin)===[],'Ledger reconciliation after receipts adjustments reversal');
 foreach(['inventory_transactions','inventory_movements','product_batches','product_batch_versions'] as $table){
     try{$pdo->exec("UPDATE $table SET ".($table==='inventory_transactions'?"reason=reason WHERE id='".$posted['transaction_id']."'":($table==='inventory_movements'?"quantity_delta=quantity_delta WHERE batch_id=$batch":($table==='product_batches'?"batch_number=batch_number WHERE id=$batch":"reason=reason WHERE batch_id=$batch"))));throw new RuntimeException('Immutable update allowed');}catch(PDOException $e){$check((int)($e->errorInfo[1]??0)===1644,'Immutable update '.$table);}
 }
 $stocks=$service->stocks($admin,['product_id'=>$product]);$check($stocks['total']===2,'Stock batch listing and product filter');$check((string)$stocks['totals'][0]['physical']==='12'&&(string)$stocks['totals'][0]['available']==='12','Physical and available stock totals');
 $check(count($service->stocks($admin,['product_id'=>$product,'expiry'=>'near'])['rows'])===1,'Near-expiry filter');
 $check(count($service->stocks($admin,['product_id'=>$product,'expiry'=>'expired'])['rows'])===1,'Expired filter');
 $check(count($service->stocks($admin,['product_id'=>$product,'availability'=>'available'])['rows'])===1,'Available filter');
 $check(count($service->stocks($admin,['product_id'=>$product,'category_id'=>$category,'batch'=>$prefix,'q'=>$prefix])['rows'])===2,'Search/category/batch filters');
 $check(count(array_filter($stocks['low'],fn($v)=>(string)$v['id']===$product))===0,'No low alert without configured threshold');
 $service->threshold($admin,['product_id'=>$product,'version'=>'0','low_stock_threshold'=>'12']);$stocks=$service->stocks($admin,['product_id'=>$product]);$check(count(array_filter($stocks['low'],fn($v)=>(string)$v['id']===$product))===1,'Configured low threshold triggers at boundary');
 $reject(fn()=>$service->threshold($admin,['product_id'=>$product,'version'=>'0','low_stock_threshold'=>'1']),409,'Threshold concurrency conflict');
 $edit=$input+['version_number'=>(string)$row['version_number'],'control_version'=>(string)$row['control_version']];$edit['status']='BLOCKED';$service->saveBatch($admin,$edit,$batch);
 $check((string)$service->stocks($admin,['product_id'=>$product])['totals'][0]['available']==='0','Blocked batch has zero available');
 $reject(fn()=>$service->saveBatch($admin,$edit,$batch),409,'Stale batch revision conflict');
 $movement=$pdo->query("SELECT batch_version_id FROM inventory_movements WHERE batch_id=$batch ORDER BY id LIMIT 1")->fetchColumn();$check((string)$movement===(string)$row['revision_id'],'Batch changes preserve movement revision snapshots');
 $check($pdo->query("SELECT COUNT(*) FROM product_batch_versions WHERE batch_id=$batch")->fetchColumn()==2,'Date/control correction appends revision');
 $pdo->exec("UPDATE inventory_balances SET quantity=13 WHERE batch_id=$batch");$check(count($service->reconciliation($admin))===1,'Reconciliation detects direct corruption');
 $bad=$increase;$bad['request_id']=Inventory::uuid();$reject(fn()=>$service->post($admin,$bad,'ADJUSTMENT'),409,'Corrupted balance blocks posting');$pdo->exec("UPDATE inventory_balances SET quantity=12 WHERE batch_id=$batch");
 $history=$service->movements($admin,['product_id'=>$product]);$check($history['total']===4,'Movement history includes all immutable posts');
 $expiredReceipt=$receipt;$expiredReceipt['request_id']=Inventory::uuid();$expiredReceipt['batch_id']=$expiredId;$expiredReceipt['quantity']='3';$service->post($admin,$expiredReceipt,'RECEIPT');
 $expiredStocks=$service->stocks($admin,['product_id'=>$product,'expiry'=>'expired']);$check(count($expiredStocks['rows'])===1&&(string)$expiredStocks['rows'][0]['physical']==='3'&&(string)$expiredStocks['rows'][0]['available']==='0','Expired physical receipt is visible but unavailable');
 $boundary=$input;$boundary['batch_number'].='DAY';$boundary['expiry_date']=$today;$boundaryId=$service->saveBatch($admin,$boundary);
 $boundaryReceipt=$receipt;$boundaryReceipt['request_id']=Inventory::uuid();$boundaryReceipt['batch_id']=$boundaryId;$boundaryReceipt['quantity']='1';$service->post($admin,$boundaryReceipt,'RECEIPT');
 $boundaryRows=$service->stocks($admin,['product_id'=>$product,'availability'=>'available']);$check(count($boundaryRows['rows'])===1&&(string)$boundaryRows['rows'][0]['batch_id']===$boundaryId,'SQL availability permits stock through expiry day');
 $nearBoundary=$input;$nearBoundary['batch_number'].='NINETY';$nearBoundary['expiry_date']=(new DateTimeImmutable($today))->modify('+90 days')->format('Y-m-d');$nearId=$service->saveBatch($admin,$nearBoundary);
 $nearRows=$service->stocks($admin,['product_id'=>$product,'expiry'=>'near']);$check(in_array($nearId,array_map('strval',array_column($nearRows['rows'],'batch_id')),true),'Near-expiry threshold includes day 90');
 $beyond=$nearBoundary;$beyond['batch_number'].='PLUSONE';$beyond['expiry_date']=(new DateTimeImmutable($today))->modify('+91 days')->format('Y-m-d');$beyondId=$service->saveBatch($admin,$beyond);
 $validRows=$service->stocks($admin,['product_id'=>$product,'expiry'=>'valid']);$check(count($validRows['rows'])===1&&(string)$validRows['rows'][0]['batch_id']===$beyondId,'Beyond-alert filter starts on day 91');
 $check($service->reconciliation($admin)===[],'Boundary/expired receipts remain reconciled');
 $opening=$receipt;$opening['request_id']=Inventory::uuid();$opening['batch_id']=$nearId;$opening['product_id']=$product;$opening['quantity']='7';$opening['reason']='Verified opening physical count';
 $opened=$service->post($admin,$opening,'OPENING');$check($opened['quantity_after']==='7','Explicit opening stock increases correct batch balance');
 $check($service->post($admin,$opening,'OPENING')['replayed'],'Opening request replay cannot duplicate stock');
 $q=$pdo->prepare('SELECT type,receipt_date,created_by_user_id FROM inventory_transactions WHERE id=?');$q->execute([$opened['transaction_id']]);$openingRow=$q->fetch();$check($openingRow['type']==='OPENING'&&$openingRow['receipt_date']===$today&&(string)$openingRow['created_by_user_id']===(string)$admin['id'],'Opening ledger records distinct type, date and responsible user');
 $secondOpening=$opening;$secondOpening['request_id']=Inventory::uuid();$reject(fn()=>$service->post($admin,$secondOpening,'OPENING'),409,'Opening forbidden after any movement history');
 $badOpening=$opening;$badOpening['batch_id']=$batch;$badOpening['request_id']=Inventory::uuid();$reject(fn()=>$service->post($admin,$badOpening,'OPENING'),409,'Opening cannot overwrite an existing receipt balance');
 $badOpening=$opening;$badOpening['quantity']='-1';$reject(fn()=>$service->post($admin,$badOpening,'OPENING'),422,'Negative opening quantity rejected');
 $reject(fn()=>$service->post($rep,$opening,'OPENING'),403,'Rep opening stock forbidden');
 $wrong=$receipt;$wrong['request_id']=Inventory::uuid();$wrong['product_id']='999999999999999999';$reject(fn()=>$service->post($admin,$wrong,'RECEIPT'),422,'Product/batch mismatch rejected before posting');
 $check($service->reconciliation($admin)===[],'Opening and receipt balances reconcile');
 foreach(['=SUM(1,2)',' +CMD','-1','@IMPORT',"\t=BAD","\xEF\xBB\xBF=BAD"] as $cell){$check(strpos(Inventory::csvCell($cell),"'")===0,'CSV formula injection escaped');}
 $check(Inventory::csvCell('Safe')==='Safe','Safe CSV text unchanged');
 $reject(fn()=>$service->movements($rep,[]),403,'Rep movement export/history denied');$reject(fn()=>$service->stocks($rep,[]),403,'Rep inventory denied');
 $_SESSION=['csrf_token'=>bin2hex(random_bytes(32))];$_GET=[];$_POST=[];$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/admin/inventory';
 foreach(['/admin/inventory','/admin/inventory/batches','/admin/inventory/receipts','/admin/inventory/adjustments','/admin/inventory/movements','/admin/inventory/settings','/admin/inventory/batches/'.$batch.'/edit'] as $path){$_SERVER['REQUEST_URI']=$path;ob_start();try{(new InventoryController($service))->handle(InventoryController::route($path),$admin);$html=ob_get_contents();}finally{ob_end_clean();}$check(strpos($html,'DHARMA CHEMICAL')!==false&&strpos($html,'<form')!==false,'Responsive screen rendered '.$path);}
 $_GET=['product_id'=>$product,'entry_type'=>'OPENING'];$_SERVER['REQUEST_URI']='/admin/inventory/receipts';http_response_code(200);ob_start();try{(new InventoryController($service))->handle(InventoryController::route('/admin/inventory/receipts'),$admin);$entryHtml=ob_get_contents();}finally{ob_end_clean();}
 $check(strpos($entryHtml,'Choose product and stock entry')!==false&&strpos($entryHtml,'Save Opening Stock')!==false,'Product-first opening form is visible');
 $check(strpos($entryHtml,'continue_receipt=1')!==false&&strpos($entryHtml,'+ Create New Batch')!==false,'New batch link returns to stock entry');
 $_GET=['product_id'=>$product,'continue_receipt'=>'1'];$_SERVER['REQUEST_URI']='/admin/inventory/batches';ob_start();try{(new InventoryController($service))->handle(InventoryController::route('/admin/inventory/batches'),$admin);$batchHtml=ob_get_contents();}finally{ob_end_clean();}
 $check(strpos($batchHtml,'name="continue_receipt" value="1"')!==false&&strpos($batchHtml,'value="'.$product.'" selected')!==false,'Batch creation preserves selected product and continuation');
 $_GET=[];$_SERVER['REQUEST_METHOD']='POST';$_POST=$receipt;http_response_code(200);ob_start();try{(new InventoryController($service))->handle(InventoryController::route('/api/v1/admin/inventory/receipts'),$admin);$body=ob_get_contents();}finally{ob_end_clean();}$check(http_response_code()===403&&strpos($body,'csrf')!==false,'Receipt endpoint CSRF enforced');
 $_POST=$receipt+['csrf_token'=>$_SESSION['csrf_token']];http_response_code(200);ob_start();try{(new InventoryController($service))->handle(InventoryController::route('/api/v1/admin/inventory/receipts'),$admin);$body=json_decode(ob_get_contents(),true);}finally{ob_end_clean();}$check(http_response_code()===200&&$body['replayed'],'Receipt controller replay preserves stock');
 $_POST=$opening+['entry_type'=>'OPENING','csrf_token'=>$_SESSION['csrf_token']];http_response_code(200);ob_start();try{(new InventoryController($service))->handle(InventoryController::route('/api/v1/admin/inventory/receipts'),$admin);$body=json_decode(ob_get_contents(),true);}finally{ob_end_clean();}$check(http_response_code()===200&&$body['replayed'],'Opening controller dispatch and replay');
 $uiReceipt=$receipt;$uiReceipt['request_id']=Inventory::uuid();$uiReceipt['product_id']=$product;$uiReceipt['batch_id']=$beyondId;$uiReceipt['quantity']='2';$_POST=$uiReceipt+['entry_type'=>'RECEIPT','csrf_token'=>$_SESSION['csrf_token']];http_response_code(200);
 ob_start();try{(new InventoryController($service))->handle(InventoryController::route('/admin/inventory/receipts'),$admin);}finally{ob_end_clean();}
 $check(http_response_code()===303&&isset($_SESSION['inventory_notice']),'Saved HTML receipt redirects with success notice');
 $_GET=['product_id'=>$product];$_POST=[];$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/admin/inventory';http_response_code(200);ob_start();try{(new InventoryController($service))->handle(InventoryController::route('/admin/inventory'),$admin);$updatedHtml=ob_get_contents();}finally{ob_end_clean();}
 $check(strpos($updatedHtml,'Stock received / adjusted.')!==false&&strpos($updatedHtml,'<td>2</td>')!==false&&strpos($updatedHtml,'+ Add Stock')!==false,'Dashboard immediately shows success, new quantity and Add Stock button');
 $check($service->reconciliation($admin)===[],'HTML receipt leaves stock and ledger consistent');
}finally{if($pdo->inTransaction())$pdo->rollBack();}
foreach($snapshot as $table=>$hash){$check(hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR))===$hash,'All original content preserved: '.$table);}
echo "$count inventory, expiry, idempotency, reconciliation, permissions and screen checks passed; all fixtures rolled back.\n";
