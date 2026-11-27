<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');$accounts=json_decode(file_get_contents($m['directory'].'/accounts.json'),true);$checks=0;
$reject=static function(callable$fn)use(&$checks):void{try{$fn();}catch(Throwable$e){$checks++;return;}throw new RuntimeException('Unsafe runtime input accepted.');};
foreach(['/api/v1/rep/invoices/00000000-0000-4000-8000-000000000000/payment','/api/v1/admin/inventory','/api/v1/rep/offline/sync','/index.php','/test/session']as$path)$reject(static fn()=>App\Support\HybridWorkerClient::route($path,'POST',[]));
$reject(static fn()=>App\Support\HybridWorkerClient::route('/api/v1/rep/invoices','POST',['items'=>[]]));
$reject(static fn()=>App\Support\HybridIsolatedRuntime::connect($m,$accounts['migration'],true));
$p=App\Support\HybridIsolatedRuntime::connect($m,$accounts['runtime']);App\Services\HybridDatabaseScope::verify($p,$m);$checks++;
foreach([3306,3309]as$port){$id='stage2c-'.bin2hex(random_bytes(16));$dir=APP_ROOT.'/storage/'.$id;mkdir($dir);mkdir($dir.'/data');$fake=$m;$fake['run_id']=$id;$fake['directory']=$dir;$fake['datadir']=$dir.'/data';$fake['port']=$port;file_put_contents($dir.'/manifest.json',json_encode($fake));$reject(static fn()=>App\Support\HybridIsolatedRuntime::manifest($dir.'/manifest.json'));}
$journal=$p->query('SELECT * FROM schema_migrations ORDER BY version')->fetchAll();
$pipes=[];$process=proc_open([PHP_BINARY,APP_ROOT.'/bin/hybrid-fresh-install.php','--new-isolated','--manifest='.$m['directory'].'/manifest.json','--contract-sha256='.App\Support\HybridFreshInstaller::CONTRACT_SHA256],[0=>['file','NUL','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true,'create_new_console'=>false]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
if($exit!==1||!str_contains($error,'not empty')||$journal!==$p->query('SELECT * FROM schema_migrations ORDER BY version')->fetchAll())throw new RuntimeException('Fresh command did not preserve an existing installation.');$checks++;
file_put_contents($m['directory'].'/stage6c-runtime-safety.json',json_encode(['checks'=>$checks,'operational_endpoints_rejected_before_connection'=>[3306,3309],'normal_migrator_unchanged'=>true,'existing_install_journal_preserved'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo "Stage 6C runtime safety: $checks checks passed.\n";
