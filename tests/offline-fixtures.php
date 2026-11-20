<?php
declare(strict_types=1);
function offlineFixtures(PDO $pdo):array
{
 if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase9_test')throw new RuntimeException('Synthetic sales are allowed only in the dedicated Phase 9 test database.');
 $tag='P9'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(18));$actors=[];
 foreach(['admin'=>'ADMIN','rep'=>'SALES_REP','other'=>'SALES_REP'] as $key=>$role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Isolated PWA '.$key,$tag.$key,password_hash($password,PASSWORD_DEFAULT),$role]);$actors[$key]=['id'=>$pdo->lastInsertId(),'name'=>'Isolated PWA '.$key,'role'=>$role,'username'=>$tag.$key];}
 $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$tag]);$cat=$pdo->lastInsertId();$products=[];
 foreach(['125.50','0.05'] as $ix=>$price){$pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)')->execute([$tag.$ix,'Isolated product '.$ix,$cat,$price]);$p=$pdo->lastInsertId();$products[]=$p;(new App\Services\ProductStock($pdo))->post($actors['admin'],['request_id'=>App\Services\Inventory::uuid(),'product_id'=>$p,'quantity'=>'200','selling_price'=>$price,'product_version'=>'1']);(new App\Services\RepresentativeStock($pdo))->post($actors['admin'],['request_id'=>App\Services\Inventory::uuid(),'rep_id'=>$actors['rep']['id'],'product_id'=>$p,'quantity'=>'80'],'ALLOCATE');}
 $pdo->prepare("INSERT INTO customers(customer_code,shop_name,contact_person,phone,address,area,assigned_rep_id,credit_limit_enabled) VALUES (?,'Isolated shop','Test contact','0110000000','Isolated test address','',?,0)")->execute([$tag,$actors['rep']['id']]);$customer=$pdo->lastInsertId();
 $offer=(new App\Services\Promotions($pdo))->save($actors['admin'],['request_id'=>App\Services\Inventory::uuid(),'name'=>$tag.' Buy2Get1','description'=>'Test database only','product_id'=>$products[0],'type'=>'BUY_X_GET_Y','buy_quantity'=>'2','free_quantity'=>'1','percent_rate'=>'','fixed_discount'=>'','start_date'=>'','end_date'=>'','is_active'=>'1']);
 return ['actors'=>$actors,'password'=>$password,'products'=>$products,'customer'=>$customer,'promotion'=>$offer['promotion_id']];
}
