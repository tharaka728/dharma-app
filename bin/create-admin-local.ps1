param([Parameter(Mandatory=$true)][string]$Username,[Parameter(Mandatory=$true)][string]$DisplayName)
$ErrorActionPreference='Stop'
$config=Get-Content 'C:\mariadb-clean\ayurveda-phase1-local\private\runtime.json' -Raw | ConvertFrom-Json
if($config.host -ne '127.0.0.1' -or [string]$config.port -ne '3309' -or $config.name -ne 'dharma_sales_phase1'){throw 'Unexpected database target; no admin created'}
$first=Read-Host 'New admin password (12–72 bytes)' -AsSecureString
$second=Read-Host 'Confirm password' -AsSecureString
$one=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($first)
$two=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($second)
try {
 $password=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($one)
 if($password -cne [Runtime.InteropServices.Marshal]::PtrToStringBSTR($two)){throw 'Passwords do not match'}
 if([Text.Encoding]::UTF8.GetByteCount($password) -lt 12 -or [Text.Encoding]::UTF8.GetByteCount($password) -gt 72 -or $password.Contains("`n") -or $password.Contains("`r")){throw 'Password must be 12–72 bytes on one line'}
 if($Username -notmatch '^[a-zA-Z0-9_.-]{3,100}$' -or $DisplayName.Contains('"') -or $DisplayName.Contains("`n") -or $DisplayName.Contains("`r")){throw 'Invalid username or display name'}
 $start=[Diagnostics.ProcessStartInfo]::new('C:\xampp\php\php.exe')
 $start.UseShellExecute=$false
 $start.RedirectStandardInput=$true
 $start.Arguments='"'+(Join-Path $PSScriptRoot 'create-admin.php')+'" '+$Username+' "'+$DisplayName+'" --stdin-password'
 foreach($key in @('host','port','name','user','password')){$start.EnvironmentVariables['DB_'+$key.ToUpperInvariant()]=[string]$config.$key}
 $start.EnvironmentVariables['APP_ENV']='development'
 $process=[Diagnostics.Process]::Start($start)
 $passwordBytes=[Text.Encoding]::UTF8.GetBytes($password+"`n")
 try {
  $process.StandardInput.BaseStream.Write($passwordBytes,0,$passwordBytes.Length)
  $process.StandardInput.BaseStream.Flush();$process.StandardInput.BaseStream.Close()
 }finally{[Array]::Clear($passwordBytes,0,$passwordBytes.Length)}
 $process.WaitForExit()
 if($process.ExitCode){throw 'Admin creation failed; existing users are never overwritten'}
}finally{
 [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($one)
 [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($two)
 $password=$null;$first.Dispose();$second.Dispose()
}
