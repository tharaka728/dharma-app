<?php
declare(strict_types=1);
namespace App\Services\HistoricalHybridV1;
// Read-only historical evaluator. Pinned ed147d41894b109fae277e8e69b40e8a3dfecb57; source blob 6b03ffcd4b842ea2ec28eaf3bd4dec59e2f28b6f.
use App\Support\ProductException;
final class Products
{
    public static function money(mixed $value, bool $optional=false): ?string
    {
        if ($optional && ($value === '' || $value === null)) { return null; }
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D',$value,$m)) { throw new ProductException(['prices'=>'Enter nonnegative decimal prices with at most 13 whole digits and 2 decimal places.']); }
        return $m[1].'.'.str_pad($m[2] ?? '',2,'0');
    }
}
