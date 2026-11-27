<?php
declare(strict_types=1);
namespace App\Services\HistoricalHybridV1;
// Read-only historical evaluator. Pinned ed147d41894b109fae277e8e69b40e8a3dfecb57; source blob 2a810a90c973dd7e8c7a1e44a16039fd74c08906.
use App\Support\InventoryException;
final class Inventory
{
    public static function quantity(mixed $value,bool $zero=false):int
    {
        if(!is_string($value)||!preg_match('/^(0|[1-9][0-9]{0,18})$/D',$value)||strlen($value)>strlen((string)PHP_INT_MAX)||(strlen($value)===strlen((string)PHP_INT_MAX)&&strcmp($value,(string)PHP_INT_MAX)>0)||(!$zero&&$value==='0')){throw new InventoryException(['quantity'=>'Enter a whole-unit quantity within the signed 64-bit range'.($zero?' (zero allowed).':', greater than zero.')]);}
        return (int)$value;
    }
}
