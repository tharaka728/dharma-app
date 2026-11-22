<?php
declare(strict_types=1);
// Owner release blocker: paid quantity must survive every benefit-mode switch.
// Quantity-first regression matrix (spec section 6) plus the isolated financial
// posting verification (spec section 7). Monetary totals alone never pass a check.
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Invoices,Inventory,ProductStock,RepresentativeStock,Promotions,BenefitPolicy};
use App\Support\{Database,InventoryException};
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_NAME')!=='dharma_sales_phase9_test'||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309')throw new RuntimeException('Exact isolated test target required.');
if(getenv('DHARMA_BENEFIT_TEST')!=='1')throw new RuntimeException('Isolated benefit gate flag required.');
$pdo=Database::connect();$svc=new Invoices($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$before=[];foreach($tables as $t)$before[$t]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));
$pdo->beginTransaction();
try{
 $tag='QTY'.strtoupper(bin2hex(random_bytes(5)));
 $actors=[];foreach(['admin'=>'ADMIN','rep'=>'SALES_REP'] as $key=>$role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Quantity fixture',$tag.$key,password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT),$role]);$actors[$key]=['id'=>$pdo->lastInsertId()];}
 $admin=['id'=>$actors['admin']['id'],'role'=>'ADMIN','name'=>'Quantity fixture admin'];$rep=['id'=>$actors['rep']['id'],'role'=>'SALES_REP','name'=>'Quantity fixture rep'];
 putenv('DHARMA_BENEFIT_TEST_PILOT_IDS='.$rep['id']);putenv('DHARMA_BENEFIT_TEST_POSTING_HOLD=0');
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$tag]);$cat=$pdo->lastInsertId();
 $products=[];foreach([['102.00','Suwada mirror'],['140.00','Glucose mirror'],['50.00','Plain mirror']] as $ix=>[$price,$name]){$pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)')->execute([$tag.$ix,$name,$cat,$price]);$products[]=$pdo->lastInsertId();}
 [$pA,$pB,$pC]=$products;
 foreach($products as $ix=>$p)(new ProductStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'product_id'=>$p,'quantity'=>'500','selling_price'=>['102.00','140.00','50.00'][$ix],'product_version'=>'1']);
 foreach($products as $p)(new RepresentativeStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'rep_id'=>$rep['id'],'product_id'=>$p,'quantity'=>'400'],'ALLOCATE');
 $pdo->prepare("INSERT INTO customers(customer_code,shop_name,contact_person,phone,address,area,assigned_rep_id,credit_terms_days,credit_limit_enabled) VALUES (?,'Quantity customer','Fixture','0110000000','Quantity address','',?,30,0)")->execute([$tag,$rep['id']]);$customer=$pdo->lastInsertId();
 $promos=new Promotions($pdo);
 $promos->save($admin,['request_id'=>Inventory::uuid(),'name'=>'QTY Buy12Get1','description'=>'','product_id'=>$pA,'type'=>'BUY_X_GET_Y','buy_quantity'=>'12','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $promos->save($admin,['request_id'=>Inventory::uuid(),'name'=>'QTY Buy6Get1','description'=>'','product_id'=>$pB,'type'=>'BUY_X_GET_Y','buy_quantity'=>'6','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $base=['customer_id'=>$customer,'stock_source'=>$rep['id'],'payment_type'=>'CASH','amount_received'=>'100000.00'];
 $benefit=static function(string $mode,string $choice='25',?string $percent=null)use($base):array{$in=$base;$in['request_id']=Inventory::uuid();$in['benefit_policy']='PROMO_CHOICE_V1';$in['benefit_mode']=$mode;$in['promotion_discount_choice']=$choice;if($percent!==null)$in['promotion_discount_percent']=$percent;return $in;};
 $cents=static fn(string $m):string=>\App\Services\PromotionCalculator::cents($m);
 $money=static fn(string $c):string=>\App\Services\PromotionCalculator::money($c);
 $expectedDiscount=static function(string $eligible,string $rate)use($cents,$money):string{$base=$cents($eligible);$rc=$cents($rate);return $money(\App\Services\InvoiceDiscount::divide(\App\Services\PromotionCalculator::add(\App\Services\PromotionCalculator::multiply($base,$rc),'5000'),'10000')[0]);};
 $lineOf=static function(array $r,$pid):array{foreach($r['lines'] as $l)if((string)$l['product_id']===(string)$pid)return $l;throw new RuntimeException('line missing '.$pid);};
 $assertLine=static function(array $l,string $paid,string $free,string $unit,string $gross,string $discount,string $payable,string $stock,string $label)use($check){
  $check($l['paid_quantity']===$paid,$label.': paid quantity stays '.$paid.' (got '.$l['paid_quantity'].')');
  $check($l['free_quantity']===$free,$label.': free quantity '.$free.' (got '.$l['free_quantity'].')');
  $check($l['unit_price']===$unit,$label.': unit price '.$unit);
  $check($l['gross_amount']===$gross,$label.': gross '.$gross.' (got '.$l['gross_amount'].')');
  $check($l['discount_amount']===$discount,$label.': discount '.$discount.' (got '.$l['discount_amount'].')');
  $check($l['payable_amount']===$payable,$label.': net '.$payable.' (got '.$l['payable_amount'].')');
  $check($l['required_stock']===$stock,$label.': physical stock demand '.$stock.' (got '.$l['required_stock'].')');
 };
 $oneItem=static fn($pid,string $q):array=>[['product_id'=>$pid,'quantity'=>$q]];
 $grossOf=static fn(string $price,string $q):string=>$money(\App\Services\PromotionCalculator::multiply($cents($price),$q));

 // Section 6 matrix: product A (102.00, Buy 12 Get 1) across quantities and rates.
 foreach(['6','11','12','13','24'] as $q){
  $gross=$grossOf('102.00',$q);$freeA=(string)intdiv((int)$q,12);$eligible=((int)$q>=12);
  $r=$svc->review($rep,$benefit('FREE_PROMOTION','0')+['items'=>$oneItem($pA,$q)]);
  $assertLine($lineOf($r,$pA),$q,$freeA,'102.00',$gross,'0.00',$gross,(string)((int)$q+(int)$freeA),'FREE q='.$q);
  foreach(['25','30','35'] as $rate){
   $r=$svc->review($rep,$benefit('PROMOTION_DISCOUNT_INSTEAD',$rate)+['items'=>$oneItem($pA,$q)]);
   $disc=$eligible?$expectedDiscount($gross,$rate.'.00'):'0.00';
   $assertLine($lineOf($r,$pA),$q,'0','102.00',$gross,$disc,$money(\App\Services\PromotionCalculator::subtract($cents($gross),$cents($disc))),$q,'PROMO '.$rate.'% q='.$q);
   $check($r['benefit_eligible_subtotal']===($eligible?$gross:'0.00'),'PROMO '.$rate.'% q='.$q.': eligible subtotal reflects threshold');
  }
  foreach([['27.5','27.50'],['100','100.00']] as [$custom,$rateStr]){
   $r=$svc->review($rep,$benefit('PROMOTION_DISCOUNT_INSTEAD','custom',$custom)+['items'=>$oneItem($pA,$q)]);
   $disc=$eligible?$expectedDiscount($gross,$rateStr):'0.00';
   $assertLine($lineOf($r,$pA),$q,'0','102.00',$gross,$disc,$money(\App\Services\PromotionCalculator::subtract($cents($gross),$cents($disc))),$q,'PROMO custom '.$custom.'% q='.$q);
  }
  foreach([['0','0.00'],['25','25.00'],['custom','100.00']] as [$choiceO,$rateStrO]){
   $r=$svc->review($rep,$benefit('ORDINARY_INVOICE_DISCOUNT',$choiceO,$choiceO==='custom'?'100':null)+['items'=>$oneItem($pA,$q)]);
   $disc=$expectedDiscount($gross,$rateStrO);
   $assertLine($lineOf($r,$pA),$q,'0','102.00',$gross,$disc,$money(\App\Services\PromotionCalculator::subtract($cents($gross),$cents($disc))),$q,'ORDINARY '.$rateStrO.'% q='.$q);
  }
  // Full transition chain on the same cart: FREE -> PROMO -> ORDINARY -> PROMO -> FREE.
  $r=$svc->review($rep,$benefit('FREE_PROMOTION','0')+['items'=>$oneItem($pA,$q)]);
  $assertLine($lineOf($r,$pA),$q,$freeA,'102.00',$gross,'0.00',$gross,(string)((int)$q+(int)$freeA),'chain end FREE q='.$q);
 }
 // Even a 100% monetary discount never reduces the paid quantity to zero.
 $r=$svc->review($rep,$benefit('PROMOTION_DISCOUNT_INSTEAD','custom','100')+['items'=>$oneItem($pA,'12')]);
 $assertLine($lineOf($r,$pA),'12','0','102.00','1224.00','1224.00','0.00','12','PROMO 100% q=12');
 // Below-threshold quantity stays ineligible for the alternative discount (6 < 12).
 $r=$svc->review($rep,$benefit('PROMOTION_DISCOUNT_INSTEAD','25')+['items'=>$oneItem($pA,'6')]);
 $check($lineOf($r,$pA)['benefit_eligible']===false&&$lineOf($r,$pA)['benefit_eligibility_reason']==='BELOW_THRESHOLD','Below-threshold line stays ineligible in PROMO mode');
 // Percentage change without mode switching preserves quantities.
 foreach(['25','30','35'] as $rate){$r=$svc->review($rep,$benefit('PROMOTION_DISCOUNT_INSTEAD',$rate)+['items'=>$oneItem($pA,'24')]);$check($lineOf($r,$pA)['paid_quantity']==='24','Rate change to '.$rate.'% keeps paid 24');}

 // Section 7: isolated financial posting of the two-product example at 25%.
 $in=$benefit('PROMOTION_DISCOUNT_INSTEAD','25')+['items'=>[$oneItem($pA,'12')[0],$oneItem($pB,'12')[0]]];
 $r=$svc->review($rep,$in);
 $check($r['benefit_eligible_subtotal']==='2904.00'&&$r['benefit_discount_amount']==='726.00'&&$r['grand_total']==='2178.00','Two-product totals: eligible 2904.00, discount 726.00, payable 2178.00');
 $check($r['benefit_suppressed_free_quantity']==='3','Suppressed free units total 3');
 $assertLine($lineOf($r,$pA),'12','0','102.00','1224.00','306.00','918.00','12','issue-review A');
 $assertLine($lineOf($r,$pB),'12','0','140.00','1680.00','420.00','1260.00','12','issue-review B');
 $balA0=(int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id=".$rep['id']." AND product_id=".$pA)->fetchColumn();
 $balB0=(int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id=".$rep['id']." AND product_id=".$pB)->fetchColumn();
 $in['review_hash']=$r['review_hash'];$issued=$svc->issue($rep,$in);$inv=$issued['invoice_id'];
 $items=$pdo->query("SELECT product_id,quantity,free_quantity,unit_price,gross_amount,discount_amount,line_total FROM invoice_items WHERE invoice_id='$inv' ORDER BY product_id")->fetchAll();
 $check(count($items)===2,'Invoice persists two lines');
 $check((int)$items[0]['quantity']===12&&(int)$items[1]['quantity']===12,'Persisted paid quantities are 12 and 12');
 $check((int)$items[0]['free_quantity']===0&&(int)$items[1]['free_quantity']===0,'Persisted free quantities are 0');
 $check($items[0]['unit_price']==='102.00'&&$items[1]['unit_price']==='140.00','Persisted original unit prices');
 $check($items[0]['gross_amount']==='1224.00'&&$items[1]['gross_amount']==='1680.00','Persisted gross amounts');
 $check($items[0]['line_total']==='918.00'&&$items[1]['line_total']==='1260.00','Persisted net line amounts');
 $check($pdo->query("SELECT grand_total FROM invoices WHERE id='$inv'")->fetchColumn()==='2178.00','Invoice payable 2178.00');
 $balA1=(int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id=".$rep['id']." AND product_id=".$pA)->fetchColumn();
 $balB1=(int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id=".$rep['id']." AND product_id=".$pB)->fetchColumn();
 $check($balA0-$balA1===12&&$balB0-$balB1===12,'Stock deduction is exactly 12 units of each product');
 $moves=$pdo->query("SELECT m.product_id,m.quantity_delta FROM inventory_movements m JOIN inventory_transactions t ON t.id=m.transaction_id WHERE t.invoice_id='$inv' AND t.type='SALE' ORDER BY m.product_id")->fetchAll();
 $check(count($moves)===2&&(int)$moves[0]['quantity_delta']===-12&&(int)$moves[1]['quantity_delta']===-12,'Sale movements are -12 for both products');
 $snap=$pdo->query("SELECT * FROM invoice_benefit_snapshots WHERE invoice_id='$inv'")->fetch();
 $check($snap['benefit_mode']==='PROMOTION_DISCOUNT_INSTEAD'&&$snap['percent_rate']==='25.00'&&$snap['eligible_subtotal']==='2904.00'&&$snap['discount_amount']==='726.00'&&(int)$snap['suppressed_free_quantity']===3,'Benefit snapshot preserves decision');
 $lsnaps=$pdo->query("SELECT * FROM invoice_benefit_line_snapshots WHERE invoice_id='$inv' ORDER BY product_id")->fetchAll();
 $check(count($lsnaps)===2&&(int)$lsnaps[0]['paid_quantity']===12&&(int)$lsnaps[1]['paid_quantity']===12,'Line snapshots preserve paid quantities');
 $check($lsnaps[0]['allocated_discount']==='306.00'&&$lsnaps[1]['allocated_discount']==='420.00','Line snapshots preserve allocation');
 $check((int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM invoice_items WHERE invoice_id='$inv' AND product_id=$pA")->fetchColumn()===12&&(int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM invoice_items WHERE invoice_id='$inv' AND product_id=$pB")->fetchColumn()===12,'Product sales quantities record 12 units of each');
 $recon=$pdo->query("SELECT l.id FROM invoice_items l JOIN online_invoice_details d ON d.invoice_id=l.invoice_id LEFT JOIN inventory_transactions t ON t.invoice_id=l.invoice_id AND t.type='SALE' LEFT JOIN inventory_movements m ON m.transaction_id=t.id AND m.product_id=l.product_id AND m.location_id=d.source_location_id WHERE l.invoice_id='$inv' AND (t.id IS NULL OR m.id IS NULL OR m.quantity_delta<>-(l.quantity+l.free_quantity))")->fetchAll();
 $check($recon===[],'Reconciliation: paid and free units match stock deductions');
 $svc->void($admin,$inv,['request_id'=>Inventory::uuid(),'reason'=>'quantity matrix verification']);
 $balA2=(int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id=".$rep['id']." AND product_id=".$pA)->fetchColumn();
 $balB2=(int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id=".$rep['id']." AND product_id=".$pB)->fetchColumn();
 $check($balA2===$balA0&&$balB2===$balB0,'Void restores the original deducted quantities');

 $pdo->rollBack();
 $after=[];foreach($tables as $t)$after[$t]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));
 $check($before===$after,'Isolated database rolls back completely');
 echo 'promo-choice-quantity: '.$count.' quantity-preservation matrix and isolated posting checks passed; fixtures rolled back.'.PHP_EOL;
}catch(Throwable $e){$pdo->rollBack();throw $e;}
