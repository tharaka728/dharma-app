<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/';$root=dirname(__DIR__);
if(str_starts_with($path,'/assets/')){
    $file=realpath($root.'/public'.$path);$assets=realpath($root.'/public/assets');
    if(in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)&&$file&&str_starts_with($file,$assets.DIRECTORY_SEPARATOR)&&is_file($file)&&in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),['js','json','css','woff','woff2','svg','png','ico'],true))return false;
    http_response_code(403);exit;
}
require $root.'/public/hybrid-isolated.php';
