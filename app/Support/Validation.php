<?php
declare(strict_types=1);
namespace App\Support;
final class Validation
{
    public static function uuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value) === 1;
    }
    public static function money(string $value): bool
    {
        return preg_match('/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/D', $value) === 1;
    }
}
