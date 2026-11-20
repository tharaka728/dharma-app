<?php
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$dictionary=json_decode(file_get_contents($root.'/public/assets/i18n/si-LK.json'),true);$keys=[];
foreach(glob($root.'/app/Views/*.php') as $file){$tokens=token_get_all(file_get_contents($file));$output='';$echo=false;$previous='';
 foreach($tokens as $token){$text=is_array($token)?$token[1]:$token;if(is_array($token)&&$token[0]===T_OPEN_TAG_WITH_ECHO){$echo=true;$previous='';}elseif(is_array($token)&&$token[0]===T_CLOSE_TAG)$echo=false;
  if($echo&&is_array($token)&&$token[0]===T_CONSTANT_ENCAPSED_STRING&&in_array($previous,['?',':','??'],true)){$key=substr($text,1,-1);if(isset($dictionary[$key])&&!preg_match('/[<>="\']/',str_replace("\\'",'', $key))){$text='\\App\\Support\\I18n::html('.$text.')';$keys[$key]=true;}}
  $output.=$text;if(!(is_array($token)&&in_array($token[0],[T_WHITESPACE,T_COMMENT],true)))$previous=$text;
 }
 $output=preg_replace('/(?:Http::escape|\$e|\$escape)\((Reports::TYPES\[\$type\]|Customers::TYPES\[\$(?:row|customer)\[\x27customer_type\x27\]\]|\$titles\[\$action\]|\$label)\)/','\\App\\Support\\I18n::html($1)',$output);
 $output=str_replace('<?= $titles[$action] ?>','<?= \\App\\Support\\I18n::html($titles[$action]) ?>',$output);
 $output=str_replace('$e(strtolower($label))','\\App\\Support\\I18n::html($label)',$output);
 foreach(['payment_type','payment_status','validity'] as $field)$output=str_replace('$e($record[\''.$field.'\'])','\\App\\Support\\I18n::html($record[\''.$field.'\'])',$output);
 $output=str_replace('$e($s)','\\App\\Support\\I18n::html($s)',$output);
 if(basename($file)!=='localization-head.php'&&basename($file)!=='invoice-document.php'&&str_contains($output,"require APP_ROOT.'/app/Views/localization-head.php'")){$output=str_replace("<?php require APP_ROOT.'/app/Views/localization-head.php'; ?>",'',$output);$output=str_replace('<head>',"<head><?php require APP_ROOT.'/app/Views/localization-head.php'; ?>",$output);}
 file_put_contents($file,$output);
}
echo count($keys)." output-only labels localized.\n";
