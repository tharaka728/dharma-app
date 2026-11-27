<?php
declare(strict_types=1);
namespace App\Support;
use PDO;use Closure;use RuntimeException;
// Explicit fresh-install workflow; normal bin/migrate.php is unchanged.
// The caller must prove a disposable physical server before passing a DDL connection.
final class HybridFreshInstaller
{
    private const STEP='006a_customer_locations_compat_v1.sql';
    public const CONTRACT_SHA256='a9e8f370fc2719d6e4476467d9e218e6edead003f96ce5399927bb959c9eb251';
    private static ?array $scope=null;
    public static function bindScope(array $scope):void
    {
        self::$scope=HybridIsolatedRuntime::manifest($scope['directory'].'/manifest.json');
    }
    public static function proof(PDO $p):array
    {
        $schema=[];$data=[];
        foreach($p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
            if(in_array($table,['schema_migrations','hybrid_test_identity','fresh_install_authorization'],true))continue;
            $schema[$table]=$p->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
            $rows=$p->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as &$row)foreach(array_keys($row) as $key)if(str_ends_with($key,'_at_utc'))unset($row[$key]);unset($row);
            usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$data[$table]=$rows;
        }
        ksort($schema);ksort($data);
        return ['schema'=>hash('sha256',json_encode($schema,JSON_THROW_ON_ERROR)),'data'=>hash('sha256',json_encode($data,JSON_THROW_ON_ERROR))];
    }
    public static function run(PDO $p,string $through='027',?Closure $interrupt=null,bool $newOnly=false):void
    {
        if(PHP_SAPI!=='cli')throw new RuntimeException('CLI isolated proposal only.');
        $scope=self::$scope;if(!$scope)throw new RuntimeException('Explicit disposable manifest binding required.');
        $identity=$p->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch(PDO::FETCH_ASSOC);
        $norm=static fn($path)=>strtolower(rtrim(str_replace('\\','/',$path),'/'));
        if((int)$identity['port']!==$scope['port']||$norm($identity['datadir'])!==$norm(realpath($scope['datadir']))||($identity['db']!==$scope['database']&&$identity['db']!=='dharma_sales_phase12_restore_20261118154343'&&!preg_match('/^dharma_stage6[bc]_(?:foundation_)?[a-f0-9]+_test$/D',$identity['db'])))throw new RuntimeException('Disposable installation target mismatch.');
        if(!str_starts_with((string)$p->query('SELECT VERSION()')->fetchColumn(),'10.11.16-MariaDB'))throw new RuntimeException('Unsupported fresh-install server version.');
        if(!hash_equals(self::CONTRACT_SHA256,hash_file('sha256',APP_ROOT.'/database/fresh-install/contract-v1.json')))throw new RuntimeException('Fresh-install contract changed.');
        $expected=json_decode(file_get_contents(APP_ROOT.'/database/fresh-install/contract-v1.json'),true,512,JSON_THROW_ON_ERROR);
        $files=glob(APP_ROOT.'/database/migrations/*.sql');sort($files,SORT_STRING);
        if(array_map('basename',$files)!==array_keys($expected['files']))throw new RuntimeException('Migration sequence differs from contract.');
        foreach($files as $file)if(!hash_equals($expected['files'][basename($file)]??'',hash_file('sha256',$file)))throw new RuntimeException('Original migration bytes differ: '.basename($file));
        $step=APP_ROOT.'/database/fresh-install/'.self::STEP;
        if(!hash_equals($expected['step'],hash_file('sha256',$step)))throw new RuntimeException('Compatibility SQL checksum mismatch.');
        if((int)$p->query("SELECT GET_LOCK('dharma_schema_migrations',10)")->fetchColumn()!==1)throw new RuntimeException('Migration lock unavailable.');
        try{
            $tables=$p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if($newOnly&&array_diff($tables,['hybrid_test_identity'])!==[])throw new RuntimeException('New installation is not empty.');
            if(!in_array('fresh_install_authorization',$tables,true)){
                if(array_diff($tables,['hybrid_test_identity'])!==[])throw new RuntimeException('Explicit new empty installation required.');
                $p->exec('CREATE TABLE fresh_install_authorization (id INT PRIMARY KEY, contract_checksum CHAR(64) NOT NULL, database_name VARCHAR(150) NOT NULL) ENGINE=InnoDB');
                $p->prepare('INSERT INTO fresh_install_authorization VALUES (1,?,DATABASE())')->execute([hash_file('sha256',APP_ROOT.'/database/fresh-install/contract-v1.json')]);
            }
            $auth=$p->query('SELECT * FROM fresh_install_authorization')->fetchAll(PDO::FETCH_ASSOC);
            if(count($auth)!==1||$auth[0]['database_name']!==$p->query('SELECT DATABASE()')->fetchColumn()||!hash_equals($auth[0]['contract_checksum'],hash_file('sha256',APP_ROOT.'/database/fresh-install/contract-v1.json')))throw new RuntimeException('Fresh-install authorization mismatch.');
            $p->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(150) PRIMARY KEY, checksum CHAR(64) NOT NULL, status VARCHAR(10) NOT NULL, applied_at_utc DATETIME(6) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $all=$files;array_splice($all,6,0,[$step]);
            $known=array_map('basename',$all);
            foreach($p->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN)as$name)if(!in_array($name,$known,true))throw new RuntimeException('Unexpected migration journal entry.');
            $prefix=true;
            foreach($all as $file){
                if($file===$step&&$through==='006')break;
                $name=basename($file);if(strcmp(substr($name,0,3),$through)>0)break;
                $hash=hash_file('sha256',$file);$q=$p->prepare('SELECT checksum,status FROM schema_migrations WHERE version=?');$q->execute([$name]);$old=$q->fetch(PDO::FETCH_ASSOC);
                if($old&&($old['checksum']!==$hash||!$prefix))throw new RuntimeException('Journal checksum/order mismatch: '.$name);
                if($old&&$old['status']==='applied')continue;
                $prefix=false;
                if($name===self::STEP){
                    $journal=$p->query("SELECT version,status FROM schema_migrations WHERE version<>'".self::STEP."' ORDER BY version")->fetchAll(PDO::FETCH_KEY_PAIR);
                    $want=array_fill_keys(array_map('basename',array_slice($files,0,6)),'applied');
                    if($journal!==$want)throw new RuntimeException('Compatibility requires exactly completed 001-006.');
                    $proof=self::proof($p);$before=in_array($proof,array_column($expected['states'],'before'),true);$after=in_array($proof,array_column($expected['states'],'after'),true);
                    if(!$before&&!$after)throw new RuntimeException('Unexpected or populated pre-007 schema.');
                    if($after&&!$old)throw new RuntimeException('Unjournaled compatibility change rejected.');
                    if($old&&$old['status']!=='applying')throw new RuntimeException('Unexpected compatibility state.');
                    if(!$old)$p->prepare("INSERT INTO schema_migrations(version,checksum,status) VALUES (?,?,'applying')")->execute([$name,$hash]);
                    if($before){$p->exec(file_get_contents($file));if($interrupt)$interrupt('compat-ddl');}
                    if(!in_array(self::proof($p),array_column($expected['states'],'after'),true))throw new RuntimeException('Compatibility postcondition failed.');
                }else{
                    if($old)throw new RuntimeException('Partially applied original migration: '.$name.'; restore a new installation, never retry DDL blindly.');
                    $p->prepare("INSERT INTO schema_migrations(version,checksum,status) VALUES (?,?,'applying')")->execute([$name,$hash]);
                    $n=0;foreach(explode(';',file_get_contents($file))as$sql)if(trim($sql)!==''){$p->exec($sql);if($interrupt)$interrupt($name.':'.++$n);}
                }
                $p->prepare("UPDATE schema_migrations SET status='applied',applied_at_utc=UTC_TIMESTAMP(6) WHERE version=? AND checksum=? AND status='applying'")->execute([$name,$hash]);
            }
        }finally{$p->query("SELECT RELEASE_LOCK('dharma_schema_migrations')");}
    }
}
