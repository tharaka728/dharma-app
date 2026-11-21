<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Invoices,BenefitPolicy};
use App\Support\{Database,InventoryException};
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_NAME')!=='dharma_sales_phase9_test'||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309')throw new RuntimeException('Exact isolated test target required.');
if(getenv('DHARMA_BENEFIT_TEST')==='1')throw new RuntimeException('This test verifies the closed gate; run it without DHARMA_BENEFIT_TEST.');
$pdo=Database::connect();$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
$check(BenefitPolicy::enabled($pdo)===false,'Gate closed without the isolated test flag');
$svc=new Invoices($pdo);$pdo->beginTransaction();
try{
 $tag='GATE'.strtoupper(bin2hex(random_bytes(5)));
 $pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Gate fixture',$tag,password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT),'SALES_REP']);$rep=['id'=>$pdo->lastInsertId()];
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$tag]);$cat=$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)')->execute([$tag,'Gate product',$cat,'10.00']);$product=$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO customers(customer_code,shop_name,contact_person,phone,address,area,assigned_rep_id,credit_terms_days,credit_limit_enabled) VALUES (?,'Gate customer','Fixture','0110000000','Gate address','',?,30,0)")->execute([$tag,$rep['id']]);$customer=$pdo->lastInsertId();
 $input=['request_id'=>\App\Services\Inventory::uuid(),'customer_id'=>$customer,'stock_source'=>$rep['id'],'payment_type'=>'CASH','amount_received'=>'10.00','items'=>[['product_id'=>$product,'quantity'=>'1']],'benefit_policy'=>'PROMO_CHOICE_V1','benefit_mode'=>'FREE_PROMOTION'];
 foreach(['review','issue'] as $action){try{$svc->$action($rep,$input);throw new RuntimeException('Unexpected acceptance');}catch(InventoryException $e){$check($e->status===409&&$e->errors['benefit']==='Benefit simulation is not available for checkout.','Safeguard message preserved for '.$action);}}
 $choiceOnly=$input;unset($choiceOnly['benefit_policy'],$choiceOnly['benefit_mode']);$choiceOnly['promotion_discount_choice']='25';
 try{$svc->review($rep,$choiceOnly);throw new RuntimeException('Unexpected acceptance');}catch(InventoryException $e){$check($e->status===409,'Choice-only forgery also blocked');}
}finally{$pdo->rollBack();}
echo "promo-choice-gate-off: $count checks passed\n";
