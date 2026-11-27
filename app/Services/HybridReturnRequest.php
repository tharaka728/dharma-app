<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\InventoryException;

final class HybridReturnRequest
{
    public const POLICY='HYBRID_RETURN_V1';
    public const CONDITIONS=['SALEABLE','QUARANTINE','UNSALEABLE'];
    public static function uuid(mixed $value):string { return OfflineSales::uuid($value); }
    public static function lines(mixed $value):array
    {
        if(!is_array($value)||$value!==array_values($value)||count($value)>100)throw new InventoryException(['lines'=>'Supply an ordered list of at most 100 original invoice lines.']);
        $out=[];$seen=[];
        foreach($value as $raw){
            $line=HybridInvoiceRequest::object($raw);HybridInvoiceRequest::keys($line,['line_number','paid_quantity','free_quantity','paid_conditions','free_conditions','condition_reason','holding_reference']);
            $number=Representatives::id($line['line_number']??null,'line_number');if(isset($seen[$number]))throw new InventoryException(['lines'=>'Each original line may appear only once.']);$seen[$number]=true;
            $paid=Inventory::quantity($line['paid_quantity']??'0',true);$free=Inventory::quantity($line['free_quantity']??'0',true);
            if($paid===0&&$free===0)throw new InventoryException(['lines'=>'Record a positive received paid or free quantity.']);
            $r=['line_number'=>$number,'paid_quantity'=>(string)$paid,'free_quantity'=>(string)$free];
            foreach(['paid'=>$paid,'free'=>$free] as $kind=>$quantity){
                $counts=HybridInvoiceRequest::object($line[$kind.'_conditions']??null);HybridInvoiceRequest::keys($counts,self::CONDITIONS);$sum=0;$normalized=[];
                foreach(self::CONDITIONS as $condition){$n=Inventory::quantity($counts[$condition]??'0',true);if($n>PHP_INT_MAX-$sum)throw new InventoryException(['condition'=>'Received quantities exceed the supported range.']);$sum+=$n;$normalized[$condition]=(string)$n;}
                if($sum!==$quantity)throw new InventoryException(['condition'=>'Saleable, Quarantine and Unsaleable counts must sum to the physically received '.$kind.' quantity.']);$r[$kind.'_conditions']=$normalized;
            }
            $r['condition_reason']=Inventory::text($line['condition_reason']??'','condition_reason',500,true);
            $r['holding_reference']=Inventory::text($line['holding_reference']??'','holding_reference',200,true);$out[]=$r;
        }
        usort($out,static fn($a,$b)=>PromotionCalculator::compare($a['line_number'],$b['line_number']));return $out;
    }
    public static function normalize(array $input):array
    {
        HybridInvoiceRequest::keys($input,['policy_version','request_id','invoice_id','operation','reason','lines','custody_id','custody_version','review_hash','csrf_token']);
        if(($input['policy_version']??null)!==self::POLICY)throw new InventoryException(['policy'=>'Use the online Hybrid return policy.'],409);
        $operation=$input['operation']??'RETURN';if(!in_array($operation,['RETURN','VOID'],true))throw new InventoryException(['operation'=>'Unsupported Hybrid return operation.']);
        $r=['policy_version'=>self::POLICY,'request_id'=>self::uuid($input['request_id']??null),'invoice_id'=>self::uuid($input['invoice_id']??null),'operation'=>$operation,
            'reason'=>Inventory::text($input['reason']??'','reason',500,true),'lines'=>self::lines($input['lines']??[])];
        if(($input['custody_id']??'')!==''){
            $r['custody_id']=self::uuid($input['custody_id']);$r['custody_version']=Representatives::id($input['custody_version']??null,'custody_version');
            if($r['lines'])throw new InventoryException(['custody'=>'Existing custody quantities come from recorded receipts, not browser replacements.']);
        }
        if(isset($input['review_hash'])){if(!is_string($input['review_hash'])||!preg_match('/^[a-f0-9]{64}$/D',$input['review_hash']))throw new InventoryException(['review'=>'Obtain a current return preview.']);$r['review_hash']=$input['review_hash'];}
        return $r;
    }
    public static function derived(string $id,string $purpose):string
    {
        $h=hash('sha256',$purpose.':'.$id);return substr($h,0,8).'-'.substr($h,8,4).'-5'.substr($h,13,3).'-8'.substr($h,17,3).'-'.substr($h,20,12);
    }
}
