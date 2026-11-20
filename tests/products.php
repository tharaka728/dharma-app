<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Support\Database;use App\Services\Products;use App\Support\ProductException;use App\Controllers\ProductController;
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||!in_array(getenv('DB_NAME'),['dharma_sales_phase1','dharma_sales_phase9_test'],true)||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'){throw new RuntimeException('Explicit app-only test target required.');}
$pdo=Database::connect();$service=new Products($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok){throw new RuntimeException($label);} $count++;};
$reject=static function(callable $fn,int $status,string $label)use($check){try{$fn();}catch(ProductException $e){$check($e->status===$status,$label);return;}throw new RuntimeException('Unexpectedly accepted: '.$label);};
$before=[];foreach(['products','categories','product_sku_history','audit_log'] as $table){$before[$table]=$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();}
$pdo->beginTransaction();
try{
 $prefix='P3TEST'.strtoupper(bin2hex(random_bytes(6)));
 foreach(['ADMIN','SALES_REP'] as $role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Rollback-only test',$prefix.$role,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$role]);$actors[$role]=['id'=>$pdo->lastInsertId(),'name'=>'Rollback-only test'];}
 $admin=$actors['ADMIN'];$rep=$actors['SALES_REP'];
 $category=$service->category($admin,$prefix.' category');
 $check($category!=='','Real category creation');
 $reject(fn()=>$service->category($admin,$prefix.' category'),422,'Duplicate category');
 $reject(fn()=>$service->category($rep,'forbidden'),403,'Rep category denial');
 $input=['sku'=>$prefix,'name'=>'Rollback-only <product>','category_id'=>$category,'unit_of_measure'=>'bottle','selling_price'=>'0.10','cost_price'=>'','description'=>'Rollback-only description'];
 $check(Products::money('1')==='1.00','Whole price normalization');
 $check(Products::money('1.2')==='1.20','One decimal normalization');
 $check(Products::money('0')==='0.00','Zero price');
 $check(Products::money('',true)===null,'Optional cost null');
 $check(Products::money('9999999999999.99')==='9999999999999.99','Maximum precision');
 foreach(['-1.00','1.001','1e2','1,000.00','10000000000000.00','NaN',' 1.00','00.10',0.1,1,null,[]] as $invalid){$reject(fn()=>Products::money($invalid),422,'Invalid or non-string price');}
 foreach(['sku'=>'','name'=>'','category_id'=>'','unit_of_measure'=>'','description'=>str_repeat('x',5001),'selling_price'=>'','cost_price'=>'-1'] as $key=>$invalid){$candidate=$input;$candidate[$key]=$invalid;$reject(fn()=>Products::validate($candidate),422,'Invalid '.$key);}
 foreach(['sku'=>101,'name'=>201,'unit_of_measure'=>41] as $key=>$length){$candidate=$input;$candidate[$key]=str_repeat('x',$length);$reject(fn()=>Products::validate($candidate),422,'Length '.$key);}
 $bad=$input;$bad['category_id']='999999999999999999';$reject(fn()=>$service->save($admin,$bad),422,'Missing category');
 $reject(fn()=>$service->save($rep,$input),403,'Rep create denial');
 $product=$service->save($admin,$input);$id=(string)$product['id'];
 $check($product['selling_price']==='0.10' && $product['cost_price']===null,'Exact stored money and unknown cost');
 $check($product['unit_of_measure']==='bottle'&&$product['description']===$input['description'],'Additional fields persist');
 $_SESSION=['csrf_token'=>bin2hex(random_bytes(32))];$_GET=[];
 $render=static function(string $path,string $method='GET',array $form=[])use($service,$admin):array{
  $_SERVER['REQUEST_METHOD']=$method;$_POST=$form;http_response_code(200);ob_start();
  try{(new ProductController($service))->handle(ProductController::route($path),$admin);return ['body'=>ob_get_contents(),'status'=>http_response_code()];}finally{ob_end_clean();}
 };
 $form=$render('/admin/products/'.$id.'/edit');
 $check($form['status']===200 && strpos($form['body'],'name="version" value="'.$product['version'].'"')!==false,'Edit form retains version');
 $check(strpos($form['body'],'value="'.$category.'" selected')!==false,'Edit form retains category selection');
 $check(strpos($form['body'],'Rollback-only &lt;product&gt;')!==false,'Form escapes stored product text');
 $detail=$render('/admin/products/'.$id);
 $check($detail['status']===200 && strpos($detail['body'],'Not provided')!==false && strpos($detail['body'],'Confirm deactivation')!==false,'Product detail and explicit status form');
 $controllerInput=$input;$controllerInput['sku']=$prefix.'API';$controllerInput['csrf_token']=$_SESSION['csrf_token'];
 $created=$render('/api/v1/admin/products','POST',$controllerInput);$payload=json_decode($created['body'],true,512,JSON_THROW_ON_ERROR);
 $check($created['status']===201 && $payload['product']['selling_price']==='0.10' && $payload['product']['cost_price']===null && $payload['currency']==='LKR','Create API controller uses decimal strings and null cost');
 $controllerInput['version']=(string)$payload['product']['version'];$controllerInput['selling_price']='42.01';
 $apiId=(string)$payload['product']['id'];$changed=$render('/api/v1/admin/products/'.$apiId,'POST',$controllerInput);
 $check($changed['status']===200 && json_decode($changed['body'],true)['product']['selling_price']==='42.01','Edit API controller persists exact money');
 $apiVersion=(string)json_decode($changed['body'],true)['product']['version'];
 $status=$render('/api/v1/admin/products/'.$apiId.'/status','POST',['csrf_token'=>$_SESSION['csrf_token'],'version'=>$apiVersion,'is_active'=>'0']);
 $check($status['status']===200 && (int)json_decode($status['body'],true)['product']['is_active']===0,'Status API controller');
 try{$pdo->prepare('UPDATE products SET cost_price=-1 WHERE id=?')->execute([$id]);throw new RuntimeException('Negative cost accepted by database');}catch(PDOException $e){$check(in_array((int)($e->errorInfo[1]??0),[3819,4025],true),'Existing cost CHECK retained after nullable expansion');}
 try{$pdo->prepare('UPDATE products SET selling_price=-1 WHERE id=?')->execute([$id]);throw new RuntimeException('Negative selling price accepted by database');}catch(PDOException $e){$check(in_array((int)($e->errorInfo[1]??0),[3819,4025],true),'Selling price CHECK retained');}
 try{$pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);throw new RuntimeException('Historical product deletion accepted');}catch(PDOException $e){$check((int)($e->errorInfo[1]??0)===1451,'SKU history prevents destructive deletion');}
 $reject(fn()=>$service->save($admin,$input),422,'Duplicate SKU');
 $same=$input;$same['sku']=strtolower($prefix);$reject(fn()=>$service->save($admin,$same),422,'Case-insensitive duplicate');
 $update=$input;$update['sku']=$prefix.'NEW';$update['selling_price']='9999999999999.99';$update['cost_price']='0.00';$update['version']=(string)$product['version'];
 $edited=$service->save($admin,$update,$id);
 $check($edited['selling_price']==='9999999999999.99'&&$edited['cost_price']==='0.00','Editing preserves maximum exact value and known zero cost');
 $reject(fn()=>$service->save($admin,$update,$id),409,'Stale version rejected');
 $reject(fn()=>$service->save($admin,$input),422,'Old SKU remains reserved after edit');
 $reject(fn()=>$service->get($rep,$id),403,'Rep cost/details denial');
 $reject(fn()=>$service->listing($rep,[]),403,'Rep list denial');
 $reject(fn()=>$service->save($rep,$update,$id),403,'Rep edit denial');
 $reject(fn()=>$service->status($rep,$id,(string)$edited['version'],'0'),403,'Rep deactivation denial');
 $reject(fn()=>$service->status($admin,$id,(string)$edited['version'],'false'),422,'Explicit status values required');
 $inactive=$service->status($admin,$id,(string)$edited['version'],'0');
 $check((int)$inactive['is_active']===0 && $inactive['archived_at_utc']!==null && (string)$inactive['id']===$id,'Deactivation preserves identity');
 $reject(fn()=>$service->save($admin,$input),422,'Inactive history cannot be recycled');
 $filters=['q'=>$prefix,'category_id'=>$category,'status'=>'inactive'];$result=$service->listing($admin,$filters);
 $check($result['total']===2 && in_array($id,array_map('strval',array_column($result['products'],'id')),true),'Search/category/inactive filters');
 $filters['status']='active';$check($service->listing($admin,$filters)['total']===0,'Active filter excludes inactive');
 $check($service->listing($admin,['q'=>'%_'.$prefix])['total']===0,'Search wildcard characters treated literally');
 $reject(fn()=>$service->listing($admin,['status'=>[]]),422,'Array filter rejected');
 $reject(fn()=>$service->listing($admin,['page'=>'0']),422,'Invalid page rejected');
 $active=$service->status($admin,$id,(string)$inactive['version'],'1');
 $check((int)$active['is_active']===1 && $active['archived_at_utc']===null,'Reactivation');
 $q=$pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE entity_type='products' AND entity_key=?");$q->execute([$id]);$check((int)$q->fetchColumn()===4,'Atomic append-only create/edit/status audit');
 $pdo->prepare('UPDATE categories SET is_active=0 WHERE id=?')->execute([$category]);
 $again=$update;$again['version']=(string)$active['version'];$again['cost_price']='';
 $check($service->save($admin,$again,$id)['cost_price']===null,'Existing inactive category may be retained');
 $new=$input;$new['sku']=$prefix.'OTHER';$reject(fn()=>$service->save($admin,$new),422,'New product cannot use inactive category');
 $pdo->rollBack();
}finally{if($pdo->inTransaction()){$pdo->rollBack();}}
foreach($before as $table=>$total){$check($pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()===$total,'No retained fixture rows in '.$table);}
echo "$count product validation, permissions, persistence, precision and status checks passed; all products/categories/audits/SKU history fixtures rolled back.\n";
