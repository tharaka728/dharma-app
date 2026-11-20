<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;
final class RepresentativeStock
{
    public function __construct(private PDO $pdo){}
    private function one(string $sql,array $params=[]):?array{$q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetch()?:null;}
    public function admin(array $actor):void{(new Inventory($this->pdo))->admin($actor);}
    private function scope(array $actor,array $filters,bool $own):?string
    {
        if(!$own){$this->admin($actor);if(($filters['rep_id']??'')==='')return null;$id=Representatives::id($filters['rep_id']);(new Representatives($this->pdo))->get($actor,$id);return $id;}
        $user=$this->one('SELECT role,is_active FROM users WHERE id=?',[$actor['id']??'0']);if(!$user||$user['role']!=='SALES_REP'||!(int)$user['is_active'])throw new InventoryException(['access'=>'Active sales representative access required.'],403);
        if(array_key_exists('rep_id',$filters)&&$filters['rep_id']!==''&&$filters['rep_id']!==(string)$actor['id'])throw new InventoryException(['access'=>'You can view only your own stock.'],403);
        return (string)$actor['id'];
    }
    private function filters(array $actor,array $filters,bool $own):array
    {
        $id=$this->scope($actor,$filters,$own);$params=[];$where=[];
        if($id!==null){$where[]='l.rep_id=?';$params[]=$id;}
        if(($filters['product_id']??'')!==''){$where[]='p.id=?';$params[]=Representatives::id($filters['product_id'],'product_id');}
        return [$where,$params];
    }
    private function page(array $filters,int $total,int $size):array
    {
        $page=$filters['page']??'1';if(!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['page'=>'Invalid page.']);$pages=max(1,intdiv($total+$size-1,$size));return [min((int)$page,$pages),$pages];
    }
    public function balances(array $actor,array $filters,bool $own=false):array
    {
        [$where,$params]=$this->filters($actor,$filters,$own);$where[]="l.type='REP'";
        $sql=' FROM representative_stock_balances b JOIN users u ON u.id=b.rep_id JOIN stock_locations l ON l.rep_id=b.rep_id JOIN products p ON p.id=b.product_id WHERE '.implode(' AND ',$where);$q=$this->pdo->prepare('SELECT COUNT(*)'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();[$page,$pages]=$this->page($filters,$total,25);
        $q=$this->pdo->prepare('SELECT u.id rep_id,u.name rep_name,u.is_active rep_active,p.id product_id,p.sku,p.name product_name,p.name_si,p.selling_price,p.is_active product_active,b.quantity'.$sql.' ORDER BY u.name,u.id,p.name,p.id LIMIT 25 OFFSET '.(($page-1)*25));$q->execute($params);return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public function history(array $actor,array $filters,bool $own=false):array
    {
        [$where,$params]=$this->filters($actor,$filters,$own);$where[]="l.type='REP' AND m.batch_id IS NULL";
        $sql=' FROM inventory_movements m JOIN stock_locations l ON l.id=m.location_id JOIN users u ON u.id=l.rep_id JOIN products p ON p.id=m.product_id JOIN inventory_transactions t ON t.id=m.transaction_id JOIN users a ON a.id=t.created_by_user_id WHERE '.implode(' AND ',$where);$q=$this->pdo->prepare('SELECT COUNT(*)'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();[$page,$pages]=$this->page($filters,$total,50);
        // Explicit whitelist: never costs, password material, other reps, or warehouse balances.
        $q=$this->pdo->prepare('SELECT (SELECT code FROM business_code_registry WHERE prefix=0x41 AND request_key=m.transaction_id) reference_code,m.transaction_id,m.created_at_utc,m.quantity_delta,m.quantity_after,t.type,t.reason,u.id rep_id,u.name rep_name,p.id product_id,p.sku,p.name product_name,p.name_si,a.name admin_name'.$sql.' ORDER BY m.id DESC LIMIT 50 OFFSET '.(($page-1)*50));$q->execute($params);return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public function options(array $actor):array
    {
        $this->admin($actor);return ['representatives'=>$this->pdo->query("SELECT (SELECT code FROM business_code_registry WHERE prefix=0x52 AND request_key=CAST(users.id AS CHAR)) reference_code,id,name,username,is_active FROM users WHERE role='SALES_REP' ORDER BY name,id")->fetchAll(),'products'=>(new ProductStock($this->pdo))->options($actor)];
    }
    public function reconciliation(array $actor):array
    {
        $this->admin($actor);return $this->pdo->query("SELECT k.rep_id,k.product_id,COALESCE(b.quantity,0) balance,COALESCE(m.quantity,0) ledger FROM (SELECT rep_id,product_id FROM representative_stock_balances UNION SELECT l.rep_id,m.product_id FROM inventory_movements m JOIN stock_locations l ON l.id=m.location_id WHERE l.type='REP' AND m.batch_id IS NULL) k LEFT JOIN representative_stock_balances b ON b.rep_id=k.rep_id AND b.product_id=k.product_id LEFT JOIN (SELECT l.rep_id,m.product_id,SUM(m.quantity_delta) quantity FROM inventory_movements m JOIN stock_locations l ON l.id=m.location_id WHERE l.type='REP' AND m.batch_id IS NULL GROUP BY l.rep_id,m.product_id) m ON m.rep_id=k.rep_id AND m.product_id=k.product_id WHERE COALESCE(b.quantity,0)<>COALESCE(m.quantity,0)")->fetchAll();
    }
    public function post(array $actor,array $input,string $command):array
    {
        $this->admin($actor);if(!in_array($command,['ALLOCATE','RETURN','CORRECTION'],true))throw new InventoryException(['command'=>'Invalid stock operation.']);
        $rep=Representatives::id($input['rep_id']??null);$product=Representatives::id($input['product_id']??null,'product_id');$qty=Inventory::quantity($input['quantity']??null);$id=$input['request_id']??null;
        if(!is_string($id)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$id))throw new InventoryException(['request_id'=>'Refresh the form to obtain a request ID.']);
        $direction=$command==='CORRECTION'?($input['direction']??null):($command==='ALLOCATE'?'IN':'OUT');if(!in_array($direction,['IN','OUT'],true))throw new InventoryException(['direction'=>'Choose an increase or decrease.']);
        $reason=Inventory::text($input['reason']??($command==='ALLOCATE'?'Stock allocated to representative':($command==='RETURN'?'Stock returned to warehouse':'')),'reason',500,true);
        $hash=hash('sha256',json_encode([$command,$rep,$product,(string)$qty,$direction,$reason],JSON_THROW_ON_ERROR));$own=!$this->pdo->inTransaction();
        for($attempt=0;;$attempt++){
            if($own){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$this->pdo->beginTransaction();}else $this->pdo->exec('SAVEPOINT rep_stock_command');
            try{
                // Shared Phase 5 gate first: existing Add Stock and all transfers use the same serialization point.
                $s=$this->one('SELECT s.*,l.type location_type,l.is_active location_active FROM inventory_settings s LEFT JOIN stock_locations l ON l.id=s.warehouse_location_id WHERE s.id=1 FOR UPDATE');
                if(!$s||$s['stock_model']!=='PRODUCT'||$s['location_type']!=='WAREHOUSE'||!(int)$s['location_active'])throw new InventoryException(['setup'=>'Active product-level warehouse stock is required.'],409);
                $r=(new Representatives($this->pdo))->get($actor,$rep,true);
                $this->pdo->prepare('INSERT INTO rep_operating_state(rep_id) VALUES (?) ON DUPLICATE KEY UPDATE rep_id=rep_id')->execute([$rep]);$this->one('SELECT inventory_revision FROM rep_operating_state WHERE rep_id=? FOR UPDATE',[$rep]);
                $p=$this->one('SELECT id,selling_price,is_active FROM products WHERE id=? FOR UPDATE',[$product]);if(!$p)throw new InventoryException(['product_id'=>'Choose an existing product.']);
                $old=$this->one('SELECT payload_hash,created_by_user_id FROM inventory_transactions WHERE id=? FOR UPDATE',[$id]);
                if($old){if($old['payload_hash']!==$hash||(string)$old['created_by_user_id']!==(string)$actor['id'])throw new InventoryException(['request_id'=>'Request ID already used with other details.'],409);$result=['transaction_id'=>$id,'replayed'=>true];}
                else{
                    if($command==='ALLOCATE'&&(!(int)$r['is_active']||!(int)$p['is_active']))throw new InventoryException(['active'=>'Allocation requires an active representative and product.']);
                    if($this->one('SELECT batch_id FROM inventory_balances WHERE quantity<>0 LIMIT 1 FOR UPDATE')||$this->one('SELECT id FROM inventory_movements WHERE batch_id IS NOT NULL LIMIT 1 FOR UPDATE'))throw new InventoryException(['legacy'=>'Legacy batch stock requires reviewed reconciliation before transfers.'],409);
                    $location=$this->one('SELECT * FROM stock_locations WHERE rep_id=? FOR UPDATE',[$rep]);if(!$location){$this->pdo->prepare("INSERT INTO stock_locations(type,code,rep_id) VALUES ('REP',?,?)")->execute(['REP:'.$rep,$rep]);$location=['id'=>$this->pdo->lastInsertId(),'type'=>'REP','is_active'=>1];}
                    if($location['type']!=='REP'||($command==='ALLOCATE'&&!(int)$location['is_active']))throw new InventoryException(['location'=>'Representative stock location is inactive or invalid.']);
                    $warehouse=(string)$s['warehouse_location_id'];$repLocation=(string)$location['id'];
                    $this->pdo->prepare('INSERT INTO product_stock_balances(product_id,quantity) VALUES (?,0) ON DUPLICATE KEY UPDATE quantity=quantity')->execute([$product]);$w=$this->one('SELECT quantity FROM product_stock_balances WHERE product_id=? FOR UPDATE',[$product]);
                    $this->pdo->prepare('INSERT INTO representative_stock_balances(rep_id,product_id,quantity) VALUES (?,?,0) ON DUPLICATE KEY UPDATE quantity=quantity')->execute([$rep,$product]);$b=$this->one('SELECT quantity FROM representative_stock_balances WHERE rep_id=? AND product_id=? FOR UPDATE',[$rep,$product]);
                    foreach([[$warehouse,(string)$w['quantity']],[$repLocation,(string)$b['quantity']]] as [$loc,$balance]){$sum=$this->one('SELECT COALESCE(SUM(quantity_delta),0) quantity FROM inventory_movements WHERE location_id=? AND product_id=? AND batch_id IS NULL FOR UPDATE',[$loc,$product]);if((string)$sum['quantity']!==$balance)throw new InventoryException(['reconciliation'=>'Warehouse or representative stock does not reconcile. Posting blocked.'],409);}
                    $beforeW=(int)$w['quantity'];$beforeR=(int)$b['quantity'];$delta=$direction==='IN'?$qty:-$qty;$afterW=$beforeW;
                    if($delta<0&&$qty>$beforeR)throw new InventoryException(['quantity'=>'Insufficient representative stock.'],409);
                    if($delta>0&&$qty>PHP_INT_MAX-$beforeR)throw new InventoryException(['quantity'=>'Representative balance exceeds supported integer range.']);$afterR=$beforeR+$delta;
                    if($command!=='CORRECTION'){
                        if($delta>0&&$qty>$beforeW)throw new InventoryException(['quantity'=>'Insufficient warehouse stock.'],409);
                        if($delta<0&&$qty>PHP_INT_MAX-$beforeW)throw new InventoryException(['quantity'=>'Warehouse balance exceeds supported integer range.']);$afterW=$beforeW-$delta;
                    }
                    $type=$command==='CORRECTION'?'ADJUSTMENT':'TRANSFER';$this->pdo->prepare('INSERT INTO inventory_transactions(id,type,reason,created_by_user_id,payload_hash,selling_price_snapshot) VALUES (?,?,?,?,?,?)')->execute([$id,$type,$reason,$actor['id'],$hash,$p['selling_price']]);
                    if($command!=='CORRECTION'){
                        $from=$command==='ALLOCATE'?$warehouse:$repLocation;$to=$command==='ALLOCATE'?$repLocation:$warehouse;
                        $this->pdo->prepare('INSERT INTO stock_transfers(id,inventory_transaction_id,from_location_id,to_location_id,created_by_user_id,posted_at_utc) VALUES (?,?,?,?,?,UTC_TIMESTAMP(6))')->execute([$id,$id,$from,$to,$actor['id']]);
                        $this->pdo->prepare('INSERT INTO product_stock_transfer_items(transfer_id,product_id,quantity) VALUES (?,?,?)')->execute([$id,$product,$qty]);
                        $this->pdo->prepare('INSERT INTO inventory_movements(transaction_id,line_number,location_id,product_id,batch_id,batch_version_id,quantity_delta,quantity_after) VALUES (?,1,?,?,NULL,NULL,?,?)')->execute([$id,$warehouse,$product,-$delta,$afterW]);
                        $this->pdo->prepare('UPDATE product_stock_balances SET quantity=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE product_id=?')->execute([$afterW,$product]);
                    }
                    $this->pdo->prepare('INSERT INTO inventory_movements(transaction_id,line_number,location_id,product_id,batch_id,batch_version_id,quantity_delta,quantity_after) VALUES (?,?,?,?,NULL,NULL,?,?)')->execute([$id,$command==='CORRECTION'?1:2,$repLocation,$product,$delta,$afterR]);
                    $this->pdo->prepare('UPDATE representative_stock_balances SET quantity=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE rep_id=? AND product_id=?')->execute([$afterR,$rep,$product]);
                    $this->pdo->prepare('UPDATE rep_operating_state SET inventory_revision=inventory_revision+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE rep_id=?')->execute([$rep]);
                    $revision=$this->one('SELECT inventory_revision FROM rep_operating_state WHERE rep_id=?',[$rep]);
                    foreach(['ADMIN','REP:'.$rep] as $scope){$this->pdo->prepare('INSERT INTO sync_stream_state(scope_key,last_change_id) VALUES (?,0) ON DUPLICATE KEY UPDATE scope_key=scope_key')->execute([$scope]);$this->one('SELECT last_change_id FROM sync_stream_state WHERE scope_key=? FOR UPDATE',[$scope]);$this->pdo->prepare('UPDATE sync_stream_state SET last_change_id=last_change_id+1 WHERE scope_key=?')->execute([$scope]);$this->pdo->prepare("INSERT INTO change_log(scope_key,change_id,entity_type,entity_key,entity_version,action,changed_at_utc) SELECT scope_key,last_change_id,'representative_stock',?,?,'UPSERT',UTC_TIMESTAMP(6) FROM sync_stream_state WHERE scope_key=?")->execute([$rep.':'.$product,$revision['inventory_revision'],$scope]);}
                    $this->pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,reason,before_json,after_json) VALUES (?,?,'inventory_transactions',?,?,?,?)")->execute([$actor['id'],'REP_STOCK_'.$command,$id,$reason,json_encode(['warehouse'=>(string)$beforeW,'rep'=>(string)$beforeR],JSON_THROW_ON_ERROR),json_encode(['rep_id'=>$rep,'product_id'=>$product,'warehouse'=>(string)$afterW,'rep'=>(string)$afterR,'delta'=>(string)$delta],JSON_THROW_ON_ERROR)]);
                    $result=['transaction_id'=>$id,'warehouse_quantity'=>(string)$afterW,'representative_quantity'=>(string)$afterR,'replayed'=>false];
                }
                if(!$old)\App\Support\BusinessCode::next($this->pdo,'A',$id);if($own)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT rep_stock_command');return $result;
            }catch(\Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$own&&$this->pdo->inTransaction())$this->pdo->exec('ROLLBACK TO SAVEPOINT rep_stock_command');$code=$e instanceof \PDOException?(int)($e->errorInfo[1]??0):0;if($own&&in_array($code,[1205,1213],true)&&$attempt<2)continue;if(in_array($code,[1205,1213,1062],true))throw new InventoryException(['conflict'=>'Concurrent allocation conflict. Retry with the same request ID.'],409);throw $e;}
        }
    }
}
