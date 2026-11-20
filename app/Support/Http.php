<?php
declare(strict_types=1);
namespace App\Support;
final class Http
{
    public static function json(array $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(I18n::messages($body), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
    public static function error(string $code, string $message, int $status): void
    {
        self::json(['error' => ['code' => $code, 'message' => $message, 'request_id' => bin2hex(random_bytes(8))]], $status);
    }
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
