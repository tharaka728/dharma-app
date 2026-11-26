<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\InvoiceDocuments;
use App\Support\{Database,Http,InventoryException,LocalPdf};
final class InvoiceDocumentController
{
    public function __construct(private ?InvoiceDocuments $documents=null){}
    public static function route(string $path):?array
    {
        if(preg_match('~^/(admin|rep)/invoices/([0-9a-f-]{36})/hybrid-returns/([0-9a-f-]{36})/(receipt|a4|pdf)$~D',$path,$m))return ['role'=>$m[1]==='admin'?'ADMIN':'SALES_REP','id'=>$m[2],'return_id'=>$m[3],'format'=>$m[4],'methods'=>['GET']];
        if(!preg_match('~^/(admin|rep)/invoices/([0-9a-f-]{36})/(receipt|a4|pdf)$~D',$path,$m))return null;
        return ['role'=>$m[1]==='admin'?'ADMIN':'SALES_REP','id'=>$m[2],'format'=>$m[3],'methods'=>['GET']];
    }
    public function handle(array $route,array $user):void
    {
        header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
        try{
            $documents=$this->documents??new InvoiceDocuments(Database::connect());$return=null;
            if(isset($route['return_id'])){$bundle=$documents->returnSnapshot($user,$route['id'],$route['return_id']);$record=$bundle['invoice'];$return=$bundle['return'];}
            else$record=$documents->snapshot($user,$route['id']);
            $base=$route['role']==='ADMIN'?'/admin/invoices':'/rep/invoices';
            $locale=\App\Support\I18n::valid($_GET['document_lang']??null);$paper=($_GET['paper']??null)==='58'?'58':'80';
            $layout=$route['format']==='pdf'&&isset($record['hybrid'])&&($_GET['layout']??null)==='receipt'?'receipt':($route['format']==='receipt'?'receipt':'a4');
            $documentNonce=null;
            if(isset($record['hybrid'])&&$route['format']!=='pdf'){
                $documentNonce=bin2hex(random_bytes(18));
                header("Content-Security-Policy: default-src 'none'; style-src 'nonce-$documentNonce'; font-src data:; script-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
            }
            $html=$return?\App\Services\HybridDocuments::render($record,$layout,$base,$route['format']==='pdf',$locale,$paper,$return,$documentNonce):InvoiceDocuments::render($record,$layout,$base,$route['format']==='pdf',$locale,$paper,$documentNonce);
            if($route['format']==='pdf'){
                // Release the PHP session lock while the isolated local renderer runs.
                session_write_close();
                $bytes=LocalPdf::generate($html);$filename=$return?'Dharma-return-'.$return['id'].'.pdf':InvoiceDocuments::filename($record);
                header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.strlen($bytes));echo $bytes;
            }else{header('Content-Type: text/html; charset=utf-8');echo $html;}
        }catch(InventoryException $e){Http::json(['errors'=>$e->errors],$e->status);}
        catch(\Throwable $e){error_log('Invoice document generation failed: '.get_class($e));Http::error('DOCUMENT_UNAVAILABLE','Document unavailable. Try again or use Print A4 and Save as PDF.',503);}
    }
}
