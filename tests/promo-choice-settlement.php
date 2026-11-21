<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Invoices,Inventory,ProductStock,RepresentativeStock,Promotions,BenefitPolicy,ProductReturnCalculation};
use App\Support\{Database,InventoryException};
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_NAME')!=='dharma_sales_phase9_test'||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309')throw new RuntimeException('Exact isolated test target required.');
if(getenv('DHARMA_BENEFIT_TEST')!=='1')throw new RuntimeException('Isolated benefit gate flag required.');
$pdo=Database::connect();$svc=new Invoices($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$reject=static function(callable $fn,int $status,string $label)use($check){try{$fn();}catch(InventoryException $e){$check($e->status===$status,$label.' status '.$e->status);return;}throw new RuntimeException('Unexpected acceptance: '.$label);};
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$before=[];foreach($tables as $t)$before[$t]=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));
$pdo->beginTransaction();
try{
 $tag='SETT'.strtoupper(bin2hex(random_bytes(5)));
 foreach(['admin'=>'ADMIN','rep'=>'SALES_REP'] as $key=>$role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Settlement fixture',$tag.$key,password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT),$role]);$actors[$key]=['id'=>$pdo->lastInsertId()];}$admin=$actors['admin'];$rep=$actors['rep'];
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$tag]);$cat=$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)')->execute([$tag.'A','Settlement product A',$cat,'100.00']);$p1=$pdo->lastInsertId();
 (new ProductStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'product_id'=>$p1,'quantity'=>'500','selling_price'=>'100.00','product_version'=>'1']);
 (new RepresentativeStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'rep_id'=>$rep['id'],'product_id'=>$p1,'quantity'=>'80'],'ALLOCATE');
 $pdo->prepare("INSERT INTO customers(customer_code,shop_name,contact_person,phone,address,area,assigned_rep_id,credit_terms_days,credit_limit_enabled) VALUES (?,'Settlement customer','Fixture','0110000000','Settlement address','',?,30,0)")->execute([$tag,$rep['id']]);$customer=$pdo->lastInsertId();
 $promos=new Promotions($pdo);
 $promos->save($admin,['request_id'=>Inventory::uuid(),'name'=>'Settlement buy get','description'=>'','product_id'=>$p1,'type'=>'BUY_X_GET_Y','buy_quantity'=>'2','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $stock=static function(string $repId,$product)use($pdo):int{return (int)$pdo->query("SELECT quantity FROM representative_stock_balances WHERE rep_id=$repId AND product_id=$product")->fetchColumn();};
 $benefit=static function(string $mode,?string $choice=null,?string $percent=null)use($customer):array{$in=['customer_id'=>$customer,'stock_source'=>null,'payment_type'=>'CASH','amount_received'=>'100000.00','benefit_policy'=>'PROMO_CHOICE_V1','benefit_mode'=>$mode];if($choice!==null)$in['promotion_discount_choice']=$choice;if($percent!==null)$in['promotion_discount_percent']=$percent;return $in;};
 $for=static function(array $in,string $repId):array{$in['stock_source']=$repId;return $in;};
 $prepare=static function(array $actor,array $in)use($svc):array{$r=$svc->review($actor,$in);$in['review_hash']=$r['review_hash'];return [$r,$in];};
 $snap=static function(string $invoice)use($pdo):?array{$q=$pdo->prepare('SELECT * FROM invoice_benefit_snapshots WHERE invoice_id=?');$q->execute([$invoice]);return $q->fetch()?:null;};

 // S16: void of a FREE_PROMOTION invoice reverses paid + free units exactly once.
 $in=$for($benefit('FREE_PROMOTION'),$rep['id']);$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'2']];
 [$r,$in]=$prepare($rep,$in);$issued=$svc->issue($rep,$in);$invFree=$issued['invoice_id'];
 $check($stock($rep['id'],$p1)===77,'Free mode issue deducts paid plus free');
 $void=['request_id'=>Inventory::uuid(),'reason'=>'Settlement void test'];
 $voided=$svc->void($admin,$invFree,$void);
 $check($voided['replayed']===false&&$stock($rep['id'],$p1)===80,'Void restores paid plus free stock');
 $check($svc->void($admin,$invFree,$void)['replayed']===true,'Void exact replay idempotent');
 $reject(fn()=>$svc->void($admin,$invFree,['request_id'=>Inventory::uuid(),'reason'=>'Second attempt']),409,'Second distinct void rejected');
 $check($svc->get($rep,$invFree)['validity']==='VOIDED','Voided benefit invoice reads VOIDED');
 $s=$snap($invFree);$check($s!==null&&$s['benefit_mode']==='FREE_PROMOTION','Benefit snapshot survives void as immutable history');

 // S17: void of a PROMOTION_DISCOUNT_INSTEAD invoice reverses paid units only.
 $in=$for($benefit('PROMOTION_DISCOUNT_INSTEAD','25'),$rep['id']);$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'2']];
 [$r,$in]=$prepare($rep,$in);$issued=$svc->issue($rep,$in);$invDisc=$issued['invoice_id'];
 $check($stock($rep['id'],$p1)===78,'Discount mode issue deducts paid units only');
 $svc->void($admin,$invDisc,['request_id'=>Inventory::uuid(),'reason'=>'Settlement discount void']);
 $check($stock($rep['id'],$p1)===80,'Discount void restores paid units only');
 $check($snap($invDisc)!==null,'Discount snapshot survives void');

 // S18: insufficient stock rejects review, and a stock drop between review and issue rejects posting atomically.
 $bad=$for($benefit('PROMOTION_DISCOUNT_INSTEAD','25'),$rep['id']);$bad['request_id']=Inventory::uuid();$bad['items']=[['product_id'=>$p1,'quantity'=>'81']];
 $reject(fn()=>$svc->review($rep,$bad),409,'Insufficient paid stock rejected at review');
 $in=$for($benefit('PROMOTION_DISCOUNT_INSTEAD','25'),$rep['id']);$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'80']];
 [$r,$in]=$prepare($rep,$in);
 $pdo->exec("UPDATE representative_stock_balances SET quantity=79 WHERE rep_id={$rep['id']} AND product_id=$p1");
 $reject(fn()=>$svc->issue($rep,$in),409,'Stock drop between review and issue rejected');
 $q=$pdo->prepare('SELECT (SELECT COUNT(*) FROM invoices WHERE id=?)+(SELECT COUNT(*) FROM inventory_movements WHERE transaction_id=?)+(SELECT COUNT(*) FROM payments WHERE id=?)');$q->execute([$in['request_id'],$in['request_id'],$in['request_id']]);
 $check((int)$q->fetchColumn()===0,'Stock failure posts nothing');
 $check($stock($rep['id'],$p1)===79,'Stock untouched after failed checkout');
 $pdo->exec("UPDATE representative_stock_balances SET quantity=80 WHERE rep_id={$rep['id']} AND product_id=$p1");

 // S19: 100% Custom produces a zero-payable checkout, never negative.
 $in=$for($benefit('PROMOTION_DISCOUNT_INSTEAD','custom','100'),$rep['id']);$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'2']];$in['amount_received']='0.00';
 [$r,$in]=$prepare($rep,$in);
 $check($r['benefit_discount_amount']==='200.00'&&$r['grand_total']==='0.00','Full discount zeroes payable');
 $issued=$svc->issue($rep,$in);$zero=$svc->get($rep,$issued['invoice_id']);
 $check($zero['grand_total']==='0.00'&&$zero['outstanding']==='0.00'&&$zero['payment_status']==='PAID','Zero-payable invoice settles as paid');

 // S20: the settlement formula composes with the benefit net exactly (full posting path
 // runs in promo-choice-historical-return.php against the isolated historical-return target).
 $in=$for($benefit('PROMOTION_DISCOUNT_INSTEAD','25'),$rep['id']);$in['request_id']=Inventory::uuid();$in['items']=[['product_id'=>$p1,'quantity'=>'4']];
 [$r]=$prepare($rep,$in);
 $settlement=\App\Services\ProductReturnCalculation::settlement('2000.00',$r['grand_total']);
 $check($r['grand_total']==='300.00'&&$settlement['applied_value']==='300.00'&&$settlement['remaining_payable']==='0.00','Settlement applies at most the benefit net');
 $settlement=\App\Services\ProductReturnCalculation::settlement('2000.00','7500.00');
 $check($settlement['applied_value']==='2000.00'&&$settlement['forfeited_value']==='0.00'&&$settlement['remaining_payable']==='5500.00','Settlement formula matches the approved example');
}finally{
 $pdo->rollBack();
 $ok=true;foreach($before as $t=>$hash){$now=hash('sha256',json_encode($pdo->query('SELECT * FROM `'.$t.'` ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));if($now!==$hash){fwrite(STDERR,"Table changed: $t\n");$ok=false;}}
 $check($ok,'Database unchanged after settlement test rollback');
}
echo "promo-choice-settlement: $count checks passed.\n";
