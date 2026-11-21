<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Invoices,Inventory,ProductStock,RepresentativeStock,Promotions,BenefitPolicy,InvoiceDiscount};
use App\Support\{Database,InventoryException};
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_NAME')!=='dharma_sales_phase9_test'||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309')throw new RuntimeException('Exact isolated test target required.');
if(getenv('DHARMA_BENEFIT_TEST')!=='1')throw new RuntimeException('Isolated benefit gate flag required.');
$pdo=Database::connect();$svc=new Invoices($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$reject=static function(callable $fn,int $status,string $label)use($check){try{$fn();}catch(InventoryException $e){$check($e->status===$status,$label.' status '.$e->status);return;}throw new RuntimeException('Unexpected acceptance: '.$label);};
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$before=[];foreach($tables as $t)$before[$t]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));
$pdo->beginTransaction();
try{
 $check(BenefitPolicy::enabled($pdo),'Isolated test gate opens with flag and matching database');
 $config=require dirname(__DIR__).'/config/benefit-modes.php';$check($config['enabled']===false&&$config['databases']===[],'Operational gate remains disabled by default');
 $tag='PROMO'.strtoupper(bin2hex(random_bytes(5)));
 foreach(['admin'=>'ADMIN','rep'=>'SALES_REP'] as $key=>$role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Benefit fixture',$tag.$key,password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT),$role]);$actors[$key]=['id'=>$pdo->lastInsertId()];}$admin=$actors['admin'];$rep=$actors['rep'];
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$tag]);$cat=$pdo->lastInsertId();
 $products=[];foreach(['100.00','0.05','50.00','25.00','10.00','10.00','0.01','0.01','0.01'] as $ix=>$price){$pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)')->execute([$tag.$ix,'Benefit product '.$ix,$cat,$price]);$products[]=$pdo->lastInsertId();}
 [$p1,$p2,$p3,$p4,$o1,$o2,$c1,$c2,$c3]=$products;
 foreach($products as $ix=>$p)(new ProductStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'product_id'=>$p,'quantity'=>'500','selling_price'=>['100.00','0.05','50.00','25.00','10.00','10.00','0.01','0.01','0.01'][$ix],'product_version'=>'1']);
 foreach($products as $p)(new RepresentativeStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'rep_id'=>$rep['id'],'product_id'=>$p,'quantity'=>$p===$p1?'80':'400'],'ALLOCATE');
 $pdo->prepare("INSERT INTO customers(customer_code,shop_name,contact_person,phone,address,area,assigned_rep_id,credit_terms_days,credit_limit_enabled) VALUES (?,'Benefit customer','Fixture','0110000000','Benefit address','',?,30,0)")->execute([$tag,$rep['id']]);$customer=$pdo->lastInsertId();
 $promos=new Promotions($pdo);
 $mkOffer=static function(array $over)use($promos,$admin){return $promos->save($admin,$over);};
 $buyGet=$mkOffer(['request_id'=>Inventory::uuid(),'name'=>'Benefit buy get','description'=>'','product_id'=>$p1,'type'=>'BUY_X_GET_Y','buy_quantity'=>'2','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $percentOffer=$mkOffer(['request_id'=>Inventory::uuid(),'name'=>'Benefit percent','description'=>'','product_id'=>$p2,'type'=>'PERCENTAGE','buy_quantity'=>'','free_quantity'=>'','percent_rate'=>'10.00','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $thresholdOffer=$mkOffer(['request_id'=>Inventory::uuid(),'name'=>'Benefit threshold','description'=>'','product_id'=>$p4,'type'=>'BUY_X_GET_Y','buy_quantity'=>'5','free_quantity'=>'2','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $mixedOffer=$mkOffer(['request_id'=>Inventory::uuid(),'name'=>'Benefit mixed oil','description'=>'','mixed_oil'=>'1','mixed_product_ids'=>[$o1,$o2],'type'=>'BUY_X_GET_Y','buy_quantity'=>'6','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $base=['customer_id'=>$customer,'stock_source'=>$rep['id'],'payment_type'=>'CASH','amount_received'=>'100000.00'];
 $benefit=static function(string $mode,?string $choice=null,?string $percent=null)use($base):array{$in=$base;$in['benefit_policy']='PROMO_CHOICE_V1';$in['benefit_mode']=$mode;if($choice!==null)$in['promotion_discount_choice']=$choice;if($percent!==null)$in['promotion_discount_percent']=$percent;return $in;};
 $prepare=static function(array $actor,array $in)use($svc):array{$r=$svc->review($actor,$in);$in['review_hash']=$r['review_hash'];return [$r,$in];};
 $snapshot=static function(string $invoice)use($pdo):?array{$q=$pdo->prepare('SELECT * FROM invoice_benefit_snapshots WHERE invoice_id=?');$q->execute([$invoice]);return $q->fetch()?:null;};
 $lineSnapshots=static function(string $invoice)use($pdo):array{$q=$pdo->prepare('SELECT * FROM invoice_benefit_line_snapshots WHERE invoice_id=? ORDER BY line_number');$q->execute([$invoice]);return $q->fetchAll();};

 // S2: FREE_PROMOTION keeps the promotional free unit, records the benefit decision.
 $in=$benefit('FREE_PROMOTION');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'2']];
 [$r,$in]=$prepare($rep,$in);
 $check($r['benefit_mode']==='FREE_PROMOTION'&&$r['benefit_discount_amount']==='0.00'&&$r['grand_total']==='200.00'&&$r['discount_total']==='0.00','Free mode review totals');
 $check($r['lines'][0]['free_quantity']==='1'&&$r['lines'][0]['required_stock']==='3','Free mode keeps free unit');
 $issued=$svc->issue($rep,$in);$invFree=$issued['invoice_id'];
 $snap=$snapshot($invFree);$check($snap!==null&&$snap['policy_version']==='PROMO_CHOICE_V1'&&$snap['benefit_mode']==='FREE_PROMOTION'&&$snap['percent_rate']==='0.00'&&$snap['discount_amount']==='0.00','Free mode snapshot persisted');
 $ls=$lineSnapshots($invFree);$check(count($ls)===1&&(int)$ls[0]['actual_free_quantity']===1&&$ls[0]['eligibility_reason']==='FREE_PROMOTION_MODE'&&(int)$ls[0]['promotion_threshold']===2,'Free mode line snapshot');
 $check((int)$pdo->query("SELECT free_quantity FROM invoice_items WHERE invoice_id='$invFree'")->fetchColumn()===1,'Invoice item keeps free unit');
 $check((int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id={$rep['id']} AND product_id=$p1")->fetchColumn()===77,'Free unit stock deducted');
 $check($svc->get($rep,$invFree)['benefit_mode']==='FREE_PROMOTION','Invoice record exposes benefit mode');

 // S3: PROMOTION_DISCOUNT_INSTEAD suppresses the free unit and discounts eligible lines only.
 $in=$benefit('PROMOTION_DISCOUNT_INSTEAD','25');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'2'],['product_id'=>$p3,'quantity'=>'1']];
 [$r,$in]=$prepare($rep,$in);
 $check($r['benefit_eligible_subtotal']==='200.00'&&$r['benefit_discount_amount']==='50.00'&&$r['grand_total']==='200.00'&&$r['discount_total']==='50.00'&&$r['subtotal']==='250.00','Promo-instead review totals');
 $check($r['benefit_suppressed_free_quantity']==='1'&&$r['benefit_suppressed_monetary_discount']==='0.00','Suppression accounted');
 $check($r['lines'][0]['free_quantity']==='0'&&$r['lines'][0]['required_stock']==='2'&&$r['lines'][0]['payable_amount']==='150.00'&&$r['lines'][0]['benefit_eligibility_reason']==='QUALIFIES','Eligible line allocated');
 $check($r['lines'][1]['payable_amount']==='50.00'&&$r['lines'][1]['benefit_eligibility_reason']==='NO_PROMOTION'&&$r['lines'][1]['invoice_discount_amount']==='0.00','Plain line excluded');
 $check(!isset($r['oil_offers']),'No oil offers in discount mode');
 $issued=$svc->issue($rep,$in);$invAlt=$issued['invoice_id'];
 $check($svc->issue($rep,$in)['replayed']===true,'Benefit issue idempotent replay');
 $snap=$snapshot($invAlt);$check($snap['percent_rate']==='25.00'&&$snap['eligible_subtotal']==='200.00'&&$snap['discount_amount']==='50.00'&&(int)$snap['suppressed_free_quantity']===1,'Promo-instead snapshot persisted');
 $check(json_decode($snap['promotion_revisions'],true)[0]['revision_id']===$buyGet['revision_id'],'Promotion revision recorded');
 $ls=$lineSnapshots($invAlt);$check(count($ls)===2&&(int)$ls[0]['eligible']===1&&$ls[0]['allocated_discount']==='50.00'&&(int)$ls[1]['eligible']===0&&$ls[1]['allocated_discount']==='0.00','Promo-instead line snapshots');
 $check((int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id={$rep['id']} AND product_id=$p1")->fetchColumn()===75,'Only paid stock deducted in discount mode');
 $check((int)$pdo->query("SELECT COUNT(*) FROM invoice_discount_snapshots WHERE invoice_id='$invAlt'")->fetchColumn()===0,'No INVOICE_PERCENT_V1 snapshot for benefit invoice');

 // Custom rate validation path.
 $in=$benefit('PROMOTION_DISCOUNT_INSTEAD','custom','12.50');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'2']];
 [$r]=$prepare($rep,$in);$check($r['benefit_discount_amount']==='25.00'&&$r['benefit_rate']==='12.50','Custom percent accepted');

 // S4: monetary promotion discounts suppressed in discount modes, kept in free mode.
 $in=$benefit('PROMOTION_DISCOUNT_INSTEAD','0');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p2,'quantity'=>'10']];
 [$r]=$prepare($rep,$in);
 $check($r['grand_total']==='0.50'&&$r['benefit_suppressed_monetary_discount']==='0.05'&&$r['lines'][0]['benefit_eligibility_reason']==='NOT_BUY_X_GET_Y','Monetary promotion suppressed');
 $in=$benefit('FREE_PROMOTION');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p2,'quantity'=>'10']];
 [$r]=$prepare($rep,$in);$check($r['grand_total']==='0.45','Monetary promotion kept in free mode');

 // S5: ORDINARY_INVOICE_DISCOUNT covers every line, half-up rounding, remainder ties by original order.
 $in=$benefit('ORDINARY_INVOICE_DISCOUNT','30');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'2'],['product_id'=>$p3,'quantity'=>'1']];
 [$r]=$prepare($rep,$in);
 $check($r['benefit_eligible_subtotal']==='250.00'&&$r['benefit_discount_amount']==='75.00'&&$r['grand_total']==='175.00','Ordinary mode totals');
 $check($r['lines'][0]['invoice_discount_amount']==='60.00'&&$r['lines'][1]['invoice_discount_amount']==='15.00','Ordinary allocation proportional');
 $in=$benefit('ORDINARY_INVOICE_DISCOUNT','35');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p2,'quantity'=>'1']];
 [$r]=$prepare($rep,$in);$check($r['benefit_discount_amount']==='0.02'&&$r['grand_total']==='0.03','Half-up rounding on cents');
 $in=$benefit('ORDINARY_INVOICE_DISCOUNT','custom','33.33');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$c3,'quantity'=>'1'],['product_id'=>$c1,'quantity'=>'1'],['product_id'=>$c2,'quantity'=>'1']];
 [$r]=$prepare($rep,$in);
 $check($r['benefit_discount_amount']==='0.01'&&$r['lines'][0]['invoice_discount_amount']==='0.01'&&$r['lines'][1]['invoice_discount_amount']==='0.00'&&$r['lines'][2]['invoice_discount_amount']==='0.00','Remainder tie breaks by original line order');
 $check((string)$r['lines'][0]['product_id']===(string)$c3,'Original input order restored');

 // S6: threshold enforcement.
 $in=$benefit('PROMOTION_DISCOUNT_INSTEAD','25');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p4,'quantity'=>'3']];
 [$r]=$prepare($rep,$in);$check($r['benefit_eligible_subtotal']==='0.00'&&$r['lines'][0]['benefit_eligibility_reason']==='BELOW_THRESHOLD'&&$r['grand_total']==='75.00','Below threshold excluded');
 $in=$benefit('PROMOTION_DISCOUNT_INSTEAD','25');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p4,'quantity'=>'5']];
 [$r]=$prepare($rep,$in);$check($r['benefit_eligible_subtotal']==='125.00'&&$r['benefit_discount_amount']==='31.25'&&$r['benefit_suppressed_free_quantity']==='2','Threshold met qualifies');

 // S7: mixed-oil rewards suppressed in discount modes, excluded from the discount base.
 $in=$benefit('PROMOTION_DISCOUNT_INSTEAD','25');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$o1,'quantity'=>'3'],['product_id'=>$o2,'quantity'=>'4']];
 [$r,$in]=$prepare($rep,$in);
 $check(!isset($r['oil_offers'])&&$r['benefit_eligible_subtotal']==='0.00'&&$r['grand_total']==='70.00','Mixed-oil excluded and rewards not required');
 $check($r['lines'][0]['benefit_eligibility_reason']==='MIXED_OIL_EXCLUDED'&&$r['lines'][1]['benefit_eligibility_reason']==='MIXED_OIL_EXCLUDED','Mixed-oil exclusion recorded');
 $issued=$svc->issue($rep,$in);$check($snapshot($issued['invoice_id'])['benefit_mode']==='PROMOTION_DISCOUNT_INSTEAD','Mixed-oil invoice issues without reward selection');
 $in=$benefit('FREE_PROMOTION');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$o1,'quantity'=>'3'],['product_id'=>$o2,'quantity'=>'4']];
 [$r]=$prepare($rep,$in);$check(isset($r['oil_offers'])&&$r['oil_offers'][0]['earned']==='1','Free mode still offers pooled oil rewards');
 $bad=$benefit('PROMOTION_DISCOUNT_INSTEAD','25');$bad['request_id']=Inventory::uuid();$bad['items']=[['product_id'=>$o1,'quantity'=>'6']];$bad['oil_rewards']=[['product_id'=>$o1,'quantity'=>'1']];
 $reject(fn()=>$svc->review($rep,$bad),409,'Oil rewards rejected in discount mode');

 // S8: validation.
 $bad=$benefit('FREE_PROMOTION');$bad['benefit_policy']='INVOICE_PERCENT_V1';$bad['request_id']=Inventory::uuid();$bad['items']=[['product_id'=>$p3,'quantity'=>'1']];$reject(fn()=>$svc->review($rep,$bad),409,'Unknown benefit policy rejected');
 $bad=$benefit('BOGUS_MODE');$bad['request_id']=Inventory::uuid();$bad['items']=[['product_id'=>$p3,'quantity'=>'1']];$reject(fn()=>$svc->review($rep,$bad),422,'Unknown benefit mode rejected');
 $bad=$benefit('ORDINARY_INVOICE_DISCOUNT','custom','150');$bad['request_id']=Inventory::uuid();$bad['items']=[['product_id'=>$p3,'quantity'=>'1']];$reject(fn()=>$svc->review($rep,$bad),422,'Rate above 100 rejected');
 $bad=$benefit('ORDINARY_INVOICE_DISCOUNT','25');$bad['discount_policy']='INVOICE_PERCENT_V1';$bad['invoice_discount_choice']='25';$bad['request_id']=Inventory::uuid();$bad['items']=[['product_id'=>$p3,'quantity'=>'1']];$reject(fn()=>$svc->review($rep,$bad),409,'Benefit and legacy discount cannot stack');
 $bad=$benefit('PROMOTION_DISCOUNT_INSTEAD','99');$bad['request_id']=Inventory::uuid();$bad['items']=[['product_id'=>$p3,'quantity'=>'1']];$reject(fn()=>$svc->review($rep,$bad),422,'Unlisted discount choice rejected');

 // S9: stock checked against paid units only in discount modes.
 $bad=$benefit('FREE_PROMOTION');$bad['request_id']=Inventory::uuid();$bad['items']=[['product_id'=>$p1,'quantity'=>'60']];$reject(fn()=>$svc->review($rep,$bad),409,'Free units still require stock in free mode');
 $in=$benefit('PROMOTION_DISCOUNT_INSTEAD','25');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'60']];
 [$r]=$prepare($rep,$in);$check($r['benefit_eligible_subtotal']==='6000.00','Discount mode needs paid stock only');

 // S10: snapshot immutability enforced by the database.
 foreach(["UPDATE invoice_benefit_snapshots SET discount_amount=0 WHERE invoice_id='$invAlt'","DELETE FROM invoice_benefit_snapshots WHERE invoice_id='$invAlt'","UPDATE invoice_benefit_line_snapshots SET allocated_discount=0 WHERE invoice_id='$invAlt'","DELETE FROM invoice_benefit_line_snapshots WHERE invoice_id='$invAlt'"] as $sql){try{$pdo->exec($sql);throw new RuntimeException('Mutable benefit snapshot');}catch(PDOException $e){$check((int)$e->errorInfo[1]===1644,'Benefit snapshot immutability enforced');}}

 // S12: reviewed totals are fingerprint-bound.
 [$r,$in]=$prepare($rep,$benefit('PROMOTION_DISCOUNT_INSTEAD','25')+['request_id'=>Inventory::uuid(),'items'=>[['product_id'=>$p1,'quantity'=>'2']]]);
 $tampered=$in;$tampered['promotion_discount_choice']='30';$reject(fn()=>$svc->issue($rep,$tampered),409,'Changed benefit choice after review rejected');
 $tampered=$in;$tampered['benefit_mode']='ORDINARY_INVOICE_DISCOUNT';$reject(fn()=>$svc->issue($rep,$tampered),409,'Changed benefit mode after review rejected');

 // S13: legacy INVOICE_PERCENT_V1 path unchanged alongside the new gate.
 $legacy=['request_id'=>Inventory::uuid(),'customer_id'=>$customer,'stock_source'=>$rep['id'],'payment_type'=>'CASH','amount_received'=>'100.00','items'=>[['product_id'=>$p3,'quantity'=>'2']],'discount_policy'=>'INVOICE_PERCENT_V1','invoice_discount_choice'=>'25'];
 [$r,$legacy]=$prepare($rep,$legacy);$check($r['grand_total']==='75.00'&&$r['discount_policy']==='INVOICE_PERCENT_V1','Legacy invoice discount still applies');
 $issued=$svc->issue($rep,$legacy);
 $check((int)$pdo->query("SELECT COUNT(*) FROM invoice_discount_snapshots WHERE invoice_id='{$issued['invoice_id']}'")->fetchColumn()===1&&(int)$pdo->query("SELECT COUNT(*) FROM invoice_benefit_snapshots WHERE invoice_id='{$issued['invoice_id']}'")->fetchColumn()===0,'Legacy snapshot routing unchanged');
 $check($svc->get($rep,$issued['invoice_id'])['benefit_mode']===null,'Legacy invoice has no benefit mode');

 // S14: invoices with no benefit data still read cleanly.
 $plain=['request_id'=>Inventory::uuid(),'customer_id'=>$customer,'stock_source'=>$rep['id'],'payment_type'=>'CASH','amount_received'=>'50.00','items'=>[['product_id'=>$p3,'quantity'=>'1']]];
 [$r,$plain]=$prepare($rep,$plain);$issued=$svc->issue($rep,$plain);$check($svc->get($rep,$issued['invoice_id'])['benefit_mode']===null,'Plain invoice benefit fields null');
 $check($svc->listing($rep,['customer_id'=>$customer])['total']>=1,'Listing survives benefit columns');
}finally{
 $pdo->rollBack();
 $ok=true;foreach($before as $t=>$hash){$now=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));if($now!==$hash){fwrite(STDERR,"Table changed: $t\n");$ok=false;}}
 $check($ok,'Database unchanged after benefit test rollback');
}
echo "promo-choice: $count checks passed\n";
