<?php
/** Synthetic rendering only. No database, session, invoice posting or network required. */
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
use App\Services\InvoiceDocuments;
function receipt58Fixture():array
{
    $items=[];
    foreach([
        ['Concentrated industrial floor cleaning liquid with extra long English product name 5 litres','වැඩි සාන්ද්‍රණයක් සහිත කාර්මික බිම් පිරිසිදු කිරීමේ දියර ලීටර් පහ'],
        ['Multipurpose disinfectant and bathroom cleaner refill pack','බහුකාර්ය විෂබීජ නාශක සහ නාන කාමර පිරිසිදු කිරීමේ දියර'],
        ['Glass cleaner promotional bottle','වීදුරු පිරිසිදු කරන දියර ප්‍රවර්ධන බෝතලය'],
    ] as $i=>[$en,$si])$items[]=['product_name_snapshot'=>$en,'name_si'=>$si,'sku_snapshot'=>'TEST-'.($i+1),'quantity'=>'2','unit_price'=>'1500.00','gross_amount'=>'3000.00','free_quantity'=>$i===2?'1':'0','discount_amount'=>'150.00','line_total'=>'2850.00'];
    return ['id'=>'00000000-0000-4000-8000-000000000058','invoice_number'=>'SYNTHETIC-0058','business_date'=>'2026-11-21','payment_type'=>'CASH','validity'=>'VALID','payment_status'=>'PARTIAL','customer'=>['shop_name'=>'Synthetic customer / පරීක්ෂණ වෙළඳසැල','address'=>'Example road, Negombo','city_town'=>'Negombo','district'=>'Gampaha'],'rep_name_snapshot'=>'Test representative','items'=>$items,'subtotal'=>'9000.00','discount_total'=>'450.00','grand_total'=>'8550.00','paid_amount'=>'8000.00','outstanding'=>'550.00','refund_due'=>'0.00','discount_policy'=>'INVOICE_PERCENT_V1','invoice_discount_percent'=>'5.00','invoice_discount_amount'=>'450.00','receipts'=>[]];
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $record=receipt58Fixture();$before=serialize($record);$out=$argv[1]??null;
    foreach(['en','si-LK'] as $locale)foreach(['58','80','a4'] as $format){
        $html=InvoiceDocuments::render($record,$format==='a4'?'a4':'receipt','/rep/invoices',false,$locale,$format==='58'?'58':'80');
        if(str_contains($html,'invoice-receipt-58.css')!==($format==='58'))throw new RuntimeException('58mm CSS isolation failed');
        foreach(['8,550.00','450.00','SYNTHETIC-0058'] as $text)if(!str_contains($html,$text))throw new RuntimeException('Snapshot content missing');
        if($out){if(!is_dir($out))mkdir($out,0700,true);file_put_contents($out.'/'.$format.'-'.$locale.'.html',$html);}
    }
    if($before!==serialize($record))throw new RuntimeException('Record mutated');
    try{InvoiceDocuments::render($record,'receipt','/rep/invoices',false,'en','bad');throw new RuntimeException('Invalid paper accepted');}catch(InvalidArgumentException $expected){}
    echo "Synthetic 58/80/A4 rendering, locale, amount, isolation and no-mutation checks passed.\n";
}
