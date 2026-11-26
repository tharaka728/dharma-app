param([Parameter(Mandatory=$true)][string]$InputPdf)
$ErrorActionPreference='Stop'
$target=(Resolve-Path -LiteralPath $InputPdf).Path
$workspace=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\storage')).Path
if (-not $target.StartsWith($workspace+'\',[StringComparison]::OrdinalIgnoreCase) -or [IO.Path]::GetExtension($target) -ne '.pdf') { throw 'Only isolated storage PDF artifacts may be rendered.' }
Add-Type -AssemblyName System.Runtime.WindowsRuntime
$null=[Windows.Storage.StorageFile,Windows.Storage,ContentType=WindowsRuntime]
$null=[Windows.Data.Pdf.PdfDocument,Windows.Data.Pdf,ContentType=WindowsRuntime]
$null=[Windows.Storage.Streams.InMemoryRandomAccessStream,Windows.Storage.Streams,ContentType=WindowsRuntime]
$null=[Windows.Storage.Streams.DataReader,Windows.Storage.Streams,ContentType=WindowsRuntime]
function Await-Pdf($Operation,[Type]$ResultType) {
 $method=[System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { $_.Name -eq 'AsTask' -and $_.IsGenericMethod -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' } | Select-Object -First 1
 $task=$method.MakeGenericMethod($ResultType).Invoke($null,@($Operation));$task.GetAwaiter().GetResult()
}
function Await-PdfAction($Operation) {
 $method=[System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { $_.Name -eq 'AsTask' -and -not $_.IsGenericMethod -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncAction' } | Select-Object -First 1
 $task=$method.Invoke($null,@($Operation));$task.GetAwaiter().GetResult()
}
$file=Await-Pdf ([Windows.Storage.StorageFile]::GetFileFromPathAsync($target)) ([Windows.Storage.StorageFile])
$document=Await-Pdf ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($file)) ([Windows.Data.Pdf.PdfDocument])
$pages=@()
for($i=0;$i -lt $document.PageCount;$i++) {
 $page=$document.GetPage($i);$stream=New-Object Windows.Storage.Streams.InMemoryRandomAccessStream
 try {
  $null=Await-PdfAction ($page.RenderToStreamAsync($stream))
  $reader=New-Object Windows.Storage.Streams.DataReader($stream.GetInputStreamAt(0))
  try { $null=Await-Pdf ($reader.LoadAsync([uint32]$stream.Size)) ([uint32]);$bytes=New-Object byte[] ([int]$stream.Size);$reader.ReadBytes($bytes);$out=[IO.Path]::ChangeExtension($target,('page-'+($i+1)+'.png'));[IO.File]::WriteAllBytes($out,$bytes) } finally { $reader.Dispose() }
  $pages+=@{page=$i+1;width=$page.Size.Width;height=$page.Size.Height;png=$out}
 } finally {$stream.Dispose();$page.Dispose()}
}
@{pdf=$target;page_count=$document.PageCount;pages=$pages} | ConvertTo-Json -Depth 4
