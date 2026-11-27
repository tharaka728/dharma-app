<?php
declare(strict_types=1);
namespace App\Services\HistoricalHybridV1;
// Read-only historical evaluator. Pinned ed147d41894b109fae277e8e69b40e8a3dfecb57; source blob eb5dfd2b620cc750efe06589426f7b3785a657c1.
use App\Support\InventoryException;
final class InvoiceDiscount
{
    public const POLICY='INVOICE_PERCENT_V1';
    public static function rate(mixed $value):string
    {
        if(!is_string($value)||!preg_match('/^(0|[1-9][0-9]{0,2})(?:\.[0-9]{1,2})?$/D',$value))throw new InventoryException(['invoice_discount_percent'=>'Enter a discount from 0 to 100 with up to two decimal places.']);
        $rate=Products::money($value);if(PromotionCalculator::compareMoney($rate,'100.00')>0)throw new InventoryException(['invoice_discount_percent'=>'Discount cannot exceed 100%.']);return $rate;
    }
    /** Exact integer long division; also used by BenefitPolicy (PROMO_CHOICE_V1) allocation. */
    public static function divide(string $numerator,string $denominator):array
    {
        if($denominator==='0')throw new \LogicException('Zero discount allocation divisor.');
        $quotient='';$remainder='0';
        foreach(str_split($numerator) as $digit){$remainder=ltrim($remainder.$digit,'0')?:'0';$n=0;while(PromotionCalculator::compare($remainder,$denominator)>=0){$remainder=PromotionCalculator::subtract($remainder,$denominator);$n++;}$quotient.=(string)$n;}
        return [ltrim($quotient,'0')?:'0',$remainder];
    }
    public static function apply(array $lines,string $rate):array
    {
        $rate=self::rate($rate);$subtotal='0.00';foreach($lines as $line)$subtotal=PromotionCalculator::sumMoney($subtotal,$line['payable_amount']);
        $base=PromotionCalculator::cents($subtotal);
        [$discount]=self::divide(PromotionCalculator::add(PromotionCalculator::multiply($base,PromotionCalculator::cents($rate)),'5000'),'10000');
        $allocated='0';$shares=[];$remainders=[];
        foreach($lines as $index=>$line){[$share,$remainder]=$base==='0'?['0','0']:self::divide(PromotionCalculator::multiply($discount,PromotionCalculator::cents($line['payable_amount'])),$base);$shares[$index]=$share;$remainders[]=['index'=>$index,'remainder'=>$remainder];$allocated=PromotionCalculator::add($allocated,$share);}
        usort($remainders,static fn($a,$b)=>-PromotionCalculator::compare($a['remainder'],$b['remainder'])?:($a['index']<=>$b['index']));
        $left=(int)PromotionCalculator::subtract($discount,$allocated);for($i=0;$i<$left;$i++){$index=$remainders[$i]['index'];$shares[$index]=PromotionCalculator::add($shares[$index],'1');}
        foreach($lines as $index=>&$line){$line['automatic_discount_amount']=$line['discount_amount'];$line['invoice_discount_amount']=PromotionCalculator::money($shares[$index]);$line['discount_amount']=PromotionCalculator::sumMoney($line['discount_amount'],$line['invoice_discount_amount']);$line['payable_amount']=PromotionCalculator::subtractMoney($line['payable_amount'],$line['invoice_discount_amount']);}unset($line);
        return ['lines'=>$lines,'invoice_discount_percent'=>$rate,'invoice_discount_amount'=>PromotionCalculator::money($discount),'discountable_subtotal'=>$subtotal,'discount_policy'=>self::POLICY];
    }
}
