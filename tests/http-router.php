<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli-server')exit(1);
$root=realpath(dirname(__DIR__).'/public');$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');$file=realpath($root.'/'.$path);
if($file!==false&&strpos($file,$root.DIRECTORY_SEPARATOR)===0&&is_file($file))return false;
require $root.'/index.php';
