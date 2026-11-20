<?php
declare(strict_types=1);
// Read-only public HTTP/asset checks; no account or business fixtures.
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$count=0;
$check=static function(bool $ok,string $label)use(&$count):void{if(!$ok)throw new RuntimeException($label);$count++;};
$manifest=json_decode(file_get_contents($root.'/public/manifest.webmanifest'),true,512,JSON_THROW_ON_ERROR);
$check($manifest['id']==='./dharma-offline','Existing installed application identity preserved');
$check($manifest['start_url']==='./'&&$manifest['scope']==='./','Role-neutral launch within existing scope');
$check($manifest['display']==='standalone'&&$manifest['name']==='Dharma Chemical Sales','App name and standalone mode');
foreach([192,512] as $size){
    $image=getimagesize($root.'/public/assets/icons/app-'.$size.'.png');
    $check($image[0]===$size&&$image[1]===$size,'Actual PNG dimensions '.$size);
    $icons=array_filter($manifest['icons'],fn($icon)=>$icon['sizes']===$size.'x'.$size&&str_contains($icon['purpose'],'maskable'));
    $check(count($icons)===1,'Maskable manifest entry '.$size);
}
$login=file_get_contents('http://127.0.0.1:8088/login');
$check(str_contains($login,'name="viewport"'),'Login viewport');
$check(str_contains($login,'rel="manifest"')&&str_contains($login,'/manifest.webmanifest'),'Login manifest discovery');
$check(str_contains($login,'rel="apple-touch-icon"')&&str_contains($login,'apple-mobile-web-app-title'),'Apple installation metadata');
$check(str_contains($login,'/assets/js/pwa-install.js'),'Login registers existing worker');
$check(!str_contains($login,'Billing and offline sync are not yet available'),'Stale phase claim removed');
foreach(['/manifest.webmanifest','/assets/icons/app-192.png','/assets/icons/app-512.png','/assets/js/pwa-install.js','/sw.js','/offline/index.html','/assets/css/pos.css?v=pos-v1','/assets/js/pos.js?v=pos-v1','/assets/js/offline-app.js?v=pos-v1'] as $path){
    $content=file_get_contents('http://127.0.0.1:8088'.$path);$check(is_string($content)&&$content!=='','Public installation asset reachable '.$path);
}
$shell=file_get_contents($root.'/public/offline/index.html');
$check(str_contains($shell,'apple-mobile-web-app-capable')&&str_contains($shell,'apple-touch-icon'),'Offline Apple metadata');
$worker=file_get_contents($root.'/public/sw.js');
$check(str_contains($worker,"const VERSION = 'dharma-static-pos-v1'")&&!str_contains($worker,'skipWaiting()'),'Waiting update preserves active tabs');
$check(!str_contains($worker,'indexedDB.deleteDatabase'),'Worker does not clear offline vault');
echo "$count public PWA installation metadata, identity, launch and asset checks passed. HTTPS and physical installation require the approved endpoint/device.\n";
