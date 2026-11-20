<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\InventoryException;
/** Exact unsigned integer-string arithmetic in cents; no floats or optional PHP extensions. */
final class PromotionCalculator
{
    private static function norm(string $v):string{return ltrim($v,'0')?:'0';}
    public static function compare(string $a,string $b):int{$a=self::norm($a);$b=self::norm($b);return strlen($a)<=>strlen($b)?:strcmp($a,$b);}
    public static function add(string $a,string $b):string
    {
        $out='';$carry=0;for($i=strlen($a)-1,$j=strlen($b)-1;$i>=0||$j>=0||$carry;$i--,$j--){$n=($i>=0?(int)$a[$i]:0)+($j>=0?(int)$b[$j]:0)+$carry;$out=($n%10).$out;$carry=intdiv($n,10);}return self::norm($out);
    }
    public static function multiply(string $a,string $b):string
    {
        $out=array_fill(0,strlen($a)+strlen($b),0);for($i=strlen($a)-1;$i>=0;$i--){for($j=strlen($b)-1;$j>=0;$j--){$n=$out[$i+$j+1]+(int)$a[$i]*(int)$b[$j];$out[$i+$j+1]=$n%10;$out[$i+$j]+=intdiv($n,10);}}return self::norm(implode('',$out));
    }
    public static function subtract(string $a,string $b):string
    {
        $out='';$borrow=0;for($i=strlen($a)-1,$j=strlen($b)-1;$i>=0;$i--,$j--){$n=(int)$a[$i]-($j>=0?(int)$b[$j]:0)-$borrow;$borrow=$n<0?1:0;$out=($n<0?$n+10:$n).$out;}return self::norm($out);
    }
    public static function cents(string $price):string{return self::norm(str_replace('.','',Products::money($price)));}
    public static function money(string $cents):string{$s=str_pad($cents,3,'0',STR_PAD_LEFT);return substr($s,0,-2).'.'.substr($s,-2);}
    public static function sumMoney(string ...$amounts):string
    {
        $total='0';foreach($amounts as $amount)$total=self::add($total,self::cents($amount));
        if(self::compare($total,'999999999999999')>0)throw new InventoryException(['amount'=>'Total exceeds the supported LKR amount.']);
        return self::money($total);
    }
    public static function compareMoney(string $a,string $b):int{return self::compare(self::cents($a),self::cents($b));}
    public static function subtractMoney(string $a,string $b):string
    {
        if(self::compareMoney($a,$b)<0)throw new InventoryException(['amount'=>'Amount cannot become negative.'],409);
        return self::money(self::subtract(self::cents($a),self::cents($b)));
    }
    public static function calculate(string $price,mixed $paid,?array $rule):array
    {
        $qty=Inventory::quantity($paid);$unit=Products::money($price);$gross=self::multiply((string)$qty,self::cents($unit));
        if(self::compare($gross,'999999999999999')>0)throw new InventoryException(['amount'=>'Paid line total exceeds DECIMAL(15,2); use a smaller quantity.']);
        $discount='0';$free=0;
        if($rule&&(isset($rule['mixed_product_ids'])||isset($rule['mixed_policy']))){
            if($rule['type']!=='BUY_X_GET_Y'||(string)$rule['buy_quantity']!=='6'||(string)$rule['free_quantity']!=='1'||($rule['mixed_policy']??MixedOil::POLICY)!==MixedOil::POLICY)throw new InventoryException(['type'=>'Unsupported mixed oil policy.']);
            // Basket rewards must be selected and validated by MixedOil, never by a single line.
            $rule=null;
        }
        if($rule){
            if($rule['type']==='BUY_X_GET_Y'){$buy=Inventory::quantity((string)$rule['buy_quantity']);$gift=Inventory::quantity((string)$rule['free_quantity']);$groups=intdiv($qty,$buy);if($groups>intdiv(PHP_INT_MAX,$gift))throw new InventoryException(['quantity'=>'Free quantity exceeds supported integer range.']);$free=$groups*$gift;}
            elseif($rule['type']==='PERCENTAGE'){$rate=self::cents((string)$rule['percent_rate']);if(self::compare($rate,'10000')>0||$rate==='0')throw new InventoryException(['percent_rate'=>'Percentage must be greater than zero and at most 100.']);$scaled=self::add(self::multiply($gross,$rate),'5000');$discount=strlen($scaled)>4?self::norm(substr($scaled,0,-4)):'0';}
            elseif($rule['type']==='FIXED'){$fixed=self::cents((string)$rule['fixed_discount']);if($fixed==='0'||self::compare($fixed,self::cents($unit))>0)throw new InventoryException(['fixed_discount'=>'Fixed discount must be positive and cannot exceed the current product price.']);$discount=self::multiply((string)$qty,$fixed);}
            else throw new InventoryException(['type'=>'Unsupported promotion type.']);
        }
        if($free>PHP_INT_MAX-$qty)throw new InventoryException(['quantity'=>'Paid plus free stock requirement exceeds the supported integer range.']);
        if(self::compare($discount,$gross)>0)throw new InventoryException(['discount'=>'Discount exceeds paid line total.']);
        return ['currency'=>'LKR','unit_price'=>$unit,'paid_quantity'=>(string)$qty,'free_quantity'=>(string)$free,'required_stock'=>(string)($qty+$free),'gross_amount'=>self::money($gross),'discount_amount'=>self::money($discount),'payable_amount'=>self::money(self::subtract($gross,$discount)),'rounding'=>'Paid line percentage discount rounded half-up to 0.01 LKR'];
    }
}
