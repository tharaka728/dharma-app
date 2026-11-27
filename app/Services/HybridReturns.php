<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Support\InventoryException;
use App\Services\PromotionCalculator as M;

/** Stage 4 coordinator. No operational connection factory, no legacy-return policy changes. */
final class HybridReturns
{
    private PdoHybridInvoiceContext $context;
    public function __construct(private PDO $pdo,private array $config,private ?array $scope=null,private bool $postingHold=true,private ?\Closure $fault=null)
    {$this->context=new PdoHybridInvoiceContext($pdo,$scope);}
    private function step(string $name):void{if($this->fault)($this->fault)($name);}
    private function rows(string $sql,array $params=[]):array{$q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);}
    private function one(string $sql,array $params=[]):?array{return $this->rows($sql,$params)[0]??null;}
    private function insert(string $table,array $r):void{$this->pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($r)).') VALUES ('.implode(',',array_fill(0,count($r),'?')).')')->execute(array_values($r));}
    private function schema():bool
    {
        foreach(['025_hybrid_invoice_snapshots.sql','026_hybrid_linked_returns.sql','027_hybrid_custody_dispositions.sql']as$file){$r=$this->one('SELECT checksum,status FROM schema_migrations WHERE version=?',[$file]);if(!$r||$r['status']!=='applied'||!hash_equals($r['checksum'],hash_file('sha256',dirname(__DIR__,2).'/database/migrations/'.$file)))return false;}return true;
    }
    private function tx(callable $fn,bool $readOnly=false):array
    {
        $own=!$this->pdo->inTransaction();for($attempt=0;;$attempt++)try{return $this->context->transaction($fn,$readOnly);}catch(\PDOException $e){$code=(int)($e->errorInfo[1]??0);if($own&&in_array($code,[1205,1213],true)&&$attempt<2)continue;if(in_array($code,[1205,1213,1062],true))throw new InventoryException(['conflict'=>'Concurrent Hybrid adjustment conflict. Retry the same request identity.'],409);throw $e;}
    }
    private function requireGate(array $ctx,string $operation):void
    {
        HybridFeatureGate::require($this->config,$this->context->database(),$ctx['control'],$this->schema(),$operation);
        if($operation==='return_posting'&&$this->postingHold)throw new InventoryException(['return'=>'Hybrid financial posting is held.'],403);
    }
    public function availability(array $identity):array
    {
        if(($this->config['enabled']??false)!==true)return ['enabled'=>false,'posting'=>false,'custody'=>false,'manager'=>false];
        return $this->tx(function()use($identity){$this->context->inventoryGate();$c=$this->context->controls();$a=$this->context->actor($identity);$ctx=['control'=>$c];$this->requireGate($ctx,'preview');
            return ['enabled'=>true,'posting'=>!$this->postingHold&&($this->config['return_posting']??false)===true&&(int)$c['return_posting']===1&&($this->config['financial_posting_hold']??true)===false,
                'custody'=>($this->config['custody']??false)===true&&(int)$c['custody']===1,'manager'=>$a['role']==='ADMIN'];},true);
    }
    public function options(array $identity):array
    {
        HybridFeatureGate::configured($this->config);
        return $this->tx(function()use($identity){$this->context->inventoryGate();$control=$this->context->controls();$a=$this->context->actor($identity);$this->requireGate(['control'=>$control],'preview');
            $params=[];$scope='';if($a['role']==='SALES_REP'){$scope=' AND i.rep_id=? AND c.assigned_rep_id=?';$params=[$a['id'],$a['id']];}
            return ['invoices'=>$this->rows('SELECT i.id,i.invoice_number,c.shop_name FROM invoices i JOIN hybrid_invoice_seals h ON h.invoice_id=i.id JOIN customers c ON c.id=i.customer_id WHERE 1=1'.$scope.' ORDER BY i.accepted_at_utc DESC,i.id LIMIT 200',$params)];},true);
    }
    private function load(array $identity,string $invoice):array
    {
        $settings=$this->context->inventoryGate();$control=$this->context->controls();$actor=$this->context->actor($identity);
        if(!$this->schema())throw new InventoryException(['return'=>'Hybrid return and custody schema is unavailable.'],409);
        $header=$this->one('SELECT i.customer_id,i.rep_id,h.source_location_id,l.type,l.rep_id location_rep,l.is_active location_active FROM invoices i JOIN hybrid_invoice_seals s ON s.invoice_id=i.id JOIN hybrid_invoice_snapshots h ON h.invoice_id=i.id JOIN stock_locations l ON l.id=h.source_location_id WHERE i.id=?',[$invoice]);
        if(!$header)throw new InventoryException(['invoice'=>'A sealed Hybrid invoice is required.'],404);
        $source=$header['type']==='WAREHOUSE'?'warehouse':(string)$header['rep_id'];
        if($source!=='warehouse'){
            $this->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$source]);$this->one('SELECT rep_id FROM rep_operating_state WHERE rep_id=? FOR UPDATE',[$source]);
            if((string)$header['location_rep']!==$source)throw new InventoryException(['source'=>'Original stock source identity no longer matches.'],409);
        }
        $customer=$this->context->customer((string)$header['customer_id']);
        if(!$customer||($actor['role']==='SALES_REP'&&((string)$header['rep_id']!==(string)$actor['id']||(string)$customer['assigned_rep_id']!==(string)$actor['id'])))throw new InventoryException(['access'=>'Original invoice/customer is not available to you.'],403);
        $this->one('SELECT id FROM invoices WHERE id=? FOR UPDATE',[$invoice]);$projection=$this->one('SELECT * FROM hybrid_return_state WHERE invoice_id=? FOR UPDATE',[$invoice]);
        $pending=$this->rows("SELECT r.id FROM hybrid_custody_receipts r JOIN hybrid_custody_state s ON s.custody_id=r.id WHERE r.invoice_id=? AND s.status<>'ACCEPTED' ORDER BY r.id FOR UPDATE",[$invoice]);
        if(count($pending)>1||($pending[0]['id']??null)!==($projection['pending_custody_id']??null))throw new \LogicException('Pending physical-custody reservation does not reconcile.');
        $this->context->promotionGate();
        $record=(new HybridInvoiceReader($this->pdo))->get($actor,$invoice);$ids=array_column($record['snapshot']['review']['calculation']['lines'],'product_id');usort($ids,static fn($a,$b)=>M::compare($a,$b));
        foreach($ids as$id)if(!$this->one('SELECT id FROM products WHERE id=? FOR UPDATE',[$id]))throw new InventoryException(['product'=>'Original product identity is unavailable.'],409);
        return ['settings'=>$settings,'control'=>$control,'actor'=>$actor,'customer'=>$customer,'record'=>$record,'projection'=>$projection,'source'=>$source,'location'=>(string)$header['source_location_id'],'location_active'=>(bool)$header['location_active'],'invoice'=>$invoice];
    }
    public function details(array $identity,string $invoice):array
    {
        HybridFeatureGate::configured($this->config);HybridReturnRequest::uuid($invoice);
        return $this->tx(function()use($identity,$invoice){$ctx=$this->load($identity,$invoice);$this->requireGate($ctx,'preview');$lines=[];
            $names=array_column($this->rows('SELECT line_number,product_name_snapshot FROM invoice_items WHERE invoice_id=?',[$invoice]),'product_name_snapshot','line_number');
            foreach($ctx['record']['snapshot']['review']['calculation']['lines']as$l){$state=$ctx['record']['adjustments']['state']['lines'][(string)$l['line_number']];$lines[]=['line_number'=>(string)$l['line_number'],'product_id'=>$l['product_id'],'product_name'=>$names[$l['line_number']],'original_paid'=>$l['paid_quantity'],'original_free'=>$l['actual_free_quantity'],'remaining_paid'=>(string)((int)$l['paid_quantity']-$state['paid']),'remaining_free'=>(string)((int)$l['actual_free_quantity']-$state['free'])];}
            $pending=$ctx['projection']['pending_custody_id']??null;
            return ['invoice_id'=>$invoice,'lines'=>$lines,'financial'=>$ctx['record']['financial'],'pending_custody'=>$pending?$this->custody($pending):null,
                'custodies'=>$this->rows('SELECT r.id,s.version,s.status FROM hybrid_custody_receipts r JOIN hybrid_custody_state s ON s.custody_id=r.id WHERE r.invoice_id=? ORDER BY r.created_at_utc,r.id',[$invoice])];},true);
    }
    private function prepare(array $ctx,array $request):array
    {
        if($request['operation']==='VOID'&&$ctx['actor']['role']!=='ADMIN')throw new InventoryException(['access'=>'Residual full void requires administrator authorization.'],403);
        $custody=null;$pending=$ctx['projection']['pending_custody_id']??null;
        if(isset($request['custody_id'])){
            if($pending!==$request['custody_id'])throw new InventoryException(['custody'=>'This is not the invoice pending-custody receipt.'],409);
            $custody=$this->custody($pending);
            if($custody['status']!=='PENDING'||(string)$custody['version']!==$request['custody_version'])throw new InventoryException(['custody'=>'Custody changed or requires manager review. Obtain a new preview.'],409);
            $request['lines']=HybridCustodyLedger::receiptLines($custody);
        }elseif($pending)throw new InventoryException(['custody'=>'Resolve the existing pending physical receipt before accepting another return.'],409);
        $b=$ctx['record']['financial'];$basis=['cash_collected'=>$b['cash_collected'],'paid_amount'=>$b['paid_amount'],'noncash'=>$b['noncash_settlement'],'legacy_void'=>$b['legacy_void']];
        $plan=HybridReturnPolicy::plan($ctx['record']['snapshot'],$ctx['record']['adjustments']['history'],$request,$basis);
        $fingerprint=['request'=>array_diff_key($request,['review_hash'=>true]),'seal'=>$ctx['record']['snapshot']['seal']['digest'],'actor'=>(string)$ctx['actor']['id'],'customer_version'=>(string)$ctx['customer']['version'],
            'control_version'=>(string)$ctx['control']['version'],'state_version'=>(string)($ctx['projection']['version']??'0'),'financial'=>$b,'custody_version'=>$custody['version']??null,'plan'=>array_diff_key($plan,['next_state'=>true])];
        return ['plan'=>$plan,'request'=>$request,'custody'=>$custody,'review_hash'=>HybridInvoiceRequest::digest($fingerprint),'can_post'=>!$plan['findings']];
    }
    public function preview(array $identity,array $input):array
    {
        HybridFeatureGate::configured($this->config);$r=HybridReturnRequest::normalize($input);
        return $this->tx(function()use($identity,$r){$ctx=$this->load($identity,$r['invoice_id']);$this->requireGate($ctx,'preview');$p=$this->prepare($ctx,$r);unset($p['plan']['next_state']);return $p;},true);
    }
    private function command(array $ctx,string $id,string $operation,array $payload):array
    {
        $hash=HybridInvoiceRequest::digest(['operation'=>$operation,'payload'=>$payload]);$old=$this->one('SELECT * FROM hybrid_return_commands WHERE request_id=? FOR UPDATE',[$id]);
        if($old){if((string)$old['actor_user_id']!==(string)$ctx['actor']['id']||$old['invoice_id']!==$ctx['invoice']||$old['operation']!==$operation||!hash_equals($old['payload_hash'],$hash))throw new InventoryException(['request_id'=>'Adjustment identity was used with different actor, operation or details.'],409);
            $result=$this->one('SELECT result_json FROM hybrid_return_results WHERE request_id=?',[$id]);if(!$result)throw new InventoryException(['request_id'=>'Incomplete adjustment requires administrator reconciliation.'],409);return ['replay'=>json_decode($result['result_json'],true,512,JSON_THROW_ON_ERROR)];}
        foreach(['hybrid_commands'=>'request_id','invoices'=>'id','payments'=>'id','sync_operations'=>'id','inventory_transactions'=>'id','invoice_receipts'=>'request_id']as$table=>$key)if($this->one('SELECT '.$key.' FROM '.$table.' WHERE '.$key.'=? FOR UPDATE',[$id]))throw new InventoryException(['request_id'=>'Identity belongs to an existing financial or offline operation.'],409);
        return ['hash'=>$hash];
    }
    private function claim(array $ctx,string $id,string $operation,string $hash):void
    {$this->insert('hybrid_return_commands',['request_id'=>$id,'invoice_id'=>$ctx['invoice'],'actor_user_id'=>$ctx['actor']['id'],'operation'=>$operation,'payload_hash'=>$hash]);$this->step('command');}
    private function projection(array $ctx,array $state,?string $pending):void
    {
        $this->pdo->prepare('INSERT INTO hybrid_return_state(invoice_id,revision,credit,debt_reduction,refund_due,pending_custody_id) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE revision=VALUES(revision),credit=VALUES(credit),debt_reduction=VALUES(debt_reduction),refund_due=VALUES(refund_due),pending_custody_id=VALUES(pending_custody_id),version=version+1')
            ->execute([$ctx['invoice'],$state['revision'],$state['credit'],$state['debt_reduction'],$state['refund_due'],$pending]);
    }
    private function complete(array $ctx,string $id,array $result,string $reason):array
    {
        if($ctx['source']!=='warehouse')$this->pdo->prepare('UPDATE rep_operating_state SET inventory_revision=inventory_revision+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE rep_id=?')->execute([$ctx['source']]);
        foreach($ctx['source']==='warehouse'?['ADMIN']:['ADMIN','REP:'.$ctx['source']]as$scope){
            $this->pdo->prepare('INSERT INTO sync_stream_state(scope_key,last_change_id) VALUES (?,0) ON DUPLICATE KEY UPDATE scope_key=scope_key')->execute([$scope]);$this->one('SELECT last_change_id FROM sync_stream_state WHERE scope_key=? FOR UPDATE',[$scope]);$this->pdo->prepare('UPDATE sync_stream_state SET last_change_id=last_change_id+1 WHERE scope_key=?')->execute([$scope]);
            $this->pdo->prepare("INSERT INTO change_log(scope_key,change_id,entity_type,entity_key,entity_version,action,changed_at_utc) SELECT scope_key,last_change_id,'invoice',?,last_change_id,'UPSERT',UTC_TIMESTAMP(6) FROM sync_stream_state WHERE scope_key=?")->execute([$ctx['invoice'],$scope]);
        }$this->step('streams');
        $this->insert('audit_log',['actor_user_id'=>$ctx['actor']['id'],'action'=>'HYBRID_ADJUSTMENT','entity_type'=>'invoices','entity_key'=>$ctx['invoice'],'reason'=>$reason,'after_json'=>HybridInvoiceRequest::json($result)]);$this->step('audit');
        $this->insert('hybrid_return_results',['request_id'=>$id,'result_json'=>HybridInvoiceRequest::json($result)]);$this->step('result');return $result+['replayed'=>false];
    }
    private function stock(array $ctx,string $id,array $quantities,string $reason):?string
    {
        $quantities=array_filter($quantities,static fn($n)=>$n>0);if(!$quantities)return null;if(!$ctx['location_active'])throw new InventoryException(['source'=>'Original physical stock location is inactive.'],409);
        uksort($quantities,static fn($a,$b)=>M::compare((string)$a,(string)$b));
        $this->insert('inventory_transactions',['id'=>$id,'type'=>'ADJUSTMENT','invoice_id'=>null,'reason'=>$reason,'created_by_user_id'=>$ctx['actor']['id']]);$line=0;
        foreach($quantities as$product=>$quantity){[$location,$balance]=$this->context->balance($ctx['settings'],$ctx['source'],(string)$product);
            if($location!==$ctx['location']||$quantity>PHP_INT_MAX-(int)$balance)throw new InventoryException(['stock'=>'Original source or stock range does not reconcile.'],409);$after=(int)$balance+$quantity;
            if($ctx['source']==='warehouse'){$q=$this->pdo->prepare('UPDATE product_stock_balances SET quantity=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE product_id=?');$q->execute([$after,$product]);}
            else{$q=$this->pdo->prepare('UPDATE representative_stock_balances SET quantity=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE rep_id=? AND product_id=?');$q->execute([$after,$ctx['source'],$product]);}
            if($q->rowCount()!==1)throw new InventoryException(['stock'=>'Original stock balance row is missing.'],409);
            $this->insert('inventory_movements',['transaction_id'=>$id,'line_number'=>++$line,'location_id'=>$location,'product_id'=>$product,'quantity_delta'=>$quantity,'quantity_after'=>$after]);
        }$this->step('stock');return $id;
    }
    public function post(array $identity,array $input):array
    {
        $r=HybridReturnRequest::normalize($input);if(!isset($r['review_hash']))throw new InventoryException(['review'=>'Obtain an authoritative return preview before posting.']);
        return $this->tx(function()use($identity,$r){$ctx=$this->load($identity,$r['invoice_id']);$cmd=$this->command($ctx,$r['request_id'],$r['operation'],$r);if(isset($cmd['replay']))return $cmd['replay']+['replayed'=>true];
            $this->requireGate($ctx,'return_posting');$p=$this->prepare($ctx,$r);
            if(!hash_equals($p['review_hash'],$r['review_hash']))throw new InventoryException(['review'=>'Return review is stale. Obtain a new preview.'],409);
            if($p['plan']['findings'])throw new InventoryException(['return'=>implode(' ',array_column($p['plan']['findings'],'message'))],409);
            $this->claim($ctx,$r['request_id'],$r['operation'],$cmd['hash']);$plan=$p['plan'];$custodyId=$r['custody_id']??null;
            if(!$custodyId&&$p['request']['lines']){$custodyId=HybridReturnRequest::derived($r['request_id'],'custody');$this->createCustody($ctx,$custodyId,$r['request_id'],$p['request']['lines'],$r['reason'],[]);}
            $quantities=[];foreach($plan['lines']as$line){$receipt=$line['receipt'];$n=(int)$receipt['paid_conditions']['SALEABLE']+(int)$receipt['free_conditions']['SALEABLE'];$quantities[$line['product_id']]=($quantities[$line['product_id']]??0)+$n;}
            $stock=$this->stock($ctx,$r['request_id'],$quantities,$r['reason']);$stored=array_diff_key($plan,['next_state'=>true]);
            $this->insert('hybrid_returns',['id'=>$r['request_id'],'invoice_id'=>$ctx['invoice'],'revision'=>$plan['revision'],'actor_user_id'=>$ctx['actor']['id'],'custody_id'=>$custodyId,'stock_transaction_id'=>$stock,'policy_version'=>HybridReturnRequest::POLICY,'operation'=>$r['operation'],
                'cash_at_return'=>$plan['cash_at_return'],'debt_reduction'=>$plan['debt_reduction'],'refund_increment'=>$plan['refund_increment'],'retained_gross'=>$plan['retained_gross'],'retained_discount'=>$plan['retained_discount'],'reason'=>$r['reason'],'review_hash'=>$r['review_hash'],'plan_json'=>HybridInvoiceRequest::json($stored)]+$plan['totals']);$this->step('credit');
            foreach($plan['lines']as$line){$this->insert('hybrid_return_lines',['return_id'=>$r['request_id'],'invoice_id'=>$ctx['invoice'],'line_number'=>$line['line_number'],'paid_quantity'=>$line['receipt']['paid_quantity'],'free_quantity'=>$line['receipt']['free_quantity'],'free_position_start'=>$line['free_position_start'],'receipt_json'=>HybridInvoiceRequest::json($line['receipt'])]);
                foreach($line['ranges']as$range){[, $segment]=explode(':',$range['segment']);$row=['return_id'=>$r['request_id'],'invoice_id'=>$ctx['invoice'],'line_number'=>$line['line_number'],'segment_number'=>$segment,'position_start'=>$range['position_start'],'position_count'=>$range['position_count']];foreach(['gross_amount'=>'gross','product_discount_amount'=>'product_discount_amount','replacement_discount_amount'=>'replacement_discount_amount','invoice_discount_amount'=>'invoice_discount_amount','payable_amount'=>'net']as$key=>$component)$row[$key]=M::money($range['cents'][$component]);$this->insert('hybrid_return_allocations',$row);}
            }$this->step('allocations');
            if($r['operation']==='VOID')$this->insert('hybrid_voids',['invoice_id'=>$ctx['invoice'],'return_id'=>$r['request_id']]);
            if($custodyId)$this->appendCustody($ctx,$custodyId,$r['request_id'],'ACCEPTED',['return_id'=>$r['request_id']],$r['reason'],$stock);
            $this->step('custody');$this->projection($ctx,$plan['next_state'],null);
            foreach($plan['next_state']['lines']as$number=>$line)foreach($line['cursors']as$key=>$count){[, $segment]=explode(':',$key);$this->pdo->prepare('INSERT INTO hybrid_segment_cursors(invoice_id,line_number,segment_number,consumed_positions) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE consumed_positions=VALUES(consumed_positions)')->execute([$ctx['invoice'],$number,$segment,$count]);}
            $this->step('projection');
            (new HybridInvoiceReader($this->pdo))->get($ctx['actor'],$ctx['invoice']);
            return $this->complete($ctx,$r['request_id'],['return_id'=>$r['request_id'],'invoice_id'=>$ctx['invoice'],'custody_id'=>$custodyId,'net_credit'=>$plan['totals']['net_credit'],'debt_reduction'=>$plan['debt_reduction'],'refund_increment'=>$plan['refund_increment'],'operation'=>$r['operation']],$r['reason']);});
    }
    private function custody(string $id):array
    {
        $header=$this->one('SELECT * FROM hybrid_custody_receipts WHERE id=?',[$id]);if(!$header)throw new InventoryException(['custody'=>'Custody receipt not found.'],404);
        $products=array_column($this->rows('SELECT line_number,product_id FROM hybrid_invoice_line_snapshots WHERE invoice_id=?',[$header['invoice_id']]),'product_id','line_number');$events=[];
        foreach($this->rows('SELECT * FROM hybrid_custody_events WHERE custody_id=? ORDER BY version',[$id])as$row){
            $e=json_decode($row['event_json'],true,512,JSON_THROW_ON_ERROR);if((int)$row['version']!==$e['version']||$row['event_type']!==$e['type'])throw new \LogicException('Custody immutable event mismatch.');
            $physical=$this->rows('SELECT * FROM hybrid_custody_lines WHERE event_id=? ORDER BY line_number',[$row['id']]);$expected=[];
            if(in_array($e['type'],['RECEIVED','SUPPLEMENT'],true))foreach($e['data']['lines']as$line){$r=['line_number'=>$line['line_number'],'product_id'=>$products[$line['line_number']],'holding_reference'=>$line['holding_reference'],'condition_reason'=>$line['condition_reason']];foreach(['paid','free']as$kind)foreach(HybridReturnRequest::CONDITIONS as$condition)$r[$kind.'_'.strtolower($condition)]=$line[$kind.'_conditions'][$condition];$expected[]=$r;}
            if(count($physical)!==count($expected))throw new \LogicException('Custody intake child count mismatch.');
            foreach($expected as$i=>$r)foreach($r as$key=>$value)if((string)$physical[$i][$key]!==(string)$value)throw new \LogicException('Custody intake projection mismatch.');$events[]=$e;
        }
        $projection=HybridCustodyLedger::rebuild($id,$events);$stored=$this->one('SELECT * FROM hybrid_custody_state WHERE custody_id=?',[$id]);
        if(!$stored||(int)$stored['version']!==$projection['version']||$stored['status']!==$projection['status']||$stored['accepted_return_id']!==$projection['accepted_return_id']||HybridInvoiceRequest::json(json_decode($stored['projection_json'],true,512,JSON_THROW_ON_ERROR))!==HybridInvoiceRequest::json($projection))throw new \LogicException('Custody projection does not reconcile to immutable events.');
        return $projection+['invoice_id'=>$header['invoice_id'],'source_location_id'=>(string)$header['source_location_id']];
    }
    public function custodyDetails(array $identity,string $invoice,string $id):array
    {
        HybridFeatureGate::configured($this->config);HybridReturnRequest::uuid($invoice);HybridReturnRequest::uuid($id);
        return $this->tx(function()use($identity,$invoice,$id){$ctx=$this->load($identity,$invoice);$this->requireGate($ctx,'preview');$p=$this->custody($id);if($p['invoice_id']!==$invoice)throw new InventoryException(['custody'=>'Receipt does not belong to this invoice.'],403);
            return $p+['events'=>$this->rows('SELECT e.event_type,e.reason,e.version,e.stock_transaction_id,e.created_at_utc,u.name actor FROM hybrid_custody_events e JOIN users u ON u.id=e.actor_user_id WHERE e.custody_id=? ORDER BY e.version',[$id])];},true);
    }
    private function appendCustody(array $ctx,string $id,string $command,string $type,array $data,string $reason,?string $stock=null):array
    {
        $rows=$this->rows('SELECT event_json FROM hybrid_custody_events WHERE custody_id=? ORDER BY version FOR UPDATE',[$id]);$events=array_map(static fn($r)=>json_decode($r['event_json'],true,512,JSON_THROW_ON_ERROR),$rows);
        $e=['version'=>count($events)+1,'type'=>$type,'data'=>$data];$events[]=$e;$p=HybridCustodyLedger::rebuild($id,$events);$eventId=HybridReturnRequest::derived($command,'custody-event-'.$e['version']);
        $this->insert('hybrid_custody_events',['id'=>$eventId,'command_id'=>$command,'custody_id'=>$id,'version'=>$e['version'],'actor_user_id'=>$ctx['actor']['id'],'event_type'=>$type,'event_json'=>HybridInvoiceRequest::json($e),'stock_transaction_id'=>$stock,'reason'=>$reason]);
        if(in_array($type,['RECEIVED','SUPPLEMENT'],true)){
            $products=array_column($ctx['record']['snapshot']['review']['calculation']['lines'],'product_id','line_number');
            foreach($data['lines']as$line){$r=['event_id'=>$eventId,'custody_id'=>$id,'invoice_id'=>$ctx['invoice'],'line_number'=>$line['line_number'],'product_id'=>$products[$line['line_number']],'holding_reference'=>$line['holding_reference'],'condition_reason'=>$line['condition_reason']];foreach(['paid','free']as$kind)foreach(HybridReturnRequest::CONDITIONS as$condition)$r[$kind.'_'.strtolower($condition)]=$line[$kind.'_conditions'][$condition];$this->insert('hybrid_custody_lines',$r);}
        }
        $this->pdo->prepare('INSERT INTO hybrid_custody_state(custody_id,version,status,accepted_return_id,projection_json) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE version=VALUES(version),status=VALUES(status),accepted_return_id=VALUES(accepted_return_id),projection_json=VALUES(projection_json)')->execute([$id,$p['version'],$p['status'],$p['accepted_return_id'],HybridInvoiceRequest::json($p)]);
        $this->step('custody_event');return $p;
    }
    private function intakeLimits(array $ctx,array $lines,?array $existing=null):void
    {
        if(!$lines)throw new InventoryException(['receipt'=>'Receive a positive original paid or free quantity.']);
        $original=array_column($ctx['record']['snapshot']['review']['calculation']['lines'],null,'line_number');$state=$ctx['record']['adjustments']['state'];
        foreach($lines as$l){$n=$l['line_number'];if(!isset($original[$n]))throw new InventoryException(['line'=>'Custody must reference an original invoice line.']);$prior=$existing['lines'][$n]['received']??['paid'=>[],'free'=>[]];
            foreach(['paid'=>'paid_quantity','free'=>'actual_free_quantity']as$kind=>$field){$already=array_sum($prior[$kind]);$remaining=(int)$original[$n][$field]-$state['lines'][$n][$kind];if($already>$remaining||(int)$l[$kind.'_quantity']>$remaining-$already)throw new InventoryException(['receipt'=>'Physical custody exceeds original unreturned delivered goods.'],409);}
        }
    }
    private function createCustody(array $ctx,string $id,string $command,array $lines,string $reason,array $findings):void
    {
        $this->intakeLimits($ctx,$lines);
        $this->insert('hybrid_custody_receipts',['id'=>$id,'invoice_id'=>$ctx['invoice'],'actor_user_id'=>$ctx['actor']['id'],'source_location_id'=>$ctx['location'],'reason'=>$reason]);
        $this->appendCustody($ctx,$id,$command,'RECEIVED',['lines'=>$lines,'blocked_findings'=>$findings],$reason);$this->step('custody_receipt');
    }
    public function receiveCustody(array $identity,array $input):array
    {
        $r=HybridReturnRequest::normalize($input);unset($r['review_hash']);if(isset($r['custody_id']))throw new InventoryException(['custody'=>'Use a supplemental receipt for an existing custody identity.']);
        return $this->tx(function()use($identity,$r){$ctx=$this->load($identity,$r['invoice_id']);$cmd=$this->command($ctx,$r['request_id'],'CUSTODY_RECEIVE',$r);if(isset($cmd['replay']))return $cmd['replay']+['replayed'=>true];$this->requireGate($ctx,'custody');
            if($ctx['projection']['pending_custody_id']??null)throw new InventoryException(['custody'=>'This invoice already has an unresolved physical receipt. Supplement that receipt instead.'],409);
            $plan=$this->prepare($ctx,$r)['plan'];$this->claim($ctx,$r['request_id'],'CUSTODY_RECEIVE',$cmd['hash']);$id=HybridReturnRequest::derived($r['request_id'],'custody');
            $this->createCustody($ctx,$id,$r['request_id'],$r['lines'],$r['reason'],$plan['findings']);$this->projection($ctx,$ctx['record']['adjustments']['state'],$id);$this->step('projection');
            return $this->complete($ctx,$r['request_id'],['custody_id'=>$id,'invoice_id'=>$ctx['invoice'],'status'=>'PENDING','financial_effect'=>'NONE','saleable_stock_effect'=>'0'],$r['reason']);});
    }
    private function custodyRequest(array $input,bool $manager):array
    {
        HybridInvoiceRequest::keys($input,['policy_version','request_id','invoice_id','custody_id','custody_version','reason','lines','action','line_number','unit_kind','quantity','from','to','csrf_token']);
        if(($input['policy_version']??null)!==HybridReturnRequest::POLICY)throw new InventoryException(['policy'=>'Online Hybrid custody policy required.']);
        $r=['request_id'=>HybridReturnRequest::uuid($input['request_id']??null),'invoice_id'=>HybridReturnRequest::uuid($input['invoice_id']??null),'custody_id'=>HybridReturnRequest::uuid($input['custody_id']??null),
            'custody_version'=>Representatives::id($input['custody_version']??null,'custody_version'),'reason'=>Inventory::text($input['reason']??'','reason',500,true)];
        if(!$manager){foreach(['action','line_number','unit_kind','quantity','from','to']as$key)if(array_key_exists($key,$input))throw new InventoryException(['receipt'=>'Unsupported supplemental receipt fields.']);$r['lines']=HybridReturnRequest::lines($input['lines']??[]);}
        else{
            if(array_key_exists('lines',$input))throw new InventoryException(['receipt'=>'Manager disposition does not receive additional goods.']);
            $r['action']=$input['action']??null;if(!in_array($r['action'],['REVIEW','REJECT','RECLASSIFY','RELEASE','DISPOSE'],true))throw new InventoryException(['action'=>'Choose an approved custody action.']);
            if(in_array($r['action'],['RECLASSIFY','RELEASE','DISPOSE'],true)){
                $r['line_number']=Representatives::id($input['line_number']??null,'line_number');$r['unit_kind']=$input['unit_kind']??null;$r['from']=$input['from']??null;
                if(!in_array($r['unit_kind'],['paid','free'],true)||!in_array($r['from'],['QUARANTINE','UNSALEABLE'],true))throw new InventoryException(['condition'=>'Select held paid/free goods and their condition.']);
                $r['quantity']=(string)Inventory::quantity($input['quantity']??null);$r['to']=$r['action']==='RELEASE'?'SALEABLE':($r['action']==='DISPOSE'?'DISPOSED':($input['to']??null));
                if($r['action']==='RECLASSIFY'&&(!in_array($r['to'],['QUARANTINE','UNSALEABLE'],true)||$r['from']===$r['to']))throw new InventoryException(['condition'=>'Choose a different non-saleable condition.']);
            }
        }return $r;
    }
    public function supplementCustody(array $identity,array $input):array
    {
        $r=$this->custodyRequest($input,false);
        return $this->tx(function()use($identity,$r){$ctx=$this->load($identity,$r['invoice_id']);$cmd=$this->command($ctx,$r['request_id'],'CUSTODY_SUPPLEMENT',$r);if(isset($cmd['replay']))return $cmd['replay']+['replayed'=>true];$this->requireGate($ctx,'custody');
            $p=$this->custody($r['custody_id']);if($p['invoice_id']!==$ctx['invoice']||($ctx['projection']['pending_custody_id']??null)!==$r['custody_id']||$p['status']==='ACCEPTED'||(string)$p['version']!==$r['custody_version'])throw new InventoryException(['custody'=>'Receipt changed, accepted, or is not the pending receipt. Refresh first.'],409);
            $this->intakeLimits($ctx,$r['lines'],$p);$this->claim($ctx,$r['request_id'],'CUSTODY_SUPPLEMENT',$cmd['hash']);$p=$this->appendCustody($ctx,$r['custody_id'],$r['request_id'],'SUPPLEMENT',['lines'=>$r['lines']],$r['reason']);$this->projection($ctx,$ctx['record']['adjustments']['state'],$r['custody_id']);
            return $this->complete($ctx,$r['request_id'],['custody_id'=>$r['custody_id'],'version'=>$p['version'],'status'=>$p['status'],'financial_effect'=>'NONE'],$r['reason']);});
    }
    public function manageCustody(array $identity,array $input):array
    {
        $r=$this->custodyRequest($input,true);
        return $this->tx(function()use($identity,$r){$ctx=$this->load($identity,$r['invoice_id']);if($ctx['actor']['role']!=='ADMIN')throw new InventoryException(['access'=>'Only an administrator may review or dispose of controlled custody.'],403);
            $cmd=$this->command($ctx,$r['request_id'],'CUSTODY_MANAGE',$r);if(isset($cmd['replay']))return $cmd['replay']+['replayed'=>true];$this->requireGate($ctx,'custody');
            $p=$this->custody($r['custody_id']);if($p['invoice_id']!==$ctx['invoice']||(string)$p['version']!==$r['custody_version'])throw new InventoryException(['custody'=>'Custody changed. Refresh its current disposition.'],409);
            $data=array_intersect_key($r,array_flip(['line_number','unit_kind','quantity','from','to']));$events=$this->rows('SELECT event_json FROM hybrid_custody_events WHERE custody_id=? ORDER BY version',[$r['custody_id']]);$history=array_map(static fn($e)=>json_decode($e['event_json'],true,512,JSON_THROW_ON_ERROR),$events);$history[]=['version'=>$p['version']+1,'type'=>$r['action'],'data'=>$data];
            try{HybridCustodyLedger::rebuild($r['custody_id'],$history);}catch(\LogicException $e){throw new InventoryException(['custody'=>$e->getMessage()],409);}
            if($r['action']==='RELEASE'&&($this->postingHold||($this->config['financial_posting_hold']??true)!==false))throw new InventoryException(['stock'=>'Controlled stock release is held.'],403);
            $this->claim($ctx,$r['request_id'],'CUSTODY_MANAGE',$cmd['hash']);$stock=null;
            if($r['action']==='RELEASE'){$products=array_column($ctx['record']['snapshot']['review']['calculation']['lines'],'product_id','line_number');$stock=$this->stock($ctx,$r['request_id'],[$products[$r['line_number']] => (int)$r['quantity']],$r['reason']);}
            $p=$this->appendCustody($ctx,$r['custody_id'],$r['request_id'],$r['action'],$data,$r['reason'],$stock);$this->projection($ctx,$ctx['record']['adjustments']['state'],$ctx['projection']['pending_custody_id']??null);
            return $this->complete($ctx,$r['request_id'],['custody_id'=>$r['custody_id'],'version'=>$p['version'],'status'=>$p['status'],'action'=>$r['action'],'stock_transaction_id'=>$stock],$r['reason']);});
    }
}
