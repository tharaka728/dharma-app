<?php
declare(strict_types=1);
namespace App\Support;
use RuntimeException;

/** Bounded private-file IPC; financial ambiguity always requires the same command UUID. */
final class HybridWorkerClient
{
    public static function route(string $path,string $method,array $payload):array
    {
        $return=\App\Controllers\HybridReturnController::route($path);$route=$return??\App\Controllers\InvoiceController::route($path);
        $allowed=$return?['hybrid-return-page','hybrid-return-options','hybrid-return-details','hybrid-return-custody','hybrid-return-preview','hybrid-return-post','hybrid-return-receive','hybrid-return-supplement','hybrid-return-manage']:['hybrid-create','hybrid-eligibility','options','preview','index','show'];
        if(!$route||!in_array($method,$route['methods'],true)||!in_array($route['action'],$allowed,true))throw new RuntimeException('ROUTE_DENIED');
        if(!$return&&$method==='POST'&&($payload['hybrid']['policy_version']??null)!=='HYBRID_PROMOTION_V1')throw new RuntimeException('ONLINE_HYBRID_REQUIRED');
        return $route;
    }
    public static function call(array $profile,array $context):array
    {
        $slot=null;$process=null;$id=bin2hex(random_bytes(16));$dir=$profile['directory'].'/ipc';
        for($n=0;$n<$profile['max_workers'];$n++){$handle=fopen($dir.'/slot-'.$n.'.lock','c');if($handle&&flock($handle,LOCK_EX|LOCK_NB)){$slot=$handle;break;}if($handle)fclose($handle);}
        if(!$slot)throw new RuntimeException('WORKER_BUSY');
        $request=$dir.'/'.$id.'.request.json';$response=$dir.'/'.$id.'.response.json';$temporary=$dir.'/'.$id.'.tmp';
        try{
            $context+=['time'=>time(),'job'=>$id];$json=json_encode($context,JSON_THROW_ON_ERROR);
            if(strlen($json)>262144)throw new RuntimeException('REQUEST_TOO_LARGE');
            file_put_contents($request,json_encode(['context'=>$json,'mac'=>hash_hmac('sha256',$json,$profile['key'])],JSON_THROW_ON_ERROR),LOCK_EX);chmod($request,0600);
            $null=PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null';
            $pipes=[];$process=proc_open([PHP_BINARY,'-d','max_execution_time=15',APP_ROOT.'/bin/hybrid-worker.php',$profile['file'],$id],[0=>['file',$null,'r'],1=>['file',$null,'w'],2=>['file',$profile['directory'].'/worker-error.log','a']],$pipes,APP_ROOT,null,['bypass_shell'=>true,'create_new_console'=>false]);
            if(!is_resource($process))throw new RuntimeException('WORKER_UNAVAILABLE');$start=microtime(true);
            do{$status=proc_get_status($process);if(!$status['running'])break;if((microtime(true)-$start)*1000>$profile['timeout_ms']){proc_terminate($process);throw new RuntimeException('OUTCOME_UNKNOWN');}usleep(20000);}while(true);
            if(!is_file($response)||filesize($response)>2097152)throw new RuntimeException('OUTCOME_UNKNOWN');
            $result=json_decode(file_get_contents($response),true,32,JSON_THROW_ON_ERROR);
            if(!is_int($result['status']??null)||!is_string($result['body']??null)||!in_array($result['type']??null,['application/json','text/html; charset=utf-8'],true))throw new RuntimeException('OUTCOME_UNKNOWN');
            return $result;
        }finally{
            if(is_resource($process)){$status=proc_get_status($process);if($status['running'])proc_terminate($process);proc_close($process);}
            foreach([$request,$response,$temporary]as$file)if(is_file($file))unlink($file);
            flock($slot,LOCK_UN);fclose($slot);
        }
    }
}
