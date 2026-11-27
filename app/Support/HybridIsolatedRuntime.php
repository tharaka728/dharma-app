<?php
declare(strict_types=1);
namespace App\Support;
use PDO;
use RuntimeException;

/** Explicit isolated application runtime. No default connection factory or .env. */
final class HybridIsolatedRuntime
{
    private static function norm(string $p):string{return strtolower(rtrim(str_replace('\\','/',$p),'/'));}
    public static function manifest(string $path):array
    {
        $file=realpath($path);$root=realpath(APP_ROOT.'/storage');
        if(!$file||!$root||!preg_match('~^'.preg_quote(self::norm($root),'~').'/stage2c-[a-f0-9]{32}/manifest.json$~D',self::norm($file)))throw new RuntimeException('Explicit disposable manifest required.');
        $m=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
        if(!is_int($m['port']??null)||$m['port']<1024||$m['port']>65535||in_array($m['port'],[3306,3309],true)||!preg_match('/^dharma_sales_hybrid_[a-f0-9]{32}_test$/D',$m['database']??'')||!preg_match('/^stage2c-[a-f0-9]{32}$/D',$m['run_id']??'')||basename(dirname($file))!==($m['run_id']??'')||realpath($m['directory']??'')!==dirname($file)||realpath($m['datadir']??'')!==realpath(dirname($file).'/data'))throw new RuntimeException('Disposable manifest mismatch.');
        if(self::norm((string)realpath($m['datadir']))!==self::norm(dirname($file).'/data'))throw new RuntimeException('Disposable data directory must stay inside its run.');
        return $m;
    }
    public static function connect(array $m,array $account,bool $runtime=true):PDO
    {
        $m=self::manifest($m['directory'].'/manifest.json'); // Reject forbidden endpoints before PDO.
        $p=new PDO('mysql:host=127.0.0.1;port='.$m['port'].';dbname='.$m['database'].';charset=utf8mb4',$account['user'],$account['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3]);
        $r=$p->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
        if($r['db']!==$m['database']||(int)$r['port']!==$m['port']||self::norm($r['datadir'])!==self::norm(realpath($m['datadir']))||$p->query('SELECT run_id FROM hybrid_test_identity')->fetchAll(PDO::FETCH_COLUMN)!==[$m['run_id']])throw new RuntimeException('Physical database identity mismatch.');
        $grants=$p->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);if(count($grants)!==2)throw new RuntimeException('Unexpected grants.');
        $allowed='GRANT '.($runtime?'SELECT, INSERT, UPDATE, DELETE':'ALL PRIVILEGES').' ON `'.$m['database'].'`.* TO ';
        foreach($grants as$g)if(!str_starts_with($g,'GRANT USAGE ON *.* TO ')&&!str_starts_with($g,$allowed))throw new RuntimeException('Database grants exceed scope.');
        $p->exec("SET time_zone='+00:00'");$p->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");return $p;
    }
    public static function profile(string $path):array
    {
        $file=realpath($path);if(!$file||basename($file)!=='profile.json')throw new RuntimeException('Private runtime profile required.');
        $r=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);$m=self::manifest($r['manifest']??'');
        if(($r['mode']??null)!=='HYBRID_ISOLATED_V1'||($r['private_acl_verified']??false)!==true||dirname(dirname($file))!==realpath($m['directory'])||!preg_match('/^stage6c-runtime-[a-f0-9]{24}$/D',basename(dirname($file))))throw new RuntimeException('Isolated runtime profile mismatch.');
        $dir=dirname($file);foreach(['key.txt','credentials.json','policy.json']as$name)if(realpath($dir.'/'.$name)!==$dir.DIRECTORY_SEPARATOR.$name)throw new RuntimeException('Private file identity mismatch.');
        $key=trim(file_get_contents($dir.'/key.txt'));if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new RuntimeException('Invalid IPC secret.');
        $cfg=json_decode(file_get_contents($dir.'/policy.json'),true,32,JSON_THROW_ON_ERROR);
        if(($cfg['databases']??null)!==[$m['database']]||!is_bool($cfg['financial_posting_hold']??null)||!is_bool($cfg['enabled']??null))throw new RuntimeException('Invalid isolated policy.');
        $source=require APP_ROOT.'/config/hybrid-promotions.php';if($source['enabled']!==false||$source['financial_posting_hold']!==true)throw new RuntimeException('Source controls must stay disabled/held.');
        $r+=['timeout_ms'=>8000,'max_workers'=>2];if(!is_int($r['timeout_ms'])||$r['timeout_ms']<100||$r['timeout_ms']>15000||!is_int($r['max_workers'])||$r['max_workers']<1||$r['max_workers']>4)throw new RuntimeException('Invalid worker bounds.');
        return array_merge($r,['file'=>$file,'directory'=>$dir,'scope'=>$m,'key'=>$key,'config'=>$cfg,'account'=>json_decode(file_get_contents($dir.'/credentials.json'),true,16,JSON_THROW_ON_ERROR)]);
    }
}
