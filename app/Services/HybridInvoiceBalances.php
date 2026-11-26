<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Services\PromotionCalculator as M;

/** Append-only credits supplement original totals. No hypothetical physical refunds. */
final class HybridInvoiceBalances
{
    /** Scalar aggregates avoid multiplying original invoice amounts by child rows. */
    public static function sql(PDO $pdo,string $alias='i'):array
    {
        if(!preg_match('/^[a-z]+$/D',$alias))throw new \LogicException('Invalid invoice alias.');
        $ready=self::schema($pdo);
        return ['credit'=>$ready?'COALESCE((SELECT SUM(hr.net_credit) FROM hybrid_returns hr WHERE hr.invoice_id='.$alias.'.id),0.00)':'0.00',
            'refund'=>$ready?'COALESCE((SELECT SUM(hr.refund_increment) FROM hybrid_returns hr WHERE hr.invoice_id='.$alias.'.id),0.00)':'0.00',
            'terminal'=>$ready?'EXISTS(SELECT 1 FROM hybrid_voids hv WHERE hv.invoice_id='.$alias.'.id)':'0',
            'voided_at'=>$ready?'(SELECT hr.created_at_utc FROM hybrid_voids hv JOIN hybrid_returns hr ON hr.id=hv.return_id WHERE hv.invoice_id='.$alias.'.id)':'NULL'];
    }
    public function __construct(private PDO $pdo){}
    public static function schema(PDO $pdo):bool
    {
        $q=$pdo->prepare('SELECT checksum,status FROM schema_migrations WHERE version=?');$q->execute(['026_hybrid_linked_returns.sql']);$r=$q->fetch(PDO::FETCH_ASSOC);
        return $r&&$r['status']==='applied'&&hash_equals($r['checksum'],hash_file('sha256',dirname(__DIR__,2).'/database/migrations/026_hybrid_linked_returns.sql'));
    }
    private function rows(string $sql,array $params=[]):array{$q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);}
    private static function equal(array $actual,array $expected):void
    {
        if(count($actual)!==count($expected))throw new \LogicException('Hybrid adjustment physical child count mismatch.');
        foreach($expected as $i=>$r)foreach($r as $key=>$value){
            if($key==='receipt_json'){$a=HybridInvoiceRequest::json(json_decode($actual[$i][$key],true,512,JSON_THROW_ON_ERROR));if($a!==$value)throw new \LogicException('Hybrid receipt projection mismatch.');}
            elseif(!array_key_exists($key,$actual[$i])||(string)$actual[$i][$key] !== (string)$value)throw new \LogicException('Hybrid adjustment projection mismatch: '.$key);
        }
    }
    public function history(string $invoice):array
    {
        if(!self::schema($this->pdo))return [];$history=[];
        foreach($this->rows('SELECT * FROM hybrid_returns WHERE invoice_id=? ORDER BY revision',[$invoice])as$row){
            $p=json_decode($row['plan_json'],true,512,JSON_THROW_ON_ERROR);
            self::equal([$row],[['revision'=>$p['revision'],'operation'=>$p['operation'],'policy_version'=>HybridReturnRequest::POLICY,'cash_at_return'=>$p['cash_at_return'],
                'debt_reduction'=>$p['debt_reduction'],'refund_increment'=>$p['refund_increment'],'retained_gross'=>$p['retained_gross'],'retained_discount'=>$p['retained_discount']]+$p['totals']]);
            $lines=[];$allocations=[];
            foreach($p['lines']as$line){
                $receipt=$line['receipt'];$lines[]=['line_number'=>$line['line_number'],'paid_quantity'=>$receipt['paid_quantity'],'free_quantity'=>$receipt['free_quantity'],'free_position_start'=>$line['free_position_start'],'receipt_json'=>HybridInvoiceRequest::json($receipt)];
                foreach($line['ranges']as$range){[, $segment]=explode(':',$range['segment']);$r=['line_number'=>$line['line_number'],'segment_number'=>$segment,'position_start'=>$range['position_start'],'position_count'=>$range['position_count']];
                    foreach(['gross_amount'=>'gross','product_discount_amount'=>'product_discount_amount','replacement_discount_amount'=>'replacement_discount_amount','invoice_discount_amount'=>'invoice_discount_amount','payable_amount'=>'net']as$key=>$component)$r[$key]=M::money($range['cents'][$component]);$allocations[]=$r;
                }
            }
            usort($allocations,static fn($a,$b)=>(int)$a['line_number']<=>(int)$b['line_number']?:(int)$a['segment_number']<=>(int)$b['segment_number']?:$a['position_start']<=>$b['position_start']);
            self::equal($this->rows('SELECT * FROM hybrid_return_lines WHERE return_id=? ORDER BY line_number',[$row['id']]),$lines);
            self::equal($this->rows('SELECT * FROM hybrid_return_allocations WHERE return_id=? ORDER BY line_number,segment_number,position_start',[$row['id']]),$allocations);
            $history[]=$p;
        }
        return $history;
    }
    public function read(string $invoice,array $snapshot):array
    {
        $cash=$this->rows("SELECT COALESCE(SUM(a.amount),0.00) paid,COALESCE(SUM(CASE WHEN p.method='CASH' THEN a.amount ELSE 0 END),0.00) cash,COALESCE(SUM(CASE WHEN p.method<>'CASH' THEN a.amount ELSE 0 END),0.00) noncash FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE a.invoice_id=? AND p.status='POSTED'",[$invoice])[0];
        $historical=$this->rows('SELECT applied_value FROM historical_customer_returns WHERE invoice_id=?',[$invoice]);
        $history=$this->history($invoice);$state=HybridReturnPolicy::state($snapshot,$history);$net=$snapshot['review']['calculation']['final_payable_total'];
        if(M::compareMoney($cash['paid'],$net)>0||M::compareMoney($state['cash_at_return'],$cash['cash'])>0)throw new \LogicException('Hybrid collected money does not reconcile.');
        $noncash=$cash['noncash']!=='0.00'||(bool)$historical;
        if($noncash&&$history)throw new \LogicException('A linked Hybrid credit cannot coexist with unsupported noncash settlement.');
        $legacy=$this->rows('SELECT refund_due FROM invoice_voids WHERE invoice_id=?',[$invoice])[0]??null;
        if($legacy&&$history)throw new \LogicException('Legacy full void cannot coexist with linked Hybrid returns.');
        $terminal=$legacy!==null||$state['terminal'];
        $used=M::add(M::cents($cash['paid']),M::cents($state['credit']));if($historical)$used=M::add($used,M::cents($historical[0]['applied_value']));
        $debt=$terminal||M::compare($used,M::cents($net))>=0?'0.00':M::money(M::subtract(M::cents($net),$used));
        $refund=$legacy?$legacy['refund_due']:$state['refund_due'];
        if(!$legacy&&M::compareMoney($refund,$cash['cash'])>0)throw new \LogicException('Hybrid Refund Due exceeds actual cash capacity.');
        if(self::schema($this->pdo)){
            $projection=$this->rows('SELECT * FROM hybrid_return_state WHERE invoice_id=?',[$invoice])[0]??null;
            if($history&&!$projection)throw new \LogicException('Missing Hybrid return projection.');
            if($projection)self::equal([$projection],[['revision'=>$state['revision'],'credit'=>$state['credit'],'debt_reduction'=>$state['debt_reduction'],'refund_due'=>$state['refund_due']]]);
            $void=$this->rows('SELECT return_id FROM hybrid_voids WHERE invoice_id=?',[$invoice]);if((bool)$void!==$state['terminal'])throw new \LogicException('Hybrid terminal void projection mismatch.');
            $expected=[];foreach($state['lines']as$number=>$line)foreach($line['cursors']as$key=>$count){[, $segment]=explode(':',$key);$expected[]=['line_number'=>$number,'segment_number'=>$segment,'consumed_positions'=>$count];}
            usort($expected,static fn($a,$b)=>(int)$a['line_number']<=>(int)$b['line_number']?:(int)$a['segment_number']<=>(int)$b['segment_number']);
            self::equal($this->rows('SELECT * FROM hybrid_segment_cursors WHERE invoice_id=? ORDER BY line_number,segment_number',[$invoice]),$expected);
        }
        return ['history'=>$history,'state'=>$state,'financial'=>['original_payable'=>$net,'paid_amount'=>$cash['paid'],'cash_collected'=>$cash['cash'],'noncash_settlement'=>$noncash,
            'legacy_void'=>$legacy!==null,'outstanding'=>$debt,'refund_due'=>$refund,'linked_return_credit'=>$state['credit'],'debt_reduction'=>$state['debt_reduction'],
            'validity'=>$terminal?'VOIDED':'ISSUED','linked_return_status'=>self::schema($this->pdo)?'AVAILABLE':'NOT_IMPLEMENTED','actual_cash_refunds'=>'UNAVAILABLE']];
    }
}
