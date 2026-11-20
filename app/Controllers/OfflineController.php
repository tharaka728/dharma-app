<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\OfflineSales;use App\Support\{Http,InventoryException};use App\Middleware\Csrf;
final class OfflineController
{
    public function __construct(private OfflineSales $offline){}
    public static function route(string $path):?array
    {
        $map=['/api/v1/offline/session'=>['session','GET'],'/api/v1/offline/register'=>['register','POST'],'/api/v1/offline/bootstrap'=>['bootstrap','POST'],'/api/v1/offline/sales'=>['sales','POST']];if(isset($map[$path]))return ['action'=>$map[$path][0],'id'=>null,'api'=>true,'role'=>'SALES_REP','methods'=>[$map[$path][1]]];
        if(in_array($path,['/admin/offline','/api/v1/admin/offline'],true))return ['action'=>'index','id'=>null,'api'=>strpos($path,'/api/')===0,'role'=>'ADMIN','methods'=>['GET']];
        if(preg_match('~^(/admin/offline|/api/v1/admin/offline)/(sales|devices)/([0-9a-f-]{36})$~D',$path,$m))return ['action'=>$m[2],'id'=>$m[3],'api'=>strpos($path,'/api/')===0,'role'=>'ADMIN','methods'=>['POST']];return null;
    }
    public function handle(array $route,array $user):void
    {
        try{
            if($route['action']==='session'){if($user['role']!=='SALES_REP')throw new InventoryException(['access'=>'Sales representative access required.'],403);Http::json(['user'=>['id'=>(string)$user['id'],'name'=>$user['name'],'role'=>$user['role']],'csrf_token'=>$_SESSION['csrf_token'],'authenticated_at'=>$_SESSION['created_at'],'server_time'=>gmdate('Y-m-d\TH:i:s\Z')]);return;}
            if($_SERVER['REQUEST_METHOD']==='POST'){$input=$_POST;if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$body=json_decode(file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new InventoryException(['body'=>'Invalid JSON.'],400);}if(!$body instanceof \stdClass)throw new InventoryException(['body'=>'Send a JSON object.'],400);$input=(array)$body;}$token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null))throw new InventoryException(['csrf'=>'Sign in again and refresh the offline session.'],403);
                switch($route['action']){case 'register':$r=$this->offline->register($user,$input);break;case 'bootstrap':$r=$this->offline->bootstrap($user,$input);break;case 'sales':$r=$route['role']==='ADMIN'?$this->offline->review($user,$route['id'],$input):$this->offline->submit($user,$input);break;case 'devices':$r=$this->offline->device($user,$route['id'],$input);break;default:throw new InventoryException(['route'=>'Invalid offline action.'],404);}
                if($route['api'])Http::json($r);else{$_SESSION['offline_notice']='Review/device action recorded. No automatic refund was made.';AuthController::redirect('/admin/offline');}return;
            }
            $listing=$this->offline->adminListing($user,$_GET);if($route['api']){Http::json($listing);return;}$notice=$_SESSION['offline_notice']??'';unset($_SESSION['offline_notice']);require APP_ROOT.'/app/Views/offline-admin.php';
        }catch(InventoryException $e){Http::json(['errors'=>$e->errors],$e->status);}
    }
}
