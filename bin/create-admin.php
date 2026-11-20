<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
if (getenv('APP_ENV') !== 'development') { fwrite(STDERR, "Development only. Set APP_ENV=development in your local environment.\n"); exit(1); }
$username = $argv[1] ?? '';
$name = $argv[2] ?? '';
if (!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/D', $username) || trim($name) === '' || strlen($name) > 150) {
    fwrite(STDERR, "Usage: php bin/create-admin.php username \"Display Name\"\n"); exit(1);
}
// Password arrives only through a private stdin pipe, never argv or output.
if (($argv[3] ?? '') !== '--stdin-password') { fwrite(STDERR,"Use bin/create-admin-local.ps1 for hidden password entry.\n"); exit(1); }
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password) < 12 || strlen($password) > 72 || strpos($password,"\0") !== false) { fwrite(STDERR,"Password must be 12 to 72 bytes.\n"); exit(1); }
try {
    $pdo = \App\Support\Database::connect();
    $pdo->beginTransaction();
    $q = $pdo->prepare("INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,'ADMIN')");
    $q->execute([$name,$username,password_hash($password, PASSWORD_DEFAULT)]);
    $id = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,reason) VALUES (?,'DEV_ADMIN_CREATE','users',?,'Development CLI provisioning')")->execute([$id,$id]);
    $pdo->commit();
    unset($password);
    fwrite(STDOUT, "Admin created. Sign in at http://127.0.0.1:8088/login. Password was not displayed.\n");
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "Admin creation failed. Check database setup and username uniqueness.\n"); exit(1);
}
