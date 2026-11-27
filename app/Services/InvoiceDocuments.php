<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class InvoiceDocuments
{
    public function __construct(private PDO $pdo){}
    public function snapshot(array $actor,string $id):array
    {
        if($this->pdo->inTransaction())return $this->withSaleTime((new Invoices($this->pdo))->get($actor,$id));
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        try{$record=$this->withSaleTime((new Invoices($this->pdo))->get($actor,$id));$this->pdo->commit();return $record;}
        catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    /** Adds the stored sale time (UTC) for document display only; the invoice record itself is unchanged. */
    private function withSaleTime(array $record):array
    {
        $stmt=$this->pdo->prepare('SELECT created_at_client_utc FROM invoices WHERE id=?');$stmt->execute([$record['id']]);
        $value=$stmt->fetchColumn();$record['sale_time_utc']=$value===false||$value===null?null:(string)$value;return $record;
    }
    public static function money(string $value):string
    {
        if(!preg_match('/^([0-9]+)\.([0-9]{2})$/D',$value,$m))throw new \RuntimeException('Invalid stored amount.');
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $m[1]).'.'.$m[2];
    }
    public static function filename(array $record):string
    {return 'Dharma-invoice-'.preg_replace('/[^A-Za-z0-9_-]/','-', $record['invoice_number']).'.pdf';}
    public static function render(array $record,string $layout,string $base,bool $pdf=false,?string $locale=null,string $receiptPaper='80',?string $documentNonce=null):string
    {
        if(!empty($record['hybrid']))return HybridDocuments::render($record,$layout,$base,$pdf,$locale,$receiptPaper,null,$documentNonce);
        if(!in_array($receiptPaper,['58','80'],true))throw new \InvalidArgumentException('Unknown receipt paper.');
        if(!in_array($layout,['receipt','a4'],true))throw new \InvalidArgumentException('Unknown layout.');
        return \App\Support\I18n::with(\App\Support\I18n::valid($locale)??($record['document_locale']??'en'),static function()use($record,$layout,$base,$pdf,$receiptPaper){ob_start();try{require APP_ROOT.'/app/Views/invoice-document.php';return ob_get_clean();}catch(\Throwable $e){ob_end_clean();throw $e;}});
    }
    public function returnSnapshot(array $actor,string $invoice,string $return):array
    {
        $own=!$this->pdo->inTransaction();if($own){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$this->pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');}
        try{return HybridDocuments::returnRecord($this->pdo,$actor,$invoice,$return);}finally{if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();}
    }
}
