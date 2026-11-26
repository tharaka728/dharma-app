<?php
declare(strict_types=1);
// Selected isolated entry points never load .env or the default database factory.
if(!defined('APP_ROOT')){
    define('APP_ROOT',dirname(__DIR__));date_default_timezone_set('UTC');ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');
    spl_autoload_register(static function(string $class):void{if(str_starts_with($class,'App\\')){$file=APP_ROOT.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require $file;}});
}
