<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Middleware\Csrf;
use App\Middleware\Session;
use App\Support\Http;
use App\Services\Authentication;
final class AuthController
{
    public function __construct(private Authentication $auth) {}
    public static function url(string $path): string { return rtrim(getenv('APP_BASE_PATH') ?: '', '/').$path; }
    public static function redirect(string $path): void { header('Location: '.self::url($path), true, 303); }
    public function login(): void
    {
        $user = $this->auth->currentUser();
        if ($user) { self::redirect($user['role'] === 'ADMIN' ? '/admin' : '/rep'); return; }
        Session::start(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $error = ''; $username = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? null;
            if (!is_string($token) || !Csrf::valid($token,$_SESSION['csrf_token'] ?? null)) { Http::error('CSRF_FAILED','Refresh the page and try again.',403); return; }
            $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/D',$username) || strlen($password) < 1 || strlen($password) > 72) {
                // Malformed credentials share the same generic outcome and limiter.
                $password = '';
            }
            $result = $this->auth->login($username, $password, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (in_array($result,['ADMIN','SALES_REP'],true)) { self::redirect($result === 'ADMIN' ? '/admin' : '/rep'); return; }
            http_response_code($result === 'limited' ? 429 : 401);
            if ($result === 'limited') { header('Retry-After: 900'); }
            $error = $result === 'limited' ? 'Too many sign-in attempts. Please try again in 15 minutes.' : 'Unable to sign in. Check your username and password.';
        }
        require APP_ROOT.'/app/Views/login.php';
    }
    public function logout(): void
    {
        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !Csrf::valid($token,$_SESSION['csrf_token'] ?? null)) { Http::error('CSRF_FAILED','Refresh the page and try again.',403); return; }
        Session::destroy(); self::redirect('/login');
    }
    public function dashboard(array $user, string $role): void
    {
        $modules = $role === 'ADMIN' ? ['Products','Customers','Inventory','Sales Representatives','Promotions','Invoices','Offline Sales Review','Reports'] : ['Assigned Stock','Promotions','Customers','New Invoice','Sales History','Offline Sales','Sync Status','Reports'];
        $dashboardToday=null;$dashboardTrend=null;
        try {
            $reports=new \App\Services\Reports(\App\Support\Database::connect());
            $today=new \DateTimeImmutable('today',new \DateTimeZone('Asia/Colombo'));
            $dashboardToday=$reports->report($user,'overview',['period'=>'today']);
            $dashboardTrend=$reports->report($user,'sales',['period'=>'custom','from'=>$today->modify('-6 days')->format('Y-m-d'),'to'=>$today->format('Y-m-d'),'group'=>'day']);
        } catch (\Throwable $e) {
            error_log('Dashboard reports unavailable: '.$e->getMessage());
        }
        if($role==='SALES_REP'&&$user['role']==='SALES_REP'){$repStock=new \App\Services\RepresentativeStock(\App\Support\Database::connect());$assignedStock=$repStock->balances($user,[],true);$allocationHistory=$repStock->history($user,[],true);}
        require APP_ROOT.'/app/Views/dashboard.php';
    }
}
