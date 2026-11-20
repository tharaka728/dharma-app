<?php
declare(strict_types=1);
namespace App\Support;
use PDO;
final class Database
{
    public static function connect(): PDO
    {
        $c = require APP_ROOT . '/config/database.php';
        foreach (['host', 'port', 'name'] as $key) {
            if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $c[$key])) { throw new \RuntimeException('Invalid database configuration.'); }
        }
        $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    }
}
