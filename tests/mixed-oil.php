<?php
declare(strict_types=1);
use App\Services\{Inventory,Promotions,Invoices,OfflineSales,MixedOil,InvoiceDocuments};
use App\Support\InventoryException;
if(PHP_SAPI!=='cli'||$p->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase9_test')throw new RuntimeException('Dedicated disposable sales test database required');
require_once __DIR__.'/offline-fixtures.php';
$n=0;$check=static function($ok,$label)use(&$n){if(!$ok)throw new RuntimeException($label);$n++;};
$reject=static function($fn,$label)use($check){try{$fn();}catch(InventoryException $e){$check(true,$label);return;}throw new RuntimeException('Unexpected acceptance: '.$label);};
$before=[];foreach($p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t)$before[$t]=hash('sha256',json_encode($p->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll()));
$p->beginTransaction();
try{
 $f=offlineFixtures($p);$admin=$f['actors']['admin'];$rep=$f['actors']['rep'];[$a,$b]=$f['products'];$promos=new Promotions($p);$invoices=new Invoices($p);
 $old=$promos->get($admin,$f['promotion']);$promos->save($admin,['request_id'=>Inventory::uuid(),'version'=>$old['version'],'is_active'=>'0'],$old['id'],true);
 $p->prepare('UPDATE products SET selling_price=?,version=version+1 WHERE id=?')->execute(['220.00',$a]);$p->prepare('UPDATE products SET selling_price=?,version=version+1 WHERE id=?')->execute(['100.00',$b]);
 $p->prepare('INSERT INTO products(sku,name,category_id,selling_price) SELECT ?,?,category_id,50 FROM products WHERE id=?')->execute(['MIX'.bin2hex(random_bytes(5)),'Test cheaper free oil',$a]);$c=$p->lastInsertId();
 (new App\Services\ProductStock($p))->post($admin,['request_id'=>Inventory::uuid(),'product_id'=>$c,'quantity'=>'10','selling_price'=>'50.00','product_version'=>'1']);
 (new App\Services\RepresentativeStock($p))->post($admin,['request_id'=>Inventory::uuid(),'rep_id'=>$rep['id'],'product_id'=>$c,'quantity'=>'1'],'ALLOCATE');
 $draft=['request_id'=>Inventory::uuid(),'name'=>'Isolated mixed oil','description'=>'Disposable test only','mixed_oil'=>'1','mixed_product_ids'=>[$a,$b,$c],'type'=>'BUY_X_GET_Y','buy_quantity'=>'6','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','is_active'=>'1','start_date'=>'','end_date'=>''];
 $saved=$promos->save($admin,$draft);$rule=$promos->get($admin,$saved['promotion_id']);$check($rule['mixed_policy']===MixedOil::POLICY&&$rule['mixed_product_ids']===[$a,$b,$c],'Explicit immutable group');
 $check($promos->quote($rep,$a,'12')['free_quantity']==='0','Individual mixed product cannot award free units');
 $check(App\Services\PromotionCalculator::calculate('220.00','12',$rule)['free_quantity']==='0','Pure single-line helper cannot duplicate mixed entitlement');
 $single=$draft;unset($single['mixed_oil'],$single['mixed_product_ids']);$single['product_id']=$b;$single['request_id']=Inventory::uuid();$reject(fn()=>$promos->save($admin,$single),'Single offer cannot overlap group member');
 $other=$draft;$other['request_id']=Inventory::uuid();$other['mixed_product_ids']=[$b,$c];$reject(fn()=>$promos->save($admin,$other),'Group overlap blocked');
 $input=static fn($items,$rewards=[],$source=null,$rate='0')=>['request_id'=>Inventory::uuid(),'customer_id'=>$f['customer'],'stock_source'=>$source??$rep['id'],'payment_type'=>'CASH','amount_received'=>'0.00','discount_policy'=>'INVOICE_PERCENT_V1','invoice_discount_percent'=>$rate,'items'=>$items,'oil_rewards'=>$rewards];
 $item=static fn($id,$q)=>['product_id'=>$id,'quantity'=>$q];
 foreach(['5'=>'0','6'=>'1','12'=>'2','18'=>'3'] as $q=>$free){$r=$invoices->review($rep,$input([$item($a,(string)$q)]));$check($r['oil_offers'][0]['earned']===$free,$q.' paid entitlement');}
 $mixed=$input([$item($a,'3'),$item($b,'3')],[$item($b,'1')]);$r=$invoices->review($rep,$mixed);$check($r['oil_offers'][0]['remaining']==='0'&&$r['lines'][1]['free_quantity']==='1','Mixed quantities earn selected cheaper oil');
 $reject(fn()=>$invoices->review($rep,$input([$item($a,'3'),$item($b,'3')],[$item($a,'1')])),'Cheapest group paid price caps reward');
 $reject(fn()=>$invoices->review($rep,$input([$item($a,'5')],[$item($b,'1')])),'Five paid plus a free selection never qualifies');
 $reject(fn()=>$invoices->review($rep,$input([$item($a,'6')],[$item($b,'2')])),'Extra reward rejected');
 $repeat=$input([$item($a,'6'),$item($b,'6')],[$item($a,'1'),$item($b,'1')]);$r=$invoices->review($rep,$repeat);$check($r['oil_offers'][0]['groups']===[['unit_price'=>'100.00','quantity'=>'1'],['unit_price'=>'220.00','quantity'=>'1']],'Repeated groups deterministic ascending prices');
 $reject(fn()=>$invoices->review($rep,$input([$item($a,'6'),$item($b,'6')],[$item($a,'2')])),'Two expensive rewards do not fit one cheap group');
 $reject(fn()=>$invoices->review($rep,$input([$item($a,'18')],[$item($c,'3')])),'Free-only reward stock shortage rejected');
 $r=$invoices->review($rep,$input([$item($a,'6')],[$item($c,'1')],null,'25'));$free=array_values(array_filter($r['lines'],fn($l)=>$l['product_id']===$c))[0];$check($free['paid_quantity']==='0'&&$free['free_quantity']==='1'&&$free['invoice_discount_amount']==='0.00'&&$free['payable_amount']==='0.00','Reward-only product has no payable or percentage discount');$check($r['grand_total']==='990.00','Percentage uses paid payable lines');
 $sale=$input([$item($a,'6')],[$item($c,'1')],null,'25');$review=$invoices->review($rep,$sale);$sale['review_hash']=$review['review_hash'];$issued=$invoices->issue($rep,$sale);$record=$invoices->get($rep,$issued['invoice_id']);$check(count($record['items'])===2,'Reward is immutable invoice product line');
 $documents=new InvoiceDocuments($p);$document=$documents->snapshot($rep,$issued['invoice_id']);
 foreach(['receipt','a4'] as $format){$html=InvoiceDocuments::render($document,$format,'/rep/invoices',false,'en');$check(str_contains($html,'Test cheaper free oil')&&str_contains($html,$format==='receipt'?'Free units':'Free qty'),'Free-only oil clearly present in '.$format);}
 $pdf=App\Support\LocalPdf::generate(InvoiceDocuments::render($document,'a4','/rep/invoices',true));$check(substr($pdf,0,5)==='%PDF-'&&str_contains(substr($pdf,-1024),'%%EOF'),'Mixed free oil A4 PDF generated locally');
 $check($p->query("SELECT quantity FROM representative_stock_balances WHERE rep_id={$rep['id']} AND product_id=$c")->fetchColumn()==0,'Correct representative reward stock deducted');
 $reject(fn()=>$invoices->review($rep,$input([$item($a,'6')],[$item($c,'1')])),'Zero remaining reward stock cannot be selected');
 $check($invoices->issue($rep,$sale)['replayed'],'Invoice retry posts exactly once');
 $void=['request_id'=>Inventory::uuid(),'reason'=>'Disposable mixed oil test'];$invoices->void($admin,$issued['invoice_id'],$void);$invoices->void($admin,$issued['invoice_id'],$void);$check($p->query("SELECT quantity FROM representative_stock_balances WHERE rep_id={$rep['id']} AND product_id=$c")->fetchColumn()==1,'Void restores reward stock once');
 $warehouse=$input([$item($a,'6')],[$item($c,'1')],'warehouse');$warehouse['review_hash']=$invoices->review($admin,$warehouse)['review_hash'];$warehouseInvoice=$invoices->issue($admin,$warehouse);$check($p->query("SELECT quantity FROM product_stock_balances WHERE product_id=$c")->fetchColumn()==8,'Warehouse reward deducted from warehouse');$invoices->void($admin,$warehouseInvoice['invoice_id'],['request_id'=>Inventory::uuid(),'reason'=>'Test warehouse reversal']);
 $missing=$input([$item($a,'6')]);$missing['review_hash']=$invoices->review($rep,$missing)['review_hash'];$reject(fn()=>$invoices->issue($rep,$missing),'Unselected reward cannot be issued');
 // Existing same-product offer remains independent for a nonmember.
 $p->prepare('INSERT INTO products(sku,name,category_id,selling_price) SELECT ?,?,category_id,10 FROM products WHERE id=?')->execute(['OTHER'.bin2hex(random_bytes(5)),'Other promotion product',$a]);$d=$p->lastInsertId();
 (new App\Services\ProductStock($p))->post($admin,['request_id'=>Inventory::uuid(),'product_id'=>$d,'quantity'=>'100','selling_price'=>'10.00','product_version'=>'1']);(new App\Services\RepresentativeStock($p))->post($admin,['request_id'=>Inventory::uuid(),'rep_id'=>$rep['id'],'product_id'=>$d,'quantity'=>'50'],'ALLOCATE');
 $legacy=$single;$legacy['product_id']=$d;$legacy['buy_quantity']='12';$legacy['free_quantity']='2';$legacy['request_id']=Inventory::uuid();$promos->save($admin,$legacy);
 $combo=$invoices->review($rep,$input([$item($a,'6'),$item($d,'12')],[$item($b,'1')]));$check($combo['oil_offers'][0]['earned']==='1'&&$combo['lines'][1]['free_quantity']==='2','Other offer free units never count toward oil entitlement');
 // Sign a real disposable offline grant and sale, then revalidate through the same posting engine.
 $key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1','config'=>'C:/xampp/apache/conf/openssl.cnf']);$public=preg_replace('/-----[^-]+-----|\s/','',openssl_pkey_get_details($key)['key']);
 $sign=static function(string $body)use($key){openssl_sign($body,$der,$key,OPENSSL_ALGO_SHA256);$pos=2;$out='';for($i=0;$i<2;$i++){$pos++;$len=ord($der[$pos++]);$part=substr($der,$pos,$len);$pos+=$len;$out.=str_pad(ltrim($part,"\0"),32,"\0",STR_PAD_LEFT);}return base64_encode($out);};
 $signed=static function($payload)use($sign){$body=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);return ['body'=>$body,'signature'=>$sign($body)];};
 $offline=new OfflineSales($p);$device=Inventory::uuid();$offline->register($rep,['device_id'=>$device,'label'=>'Mixed oil disposable','public_key'=>$public,'signature'=>$sign("REGISTER\n".$device."\n".$public)]);
 $reject(fn()=>$offline->bootstrap($rep,$signed(['action'=>'BOOTSTRAP','device_id'=>$device,'request_id'=>Inventory::uuid(),'pending_ids'=>[]])),'Older client must update before receiving mixed oil snapshot');
 $snap=$offline->bootstrap($rep,$signed(['action'=>'BOOTSTRAP','promotion_policies'=>[MixedOil::POLICY],'device_id'=>$device,'request_id'=>Inventory::uuid(),'pending_ids'=>[]]));
 $check(array_values(array_filter($snap['products'],fn($v)=>$v['id']===$a))[0]['rule']['mixed_policy']===MixedOil::POLICY,'Offline grant contains immutable group rule');
 $cases=[];foreach([$mixed,$repeat,$input([$item($a,'6')],[$item($c,'1')],null,'25')] as $case){$cases[]=['input'=>$case,'expected'=>$invoices->review($rep,$case)];}
 $oracle=APP_ROOT.'/storage/mixed-oil-oracle.json';file_put_contents($oracle,json_encode(['snapshot'=>$snap,'cases'=>$cases],JSON_THROW_ON_ERROR));
 $process=proc_open(['node',__DIR__.'/mixed-oil-oracle.js',$oracle],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);fclose($pipes[0]);$json=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('JavaScript oracle failed: '.$errors);$results=json_decode($json,true,512,JSON_THROW_ON_ERROR);$check(count($results)===3,'PHP and JS mixed parity cases');
 $sequence=0;$payload=static function($result)use(&$sequence,$device,$snap){unset($result['oil_offers'],$result['applied_amount'],$result['change_amount'],$result['outstanding']);return ['operation_id'=>Inventory::uuid(),'device_id'=>$device,'grant_id'=>$snap['grant_id'],'sequence'=>(string)++$sequence]+$result;};
 $off=$payload($results[2]);$envelope=$signed($off);$receipt=$offline->submit($rep,$envelope);$check($receipt['status']==='Synced','Signed mixed oil offline sale synchronized');$check($offline->submit($rep,$envelope)===$receipt,'Signed mixed offline replay exactly once');$check($p->query("SELECT body_json FROM offline_sale_payloads WHERE operation_id='{$off['operation_id']}'")->fetchColumn()===$envelope['body'],'Signed body retained byte for byte');
 $invoices->void($admin,$receipt['invoice_id'],['request_id'=>Inventory::uuid(),'reason'=>'Offline mixed reversal']);
 $tampered=$payload($results[0]);$tampered['oil_rewards']=[['product_id'=>$a,'quantity'=>'1']];$check($offline->submit($rep,$signed($tampered))['status']==='Needs Attention','Invalid free selection held without posting');
 $changed=$payload($results[0]);$p->prepare('UPDATE products SET selling_price=99,version=version+1 WHERE id=?')->execute([$b]);$e=$signed($changed);$check($offline->submit($rep,$e)['status']==='Needs Attention','Price change holds signed draft without repricing');$check($p->query("SELECT body_json FROM offline_sale_payloads WHERE operation_id='{$changed['operation_id']}'")->fetchColumn()===$e['body'],'Held signed body unchanged');
 $p->prepare('UPDATE products SET selling_price=100,version=version+1 WHERE id=?')->execute([$b]);
 $fresh=$offline->bootstrap($rep,$signed(['action'=>'BOOTSTRAP','promotion_policies'=>[MixedOil::POLICY],'device_id'=>$device,'request_id'=>Inventory::uuid(),'pending_ids'=>[]]));
 $stale=$payload($results[0]);$stale['grant_id']=$fresh['grant_id'];foreach($stale['items'] as &$line)foreach($fresh['products'] as $product)if($product['id']===$line['product_id'])$line['price_version']=$product['version'];unset($line);
 $revision=$promos->get($admin,$saved['promotion_id']);$edit=$draft;$edit['request_id']=Inventory::uuid();$edit['version']=$revision['version'];$edit['name']='Revised test oil rule';$promos->save($admin,$edit,$revision['id']);
 $staleReceipt=$offline->submit($rep,$signed($stale));$check($staleReceipt['status']==='Needs Attention'&&$staleReceipt['code']==='pricing','Changed promotion revision holds earlier signed grant with otherwise matching prices');
 $history=$promos->revisions($admin,$saved['promotion_id']);$check(count($history)===2&&$history[1]['mixed_product_ids']===[$a,$b,$c],'Earlier explicit eligibility snapshot remains immutable');
 $p->rollBack();
 foreach($before as $t=>$hash)$check(hash('sha256',json_encode($p->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll()))===$hash,'Preserved test table '.$t);
 echo "$n mixed oil rule, grouping, stock, discount, reversal, nonstacking, PHP/JS parity, signed sync and preservation checks passed; all fixtures rolled back.\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
