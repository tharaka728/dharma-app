<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Promotions;use App\Support\InventoryException;use App\Support\Http;use App\Middleware\Csrf;
final class PromotionController
{
    public function __construct(private Promotions $promotions){}
    public static function route(string $path):?array
    {
        foreach(['/admin/promotions'=>false,'/api/v1/admin/promotions'=>true] as $base=>$api){
            if($path===$base)return ['action'=>'index','id'=>null,'api'=>$api,'role'=>'ADMIN','methods'=>$api?['GET','POST']:['GET']];
            if($path===$base.'/new'||$path===$base.'/preview')return ['action'=>$path===$base.'/new'?'create':'preview','id'=>null,'api'=>$api,'role'=>'ADMIN','methods'=>$path===$base.'/preview'?['POST']:['GET','POST']];
            if(preg_match('~^'.preg_quote($base,'~').'/([1-9][0-9]{0,18})(?:/(edit|status))?$~D',$path,$m)){$action=$m[2]??'show';return ['action'=>$action,'id'=>$m[1],'api'=>$api,'role'=>'ADMIN','methods'=>$action==='status'?['POST']:($action==='edit'?['GET','POST']:['GET'])];}
        }
        foreach(['/rep/promotions'=>false,'/api/v1/rep/promotions'=>true] as $base=>$api){if($path===$base)return ['action'=>'applicable','id'=>null,'api'=>$api,'role'=>'SALES_REP','methods'=>['GET']];}
        if($path==='/api/v1/admin/promotions/quote'||$path==='/api/v1/rep/promotions/quote')return ['action'=>'quote','id'=>null,'api'=>true,'role'=>strpos($path,'/admin/')!==false?'ADMIN':'SALES_REP','methods'=>['GET']];return null;
    }
    public function handle(array $route,array $user):void
    {
        $action=$route['action'];$id=$route['id'];$api=$route['api'];$own=$route['role']==='SALES_REP';$errors=[];$input=[];$preview=null;$notice=$_SESSION['promotion_notice']??'';unset($_SESSION['promotion_notice']);
        try{
            if(!$own)$this->promotions->admin($user);
            if($action==='quote'){$product=$_GET['product_id']??null;if(!is_string($product))throw new InventoryException(['product_id'=>'Choose a product.']);Http::json($this->promotions->quote($user,$product,$_GET['paid_quantity']??'1'));return;}
            if($_SERVER['REQUEST_METHOD']==='POST'){
                $input=$_POST;if($api&&stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$body=json_decode(file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new InventoryException(['body'=>'Invalid JSON.'],400);}if(!$body instanceof \stdClass)throw new InventoryException(['body'=>'Send a JSON object.'],400);$input=(array)$body;}
                $token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null))throw new InventoryException(['csrf'=>'Refresh this form and try again.'],403);
                if($action==='preview'||($input['intent']??null)==='preview'){$preview=$this->promotions->draftPreview($user,$input);if($api){Http::json($preview);return;}$action=$id===null?'create':'edit';}
                else{$result=$this->promotions->save($user,$input,in_array($action,['create','index'],true)?null:$id,$action==='status');if($api){Http::json($result,$action==='index'?201:200);return;}$_SESSION['promotion_notice']=$result['replayed']?'This request was already saved; no duplicate revision.':'Promotion saved. Product prices and stock were not changed.';AuthController::redirect('/admin/promotions/'.$result['promotion_id']);return;}
            }elseif(in_array($action,['index','applicable'],true)){$listing=$this->promotions->listing($user,$_GET,$own);if($api){Http::json($listing);return;}}
            elseif($action==='create'){$input=['is_active'=>'1','type'=>'BUY_X_GET_Y'];}
            else{$record=$this->promotions->get($user,$id);$input=$record;if($api){Http::json(['promotion'=>$record,'revisions'=>$this->promotions->revisions($user,$id)]);return;}}
        }catch(InventoryException $e){http_response_code($e->status);if($api||!in_array($e->status,[422,409],true)||$_SERVER['REQUEST_METHOD']!=='POST'){Http::json(['errors'=>$e->errors],$e->status);return;}$errors=$e->errors;if(in_array($action,['preview','index'],true))$action='create';elseif($action==='status'){$action='show';$record=$this->promotions->get($user,$id);$input=$record;}}
        $options=$own?[]:$this->promotions->options($user);$revisions=$action==='show'?$this->promotions->revisions($user,$id):[];
        require APP_ROOT.'/app/Views/promotions.php';
    }
}
