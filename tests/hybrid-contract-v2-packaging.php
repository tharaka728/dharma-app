<?php
declare(strict_types=1);
// Pure, database-independent Contract V2 packaging checks. CLI only.
// Verifies canonical LF release bytes, contract pins, fail-closed behavior
// and preservation of the historical V1 contract. Never connects anywhere.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }
require APP_ROOT.'/app/Support/HybridFreshInstaller.php';

$checks = 0;
$mc = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) { throw new RuntimeException('FAILED: '.$label); }
    $checks++;
};

// 1. .gitattributes pins deterministic LF checkout bytes for the release package.
$attr = file_get_contents(APP_ROOT.'/.gitattributes');
$mc($attr !== false, '.gitattributes exists');
foreach ([
    '/database/migrations/*.sql text eol=lf',
    '/database/fresh-install/*.sql text eol=lf',
    '/database/fresh-install/contract-v2.json text eol=lf',
    '/tests/hybrid-stage6b-install-contract.json text eol=lf',
] as $rule) {
    $mc(str_contains($attr, $rule), '.gitattributes rule: '.$rule);
}
$mc(!preg_match('/^\/database\/fresh-install\/\*\.json/m', $attr), 'no wildcard json normalization rule');
$mc(str_contains($attr, '/database/fresh-install/contract-v1.json -text'), 'contract-v1.json bytes protected from conversion');

// 2. V2 contract integrity: the installer pins this exact file's hash.
$v2path = APP_ROOT.'/database/fresh-install/contract-v2.json';
$mc(is_file($v2path), 'contract-v2.json exists');
$v2bytes = file_get_contents($v2path);
$mc(strpos($v2bytes, "\r") === false, 'contract-v2.json is pure LF');
$mc(hash_equals(App\Support\HybridFreshInstaller::CONTRACT_SHA256, hash('sha256', $v2bytes)), 'CONTRACT_SHA256 matches contract-v2.json bytes');
$v2 = json_decode($v2bytes, true, 512, JSON_THROW_ON_ERROR);

// 3. Historical V1 contract preserved byte-for-byte; new code must not reference it.
$v1path = APP_ROOT.'/database/fresh-install/contract-v1.json';
$mc(is_file($v1path), 'contract-v1.json preserved');
$mc(hash('sha256', file_get_contents($v1path)) === 'a9e8f370fc2719d6e4476467d9e218e6edead003f96ce5399927bb959c9eb251', 'contract-v1.json bytes unchanged (historical pin)');
$v1 = json_decode(file_get_contents($v1path), true, 512, JSON_THROW_ON_ERROR);

// 4. Every migration ships as canonical LF bytes matching its V2 pin, and any
//    other byte form (CRLF, mixed, tampered) hashes differently: fail-closed.
$files = glob(APP_ROOT.'/database/migrations/*.sql');
sort($files, SORT_STRING);
$mc(array_map('basename', $files) === array_keys($v2['files']), 'migration sequence matches contract exactly');
foreach ($files as $file) {
    $bytes = file_get_contents($file);
    $name = basename($file);
    $mc(strpos($bytes, "\r") === false, $name.' is canonical LF');
    $mc(hash_equals($v2['files'][$name], hash('sha256', $bytes)), $name.' matches V2 pin');
    $crlf = str_replace("\n", "\r\n", $bytes);
    $mc(!hash_equals($v2['files'][$name], hash('sha256', $crlf)), $name.' CRLF form is rejected');
    $mixed = preg_replace('/(?<!\r)\n/', "\r\n", $bytes, 1);
    $mc(!hash_equals($v2['files'][$name], hash('sha256', $mixed)), $name.' first-line CRLF mixed form is rejected');
    $mc(!hash_equals($v2['files'][$name], hash('sha256', $bytes.' ')), $name.' tampered form is rejected');
}

// 5. Compatibility step 006a: same filename, same LF bytes, pinned in V2.
$step = APP_ROOT.'/database/fresh-install/006a_customer_locations_compat_v1.sql';
$stepBytes = file_get_contents($step);
$mc(strpos($stepBytes, "\r") === false, '006a step is canonical LF');
$mc(hash_equals($v2['step'], hash('sha256', $stepBytes)), '006a step matches V2 pin');
$mc($v2['step'] === $v1['step'], '006a step pin identical across V1 and V2');

// 6. Migration 007 compatibility mechanism preserved: identical proof states.
$mc($v2['states'] === $v1['states'], 'V2 proof states identical to V1');

// 7. Installer wiring: V2 referenced everywhere, V1 never, raw-byte hashing kept.
$src = file_get_contents(APP_ROOT.'/app/Support/HybridFreshInstaller.php');
$mc(substr_count($src, "/database/fresh-install/contract-v2.json") === 4, 'installer references contract-v2.json in all 4 places');
$mc(strpos($src, '/database/fresh-install/contract-v1.json') === false, 'installer no longer loads contract-v1.json');
$mc(strpos($src, "private const STEP='006a_customer_locations_compat_v1.sql';") !== false, 'STEP filename unchanged');
$mc(strpos($src, "hash_file('sha256',APP_ROOT.'/database/fresh-install/contract-v2.json')") !== false, 'contract gate hashes the raw contract file');
$mc(strpos($src, "hash_file('sha256',$file)") !== false && strpos($src, "hash('sha256',file_get_contents") === false, 'raw-byte hashing preserved, no content normalization in installer');
$mig = file_get_contents(APP_ROOT.'/app/Support/Migrator.php');
$mc(strpos($mig, "hash_file('sha256', \$file)") !== false, 'Migrator raw-byte journal check preserved');

// 8. Installer test contract synchronized byte-for-byte with V2.
$mc(file_get_contents(APP_ROOT.'/tests/hybrid-stage6b-install-contract.json') === $v2bytes, 'test install contract identical to contract-v2.json');

echo "Contract V2 packaging: $checks checks passed.\n";
