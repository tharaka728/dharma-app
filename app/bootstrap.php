<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'App\\') === 0) {
        $file = APP_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) { require $file; }
    }
});
if (is_file(APP_ROOT . '/.env')) {
    foreach (file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') { continue; }
        if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $m)) {
            throw new RuntimeException('Invalid environment configuration.');
        }
        if (getenv($m[1]) === false) { putenv($m[1] . '=' . trim($m[2], "\"'")); }
    }
}
