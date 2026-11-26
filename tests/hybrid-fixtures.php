<?php
declare(strict_types=1);
// Required by the explicitly isolated database suite only.
function hybridUuid(): string { $h=bin2hex(random_bytes(16)); return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-8'.substr($h,17,3).'-'.substr($h,20); }
function hybridFixtures(PDO $pdo,array $manifest): array
{
    $prefix='SYNTHETIC-'.bin2hex(random_bytes(5)); $f=['users'=>[],'products'=>[],'bindings'=>[],'customers'=>[],'locations'=>[]];
    $pdo->beginTransaction();
    try {
        foreach (['admin'=>'ADMIN','rep1'=>'SALES_REP','rep2'=>'SALES_REP'] as $name=>$role) {
            $pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute([$prefix.' '.$name,$prefix.'-'.$name,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$role]); $f['users'][$name]=(string)$pdo->lastInsertId();
        }
        $f['locations']['warehouse']=(string)$pdo->query('SELECT warehouse_location_id FROM inventory_settings WHERE id=1')->fetchColumn();
        foreach (['rep1','rep2'] as $name) {
            $id=$f['users'][$name]; $pdo->prepare("INSERT INTO stock_locations(type,code,rep_id) VALUES ('REP',?,?)")->execute([$prefix.'-'.$name,$id]); $f['locations'][$name]=(string)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO rep_operating_state(rep_id) VALUES (?)')->execute([$id]);
            $pdo->prepare('INSERT INTO customers(shop_name,contact_person,phone,address,area,customer_code,assigned_rep_id,allow_credit_sales,credit_limit_enabled,credit_limit,credit_terms_days) VALUES (?,?,?,?,?,?,?,1,1,100000.00,7)')
                ->execute([$prefix.' shop '.$name,'Synthetic contact','synthetic','Synthetic address','Synthetic area',$prefix.'-C-'.$name,$id]); $f['customers'][$name]=(string)$pdo->lastInsertId();
        }
        $pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([$prefix]); $category=$pdo->lastInsertId();
        $definitions=['glucose'=>['6',500],'suwada'=>['12',500],'ordinary'=>[null,500],'replacement'=>['12',24],'partial'=>['6',13],'limited'=>['6',9],'monetary'=>[null,500],'oil1'=>[null,500],'oil2'=>[null,500]];
        foreach ($definitions as $name=>[$threshold,$stock]) {
            $pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,30.00,100.00)')->execute([$prefix.'-'.$name,$prefix.' '.$name,$category]); $id=(string)$pdo->lastInsertId(); $f['products'][$name]=$id;
            if ($threshold!==null || $name==='monetary') {
                $pdo->prepare('INSERT INTO promotions(name,current_version) VALUES (?,1)')->execute([$prefix.' offer '.$name]); $promotion=(string)$pdo->lastInsertId();
                $type=$name==='monetary'?'PERCENTAGE':'BUY_X_GET_Y';
                $pdo->prepare('INSERT INTO promotion_versions(promotion_id,version_number,product_id,type,percent_rate,buy_quantity,free_quantity,published_by_user_id,name_snapshot) VALUES (?,1,?,?,?,?,?,?,?)')
                    ->execute([$promotion,$id,$type,$type==='PERCENTAGE'?'10.00':null,$threshold,$threshold!==null?'1':null,$f['users']['admin'],$prefix.' offer '.$name]);
                if ($threshold!==null) { $f['bindings'][$id]=['promotion_id'=>$promotion,'buy_quantity'=>$threshold]; }
            }
        }
        $pdo->prepare('INSERT INTO promotions(name,current_version) VALUES (?,1)')->execute([$prefix.' pooled oil']); $oilPromotion=$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO promotion_versions(promotion_id,version_number,product_id,type,buy_quantity,free_quantity,published_by_user_id,name_snapshot,mixed_product_ids) VALUES (?,1,?,'BUY_X_GET_Y',6,1,?,?,?)")
            ->execute([$oilPromotion,$f['products']['oil1'],$f['users']['admin'],$prefix.' pooled oil',json_encode([$f['products']['oil1'],$f['products']['oil2']],JSON_THROW_ON_ERROR)]);
        $opening=hybridUuid(); $pdo->prepare("INSERT INTO inventory_transactions(id,type,reason,created_by_user_id) VALUES (?,'OPENING','Synthetic isolated fixture',?)")->execute([$opening,$f['users']['admin']]); $line=0;
        foreach ($definitions as $name=>[$threshold,$stock]) {
            $id=$f['products'][$name]; $pdo->prepare('INSERT INTO product_stock_balances(product_id,quantity) VALUES (?,?)')->execute([$id,$stock]);
            foreach (['warehouse','rep1','rep2'] as $location) {
                if ($location!=='warehouse') { $pdo->prepare('INSERT INTO representative_stock_balances(rep_id,product_id,quantity) VALUES (?,?,?)')->execute([$f['users'][$location],$id,$stock]); }
                $pdo->prepare('INSERT INTO inventory_movements(transaction_id,line_number,location_id,product_id,quantity_delta,quantity_after) VALUES (?,?,?,?,?,?)')->execute([$opening,++$line,$f['locations'][$location],$id,$stock,$stock]);
            }
        }
        $before=$pdo->query('SELECT * FROM hybrid_operating_state WHERE id=1 FOR UPDATE')->fetch();
        $pdo->exec('UPDATE hybrid_operating_state SET enabled=1,sale_posting=1,version=version+1 WHERE id=1');
        $after=$pdo->query('SELECT * FROM hybrid_operating_state WHERE id=1')->fetch();
        $pdo->prepare('INSERT INTO hybrid_control_events(actor_user_id,previous_version,new_version,reason,previous_json,new_json) VALUES (?,?,?,?,?,?)')->execute([$f['users']['admin'],$before['version'],$after['version'],'Synthetic isolated test activation only',json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    return $f;
}
function hybridTestConfig(array $m,array $f): array { return ['enabled'=>true,'sale_posting'=>true,'return_posting'=>false,'custody'=>false,'financial_posting_hold'=>false,'databases'=>[$m['database']],'configuration_version'=>'HYBRID_SYNTHETIC_STAGE2C','bindings'=>$f['bindings']]; }
function hybridTestService(PDO $pdo,array $m,array $f,?Closure $fault=null): App\Services\Invoices
{
    $store=new App\Services\PdoHybridCheckoutStore($pdo,$m,false,$fault);
    return new App\Services\Invoices($pdo,new App\Services\HybridInvoiceCheckout($store,hybridTestConfig($m,$f)));
}
function hybridInput(array $f,string $product='glucose',string $quantity='8',string $rep='rep1'): array
{
    return ['request_id'=>hybridUuid(),'customer_id'=>$f['customers'][$rep],'payment_type'=>'CASH','amount_received'=>'10000.00',
        'hybrid'=>['policy_version'=>'HYBRID_PROMOTION_V1','lines'=>[['product_id'=>$f['products'][$product],'paid_quantity'=>$quantity,'product_discount'=>['choice'=>'25']]],'invoice_discount_percent'=>'10']];
}
