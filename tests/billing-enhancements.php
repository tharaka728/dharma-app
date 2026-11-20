<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';require_once __DIR__.'/offline-fixtures.php';
use App\Services\{InvoiceDiscount,Invoices,Inventory,Reports,InvoiceDocuments,PromotionCalculator as Money};
use App\Support\{Database,BusinessCode,InventoryException};
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='dharma_sales_phase9_test')throw new RuntimeException('Dedicated test database required.');
$pdo=Database::connect();$count=0;$check=static function($value,$label)use(&$count){if(!$value)throw new RuntimeException($label);$count++;};
foreach(['1'=>'P001','2'=>'P002','99'=>'P099','100'=>'P100','999'=>'P999','1000'=>'P1000'] as $number=>$expected)$check(BusinessCode::format('P',(string)$number)===$expected,'Short sequence '.$number);
foreach(['-1','100.01','',null,'1e2','12.555'] as $rate){try{InvoiceDiscount::rate($rate);throw new RuntimeException('Invalid percentage accepted');}catch(InventoryException $e){$check($e->status===422,'Invalid percentage rejected');}}
$tiny=[['payable_amount'=>'0.01','discount_amount'=>'0.00'],['payable_amount'=>'0.01','discount_amount'=>'0.00'],['payable_amount'=>'0.01','discount_amount'=>'0.00']];
$rounded=InvoiceDiscount::apply($tiny,'50');$check($rounded['invoice_discount_amount']==='0.02','Half-up invoice rounding');$check(array_column($rounded['lines'],'invoice_discount_amount')===['0.01','0.01','0.00'],'Largest remainder ties follow original line order');
$huge=InvoiceDiscount::apply([['payable_amount'=>'9999999999999.99','discount_amount'=>'0.00']],'12.5');$check($huge['invoice_discount_amount']==='1250000000000.00','Maximum money multiplication is exact');
$pdo->beginTransaction();
try{
 $f=offlineFixtures($pdo);$admin=$f['actors']['admin'];$rep=$f['actors']['rep'];$svc=new Invoices($pdo);
 $q=$pdo->prepare('SELECT r.code FROM business_code_registry r JOIN inventory_transactions t ON r.request_key=t.id WHERE r.prefix=0x53 AND t.created_by_user_id=? LIMIT 1');$q->execute([$admin['id']]);$stockCode=$q->fetchColumn();
 $history=new \App\Services\ProductStock($pdo);$check($history->history($admin,['q'=>$stockCode])['total']===1,'Stock history searches short references');$check($history->history($admin,['q'=>'zz-never-a-stock-code'])['total']===0,'History empty search is clear');
 $token=Inventory::uuid();$one=BusinessCode::next($pdo,'P',$token);$check(BusinessCode::next($pdo,'P',$token)===$one,'Submission token reserves one code');
 $check(BusinessCode::next($pdo,'P',Inventory::uuid())!==$one,'Next token receives another code');
 try{$pdo->prepare('DELETE FROM business_code_registry WHERE prefix=? AND request_key=?')->execute(['P',$token]);throw new RuntimeException('Code recycled');}catch(PDOException $e){$check((int)$e->errorInfo[1]===1644,'Reserved codes cannot be deleted');}
 $cases=['0'=>['0.00','251.00'],'25'=>['62.75','188.25'],'30'=>['75.30','175.70'],'35'=>['87.85','163.15'],'12.5'=>['31.38','219.62'],'100'=>['251.00','0.00']];
 foreach($cases as $rate=>[$amount,$total]){
  $input=['request_id'=>Inventory::uuid(),'customer_id'=>$f['customer'],'stock_source'=>(string)$rep['id'],'payment_type'=>'CASH','amount_received'=>'300.00','items'=>[['product_id'=>$f['products'][0],'quantity'=>'2']],'discount_policy'=>InvoiceDiscount::POLICY,'invoice_discount_percent'=>(string)$rate];
  $r=$svc->review($rep,$input);$check($r['invoice_discount_amount']===$amount&&$r['grand_total']===$total,'Rate '.$rate.' server total');$check($r['lines'][0]['free_quantity']==='1','Free stock preserved');
  $input['review_hash']=$r['review_hash'];$result=$svc->issue($rep,$input);$id=$result['invoice_id'];$record=$svc->get($rep,$id);
  $check($record['invoice_discount_amount']===$amount&&$record['grand_total']===$total,'Immutable snapshot '.$rate);$check($record['paid_amount']===$total&&$record['outstanding']==='0.00','Discounted payment applied once');
  $check($svc->issue($rep,$input)['replayed'],'Discount issue retry');
  $html=InvoiceDocuments::render($record,'receipt','/rep/invoices',false,'en');$check(str_contains($html,'Invoice Discount')&&str_contains($html,$record['invoice_discount_percent']),'Printed discount snapshot');
  $check($svc->get($rep,$id)['grand_total']===$total,'Printing does not post');if((string)$rate==='25'){$pdf=\App\Support\LocalPdf::generate(InvoiceDocuments::render($record,'a4','/rep/invoices',true));$check(str_starts_with($pdf,'%PDF-'),'Actual discounted invoice PDF generation');}

  try{$pdo->prepare('UPDATE invoice_discount_snapshots SET percent_rate=0 WHERE invoice_id=?')->execute([$id]);throw new RuntimeException('Snapshot editable');}catch(PDOException $e){$check((int)$e->errorInfo[1]===1644,'Discount snapshot immutable');}
  $void=['request_id'=>Inventory::uuid(),'reason'=>'Isolated discount verification reversal'];$svc->void($admin,$id,$void);$check($svc->get($admin,$id)['refund_due']===$total,'Refund liability uses actual discounted collection');$check($svc->void($admin,$id,$void)['replayed'],'Void exactly once');
 }
 $input['request_id']=Inventory::uuid();$input['invoice_discount_percent']='25';$input['amount_received']='50.00';unset($input['review_hash']);$r=$svc->review($rep,$input);$check($r['outstanding']==='138.25','Partial payment remains outstanding');$input['review_hash']=$r['review_hash'];$id=$svc->issue($rep,$input)['invoice_id'];
 $paid=$svc->pay($admin,$id,['request_id'=>Inventory::uuid(),'amount_received'=>'200.00']);$check($paid['applied_amount']==='138.25'&&$paid['change_amount']==='61.75','Later receipt uses discounted outstanding');$svc->void($admin,$id,['request_id'=>Inventory::uuid(),'reason'=>'Isolated test reversal after payment']);
 $customer=$pdo->query('SELECT version FROM customers WHERE id='.(int)$f['customer'])->fetchColumn();$svc->customerSettings($admin,['customer_id'=>$f['customer'],'version'=>(string)$customer,'assigned_rep_id'=>$rep['id'],'allow_credit_sales'=>'1']);
 $pdo->prepare('UPDATE customers SET credit_limit=150.00,credit_limit_enabled=1 WHERE id=?')->execute([$f['customer']]);
 $input['request_id']=Inventory::uuid();$input['payment_type']='CREDIT';$input['amount_received']='0.00';unset($input['review_hash']);try{$svc->review($rep,$input);throw new RuntimeException('Credit cap bypassed');}catch(InventoryException $e){$check($e->status===409,'Discounted credit still enforces limit');}
 $input['invoice_discount_percent']='50';$r=$svc->review($rep,$input);$check($r['grand_total']==='125.50','Discounted credit within headroom');$input['review_hash']=$r['review_hash'];$id=$svc->issue($rep,$input)['invoice_id'];$check($svc->get($rep,$id)['outstanding']==='125.50','Credit debt discounted');
 foreach((new Reports($pdo))->reconciliation($admin) as $label=>$violations)$check($violations===0,$label);
 $check((string)$pdo->query('SELECT quantity FROM representative_stock_balances WHERE rep_id='.(int)$rep['id'].' AND product_id='.(int)$f['products'][0])->fetchColumn()==='77','Stock depends on paid plus free units, not discount');
}finally{$pdo->rollBack();}
echo "$count short-code and invoice-discount financial/snapshot/credit/refund/reconciliation checks passed; fixtures rolled back.\n";
