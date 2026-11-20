<#!
Review preparation only. Does not install/connect Tailscale, start Apache, open a
listener, change firewall policy or modify the existing Apache configuration.
Default: syntax-check a secret-free candidate using reserved example DNS.
PreparePrivateConfiguration is for use only after owner approval.
!#>
[CmdletBinding()]
param(
    [ValidatePattern('^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.[a-z0-9-]+\.ts\.net$')]
    [string]$MobileHost = 'dharma-review.example.ts.net',
    [switch]$PreparePrivateConfiguration
)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
$privateRoot='C:\mariadb-clean\ayurveda-phase1-local\private'
$apache='C:\xampp\apache\bin\httpd.exe'
if($PreparePrivateConfiguration -and $MobileHost -eq 'dharma-review.example.ts.net'){
    throw 'Supply the actual approved Tailscale DNS name; the example is not a mobile URL.'
}
$reviewRoot=Join-Path $projectRoot 'storage\mobile-access-review'
if(!(Test-Path -LiteralPath $reviewRoot)){New-Item -ItemType Directory -Path $reviewRoot | Out-Null}
$outputRoot=if($PreparePrivateConfiguration){$privateRoot}else{$reviewRoot}
$candidate=Join-Path $outputRoot 'httpd-mobile.conf'
$environmentFile=Join-Path $outputRoot 'mobile-runtime.conf'
if((Test-Path -LiteralPath $candidate) -or (Test-Path -LiteralPath $environmentFile)){
    throw 'Candidate already exists; preserved. Review it before deliberately preparing another candidate.'
}
$utf8=New-Object System.Text.UTF8Encoding($false)
if($PreparePrivateConfiguration){
    $runtime=Get-Content -LiteralPath (Join-Path $privateRoot 'runtime.json') -Raw | ConvertFrom-Json
    if($runtime.host -ne '127.0.0.1' -or [string]$runtime.port -ne '3309' -or $runtime.name -ne 'dharma_sales_phase1'){
        throw 'Runtime target mismatch; no configuration written.'
    }
    # Apache quoted arguments: escape backslashes/quotes; reject line injection.
    function Quote-Apache([string]$value){
        if($value -match '[\x00-\x1f\x7f]'){throw 'Invalid control character in private configuration.'}
        '"'+$value.Replace('\','\\').Replace('"','\"')+'"'
    }
    $environment=@('SetEnv APP_ENV development','SetEnv APP_BASE_PATH ""','SetEnv DB_HOST 127.0.0.1','SetEnv DB_PORT 3309','SetEnv DB_NAME dharma_sales_phase1',('SetEnv DB_USER '+(Quote-Apache $runtime.user)),('SetEnv DB_PASSWORD '+(Quote-Apache $runtime.password))) -join "`r`n"
}else{
    $environment="SetEnv APP_ENV development`r`nSetEnv APP_BASE_PATH `"`"`r`n# Syntax review only. No database credentials and never started."
}
$publicPath=(Join-Path $projectRoot 'public').Replace('\','/')
$privatePath=$privateRoot.Replace('\','/')
$envPath=$environmentFile.Replace('\','/')
$hostPattern=[regex]::Escape($MobileHost)
$candidateText=@"
# Separate, optional mobile gateway. Existing 8088/global XAMPP configuration unchanged.
ServerRoot "C:/xampp/apache"
Listen 127.0.0.1:8090
ServerName $MobileHost
PidFile "$privatePath/apache-mobile.pid"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule env_module modules/mod_env.so
LoadModule log_config_module modules/mod_log_config.so
LoadFile "C:/xampp/php/php8ts.dll"
LoadFile "C:/xampp/php/libpq.dll"
LoadFile "C:/xampp/php/libsqlite3.dll"
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "C:/xampp/php"
TypesConfig "C:/xampp/apache/conf/mime.types"
ErrorLog "$privatePath/apache-mobile-error.log"
LogLevel warn
LogFormat "%t %m %U %>s" mobile_safe
CustomLog "$privatePath/apache-mobile-access.log" mobile_safe
ServerTokens Prod
ServerSignature Off
TraceEnable Off
LimitRequestBody 1048576
DocumentRoot "$publicPath"
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$publicPath">
    AllowOverride None
    Options -Indexes -Includes -ExecCGI
    Require local
    DirectoryIndex index.php
    RewriteEngine On
    # Dedicated backend accepts only the reviewed HTTPS proxy host/protocol.
    RewriteCond %{HTTP_HOST} !^${hostPattern}$ [NC]
    RewriteRule ^ - [F,L]
    RewriteCond %{HTTP:X-Forwarded-Proto} !^https$ [NC]
    RewriteRule ^ - [F,L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</Directory>
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
# This dedicated loopback backend is exclusively for Tailscale HTTPS Serve.
# Do not add this setting to the existing HTTP 8088 listener.
SetEnv HTTPS on
Include "$envPath"
"@
[IO.File]::WriteAllText($environmentFile,$environment,$utf8)
[IO.File]::WriteAllText($candidate,$candidateText,$utf8)
# Never print private configuration/tool errors that might include secret lines.
$syntax=New-Object System.Diagnostics.Process
$syntax.StartInfo.FileName=$apache
$syntax.StartInfo.Arguments='-t -f "'+$candidate+'"'
$syntax.StartInfo.UseShellExecute=$false
$syntax.StartInfo.CreateNoWindow=$true
$syntax.StartInfo.RedirectStandardOutput=$true
$syntax.StartInfo.RedirectStandardError=$true
try {
    if(!$syntax.Start()){throw 'Cannot start syntax checker.'}
    $stdoutTask=$syntax.StandardOutput.ReadToEndAsync()
    $stderrTask=$syntax.StandardError.ReadToEndAsync()
    if(!$syntax.WaitForExit(15000)){
        $syntax.Kill()
        throw 'Syntax checker timed out; no server was started. Retry review with access to the Apache runtime.'
    }
    if($syntax.ExitCode -ne 0){throw 'Candidate syntax failed; private candidate files preserved for local inspection.'}
} finally {$syntax.Dispose()}
Write-Output 'Separate mobile Apache candidate: syntax passed. No server or network access started.'
Write-Output ('Candidate: '+$candidate)
if(!$PreparePrivateConfiguration){Write-Output 'Review-only example DNS and no database credentials. This candidate cannot run the application.'}
