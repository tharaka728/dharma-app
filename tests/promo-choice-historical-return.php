<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Invoices,Inventory,ProductStock,RepresentativeStock,Promotions,BenefitPolicy,HistoricalReturnPrices,HistoricalReturnIntents};
use App\Support\{Database,InventoryException};
// Isolated combined target: synthetic copy named like the historical-return restore database, never the operational one.
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_NAME')!=='dharma_sales_phase12_restore_20261118154343'||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309')throw new RuntimeException('Exact isolated test target required.');
if(getenv('DHARMA_BENEFIT_TEST')!=='1'||getenv('DHARMA_BENEFIT_TEST_DB')!=='dharma_sales_phase12_restore_20261118154343'||getenv('HISTORICAL_RETURN_TEST')!=='1')throw new RuntimeException('Isolated benefit and return test flags required.');
$pdo=Database::connect();$svc=new Invoices($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$before=[];foreach($tables as $t)$before[$t]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));
$pdo->beginTransaction();
try{
 $tag='HRC'.strtoupper(bin2hex(random_bytes(5)));
 foreach(['admin'=>'ADMIN','rep'=>'SALES_REP'] as $key=>$role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Combined fixture',$tag.$key,password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT),$role]);$actors[$key]=['id'=>$pdo->lastInsertId()];}$admin=$actors['admin'];$rep=$actors['rep'];
 putenv('DHARMA_BENEFIT_TEST_PILOT_IDS='.$rep['id']);
putenv('DHARMA_BENEFIT_TEST_POSTING_HOLD=0');
 putenv('HISTORICAL_RETURN_TEST_REP_ID='.$rep['id']);
 $check($svc->historicalReturnAllowed($rep),'Isolated return pilot unlocks for the fixture representative');
 $check(BenefitPolicy::enabled($pdo),'Isolated benefit gate opens on the combined target');
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$tag]);$cat=$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)')->execute([$tag,'Combined product',$cat,'2500.00']);$product=$pdo->lastInsertId();
 (new ProductStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'product_id'=>$product,'quantity'=>'500','selling_price'=>'2500.00','product_version'=>'1']);
 (new RepresentativeStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'rep_id'=>$rep['id'],'product_id'=>$product,'quantity'=>'40'],'ALLOCATE');
 $pdo->prepare("INSERT INTO customers(customer_code,shop_name,contact_person,phone,address,area,assigned_rep_id,credit_terms_days,credit_limit_enabled) VALUES (?,'Combined customer','Fixture','0110000000','Combined address','',?,30,0)")->execute([$tag,$rep['id']]);$customer=$pdo->lastInsertId();
 (new Promotions($pdo))->save($admin,['request_id'=>Inventory::uuid(),'name'=>'Combined buy get','description'=>'','product_id'=>$product,'type'=>'BUY_X_GET_Y','buy_quantity'=>'2','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 (new HistoricalReturnPrices($pdo))->save($admin,['product_id'=>$product,'price_year'=>'2024','unit_price'=>'2000.00','reason'=>'Combined test']);
 $intent=(new HistoricalReturnIntents($pdo))->start($rep,(string)$customer)['return_intent_id'];

 // Spec section 13 example: 10000 merchandise, 2500 benefit discount, 7500 net, 2000 return, 5500 payable.
 $in=['request_id'=>Inventory::uuid(),'customer_id'=>$customer,'stock_source'=>$rep['id'],'payment_type'=>'CASH','amount_received'=>'5500.00','items'=>[['product_id'=>$product,'quantity'=>'4']],'benefit_policy'=>'PROMO_CHOICE_V1','benefit_mode'=>'PROMOTION_DISCOUNT_INSTEAD','promotion_discount_choice'=>'25','historical_return'=>['return_intent_id'=>$intent,'received'=>'1','unsaleable'=>'1','eligible'=>'1','reason'=>'Combined test','lines'=>[['product_id'=>$product,'year'=>'2024','quantity'=>'1']]]];
 $r=$svc->review($rep,$in);$in['review_hash']=$r['review_hash'];
 $check($r['subtotal']==='10000.00'&&$r['benefit_discount_amount']==='2500.00'&&$r['grand_total']==='7500.00','Benefit discount precedes return settlement');
 $check($r['historical_return']['approved_value']==='2000.00'&&$r['return_settlement']['applied_value']==='2000.00'&&$r['return_settlement']['forfeited_value']==='0.00'&&$r['return_settlement']['remaining_payable']==='5500.00','Return settlement matches the approved example');
 $issued=$svc->issue($rep,$in);$invoice=$issued['invoice_id'];
 $hr=$pdo->query("SELECT approved_value,applied_value,forfeited_value FROM historical_customer_returns WHERE invoice_id='$invoice'")->fetch();
 $check($hr!==false&&$hr['approved_value']==='2000.00'&&$hr['applied_value']==='2000.00'&&$hr['forfeited_value']==='0.00','Return settlement persisted as a noncash adjustment');
 $doc=$svc->get($rep,$invoice);
 $check($doc['grand_total']==='7500.00'&&$doc['outstanding']==='0.00'&&$doc['payment_status']==='PAID'&&$doc['benefit_mode']==='PROMOTION_DISCOUNT_INSTEAD','Combined invoice settles at 5500 cash plus 2000 return');
 $snap=$pdo->query("SELECT discount_amount,benefit_mode FROM invoice_benefit_snapshots WHERE invoice_id='$invoice'")->fetch();
 $check($snap!==false&&$snap['discount_amount']==='2500.00','Combined benefit snapshot persisted');
 $check((int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id={$rep['id']} AND product_id=$product")->fetchColumn()===36,'Combined invoice deducts paid stock only');
 $check((int)$pdo->query("SELECT COUNT(*) FROM historical_customer_return_lines WHERE invoice_id='$invoice' AND product_id=$product AND quantity=1")->fetchColumn()===1,'Returned unit recorded on the return lines');
 // Voiding a combined invoice retains the return and reverses paid stock.
 $svc->void($admin,$invoice,['request_id'=>Inventory::uuid(),'reason'=>'Combined void test']);
 $check((int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id={$rep['id']} AND product_id=$product")->fetchColumn()===40,'Combined void restores paid stock');
 $check($pdo->query("SELECT benefit_mode FROM invoice_benefit_snapshots WHERE invoice_id='$invoice'")->fetchColumn()==='PROMOTION_DISCOUNT_INSTEAD','Combined snapshot survives void');
}finally{
 $pdo->rollBack();
 $ok=true;foreach($before as $t=>$hash){$now=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));if($now!==$hash){fwrite(STDERR,"Table changed: $t\n");$ok=false;}}
 $check($ok,'Database unchanged after combined test rollback');
}
echo "promo-choice-historical-return: $count checks passed.\n";
