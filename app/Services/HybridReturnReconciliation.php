<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
/** Read-only checks; caller's sealed-invoice reader enforces current scope first. */
final class HybridReturnReconciliation
{
    public function __construct(private PDO $pdo){}
    private function rows(string $sql,array $params=[]):array{$q=$this->pdo->prepare($sql);$q->execute($params);return$q->fetchAll(PDO::FETCH_ASSOC);}
    private function stock(?string $id,string $location,array $expected):void
    {
        $expected=array_filter($expected,static fn($n)=>$n>0);if(!$expected){if($id!==null)throw new \LogicException('Unexpected custody stock transaction.');return;}
        if(!$id)throw new \LogicException('Missing received-saleable stock transaction.');
        $rows=$this->rows('SELECT t.type,t.invoice_id,m.location_id,m.product_id,m.quantity_delta FROM inventory_transactions t JOIN inventory_movements m ON m.transaction_id=t.id WHERE t.id=? ORDER BY m.line_number',[$id]);
        if(count($rows)!==count($expected))throw new \LogicException('Received-saleable stock child count mismatch.');
        foreach($rows as$r){$product=(string)$r['product_id'];if($r['type']!=='ADJUSTMENT'||$r['invoice_id']!==null||(string)$r['location_id']!==$location||!isset($expected[$product])||(int)$r['quantity_delta']!==$expected[$product])throw new \LogicException('Received-saleable stock movement mismatch.');unset($expected[$product]);}
    }
    public function verify(array $actor,string $invoice):array
    {
        $record=(new HybridInvoiceReader($this->pdo))->get($actor,$invoice);if(!HybridInvoiceBalances::schema($this->pdo))return ['available'=>false];
        $products=array_column($record['snapshot']['review']['calculation']['lines'],'product_id','line_number');$location=$record['snapshot']['review']['source_location_id'];$count=0;
        foreach($this->rows('SELECT * FROM hybrid_custody_receipts WHERE invoice_id=? ORDER BY id',[$invoice])as$receipt){
            if((string)$receipt['source_location_id']!==$location)throw new \LogicException('Custody source differs from sealed source.');$events=[];
            foreach($this->rows('SELECT * FROM hybrid_custody_events WHERE custody_id=? ORDER BY version',[$receipt['id']])as$row){
                $event=json_decode($row['event_json'],true,512,JSON_THROW_ON_ERROR);if((int)$row['version']!==$event['version']||$row['event_type']!==$event['type'])throw new \LogicException('Custody typed event mismatch.');
                $expected=[];
                if(in_array($event['type'],['RECEIVED','SUPPLEMENT'],true)){
                    $typed=$this->rows('SELECT * FROM hybrid_custody_lines WHERE event_id=? ORDER BY line_number',[$row['id']]);if(count($typed)!==count($event['data']['lines']))throw new \LogicException('Custody intake child count mismatch.');
                    foreach($event['data']['lines']as$i=>$line){foreach(['line_number','holding_reference','condition_reason']as$key)if((string)$typed[$i][$key]!==$line[$key])throw new \LogicException('Custody intake evidence mismatch.');if((string)$typed[$i]['product_id']!==$products[$line['line_number']])throw new \LogicException('Custody intake product mismatch.');foreach(['paid','free']as$kind)foreach(HybridReturnRequest::CONDITIONS as$condition)if((string)$typed[$i][$kind.'_'.strtolower($condition)]!==$line[$kind.'_conditions'][$condition])throw new \LogicException('Custody condition quantity mismatch.');}
                }elseif($event['type']==='ACCEPTED'){
                    $return=$this->rows('SELECT * FROM hybrid_returns WHERE id=? AND invoice_id=? AND custody_id=?',[$event['data']['return_id'],$invoice,$receipt['id']])[0]??null;if(!$return)throw new \LogicException('Custody acceptance has no linked financial event.');$plan=json_decode($return['plan_json'],true,512,JSON_THROW_ON_ERROR);
                    $prior=HybridCustodyLedger::rebuild($receipt['id'],$events);$physical=HybridCustodyLedger::receiptLines($prior);
                    foreach($plan['lines']as$i=>$line){foreach(['line_number','paid_quantity','free_quantity','paid_conditions','free_conditions']as$key)if(HybridInvoiceRequest::json($line['receipt'][$key])!==HybridInvoiceRequest::json($physical[$i][$key]))throw new \LogicException('Financial acceptance differs from physical intake.');$n=(int)$line['receipt']['paid_conditions']['SALEABLE']+(int)$line['receipt']['free_conditions']['SALEABLE'];$expected[$line['product_id']]=($expected[$line['product_id']]??0)+$n;}
                    if(count($physical)!==count($plan['lines'])||$row['stock_transaction_id']!==$return['stock_transaction_id'])throw new \LogicException('Custody acceptance linkage mismatch.');
                }elseif($event['type']==='RELEASE'){$expected[$products[$event['data']['line_number']]]=(int)$event['data']['quantity'];}
                $this->stock($row['stock_transaction_id'],$location,$expected);$events[]=$event;
                if(!$this->rows('SELECT request_id FROM hybrid_return_results WHERE request_id=?',[$row['command_id']]))throw new \LogicException('Custody event has no completed command.');
            }
            $rebuilt=HybridCustodyLedger::rebuild($receipt['id'],$events);$projection=$this->rows('SELECT projection_json FROM hybrid_custody_state WHERE custody_id=?',[$receipt['id']])[0]??null;if(!$projection||HybridInvoiceRequest::json(json_decode($projection['projection_json'],true,512,JSON_THROW_ON_ERROR))!==HybridInvoiceRequest::json($rebuilt))throw new \LogicException('Custody projection mismatch.');$count++;
        }
        $orphans=$this->rows('SELECT r.id FROM hybrid_returns r LEFT JOIN hybrid_custody_state c ON c.custody_id=r.custody_id AND c.accepted_return_id=r.id LEFT JOIN hybrid_return_results x ON x.request_id=r.id WHERE r.invoice_id=? AND (x.request_id IS NULL OR (r.custody_id IS NOT NULL AND c.custody_id IS NULL))',[$invoice]);if($orphans)throw new \LogicException('Financial return acceptance is incomplete.');
        return ['available'=>true,'financial'=>true,'original_positions'=>true,'custody_receipts'=>$count,'saleable_movements'=>true];
    }
}
