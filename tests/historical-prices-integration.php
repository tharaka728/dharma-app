<?php
declare(strict_types=1);
// Synthetic fixtures are rolled back on the verified isolated restore only.
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\HistoricalReturnPrices;use App\Support\InventoryException;
$schema='dharma_sales_phase12_restore_20261118154343';
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname='.$schema.';charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);unset($ini,$auth);
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$schema||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected target.');
$checks=0;$assert=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$reject=static function(callable $fn,int $status)use($assert){try{$fn();}catch(InventoryException $e){$assert($e->status===$status,'Unexpected rejection status');return;}throw new RuntimeException('Unexpected acceptance');};
$pdo->beginTransaction();try{
 $tag='HT'.strtoupper(bin2hex(random_bytes(6)));$actors=[];
 foreach(['admin'=>'ADMIN','rep'=>'SALES_REP'] as $key=>$role){$q=$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)');$q->execute(['Synthetic test',$tag.$key,password_hash($tag,PASSWORD_DEFAULT),$role]);$actors[$key]=['id'=>$pdo->lastInsertId()];}
 $q=$pdo->prepare('INSERT INTO categories(name) VALUES (?)');$q->execute([$tag]);$cat=$pdo->lastInsertId();
 $q=$pdo->prepare('INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES (?,?,?,NULL,?)');$q->execute([$tag,'Synthetic test product',$cat,'87.00']);$product=$pdo->lastInsertId();
 $service=new HistoricalReturnPrices($pdo);$input=['product_id'=>$product,'price_year'=>'2024','unit_price'=>'45.50','reason'=>'Synthetic test','expected_revision'=>''];
 $reject(fn()=>$service->save($actors['rep'],$input),403);
 $reject(fn()=>$service->save($actors['admin'],array_replace($input,['unit_price'=>'-1'])),422);
 $reject(fn()=>$service->save($actors['admin'],array_replace($input,['unit_price'=>'1.001'])),422);
 $first=$service->save($actors['admin'],$input);$assert($first['revision']==='1','First revision');
 $next=array_replace($input,['unit_price'=>'47.00','expected_revision'=>'1']);$reject(fn()=>$service->save($actors['admin'],$next),422);
 $next['confirm_change']='1';$second=$service->save($actors['admin'],$next);$assert($second['revision']==='2','Second revision');
 $reject(fn()=>$service->save($actors['admin'],$next),409);
 $service->save($actors['admin'],array_replace($input,['price_year'=>'2025','unit_price'=>'50.00']));
 $q=$pdo->prepare("SELECT entity_key,reason,before_json,after_json FROM audit_log WHERE action='HISTORICAL_RETURN_PRICE' AND entity_key IN (?,?) ORDER BY id");$q->execute([$first['id'],$second['id']]);$audit=$q->fetchAll();$assert(count($audit)===2&&$audit[0]['reason']==='Synthetic test'&&json_decode($audit[1]['before_json'],true)['unit_price']==='45.50'&&json_decode($audit[1]['after_json'],true)['unit_price']==='47.00','Price creation and revision audit records preserve before and after values');
 $history=$service->history($actors['admin'],(string)$product,'2024');$assert(count($history)===2&&$history[1]['unit_price']==='45.50','History preserves old price');
 $rows=$service->listing($actors['admin'],['year'=>'2024','q'=>$tag]);$assert(count($rows)===1&&$rows[0]['unit_price']==='47.00','Listing shows latest price');
 $rows=$service->approved($actors['rep'],['year'=>'2025','q'=>$tag]);$assert(count($rows)===1&&$rows[0]['unit_price']==='50.00'&&!array_key_exists('cost_price',$rows[0]),'Representative sees only approved price and no cost');
 $service->save($actors['admin'],array_replace($next,['status'=>'DISABLED','expected_revision'=>'2']));
 $assert($service->approved($actors['rep'],['year'=>'2024','q'=>$tag])===[],'Disabled latest revision is not offered');
 $q=$pdo->prepare('SELECT selling_price FROM products WHERE id=?');$q->execute([$product]);$assert($q->fetchColumn()==='87.00','Selling price unchanged');
 try{$pdo->exec('UPDATE historical_return_price_revisions SET unit_price=0 WHERE id='.(int)$first['id']);throw new RuntimeException('Revision is mutable');}catch(PDOException $e){$assert((int)$e->errorInfo[1]===1644,'Immutable revision');}
}finally{$pdo->rollBack();}
echo "$checks historical price integration checks passed on isolated restore; synthetic changes rolled back.\n";
