<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\InventoryException;

/** Pure, bounded quantity arithmetic. Never expand quantities into individual units. */
final class MixedOil
{
    public const POLICY='MIXED_OIL_V1';
    public static function rewards(mixed $input):array
    {
        if(!is_array($input)||count($input)>100)throw new InventoryException(['oil_rewards'=>'Invalid oil reward selections.']);
        $out=[];foreach($input as $r){$r=(array)$r;$id=Representatives::id($r['product_id']??null,'oil_rewards');$q=$r['quantity']??'0';if($q==='0')continue;$q=(string)Inventory::quantity($q);if(isset($out[$id]))throw new InventoryException(['oil_rewards'=>'Select each free oil only once.']);$out[$id]=$q;}
        uksort($out,fn($a,$b)=>PromotionCalculator::compare((string)$a,(string)$b));return $out;
    }
    public static function plan(array $rule,array $lines,array $products,array $selected):array
    {
        $members=$rule['mixed_product_ids'];$paid=[];$total=0;
        foreach($lines as $l)if(in_array($l['product_id'],$members,true)){
            if(($l['rule_snapshot']['revision_id']??null)!==$rule['revision_id'])throw new InventoryException(['oil_rewards'=>'Conflicting automatic oil promotion.'],409);
            $q=(int)$l['paid_quantity'];if($q>PHP_INT_MAX-$total)throw new InventoryException(['quantity'=>'Combined oil quantity exceeds the supported range.']);$total+=$q;$paid[]=$l;
        }
        usort($paid,fn($a,$b)=>PromotionCalculator::compareMoney($a['unit_price'],$b['unit_price'])?:PromotionCalculator::compare($a['product_id'],$b['product_id']));
        $earned=intdiv($total,6);$bands=[];$offset=0;
        foreach($paid as $l){$end=$offset+(int)$l['paid_quantity'];$first=intdiv($offset,6)+($offset%6?1:0);$last=min($earned,intdiv($end-1,6)+1);$n=max(0,$last-$first);if($n)$bands[]=['unit_price'=>$l['unit_price'],'quantity'=>(string)$n];$offset=$end;}
        $chosen=[];$count=0;foreach($selected as $id=>$qty)if(in_array((string)$id,$members,true)){$p=$products[$id];if((int)$qty>PHP_INT_MAX-$count)throw new InventoryException(['oil_rewards'=>'Too many oil rewards.']);$count+=(int)$qty;$chosen[]=['product_id'=>(string)$id,'quantity'=>$qty,'unit_price'=>$p['selling_price']];}
        if($count>$earned)throw new InventoryException(['oil_rewards'=>'Selected free oils exceed the earned entitlement.'],409);
        usort($chosen,fn($a,$b)=>PromotionCalculator::compareMoney($a['unit_price'],$b['unit_price'])?:PromotionCalculator::compare($a['product_id'],$b['product_id']));
        $remaining=array_map(fn($b)=>(int)$b['quantity'],$bands);
        foreach($chosen as $c){$need=(int)$c['quantity'];foreach($bands as $i=>$b){if(PromotionCalculator::compareMoney($c['unit_price'],$b['unit_price'])>0)continue;$take=min($need,$remaining[$i]);$need-=$take;$remaining[$i]-=$take;if(!$need)break;}if($need)throw new InventoryException(['oil_rewards'=>'A selected free oil costs more than the cheapest paid oil in its six-unit group.'],409);}
        $choices=[];foreach($members as $id){$p=$products[$id];$capacity=0;foreach($bands as $i=>$b)if(PromotionCalculator::compareMoney($p['selling_price'],$b['unit_price'])<=0)$capacity+=$remaining[$i];$stock=(int)$p['available_quantity'];foreach($lines as $l)if($l['product_id']===$id)$stock-=(int)$l['required_stock'];$stock-=(int)($selected[$id]??0);$choices[]=['product_id'=>$id,'name'=>$p['name'],'name_si'=>$p['name_si']??null,'sku'=>$p['sku'],'unit_price'=>$p['selling_price'],'available_quantity'=>(string)max(0,$stock),'additional_quantity'=>(string)min(max(0,$stock),$capacity),'selected_quantity'=>$selected[$id]??'0'];}
        return ['policy'=>self::POLICY,'promotion_id'=>$rule['id'],'name'=>$rule['name'],'earned'=>(string)$earned,'selected'=>(string)$count,'remaining'=>(string)($earned-$count),'groups'=>$bands,'choices'=>$choices];
    }
}
