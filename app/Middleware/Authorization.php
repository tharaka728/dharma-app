<?php
declare(strict_types=1);
namespace App\Middleware;
final class Authorization
{
    // Call with a user freshly loaded server-side, never request-supplied identity.
    public static function allows(?array $user, string $role): bool
    {
        return $user !== null && (int) ($user['is_active'] ?? 0) === 1
            && in_array($user['role'] ?? '', ['ADMIN', 'SALES_REP'], true)
            && ($user['role'] === 'ADMIN' || $user['role'] === $role);
    }
    public static function owns(?array $user, string $ownerId): bool
    {
        return self::allows($user, 'SALES_REP') && ($user['role'] === 'ADMIN' || (string) $user['id'] === $ownerId);
    }
}
