<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Middleware\Csrf;
use App\Services\HistoricalReturnPrices;
use App\Support\InventoryException;

final class HistoricalReturnPricesController
{
    public function __construct(private HistoricalReturnPrices $prices) {}
    public function handle(array $user): void
    {
        $errors=[];$notice='';
        try{
            if($_SERVER['REQUEST_METHOD']==='POST'){
                $token=$_POST['csrf_token']??null;
                if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null))throw new InventoryException(['csrf'=>'Refresh the form and try again.'],403);
                $this->prices->save($user,$_POST);
                $_SESSION['historical_price_notice']='Historical price saved as a new revision.';
                AuthController::redirect('/admin/historical-prices?'.http_build_query(['year'=>$_POST['price_year']??'2024','q'=>$_POST['search']??'']));return;
            }
            $notice=$_SESSION['historical_price_notice']??'';unset($_SESSION['historical_price_notice']);
        }catch(InventoryException $e){http_response_code($e->status);$errors=$e->errors;}
        $year=is_string($_GET['year']??null)?$_GET['year']:(is_string($_POST['price_year']??null)?$_POST['price_year']:'2024');
        $search=is_string($_GET['q']??null)?$_GET['q']:(is_string($_POST['search']??null)?$_POST['search']:'');
        try{$rows=$this->prices->listing($user,['year'=>$year,'q'=>$search]);}
        catch(InventoryException $e){http_response_code($e->status);$errors=$e->errors;$rows=[];}
        $history=[];$historyProduct=$_GET['history']??null;
        if(is_string($historyProduct))try{$history=$this->prices->history($user,$historyProduct,$year);}catch(InventoryException $e){$errors=$e->errors;}
        require APP_ROOT.'/app/Views/historical-return-prices.php';
    }
}
