<?php
declare(strict_types=1);
// CLI-only isolated harness. Never load .env or the application's connection factory.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('zend.exception_ignore_args','1');
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__)); date_default_timezone_set('UTC');
    spl_autoload_register(static function (string $class): void {
        if (strpos($class,'App\\')===0) { $path=APP_ROOT.'/app/'.str_replace('\\','/',substr($class,4)).'.php'; if (is_file($path)) { require $path; } }
    });
}
final class HybridIsolationHarness
{
    public static function harden(PDO $pdo,string $rootPassword): void
    {
        foreach ($pdo->query("SELECT Db FROM mysql.db WHERE User='PUBLIC'")->fetchAll(PDO::FETCH_COLUMN) as $db) {
            if (!in_array($db,['test','test\\_%'],true)) { throw new RuntimeException('Unexpected bootstrap public privilege.'); }
            $pdo->exec('REVOKE ALL PRIVILEGES ON `'.$db.'`.* FROM PUBLIC');
        }
        foreach (['localhost','127.0.0.1','::1'] as $host) {
            $pdo->exec("ALTER USER 'root'@".$pdo->quote($host).' IDENTIFIED BY '.$pdo->quote($rootPassword));
        }
    }
    public static function manifest(string $path): array
    {
        $real=realpath($path); $storage=realpath(APP_ROOT.'/storage');
        if (!$real || !$storage || strpos(strtolower($real),strtolower($storage.DIRECTORY_SEPARATOR.'stage2c-'))!==0 || basename($real)!=='manifest.json') { throw new RuntimeException('Explicit isolated manifest required.'); }
        $m=json_decode(file_get_contents($real),true,512,JSON_THROW_ON_ERROR);
        if (!preg_match('/^stage2c-[a-f0-9]{32}$/D',$m['run_id']??'') || !preg_match('/^dharma_sales_hybrid_[a-f0-9]{32}_test$/D',$m['database']??'')
            || !is_int($m['port']??null) || $m['port']<1024 || $m['port']>65535 || in_array($m['port'],[3306,3309],true)
            || realpath($m['directory'])!==dirname($real) || realpath($m['datadir'])!==realpath(dirname($real).'/data')) { throw new RuntimeException('Isolation manifest mismatch.'); }
        return $m;
    }
    public static function pdo(array $m,string $user,string $password,bool $schema=true): PDO
    {
        if (in_array($m['port'],[3306,3309],true)) { throw new RuntimeException('Forbidden endpoint.'); }
        $pdo=new PDO('mysql:host=127.0.0.1;port='.$m['port'].($schema?';dbname='.$m['database']:'').';charset=utf8mb4',$user,$password,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3]);
        $metadata=$pdo->query('SELECT @@port port,@@datadir datadir')->fetch();
        $normalize=static fn($p)=>strtolower(rtrim(str_replace('\\','/',$p),'/'));
        if ((int)$metadata['port']!==$m['port'] || $normalize($metadata['datadir'])!==$normalize(realpath($m['datadir']))) { throw new RuntimeException('Physical isolated server identity mismatch.'); }
        $pdo->exec("SET time_zone='+00:00'");
        $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    }
    public static function connection(array $m,string $role='runtime'): PDO
    {
        if (!in_array($role,['runtime','migration','admin'],true)) { throw new RuntimeException('Unknown isolated account role.'); }
        $accounts=json_decode(file_get_contents($m['directory'].'/accounts.json'),true,512,JSON_THROW_ON_ERROR);
        $a=$accounts[$role]; $pdo=self::pdo($m,$a['user'],$a['password']);
        if ($role==='runtime') {
            $grants=$pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($grants as $grant) {
                if (preg_match('/^GRANT USAGE ON \*\.\*/',$grant)) { continue; }
                if (strpos($grant,'GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$m['database'].'`.* TO ')!==0) { throw new RuntimeException('Runtime grants exceed the isolated schema.'); }
            }
            if (count($grants)!==2 || $pdo->query('SELECT run_id FROM hybrid_test_identity')->fetchColumn()!==$m['run_id']) { throw new RuntimeException('Isolated identity/grant proof failed.'); }
        }
        return $pdo;
    }
}
