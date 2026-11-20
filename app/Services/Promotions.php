<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;use App\Support\ProductException;
final class Promotions
{
    public const TYPES=['BUY_X_GET_Y'=>'Buy X, Get Y Free','PERCENTAGE'=>'Percentage discount','FIXED'=>'Fixed LKR discount per paid unit'];
    public function __construct(private PDO $pdo){}
    public function admin(array $actor):void{(new Inventory($this->pdo))->admin($actor);}
    private function reader(array $actor):void{$q=$this->pdo->prepare('SELECT role,is_active FROM users WHERE id=?');$q->execute([$actor['id']??'0']);$u=$q->fetch();if(!$u||!(int)$u['is_active']||!in_array($u['role'],['ADMIN','SALES_REP'],true))throw new InventoryException(['access'=>'Sign in with an active account.'],403);}
    private function one(string $sql,array $p=[]):?array{$q=$this->pdo->prepare($sql);$q->execute($p);return $q->fetch()?:null;}
    private function serialized(array $row):array{if(!empty($row['mixed_product_ids'])){$row['mixed_product_ids']=is_array($row['mixed_product_ids'])?$row['mixed_product_ids']:json_decode($row['mixed_product_ids'],true,32,JSON_THROW_ON_ERROR);$row['mixed_policy']=MixedOil::POLICY;}else unset($row['mixed_product_ids']);foreach(['id','revision_id','version','current_version','version_number','product_id','buy_quantity','free_quantity'] as $key)if(isset($row[$key]))$row[$key]=(string)$row[$key];return $row;}
    public static function validate(array $input):array
    {
        $mixed=($input['mixed_oil']??'0')==='1';$members=null;if($mixed){$members=$input['mixed_product_ids']??[];if(!is_array($members)||!count($members)||count($members)>100)throw new InventoryException(['mixed_product_ids'=>'Select 1–100 eligible oil products.']);$members=array_values(array_unique(array_map(fn($v)=>Representatives::id($v,'mixed_product_ids'),$members)));usort($members,fn($a,$b)=>PromotionCalculator::compare($a,$b));$input['product_id']=$members[0];if(($input['type']??null)!=='BUY_X_GET_Y'||($input['buy_quantity']??null)!=='6'||($input['free_quantity']??null)!=='1')throw new InventoryException(['mixed_oil'=>'Mixed oil requires Buy 6 Get 1.']);}
        $out=['name'=>Inventory::text($input['name']??'','name',200,true),'description'=>Inventory::text($input['description']??'','description',2000),'product_id'=>Representatives::id($input['product_id']??null,'product_id')];
        $type=$input['type']??null;if(!is_string($type)||!isset(self::TYPES[$type]))throw new InventoryException(['type'=>'Choose a supported promotion type.']);$out['type']=$type;$out['is_active']=$input['is_active']??'1';if(!in_array($out['is_active'],['0','1'],true))throw new InventoryException(['is_active'=>'Choose enabled or disabled.']);
        foreach(['percent_rate','fixed_discount','buy_quantity','free_quantity'] as $key){$value=$input[$key]??'';$required=($type==='BUY_X_GET_Y'&&in_array($key,['buy_quantity','free_quantity'],true))||($type==='PERCENTAGE'&&$key==='percent_rate')||($type==='FIXED'&&$key==='fixed_discount');
            if(!$required){if($value!=='')throw new InventoryException([$key=>'Leave values for other promotion types empty.']);$out[$key]=null;continue;}
            if(in_array($key,['buy_quantity','free_quantity'],true)){$out[$key]=(string)Inventory::quantity($value);}
            else{try{$out[$key]=Products::money($value);}catch(ProductException $e){throw new InventoryException([$key=>'Enter a positive decimal with at most two places.']);}if($out[$key]==='0.00'||($key==='percent_rate'&&PromotionCalculator::compare(str_replace('.','',$out[$key]),'10000')>0))throw new InventoryException([$key=>$key==='percent_rate'?'Percentage must be greater than 0 and at most 100.':'Fixed discount must be greater than zero.']);}
        }
        if($type==='BUY_X_GET_Y'&&(int)$out['free_quantity']>PHP_INT_MAX-(int)$out['buy_quantity'])throw new InventoryException(['quantity'=>'One qualifying group exceeds the supported whole-unit stock range.']);
        foreach(['start_date','end_date'] as $key){$out[$key]=Inventory::date($input[$key]??'',$key,true);if($out[$key]!==null){$date=new \DateTimeImmutable($out[$key].' 00:00:00',new \DateTimeZone('Asia/Colombo'));if($date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d')<'1000-01-01')throw new InventoryException([$key=>'Date is outside the supported database range.']);}}
        if($out['start_date']!==null&&$out['end_date']!==null&&$out['end_date']<$out['start_date'])throw new InventoryException(['dates'=>'End date must be on or after start date.']);if($members!==null)$out['mixed_product_ids']=$members;return $out;
    }
    private function select():string
    {
        return 'SELECT (SELECT code FROM business_code_registry WHERE prefix=0x4d AND request_key=CAST(h.id AS CHAR)) reference_code,h.id,h.name,h.description,h.is_active,h.version,h.current_version,v.id revision_id,v.product_id,v.type,v.percent_rate,v.fixed_discount,v.buy_quantity,v.free_quantity,v.start_date,v.end_date,v.mixed_product_ids,p.sku,p.name product_name,p.name_si,p.selling_price,p.is_active product_active FROM promotions h JOIN promotion_versions v ON v.promotion_id=h.id AND v.version_number=h.current_version JOIN products p ON p.id=v.product_id';
    }
    public function get(array $actor,string $id,bool $lock=false):array
    {
        $this->admin($actor);Representatives::id($id,'promotion_id');$row=$this->one($this->select().' WHERE h.id=?'.($lock?' FOR UPDATE':''),[$id])??throw new InventoryException(['promotion'=>'Promotion not found.'],404);return $this->serialized($row);
    }
    public function options(array $actor):array{$this->admin($actor);return (new ProductStock($this->pdo))->options($actor);}
    public function listing(array $actor,array $filters,bool $rep=false,?\DateTimeImmutable $instant=null):array
    {
        if($rep)$this->reader($actor);else $this->admin($actor);$where=[];$params=[];$q=Inventory::text($filters['q']??'','q',100);$status=$filters['status']??'all';$type=$filters['type']??'';$page=$filters['page']??'1';
        if(!is_string($status)||!in_array($status,['all','enabled','disabled','current','scheduled','ended','invalid_price'],true)||!is_string($type)||($type!==''&&!isset(self::TYPES[$type]))||!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['filters'=>'Invalid promotion filters.']);
        if($q!==''){$like='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(h.name LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!' OR p.name_si LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM business_code_registry b WHERE b.prefix=0x4d AND b.request_key=CAST(h.id AS CHAR) AND b.code LIKE ? ESCAPE '!'))";array_push($params,$like,$like,$like,$like,$like);}
        if($type!==''){$where[]='v.type=?';$params[]=$type;}if(($filters['product_id']??'')!==''){$where[]='(v.product_id=? OR JSON_CONTAINS(v.mixed_product_ids,JSON_QUOTE(?)))';$pid=Representatives::id($filters['product_id'],'product_id');array_push($params,$pid,$pid);}
        $today=Inventory::today($instant);if($rep||$status==='current'){$where[]="h.is_active=1 AND p.is_active=1 AND (v.start_date IS NULL OR v.start_date<=?) AND (v.end_date IS NULL OR v.end_date>=?) AND (v.type<>'FIXED' OR v.fixed_discount<=p.selling_price)";array_push($params,$today,$today);}
        elseif($status==='enabled'||$status==='disabled'){$where[]='h.is_active=?';$params[]=$status==='enabled'?'1':'0';}
        elseif($status==='scheduled'){$where[]='v.start_date>?';$params[]=$today;}elseif($status==='ended'){$where[]='v.end_date<?';$params[]=$today;}elseif($status==='invalid_price'){$where[]="v.type='FIXED' AND v.fixed_discount>p.selling_price";}
        $sql=$this->select().($where?' WHERE '.implode(' AND ',$where):'');$count=$this->pdo->prepare('SELECT COUNT(*) FROM ('.$sql.') matches');$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,intdiv($total+24,25));$page=min((int)$page,$pages);$stmt=$this->pdo->prepare($sql.' ORDER BY h.name,h.id LIMIT 25 OFFSET '.(($page-1)*25));$stmt->execute($params);return ['rows'=>array_map(fn($r)=>$this->serialized($r),$stmt->fetchAll()),'total'=>$total,'page'=>$page,'pages'=>$pages,'today'=>$today];
    }
    public function draftPreview(array $actor,array $input):array
    {
        $this->admin($actor);$rule=self::validate($input);$p=$this->one('SELECT selling_price,is_active FROM products WHERE id=?',[$rule['product_id']]);if(!$p)throw new InventoryException(['product_id'=>'Choose an existing product.']);$today=Inventory::today();$result=PromotionCalculator::calculate($p['selling_price'],$input['paid_quantity']??'1',isset($rule['mixed_product_ids'])?null:$rule);$result['eligible_now']=(int)$p['is_active']===1&&$rule['is_active']==='1'&&($rule['start_date']===null||$rule['start_date']<=$today)&&($rule['end_date']===null||$rule['end_date']>=$today);if(isset($rule['mixed_product_ids'])){$result['mixed_earned']=(string)intdiv((int)$result['paid_quantity'],6);$result['rounding']='Mixed oil: choose rewards separately in POS; six paid units earn one eligible free oil';}$result['preview_only']=true;return $result;
    }
    /** Phase 8 integration: read-only automatic selection with immutable rule and authoritative price snapshots. */
    public function quote(array $actor,string $product,mixed $paid,?\DateTimeImmutable $instant=null):array
    {
        $owner=!$this->pdo->inTransaction();if($owner){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$this->pdo->beginTransaction();}
        try{
        $this->reader($actor);Representatives::id($product,'product_id');$today=Inventory::today($instant);
        $p=$this->one('SELECT id,sku,name,name_si,selling_price,version,is_active FROM products WHERE id=?',[$product]);if(!$p||!(int)$p['is_active'])throw new InventoryException(['product'=>'Product is inactive or unavailable.']);
        $q=$this->pdo->prepare($this->select().' WHERE h.is_active=1 AND (v.product_id=? OR JSON_CONTAINS(v.mixed_product_ids,JSON_QUOTE(?))) AND (v.start_date IS NULL OR v.start_date<=?) AND (v.end_date IS NULL OR v.end_date>=?) ORDER BY h.id LIMIT 2');$q->execute([$product,$product,$today,$today]);$rows=$q->fetchAll();if(count($rows)>1)throw new InventoryException(['conflict'=>'Multiple eligible rules detected; automatic pricing is blocked until reviewed.'],409);$rule=$rows[0]??null;
        if($rule)$rule=$this->serialized($rule);$result=PromotionCalculator::calculate($p['selling_price'],$paid,isset($rule['mixed_product_ids'])?null:$rule);$result['product_id']=$product;$result['price_version']=(string)$p['version'];$result['business_date']=$today;$result['promotion_id']=$rule?(string)$rule['id']:null;$result['promotion_version_id']=$rule?(string)$rule['revision_id']:null;
        $result['rule_snapshot']=$rule?array_intersect_key($rule,array_flip(['id','revision_id','current_version','name','description','product_id','type','percent_rate','fixed_discount','buy_quantity','free_quantity','start_date','end_date','mixed_product_ids','mixed_policy'])):null;if($owner)$this->pdo->commit();return $result;
        }catch(\Throwable $e){if($owner&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function revisions(array $actor,string $id):array{$this->get($actor,$id);$q=$this->pdo->prepare('SELECT id,version_number,mixed_product_ids,name_snapshot,description_snapshot,is_active_snapshot,type,percent_rate,fixed_discount,buy_quantity,free_quantity,start_date,end_date,published_at_utc FROM promotion_versions WHERE promotion_id=? ORDER BY version_number DESC');$q->execute([$id]);return array_map(fn($r)=>$this->serialized($r),$q->fetchAll());}
    public function save(array $actor,array $input,?string $id=null,bool $statusOnly=false):array
    {
        $this->admin($actor);if($statusOnly&&$id===null)throw new InventoryException(['promotion_id'=>'Choose an existing promotion.']);$request=$input['request_id']??null;if(!is_string($request)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$request))throw new InventoryException(['request_id'=>'Refresh the form to obtain a request ID.']);
        if($id!==null)Representatives::id($id,'promotion_id');$expected=$id===null?null:($input['version']??null);if($id!==null&&(!is_string($expected)||!preg_match('/^[1-9][0-9]{0,18}$/D',$expected)))throw new InventoryException(['version'=>'Reload the current promotion version.']);
        $rule=$statusOnly?['is_active'=>$input['is_active']??null]:self::validate($input);if($statusOnly&&!in_array($rule['is_active'],['0','1'],true))throw new InventoryException(['status'=>'Choose enabled or disabled.']);$hash=hash('sha256',json_encode([$id,$statusOnly,$expected,$rule],JSON_THROW_ON_ERROR));$owner=!$this->pdo->inTransaction();
        for($attempt=0;;$attempt++){
            if($owner){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$this->pdo->beginTransaction();}else $this->pdo->exec('SAVEPOINT promotion_command');
            try{
                $this->pdo->query('SELECT id FROM promotion_write_gate WHERE id=1 FOR UPDATE')->fetchColumn();$done=$this->one('SELECT * FROM promotion_commands WHERE request_id=? FOR UPDATE',[$request]);
                if($done){if($done['payload_hash']!==$hash||(string)$done['actor_user_id']!==(string)$actor['id'])throw new InventoryException(['request_id'=>'Request ID was used with different promotion details.'],409);$result=['promotion_id'=>(string)$done['promotion_id'],'revision_id'=>(string)$done['revision_id'],'version'=>(string)$done['result_version'],'replayed'=>true];}
                else{
                    $before=$id===null?null:$this->get($actor,$id,true);if($before&&$expected!==(string)$before['version'])throw new InventoryException(['version'=>'Promotion changed. Reload before saving.'],409);
                    $r=$rule;if($statusOnly){foreach(['name','description','product_id','type','percent_rate','fixed_discount','buy_quantity','free_quantity','start_date','end_date'] as $key)$r[$key]=$before[$key];if(isset($before['mixed_product_ids']))$r['mixed_product_ids']=$before['mixed_product_ids'];}
                    $members=$r['mixed_product_ids']??[$r['product_id']];
                    foreach($members as $member){$p=$this->one('SELECT id,selling_price,is_active FROM products WHERE id=? FOR UPDATE',[$member]);if(!$p||($r['is_active']==='1'&&!(int)$p['is_active']))throw new InventoryException(['product_id'=>'Choose active existing products for an enabled offer.']);
                    if($r['type']==='FIXED'&&(!$statusOnly||$r['is_active']==='1')&&PromotionCalculator::compareMoney($r['fixed_discount'],$p['selling_price'])>0)throw new InventoryException(['fixed_discount'=>'Fixed discount cannot exceed the current Products selling price.']);
                    if($r['is_active']==='1'){$collision=$this->one("SELECT h.id FROM promotions h JOIN promotion_versions v ON v.promotion_id=h.id AND v.version_number=h.current_version WHERE h.is_active=1 AND (v.product_id=? OR JSON_CONTAINS(v.mixed_product_ids,JSON_QUOTE(?))) AND h.id<>? AND COALESCE(v.start_date,'1000-01-01')<=? AND COALESCE(v.end_date,'9999-12-31')>=? LIMIT 1 FOR UPDATE",[$member,$member,$id??'0',$r['end_date']??'9999-12-31',$r['start_date']??'1000-01-01']);if($collision)throw new InventoryException(['overlap'=>'An enabled offer already overlaps a selected product and date period.'],409);}}
                    $version=$before?(int)$before['version']+1:1;$revision=$before?(int)$before['current_version']+1:1;
                    if(!$before){$this->pdo->prepare('INSERT INTO promotions(name,description,is_active,current_version,version) VALUES (?,?,?,?,?)')->execute([$r['name'],$r['description'],$r['is_active'],$revision,$version]);$promotion=$this->pdo->lastInsertId();\App\Support\BusinessCode::next($this->pdo,'M',(string)$promotion);}
                    else{$promotion=$id;$this->pdo->prepare('UPDATE promotions SET name=?,description=?,is_active=?,current_version=?,version=?,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute([$r['name'],$r['description'],$r['is_active'],$revision,$version,$id]);}
                    $zone=new \DateTimeZone('Asia/Colombo');$utc=new \DateTimeZone('UTC');$from=$r['start_date']===null?null:(new \DateTimeImmutable($r['start_date'].' 00:00:00',$zone))->setTimezone($utc)->format('Y-m-d H:i:s.u');$until=$r['end_date']===null?null:(new \DateTimeImmutable($r['end_date'].' 23:59:59.999999',$zone))->setTimezone($utc)->format('Y-m-d H:i:s.u');
                    $this->pdo->prepare('INSERT INTO promotion_versions(promotion_id,version_number,product_id,type,percent_rate,fixed_discount,buy_quantity,free_quantity,start_date,end_date,valid_from_utc,valid_until_utc,name_snapshot,description_snapshot,is_active_snapshot,published_by_user_id,mixed_product_ids) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$promotion,$revision,$r['product_id'],$r['type'],$r['percent_rate'],$r['fixed_discount'],$r['buy_quantity'],$r['free_quantity'],$r['start_date'],$r['end_date'],$from,$until,$r['name'],$r['description'],$r['is_active'],$actor['id'],isset($r['mixed_product_ids'])?json_encode($r['mixed_product_ids'],JSON_THROW_ON_ERROR):null]);$revisionId=$this->pdo->lastInsertId();
                    $this->pdo->prepare('INSERT INTO promotion_commands(request_id,actor_user_id,payload_hash,promotion_id,revision_id,result_version) VALUES (?,?,?,?,?,?)')->execute([$request,$actor['id'],$hash,$promotion,$revisionId,$version]);
                    $this->pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,before_json,after_json) VALUES (?,?,'promotions',?,?,?)")->execute([$actor['id'],$statusOnly?'PROMOTION_STATUS':($before?'PROMOTION_REVISION':'PROMOTION_CREATE'),$promotion,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),json_encode($r+['revision_id'=>$revisionId,'version'=>(string)$version],JSON_THROW_ON_ERROR)]);
                    $result=['promotion_id'=>$promotion,'revision_id'=>$revisionId,'version'=>(string)$version,'replayed'=>false];
                }
                if($owner)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT promotion_command');return $result;
            }catch(\Throwable $e){if($owner&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$owner&&$this->pdo->inTransaction())$this->pdo->exec('ROLLBACK TO SAVEPOINT promotion_command');$code=$e instanceof \PDOException?(int)($e->errorInfo[1]??0):0;if($owner&&in_array($code,[1205,1213],true)&&$attempt<2)continue;if(in_array($code,[1205,1213,1062],true))throw new InventoryException(['conflict'=>'Concurrent promotion request conflict. Retry with the same request ID.'],409);throw $e;}
        }
    }
}
