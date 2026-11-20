<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$keys=[];
foreach(glob($root.'/app/Views/*.php') as $file){if(in_array(basename($file),['language-switcher.php','localization-head.php'],true))continue;$tokens=token_get_all(file_get_contents($file));$out='';$inTag=false;$quote=null;$skipTag=false;
 foreach($tokens as $token){if(!is_array($token)){$out.=$token;continue;}if($token[0]!==T_INLINE_HTML){$out.=$token[1];continue;}$html=$token[1];$result='';$text='';$flush=static function()use(&$text,&$result,&$keys){$trim=trim(html_entity_decode($text,ENT_QUOTES|ENT_HTML5,'UTF-8'));if(preg_match('/[A-Za-z]/',$trim)&&!in_array($trim,['Dharma Chemical','DHARMA CHEMICAL'],true)){$keys[$trim]=true;preg_match('/^(\s*)(.*?)(\s*)$/su',$text,$m);$result.=$m[1].'<?= \\App\\Support\\I18n::html('.var_export($trim,true).') ?>'.$m[3];}else $result.=$text;$text='';};
  // Translate complete literal accessible attributes, leaving values, URLs, form names and technical attributes intact.
  $html=preg_replace_callback('/\b(placeholder|aria-label|title|alt)="([^"<>]+)"/u',static function($m)use(&$keys){$key=html_entity_decode($m[2],ENT_QUOTES|ENT_HTML5,'UTF-8');if(!preg_match('/[A-Za-z]/',$key))return $m[0];$keys[$key]=true;return $m[1].'="<?= \\App\\Support\\I18n::html('.var_export($key,true).') ?>"';},$html);
  // PHP inserted into attributes is kept intact while scanning HTML structure.
  $chunks=preg_split('/(<\?=.*?\?>)/s',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
  foreach($chunks as $chunk){if(str_starts_with($chunk,'<?=')){$result.=$chunk;continue;}for($i=0,$n=strlen($chunk);$i<$n;$i++){$c=$chunk[$i];if($inTag){$result.=$c;if($quote!==null){if($c===$quote)$quote=null;}elseif($c==='"'||$c==="'")$quote=$c;elseif($c==='>')$inTag=false;}elseif($c==='<'){$flush();$inTag=true;$result.=$c;}else $text.=$c;}}$flush();$out.=$result;
 }
 // Translate only known display variables. Business data expressions stay untouched.
 $out=preg_replace('/(?:Http::escape|\$e|\$workspaceEscape)\(\$(label|title|notice|error|heading|hint)\)/','\\App\\Support\\I18n::html($$1)',$out);
 $out=preg_replace('/<\?=\s*\$(label|title|notice|error)\s*\?>/','<?= \\App\\Support\\I18n::html($$1) ?>',$out);
 $out=str_replace('<html lang="en">','<html lang="<?= \\App\\Support\\I18n::locale() ?>">',$out);
 if(str_contains($out,'</head>')&&basename($file)!=='invoice-document.php')$out=str_replace('</head>',"<?php require APP_ROOT.'/app/Views/localization-head.php'; ?></head>",$out);
 file_put_contents($file,$out);
}
file_put_contents($root.'/storage/localization-view-keys.json',json_encode(array_keys($keys),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo count($keys)." static view messages centralized.\n";
