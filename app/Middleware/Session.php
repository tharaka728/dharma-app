<?php
declare(strict_types=1);
namespace App\Middleware;
final class Session
{
    public static function start(bool $https): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('dharma_session');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => (rtrim(getenv('APP_BASE_PATH') ?: '', '/') ?: '') . '/',
            'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
        ]);
        if (!session_start()) { throw new \RuntimeException('Session unavailable.'); }
        if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    }
    public static function establish(int $userId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || $userId < 1) { throw new \LogicException('Active session and valid identity required.'); }
        if (!session_regenerate_id(true)) { throw new \RuntimeException('Session rotation failed.'); }
        $_SESSION = ['user_id' => $userId, 'csrf_token' => bin2hex(random_bytes(32)), 'created_at' => time(), 'last_seen' => time(), 'rotated_at' => time()];
    }
    public static function expired(array $session, int $now): bool
    {
        return !isset($session['created_at'], $session['last_seen']) || $now - $session['last_seen'] >= 1800 || $now - $session['created_at'] >= 28800 || $now < $session['last_seen'];
    }
    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>$cookie['path'],'secure'=>$cookie['secure'],'httponly'=>true,'samesite'=>'Lax']);
            session_destroy();
        }
    }
}
