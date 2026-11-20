<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/offline-fixtures.php';
use App\Services\{Invoices,InvoiceDocuments,Inventory};use App\Support\{Database,InventoryException,LocalPdf};use App\Controllers\InvoiceDocumentController;
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='dharma_sales_phase9_test')throw new RuntimeException('Dedicated test database required.');
$pdo=Database::connect();$f=offlineFixtures($pdo);$svc=new Invoices($pdo);$docs=new InvoiceDocuments($pdo);$count=0;
$check=static function($ok,$label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$names='සිංහල தமிழ் '.str_repeat('Long product <script>alert(1)</script> & ',4);
$pdo->prepare('UPDATE products SET name=? WHERE id=?')->execute([$names,$f['products'][0]]);
$pdo->prepare('UPDATE customers SET shop_name=?,address=?,allow_credit_sales=1 WHERE id=?')->execute(['සිංහල தமிழ் '.str_repeat('Long customer & ',8),"Snapshot address <img src=https://invalid.example/secret>\nColombo",$f['customer']]);
$issue=static function($type,$cash)use($svc,$f){$input=['request_id'=>Inventory::uuid(),'customer_id'=>$f['customer'],'stock_source'=>$f['actors']['rep']['id'],'payment_type'=>$type,'amount_received'=>$cash,'items'=>[['product_id'=>$f['products'][0],'quantity'=>'2']]];$input['review_hash']=$svc->review($f['actors']['rep'],$input)['review_hash'];return $svc->issue($f['actors']['rep'],$input)['invoice_id'];};
$id=$issue('CASH','50.00');$creditId=$issue('CREDIT','0.00');$voidId=$issue('CASH','300.00');$svc->void($f['actors']['admin'],$voidId,['request_id'=>Inventory::uuid(),'reason'=>'Isolated document test void']);
$pdo->prepare('UPDATE products SET name=?,selling_price=777,version=version+1 WHERE id=?')->execute(['Changed current catalogue',$f['products'][0]]);
$pdo->prepare('UPDATE customers SET address=?,version=version+1 WHERE id=?')->execute(['Changed current address',$f['customer']]);
$hashes=static function()use($pdo){$out=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t)$out[$t]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));return $out;};$before=$hashes();
$record=$docs->snapshot($f['actors']['rep'],$id);$record['document_locale']='en';$a4=InvoiceDocuments::render($record,'a4','/rep/invoices');$receipt=InvoiceDocuments::render($record,'receipt','/rep/invoices');
$check($record['items'][0]['unit_price']==='125.50'&&$record['items'][0]['product_name_snapshot']===$names,'Immutable price and name');
$check(str_contains($a4,'Snapshot address')&&!str_contains($a4,'Changed current'),'Immutable customer address');
$check(str_contains($a4,'&lt;script&gt;')&&!str_contains($a4,'<script>alert'),'Escaped document content');
$check(str_contains($a4,'&lt;img')&&!str_contains($a4,'<img src='),'No customer controlled resources');
$check($record['items'][0]['free_quantity']==1&&str_contains($receipt,'Free units'),'Free quantity snapshot');
$check($record['outstanding']==='201.00'&&str_contains($a4,'201.00')&&str_contains($a4,'PARTIAL'),'Partial cash payment');
$check(str_contains(InvoiceDocuments::render($docs->snapshot($f['actors']['rep'],$creditId),'a4','/rep/invoices',false,'en'),'CREDIT'),'Credit display');
$void=InvoiceDocuments::render($docs->snapshot($f['actors']['admin'],$voidId),'a4','/admin/invoices',false,'en');$check(str_contains($void,'VOID — NOT VALID FOR PAYMENT')&&str_contains($void,'251.00')&&str_contains($void,'Refund Due'),'Void and received liability');
foreach(['receipt','a4','pdf'] as $format){$route=InvoiceDocumentController::route('/rep/invoices/'.$id.'/'.$format);$check($route['methods']===['GET']&&$route['role']==='SALES_REP','Read-only scoped route '.$format);}
foreach([$f['actors']['other']] as $actor){try{$docs->snapshot($actor,$id);throw new RuntimeException('Unauthorized document accepted');}catch(InventoryException $e){$check($e->status===404,'Other rep denied');}}
$check(InvoiceDocuments::money('9999999999999.99')==='9,999,999,999,999.99','Maximum decimal money without floats');
$check(!preg_match('/[^A-Za-z0-9_.-]/',InvoiceDocuments::filename(['invoice_number'=>'../bad"/number'])),'Safe filename');
$pdf=LocalPdf::generate(InvoiceDocuments::render($record,'a4','/rep/invoices',true));
$check(substr($pdf,0,5)==='%PDF-'&&str_contains(substr($pdf,-1024),'%%EOF'),'Valid PDF envelope');
$check(str_contains($pdf,'/MediaBox [0 0 594.95996 841.91998]')||preg_match('~/MediaBox\s*\[0 0 59[45][^\]]+84[12]~',$pdf),'A4 PDF media box');
$check(str_contains($pdf,'/ToUnicode')&&str_contains($pdf,'/FontFile2'),'Embedded Unicode font mapping');
$check(str_contains($pdf,'Nirmala'),'Sinhala/Tamil Windows font embedded');
$check(str_contains($pdf,'/Type /Catalog')&&preg_match('/startxref\s+[0-9]+\s+%%EOF/',$pdf),'PDF catalog and cross-reference trailer');
$long=$record;$long['items']=array_fill(0,100,$record['items'][0]);$longHtml=InvoiceDocuments::render($long,'a4','/rep/invoices',true);$longPdf=LocalPdf::generate($longHtml);
$check(preg_match_all('~/Type /Page\b~',$longPdf)>2,'Large invoice paginates');
for($i=0;$i<3;$i++){$docs->snapshot($f['actors']['admin'],$id);InvoiceDocuments::render($record,'receipt','/admin/invoices');}
$check($hashes()===$before,'All tables unchanged by repeated snapshots, HTML and PDF generation');
// A current assignment change must revoke access to existing document snapshots.
$pdo->prepare('UPDATE customers SET assigned_rep_id=? WHERE id=?')->execute([$f['actors']['other']['id'],$f['customer']]);
try{$docs->snapshot($f['actors']['rep'],$id);throw new RuntimeException('Reassigned invoice exposed');}catch(InventoryException $e){$check($e->status===404,'Document access follows current customer assignment');}
$pdo->prepare('UPDATE customers SET assigned_rep_id=? WHERE id=?')->execute([$f['actors']['rep']['id'],$f['customer']]);
// Document output also preserves an actual posted decimal discount, not a quote.
(new App\Services\Promotions($pdo))->save($f['actors']['admin'],['request_id'=>Inventory::uuid(),'name'=>'Document precision fixture','description'=>'Isolated test only','product_id'=>$f['products'][1],'type'=>'PERCENTAGE','buy_quantity'=>'','free_quantity'=>'','percent_rate'=>'10.00','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
$discountInput=['request_id'=>Inventory::uuid(),'customer_id'=>$f['customer'],'stock_source'=>$f['actors']['rep']['id'],'payment_type'=>'CASH','amount_received'=>'0.00','items'=>[['product_id'=>$f['products'][1],'quantity'=>'1']]];
$discountInput['review_hash']=$svc->review($f['actors']['rep'],$discountInput)['review_hash'];$discountId=$svc->issue($f['actors']['rep'],$discountInput)['invoice_id'];$discountRecord=$docs->snapshot($f['actors']['rep'],$discountId);
$check($discountRecord['discount_total']==='0.01'&&$discountRecord['grand_total']==='0.04'&&str_contains(InvoiceDocuments::render($discountRecord,'a4','/rep/invoices'),'0.01'),'Posted discount and half-cent rounding displayed unchanged');
$before=$hashes();
// Real browser HTTP authorization/layout/print checks, exclusively on an ephemeral test server.
$listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($listener,false);fclose($listener);$base='http://'.$address;$pipes=[];
$server=proc_open([PHP_BINARY,'-S',$address,'-t',APP_ROOT.'/public',APP_ROOT.'/tests/http-router.php'],[0=>['pipe','r'],1=>['file',APP_ROOT.'/storage/phase10-test-server.log','a'],2=>['file',APP_ROOT.'/storage/phase10-test-server.log','a']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);fclose($pipes[0]);
try{
 usleep(500000);$pipes=[];$node=proc_open(['C:/nvm4w/nodejs/node.exe',APP_ROOT.'/tests/invoice-documents-browser.js'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);
 fwrite($pipes[0],json_encode(['base'=>$base,'actors'=>$f['actors'],'password'=>$f['password'],'id'=>$id,'credit_id'=>$creditId,'void_id'=>$voidId],JSON_THROW_ON_ERROR));fclose($pipes[0]);echo stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$check(proc_close($node)===0,'Browser checks: '.$error);
}finally{proc_terminate($server);proc_close($server);}
// Authentication deliberately changes session/audit tables; financial/stock tables must not change.
$after=$hashes();foreach(['invoices','invoice_items','invoice_receipts','payments','payment_allocations','invoice_voids','stock_movements','stock_transactions','central_stock_balances','representative_stock_balances'] as $t)if(isset($before[$t]))$check($before[$t]===$after[$t],'HTTP prints preserve '.$t);
echo "$count invoice document checks passed. Synthetic records exist only in dharma_sales_phase9_test.\n";
