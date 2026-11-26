<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');$directory=trim(file_get_contents($m['directory'].'/stage5-documents-current.txt'));$real=realpath($directory);if(!$real||dirname($real)!==realpath($m['directory'])||strpos(basename($real),'stage5-documents-')!==0)throw new RuntimeException('Explicit synthetic document directory required.');
$index=json_decode(file_get_contents($real.'/index.json'),true,512,JSON_THROW_ON_ERROR);$count=0;
foreach($index as$case){if(!preg_match('/^(invoice|return|void)-(en|si-LK)-(58|80|a4)$/D',$case['name']))throw new RuntimeException('Invalid document case.');$html=file_get_contents($real.'/'.$case['name'].'.html');$pdf=App\Support\LocalPdf::generate($html);file_put_contents($real.'/'.$case['name'].'.pdf',$pdf);$count++;echo 'PASS local PDF '.$case['name'].' ('.strlen($pdf).' bytes)'.PHP_EOL;}
echo 'Stage 5 local PDF: '.$count.' documents generated through application LocalPdf; no remote service.'.PHP_EOL;
