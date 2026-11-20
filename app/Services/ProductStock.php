<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;use App\Support\ProductException;
/** Product-only central stock. Uses the existing append-only transaction/movement ledger. */
final class ProductStock
{
    public function __construct(private PDO $pdo) {}
    public function admin(array $actor):void{(new Inventory($this->pdo))->admin($actor);}
    private function one(string $sql,array $params=[]):?array{$q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetch()?:null;}
    private function id(mixed $value):string{if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,18}$/D',$value)){throw new InventoryException(['product_id'=>'Choose an existing product.']);}return $value;}
    public function options(array $actor):array{$this->admin($actor);return $this->pdo->query('SELECT id,sku,name,name_si,selling_price,version,is_active FROM products ORDER BY name,id')->fetchAll();}
    public function state(array $actor):array{$this->admin($actor);return $this->one('SELECT s.*,((EXISTS(SELECT 1 FROM inventory_balances WHERE quantity<>0)) OR (EXISTS(SELECT 1 FROM inventory_movements WHERE batch_id IS NOT NULL))) legacy_stock_conflict FROM inventory_settings s WHERE s.id=1')??throw new InventoryException(['setup'=>'Inventory migration required.'],503);}
    public function listing(array $actor,array $filters):array
    {
        $this->admin($actor);$params=[];$where=[];$search=Inventory::text($filters['q']??'','q',100);
        if($search!==''){$like='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(p.name LIKE ? ESCAPE '!' OR p.name_si LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";array_push($params,$like,$like,$like);}
        if(($filters['product_id']??'')!==''){$id=$this->id($filters['product_id']);if(!$this->one('SELECT id FROM products WHERE id=?',[$id]))throw new InventoryException(['product_id'=>'Product not found.']);$where[]='p.id=?';$params[]=$id;}
        
        $page=$filters['page']??'1';if(!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['page'=>'Invalid page.']);
        $sql=' FROM products p LEFT JOIN product_stock_balances b ON b.product_id=p.id'.($where?' WHERE '.implode(' AND ',$where):'');$q=$this->pdo->prepare('SELECT COUNT(*)'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,intdiv($total+24,25));$page=min((int)$page,$pages);
        $q=$this->pdo->prepare("SELECT p.id,p.sku,p.name,p.name_si,p.selling_price,p.is_active,COALESCE(b.quantity,0) quantity,CAST(COALESCE(b.quantity,0) AS DECIMAL(20,0))*p.selling_price stock_value".$sql.' ORDER BY p.name,p.id LIMIT 25 OFFSET '.(($page-1)*25));$q->execute($params);
        return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public function history(array $actor,array $filters):array
    {
        $this->admin($actor);$where=['m.batch_id IS NULL','m.location_id=(SELECT warehouse_location_id FROM inventory_settings WHERE id=1)'];$params=[];
        if(($filters['product_id']??'')!==''){$where[]='m.product_id=?';$params[]=$this->id($filters['product_id']);}
        $search=Inventory::text($filters['q']??'','q',100);if($search!==''){$like='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(p.sku LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!' OR p.name_si LIKE ? ESCAPE '!' OR m.transaction_id LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM business_code_registry r WHERE r.request_key=m.transaction_id AND r.prefix IN (0x53,0x41) AND r.code LIKE ? ESCAPE '!'))";array_push($params,$like,$like,$like,$like,$like);}
        $page=$filters['page']??'1';if(!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['page'=>'Invalid page.']);
        $sql=' FROM inventory_movements m JOIN inventory_transactions t ON t.id=m.transaction_id JOIN products p ON p.id=m.product_id JOIN users u ON u.id=t.created_by_user_id WHERE '.implode(' AND ',$where);
        $q=$this->pdo->prepare('SELECT COUNT(*)'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,intdiv($total+49,50));$page=min((int)$page,$pages);
        $q=$this->pdo->prepare('SELECT (SELECT code FROM business_code_registry WHERE request_key=m.transaction_id AND prefix IN (0x53,0x41) ORDER BY prefix LIMIT 1) reference_code,m.transaction_id,m.quantity_delta,m.quantity_after,m.created_at_utc,p.sku,p.name,p.name_si,t.type,t.reason,t.selling_price_snapshot,u.name admin_name'.$sql.' ORDER BY m.id DESC LIMIT 50 OFFSET '.(($page-1)*50));$q->execute($params);return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public function reconciliation(array $actor):array
    {
        $this->admin($actor);return $this->pdo->query('SELECT k.product_id,COALESCE(b.quantity,0) balance,COALESCE(l.quantity,0) ledger FROM (SELECT product_id FROM product_stock_balances UNION SELECT product_id FROM inventory_movements WHERE batch_id IS NULL AND location_id=(SELECT warehouse_location_id FROM inventory_settings WHERE id=1)) k LEFT JOIN product_stock_balances b ON b.product_id=k.product_id LEFT JOIN (SELECT product_id,SUM(quantity_delta) quantity FROM inventory_movements WHERE batch_id IS NULL AND location_id=(SELECT warehouse_location_id FROM inventory_settings WHERE id=1) GROUP BY product_id) l ON l.product_id=k.product_id WHERE COALESCE(b.quantity,0)<>COALESCE(l.quantity,0)')->fetchAll();
    }
    public function post(array $actor,array $input,bool $adjust=false):array
    {
        $this->admin($actor);$id=$this->id($input['product_id']??null);$qty=Inventory::quantity($input['quantity']??null);$uuid=$input['request_id']??null;
        if(!is_string($uuid)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$uuid))throw new InventoryException(['request_id'=>'Refresh the stock form to obtain a request ID.']);
        $direction=$adjust?($input['direction']??null):'IN';if(!in_array($direction,['IN','OUT'],true))throw new InventoryException(['direction'=>'Choose add or remove.']);
        $reason=$adjust?Inventory::text($input['reason']??'','reason',500,true):'Stock added by admin';$price=null;$version=null;
        if(!$adjust){try{$price=Products::money($input['selling_price']??null);}catch(ProductException $e){throw new InventoryException(['selling_price'=>'Enter a nonnegative LKR price with at most two decimal places.']);}$version=$input['product_version']??null;if(!is_string($version)||!preg_match('/^[1-9][0-9]{0,18}$/D',$version))throw new InventoryException(['product_version'=>'Select a product and reload its current price.']);}
        $hash=hash('sha256',json_encode([$id,(string)$qty,$adjust,$direction,$reason,$price,$version],JSON_THROW_ON_ERROR));$owner=!$this->pdo->inTransaction();
        for($attempt=0;;$attempt++){
            if($owner){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$this->pdo->beginTransaction();}else{$this->pdo->exec('SAVEPOINT product_stock_command');}
            try{
                $settings=$this->one('SELECT s.*,l.is_active location_active,l.type location_type FROM inventory_settings s LEFT JOIN stock_locations l ON l.id=s.warehouse_location_id WHERE s.id=1 FOR UPDATE');
                if(!$settings||$settings['stock_model']!=='PRODUCT'||!$settings['warehouse_location_id']||!(int)$settings['location_active']||$settings['location_type']!=='WAREHOUSE')throw new InventoryException(['compatibility'=>'Product stock is not enabled. Existing batch stock requires a separately reviewed conversion.'],409);
                $product=$this->one('SELECT * FROM products WHERE id=? FOR UPDATE',[$id]);if(!$product)throw new InventoryException(['product_id'=>'Product not found.']);
                $old=$this->one('SELECT * FROM inventory_transactions WHERE id=? FOR UPDATE',[$uuid]);
                if($old){if($old['payload_hash']!==$hash||(string)$old['created_by_user_id']!==(string)$actor['id'])throw new InventoryException(['duplicate'=>'This request ID was used with different details.'],409);$result=['transaction_id'=>$uuid,'replayed'=>true];}
                else{
                    if(!$adjust&&!(int)$product['is_active'])throw new InventoryException(['product_id'=>'Activate the product before adding stock.']);
                    if(!$adjust&&$version!==(string)$product['version'])throw new InventoryException(['price'=>'Product details or price changed. Reload before saving; no stock was added.'],409);
                    // Never ignore newly discovered legacy stock or mix it into product balances.
                    if($this->one('SELECT batch_id FROM inventory_balances WHERE quantity<>0 LIMIT 1 FOR UPDATE')||$this->one('SELECT id FROM inventory_movements WHERE batch_id IS NOT NULL LIMIT 1 FOR UPDATE'))throw new InventoryException(['legacy'=>'Batch stock/history exists. Posting blocked until a reviewed compatibility conversion.'],409);
                    $this->pdo->prepare('INSERT INTO product_stock_balances(product_id,quantity) VALUES (?,0) ON DUPLICATE KEY UPDATE quantity=quantity')->execute([$id]);
                    $balance=$this->one('SELECT * FROM product_stock_balances WHERE product_id=? FOR UPDATE',[$id]);$before=(int)$balance['quantity'];
                    $sum=$this->one('SELECT COALESCE(SUM(quantity_delta),0) quantity FROM inventory_movements WHERE product_id=? AND batch_id IS NULL AND location_id=(SELECT warehouse_location_id FROM inventory_settings WHERE id=1) FOR UPDATE',[$id]);if((string)$sum['quantity']!==(string)$before)throw new InventoryException(['ledger'=>'Stock reconciliation failed; investigate before posting.'],409);
                    if($direction==='OUT'&&$qty>$before)throw new InventoryException(['quantity'=>'Insufficient stock. Quantity cannot become negative.'],409);
                    if($direction==='IN'&&$qty>PHP_INT_MAX-$before)throw new InventoryException(['quantity'=>'Resulting quantity exceeds the supported integer range.']);
                    $delta=$direction==='IN'?$qty:-$qty;$after=$before+$delta;
                    if(!$adjust&&$price!==$product['selling_price']){
                        $this->pdo->prepare('UPDATE products SET selling_price=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute([$price,$id]);
                        $this->audit($actor,'PRODUCT_PRICE_STOCK','products',$id,['selling_price'=>$product['selling_price'],'version'=>(string)$product['version']],['selling_price'=>$price,'version'=>(string)((int)$product['version']+1)],'Selling price explicitly changed during stock entry');
                    }
                    $postedPrice=$adjust?$product['selling_price']:$price;
                    $this->pdo->prepare('INSERT INTO inventory_transactions(id,type,reason,created_by_user_id,payload_hash,selling_price_snapshot,receipt_date) VALUES (?,?,?,?,?,?,?)')->execute([$uuid,$adjust?'ADJUSTMENT':'RECEIPT',$reason,$actor['id'],$hash,$postedPrice,$adjust?null:Inventory::today()]);
                    $this->pdo->prepare('INSERT INTO inventory_movements(transaction_id,line_number,location_id,product_id,batch_id,batch_version_id,quantity_delta,quantity_after) VALUES (?,1,?,?,NULL,NULL,?,?)')->execute([$uuid,$settings['warehouse_location_id'],$id,$delta,$after]);
                    $this->pdo->prepare('UPDATE product_stock_balances SET quantity=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE product_id=?')->execute([$after,$id]);
                    $this->audit($actor,'PRODUCT_STOCK_'.$direction,'inventory_transactions',$uuid,['quantity'=>(string)$before],['product_id'=>$id,'quantity'=>(string)$after,'delta'=>(string)$delta],$reason);
                    \App\Support\BusinessCode::next($this->pdo,'S',$uuid);$result=['transaction_id'=>$uuid,'quantity'=>(string)$after,'replayed'=>false];
                }
                if($owner)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT product_stock_command');return $result;
            }catch(\Throwable $e){if($owner&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$owner&&$this->pdo->inTransaction())$this->pdo->exec('ROLLBACK TO SAVEPOINT product_stock_command');$code=$e instanceof \PDOException?(int)($e->errorInfo[1]??0):0;if($owner&&in_array($code,[1205,1213],true)&&$attempt<2)continue;if(in_array($code,[1205,1213,1062],true))throw new InventoryException(['conflict'=>'Stock request conflict. Retry with the same request ID.'],409);throw $e;}
        }
    }
    private function audit(array $actor,string $action,string $entity,string $id,array $before,array $after,string $reason):void
    {
        $this->pdo->prepare('INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,reason,before_json,after_json) VALUES (?,?,?,?,?,?,?)')->execute([$actor['id'],$action,$entity,$id,$reason,json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);
    }
}
