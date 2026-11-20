<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Middleware\Authorization;
use App\Support\Http;
$count = 0;
$check = static function (bool $value, string $label) use (&$count): void {
    if (!$value) { throw new RuntimeException($label); }
    $count++;
};
$check(!Authorization::allows(null, 'ADMIN'), 'Anonymous access denied');
$rep = ['id' => '12', 'role' => 'SALES_REP', 'is_active' => 1];
$check(!Authorization::allows($rep, 'ADMIN'), 'Rep cannot access admin');
$check(Authorization::owns($rep, '12'), 'Rep owns own scope');
$check(!Authorization::owns($rep, '13'), 'Cross-rep scope denied');
$check(!Authorization::owns(array_merge($rep, ['is_active'=>0]), '12'), 'Inactive denied');
$check(Http::escape('<script>"&') === '&lt;script&gt;&quot;&amp;', 'Output escaped');
$hash = password_hash('test-value-only', PASSWORD_DEFAULT);
$check(password_verify('test-value-only', $hash) && !password_verify('wrong', $hash), 'Password hashing');
$check(date_default_timezone_get() === 'UTC', 'UTC default');
$check(\App\Support\Validation::uuid('11111111-1111-4111-8111-111111111111'), 'Valid UUID');
$check(!\App\Support\Validation::uuid('INVALID'), 'Invalid UUID');
$check(\App\Support\Validation::money('500.00') && !\App\Support\Validation::money('-1.00') && !\App\Support\Validation::money('1.234'), 'Decimal money validation');
$token = str_repeat('a',64);
$check(\App\Middleware\Csrf::valid($token,$token) && !\App\Middleware\Csrf::valid(null,$token) && !\App\Middleware\Csrf::valid('wrong',$token), 'CSRF fail closed');
echo "$count foundation checks passed.\n";
