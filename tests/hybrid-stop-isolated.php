<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');
$pdo=HybridIsolationHarness::connection($m,'admin');
$pdo->exec('SHUTDOWN');
echo 'Stopped only the physically verified isolated test instance; all files and synthetic schemas retained.'.PHP_EOL;
