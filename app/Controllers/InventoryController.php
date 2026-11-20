<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Inventory;
use App\Support\InventoryException;
use App\Support\Http;
use App\Middleware\Csrf;
final class InventoryController
{
    public function __construct(private Inventory $inventory) {}
    public static function route(string $path):?array
    {
        foreach(['/admin/inventory'=>false,'/api/v1/admin/inventory'=>true] as $base=>$api){
            if($path===$base){return ['action'=>'index','id'=>null,'api'=>$api,'methods'=>['GET']];}
            foreach(['batches','receipts','adjustments','movements','settings','threshold'] as $action){if($path===$base.'/'.$action){return ['action'=>$action,'id'=>null,'api'=>$api,'methods'=>$action==='movements'?['GET']:($action==='threshold'?['POST']:['GET','POST'])];}}
            if($path===$base.'/movements.csv'){return ['action'=>'export','id'=>null,'api'=>$api,'methods'=>['GET']];}
            if(preg_match('~^'.preg_quote($base,'~').'/batches/([1-9][0-9]{0,18})/edit$~D',$path,$m)){return ['action'=>'edit','id'=>$m[1],'api'=>$api,'methods'=>['GET','POST']];}
        }return null;
    }
    public function handle(array $route,array $user):void
    {
        $action=$route['action'];$id=$route['id'];$api=$route['api'];$input=[];$errors=[];$notice=$_SESSION['inventory_notice']??'';unset($_SESSION['inventory_notice']);
        try{
            $this->inventory->admin($user);
            if($_SERVER['REQUEST_METHOD']==='POST'){
                $input=$_POST;
                if($api&&stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$body=json_decode(file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new InventoryException(['body'=>'Invalid JSON.'],400);}if(!$body instanceof \stdClass){throw new InventoryException(['body'=>'Send a JSON object.'],400);}$input=(array)$body;}
                $token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null)){throw new InventoryException(['csrf'=>'Refresh this page and try again.'],403);}
                if(in_array($action,['receipts','adjustments'],true)){
                    $entry=$action==='receipts'?($input['entry_type']??'RECEIPT'):'ADJUSTMENT';
                    if($action==='receipts'&&(!is_string($entry)||!in_array($entry,['RECEIPT','OPENING'],true))){throw new InventoryException(['entry_type'=>'Choose goods receipt or opening stock.']);}
                    $result=$this->inventory->post($user,$input,$entry);
                    $message=$result['replayed']?'Previously posted request confirmed. No duplicate movement was created.':($entry==='OPENING'?'Opening stock recorded.':'Stock received / adjusted.').' Transaction '.$result['transaction_id'];
                    $postedBatch=$this->inventory->batch($user,$input['batch_id']);$destination='/admin/inventory?'.http_build_query(['product_id'=>(string)$postedBatch['product_id']]);
                }
                elseif(in_array($action,['batches','edit'],true)){$batch=$this->inventory->saveBatch($user,$input,$id);$result=['batch_id'=>$batch];$message='Batch saved; stock quantities were not changed.';$destination=$action==='batches'&&($input['continue_receipt']??null)==='1'?'/admin/inventory/receipts?'.http_build_query(['batch_id'=>$batch,'product_id'=>(string)$input['product_id'],'entry_type'=>($input['entry_type']??'RECEIPT')==='OPENING'?'OPENING':'RECEIPT']):'/admin/inventory/batches/'.$batch.'/edit';}
                elseif($action==='settings'){$this->inventory->configure($user,$input);$result=['saved'=>true];$message='Inventory settings saved.';$destination='/admin/inventory/settings';}
                else{$this->inventory->threshold($user,$input);$result=['saved'=>true];$message='Low-stock threshold saved.';$destination='/admin/inventory/settings';}
                if($api){Http::json($result);return;}$_SESSION['inventory_notice']=$message;AuthController::redirect($destination);return;
            }
            if($action==='export'){
                $listing=$this->inventory->movements($user,$_GET,true);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="inventory-movements.csv"');
                $out=fopen('php://output','wb');$columns=['id','transaction_id','type','created_at_utc','receipt_date','location','location_type','sku','product_name','batch_number','snapshot_expiry','quantity_delta','quantity_after','unit_cost','supplier','reference','reason','notes','responsible_user','reverses_transaction_id'];fputcsv($out,$columns,',','"','');
                foreach($listing['rows'] as $row){$cells=[];foreach($columns as $column){$cells[]=Inventory::csvCell($row[$column]);}fputcsv($out,$cells,',','"','');}fclose($out);return;
            }
            if($action==='movements'){$listing=$this->inventory->movements($user,$_GET);}
            elseif($action==='index'||$action==='batches'){$listing=$this->inventory->stocks($user,$_GET);}
            elseif($action==='edit'){$input=$this->inventory->batch($user,$id);}
            if($api){Http::json($listing??['batch'=>$input,'settings'=>$this->inventory->settings($user),'options'=>$this->inventory->options($user)]);return;}
        }catch(InventoryException $e){http_response_code($e->status);if($api||!in_array($e->status,[422,409],true)||$_SERVER['REQUEST_METHOD']!=='POST'){Http::json(['errors'=>$e->errors],$e->status);return;}$errors=$e->errors;if($action==='threshold'){$action='settings';}}
        if($action==='batches'&&$_SERVER['REQUEST_METHOD']==='GET'&&is_string($_GET['product_id']??null)){$input['product_id']=$_GET['product_id'];}
        if($action==='batches'&&!isset($listing)){$listing=$this->inventory->stocks($user,[]);}
        $settings=$this->inventory->settings($user);$options=$this->inventory->options($user);$mismatches=$this->inventory->reconciliation($user);
        if(in_array($action,['receipts','adjustments'],true)){
            $chosenProduct=$input['product_id']??($_GET['product_id']??'');
            if($action==='receipts'&&$chosenProduct===''&&is_string($_GET['batch_id']??null)){try{$selectedBatch=$this->inventory->batch($user,$_GET['batch_id']);$chosenProduct=(string)$selectedBatch['product_id'];}catch(InventoryException $e){$errors=array_merge($errors,$e->errors);http_response_code($e->status);}}
            if($action==='receipts'&&is_string($chosenProduct)){$input['product_id']=$chosenProduct;}
            try{$batchOptions=$this->inventory->stocks($user,['q'=>is_string($_GET['batch_search']??null)?$_GET['batch_search']:'','page'=>is_string($_GET['batch_page']??null)?$_GET['batch_page']:'1','product_id'=>$action==='receipts'?$chosenProduct:'']);}
            catch(InventoryException $e){$errors=array_merge($errors,$e->errors);http_response_code($e->status);$batchOptions=$this->inventory->stocks($user,[]);}
            $chosen=$input['batch_id']??($_GET['batch_id']??'');
            if($chosen!==''){
                try{
                    if(!is_string($chosen)||!preg_match('/^[1-9][0-9]{0,18}$/D',$chosen)){throw new InventoryException(['batch_id'=>'Choose an existing batch.']);}
                    $selected=$this->inventory->batch($user,$chosen);
                    if($action==='receipts'&&$chosenProduct!==''&&(string)$selected['product_id']!==$chosenProduct){throw new InventoryException(['batch_id'=>'This batch belongs to another product. Choose a batch for the selected product.']);}
                    if(!in_array($chosen,array_map('strval',array_column($batchOptions['rows'],'batch_id')),true)){array_unshift($batchOptions['rows'],['batch_id'=>$chosen,'product_name'=>$selected['product_name'],'batch_number'=>$selected['batch_number'],'expiry_date'=>$selected['expiry_date']]);}
                    $input['batch_id']=$chosen;
                }catch(InventoryException $e){$errors=array_merge($errors,$e->errors);http_response_code($e->status);unset($input['batch_id']);}
            }
        }
        require APP_ROOT.'/app/Views/inventory.php';
    }
}
