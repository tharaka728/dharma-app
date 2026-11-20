<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/offline-fixtures.php';
use App\Services\{Reports,Invoices,Inventory};use App\Support\{Database,InventoryException};
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='dharma_sales_phase9_test')throw new RuntimeException('Dedicated isolated test database required.');
$pdo=Database::connect();$f=offlineFixtures($pdo);$svc=new Reports($pdo);$invoices=new Invoices($pdo);$admin=$f['actors']['admin'];$rep=$f['actors']['rep'];$other=$f['actors']['other'];$count=0;
$check=static function($ok,$label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$reject=static function($fn,$status)use($check){try{$fn();throw new RuntimeException('Rejected request was accepted');}catch(InventoryException $e){$check($e->status===$status,'Rejected scoped/invalid report');}};
$pdo->prepare('UPDATE customers SET allow_credit_sales=1,credit_terms_days=0 WHERE id=?')->execute([$f['customer']]);
$pdo->prepare('UPDATE customers SET shop_name=? WHERE id=?')->execute(['=SUM(1,1)',$f['customer']]);
$issue=static function($type,$cash,$items)use($invoices,$rep,$f){$input=['request_id'=>Inventory::uuid(),'customer_id'=>$f['customer'],'stock_source'=>$rep['id'],'payment_type'=>$type,'amount_received'=>$cash,'items'=>$items];$input['review_hash']=$invoices->review($rep,$input)['review_hash'];return $invoices->issue($rep,$input)['invoice_id'];};
$cashId=$issue('CASH','50.00',[['product_id'=>$f['products'][0],'quantity'=>'2'],['product_id'=>$f['products'][1],'quantity'=>'3']]);
$creditId=$issue('CREDIT','0.00',[['product_id'=>$f['products'][0],'quantity'=>'1']]);
$voidId=$issue('CASH','200.00',[['product_id'=>$f['products'][0],'quantity'=>'1']]);$invoices->void($admin,$voidId,['request_id'=>Inventory::uuid(),'reason'=>'Isolated reporting void']);
$filters=['rep_id'=>(string)$rep['id'],'period'=>'today'];
$hashes=static function()use($pdo){$out=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table)$out[$table]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$table.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));return $out;};$before=$hashes();
foreach($svc->availableTypes() as $type=>$label){$r=$svc->report($admin,$type,$filters);$check(isset($r['rows'],$r['columns'],$r['notes']),'Report executes '.$type);$csv=$svc->report($admin,$type,$filters,true);$check($type==='overview'?$csv['total']===count($r['cards'])+1:$csv['total']===$r['total'],'CSV same authorized cohort '.$type);}
$r=$svc->report($admin,'overview',$filters);$cards=$r['cards'];$check($cards['Period gross issued sales · LKR']==='376.65'&&$cards['Period net sales · LKR']==='376.65','Void excluded, both invoice types included');
$check($cards['Period posted collections · LKR']==='175.50','Applied partial plus void payment; change excluded');$check($cards['Current Refund Due · LKR']==='125.50','Current refund liability');$check($cards['Current outstanding · LKR']==='326.65','Outstanding includes cash partial and credit');
$sales=$svc->report($rep,'sales',['period'=>'today']);$check(count($sales['rows'])===1&&$sales['rows'][0]['void_count']==='1'&&$sales['rows'][0]['paid_units']==='6'&&$sales['rows'][0]['free_units']==='1','Paid/free and void audit counts');
$whole=$svc->report($admin,'overview',$filters+['product_id'=>(string)$f['products'][1]]);$check($whole['cards']['Period net sales · LKR']==='251.15','Product filter uses whole invoice');
$lines=$svc->report($rep,'products',['period'=>'today','product_id'=>(string)$f['products'][1]]);$check(count($lines['rows'])===1&&$lines['rows'][0]['net_lkr']==='0.15'&&$lines['rows'][0]['paid_units']==='3','Product sales use matching lines only');
$collections=$svc->report($rep,'collections',['period'=>'today']);$check(count($collections['rows'])===2&&in_array('VOIDED',array_column($collections['rows'],'validity'),true),'Voided cash collection clearly retained');
foreach(['day','customer','collector'] as $group){$grouped=$svc->report($rep,'collections',['period'=>'today','collection_group'=>$group]);$check(count($grouped['rows'])===1&&$grouped['rows'][0]['gross_posted_collections_lkr']==='175.50'&&$grouped['rows'][0]['collected_on_voided_invoices_lkr']==='125.50','Collections grouping '.$group);}
$outstanding=$svc->report($rep,'outstanding',['period'=>'custom','from'=>'1990-01-01','to'=>'1990-01-02']);$check(count($outstanding['rows'])===2,'Current outstanding ignores historical period');
$check(array_values(array_unique(array_map('strval',array_column($outstanding['rows'],'overdue_days'))))===['0'],'Due today is not overdue');
$refunds=$svc->report($rep,'refunds',['period'=>'yesterday']);$check(count($refunds['rows'])===1&&str_contains($refunds['rows'][0]['actual_refunds'],'Unavailable'),'Current refund snapshot not inferred payment');
$stock=$svc->report($rep,'inventory',['period'=>'today']);$check(count($stock['rows'])===2&&!in_array('Warehouse',array_column($stock['rows'],'location'),true),'Rep stock excludes warehouse');
$check(!str_contains(json_encode($stock),'cost_price'),'No costs in representative reports');
$empty=$svc->report($other,'overview',['period'=>'today']);$check($empty['rows']===[]&&$empty['cards']['Period net sales · LKR']==='0.00','Empty representative reporting is truthful');
$reject(fn()=>$svc->report($rep,'overview',['rep_id'=>(string)$other['id']]),403);$reject(fn()=>$svc->report($rep,'reconciliation',[]),403);
foreach([['period'=>'custom','from'=>'2026-02-30','to'=>'2026-03-02'],['period'=>'custom','from'=>'2026-11-09','to'=>'2026-11-01'],['period'=>'custom','from'=>[],'to'=>'2026-11-09']] as $bad)$reject(fn()=>$svc->report($admin,'sales',$bad),422);
foreach(['today'=>['2026-11-09','2026-11-09'],'yesterday'=>['2026-11-08','2026-11-08'],'week'=>['2026-11-09','2026-11-15'],'month'=>['2026-11-01','2026-11-30']] as $period=>$expected){$range=Reports::range($period,'','',new DateTimeImmutable('2026-11-08T18:30:00Z'));$check(array_values($range)===$expected,'Colombo range '.$period);}
$week=Reports::range('week','','',new DateTimeImmutable('2026-11-08T17:30:00Z'));$check($week['from']==='2026-11-02'&&$week['to']==='2026-11-08','Sunday belongs to previous Monday week');
$reconcile=$svc->report($admin,'reconciliation',[]);foreach($reconcile['rows'] as $row)$check($row['result']==='PASS','Ledger integrity '.$row['check']);
$pdo->beginTransaction();
try{
 // Append synthetic historical source snapshots inside this disposable test
 // transaction only. Never update immutable invoices, disable triggers or commit.
 $insert=static function(string $table,array $row)use($pdo){$columns=array_keys($row);$pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')')->execute(array_values($row));};
 $header=$pdo->query("SELECT * FROM invoices WHERE id='$cashId'")->fetch();$detail=$pdo->query("SELECT * FROM online_invoice_details WHERE invoice_id='$cashId'")->fetch();$items=$pdo->query("SELECT * FROM invoice_items WHERE invoice_id='$cashId'")->fetchAll();$historical=[];
 foreach(['1990-01-01','1990-01-07','1990-02-01'] as $ix=>$day){$clone=$header;$clone['id']=Inventory::uuid();$clone['invoice_number']='REPORT-TEST-'.$clone['id'];$clone['sequence_number']=(string)(1000+$ix);$clone['business_date']=$day;$insert('invoices',$clone);$d=$detail;$d['invoice_id']=$clone['id'];$d['due_date']=$day;$insert('online_invoice_details',$d);foreach($items as $item){unset($item['id']);$item['invoice_id']=$clone['id'];$insert('invoice_items',$item);}$historical[]=$clone['id'];}
 $historicalFilters=$filters;$historicalFilters['period']='custom';$historicalFilters['from']='1990-01-01';$historicalFilters['to']='1990-02-01';
 $daily=$svc->report($rep,'sales',$historicalFilters+['group'=>'day']);$weekly=$svc->report($rep,'sales',$historicalFilters+['group'=>'week']);$monthly=$svc->report($rep,'sales',$historicalFilters+['group'=>'month']);
 $check(count($daily['rows'])===3,'Historical daily buckets');$check(count($weekly['rows'])===2&&$weekly['rows'][1]['net_lkr']==='502.30','Monday-to-Sunday weekly sums');$check(count($monthly['rows'])===2&&$monthly['rows'][1]['net_lkr']==='502.30','Monthly exact sums');
 $oldDebt=$svc->report($rep,'outstanding',$filters);$oldRows=array_values(array_filter($oldDebt['rows'],fn($r)=>$r['business_date']==='1990-01-01'));$check(count($oldRows)===1&&(int)$oldRows[0]['overdue_days']>10000&&$oldRows[0]['outstanding_lkr']==='251.15','Overdue days and amount use current Colombo date');
 // Posting just before and at Colombo midnight belongs to different local days,
 // irrespective of the old invoice business date. No receipt/stock is posted.
 foreach(['1990-02-01 18:29:59','1990-02-01 18:30:00'] as $stamp){$payment=Inventory::uuid();$pdo->prepare("INSERT INTO payments(id,customer_id,received_by_user_id,method,amount,received_at_client_utc,accepted_at_utc,status) VALUES (?,?,?,'CASH','1.00',?,?,'POSTED')")->execute([$payment,$f['customer'],$rep['id'],$stamp,$stamp]);$pdo->prepare('INSERT INTO payment_allocations(payment_id,invoice_id,customer_id,amount) VALUES (?,?,?,1.00)')->execute([$payment,$historical[0],$f['customer']]);}
 foreach(['1990-02-01','1990-02-02'] as $day){$c=$svc->report($rep,'collections',['period'=>'custom','from'=>$day,'to'=>$day]);$check(count($c['rows'])===1&&$c['rows'][0]['applied_collection_lkr']==='1.00','Collections use local posting midnight '.$day);}
 // Server-known pending/conflicted payloads never enter sales or collections.
 $device=Inventory::uuid();$pdo->prepare("INSERT INTO devices(id,user_id,label) VALUES (?,?,'Reporting test device')")->execute([$device,$rep['id']]);$operation=Inventory::uuid();
 $pdo->prepare("INSERT INTO sync_operations(id,user_id,device_id,operation_type,payload_schema_version,canonical_payload_hash,result_status,received_at_utc,last_attempt_at_utc,last_error_code) VALUES (?,?,?,'SALE_CREATE',1,?,'RECEIVED',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),'pricing')")->execute([$operation,$rep['id'],$device,str_repeat('a',64)]);
 $body=json_encode(['customer_id'=>(string)$f['customer'],'items'=>[['product_id'=>(string)$f['products'][0]]],'amount_received'=>'999.99']);$pdo->prepare('INSERT INTO offline_sale_payloads(operation_id,device_id,sequence_number,body_json,signature) VALUES (?,?,1,?,?)')->execute([$operation,$device,$body,'Isolated non-posting fixture']);
 $off=$svc->report($rep,'offline',['period'=>'today','product_id'=>(string)$f['products'][0]]);$check(count($off['rows'])===1&&$off['rows'][0]['last_error_code']==='pricing','Pending/conflict product filtering');
 $performance=$svc->report($rep,'representatives',['period'=>'today']);$check((string)$performance['rows'][0]['period_sync_conflicts']==='1','Representative sync-conflict attribution');
 $check($svc->report($rep,'overview',['period'=>'today'])['cards']['Period net sales · LKR']==='376.65','Provisional cash/sales excluded from finalized revenue');
}finally{$pdo->rollBack();}
$pdo->beginTransaction();
try{
 $pdo->prepare('UPDATE customers SET credit_limit_enabled=1,credit_limit=1000.00 WHERE id=?')->execute([$f['customer']]);
 $creditUsage=$svc->report($admin,'customers',$filters+['product_id'=>(string)$f['products'][1]]);
 $check($creditUsage['rows'][0]['company_credit_used_lkr']==='326.65'&&$creditUsage['rows'][0]['company_credit_remaining_lkr']==='673.35','Credit usage uses all current customer debt despite product cohort');
 $pdo->prepare('UPDATE customers SET assigned_rep_id=? WHERE id=?')->execute([$other['id'],$f['customer']]);
 $check($svc->report($rep,'sales',['period'=>'today'])['rows']===[],'Reassignment removes historical financial access');
 $check($svc->report($other,'sales',['period'=>'today'])['rows']===[],'New assignee cannot read previous representative invoices');
 $pdo->prepare('UPDATE customers SET assigned_rep_id=? WHERE id=?')->execute([$rep['id'],$f['customer']]);
 for($ix=0;$ix<55;$ix++){
  $clone=$header;$clone['id']=Inventory::uuid();$clone['invoice_number']='REPORT-PAGE-'.$clone['id'];$clone['sequence_number']=(string)(2000+$ix);$clone['business_date']=(new DateTimeImmutable('1991-01-01'))->modify('+'.$ix.' days')->format('Y-m-d');$insert('invoices',$clone);
  $d=$detail;$d['invoice_id']=$clone['id'];$insert('online_invoice_details',$d);foreach($items as $item){unset($item['id']);$item['invoice_id']=$clone['id'];$insert('invoice_items',$item);}
 }
 $paged=['period'=>'custom','from'=>'1991-01-01','to'=>'1991-03-01','product_id'=>(string)$f['products'][0]];
 $first=$svc->report($rep,'sales',$paged);$second=$svc->report($rep,'sales',$paged+['page'=>'2']);$all=$svc->report($rep,'sales',$paged,true);
 $check($first['total']===55&&count($first['rows'])===50&&count($second['rows'])===5,'Pagination preserves scoped filtered totals');$check(count($all['rows'])===55,'CSV exports all pages');
}finally{$pdo->rollBack();}
$check($hashes()===$before,'Every table unchanged by report and CSV queries');
echo "$count reporting calculation, scope, filter, snapshot, reconciliation and no-write checks passed. Synthetic fixtures only in isolated test database.\n";
