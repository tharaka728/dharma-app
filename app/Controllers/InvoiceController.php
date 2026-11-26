<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Invoices;use App\Support\InventoryException;use App\Support\Http;use App\Middleware\Csrf;
final class InvoiceController
{
    public function __construct(private Invoices $invoices){}
    public static function route(string $path):?array
    {
        if($r=HybridReturnController::route($path))return $r;
        if($path==='/rep/invoices/hybrid/new')return ['action'=>'hybrid-create','id'=>null,'api'=>false,'role'=>'SALES_REP','methods'=>['GET']];
        if($path==='/api/v1/rep/invoices/hybrid/eligibility')return ['action'=>'hybrid-eligibility','id'=>null,'api'=>true,'role'=>'SALES_REP','methods'=>['POST']];
        foreach(['/admin/invoices'=>['ADMIN',false],'/rep/invoices'=>['SALES_REP',false],'/api/v1/admin/invoices'=>['ADMIN',true],'/api/v1/rep/invoices'=>['SALES_REP',true]] as $base=>[$role,$api]){
            $action=null;$id=null;$methods=['GET'];
            if($path===$base){$action='index';if($api)$methods[]='POST';}
            elseif($path===$base.'/new'){$action='create';$methods=['GET','POST'];}
            elseif($path===$base.'/preview'){$action='preview';$methods=['POST'];}
            elseif($path===$base.'/options')$action='options';
            elseif($role==='ADMIN'&&$path===$base.'/settings'){$action='settings';$methods=['GET','POST'];}
            elseif($role==='ADMIN'&&$path===$base.'/customer-settings'){$action='customer-settings';$methods=['POST'];}
            elseif(preg_match('~^'.preg_quote($base,'~').'/([0-9a-f-]{36})(?:/(payment|void))?$~D',$path,$m)){$id=$m[1];$action=$m[2]??'show';if($action!=='show')$methods=['POST'];if($action==='void')$role='ADMIN';}
            if($action!==null)return compact('action','id','api','role','methods');
        }
        if(in_array($path,['/rep/customers','/api/v1/rep/customers'],true))return ['action'=>'customers','id'=>null,'api'=>strpos($path,'/api/')===0,'role'=>'SALES_REP','methods'=>['GET']];return null;
    }
    public function handle(array $route,array $user):void
    {
        if(strpos($route['action'],'hybrid-return-')===0){(new HybridReturnController($this->invoices->hybridReturns()))->handle($route,$user);return;}
        if(in_array($route['action'],['hybrid-create','hybrid-eligibility'],true)){$this->hybridHandle($route,$user);return;}
        $action=$route['action'];$id=$route['id'];$api=$route['api'];$admin=$route['role']==='ADMIN';$base=$admin?'/admin/invoices':'/rep/invoices';$returnTestEnabled=$this->invoices->historicalReturnAllowed($user);$returnReadOnly=!$returnTestEnabled&&!$admin&&$this->invoices->historicalReturnPreviewAllowed($user);$input=[];$errors=[];$review=null;$record=null;$listing=null;$notice=$_SESSION['invoice_notice']??'';unset($_SESSION['invoice_notice']);
        try{
            $this->invoices->actor($user,$admin);
            if($_SERVER['REQUEST_METHOD']==='POST'){
                $input=$_POST;if($api&&stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$body=json_decode(file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new InventoryException(['body'=>'Invalid JSON.'],400);}if(!$body instanceof \stdClass)throw new InventoryException(['body'=>'Send a JSON object.'],400);$input=(array)$body;}
                if(array_key_exists('historical_return',$input)&&!$returnTestEnabled)throw new InventoryException(['return'=>'Historical return checkout is not yet available.'],409);
                $token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null))throw new InventoryException(['csrf'=>'Refresh the form and try again.'],403);
                if($action==='customer-settings'){$result=$this->invoices->customerSettings($user,$input);if($api){Http::json($result);return;}$_SESSION['customer_notice']='Credit permission and representative assignment saved.';AuthController::redirect('/admin/customers/'.$result['customer_id']);return;}
                if($action==='settings'){$result=$this->invoices->setPrefix($user,$input);if($api){Http::json($result);return;}$notice='Invoice prefix saved. Existing numbers and lifetime counters are unchanged.';}
                elseif(in_array($action,['payment','void'],true)){$result=$action==='payment'?$this->invoices->pay($user,$id,$input):$this->invoices->void($user,$id,$input);if($api){Http::json($result);return;}$_SESSION['invoice_notice']=$action==='void'?'Invoice voided. Stock reversed; received money remains Refund Due. No physical refund was recorded.':'Payment recorded. Change: LKR '.($result['change_amount']??'0.00').'.';AuthController::redirect($base.'/'.$id);return;}
                elseif(in_array($input['intent']??'',['source','addline'],true)){$action='create';if(($input['intent']??'')==='addline'){$lines=$input['items']??[];if(!is_array($lines)||count($lines)>=100)throw new InventoryException(['items'=>'At most 100 product lines.']);$lines[]=['product_id'=>'','quantity'=>'1'];$input['items']=$lines;}}
                elseif($action==='preview'||($input['intent']??'')==='review'){$review=$this->invoices->review($user,$input);if($api){Http::json($review);return;}$action='create';}
                else{$result=$this->invoices->issue($user,$input);if($api){Http::json($result,$result['replayed']?200:201);return;}$_SESSION['invoice_notice']=$result['replayed']?'Invoice already issued; no duplicate posting.':'Invoice issued and stock updated.';AuthController::redirect($base.'/'.$result['invoice_id']);return;}
            }elseif($action==='options'){Http::json(($_GET['catalogue']??'')==='1'?$this->invoices->catalogue($user,$_GET):$this->invoices->options($user,is_string($_GET['stock_source']??null)?$_GET['stock_source']:null));return;}
            elseif($action==='customers'){$customers=$this->invoices->customers($user);if($api){Http::json(['customers'=>$customers]);return;}}
            elseif($action==='index'){$listing=$this->invoices->listing($user,$_GET);if($api){Http::json($listing);return;}}
            elseif($action==='show'){$record=$this->invoices->get($user,$id);if($api){Http::json($record);return;}}
            elseif($action==='create')$input=['payment_type'=>'CASH','amount_received'=>'0.00'];
        }catch(InventoryException $e){http_response_code($e->status);if($api||!in_array($e->status,[422,409],true)){Http::json(['errors'=>$e->errors],$e->status);return;}$errors=$e->errors;if(in_array($action,['preview','index'],true)&&$_SERVER['REQUEST_METHOD']==='POST')$action='create';elseif(in_array($action,['void','payment'],true)){$action='show';$record=$this->invoices->get($user,$id);}}
        // Benefit checkout UI is offered only to an authorized pilot representative (Phase 3 decision C).
        $benefitModes=$this->invoices->benefitModesEnabled()&&\App\Services\BenefitPolicy::pilotMember($user);$benefitRequired=$benefitModes;
        $source=is_string($input['stock_source']??null)?$input['stock_source']:null;$selected=$action==='create'&&($_POST['catalogue_fallback']??'')!=='1'?array_column(is_array($input['items']??null)?$input['items']:[],'product_id'):null;$options=$this->invoices->options($user,$source,$action==='index',$selected);
        $hybridAvailability=$action==='create'&&!$admin?$this->invoices->hybridAvailability($user):['enabled'=>false,'posting'=>false];
        require APP_ROOT.'/app/Views/invoices.php';
    }
    private function hybridHandle(array $route,array $user):void
    {
        header('Cache-Control: private, no-store');
        try{
            if(!in_array($_SERVER['REQUEST_METHOD'],$route['methods'],true))throw new InventoryException(['method'=>'Method not allowed.'],405);
            $user=$this->invoices->actor($user);
            if($user['role']!=='SALES_REP')throw new InventoryException(['access'=>'Sales Representative access is required.'],403);
            $hybridAvailability=$this->invoices->hybridAvailability($user);
            if(!$hybridAvailability['enabled'])throw new InventoryException(['hybrid'=>'Hybrid checkout is disabled.'],409);
            if($route['action']==='hybrid-eligibility'){
                $input=$_POST;
                if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$body=json_decode(file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new InventoryException(['body'=>'Invalid JSON.'],400);}if(!$body instanceof \stdClass)throw new InventoryException(['body'=>'Send a JSON object.'],400);$input=(array)$body;}
                $token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);
                if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null))throw new InventoryException(['csrf'=>'Refresh the form and try again.'],403);
                Http::json($this->invoices->hybridEligibility($user,$input));return;
            }
            $options=$this->invoices->options($user,(string)$user['id']);$admin=false;$home='/rep';$base='/rep/invoices';
            require APP_ROOT.'/app/Views/hybrid-invoice.php';
        }catch(InventoryException $e){Http::json(['errors'=>$e->errors],$e->status);}
    }
}
