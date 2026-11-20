<?php
declare(strict_types=1);
namespace App\Middleware;
final class Csrf
{
    public static function valid(?string $submitted, ?string $expected): bool
    {
        return $submitted !== null && $expected !== null && strlen($expected) === 64 && hash_equals($expected, $submitted);
    }
}
