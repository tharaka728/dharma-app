<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\ProductStock;use App\Services\Inventory;use App\Support\InventoryException;use App\Support\Http;use App\Middleware\Csrf;
final class ProductStockController
{
    public function __construct(private ProductStock $stock){}
    public static function route(string $path):?array
    {
        foreach(['/admin/inventory'=>false,'/api/v1/admin/inventory'=>true] as $base=>$api){
            foreach([''=>'index','/add'=>'add','/receipts'=>'add','/adjustments'=>'adjust','/history'=>'history','/movements'=>'history','/legacy'=>'legacy'] as $suffix=>$action){if($path===$base.$suffix)return ['action'=>$action,'api'=>$api,'methods'=>in_array($action,['add','adjust'],true)?['GET','POST']:['GET']];}
        }
        $old=InventoryController::route($path);if($old){$old['action']=$old['action']==='export'?'export':'retired';return $old;}return null;
    }
    public function handle(array $route,array $user):void
    {
        $action=$route['action'];$api=$route['api'];$input=[];$errors=[];$notice=$_SESSION['inventory_notice']??'';unset($_SESSION['inventory_notice']);
        try{
            $this->stock->admin($user);
            if($action==='legacy'){
                (new InventoryController(new Inventory(\App\Support\Database::connect())))->handle(['action'=>'movements','id'=>null,'api'=>$api,'methods'=>['GET']],$user);return;
            }
            if($_SERVER['REQUEST_METHOD']==='POST'){
                $input=$_POST;if($api&&stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$body=json_decode(file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new InventoryException(['body'=>'Invalid JSON.'],400);}if(!$body instanceof \stdClass)throw new InventoryException(['body'=>'Send a JSON object.'],400);$input=(array)$body;}
                $token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null))throw new InventoryException(['csrf'=>'Refresh this form and try again.'],403);
                if($action==='retired')throw new InventoryException(['retired'=>'Batch-based entry is retired. Use product stock entry.'],409);
                if(!$api&&$action==='add'&&($input['price_product_id']??null)!==($input['product_id']??null))throw new InventoryException(['price'=>'Select the product to load its own price before saving. With JavaScript disabled, use the Choose product button below.']);
                $result=$this->stock->post($user,$input,$action==='adjust');
                if($api){Http::json($result);return;}$_SESSION['inventory_notice']=$result['replayed']?'This stock request was already saved; no duplicate was added.':($action==='adjust'?'Stock adjustment saved.':'Stock saved.');AuthController::redirect('/admin/inventory?'.http_build_query(['product_id'=>$input['product_id']]));return;
            }
            if($action==='retired'){if($api){Http::json(['errors'=>['retired'=>'Use /api/v1/admin/inventory, /add, /adjustments or /history.']],410);}else AuthController::redirect('/admin/inventory');return;}
            if($action==='export'){
                $filters=$_GET;$filters['page']='1';$first=$this->stock->history($user,$filters);if($first['total']>10000)throw new InventoryException(['export'=>'Narrow the product filter to export at most 10,000 entries.']);
                header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="stock-history.csv"');$out=fopen('php://output','wb');$columns=['transaction_id','created_at_utc','sku','name','quantity_delta','quantity_after','admin_name','reason'];fputcsv($out,$columns,',','"','');
                for($page=1;$page<=$first['pages'];$page++){$filters['page']=(string)$page;$data=$page===1?$first:$this->stock->history($user,$filters);foreach($data['rows'] as $row){$cells=[];foreach($columns as $key)$cells[]=Inventory::csvCell($row[$key]);fputcsv($out,$cells,',','"','');}}fclose($out);return;
            }
            if($action==='index')$listing=$this->stock->listing($user,$_GET);
            elseif($action==='history')$listing=$this->stock->history($user,$_GET);
            if($api){Http::json($listing??['products'=>$this->stock->options($user)]);return;}
        }catch(InventoryException $e){http_response_code($e->status);if($api||!in_array($action,['add','adjust'],true)||!in_array($e->status,[422,409],true)){Http::json(['errors'=>$e->errors],$e->status);return;}$errors=$e->errors;}
        $options=$this->stock->options($user);$state=$this->stock->state($user);$mismatches=$this->stock->reconciliation($user);
        $selected=is_string($input['product_id']??null)?$input['product_id']:(is_string($_GET['product_id']??null)?$_GET['product_id']:'');
        if($_SERVER['REQUEST_METHOD']==='GET'){$found=$selected==='';foreach($options as $p){if((string)$p['id']===$selected){$found=true;$input=['selling_price'=>$p['selling_price'],'product_version'=>(string)$p['version']];break;}}if(!$found){$errors['product_id']='Choose an existing product.';http_response_code(422);$selected='';}}
        require APP_ROOT.'/app/Views/product-stock.php';
    }
}
