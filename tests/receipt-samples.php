<?php
declare(strict_types=1);
// Generates clearly-marked SYNTHETIC sample receipts for the three benefit modes and the
// approved 0% no-benefit case (spec section 16). Isolated test database only; everything rolls back.
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{Invoices,Inventory,ProductStock,RepresentativeStock,Promotions,InvoiceDocuments};
use App\Support\{Database,I18n};
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_NAME')!=='dharma_sales_phase9_test'||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309')throw new RuntimeException('Exact isolated test target required.');
if(getenv('DHARMA_BENEFIT_TEST')!=='1')throw new RuntimeException('Isolated benefit gate flag required.');
$pdo=Database::connect();$svc=new Invoices($pdo);$docs=new InvoiceDocuments($pdo);
$out=dirname(__DIR__).'/docs/receipt-samples';if(!is_dir($out))mkdir($out,0755,true);
$pdo->beginTransaction();
try{
 $tag='RCPT'.strtoupper(bin2hex(random_bytes(4)));
 $pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Sample admin '.$tag,$tag.'a',password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT),'ADMIN']);$admin=['id'=>$pdo->lastInsertId()];
 $pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Sample rep '.$tag,$tag.'r',password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT),'SALES_REP']);$rep=['id'=>$pdo->lastInsertId(),'role'=>'SALES_REP'];
 putenv('DHARMA_BENEFIT_TEST_PILOT_IDS='.$rep['id']);
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$tag]);$cat=$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)')->execute([$tag.'0','Sample rice 5kg '.$tag,$cat,'125.50']);$pA=$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)')->execute([$tag.'1','Sample oil 1L '.$tag,$cat,'400.00']);$pB=$pdo->lastInsertId();
 (new ProductStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'product_id'=>$pA,'quantity'=>'500','selling_price'=>'125.50','product_version'=>'1']);
 (new ProductStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'product_id'=>$pB,'quantity'=>'500','selling_price'=>'400.00','product_version'=>'1']);
 foreach([$pA,$pB] as $p)(new RepresentativeStock($pdo))->post($admin,['request_id'=>Inventory::uuid(),'rep_id'=>$rep['id'],'product_id'=>$p,'quantity'=>'100'],'ALLOCATE');
 $pdo->prepare("INSERT INTO customers(customer_code,shop_name,contact_person,phone,address,area,assigned_rep_id,credit_terms_days,credit_limit_enabled) VALUES (?,'SAMPLE TEST SHOP','Fixture','0110000000','Sample address','',?,30,0)")->execute([$tag,$rep['id']]);$customer=$pdo->lastInsertId();
 $promos=new Promotions($pdo);
 $promos->save($admin,['request_id'=>Inventory::uuid(),'name'=>'Sample buy get '.$tag,'description'=>'','product_id'=>$pA,'type'=>'BUY_X_GET_Y','buy_quantity'=>'4','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 $base=['customer_id'=>$customer,'stock_source'=>$rep['id'],'payment_type'=>'CASH','amount_received'=>'100000.00'];
 $cases=[
  'free-promotion'=>['benefit_mode'=>'FREE_PROMOTION'],
  'promotion-discount-instead-25'=>['benefit_mode'=>'PROMOTION_DISCOUNT_INSTEAD','promotion_discount_choice'=>'25'],
  'ordinary-invoice-discount-35'=>['benefit_mode'=>'ORDINARY_INVOICE_DISCOUNT','promotion_discount_choice'=>'35'],
  'no-benefit-ordinary-0'=>['benefit_mode'=>'ORDINARY_INVOICE_DISCOUNT','promotion_discount_choice'=>'0'],
 ];
 $banner='<div style="position:fixed;top:0;left:0;right:0;background:#b91c1c;color:#fff;font:700 12px sans-serif;text-align:center;padding:2px;z-index:9999">SYNTHETIC TEST RECEIPT &mdash; NOT A REAL SALE</div>';
 foreach($cases as $slug=>$b){
  $in=$base+['benefit_policy'=>'PROMO_CHOICE_V1']+$b;$in['request_id']=Inventory::uuid();
  $in['items']=[['product_id'=>$pA,'quantity'=>'4'],['product_id'=>$pB,'quantity'=>'2']];
  $r=$svc->review($rep,$in);$in['review_hash']=$r['review_hash'];$issued=$svc->issue($rep,$in);
  $record=$docs->snapshot($rep,$issued['invoice_id']);
  foreach([['receipt','58','en'],['receipt','80','en'],['receipt','58','si-LK'],['a4',null,'en']] as [$layout,$paper,$lang]){
   $html=InvoiceDocuments::render($record,$layout,'/rep/invoices',false,I18n::valid($lang),$paper??'80');
   $html=preg_replace('/<\/body>/',$banner.'</body>',$html,1);
   $name=$out.'/TEST-'.$slug.'-'.$layout.($paper?'-'.$paper.'mm':'').'-'.$lang.'.html';
   file_put_contents($name,$html);
  }
 }
 $n=count($cases)*4;echo "$n synthetic sample receipt files written to docs/receipt-samples (fixtures rolled back).\n";
}finally{$pdo->rollBack();}
