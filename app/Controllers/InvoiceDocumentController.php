<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\InvoiceDocuments;
use App\Support\{Database,Http,InventoryException,LocalPdf};
final class InvoiceDocumentController
{
    public static function route(string $path):?array
    {
        if(!preg_match('~^/(admin|rep)/invoices/([0-9a-f-]{36})/(receipt|a4|pdf)$~D',$path,$m))return null;
        return ['role'=>$m[1]==='admin'?'ADMIN':'SALES_REP','id'=>$m[2],'format'=>$m[3],'methods'=>['GET']];
    }
    public function handle(array $route,array $user):void
    {
        header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
        try{
            $record=(new InvoiceDocuments(Database::connect()))->snapshot($user,$route['id']);
            $base=$route['role']==='ADMIN'?'/admin/invoices':'/rep/invoices';
            if($route['format']==='pdf'){
                // Release the PHP session lock while the isolated local renderer runs.
                session_write_close();
                $bytes=LocalPdf::generate(InvoiceDocuments::render($record,'a4',$base,true,\App\Support\I18n::valid($_GET['document_lang']??null)));
                header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.InvoiceDocuments::filename($record).'"');header('Content-Length: '.strlen($bytes));echo $bytes;
            }else{header('Content-Type: text/html; charset=utf-8');echo InvoiceDocuments::render($record,$route['format'],$base,false,\App\Support\I18n::valid($_GET['document_lang']??null));}
        }catch(InventoryException $e){Http::json(['errors'=>$e->errors],$e->status);}
        catch(\Throwable $e){error_log('Invoice document generation failed: '.get_class($e));Http::error('DOCUMENT_UNAVAILABLE','Document unavailable. Try again or use Print A4 and Save as PDF.',503);}
    }
}
