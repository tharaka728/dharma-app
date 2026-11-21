<?php
/** Synthetic benefit-mode receipt rendering only. No database, session, invoice posting or network required. */
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
use App\Services\InvoiceDocuments;
function benefitReceiptFixture():array
{
    $items=[
        ['product_name_snapshot'=>'Suwada Kudu','name_si'=>'සුවඳ කුඩු','sku_snapshot'=>'P012','quantity'=>'12','unit_price'=>'200.00','gross_amount'=>'2400.00','free_quantity'=>'0','discount_amount'=>'600.00','line_total'=>'1800.00'],
        ['product_name_snapshot'=>'Glucose','name_si'=>'ග්ලුකෝස්','sku_snapshot'=>'P013','quantity'=>'6','unit_price'=>'140.00','gross_amount'=>'840.00','free_quantity'=>'0','discount_amount'=>'0.00','line_total'=>'840.00'],
    ];
    return ['id'=>'00000000-0000-4000-8000-0000000000b1','invoice_number'=>'SYNTHETIC-BENEFIT-01','business_date'=>'2026-11-21','payment_type'=>'CASH','validity'=>'VALID','payment_status'=>'PAID','customer'=>['shop_name'=>'Synthetic benefit customer','address'=>'Example road, Negombo','city_town'=>'Negombo','district'=>'Gampaha'],'rep_name_snapshot'=>'Test representative','items'=>$items,'subtotal'=>'3240.00','discount_total'=>'600.00','grand_total'=>'2640.00','paid_amount'=>'2640.00','outstanding'=>'0.00','refund_due'=>'0.00','discount_policy'=>null,'invoice_discount_percent'=>null,'invoice_discount_amount'=>null,'benefit_mode'=>'PROMOTION_DISCOUNT_INSTEAD','benefit_percent_rate'=>'25.00','benefit_eligible_subtotal'=>'2400.00','benefit_discount_amount'=>'600.00','receipts'=>[]];
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $record=benefitReceiptFixture();$before=serialize($record);$out=$argv[1]??null;$count=0;
    $check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;};
    foreach(['en','si-LK'] as $locale)foreach(['58','80','a4'] as $format){
        $html=InvoiceDocuments::render($record,$format==='a4'?'a4':'receipt','/rep/invoices',false,$locale,$format==='58'?'58':'80');
        $check(str_contains($html,'invoice-receipt-58.css')===($format==='58'),'58mm CSS isolation '.$format.' '.$locale);
        foreach(['2,640.00','600.00','SYNTHETIC-BENEFIT-01'] as $text)$check(str_contains($html,$text),'Benefit amounts render '.$format.' '.$locale);
        $check(str_contains($html,$locale==='en'?'Benefit mode':'ප්‍රතිලාභ ක්‍රමය'),'Benefit mode label renders '.$format.' '.$locale);
        $check(str_contains($html,$locale==='en'?'Promotion discount instead':'නොමිලේ භාණ්ඩ වෙනුවට වට්ටම'),'Spec benefit label renders '.$format.' '.$locale);
        $check(str_contains($html,'25.00')&&str_contains($html,'2,400.00'),'Benefit rate and eligible subtotal render '.$format.' '.$locale);
        if($out){if(!is_dir($out))mkdir($out,0700,true);file_put_contents($out.'/benefit-'.$format.'-'.$locale.'.html',$html);}
    }
    $check($before===serialize($record),'Record not mutated by rendering');
    echo "Synthetic benefit 58/80/A4 receipt rendering checks passed ($count).\n";
}
