<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Support\{InventoryException,I18n};
/** Read-only documents. Original benefits come exclusively from sealed invoice records. */
final class HybridDocuments
{
    public const SI=[
        'Hybrid invoice'=>'හයිබ්‍රිඩ් බිල්පත','Linked return'=>'බිල්පතට සම්බන්ධ භාණ්ඩ ආපසු භාරගැනීම','Residual void'=>'ඉතිරි කොටස අවලංගු කිරීම',
        'Original invoice'=>'මුල් බිල්පත','Reference'=>'යොමුව','Date'=>'දිනය','Customer'=>'පාරිභෝගිකයා','Representative'=>'විකුණුම් නියෝජිතයා',
        'Paid quantity'=>'මිල ගෙවන ප්‍රමාණය','Qualified quantity'=>'ප්‍රවර්ධනයට සුදුසු ප්‍රමාණය','Remainder quantity'=>'ඉතිරි ප්‍රමාණය','Actual free quantity'=>'සැබවින් ලබාදුන් නොමිලේ ප්‍රමාණය',
        'Product discount'=>'භාණ්ඩ වට්ටම','Replacement discount'=>'නොමිලේ භාණ්ඩ වෙනුවට මුදල් වට්ටම','Invoice discount'=>'සම්පූර්ණ බිල්පත් වට්ටම',
        'Original paid subtotal'=>'මුල් මිල ගෙවන භාණ්ඩ උප එකතුව','Combined monetary discounts'=>'ඒකාබද්ධ මුදල් වට්ටම්','Final payable'=>'අවසන් ගෙවිය යුතු මුදල',
        'Paid / applied'=>'ගෙවා ගැලපූ මුදල','Outstanding'=>'ඉතිරි ණය මුදල','Refund Due'=>'ආපසු ගෙවීමට නියමිත මුදල',
        'Refund Due is a liability. Actual cash refunds are not recorded by this system.'=>'ආපසු ගෙවීමට නියමිත මුදල වගකීමකි. සැබෑ මුදල් ආපසු ගෙවීම් මෙම පද්ධතියේ සටහන් නොවේ.',
        'Linked credits'=>'සම්බන්ධිත බැර ගැලපීම්','Returned paid quantity'=>'ආපසු භාරගත් මිල ගෙවූ ප්‍රමාණය','Recovered free quantity'=>'ආපසු භාරගත් නොමිලේ ප්‍රමාණය',
        'Original gross reversed'=>'ආපසු හරවන මුල් දළ වටිනාකම','Original-net credit'=>'මුල් ශුද්ධ වටිනාකම අනුව බැර මුදල','Receivable reduction'=>'අයවිය යුතු ණය අඩුවීම',
        'Refund Due increment'=>'ආපසු ගෙවීමට නියමිත මුදලේ වැඩිවීම','Condition at receipt'=>'භාරගැනීමේදී භාණ්ඩ තත්ත්වය','Saleable'=>'විකිණිය හැකි','Quarantine'=>'වෙන්කර තබන ලද','Unsaleable'=>'විකිණිය නොහැකි',
        'Paid'=>'මිල ගෙවූ','Free'=>'නොමිලේ','Custody'=>'භෞතික භාරකාරත්වය','Manager disposition and audit'=>'කළමනාකරුගේ තීරණ සහ විගණන යොමු',
        'Reason'=>'හේතුව','Holding location'=>'රඳවා තබන ස්ථානය','Version'=>'අනුවාදය','Actor'=>'ක්‍රියාකරු','Stock transaction'=>'තොග ගනුදෙනුව',
        'Current balance as of'=>'වත්මන් ශේෂය ගණනය කළ වේලාව','Original components remain unchanged.'=>'මුල් ගණනය කිරීම් වෙනස් කර නැත.',
        'Print'=>'මුද්‍රණය','Invoice details'=>'බිල්පත් විස්තර','Download PDF'=>'PDF බාගත කරන්න','Unit price'=>'ඒකක මිල','Line payable'=>'පේළියේ ගෙවිය යුතු මුදල',
        'Rate'=>'අනුපාතය','Amount'=>'මුදල','No physical stock release is implied by this document.'=>'මෙම ලේඛනයෙන් භාණ්ඩ තොගයට නිදහස් කළ බවක් අදහස් නොවේ.',
        'RECEIVED'=>'භාරගන්නා ලදී','SUPPLEMENT'=>'අමතර භාරගැනීම','ACCEPTED'=>'පිළිගන්නා ලදී','REVIEW'=>'සමාලෝචනය','REJECT'=>'ප්‍රතික්ෂේප කිරීම',
        'RECLASSIFY'=>'තත්ත්වය නැවත වර්ග කිරීම','RELEASE'=>'නිදහස් කිරීම','DISPOSE'=>'ඉවත් කිරීම','ISSUED'=>'නිකුත් කළ','VOIDED'=>'අවලංගු කළ',
        'Original line'=>'මුල් පේළිය','Quantity'=>'ප්‍රමාණය','From'=>'පෙර තත්ත්වය','To'=>'පසු තත්ත්වය','Disposed'=>'ඉවත් කළ',
        'Cash receipt history'=>'මුදල් භාරගැනීමේ ඉතිහාසය','Received'=>'භාරගත් මුදල','Applied'=>'ගැලපූ මුදල','Change'=>'ඉතිරි මුදල',
    ];
    public static function text(string $key,string $locale):string
    {if($locale==='si-LK'&&!isset(self::SI[$key]))throw new \LogicException('Missing Hybrid document translation: '.$key);return $locale==='si-LK'?self::SI[$key]:$key;}
    public static function render(array $record,string $layout,string $base,bool $pdf=false,?string $locale=null,string $paper='80',?array $return=null,?string $documentNonce=null):string
    {
        if(empty($record['hybrid']))throw new \InvalidArgumentException('Sealed Hybrid document required.');
        if(!in_array($layout,['receipt','a4'],true)||!in_array($paper,['58','80'],true))throw new \InvalidArgumentException('Invalid Hybrid document layout.');
        $locale=I18n::valid($locale)??($record['document_locale']??'en');
        return I18n::with($locale,static function()use($record,$layout,$base,$pdf,$locale,$paper,$return,$documentNonce){ob_start();try{require APP_ROOT.'/app/Views/hybrid-document.php';return ob_get_clean();}catch(\Throwable$e){ob_end_clean();throw$e;}});
    }
    public static function returnRecord(PDO $pdo,array $actor,string $invoice,string $id):array
    {
        HybridReturnRequest::uuid($id);$record=(new Invoices($pdo))->get($actor,$invoice);if(empty($record['hybrid']))throw new InventoryException(['return'=>'A sealed Hybrid invoice is required.'],404);
        (new HybridReturnReconciliation($pdo))->verify($actor,$invoice);
        $q=$pdo->prepare('SELECT r.*,u.name actor FROM hybrid_returns r JOIN users u ON u.id=r.actor_user_id WHERE r.id=? AND r.invoice_id=?');$q->execute([$id,$invoice]);$return=$q->fetch(PDO::FETCH_ASSOC);if(!$return)throw new InventoryException(['return'=>'Linked return not found or unavailable.'],404);
        $return['plan']=json_decode($return['plan_json'],true,512,JSON_THROW_ON_ERROR);$return['events']=[];
        if($return['custody_id']){$q=$pdo->prepare('SELECT e.id,e.command_id,e.version,e.event_type,e.event_json,e.reason,e.stock_transaction_id,e.created_at_utc,u.name actor FROM hybrid_custody_events e JOIN users u ON u.id=e.actor_user_id WHERE e.custody_id=? ORDER BY e.version');$q->execute([$return['custody_id']]);$return['events']=$q->fetchAll(PDO::FETCH_ASSOC);}
        return ['invoice'=>$record,'return'=>$return];
    }
}
