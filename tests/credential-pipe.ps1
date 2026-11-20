$ErrorActionPreference='Stop'
$temporary=Join-Path ([IO.Path]::GetTempPath()) ('dharma-pipe-'+[Guid]::NewGuid().ToString('N')+'.php')
try {
 '<?php $value=rtrim((string)fgets(STDIN),"\r\n"); echo hash("sha256",$value);' | Set-Content -LiteralPath $temporary -Encoding ASCII
 $start=[Diagnostics.ProcessStartInfo]::new('C:\xampp\php\php.exe')
 $start.UseShellExecute=$false;$start.RedirectStandardInput=$true;$start.RedirectStandardOutput=$true
 $start.Arguments='-n "'+$temporary+'"'
 $process=[Diagnostics.Process]::Start($start)
 $testValue=[Guid]::NewGuid().ToString('N')+[char]0x0D85+[char]0x00E9
 $bytes=[Text.Encoding]::UTF8.GetBytes($testValue+"`n")
 $process.StandardInput.BaseStream.Write($bytes,0,$bytes.Length)
 $process.StandardInput.BaseStream.Flush();$process.StandardInput.BaseStream.Close()
 $received=$process.StandardOutput.ReadToEnd();$process.WaitForExit()
 $sha=[Security.Cryptography.SHA256]::Create()
 try{$expected=([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($testValue)))).Replace('-','').ToLowerInvariant()}finally{$sha.Dispose()}
 if($process.ExitCode -ne 0 -or $received -cne $expected){throw 'Private UTF-8 stdin transport failed'}
 'Hidden-input pipe transport check passed (ASCII and Unicode); no credential displayed or database accessed.'
}finally{
 if($bytes){[Array]::Clear($bytes,0,$bytes.Length)}
 $testValue=$null
 if(Test-Path -LiteralPath $temporary){Remove-Item -LiteralPath $temporary}
}
