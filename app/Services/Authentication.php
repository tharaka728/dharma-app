<?php
declare(strict_types=1);
namespace App\Services;
use App\Middleware\Session;
use PDO;
final class Authentication
{
    public function __construct(private PDO $pdo) {}
    public function currentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) { return null; }
        if (Session::expired($_SESSION, time())) { Session::destroy(); return null; }
        $q = $this->pdo->prepare('SELECT id,name,username,role,is_active,password_hash FROM users WHERE id=?');
        $q->execute([$_SESSION['user_id']]); $user = $q->fetch();
        if (!$user || !(int)$user['is_active'] || !in_array($user['role'], ['ADMIN','SALES_REP'], true) || !hash_equals($_SESSION['auth_stamp'] ?? '', hash('sha256', $user['password_hash']))) {
            Session::destroy(); return null;
        }
        $_SESSION['last_seen'] = time();
        if (time() - ($_SESSION['rotated_at'] ?? 0) >= 900) { if (!session_regenerate_id(true)) { throw new \RuntimeException('Session rotation failed.'); } $_SESSION['rotated_at'] = time(); }
        unset($user['password_hash']);
        return $user;
    }
    // Atomic fixed-window reservation, including successful attempts. Both keys
    // are locked in sorted order so parallel requests cannot evade the limits.
    public function reserveAttempt(string $username, string $ip): bool
    {
        $limits = [hash('sha256', 'ip:'.$ip)=>30, hash('sha256', 'pair:'.$ip.':'.strtolower($username))=>5];
        ksort($limits, SORT_STRING);
        $now = gmdate('Y-m-d H:i:s'); $cutoff = gmdate('Y-m-d H:i:s', time()-900);
        $this->pdo->beginTransaction();
        try {
            $rows = [];
            foreach ($limits as $key=>$maximum) {
                $this->pdo->prepare('INSERT INTO auth_login_limits(bucket_hash,window_started_utc,attempts) VALUES (?,?,0) ON DUPLICATE KEY UPDATE bucket_hash=bucket_hash')->execute([$key,$now]);
                $q = $this->pdo->prepare('SELECT window_started_utc,attempts FROM auth_login_limits WHERE bucket_hash=? FOR UPDATE');
                $q->execute([$key]); $rows[$key] = $q->fetch();
            }
            foreach ($rows as $key=>$row) {
                if ($row['window_started_utc'] > $cutoff && (int)$row['attempts'] >= $limits[$key]) { $this->pdo->rollBack(); return false; }
            }
            foreach ($rows as $key=>$row) {
                $reset = $row['window_started_utc'] <= $cutoff;
                $this->pdo->prepare('UPDATE auth_login_limits SET window_started_utc=?,attempts=? WHERE bucket_hash=?')->execute([$reset ? $now : $row['window_started_utc'], $reset ? 1 : (int)$row['attempts']+1, $key]);
            }
            $this->pdo->commit(); return true;
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $e; }
    }
    public function login(string $username, string $password, string $ip): string
    {
        $username = strtolower(trim($username));
        if (!$this->reserveAttempt(substr($username,0,100), $ip)) { return 'limited'; }
        $q = $this->pdo->prepare('SELECT id,password_hash,is_active,role FROM users WHERE username=?');
        $q->execute([$username]); $user = $q->fetch();
        // Public dummy hash equalizes the password-verification path for unknown users.
        $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $syntaxValid = preg_match('/^[a-zA-Z0-9_.-]{3,100}$/D',$username) === 1 && strlen($password) >= 1 && strlen($password) <= 72;
        $valid = password_verify($syntaxValid ? $password : '', $hash);
        if (!$syntaxValid || !$user || !$valid || !(int)$user['is_active'] || !in_array($user['role'],['ADMIN','SALES_REP'],true)) { return 'invalid'; }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $new = password_hash($password, PASSWORD_DEFAULT);
            $this->pdo->prepare('UPDATE users SET password_hash=? WHERE id=? AND password_hash=?')->execute([$new,$user['id'],$hash]);
            $hash = $new;
        }
        Session::establish((int)$user['id']);
        $_SESSION['auth_stamp'] = hash('sha256', $hash);
        return $user['role'];
    }
}
