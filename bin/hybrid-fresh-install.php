<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/isolated-bootstrap.php';
try{
    $args=getopt('',['new-isolated','manifest:','contract-sha256:']);
    if(!array_key_exists('new-isolated',$args)||($args['contract-sha256']??null)!==App\Support\HybridFreshInstaller::CONTRACT_SHA256)throw new RuntimeException('Explicit --new-isolated, --manifest and --contract-sha256 are required.');
    $m=App\Support\HybridIsolatedRuntime::manifest($args['manifest']??'');
    $accounts=json_decode(file_get_contents($m['directory'].'/accounts.json'),true,16,JSON_THROW_ON_ERROR);
    $p=App\Support\HybridIsolatedRuntime::connect($m,$accounts['migration'],false);
    App\Support\HybridFreshInstaller::bindScope($m);App\Support\HybridFreshInstaller::run($p,'027',null,true);
    echo "Explicit fresh install completed: original 001-027 plus separately journaled compatibility preparation.\n";
}catch(Throwable$e){fwrite(STDERR,'Fresh installation refused: '.$e->getMessage().PHP_EOL);exit(1);}
