<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-fixtures.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');$pdo=HybridIsolationHarness::connection($m);App\Services\HybridDatabaseScope::verify($pdo,$m);
$f=hybridFixtures($pdo,$m);file_put_contents($m['directory'].'/fixtures-stage3.json',json_encode($f,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'Appended synthetic Stage 3 fixtures to the verified isolated database. No migrations or resets.'.PHP_EOL;
