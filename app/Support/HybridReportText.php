<?php
declare(strict_types=1);
namespace App\Support;
/** Scoped Hybrid presentation catalogue: report labels and server-only UI fallback text. */
final class HybridReportText
{
    private static ?array $dictionary=null;
    public static function text(string $key):string
    {
        if(I18n::locale()!=='si-LK')return I18n::text($key);
        self::$dictionary??=json_decode(file_get_contents(APP_ROOT.'/public/assets/i18n/hybrid-si-LK.json'),true,512,JSON_THROW_ON_ERROR);
        return self::$dictionary[$key]??I18n::text($key);
    }
    public static function html(string $key):string{return Http::escape(self::text($key));}
}
