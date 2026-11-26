<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\HybridReturns;
use App\Support\{Http,InventoryException};
use App\Middleware\Csrf;
final class HybridReturnController
{
    public function __construct(private HybridReturns $returns){}
    public static function route(string $path):?array
    {
        if(!preg_match('~^(/api/v1)?/(rep|admin)/invoices/hybrid/returns(?:/(options|details|custody|preview|post|receive|supplement|manage))?$~D',$path,$m))return null;
        $api=($m[1]??'')!=='';$action=$m[3]??'page';
        if(($api&&$action==='page')||(!$api&&$action!=='page'))return null;
        return ['action'=>'hybrid-return-'.$action,'id'=>null,'api'=>$api,'role'=>$m[2]==='admin'?'ADMIN':'SALES_REP','methods'=>[in_array($action,['page','options','details','custody'],true)?'GET':'POST']];
    }
    public function handle(array $route,array $user):void
    {
        header('Cache-Control: private, no-store');
        try{
            if(!in_array($_SERVER['REQUEST_METHOD'],$route['methods'],true))throw new InventoryException(['method'=>'Method not allowed.'],405);
            // Services reload current account and invoice scope on every request.
            if(($user['role']??null)!==$route['role'])throw new InventoryException(['access'=>'This route is unavailable for your role.'],403);
            $action=substr($route['action'],14);
            if($action==='page'){$capabilities=$this->returns->availability($user);if(!$capabilities['enabled'])throw new InventoryException(['return'=>'Hybrid returns are disabled.'],409);$admin=$route['role']==='ADMIN';$home=$admin?'/admin':'/rep';require APP_ROOT.'/app/Views/hybrid-returns.php';return;}
            if($_SERVER['REQUEST_METHOD']==='GET'){
                if($action==='options')$out=$this->returns->options($user);
                elseif($action==='details')$out=$this->returns->details($user,is_string($_GET['invoice_id']??null)?$_GET['invoice_id']:'');
                else$out=$this->returns->custodyDetails($user,is_string($_GET['invoice_id']??null)?$_GET['invoice_id']:'',is_string($_GET['custody_id']??null)?$_GET['custody_id']:'');
            }else{
                $input=$_POST;
                if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$body=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);}catch(\JsonException$e){throw new InventoryException(['body'=>'Invalid JSON.'],400);}if(!is_array($body)||$body===array_values($body))throw new InventoryException(['body'=>'Send a JSON object.'],400);$input=$body;}
                $token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null))throw new InventoryException(['csrf'=>'Refresh this page and try again.'],403);
                $method=['preview'=>'preview','post'=>'post','receive'=>'receiveCustody','supplement'=>'supplementCustody','manage'=>'manageCustody'][$action];$out=$this->returns->$method($user,$input);
            }
            Http::json($out);
        }catch(InventoryException$e){Http::json(['errors'=>$e->errors],$e->status);}
    }
}
