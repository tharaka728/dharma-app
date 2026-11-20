<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Support\InventoryException;
use App\Support\ProductException;

/** Warehouse-only posting. Batch identity, revisions, transactions and movements stay append-only. */
final class Inventory
{
    public function __construct(private PDO $pdo) {}
    public function admin(array $actor):void
    {
        try{(new Products($this->pdo))->admin($actor);}catch(ProductException $e){throw new InventoryException($e->errors,403);}
    }
    public static function uuid():string
    {
        $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);
        return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
    }
    public static function today(?\DateTimeImmutable $instant=null):string
    {
        return ($instant??new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Asia/Colombo'))->format('Y-m-d');
    }
    public static function saleable(string $expiry,string $status,bool $active,?\DateTimeImmutable $instant=null):bool
    {
        return $active&&$status==='SALEABLE'&&$expiry>=self::today($instant);
    }
    public static function text(mixed $value,string $key,int $max,bool $required=false):string
    {
        if(!is_string($value)||!mb_check_encoding($value,'UTF-8')){throw new InventoryException([$key=>'Enter valid text.']);}
        $value=trim($value);
        if(($required&&$value==='')||mb_strlen($value,'UTF-8')>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value)){throw new InventoryException([$key=>"Enter ".($required?'1 to':'up to')." $max characters."]);}
        return $value;
    }
    public static function quantity(mixed $value,bool $zero=false):int
    {
        if(!is_string($value)||!preg_match('/^(0|[1-9][0-9]{0,18})$/D',$value)||strlen($value)>strlen((string)PHP_INT_MAX)||(strlen($value)===strlen((string)PHP_INT_MAX)&&strcmp($value,(string)PHP_INT_MAX)>0)||(!$zero&&$value==='0')){throw new InventoryException(['quantity'=>'Enter a whole-unit quantity within the signed 64-bit range'.($zero?' (zero allowed).':', greater than zero.')]);}
        return (int)$value;
    }
    public static function date(mixed $value,string $key,bool $optional=false):?string
    {
        if($optional&&$value===''){return null;}
        if(!is_string($value)||!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D',$value)||!checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4))){throw new InventoryException([$key=>'Choose a valid calendar date.']);}return $value;
    }
    private static function id(mixed $value,string $key):string
    {
        if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,18}$/D',$value)){throw new InventoryException([$key=>'Choose a listed record.']);}return $value;
    }
    private function one(string $sql,array $params=[]):?array
    {
        $q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetch()?:null;
    }
    private function atomic(callable $work):mixed
    {
        $owner=!$this->pdo->inTransaction();
        for($attempt=0;;$attempt++){
            // A waiter must observe the post/revision that released its locks, not an older auth-read snapshot.
            if($owner){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$this->pdo->beginTransaction();}else{$this->pdo->exec('SAVEPOINT inventory_command');}
            try{$result=$work();if($owner){$this->pdo->commit();}else{$this->pdo->exec('RELEASE SAVEPOINT inventory_command');}return $result;}
            catch(\Throwable $e){if($owner&&$this->pdo->inTransaction()){$this->pdo->rollBack();}elseif(!$owner&&$this->pdo->inTransaction()){$this->pdo->exec('ROLLBACK TO SAVEPOINT inventory_command');}
                $code=$e instanceof \PDOException?(int)($e->errorInfo[1]??0):0;
                if($owner&&in_array($code,[1213,1205],true)&&$attempt<2){continue;}
                if(in_array($code,[1213,1205],true)){throw new InventoryException(['conflict'=>'Another stock command is in progress. Retry with the same request ID.'],409);}
                if($code===1062){throw new InventoryException(['duplicate'=>'This batch or request ID already exists. Reload and review; do not create a second request for the same stock.'],409);}throw $e;
            }
        }
    }
    private function audit(array $actor,string $action,string $entity,string $id,?array $before,array $after,string $reason):void
    {
        $this->pdo->prepare('INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,reason,before_json,after_json) VALUES (?,?,?,?,?,?,?)')->execute([$actor['id'],$action,$entity,$id,$reason,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);
    }
    private function change(string $entity,string $id,string $version):void
    {
        $this->pdo->exec("INSERT INTO sync_stream_state(scope_key,last_change_id) VALUES ('ADMIN',0) ON DUPLICATE KEY UPDATE scope_key=scope_key");
        $this->one("SELECT last_change_id FROM sync_stream_state WHERE scope_key='ADMIN' FOR UPDATE");
        $this->pdo->exec("UPDATE sync_stream_state SET last_change_id=last_change_id+1 WHERE scope_key='ADMIN'");
        $this->pdo->prepare("INSERT INTO change_log(scope_key,change_id,entity_type,entity_key,entity_version,action,changed_at_utc) SELECT 'ADMIN',last_change_id,?,?,?,'UPSERT',UTC_TIMESTAMP(6) FROM sync_stream_state WHERE scope_key='ADMIN'")->execute([$entity,$id,$version]);
    }
    public function settings(array $actor,bool $lock=false):array
    {
        $this->admin($actor);return $this->one('SELECT s.*,l.code warehouse_code FROM inventory_settings s LEFT JOIN stock_locations l ON l.id=s.warehouse_location_id WHERE s.id=1'.($lock?' FOR UPDATE':''))??throw new InventoryException(['setup'=>'Inventory migration required.'],503);
    }
    public function configure(array $actor,array $input):void
    {
        $this->admin($actor);$days=self::quantity($input['near_expiry_days']??'',true);if($days>3650){throw new InventoryException(['near_expiry_days'=>'Choose 0 to 3650 calendar days.']);}
        $this->atomic(function()use($actor,$input,$days){
            $before=$this->settings($actor,true);if(($input['version']??null)!==(string)$before['version']){throw new InventoryException(['version'=>'Settings changed. Reload before saving.'],409);}
            $warehouse=$before['warehouse_location_id'];
            if($warehouse===null){
                $chosen=$input['warehouse_location_id']??'';
                if($chosen!==''){$row=$this->one("SELECT * FROM stock_locations WHERE id=? AND type='WAREHOUSE' AND is_active=1 FOR UPDATE",[self::id($chosen,'warehouse_location_id')]);if(!$row){throw new InventoryException(['warehouse'=>'Choose an active warehouse.']);}$warehouse=$row['id'];}
                else{$code=self::text($input['warehouse_code']??'','warehouse_code',80,true);$this->pdo->prepare("INSERT INTO stock_locations(type,code) VALUES ('WAREHOUSE',?)")->execute([$code]);$warehouse=$this->pdo->lastInsertId();}
            }
            $this->pdo->prepare('UPDATE inventory_settings SET warehouse_location_id=?,near_expiry_days=?,version=version+1 WHERE id=1')->execute([$warehouse,$days]);
            $this->audit($actor,'INVENTORY_SETTINGS','inventory_settings','1',$before,$this->settings($actor),'Configure warehouse and expiry alerts');
        });
    }
    public function threshold(array $actor,array $input):void
    {
        $this->admin($actor);$product=self::id($input['product_id']??null,'product_id');$value=$input['low_stock_threshold']??'';$threshold=$value===''?null:self::quantity($value,true);
        $this->atomic(function()use($actor,$input,$product,$threshold){
            $this->settings($actor,true);if(!$this->one('SELECT id FROM products WHERE id=? FOR UPDATE',[$product])){throw new InventoryException(['product'=>'Product not found.']);}
            $before=$this->one('SELECT * FROM inventory_product_settings WHERE product_id=? FOR UPDATE',[$product]);
            if(($input['version']??null)!==(string)($before['version']??'0')){throw new InventoryException(['version'=>'Threshold changed. Reload and retry.'],409);}
            $this->pdo->prepare('INSERT INTO inventory_product_settings(product_id,low_stock_threshold) VALUES (?,?) ON DUPLICATE KEY UPDATE low_stock_threshold=VALUES(low_stock_threshold),version=version+1')->execute([$product,$threshold]);
            $this->audit($actor,'STOCK_THRESHOLD','products',$product,$before,['threshold'=>$threshold===null?null:(string)$threshold],'Configure low-stock alert');
        });
    }
    private function warehouse(array $settings):string
    {
        if($settings['warehouse_location_id']===null){throw new InventoryException(['warehouse'=>'Configure the central warehouse in Inventory settings first.']);}
        $row=$this->one("SELECT id FROM stock_locations WHERE id=? AND type='WAREHOUSE' AND is_active=1",[$settings['warehouse_location_id']]);if(!$row){throw new InventoryException(['warehouse'=>'Configured warehouse is inactive or invalid.']);}return (string)$row['id'];
    }
    public function batch(array $actor,string $id,bool $lock=false):array
    {
        $this->admin($actor);self::id($id,'batch_id');
        if($lock&&!$this->one('SELECT id FROM product_batches WHERE id=? FOR UPDATE',[$id])){throw new InventoryException(['batch'=>'Batch not found.'],404);}
        $revision=$lock?$this->one('SELECT id FROM product_batch_versions WHERE batch_id=? ORDER BY version_number DESC LIMIT 1 FOR UPDATE',[$id]):null;
        $row=$this->one("SELECT b.*,p.name product_name,p.name_si,p.sku,p.is_active product_active,v.id revision_id,v.version_number,v.manufacturing_date,v.expiry_date,COALESCE(c.status,'QUARANTINED') status,COALESCE(c.acquisition_info,'') acquisition_info,COALESCE(c.version,0) control_version FROM product_batches b JOIN products p ON p.id=b.product_id JOIN product_batch_versions v ON v.batch_id=b.id AND ".($lock?'v.id=?':'v.version_number=(SELECT MAX(v2.version_number) FROM product_batch_versions v2 WHERE v2.batch_id=b.id)')." LEFT JOIN inventory_batch_controls c ON c.batch_id=b.id WHERE b.id=?".($lock?' FOR UPDATE':''),$lock?[$revision['id']??'0',$id]:[$id]);
        if(!$row){throw new InventoryException(['batch'=>'Batch or its initial revision was not found.'],404);}return $row;
    }
    public function saveBatch(array $actor,array $input,?string $id=null):string
    {
        $this->admin($actor);$mfg=self::date($input['manufacturing_date']??'','manufacturing_date',true);$expiry=self::date($input['expiry_date']??null,'expiry_date');
        if($mfg!==null&&$mfg>$expiry){throw new InventoryException(['dates'=>'Expiry cannot precede manufacturing date.']);}
        $status=$input['status']??'';if(!is_string($status)||!in_array($status,['SALEABLE','QUARANTINED','BLOCKED','RECALLED'],true)){throw new InventoryException(['status'=>'Choose a listed batch status.']);}
        $info=self::text($input['acquisition_info']??'','acquisition_info',1000);$reason=self::text($input['reason']??'','reason',500,true);
        return $this->atomic(function()use($actor,$input,$id,$mfg,$expiry,$status,$info,$reason){
            $mode=$this->settings($actor,true);if(($mode['stock_model']??'BATCH')!=='BATCH'){throw new InventoryException(['retired'=>'Batch stock entry is retired; use product stock.'],409);}$today=self::today();
            if($mfg!==null&&$mfg>$today){throw new InventoryException(['manufacturing_date'=>'Manufacturing date cannot be in the future.']);}
            if($status==='SALEABLE'&&$expiry<$today){throw new InventoryException(['status'=>'An expired batch cannot be marked saleable. Choose quarantine, blocked or recalled.']);}
            $before=null;
            if($id===null){$product=self::id($input['product_id']??null,'product_id');$row=$this->one('SELECT id,is_active FROM products WHERE id=? FOR UPDATE',[$product]);if(!$row||!(int)$row['is_active']){throw new InventoryException(['product'=>'Choose an active existing product.']);}
                $number=self::text($input['batch_number']??'','batch_number',100,true);if(preg_match('/[\x00-\x1F\x7F]/',$number)){throw new InventoryException(['batch_number'=>'Batch number cannot contain control characters.']);}
                $this->pdo->prepare('INSERT INTO product_batches(product_id,batch_number) VALUES (?,?)')->execute([$product,$number]);$id=$this->pdo->lastInsertId();$version=1;
            }else{$before=$this->batch($actor,$id,true);
                if(($input['version_number']??null)!==(string)$before['version_number']||($input['control_version']??null)!==(string)$before['control_version']){throw new InventoryException(['version'=>'Batch changed. Reload before saving.'],409);}
                if($status==='SALEABLE'&&!(int)$before['product_active']){throw new InventoryException(['status'=>'Inactive products cannot be saleable.']);}$version=(int)$before['version_number']+1;
            }
            // Dates and reason are a new immutable revision even for a control-only correction.
            $this->pdo->prepare('INSERT INTO product_batch_versions(batch_id,version_number,manufacturing_date,expiry_date,reason,recorded_by_user_id) VALUES (?,?,?,?,?,?)')->execute([$id,$version,$mfg,$expiry,$reason,$actor['id']]);
            $this->pdo->prepare('INSERT INTO inventory_batch_controls(batch_id,status,acquisition_info,updated_by_user_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),acquisition_info=VALUES(acquisition_info),updated_by_user_id=VALUES(updated_by_user_id),version=version+1,updated_at_utc=UTC_TIMESTAMP(6)')->execute([$id,$status,$info,$actor['id']]);
            $after=$this->batch($actor,$id);$this->change('product_batches',$id,(string)$version);$this->audit($actor,$before?'BATCH_REVISION':'BATCH_CREATE','product_batches',$id,$before,$after,$reason);return $id;
        });
    }
    public function options(array $actor):array
    {
        $this->admin($actor);
        return ['products'=>$this->pdo->query('SELECT p.id,p.sku,p.name,p.name_si,p.is_active,s.low_stock_threshold,COALESCE(s.version,0) threshold_version FROM products p LEFT JOIN inventory_product_settings s ON s.product_id=p.id ORDER BY p.name,p.id')->fetchAll(),
            'categories'=>$this->pdo->query('SELECT id,name FROM categories ORDER BY name,id')->fetchAll(),
            'warehouses'=>$this->pdo->query("SELECT id,code FROM stock_locations WHERE type='WAREHOUSE' AND is_active=1 ORDER BY code")->fetchAll()];
    }
    private function filters(array $filters):array
    {
        $q=self::text($filters['q']??'','q',100);$batch=self::text($filters['batch']??'','batch',100);$params=[];$where=[];
        foreach(['product_id'=>'products','category_id'=>'categories'] as $key=>$table){$value=$filters[$key]??'';if($value!==''){$value=self::id($value,$key);if(!$this->one("SELECT id FROM $table WHERE id=?",[$value])){throw new InventoryException([$key=>'Choose a listed record.']);}$where[]='r.'.$key.'=?';$params[]=$value;}}
        $like=static fn(string $v):string=>'%'.strtr($v,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
        if($q!==''){$where[]="(r.product_name LIKE ? ESCAPE '!' OR r.sku LIKE ? ESCAPE '!' OR r.batch_number LIKE ? ESCAPE '!')";array_push($params,$like($q),$like($q),$like($q));}
        if($batch!==''){$where[]="r.batch_number LIKE ? ESCAPE '!'";$params[]=$like($batch);}
        return [$where,$params];
    }
    private function page(array $filters):int
    {
        $value=$filters['page']??'1';if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,5}$/D',$value)){throw new InventoryException(['page'=>'Invalid page.']);}return (int)$value;
    }
    public function stocks(array $actor,array $filters):array
    {
        $settings=$this->settings($actor);[$where,$params]=$this->filters($filters);$today=self::today();$near=(new \DateTimeImmutable($today,new \DateTimeZone('Asia/Colombo')))->modify('+'.$settings['near_expiry_days'].' days')->format('Y-m-d');
        $expiry=$filters['expiry']??'all';$availability=$filters['availability']??'all';
        if(!is_string($expiry)||!in_array($expiry,['all','expired','near','valid'],true)||!is_string($availability)||!in_array($availability,['all','physical','available','unavailable','zero'],true)){throw new InventoryException(['filters'=>'Invalid expiry or availability filter.']);}
        if($expiry==='expired'){$where[]='r.expiry_date<?';$params[]=$today;}
        if($expiry==='near'){$where[]='r.expiry_date>=? AND r.expiry_date<=?';array_push($params,$today,$near);}
        if($expiry==='valid'){$where[]='r.expiry_date>?';$params[]=$near;}
        $available=['physical'=>'r.physical>0','available'=>'r.available>0','unavailable'=>'r.physical>0 AND r.available=0','zero'=>'r.physical=0'];if($availability!=='all'){$where[]=$available[$availability];}
        $base="SELECT b.id batch_id,b.product_id,b.batch_number,p.sku,p.name product_name,p.name_si,p.category_id,p.is_active product_active,v.expiry_date,v.manufacturing_date,v.version_number,COALESCE(c.status,'QUARANTINED') status,COALESCE(c.version,0) control_version,COALESCE(ib.quantity,0) physical,CASE WHEN p.is_active=1 AND c.status='SALEABLE' AND v.expiry_date>=? THEN COALESCE(ib.quantity,0) ELSE 0 END available,(SELECT COALESCE(SUM(rb.quantity),0) FROM inventory_balances rb JOIN stock_locations rl ON rl.id=rb.location_id WHERE rb.batch_id=b.id AND rl.type='REP') rep_held FROM product_batches b JOIN products p ON p.id=b.product_id JOIN product_batch_versions v ON v.batch_id=b.id AND v.version_number=(SELECT MAX(v2.version_number) FROM product_batch_versions v2 WHERE v2.batch_id=b.id) LEFT JOIN inventory_batch_controls c ON c.batch_id=b.id LEFT JOIN inventory_balances ib ON ib.batch_id=b.id AND ib.product_id=b.product_id AND ib.location_id=?";
        $sql=' FROM ('.$base.') r'.($where?' WHERE '.implode(' AND ',$where):'');$bound=array_merge([$today,$settings['warehouse_location_id']??'0'],$params);
        $q=$this->pdo->prepare('SELECT COUNT(*)'.$sql);$q->execute($bound);$total=(int)$q->fetchColumn();$pages=max(1,intdiv($total+24,25));$page=min($this->page($filters),$pages);
        $q=$this->pdo->prepare('SELECT r.*'.$sql.' ORDER BY r.expiry_date,r.batch_id LIMIT 25 OFFSET '.(($page-1)*25));$q->execute($bound);$rows=$q->fetchAll();
        $q=$this->pdo->prepare('SELECT r.product_id,r.sku,r.product_name,r.name_si,SUM(r.physical) physical,SUM(r.available) available,SUM(r.rep_held) rep_held,s.low_stock_threshold,COALESCE(s.version,0) threshold_version FROM (SELECT r.*'.$sql.') r LEFT JOIN inventory_product_settings s ON s.product_id=r.product_id GROUP BY r.product_id,r.sku,r.product_name,r.name_si,s.low_stock_threshold,s.version ORDER BY r.product_name,r.product_id');$q->execute($bound);$totals=$q->fetchAll();
        // Include real catalogue products with no batches when zero stock satisfies the selected filters.
        if(($filters['batch']??'')===''&&$expiry==='all'&&in_array($availability,['all','zero'],true)){
            $zeroWhere=['NOT EXISTS (SELECT 1 FROM product_batches z WHERE z.product_id=p.id)'];$zeroParams=[];
            foreach(['product_id'=>'p.id','category_id'=>'p.category_id'] as $key=>$column){if(($filters[$key]??'')!==''){$zeroWhere[]=$column.'=?';$zeroParams[]=$filters[$key];}}
            $search=trim($filters['q']??'');if($search!==''){$like='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$zeroWhere[]="(p.name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";array_push($zeroParams,$like,$like);}
            $q=$this->pdo->prepare("SELECT p.id product_id,p.sku,p.name product_name,p.name_si,'0' physical,'0' available,'0' rep_held,s.low_stock_threshold,COALESCE(s.version,0) threshold_version FROM products p LEFT JOIN inventory_product_settings s ON s.product_id=p.id WHERE ".implode(' AND ',$zeroWhere).' ORDER BY p.name,p.id');$q->execute($zeroParams);$totals=array_merge($totals,$q->fetchAll());
            usort($totals,static fn($a,$b)=>strcmp($a['product_name'],$b['product_name'])?:strcmp((string)$a['product_id'],(string)$b['product_id']));
        }
        $low=$this->pdo->prepare("SELECT p.id,p.name,p.name_si,s.low_stock_threshold,COALESCE(a.available,0) available FROM products p JOIN inventory_product_settings s ON s.product_id=p.id LEFT JOIN (SELECT b.product_id,SUM(CASE WHEN c.status='SALEABLE' AND v.expiry_date>=? THEN COALESCE(ib.quantity,0) ELSE 0 END) available FROM product_batches b JOIN product_batch_versions v ON v.batch_id=b.id AND v.version_number=(SELECT MAX(v2.version_number) FROM product_batch_versions v2 WHERE v2.batch_id=b.id) LEFT JOIN inventory_batch_controls c ON c.batch_id=b.id LEFT JOIN inventory_balances ib ON ib.batch_id=b.id AND ib.location_id=? GROUP BY b.product_id) a ON a.product_id=p.id WHERE p.is_active=1 AND s.low_stock_threshold IS NOT NULL AND COALESCE(a.available,0)<=s.low_stock_threshold ORDER BY p.name");$low->execute([$today,$settings['warehouse_location_id']??'0']);
        return ['rows'=>$rows,'totals'=>$totals,'low'=>$low->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'today'=>$today,'near_date'=>$near];
    }
    public function movements(array $actor,array $filters,bool $export=false):array
    {
        $this->admin($actor);[$where,$params]=$this->filters($filters);$type=$filters['type']??'all';
        if(!is_string($type)||!in_array($type,['all','RECEIPT','ADJUSTMENT','OPENING','TRANSFER','SALE'],true)){throw new InventoryException(['type'=>'Invalid movement type.']);}
        if($type!=='all'){$where[]='r.type=?';$params[]=$type;}
        $base='SELECT m.id,m.transaction_id,m.product_id,p.category_id,p.name product_name,p.name_si,p.sku,b.batch_number,m.quantity_delta,m.quantity_after,m.created_at_utc,t.type,t.reason,t.reference,t.supplier,t.receipt_date,t.unit_cost,t.notes,t.reverses_transaction_id,u.name responsible_user,l.code location,l.type location_type,v.expiry_date snapshot_expiry FROM inventory_movements m JOIN inventory_transactions t ON t.id=m.transaction_id JOIN product_batches b ON b.id=m.batch_id JOIN product_batch_versions v ON v.id=m.batch_version_id JOIN products p ON p.id=m.product_id JOIN users u ON u.id=t.created_by_user_id JOIN stock_locations l ON l.id=m.location_id';
        $sql=' FROM ('.$base.') r'.($where?' WHERE '.implode(' AND ',$where):'');$q=$this->pdo->prepare('SELECT COUNT(*)'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();
        if($export&&$total>10000){throw new InventoryException(['export'=>'CSV export is limited to 10,000 movements. Narrow the filters first.']);}
        $pages=max(1,intdiv($total+49,50));$page=min($this->page($filters),$pages);$q=$this->pdo->prepare('SELECT r.*'.$sql.' ORDER BY r.id DESC LIMIT '.($export?'10000':('50 OFFSET '.(($page-1)*50))));$q->execute($params);
        return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public function reconciliation(array $actor):array
    {
        $this->admin($actor);
        // Include ledger-only keys as well as balance-only keys. One SQL snapshot sees committed atomic posts.
        return $this->pdo->query('SELECT k.location_id,k.product_id,k.batch_id,COALESCE(b.quantity,0) balance,COALESCE(m.total,0) ledger FROM (SELECT location_id,product_id,batch_id FROM inventory_balances UNION SELECT location_id,product_id,batch_id FROM inventory_movements WHERE batch_id IS NOT NULL) k LEFT JOIN inventory_balances b ON b.location_id=k.location_id AND b.product_id=k.product_id AND b.batch_id=k.batch_id LEFT JOIN (SELECT location_id,product_id,batch_id,SUM(quantity_delta) total FROM inventory_movements WHERE batch_id IS NOT NULL GROUP BY location_id,product_id,batch_id) m ON m.location_id=k.location_id AND m.product_id=k.product_id AND m.batch_id=k.batch_id WHERE COALESCE(b.quantity,0)<>COALESCE(m.total,0)')->fetchAll();
    }
    public static function csvCell(mixed $value):string
    {
        $s=(string)($value??'');return preg_match('/^[\s\p{Z}\p{Cf}\x00-\x20]*[=+\-@]/u',$s)?"'".$s:$s;
    }
    public function post(array $actor,array $input,string $type):array
    {
        $this->admin($actor);if(!in_array($type,['RECEIPT','OPENING','ADJUSTMENT'],true)){throw new InventoryException(['type'=>'Unsupported stock command.']);}
        $incoming=in_array($type,['RECEIPT','OPENING'],true);
        $id=$input['request_id']??null;if(!is_string($id)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$id)){throw new InventoryException(['request_id'=>'Refresh the form to obtain a valid request ID.']);}
        $batch=self::id($input['batch_id']??null,'batch_id');$qty=self::quantity($input['quantity']??null);$direction=$incoming?'IN':($input['direction']??null);
        if(!in_array($direction,['IN','OUT'],true)){throw new InventoryException(['direction'=>'Choose increase or decrease.']);}
        $reason=self::text($input['reason']??'','reason',500,true);$reference=self::text($input['reference']??'','reference',200);$notes=self::text($input['notes']??'','notes',2000);
        $supplier=$incoming?self::text($input['supplier']??'','supplier',200):'';$date=$incoming?self::date($input['receipt_date']??null,'receipt_date'):null;
        try{$cost=$incoming?Products::money($input['unit_cost']??'',true):null;}catch(ProductException $e){throw new InventoryException(['unit_cost'=>'Enter an optional nonnegative LKR cost with at most 2 decimal places.']);}
        $reverse=$input['reverses_transaction_id']??'';if(!is_string($reverse)||($reverse!==''&&!preg_match('/^[0-9a-f-]{36}$/D',$reverse))){throw new InventoryException(['reversal'=>'Invalid referenced transaction.']);}
        $payload=['type'=>$type,'batch'=>$batch,'qty'=>(string)$qty,'direction'=>$direction,'reason'=>$reason,'reference'=>$reference,'notes'=>$notes,'supplier'=>$supplier,'date'=>$date,'cost'=>$cost,'reverse'=>$reverse];$hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        return $this->atomic(function()use($actor,$input,$id,$batch,$qty,$direction,$reason,$reference,$notes,$supplier,$date,$cost,$reverse,$hash,$type,$incoming){
            $settings=$this->settings($actor,true);if(($settings['stock_model']??'BATCH')!=='BATCH'){throw new InventoryException(['retired'=>'Batch stock posting is retired; use product stock.'],409);}$location=$this->warehouse($settings);$row=$this->batch($actor,$batch,true);
            if(array_key_exists('product_id',$input)&&self::id($input['product_id'],'product_id')!==(string)$row['product_id']){throw new InventoryException(['product_id'=>'The selected batch does not belong to this product.']);}
            $old=$this->one('SELECT * FROM inventory_transactions WHERE id=? FOR UPDATE',[$id]);
            if($old){if($old['payload_hash']!==$hash||(string)$old['created_by_user_id']!==(string)$actor['id']){throw new InventoryException(['request_id'=>'Request ID was already used with different details.'],409);}return ['transaction_id'=>$id,'replayed'=>true];}
            if($incoming&&($date>self::today()||($row['manufacturing_date']!==null&&$date<$row['manufacturing_date']))){throw new InventoryException(['receipt_date'=>'Receipt / opening date must not be future-dated or precede manufacturing.']);}
            if($incoming&&!(int)$row['product_active']){throw new InventoryException(['product'=>'Cannot receive an inactive product.']);}
            if($type==='OPENING'&&$this->one('SELECT id FROM inventory_movements WHERE location_id=? AND product_id=? AND batch_id=? LIMIT 1 FOR UPDATE',[$location,$row['product_id'],$batch])){throw new InventoryException(['opening'=>'Opening stock is allowed only before this warehouse batch has any movement history. Use a goods receipt or a documented adjustment instead.'],409);}
            if($reverse!==''){
                $original=$this->one("SELECT m.* FROM inventory_movements m JOIN inventory_transactions t ON t.id=m.transaction_id WHERE m.transaction_id=? AND m.line_number=1 AND t.type IN ('OPENING','RECEIPT','ADJUSTMENT') AND t.payload_hash IS NOT NULL AND (SELECT COUNT(*) FROM inventory_movements m2 WHERE m2.transaction_id=t.id)=1 FOR UPDATE",[$reverse]);
                if($type!=='ADJUSTMENT'||!$original||(string)$original['batch_id']!==$batch||(string)$original['location_id']!==$location||(int)$original['quantity_delta']!==($direction==='IN'?-$qty:$qty)){throw new InventoryException(['reversal'=>'A reversal must exactly offset the referenced warehouse batch movement.']);}
                if($this->one('SELECT id FROM inventory_transactions WHERE reverses_transaction_id=? FOR UPDATE',[$reverse])){throw new InventoryException(['reversal'=>'This movement already has a reversal.'],409);}
            }
            $this->pdo->prepare('INSERT INTO inventory_balances(location_id,product_id,batch_id,quantity) VALUES (?,?,?,0) ON DUPLICATE KEY UPDATE quantity=quantity')->execute([$location,$row['product_id'],$batch]);
            $balance=$this->one('SELECT quantity,version FROM inventory_balances WHERE location_id=? AND product_id=? AND batch_id=? FOR UPDATE',[$location,$row['product_id'],$batch]);$before=(int)$balance['quantity'];
            $sum=$this->one('SELECT COALESCE(SUM(quantity_delta),0) total FROM inventory_movements WHERE location_id=? AND product_id=? AND batch_id=? FOR UPDATE',[$location,$row['product_id'],$batch]);
            if((string)$sum['total']!==(string)$before){throw new InventoryException(['reconciliation'=>'Balance does not match its movement ledger. Posting blocked pending investigation.'],409);}
            if($direction==='OUT'&&$qty>$before){throw new InventoryException(['quantity'=>'Insufficient physical stock. Negative balances are prohibited.'],409);}
            if($direction==='IN'&&$qty>PHP_INT_MAX-$before){throw new InventoryException(['quantity'=>'The resulting balance exceeds the supported integer range.']);}
            $delta=$direction==='IN'?$qty:-$qty;$after=$before+$delta;
            $this->pdo->prepare('INSERT INTO inventory_transactions(id,type,reason,created_by_user_id,payload_hash,receipt_date,supplier,reference,unit_cost,notes,reverses_transaction_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,$type,$reason,$actor['id'],$hash,$date,$supplier,$reference,$cost,$notes,$reverse===''?null:$reverse]);
            $this->pdo->prepare('INSERT INTO inventory_movements(transaction_id,line_number,location_id,product_id,batch_id,batch_version_id,quantity_delta,quantity_after) VALUES (?,1,?,?,?,?,?,?)')->execute([$id,$location,$row['product_id'],$batch,$row['revision_id'],$delta,$after]);
            $this->pdo->prepare('UPDATE inventory_balances SET quantity=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE location_id=? AND product_id=? AND batch_id=?')->execute([$after,$location,$row['product_id'],$batch]);
            $this->change('inventory_balances',$location.':'.$row['product_id'].':'.$batch,(string)((int)$balance['version']+1));
            $this->audit($actor,'STOCK_'.$type,'inventory_transactions',$id,null,['batch_id'=>$batch,'location_id'=>$location,'delta'=>(string)$delta,'quantity_after'=>(string)$after,'batch_version_id'=>(string)$row['revision_id']],$reason);
            return ['transaction_id'=>$id,'quantity_after'=>(string)$after,'replayed'=>false];
        });
    }
}
