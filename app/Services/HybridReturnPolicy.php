<?php
declare(strict_types=1);
namespace App\Services;
use App\Services\PromotionCalculator as M;
use App\Support\InventoryException;

/** A1–E1 original-position arithmetic only. No PDO, clock, current price or posting. */
final class HybridReturnPolicy
{
    public const COMPONENTS=['gross'=>'gross_amount','product_discount_amount'=>'product_discount_amount','replacement_discount_amount'=>'replacement_discount_amount','invoice_discount_amount'=>'invoice_discount_amount','net'=>'net_credit'];
    public static function zero():array{return array_fill_keys(array_values(self::COMPONENTS),'0.00');}
    public static function amounts(array $ranges):array
    {
        $totals=self::zero();foreach($ranges as $range)foreach(self::COMPONENTS as $key=>$field)$totals[$field]=M::sumMoney($totals[$field],M::money(M::multiply($range['cents'][$key],(string)$range['position_count'])));return $totals;
    }
    public static function state(array $snapshot,array $history):array
    {
        $state=['revision'=>0,'credit'=>'0.00','debt_reduction'=>'0.00','refund_due'=>'0.00','cash_at_return'=>'0.00','totals'=>self::zero(),'lines'=>[],'terminal'=>false];
        foreach($snapshot['review']['calculation']['lines'] as $line)$state['lines'][(string)$line['line_number']]=['paid'=>0,'free'=>0,'accepted'=>[],'cursors'=>[]];
        foreach($history as $event){
            if($state['terminal']||($event['revision']??0)!==$state['revision']+1)throw new \LogicException('Hybrid return history revision or terminal state mismatch.');
            $basis=['cash_collected'=>$event['cash_at_return'],'paid_amount'=>$event['cash_at_return'],'noncash'=>false,'legacy_void'=>false];
            $request=['operation'=>$event['operation'],'lines'=>array_map(static fn($line)=>$line['receipt'],$event['lines'])];
            $rebuilt=self::fromState($snapshot,$state,$request,$basis);
            foreach(['lines','totals','retained_gross','retained_discount','debt_reduction','refund_increment'] as $field)if(HybridInvoiceRequest::json($event[$field])!==HybridInvoiceRequest::json($rebuilt[$field]))throw new \LogicException('Hybrid return immutable component mismatch: '.$field);
            if($rebuilt['findings'])throw new \LogicException('An accepted Hybrid return violates A1–E1.');
            if(M::compareMoney($event['cash_at_return'],$state['cash_at_return'])<0)throw new \LogicException('Original collected cash cannot move backwards.');
            $state=$rebuilt['next_state'];$state['cash_at_return']=$event['cash_at_return'];
        }
        return $state;
    }
    public static function plan(array $snapshot,array $history,array $request,array $financial):array
    {
        HybridInvoiceSnapshots::reconcile($snapshot);
        return self::fromState($snapshot,self::state($snapshot,$history),$request,$financial);
    }
    private static function fromState(array $snapshot,array $state,array $request,array $financial):array
    {
        $request['lines']=HybridReturnRequest::lines($request['lines']);
        $c=$snapshot['review']['calculation'];$original=[];foreach($c['lines'] as $line)$original[(string)$line['line_number']]=$line;
        if($state['terminal']||($financial['legacy_void']??false))throw new InventoryException(['return'=>'The original invoice is already terminally voided.'],409);
        $lines=[];$totals=self::zero();$next=$state;$findings=[];
        foreach($request['lines'] as $receipt){
            $key=$receipt['line_number'];$line=$original[$key]??null;if(!$line)throw new InventoryException(['line'=>'Choose an original sealed invoice line.']);
            $prior=$state['lines'][$key];$paid=Inventory::quantity($receipt['paid_quantity'],true);$free=Inventory::quantity($receipt['free_quantity'],true);
            if($paid>(int)$line['paid_quantity']-$prior['paid']||$free>(int)$line['actual_free_quantity']-$prior['free'])throw new InventoryException(['quantity'=>'Return exceeds unreturned original delivered quantities.'],409);
            $allocation=$paid?HybridUnitAllocations::consumeAfter($snapshot['allocations'],(int)$key,$paid,$prior['accepted']):['ranges'=>[],'next_cursors'=>$prior['cursors']];
            $amounts=self::amounts($allocation['ranges']);
            if($paid)$next['lines'][$key]['accepted'][]=$allocation['ranges'];
            $next['lines'][$key]['cursors']=$allocation['next_cursors'];$next['lines'][$key]['paid']+=$paid;$next['lines'][$key]['free']+=$free;
            $remainingPaid=(int)$line['paid_quantity']-$next['lines'][$key]['paid'];
            $rule=$line['promotion_snapshot'];$retainedEntitlement=$rule===null?0:intdiv($remainingPaid,(int)$rule['buy_quantity'])*(int)$rule['free_quantity'];
            $required=max(0,(int)$line['actual_free_quantity']-$prior['free']-$retainedEntitlement);
            if($free<$required)$findings[]=['code'=>'MISSING_FREE_RECOVERY','line_number'=>$key,'message'=>'Receive at least '.$required.' original free units for line '.$key.'; financial posting is blocked.'];
            $lines[]=['line_number'=>$key,'product_id'=>$line['product_id'],'receipt'=>$receipt,'ranges'=>$allocation['ranges'],'free_position_start'=>$prior['free'],
                'required_free_recovery'=>(string)$required,'remaining_paid_quantity'=>(string)$remainingPaid]+$amounts;
            foreach($totals as $field=>$value)$totals[$field]=M::sumMoney($value,$amounts[$field]);
        }
        if(!$request['lines']&&$request['operation']!=='VOID')throw new InventoryException(['lines'=>'Receive at least one original paid or free unit.']);
        foreach($totals as $field=>$value)$next['totals'][$field]=M::sumMoney($state['totals'][$field],$value);
        $remainingGross=M::subtractMoney($c['original_paid_subtotal'],$next['totals']['gross_amount']);
        $reversedDiscount=M::sumMoney($next['totals']['product_discount_amount'],$next['totals']['replacement_discount_amount'],$next['totals']['invoice_discount_amount']);
        $retainedDiscount=M::subtractMoney($c['combined_monetary_discount'],$reversedDiscount);
        if(M::compare(M::multiply(M::cents($retainedDiscount),'2'),M::cents($remainingGross))>0)$findings[]=['code'=>'RETAINED_CAP_EXCEEDED','message'=>'Retained original monetary discounts exceed the strict 50% cap. Manager review cannot waive this rule.'];
        if($financial['noncash']??false)$findings[]=['code'=>'NONCASH_SETTLEMENT','message'=>'Existing noncash settlement blocks Hybrid linked returns and residual voids. No cash or transferable credit is inferred.'];
        if($request['operation']==='VOID')foreach($original as $key=>$line){
            if($next['lines'][$key]['paid']!==(int)$line['paid_quantity']||$next['lines'][$key]['free']!==(int)$line['actual_free_quantity'])$findings[]=['code'=>'RESIDUAL_VOID_INCOMPLETE','message'=>'A residual void must receive every remaining paid and actual free unit.'];
        }
        $priorCredit=$state['credit'];$paidAmount=$financial['paid_amount'];
        $used=M::add(M::cents($paidAmount),M::cents($priorCredit));$debt=M::compare($used,M::cents($c['final_payable_total']))>=0?'0.00':M::money(M::subtract(M::cents($c['final_payable_total']),$used));
        $credit=$totals['net_credit'];$reduction=M::compareMoney($credit,$debt)>0?$debt:$credit;$refund=M::subtractMoney($credit,$reduction);
        $capacity=M::compareMoney($financial['cash_collected'],$state['refund_due'])<0?'0.00':M::subtractMoney($financial['cash_collected'],$state['refund_due']);
        if(M::compareMoney($refund,$capacity)>0)$findings[]=['code'=>'CASH_CAPACITY','message'=>'Refund Due exceeds remaining reconciled collected-cash capacity.'];
        $next['revision']++;$next['credit']=M::sumMoney($priorCredit,$credit);$next['debt_reduction']=M::sumMoney($state['debt_reduction'],$reduction);$next['refund_due']=M::sumMoney($state['refund_due'],$refund);$next['terminal']=$request['operation']==='VOID';
        return ['policy_version'=>HybridReturnRequest::POLICY,'operation'=>$request['operation'],'revision'=>$next['revision'],'lines'=>$lines,'totals'=>$totals,
            'cash_at_return'=>$financial['cash_collected'],'retained_gross'=>$remainingGross,'retained_discount'=>$retainedDiscount,'debt_reduction'=>$reduction,'refund_increment'=>$refund,
            'findings'=>$findings,'next_state'=>$next];
    }
}
