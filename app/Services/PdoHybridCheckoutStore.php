<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Support\InventoryException;
use App\Services\PromotionCalculator as M;

/** Real official writer, restricted to physically proved disposable CLI databases in Stage 2C. */
final class PdoHybridCheckoutStore extends PdoHybridInvoiceContext implements HybridCheckoutStore
{
    public function __construct(PDO $pdo,private ?array $scope=null,private bool $isolatedPostingHold=true,private ?\Closure $fault=null) { parent::__construct($pdo,$scope); }
    private function step(string $name): void { if ($this->fault) { ($this->fault)($name); } }
    private function requireWrite(): void
    {
        HybridDatabaseScope::verify($this->pdo,$this->scope);
        if (!$this->pdo->inTransaction() || $this->isolatedPostingHold) { throw new InventoryException(['hybrid'=>'A permitted owning isolated transaction is required.'],409); }
        $control=$this->controls();
        if (!$control || (int)$control['enabled']!==1 || (int)$control['sale_posting']!==1) { throw new InventoryException(['hybrid'=>'Hybrid sale posting is disabled.'],409); }
    }
    public function transaction(callable $work,bool $readOnly): array
    {
        HybridDatabaseScope::verify($this->pdo,$this->scope); $own=!$this->pdo->inTransaction();
        for ($attempt=0;;$attempt++) {
            try { return parent::transaction($work,$readOnly); }
            catch (\PDOException $e) {
                $code=(int)($e->errorInfo[1]??0);
                if ($own && in_array($code,[1205,1213],true) && $attempt<2) { continue; }
                if (in_array($code,[1205,1213,1062],true)) { throw new InventoryException(['conflict'=>'Concurrent Hybrid command conflict. Retry the same request identity.'],409); }
                throw $e;
            }
        }
    }
    public function financialPostingHeld(): bool { return $this->isolatedPostingHold; }
    public function command(string $uuid): ?array
    {
        if (!$this->schemaReady()) { throw new InventoryException(['hybrid'=>'Hybrid snapshot schema is not ready.'],409); }
        return $this->one('SELECT request_id,operation,CAST(actor_user_id AS CHAR) actor,customer_id,payload_hash FROM hybrid_commands WHERE request_id=? FOR UPDATE',[$uuid]);
    }
    public function replayResult(array $actor,array $command): array
    {
        $result=$this->one('SELECT invoice_id,result_json FROM hybrid_command_results WHERE request_id=?',[$command['request_id']]);
        if (!$result) { throw new InventoryException(['hybrid'=>'Incomplete Hybrid command requires review.'],409); }
        (new HybridInvoiceReader($this->pdo))->get($actor,$result['invoice_id']);
        return json_decode($result['result_json'],true,32,JSON_THROW_ON_ERROR);
    }
    public function claim(array $request,string $actor,string $payloadHash): void
    {
        $this->requireWrite();
        $id=$request['request_id'];
        foreach (['invoices'=>'id','sync_operations'=>'id','payments'=>'id','invoice_receipts'=>'request_id','inventory_transactions'=>'id'] as $table=>$key) {
            if ($this->one('SELECT '.$key.' FROM '.$table.' WHERE '.$key.'=? FOR UPDATE',[$id])) { throw new InventoryException(['request_id'=>'Identity already belongs to an existing financial or offline operation.'],409); }
        }
        $this->pdo->prepare("INSERT INTO hybrid_commands(request_id,operation,actor_user_id,customer_id,payload_hash) VALUES (?,'HYBRID_SALE_V1',?,?,?)")->execute([$id,$actor,$request['customer_id'],$payloadHash]); $this->step('claim');
    }
    public function postOfficial(array $request,array $prepared,array $mapping): array
    {
        $this->requireWrite();
        $r=$prepared['review']; $id=$request['request_id']; $a=$r['actor']; $owner=$r['number_owner'];
        $hash=HybridInvoiceRequest::digest(['operation'=>'HYBRID_SALE_V1','request'=>$request]);
        $this->pdo->prepare('INSERT INTO invoice_sequence_counters(rep_id,next_sequence) VALUES (?,1) ON DUPLICATE KEY UPDATE rep_id=rep_id')->execute([$owner]);
        $counter=$this->one('SELECT next_sequence FROM invoice_sequence_counters WHERE rep_id=? FOR UPDATE',[$owner]); $seq=(string)$counter['next_sequence'];
        if (M::compare($seq,(string)(PHP_INT_MAX-1))>0) { throw new InventoryException(['number'=>'Lifetime sequence exhausted.'],409); }
        $settings=$this->one('SELECT business_prefix FROM invoice_settings WHERE id=1 FOR UPDATE');
        if (!$settings) { throw new InventoryException(['number'=>'Official invoice settings unavailable.'],409); }
        $number=$settings['business_prefix'].'-'.$owner.'-'.str_pad($seq,8,'0',STR_PAD_LEFT);
        $this->pdo->prepare('UPDATE invoice_sequence_counters SET next_sequence=next_sequence+1 WHERE rep_id=?')->execute([$owner]); $this->step('number');
        $customer=array_intersect_key($r['customer'],array_flip(['customer_code','shop_name','contact_person','phone','address','city_town','district']));
        $this->pdo->prepare("INSERT INTO invoices(id,invoice_number,rep_id,customer_id,sequence_number,business_date,created_at_client_utc,received_at_utc,accepted_at_utc,subtotal,automatic_discount_total,manual_discount_total,discount_total,grand_total,payment_type,customer_snapshot,rep_name_snapshot,client_payload_hash,posting_channel) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),?,?,?,?,?,?,?,?,?,'ONLINE')")
            ->execute([$id,$number,$owner,$request['customer_id'],$seq,$r['business_date'],$mapping['subtotal'],'0.00',$mapping['manual_discount_total'],$mapping['discount_total'],$mapping['grand_total'],$request['payment_type'],HybridInvoiceRequest::json($customer),$r['rep_name'],$hash]); $this->step('invoice');
        $this->pdo->prepare('INSERT INTO online_invoice_details(invoice_id,source_location_id,issued_by_user_id,due_date,credit_terms_days,credit_limit_snapshot,review_hash) VALUES (?,?,?,?,?,?,?)')
            ->execute([$id,$r['source_location_id'],$a['id'],$r['due_date'],$r['credit_terms_days'],(int)$r['customer']['credit_limit_enabled']?$r['customer']['credit_limit']:null,$r['review_hash']]);
        $this->pdo->prepare('INSERT INTO invoice_company_snapshots(invoice_id,details) VALUES (?,?)')->execute([$id,HybridInvoiceRequest::json((new CompanyLanguages($this->pdo))->snapshot())]);
        $this->pdo->prepare('INSERT INTO invoice_language_snapshots(invoice_id,default_locale) VALUES (?,?)')->execute([$id,'si-LK']);
        $this->pdo->prepare("INSERT INTO inventory_transactions(id,type,invoice_id,reason,created_by_user_id,payload_hash) VALUES (?,'SALE',?,'Hybrid invoice issued',?,?)")->execute([$id,$id,$a['id'],$hash]);
        $display=array_column($r['products'],null,'product_id');
        foreach ($r['calculation']['lines'] as $line) {
            $p=$this->one('SELECT cost_price,name_si FROM products WHERE id=?',[$line['product_id']]); $d=$display[$line['product_id']];
            $this->pdo->prepare('INSERT INTO invoice_items(invoice_id,line_number,product_id,product_name_snapshot,sku_snapshot,quantity,free_quantity,unit_price,gross_amount,automatic_discount_amount,manual_discount_amount,discount_amount,line_total,price_version,promotion_version_id,cost_price_snapshot,promotion_snapshot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$id,$line['line_number'],$line['product_id'],$d['name'],$d['sku'],$line['paid_quantity'],$line['actual_free_quantity'],$line['unit_price'],$line['gross_amount'],'0.00',$line['discount_amount'],$line['discount_amount'],$line['payable_amount'],$line['price_version'],$line['promotion_revision_id'],$p['cost_price'],HybridInvoiceRequest::json($line['promotion_snapshot'])]);
            $this->pdo->prepare('INSERT INTO invoice_product_language_snapshots(invoice_id,product_id,name_si) VALUES (?,?,?)')->execute([$id,$line['product_id'],$p['name_si']]);
        }
        $this->step('items');
        foreach ($r['calculation']['lines'] as $line) {
            $table=$r['stock_source']==='warehouse'?'product_stock_balances':'representative_stock_balances';
            $where=$r['stock_source']==='warehouse'?'product_id=?':'rep_id=? AND product_id=?';
            $params=$r['stock_source']==='warehouse'?[$line['product_id']]:[$r['stock_source'],$line['product_id']];
            $balance=$this->one('SELECT quantity FROM '.$table.' WHERE '.$where.' FOR UPDATE',$params);
            if (!$balance || M::compare((string)$balance['quantity'],$line['required_stock'])<0) { throw new InventoryException(['stock'=>'Locked paid-plus-free stock unavailable.'],409); }
            $after=(int)$balance['quantity']-(int)$line['required_stock'];
            $this->pdo->prepare('UPDATE '.$table.' SET quantity=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE '.$where)->execute(array_merge([$after],$params));
            $this->pdo->prepare('INSERT INTO inventory_movements(transaction_id,line_number,location_id,product_id,quantity_delta,quantity_after) VALUES (?,?,?,?,?,?)')->execute([$id,$line['line_number'],$r['source_location_id'],$line['product_id'],-(int)$line['required_stock'],$after]);
        }
        $this->step('stock'); $payment=null;
        if ($r['applied_amount']!=='0.00') {
            $payment=$id;
            $this->pdo->prepare("INSERT INTO payments(id,customer_id,received_by_user_id,method,amount,received_at_client_utc,accepted_at_utc,status) VALUES (?,?,?,'CASH',?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),'POSTED')")->execute([$id,$request['customer_id'],$a['id'],$r['applied_amount']]);
            $this->pdo->prepare('INSERT INTO payment_allocations(payment_id,invoice_id,customer_id,amount) VALUES (?,?,?,?)')->execute([$id,$id,$request['customer_id'],$r['applied_amount']]);
        }
        $this->pdo->prepare('INSERT INTO invoice_receipts(request_id,invoice_id,actor_user_id,payload_hash,received_amount,applied_amount,change_amount,payment_id) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$id,$id,$a['id'],hash('sha256','INITIAL:'.$hash),$r['amount_received'],$r['applied_amount'],$r['change_amount'],$payment]); $this->step('payment');
        return ['invoice_id'=>$id,'invoice_number'=>$number];
    }
    public function persistSnapshot(string $invoice,array $snapshot): void
    {
        $this->requireWrite();
        $names=['hybrid_invoice_snapshots'=>'snapshot_parent','hybrid_invoice_line_snapshots'=>'snapshot_lines','hybrid_invoice_segments'=>'snapshot_segments','hybrid_unit_allocation_runs'=>'snapshot_runs','hybrid_invoice_seals'=>'seal'];
        (new PdoHybridInvoiceSnapshots($this->pdo,function (string $table) use ($names) { if (isset($names[$table])) { $this->step($names[$table]); } },$this->scope))->insertSealed($invoice,$snapshot);
    }
    public function complete(string $uuid,array $result): void
    {
        $this->requireWrite();
        $row=$this->one('SELECT actor_user_id,customer_id FROM hybrid_commands WHERE request_id=?',[$uuid]);
        $i=$this->one('SELECT i.rep_id,d.source_location_id,l.type FROM invoices i JOIN online_invoice_details d ON d.invoice_id=i.id JOIN stock_locations l ON l.id=d.source_location_id WHERE i.id=?',[$result['invoice_id']]);
        $source=$i['type']==='WAREHOUSE'?'warehouse':(string)$i['rep_id'];
        if ($source!=='warehouse') { $this->pdo->prepare('UPDATE rep_operating_state SET inventory_revision=inventory_revision+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE rep_id=?')->execute([$source]); }
        foreach ($source==='warehouse'?['ADMIN']:['ADMIN','REP:'.$source] as $scope) {
            $this->pdo->prepare('INSERT INTO sync_stream_state(scope_key,last_change_id) VALUES (?,0) ON DUPLICATE KEY UPDATE scope_key=scope_key')->execute([$scope]);
            $this->one('SELECT last_change_id FROM sync_stream_state WHERE scope_key=? FOR UPDATE',[$scope]);
            $this->pdo->prepare('UPDATE sync_stream_state SET last_change_id=last_change_id+1 WHERE scope_key=?')->execute([$scope]);
            $this->pdo->prepare("INSERT INTO change_log(scope_key,change_id,entity_type,entity_key,entity_version,action,changed_at_utc) SELECT scope_key,last_change_id,'invoice',?,last_change_id,'UPSERT',UTC_TIMESTAMP(6) FROM sync_stream_state WHERE scope_key=?")->execute([$uuid,$scope]);
        }
        $this->step('streams');
        $this->pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,after_json) VALUES (?,'HYBRID_INVOICE_ISSUED','invoices',?,?)")->execute([$row['actor_user_id'],$uuid,HybridInvoiceRequest::json($result)]); $this->step('audit');
        $this->pdo->prepare('INSERT INTO hybrid_command_results(request_id,invoice_id,result_json) VALUES (?,?,?)')->execute([$uuid,$result['invoice_id'],HybridInvoiceRequest::json($result)]); $this->step('result');
    }
}
