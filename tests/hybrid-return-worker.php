<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-fixtures.php';
try{
 $m=HybridIsolationHarness::manifest($argv[1]??'');$path=realpath($argv[2]??'');if(!$path||dirname($path)!==realpath($m['directory'])||!preg_match('/^return-race-[a-f0-9]+\.json$/D',basename($path)))throw new RuntimeException('Invalid isolated worker input.');
 $pdo=HybridIsolationHarness::connection($m);$job=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$cfg=hybridTestConfig($m,$job['fixtures']);$cfg['return_posting']=$cfg['custody']=true;file_put_contents($path.'.ready','ready');
 $out=(new App\Services\HybridReturns($pdo,$cfg,$m,false))->post(['id'=>$job['fixtures']['users']['rep1']],$job['request']);echo json_encode(['ok'=>true,'result'=>$out],JSON_THROW_ON_ERROR);
}catch(Throwable$e){echo json_encode(['ok'=>false,'class'=>get_class($e),'message'=>$e->getMessage()],JSON_THROW_ON_ERROR);exit(2);}
