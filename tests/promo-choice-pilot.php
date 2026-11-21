<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Invoices,Inventory,ProductStock,RepresentativeStock,Promotions,BenefitPolicy};
use App\Support\{Database,InventoryException};
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_NAME')!=='dharma_sales_phase9_test'||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309')throw new RuntimeException('Exact isolated test target required.');
if(getenv('DHARMA_BENEFIT_TEST')!=='1')throw new RuntimeException('Isolated benefit gate flag required.');
$pdo=Database::connect();$svc=new Invoices($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$reject=static function(callable $fn,int $status,string $label)use($check){try{$fn();}catch(InventoryException $e){$check($e->status===$status,$label.' status '.$e->status);return;}throw new RuntimeException('Unexpected acceptance: '.$label);};
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$before=[];foreach($tables as $t)$before[$t]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));
$pdo->beginTransaction();
try{
 $tag='PILOT'.strtoupper(bin2hex(random_bytes(4)));
 foreach(['admin'=>'ADMIN','pilot'=>'SALES_REP','plain'=>'SALES_REP'] as $key=>$role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Pilot fixture '.$key,$tag.$key,password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT),$role]);$actors[$key]=['id'=>$pdo->lastInsertId()];}
 $admin=$actors['admin'];$pilot=$actors['pilot'];$plain=$actors['plain'];
 putenv('DHARMA_BENEFIT_TEST_PILOT_IDS='.$pilot['id']);
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$tag]);$cat=$pdo->lastInsertId();
 $products=[];foreach(['200.00','140.00','100.00','10.00'] as $ix=>$price){$pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)')->execute([$tag.$ix,'Pilot product '.$ix,$cat,$price]);$products[]=$pdo->lastInsertId();}
 [$pA,$pB,$pC,$o1]=$products;
 foreach($products as $ix=>$p)(new ProductStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'product_id'=>$p,'quantity'=>'500','selling_price'=>['200.00','140.00','100.00','10.00'][$ix],'product_version'=>'1']);
 foreach([$pilot['id'],$plain['id']] as $repId)foreach($products as $p)(new RepresentativeStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'rep_id'=>$repId,'product_id'=>$p,'quantity'=>'100'],'ALLOCATE');
 $customers=[];foreach([$pilot['id'],'plain'=>$plain['id']] as $repId){$pdo->prepare("INSERT INTO customers(customer_code,shop_name,contact_person,phone,address,area,assigned_rep_id,credit_terms_days,credit_limit_enabled) VALUES (?,'Pilot customer','Fixture','0110000000','Pilot address','',?,30,0)")->execute([$tag.$repId,$repId]);$customers[$repId]=$pdo->lastInsertId();}
 $promos=new Promotions($pdo);
 $promos->save($admin,['request_id'=>Inventory::uuid(),'name'=>'Pilot buy get A','description'=>'','product_id'=>$pA,'type'=>'BUY_X_GET_Y','buy_quantity'=>'12','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $promos->save($admin,['request_id'=>Inventory::uuid(),'name'=>'Pilot buy get B','description'=>'','product_id'=>$pB,'type'=>'BUY_X_GET_Y','buy_quantity'=>'12','free_quantity'=>'2','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $promos->save($admin,['request_id'=>Inventory::uuid(),'name'=>'Pilot monetary','description'=>'','product_id'=>$pC,'type'=>'PERCENTAGE','buy_quantity'=>'','free_quantity'=>'','percent_rate'=>'10.00','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $promos->save($admin,['request_id'=>Inventory::uuid(),'name'=>'Pilot oil','description'=>'','mixed_oil'=>'1','mixed_product_ids'=>[$o1],'type'=>'BUY_X_GET_Y','buy_quantity'=>'6','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $base=static function(array $rep)use($customers):array{return ['customer_id'=>$customers[$rep['id']],'stock_source'=>$rep['id'],'payment_type'=>'CASH','amount_received'=>'100000.00'];};
 $benefit=static function(string $mode,?string $choice=null,?string $percent=null){$in=['benefit_policy'=>'PROMO_CHOICE_V1','benefit_mode'=>$mode];if($choice!==null)$in['promotion_discount_choice']=$choice;if($percent!==null)$in['promotion_discount_percent']=$percent;return $in;};
 $stock=static function(string $repId,$product)use($pdo):int{return (int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id=$repId AND product_id=$product")->fetchColumn();};
 $snap=static function(string $invoice)use($pdo):?array{$q=$pdo->prepare('SELECT * FROM invoice_benefit_snapshots WHERE invoice_id=?');$q->execute([$invoice]);return $q->fetch()?:null;};

 // D1: global flag ON (isolated) - non-pilot representative cannot use the new mode.
 $in=$base($plain)+$benefit('PROMOTION_DISCOUNT_INSTEAD','25');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pA,'quantity'=>'12']];
 $reject(fn()=>$svc->review($plain,$in),403,'Non-pilot representative rejected from new mode');
 $reject(fn()=>$svc->issue($plain,$in+['review_hash'=>str_repeat('0',64)]),403,'Non-pilot representative rejected at posting');
 // D2: non-pilot legacy online checkout stays available during the transition.
 $in=$base($plain);$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pA,'quantity'=>'12']];$in['discount_policy']='INVOICE_PERCENT_V1';$in['invoice_discount_choice']='25';
 $r=$svc->review($plain,$in);$in['review_hash']=$r['review_hash'];$issued=$svc->issue($plain,$in);
 $check($r['grand_total']==='1800.00','Non-pilot legacy checkout preserved');
 // D3: allowlisted pilot representative runs authorized PROMO_CHOICE_V1 checkout.
 $in=$base($pilot)+$benefit('PROMOTION_DISCOUNT_INSTEAD','25');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pA,'quantity'=>'12']];
 $r=$svc->review($pilot,$in);$in['review_hash']=$r['review_hash'];
 $check($r['benefit_discount_amount']==='600.00'&&$r['grand_total']==='1800.00','Pilot benefit review authorized');
 // D4: pilot representative cannot bypass the new policy with legacy online checkout.
 $in=$base($pilot);$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pA,'quantity'=>'12']];
 $reject(fn()=>$svc->review($pilot,$in),409,'Pilot plain online checkout rejected');
 $in['discount_policy']='INVOICE_PERCENT_V1';$in['invoice_discount_choice']='25';
 $reject(fn()=>$svc->review($pilot,$in),409,'Pilot legacy-discount online checkout rejected');
 // D5: the pilot exception preserves previously signed LEGACY OFFLINE synchronization.
 $in=$base($pilot);$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pA,'quantity'=>'12']];
 $r=$svc->review($pilot,$in,true);
 $check($r['grand_total']==='2400.00'&&(int)$r['lines'][0]['free_quantity']===1,'Pilot legacy offline sync path preserved');
 // D6: an authorization change between review and issue blocks the posting.
 $in=$base($pilot)+$benefit('ORDINARY_INVOICE_DISCOUNT','25');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pC,'quantity'=>'2']];
 $r=$svc->review($pilot,$in);$in['review_hash']=$r['review_hash'];
 putenv('DHARMA_BENEFIT_TEST_PILOT_IDS=');
 $reject(fn()=>$svc->issue($pilot,$in),403,'Allowlist removal between review and issue blocks posting');
 putenv('DHARMA_BENEFIT_TEST_PILOT_IDS='.$pilot['id']);
 $issued=$svc->issue($pilot,$in);$check($snap($issued['invoice_id'])!==null,'Restored authorization issues the reviewed invoice');

 // B1: Decision B - ORDINARY_INVOICE_DISCOUNT at 0% is the no-benefit representation (spec example shape).
 $in=$base($pilot)+$benefit('ORDINARY_INVOICE_DISCOUNT','0');$in['request_id']=Inventory::uuid();
 $in['items']=[['product_id'=>$pA,'quantity'=>'12'],['product_id'=>$pB,'quantity'=>'12'],['product_id'=>$pC,'quantity'=>'10']];
 $r=$svc->review($pilot,$in);$in['review_hash']=$r['review_hash'];
 $check($r['subtotal']==='5080.00'&&$r['benefit_discount_amount']==='0.00'&&$r['discount_total']==='0.00'&&$r['grand_total']==='5080.00','No-benefit invoice keeps full payable');
 $check($r['benefit_suppressed_free_quantity']==='3'&&$r['benefit_suppressed_monetary_discount']==='100.00','No-benefit suppresses free units and monetary promotions');
 foreach($r['lines'] as $line)$check($line['free_quantity']==='0'&&$line['discount_amount']==='0.00','No line keeps a promotional benefit');
 $issued=$svc->issue($pilot,$in);$invZero=$issued['invoice_id'];
 $s=$snap($invZero);$check($s!==null&&$s['benefit_mode']==='ORDINARY_INVOICE_DISCOUNT'&&$s['percent_rate']==='0.00'&&$s['discount_amount']==='0.00','No-benefit snapshot records the exact 0 percent rate');
 $check($stock($pilot['id'],$pA)===88&&$stock($pilot['id'],$pB)===88&&$stock($pilot['id'],$pC)===88,'No-benefit deducts paid stock only');
 // B2: Custom 0% produces the same financial result and never switches modes.
 $in=$base($pilot)+$benefit('ORDINARY_INVOICE_DISCOUNT','custom','0');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pA,'quantity'=>'12']];
 $r=$svc->review($pilot,$in);
 $check($r['benefit_rate']==='0.00'&&$r['benefit_discount_amount']==='0.00'&&$r['grand_total']==='2400.00','Custom zero equals the preset zero');
 $in=$base($pilot)+$benefit('PROMOTION_DISCOUNT_INSTEAD','custom','0');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pA,'quantity'=>'12']];
 $r=$svc->review($pilot,$in);$in['review_hash']=$r['review_hash'];
 $check($r['benefit_mode']==='PROMOTION_DISCOUNT_INSTEAD'&&$r['benefit_discount_amount']==='0.00'&&$r['grand_total']==='2400.00','Custom zero keeps the selected mode');
 $check((int)$r['benefit_suppressed_free_quantity']===1,'Custom zero still suppresses free units');
 $issued=$svc->issue($pilot,$in);
 $check($snap($issued['invoice_id'])['benefit_mode']==='PROMOTION_DISCOUNT_INSTEAD','Custom zero snapshot keeps PROMOTION_DISCOUNT_INSTEAD');
 // B3: the predefined 0% choice is not offered for PROMOTION_DISCOUNT_INSTEAD.
 $in=$base($pilot)+$benefit('PROMOTION_DISCOUNT_INSTEAD','0');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pA,'quantity'=>'12']];
 $reject(fn()=>$svc->review($pilot,$in),422,'Preset zero not a PROMOTION_DISCOUNT_INSTEAD choice');
 // B4: mixed-oil rewards are suppressed under the 0% no-benefit invoice.
 $in=$base($pilot)+$benefit('ORDINARY_INVOICE_DISCOUNT','0');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$o1,'quantity'=>'6']];
 $r=$svc->review($pilot,$in);
 $check(!isset($r['oil_offers'])&&$r['grand_total']==='60.00','No-benefit suppresses pooled oil rewards');

 // E1: new-mode offline issuance and synchronization stay rejected with the gate open.
 $in=$base($pilot)+$benefit('FREE_PROMOTION');$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$pA,'quantity'=>'12']];
 $r=$svc->review($pilot,$in);$in['review_hash']=$r['review_hash'];
 $reject(fn()=>$svc->issue($pilot,$in,['device_id'=>'00000000-0000-4000-8000-0000000000e1','grant_id'=>'00000000-0000-4000-8000-0000000000e2','created_at_utc'=>'2026-11-22 00:00:00']),409,'New-mode offline synchronization rejected');
}finally{
 $pdo->rollBack();
 $ok=true;foreach($before as $t=>$hash){$now=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));if($now!==$hash){fwrite(STDERR,"Table changed: $t\n");$ok=false;}}
 $check($ok,'Database unchanged after pilot test rollback');
}
echo "promo-choice-pilot: $count checks passed.\n";
