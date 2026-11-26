<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-fixtures.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');$p=HybridIsolationHarness::connection($m);App\Services\HybridDatabaseScope::verify($p,$m);
$dir=realpath($argv[2]??'');if(!$dir||dirname($dir)!==realpath($m['directory'])||!preg_match('/^stage6c-runtime-[a-f0-9]{24}$/D',basename($dir))||!is_file($dir.'/acl-verified.json')||is_file($dir.'/profile.json'))throw new RuntimeException('New verified private runtime required.');
$f=json_decode(file_get_contents($m['directory'].'/fixtures-stage3.json'),true,512,JSON_THROW_ON_ERROR);$cfg=hybridTestConfig($m,$f);$cfg['financial_posting_hold']=true;$cfg['return_posting']=$cfg['custody']=true;
$accounts=json_decode(file_get_contents($m['directory'].'/accounts.json'),true);file_put_contents($dir.'/credentials.json',json_encode($accounts['runtime']));file_put_contents($dir.'/key.txt',bin2hex(random_bytes(32)));file_put_contents($dir.'/policy.json',json_encode($cfg));mkdir($dir.'/sessions');mkdir($dir.'/ipc');file_put_contents($dir.'/profile.json',json_encode(['mode'=>'HYBRID_ISOLATED_V1','private_acl_verified'=>true,'manifest'=>$m['directory'].'/manifest.json','timeout_ms'=>8000,'max_workers'=>2]));
$logins=[];foreach($f['users']as$key=>$id){$password=bin2hex(random_bytes(18));$p->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);$q=$p->prepare('SELECT username FROM users WHERE id=?');$q->execute([$id]);$logins[$key]=['username'=>$q->fetchColumn(),'password'=>$password];}
file_put_contents($dir.'/logins.json',json_encode($logins));echo 'Prepared physically verified disposable-only financial acceptance runtime; initial policy held.'.PHP_EOL;
