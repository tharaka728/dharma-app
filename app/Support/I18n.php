<?php
declare(strict_types=1);
namespace App\Support;
/** Display-only localization. Never use translated strings as business identifiers or signed values. */
final class I18n
{
    private static ?string $locale=null;
    private static ?array $catalogue=null;
    private static ?array $patterns=null;
    public static function locale():string{return self::$locale??self::valid($_COOKIE['dharma_language']??null)??'si-LK';}
    public static function valid(mixed $locale):?string{return is_string($locale)&&in_array($locale,['si-LK','en'],true)?$locale:null;}
    public static function set(string $locale):void{self::$locale=self::valid($locale)??'si-LK';}
    public static function dictionary():array{return self::$catalogue??=json_decode(file_get_contents(APP_ROOT.'/public/assets/i18n/si-LK.json'),true,512,JSON_THROW_ON_ERROR);}
    public static function text(string $key,array $values=[]):string
    {
        $text=$key;
        if(self::locale()!=='en'){
            $dictionary=self::dictionary();$returnMessages=[
                'Confirm receipt, eligibility and unsaleable condition.'=>'භාණ්ඩ භාරගත් බව, ප්‍රතිපත්තියට සුදුසු බව සහ නැවත විකිණීමට නුසුදුසු බව තහවුරු කරන්න.',
                'An approved historical price is missing or disabled.'=>'අනුමත පැරණි මිලක් නොමැත හෝ අක්‍රීය කර ඇත.',
                'Return intent is unavailable for this customer.'=>'මෙම පාරිභෝගිකයා සඳහා ආපසු භාරගැනීම ලබාගත නොහැක.',
                'This return intent expired. Start a new return.'=>'මෙම ආපසු භාරගැනීම කල් ඉකුත් වී ඇත. නව එකක් ආරම්භ කරන්න.',
                'This return is already finalized. Recover its linked invoice.'=>'මෙම ආපසු භාරගැනීම දැනටමත් සම්පූර්ණයි. අදාළ බිල්පත නැවත ලබාගන්න.',
                'This return identity has already been used with different details.'=>'මෙම ආපසු භාරගැනීමේ යොමුව වෙනත් විස්තර සමඟ දැනටමත් භාවිතා කර ඇත.',
                'Return intent unavailable.'=>'ආපසු භාරගැනීමේ යොමුව ලබාගත නොහැක.',
            ];$text=$dictionary[$key]??$returnMessages[$key]??$dictionary[preg_replace('/\s+/u',' ',trim($key))]??$key;
            if($text===$key&&!isset($dictionary[$key])&&!$values){
                if(self::$patterns===null){self::$patterns=[];foreach($dictionary as $pattern=>$translation)if(preg_match_all('/\{([0-9]+)\}/',$pattern,$names)){$quoted=preg_quote($pattern,'~');$regex=preg_replace('/\\\\\{[0-9]+\\\\\}/','(.+?)',$quoted);self::$patterns[]=[$regex,$names[1],$translation];}}
                foreach(self::$patterns as [$regex,$names,$translation])if(preg_match('~^'.$regex.'$~uD',$key,$matches)){foreach($names as $i=>$name)$translation=str_replace('{'.$name.'}',$matches[$i+1],$translation);$text=$translation;break;}
            }
        }
        foreach($values as $name=>$value)$text=str_replace('{'.$name.'}',(string)$value,$text);
        return $text;
    }
    public static function html(string $key,array $values=[]):string{return Http::escape(self::text($key,$values));}
    public static function product(array $product):string
    {
        $english=(string)($product['product_name_snapshot']??$product['product_name']??$product['name']??'');
        $sinhala=(string)($product['name_si']??$product['product_name_si']??'');
        $first=self::locale()==='si-LK'?$sinhala:$english;$second=self::locale()==='si-LK'?$english:$sinhala;
        return trim($first)!==''?$first:(trim($second)!==''?$second:'');
    }
    public static function with(string $locale,callable $fn):mixed{$before=self::$locale;self::set($locale);try{return $fn();}finally{self::$locale=$before;}}
    public static function messages(array $body):array
    {
        // Only explicitly identified presentation fields are translated. API data, enums and keys stay stable.
        if(isset($body['errors'])&&is_array($body['errors']))foreach($body['errors'] as &$message)if(is_string($message))$message=self::text($message);unset($message);
        if(isset($body['error']['message'])&&is_string($body['error']['message']))$body['error']['message']=self::text($body['error']['message']);
        if(isset($body['message'])&&is_string($body['message']))$body['message']=self::text($body['message']);
        return $body;
    }
}
