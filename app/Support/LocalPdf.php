<?php
declare(strict_types=1);
namespace App\Support;
final class LocalPdf
{
    public static function generate(string $html):string
    {
        $node='';foreach(['C:/nvm4w/nodejs/node.exe','C:/Program Files/nodejs/node.exe']as$candidate)if(is_file($candidate)){$node=$candidate;break;}
        if(!is_file($node)||strlen($html)>2097152)throw new \RuntimeException('Local PDF renderer unavailable.');
        // File descriptors avoid pipe-buffer deadlocks. Anonymous temporary streams
        // are outside public/, disappear on close, and never receive predictable names.
        $in=tmpfile();$out=tmpfile();$err=tmpfile();$process=null;
        $gate=fopen(APP_ROOT.'/storage/invoice-pdf.lock','c');
        try{
            if(!$in||!$out||!$err||!$gate||!flock($gate,LOCK_EX|LOCK_NB))throw new \RuntimeException('PDF renderer busy or unavailable.');
            fwrite($in,json_encode(['html'=>$html],JSON_THROW_ON_ERROR));rewind($in);
            $environment=[];foreach(['SystemRoot','WINDIR','TEMP','TMP','USERPROFILE','LOCALAPPDATA','PROGRAMFILES','PROGRAMFILES(X86)'] as $key){$value=getenv($key);if($value!==false)$environment[$key]=$value;}
            $pipes=[];$process=proc_open([$node,APP_ROOT.'/bin/render-invoice-pdf.js'],[0=>$in,1=>$out,2=>$err],$pipes,APP_ROOT,$environment,['bypass_shell'=>true,'create_no_window'=>true]);
            if(!is_resource($process))throw new \RuntimeException('Cannot start local PDF renderer.');
            $until=microtime(true)+45;
            do{$status=proc_get_status($process);if(!$status['running'])break;usleep(100000);}while(microtime(true)<$until);
            if($status['running']){proc_terminate($process);throw new \RuntimeException('PDF renderer timed out.');}
            proc_close($process);$process=null;rewind($out);$bytes=stream_get_contents($out,20971521);
            if($status['exitcode']!==0||strlen($bytes)>20971520||substr($bytes,0,5)!=='%PDF-'||strpos(substr($bytes,-1024),'%%EOF')===false)throw new \RuntimeException('Invalid PDF output.');
            return $bytes;
        }finally{if(is_resource($process)){proc_terminate($process);proc_close($process);}foreach([$in,$out,$err,$gate] as $stream)if(is_resource($stream))fclose($stream);}
    }
}
