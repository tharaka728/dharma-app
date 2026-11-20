<?php
if(PHP_SAPI!=='cli')exit(1);
$count=0;foreach(glob(dirname(__DIR__).'/app/Views/*.php') as $file){$tokens=token_get_all(file_get_contents($file));$out='';$inTag=false;$quote=null;$tag='';$echo=false;$protected=false;$expr='';
 foreach($tokens as $token){$text=is_array($token)?$token[1]:$token;
  if(is_array($token)&&$token[0]===T_OPEN_TAG_WITH_ECHO){$echo=true;$expr=$text;$attr='';if(preg_match('/([\w:-]+)\s*=\s*[\x22\x27][^\x22\x27]*$/s',$tag,$m))$attr=$m[1];$protected=$inTag&&$quote!==null&&!in_array($attr,['placeholder','aria-label','title','alt'],true);continue;}
  if($echo){$expr.=$text;if(is_array($token)&&$token[0]===T_CLOSE_TAG){if($protected)$expr=preg_replace('/\\\\App\\\\Support\\\\I18n::html\((\x27[^\x27]*\x27)\)/','$1',$expr,-1,$n);else $n=0;$count+=$n;$out.=$expr;$echo=false;}continue;}
  $out.=$text;if(is_array($token)&&$token[0]===T_INLINE_HTML)for($i=0,$n=strlen($text);$i<$n;$i++){$c=$text[$i];if(!$inTag){if($c==='<'){$inTag=true;$tag='<';}}else{$tag.=$c;if($quote!==null){if($c===$quote)$quote=null;}elseif($c==='"'||$c==="'")$quote=$c;elseif($c==='>'){$inTag=false;$tag='';}}}
 }
 file_put_contents($file,$out);
}echo "$count technical attribute literals preserved.\n";
