<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php'; require __DIR__.'/hybrid-fixtures.php';
try {
    $m=HybridIsolationHarness::manifest($argv[1]??''); $request=realpath($argv[2]??'');
    if (!$request || dirname($request)!==realpath($m['directory']) || !preg_match('/^concurrent-[a-f0-9]+\.json$/D',basename($request))) { throw new RuntimeException('Isolated worker request path rejected.'); }
    $pdo=HybridIsolationHarness::connection($m); $f=json_decode(file_get_contents($m['directory'].'/fixtures.json'),true,512,JSON_THROW_ON_ERROR);
    $input=json_decode(file_get_contents($request),true,512,JSON_THROW_ON_ERROR);
    file_put_contents($request.'.ready.'.getmypid(),'ready');
    $result=hybridTestService($pdo,$m,$f)->issue(['id'=>$f['users']['rep1']],$input);
    echo json_encode(['ok'=>true,'result'=>$result],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'class'=>get_class($e),'message'=>$e->getMessage()],JSON_THROW_ON_ERROR).PHP_EOL; exit(2);
}
