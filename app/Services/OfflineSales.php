<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;

/** Signed, authenticated foreground synchronization; no client totals are posting authority. */
final class OfflineSales
{
    public function __construct(private PDO $pdo){}
    private function one(string $sql,array $p=[]):?array{$q=$this->pdo->prepare($sql);$q->execute($p);return $q->fetch()?:null;}
    private function rows(string $sql,array $p=[]):array{$q=$this->pdo->prepare($sql);$q->execute($p);return $q->fetchAll();}
    private function actor(array $a,bool $admin=false):array
    {
        $u=(new Invoices($this->pdo))->actor($a,$admin);if(!$admin&&$u['role']!=='SALES_REP')throw new InventoryException(['access'=>'Registered representative access required.'],403);return $u;
    }
    public static function uuid(mixed $v):string
    {
        if(!is_string($v)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$v))throw new InventoryException(['id'=>'Invalid offline request identity.']);return $v;
    }
    private function tx(callable $fn):array
    {
        $own=!$this->pdo->inTransaction();
        for($attempt=0;;$attempt++){
            if($own){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$this->pdo->beginTransaction();}else $this->pdo->exec('SAVEPOINT offline_command');
            try{$this->one('SELECT id FROM inventory_settings WHERE id=1 FOR UPDATE');$r=$fn();if($own)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT offline_command');return $r;}
            catch(\Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$own&&$this->pdo->inTransaction())$this->pdo->exec('ROLLBACK TO SAVEPOINT offline_command');$code=$e instanceof \PDOException?(int)($e->errorInfo[1]??0):0;if($own&&in_array($code,[1205,1213],true)&&$attempt<2)continue;if(in_array($code,[1205,1213,1062],true))throw new InventoryException(['conflict'=>'Concurrent offline request. Retry the identical request.'],409);throw $e;}
        }
    }
    private function state(string $rep):array
    {
        $this->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$rep]);$this->pdo->prepare('INSERT INTO rep_operating_state(rep_id) VALUES (?) ON DUPLICATE KEY UPDATE rep_id=rep_id')->execute([$rep]);return $this->one('SELECT * FROM rep_operating_state WHERE rep_id=? FOR UPDATE',[$rep]);
    }
    private function audit(array $a,string $action,string $id,array $data):void{$this->pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,after_json) VALUES (?,?,'offline_sales',?,?)")->execute([$a['id'],$action,$id,json_encode($data,JSON_THROW_ON_ERROR)]);}
    public static function verifySignature(string $message,mixed $signature,string $public):bool
    {
        if(!is_string($signature)||strlen($signature)>128)return false;$raw=base64_decode($signature,true);if($raw===false||strlen($raw)!==64)return false;
        $integers='';foreach([substr($raw,0,32),substr($raw,32,32)] as $part){$part=ltrim($part,"\0");if($part==='')$part="\0";if(ord($part[0])>=128)$part="\0".$part;$integers.="\x02".chr(strlen($part)).$part;}
        $der="\x30".chr(strlen($integers)).$integers;$pem="-----BEGIN PUBLIC KEY-----\n".chunk_split($public,64,"\n")."-----END PUBLIC KEY-----\n";
        return openssl_verify($message,$der,$pem,OPENSSL_ALGO_SHA256)===1;
    }
    public function register(array $actor,array $input):array
    {
        $a=$this->actor($actor);$id=self::uuid($input['device_id']??null);$key=$input['public_key']??null;$label=Inventory::text($input['label']??'','label',100,true);
        if(!is_string($key)||strlen($key)>1000||base64_decode($key,true)===false)throw new InventoryException(['device'=>'Invalid device public key.']);$pem="-----BEGIN PUBLIC KEY-----\n".chunk_split($key,64,"\n")."-----END PUBLIC KEY-----\n";$resource=openssl_pkey_get_public($pem);$details=$resource?openssl_pkey_get_details($resource):false;
        if(!$details||$details['type']!==OPENSSL_KEYTYPE_EC||($details['ec']['curve_name']??'')!=='prime256v1'||!self::verifySignature("REGISTER\n".$id."\n".$key,$input['signature']??null,$key))throw new InventoryException(['device'=>'Device key ownership could not be verified.'],403);
        return $this->tx(function()use($a,$id,$key,$label){$this->actor($a);$state=$this->state((string)$a['id']);$old=$this->one('SELECT d.user_id,d.revoked_at_utc,c.public_key FROM devices d JOIN offline_device_controls c ON c.device_id=d.id WHERE d.id=? FOR UPDATE',[$id]);
            if($old){if((string)$old['user_id']!==(string)$a['id']||!hash_equals($old['public_key'],$key)||$old['revoked_at_utc']!==null||$state['primary_device_id']!==$id)throw new InventoryException(['device'=>'Device is revoked, replaced or owned by another user.'],403);return ['device_id'=>$id,'registered'=>true];}
            if($state['primary_device_id']!==null)throw new InventoryException(['device'=>'Another device is registered. Admin revocation and reconciliation are required before replacement.'],409);
            $this->pdo->prepare('INSERT INTO devices(id,user_id,label) VALUES (?,?,?)')->execute([$id,$a['id'],$label]);$this->pdo->prepare('INSERT INTO offline_device_controls(device_id,public_key) VALUES (?,?)')->execute([$id,$key]);$this->pdo->prepare('UPDATE rep_operating_state SET primary_device_id=?,updated_at_utc=UTC_TIMESTAMP(6) WHERE rep_id=?')->execute([$id,$a['id']]);$this->audit($a,'OFFLINE_DEVICE_REGISTERED',$id,[]);return ['device_id'=>$id,'registered'=>true];
        });
    }
    private function envelope(array $a,array $input):array
    {
        HybridInvoiceRequest::rejectOffline($input);
        $body=$input['body']??null;if(!is_string($body)||strlen($body)>500000)throw new InventoryException(['body'=>'Invalid or excessive offline submission.']);try{$p=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new InventoryException(['body'=>'Invalid offline JSON.']);}if(!is_array($p)||($p===[]||array_keys($p)===range(0,count($p)-1)))throw new InventoryException(['body'=>'An offline object is required.']);
        HybridInvoiceRequest::rejectOffline($p);
        $device=self::uuid($p['device_id']??null);$d=$this->one('SELECT d.*,c.public_key,c.last_sequence,c.replacement_cleared_at_utc FROM devices d JOIN offline_device_controls c ON c.device_id=d.id WHERE d.id=?',[$device]);
        if(!$d||(string)$d['user_id']!==(string)$a['id']||!self::verifySignature($body,$input['signature']??null,$d['public_key']))throw new InventoryException(['device'=>'Device identity or signed submission is invalid.'],403);return [$p,$d,hash('sha256',$body)];
    }
    public function bootstrap(array $actor,array $input):array
    {
        $a=$this->actor($actor);
        return $this->tx(function()use($a,$input){$a=$this->actor($a);$state=$this->state((string)$a['id']);[$p,$d,$hash]=$this->envelope($a,$input);if(($p['action']??null)!=='BOOTSTRAP'||$d['revoked_at_utc']!==null||$state['primary_device_id']!==$d['id'])throw new InventoryException(['device'=>'Active primary device registration is required.'],403);$id=self::uuid($p['request_id']??null);$pending=$p['pending_ids']??[];if(!is_array($pending)||count($pending)>1000)throw new InventoryException(['pending'=>'Too many pending sale identities.']);foreach($pending as $value)self::uuid($value);
            $old=$this->one('SELECT approved_snapshot FROM offline_policy_grants WHERE id=?',[$id]);if($old){$s=json_decode($old['approved_snapshot'],true,512,JSON_THROW_ON_ERROR);if(($s['request_hash']??'')!==$hash)throw new InventoryException(['request'=>'Bootstrap identity already used.'],409);return $s;}
            $now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));$version=$this->one('SELECT version FROM users WHERE id=?',[$a['id']]);$customers=$this->rows('SELECT id,customer_code,shop_name,address,city_town,district,version FROM customers WHERE assigned_rep_id=? AND is_active=1 ORDER BY id',[$a['id']]);foreach($customers as &$c){$c['id']=(string)$c['id'];$c['version']=(string)$c['version'];}unset($c);
            $products=[];$stock=[];$ps=$this->rows('SELECT p.id,p.sku,p.name,p.name_si,c.name category,b.quantity FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN representative_stock_balances b ON b.product_id=p.id WHERE b.rep_id=? AND p.is_active=1 ORDER BY p.id',[$a['id']]);foreach($ps as $row){$q=(new Promotions($this->pdo))->quote($a,(string)$row['id'],'1',$now);if(isset($q['rule_snapshot']['mixed_policy'])&&(!is_array($p['promotion_policies']??null)||!in_array(MixedOil::POLICY,$p['promotion_policies'],true)))throw new InventoryException(['update'=>'Close all application tabs, reopen the updated offline workspace and refresh before using mixed oil promotions. Pending sales are retained.'],409);$products[]=['id'=>(string)$row['id'],'sku'=>$row['sku'],'name'=>$row['name'],'name_si'=>$row['name_si']??null,'category'=>$row['category']??'','selling_price'=>$q['unit_price'],'version'=>$q['price_version'],'rule'=>$q['rule_snapshot']];$stock[(string)$row['id']]=(string)$row['quantity'];}
            $receipts=[];foreach(array_unique($pending) as $op){$receipt=$this->receipt($a,$op);if($receipt!==null)$receipts[]=$receipt;}
            $snapshot=['schema_version'=>1,'grant_id'=>$id,'device_id'=>$d['id'],'rep'=>['id'=>(string)$a['id'],'name'=>$a['name'],'version'=>(string)$version['version']],'server_time'=>$now->format('Y-m-d\TH:i:s\Z'),'expires_at'=>$now->modify('+7 days')->format('Y-m-d\TH:i:s\Z'),'customers'=>$customers,'products'=>$products,'stock'=>(object)$stock,'inventory_revision'=>(string)$state['inventory_revision'],'receipts'=>$receipts,'request_hash'=>$hash,'offline_credit'=>false];
            $json=json_encode($snapshot,JSON_THROW_ON_ERROR);$this->pdo->prepare('INSERT INTO offline_policy_grants(id,rep_id,device_id,schema_version,approved_snapshot,snapshot_hash,issued_at_utc,expires_at_utc) VALUES (?,?,?,1,?,?,?,?)')->execute([$id,$a['id'],$d['id'],$json,hash('sha256',$json),$now->format('Y-m-d H:i:s'),$now->modify('+7 days')->format('Y-m-d H:i:s')]);$this->pdo->prepare('UPDATE devices SET last_sync_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute([$d['id']]);return $snapshot;
        });
    }
    private function receipt(array $a,string $id):?array
    {
        $r=$this->one('SELECT o.id,o.result_status,o.invoice_id,o.accepted_at_utc,o.last_error_code,o.last_error_message,i.invoice_number FROM sync_operations o LEFT JOIN invoices i ON i.id=o.invoice_id WHERE o.id=? AND o.user_id=?',[$id,$a['id']]);if(!$r)return null;
        $status=$r['result_status']==='ACCEPTED'?'Synced':($r['result_status']==='REJECTED'?'Rejected':'Needs Attention');return ['operation_id'=>$id,'status'=>$status,'invoice_id'=>$r['invoice_id'],'invoice_number'=>$r['invoice_number'],'accepted_at'=>$r['accepted_at_utc']===null?null:(new \DateTimeImmutable($r['accepted_at_utc'],new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),'code'=>$r['last_error_code'],'message'=>$r['last_error_message'],'reservation_resolved'=>$r['result_status']!=='RECEIVED'];
    }
    public function submit(array $actor,array $input):array
    {
        $a=$this->actor($actor);
        return $this->tx(function()use($a,$input){$a=$this->actor($a);$state=$this->state((string)$a['id']);[$p,$d,$hash]=$this->envelope($a,$input);$id=self::uuid($p['operation_id']??null);$old=$this->one('SELECT canonical_payload_hash FROM sync_operations WHERE id=? FOR UPDATE',[$id]);if($old){if(!hash_equals($old['canonical_payload_hash'],$hash))throw new InventoryException(['request'=>'Sale identity already used with different details.'],409);return $this->receipt($a,$id);}
            $seq=Inventory::quantity($p['sequence']??null);$revoked=$d['revoked_at_utc']!==null||$state['primary_device_id']!==$d['id'];if(!$revoked&&$seq!==(int)$d['last_sequence']+1)throw new InventoryException(['sequence'=>'A previous sale must be submitted first. Restore the pending queue; do not renumber sales.'],409);
            $this->pdo->prepare("INSERT INTO sync_operations(id,user_id,device_id,operation_type,payload_schema_version,canonical_payload_hash,result_status,received_at_utc,last_attempt_at_utc) VALUES (?,?,?,'SALE_CREATE',1,?,'RECEIVED',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([$id,$a['id'],$d['id'],$hash]);$this->pdo->prepare('INSERT INTO offline_sale_payloads(operation_id,device_id,sequence_number,body_json,signature) VALUES (?,?,?,?,?)')->execute([$id,$d['id'],$seq,$input['body'],$input['signature']]);$this->pdo->prepare('UPDATE offline_device_controls SET last_sequence=GREATEST(last_sequence,?) WHERE device_id=?')->execute([$seq,$d['id']]);
            $this->process($a,$p,$d,$state,$revoked);return $this->receipt($a,$id);
        });
    }
    private function process(array $a,array $p,array $d,array $state,bool $revoked):void
    {
        $id=$p['operation_id'];
        try{
            HybridInvoiceRequest::rejectOffline($p);
            if($revoked)throw new InventoryException(['device'=>'Revoked or replaced device: administrator reconciliation required.'],409);
            $grant=$this->one('SELECT * FROM offline_policy_grants WHERE id=? AND rep_id=? AND device_id=?',[self::uuid($p['grant_id']??null),$a['id'],$d['id']]);if(!$grant)throw new InventoryException(['grant'=>'Offline authorization is missing.'],409);$snapshot=json_decode($grant['approved_snapshot'],true,512,JSON_THROW_ON_ERROR);
            $now=gmdate('Y-m-d H:i:s');if(time()>=(new \DateTimeImmutable($grant['expires_at_utc'],new \DateTimeZone('UTC')))->getTimestamp())throw new InventoryException(['expired'=>'Offline authorization expired. Sale and provisional cash are retained for review.'],409);
            $created=$p['created_at_utc']??null;$date=is_string($created)?\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$created,new \DateTimeZone('UTC')):false;if(!$date||$date->format('Y-m-d\TH:i:s\Z')!==$created||$date->getTimestamp()<(new \DateTimeImmutable($grant['issued_at_utc'],new \DateTimeZone('UTC')))->getTimestamp()||$date->getTimestamp()>time())throw new InventoryException(['clock'=>'Sale time is outside its authorization.'],409);
            $user=$this->one('SELECT version FROM users WHERE id=?',[$a['id']]);if((string)$user['version']!==$snapshot['rep']['version'])throw new InventoryException(['account'=>'Representative account changed after provisioning.'],409);
            if(($p['payment_type']??null)!=='CASH')throw new InventoryException(['credit'=>'Offline credit invoices are disabled.'],409);
            $customer=Representatives::id($p['customer_id']??null,'customer_id');$saved=null;foreach($snapshot['customers'] as $c)if($c['id']===$customer)$saved=$c;if(!$saved)throw new InventoryException(['customer'=>'Customer was not authorized in this snapshot.'],409);$current=$this->one('SELECT version,is_active,assigned_rep_id FROM customers WHERE id=? FOR UPDATE',[$customer]);if(!$current||!(int)$current['is_active']||(string)$current['assigned_rep_id']!==(string)$a['id']||(string)$current['version']!==$saved['version'])throw new InventoryException(['customer'=>'Customer assignment, status or details changed.'],409);
            if(array_key_exists('historical_return',$p))throw new InventoryException(['return'=>'Historical returns require an online transaction.'],409);
            foreach(BenefitPolicy::FIELDS as $benefitKey)if(array_key_exists($benefitKey,$p))throw new InventoryException(['benefit'=>'Benefit modes require an online transaction.'],409);
            $items=$p['items']??null;if(!is_array($items)||count($items)<1||count($items)>100)throw new InventoryException(['items'=>'Invalid offline product lines.']);$input=['request_id'=>$id,'customer_id'=>$customer,'stock_source'=>(string)$a['id'],'payment_type'=>'CASH','amount_received'=>$p['amount_received']??null,'items'=>[]];$seen=[];$granted=[];foreach($snapshot['products'] as $v)$granted[$v['id']]=$v;
            foreach($items as $item){if(!is_array($item))throw new InventoryException(['items'=>'Invalid line.']);$product=Representatives::id($item['product_id']??null,'product_id');if(isset($seen[$product])||!isset($granted[$product]))throw new InventoryException(['items'=>'Duplicate or unauthorized product.']);$seen[$product]=true;if(($item['quantity']??null)!=='0')$input['items'][]=['product_id'=>$product,'quantity'=>$item['quantity']??null];}
            $input['oil_rewards']=$p['oil_rewards']??[];
            // Existing Phase 8 review locks source/products/balances and rechecks authoritative prices, rules and stock.
            if(array_key_exists('discount_policy',$p)||array_key_exists('invoice_discount_percent',$p)){$input['discount_policy']=$p['discount_policy']??null;$input['invoice_discount_percent']=$p['invoice_discount_percent']??null;}
            $invoices=new Invoices($this->pdo);$r=$invoices->review($a,$input,true);foreach($r['oil_offers']??[] as $offer)if($offer['remaining']!=='0')throw new InventoryException(['oil_rewards'=>'Select all earned free oils.'],409);if(count($r['lines'])!==count($items))throw new InventoryException(['items'=>'Offline paid and reward lines differ from the authorized calculation.'],409);if(isset($input['discount_policy']))foreach(['invoice_discount_percent','invoice_discount_amount','discountable_subtotal','discount_policy'] as $key)if(($p[$key]??null)!==$r[$key])throw new InventoryException(['totals'=>'Offline invoice discount differs from the approved calculation.'],409);$claimed=[];foreach($items as $item)$claimed[$item['product_id']]=$item;
            foreach($r['lines'] as $line){$g=$granted[$line['product_id']];$cl=$claimed[$line['product_id']]??null;if(!$cl||($cl['quantity']??null)!==$line['paid_quantity'])throw new InventoryException(['items'=>'Offline quantities differ from the signed calculation.'],409);if($line['price_version']!==$g['version']||$line['unit_price']!==$g['selling_price']||json_encode($line['rule_snapshot'],JSON_THROW_ON_ERROR)!==json_encode($g['rule'],JSON_THROW_ON_ERROR))throw new InventoryException(['pricing'=>'Price or promotion revision/eligibility changed. Agreed amounts were not changed.'],409);foreach(['unit_price','price_version','free_quantity','discount_amount','payable_amount'] as $key){if(($cl[$key]??null)!==$line[$key])throw new InventoryException(['totals'=>'Offline line calculations do not match the authorized rules.'],409);}}
            foreach(['subtotal','discount_total','grand_total'] as $key)if(($p[$key]??null)!==$r[$key])throw new InventoryException(['totals'=>'Offline total differs from the authoritative calculation.'],409);
            $input['review_hash']=$r['review_hash'];$result=$invoices->issue($a,$input,['device_id'=>$d['id'],'grant_id'=>$grant['id'],'created_at_utc'=>$date->format('Y-m-d H:i:s')]);$this->pdo->prepare("UPDATE sync_operations SET result_status='ACCEPTED',invoice_id=?,accepted_at_utc=UTC_TIMESTAMP(6),last_attempt_at_utc=UTC_TIMESTAMP(6),last_error_code=NULL,last_error_message=NULL WHERE id=?")->execute([$result['invoice_id'],$id]);$this->audit($a,'OFFLINE_SALE_ACCEPTED',$id,['invoice_id'=>$result['invoice_id']]);
        }catch(InventoryException $e){$code=(string)array_key_first($e->errors);$message=implode(' ',array_values($e->errors));$this->pdo->prepare('UPDATE sync_operations SET last_error_code=?,last_error_message=?,last_attempt_at_utc=UTC_TIMESTAMP(6),attempt_count=attempt_count+1 WHERE id=?')->execute([$code,mb_substr($message,0,500),$id]);$this->audit($a,'OFFLINE_SALE_HELD',$id,['code'=>$code]);}
    }
    public function adminListing(array $actor,array $filters=[]):array
    {
        $this->actor($actor,true);$page=$filters['page']??'1';if(!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['page'=>'Invalid review page.']);$search=Inventory::text($filters['q']??'','q',100);$status=$filters['status']??'';if(!is_string($status)||!in_array($status,['','RECEIVED','ACCEPTED','REJECTED'],true))throw new InventoryException(['filters'=>'Invalid synchronization status.']);$where=[];$params=[];
        if($search!==''){$like='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(o.id LIKE ? ESCAPE '!' OR o.device_id LIKE ? ESCAPE '!' OR u.name LIKE ? ESCAPE '!')";$params=[$like,$like,$like];}if($status!==''){$where[]='o.result_status=?';$params[]=$status;}
        $from=' FROM sync_operations o JOIN offline_sale_payloads p ON p.operation_id=o.id JOIN users u ON u.id=o.user_id'.($where?' WHERE '.implode(' AND ',$where):'');$count=(int)$this->one('SELECT COUNT(*) n'.$from,$params)['n'];$pages=max(1,intdiv($count+49,50));$page=min((int)$page,$pages);
        $result=['page'=>$page,'pages'=>$pages,'total'=>$count,'devices'=>$this->rows('SELECT d.id,d.user_id,u.name,d.label,d.revoked_at_utc,d.last_sync_at_utc,c.replacement_cleared_at_utc FROM devices d JOIN offline_device_controls c ON c.device_id=d.id JOIN users u ON u.id=d.user_id ORDER BY d.registered_at_utc DESC'),'sales'=>$this->rows("SELECT o.id,o.user_id,u.name,o.device_id,o.result_status,o.last_error_code,o.last_error_message,o.received_at_utc,p.body_json ".$from." ORDER BY (o.result_status='RECEIVED') DESC,o.received_at_utc DESC,o.id LIMIT 50 OFFSET ".(($page-1)*50),$params)];
        foreach($result['sales'] as &$sale)$sale['reviews']=$this->rows('SELECT r.action,r.reason,r.cash_reconciliation_note,r.created_at_utc,u.name reviewer FROM offline_sale_reviews r JOIN users u ON u.id=r.actor_user_id WHERE r.operation_id=? ORDER BY r.id',[$sale['id']]);unset($sale);return $result;
    }
    public function review(array $actor,string $id,array $input):array
    {
        $a=$this->actor($actor,true);self::uuid($id);$action=$input['action']??'';if(!in_array($action,['RETRY','REJECT'],true))throw new InventoryException(['action'=>'Choose Retry unchanged terms or Reject after reconciliation.']);$reason=Inventory::text($input['reason']??'','reason',2000,true);$note=Inventory::text($input['cash_reconciliation_note']??'','cash_reconciliation_note',2000,$action==='REJECT');if($action==='REJECT'&&($input['reconciled']??null)!=='1')throw new InventoryException(['reconciled'=>'Confirm physical stock and provisional cash were reconciled externally. No automatic refund will be recorded.']);
        return $this->tx(function()use($a,$id,$action,$reason,$note){$this->actor($a,true);$op=$this->one('SELECT o.*,p.body_json FROM sync_operations o JOIN offline_sale_payloads p ON p.operation_id=o.id WHERE o.id=? FOR UPDATE',[$id]);if(!$op)throw new InventoryException(['sale'=>'Offline sale not found.'],404);if($op['result_status']!=='RECEIVED')return ['operation_id'=>$id,'already_resolved'=>true];$this->pdo->prepare('INSERT INTO offline_sale_reviews(operation_id,actor_user_id,action,reason,cash_reconciliation_note) VALUES (?,?,?,?,?)')->execute([$id,$a['id'],$action,$reason,$note?:null]);
            if($action==='REJECT'){$this->pdo->prepare("UPDATE sync_operations SET result_status='REJECTED',last_error_code='ADMIN_REJECTED',last_error_message='Rejected after explicit external stock/cash reconciliation. No invoice or refund was posted.' WHERE id=?")->execute([$id]);}
            else{$rep=$this->actor(['id'=>$op['user_id']]);$state=$this->state((string)$rep['id']);$d=$this->one('SELECT * FROM devices WHERE id=?',[$op['device_id']]);$this->process($rep,json_decode($op['body_json'],true,32,JSON_THROW_ON_ERROR),$d,$state,$d['revoked_at_utc']!==null||$state['primary_device_id']!==$d['id']);}
            $this->audit($a,'OFFLINE_SALE_REVIEWED',$id,['action'=>$action,'reason'=>$reason]);return ['operation_id'=>$id,'reviewed'=>true];
        });
    }
    public function device(array $actor,string $id,array $input):array
    {
        $a=$this->actor($actor,true);self::uuid($id);$reason=Inventory::text($input['reason']??'','reason',2000,true);$action=$input['action']??'';if(!in_array($action,['REVOKE','RELEASE'],true))throw new InventoryException(['action'=>'Invalid device action.']);
        return $this->tx(function()use($a,$id,$input,$reason,$action){$this->actor($a,true);$d=$this->one('SELECT * FROM devices WHERE id=?',[$id]);if(!$d)throw new InventoryException(['device'=>'Device not found.'],404);$state=$this->state((string)$d['user_id']);
            if($action==='REVOKE')$this->pdo->prepare('UPDATE devices SET revoked_at_utc=COALESCE(revoked_at_utc,UTC_TIMESTAMP(6)) WHERE id=?')->execute([$id]);
            else{if($d['revoked_at_utc']===null||($input['reconciled']??null)!=='1')throw new InventoryException(['device'=>'Revoke first and explicitly reconcile all stock, provisional cash and unuploaded sales before replacement.']);$pending=$this->one("SELECT id FROM sync_operations WHERE device_id=? AND result_status='RECEIVED' LIMIT 1",[$id]);if($pending)throw new InventoryException(['device'=>'Unresolved server-side sales remain. Review them before replacement.'],409);$this->pdo->prepare('UPDATE offline_device_controls SET replacement_cleared_at_utc=COALESCE(replacement_cleared_at_utc,UTC_TIMESTAMP(6)) WHERE device_id=?')->execute([$id]);if($state['primary_device_id']===$id)$this->pdo->prepare('UPDATE rep_operating_state SET primary_device_id=NULL WHERE rep_id=?')->execute([$d['user_id']]);}
            $this->audit($a,'OFFLINE_DEVICE_'.$action,$id,['reason'=>$reason,'reconciliation_attested'=>$action==='RELEASE']);return ['device_id'=>$id,'action'=>$action];
        });
    }
}
