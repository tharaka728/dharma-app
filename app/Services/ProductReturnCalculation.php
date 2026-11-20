<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\InventoryException;

/** Pure arithmetic only; prices and quantities must also be authorized by the posting service. */
final class ProductReturnCalculation
{
    public static function historicalLine(string $approvedUnitPrice, mixed $quantity): string
    {
        $units=Inventory::quantity($quantity);
        $cents=PromotionCalculator::multiply((string)$units, PromotionCalculator::cents($approvedUnitPrice));
        if(PromotionCalculator::compare($cents,'999999999999999')>0)
            throw new InventoryException(['amount'=>'Return line exceeds the supported LKR amount.']);
        return PromotionCalculator::money($cents);
    }

    public static function settlement(string $approvedValue, string $invoicePayable): array
    {
        $approvedValue=Products::money($approvedValue);
        $invoicePayable=Products::money($invoicePayable);
        $applied=PromotionCalculator::compareMoney($approvedValue,$invoicePayable)>0?$invoicePayable:$approvedValue;
        return [
            'approved_value'=>$approvedValue,
            'applied_value'=>$applied,
            'forfeited_value'=>PromotionCalculator::subtractMoney($approvedValue,$applied),
            'remaining_payable'=>PromotionCalculator::subtractMoney($invoicePayable,$applied),
        ];
    }
}
