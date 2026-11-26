param([ValidateSet('Start','Stop')][string]$Action='Start')
$ErrorActionPreference='Stop'
$project=Split-Path $PSScriptRoot -Parent
Set-Location -LiteralPath $project
$php='C:\xampp\php\php.exe'
$run=[IO.File]::ReadAllText((Join-Path $project 'storage/stage2c-current.txt')).Trim()
$manifestPath=Join-Path $run 'manifest.json'
$manifest=Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
if($manifest.port -in @(3306,3309) -or $manifest.port -lt 1024){throw 'Forbidden database port.'}
$statePath=Join-Path $run 'owner-preview.json'
if($Action -eq 'Stop'){
 if(Test-Path -LiteralPath $statePath){
  $state=Get-Content -LiteralPath $statePath -Raw|ConvertFrom-Json
  $process=Get-CimInstance Win32_Process -Filter ('ProcessId='+$state.http_pid) -ErrorAction SilentlyContinue
  if($process){if($process.Name -ne 'php.exe' -or $process.CommandLine -notlike ('*'+$project+'*hybrid-ui-router.php*')){throw 'Preview process identity mismatch.'};Stop-Process -Id $state.http_pid}
 }
 & $php tests/hybrid-stage4-close.php $manifestPath
 if($LASTEXITCODE -ne 0){throw 'Could not close disposable preview controls.'}
 & $php tests/hybrid-stop-isolated.php $manifestPath
 if($LASTEXITCODE -ne 0){throw 'Could not stop verified disposable server.'}
 Write-Output 'Stopped preview HTTP and disposable database. Application source and operational controls unchanged.'
 exit
}
$httpPort=63825
foreach($port in @($httpPort,[int]$manifest.port)){if(@(Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue).Count){throw ('Port '+$port+' is already occupied; stop the existing preview first.')}}
$sessionDir=Join-Path $run ('owner-preview-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $sessionDir | Out-Null
New-Item -ItemType Directory -Path (Join-Path $sessionDir 'sessions') | Out-Null
$databaseProcess=$null;$httpProcess=$null
try{
 # Normal disposable MariaDB permits the authoritative preview's FOR UPDATE locks.
 # Safety is the two default-deny HTTP/CLI bridge boundaries, not server read-only mode.
 $databaseProcess=Start-Process C:\xampp\mysql\bin\mysqld.exe -ArgumentList ('--defaults-file="'+(Join-Path $run 'server.ini')+'"','--console') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $sessionDir 'db-output.log') -RedirectStandardError (Join-Path $sessionDir 'db-errors.log')
 Start-Sleep -Milliseconds 800
 @'
<?php
require 'tests/hybrid-isolation.php';
$m=HybridIsolationHarness::manifest(trim(file_get_contents('storage/stage2c-current.txt')).'/manifest.json');$p=HybridIsolationHarness::connection($m);App\Services\HybridDatabaseScope::verify($p,$m);
if((int)$p->query('SELECT @@global.read_only')->fetchColumn()!==0)throw new RuntimeException('Disposable preview locks require normal server mode.');
$config=require 'config/hybrid-promotions.php';if($config['enabled']!==false||$config['financial_posting_hold']!==true)throw new RuntimeException('Source gates/holds must remain unchanged.');
$f=json_decode(file_get_contents($m['directory'].'/fixtures-stage3.json'),true,512,JSON_THROW_ON_ERROR);$admin=HybridIsolationHarness::connection($m,'admin');$admin->beginTransaction();
try{$before=$admin->query('SELECT * FROM hybrid_operating_state WHERE id=1 FOR UPDATE')->fetch();$admin->exec('UPDATE hybrid_operating_state SET enabled=1,sale_posting=0,return_posting=0,custody=0,version=version+1 WHERE id=1');$after=$admin->query('SELECT * FROM hybrid_operating_state WHERE id=1')->fetch();$admin->prepare('INSERT INTO hybrid_control_events(actor_user_id,previous_version,new_version,reason,previous_json,new_json) VALUES (?,?,?,?,?,?)')->execute([$f['users']['admin'],$before['version'],$after['version'],'Owner synthetic preview only; default-deny demo boundary; all posting disabled',json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);$admin->commit();}catch(Throwable $e){$admin->rollBack();throw $e;}
echo 'Verified existing disposable fixture at 127.0.0.1:'.$m['port'].'; synthetic posting/return/custody disabled.'.PHP_EOL;
'@ | & $php
 if($LASTEXITCODE -ne 0){throw 'Disposable preview preflight failed.'}
 $env:HYBRID_UI_MANIFEST=$manifestPath
 $env:HYBRID_UI_TEST_KEY=[guid]::NewGuid().ToString('N')
 $env:HYBRID_UI_PREVIEW_ONLY='1'
 $env:HYBRID_UI_STAGE4='0';$env:HYBRID_UI_STAGE5='0';$env:HYBRID_UI_STAGE6A='0'
 $arguments=@('-d',('session.save_path="'+(Join-Path $sessionDir 'sessions')+'"'),'-d','session.use_only_cookies=1','-S',('127.0.0.1:'+$httpPort),'-t',('"'+$project+'\public"'),('"'+$project+'\tests\hybrid-ui-router.php"'))
 $httpProcess=Start-Process $php -ArgumentList $arguments -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $sessionDir 'http-output.log') -RedirectStandardError (Join-Path $sessionDir 'http-errors.log')
 $state=@{url=('http://127.0.0.1:'+$httpPort+'/');http_pid=$httpProcess.Id;database_port=$manifest.port;session_directory=$sessionDir}
 [IO.File]::WriteAllText($statePath,($state|ConvertTo-Json),(New-Object Text.UTF8Encoding($false)))
 Write-Output ('OPEN '+$state.url)
 Write-Output 'Synthetic representative only. Hybrid selection/eligibility/preview permitted; every other application route denied.'
}catch{
 if($httpProcess -and -not $httpProcess.HasExited){Stop-Process -Id $httpProcess.Id}
 if($databaseProcess -and -not $databaseProcess.HasExited){Stop-Process -Id $databaseProcess.Id}
 throw
}
