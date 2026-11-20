<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;

/** Opaque return identity, bound to its creator and current customer scope. */
final class HistoricalReturnIntents
{
    public function __construct(private PDO $pdo){}
    private function actorCustomer(array $actor,string $customer):array
    {
        $a=(new Invoices($this->pdo))->actor($actor);Representatives::id($customer,'customer_id');
        $q=$this->pdo->prepare('SELECT id,assigned_rep_id,is_active FROM customers WHERE id=? FOR UPDATE');$q->execute([$customer]);$c=$q->fetch(PDO::FETCH_ASSOC);
        if(!$c||($a['role']==='SALES_REP'&&(string)$c['assigned_rep_id']!==(string)$a['id']))throw new InventoryException(['customer'=>'Customer is not available to you.'],403);
        if(!(int)$c['is_active'])throw new InventoryException(['customer'=>'Choose an active customer.']);return $a;
    }
    public function start(array $actor,string $customer):array
    {
        (new Invoices($this->pdo))->requireHistoricalReturnAccess($actor);
        $own=!$this->pdo->inTransaction();if($own)$this->pdo->beginTransaction();else $this->pdo->exec('SAVEPOINT return_intent');
        try{
            if(!$this->pdo->query('SELECT id FROM inventory_settings WHERE id=1 FOR UPDATE')->fetchColumn())throw new InventoryException(['stock'=>'Inventory settings are unavailable.'],409);
            $a=$this->actorCustomer($actor,$customer);(new Invoices($this->pdo))->requireHistoricalReturnAccess($a);$id=Inventory::uuid();
            $q=$this->pdo->prepare('INSERT INTO historical_return_intents(id,actor_user_id,customer_id,expires_at_utc) VALUES (?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 7 DAY))');$q->execute([$id,$a['id'],$customer]);
            if($own)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT return_intent');
            return ['return_intent_id'=>$id,'customer_id'=>$customer,'status'=>'IN_PROGRESS'];
        }catch(\Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$own&&$this->pdo->inTransaction())$this->pdo->exec('ROLLBACK TO SAVEPOINT return_intent');throw $e;}
    }
    public function recover(array $actor,string $id):array
    {
        (new Invoices($this->pdo))->requireHistoricalReturnAccess($actor);
        if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$id))throw new InventoryException(['return'=>'Return intent unavailable.'],404);
        $a=(new Invoices($this->pdo))->actor($actor);
        $q=$this->pdo->prepare('SELECT t.id,t.actor_user_id,t.customer_id,t.expires_at_utc,(t.expires_at_utc<=UTC_TIMESTAMP(6)) expired,c.assigned_rep_id,r.invoice_id,i.invoice_number FROM historical_return_intents t JOIN customers c ON c.id=t.customer_id LEFT JOIN historical_return_identities r ON r.return_intent_id=t.id LEFT JOIN invoices i ON i.id=r.invoice_id WHERE t.id=?');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row||(string)$row['actor_user_id']!==(string)$a['id']||($a['role']==='SALES_REP'&&(string)$row['assigned_rep_id']!==(string)$a['id']))throw new InventoryException(['return'=>'Return intent unavailable.'],404);
        return ['return_intent_id'=>$row['id'],'customer_id'=>(string)$row['customer_id'],'status'=>$row['invoice_id']?'FINALIZED':((int)$row['expired']?'EXPIRED':'IN_PROGRESS'),'invoice_id'=>$row['invoice_id'],'invoice_number'=>$row['invoice_number'],'expires_at_utc'=>$row['expires_at_utc']];
    }
    public function recent(array $actor):array
    {
        (new Invoices($this->pdo))->requireHistoricalReturnAccess($actor);
        $a=(new Invoices($this->pdo))->actor($actor);$params=[$a['id']];$scope='';
        if($a['role']==='SALES_REP'){$scope=' AND c.assigned_rep_id=?';$params[]=$a['id'];}
        $q=$this->pdo->prepare("SELECT t.id return_intent_id,c.customer_code,c.shop_name,t.created_at_utc,CASE WHEN r.invoice_id IS NOT NULL THEN 'FINALIZED' WHEN t.expires_at_utc<=UTC_TIMESTAMP(6) THEN 'EXPIRED' ELSE 'IN_PROGRESS' END status FROM historical_return_intents t JOIN customers c ON c.id=t.customer_id LEFT JOIN historical_return_identities r ON r.return_intent_id=t.id WHERE t.actor_user_id=?".$scope.' ORDER BY t.created_at_utc DESC,t.id DESC LIMIT 50');$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
