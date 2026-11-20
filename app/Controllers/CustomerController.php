<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Customers;use App\Services\Locations;use App\Support\Database;use App\Support\CustomerException;use App\Support\Http;use App\Middleware\Csrf;
final class CustomerController
{
    public function __construct(private Customers $customers,private ?Locations $locations=null) {}
    public static function route(string $path):?array
    {
        foreach(['/admin/customers'=>false,'/api/v1/admin/customers'=>true] as $base=>$api){
            if($path===$base){return ['action'=>'index','id'=>null,'api'=>$api,'methods'=>$api?['GET','POST']:['GET']];}
            if(!$api&&$path===$base.'/new'){return ['action'=>'create','id'=>null,'api'=>false,'methods'=>['GET','POST']];}
            if(preg_match('~^'.preg_quote($base,'~').'/([1-9][0-9]{0,18})(?:/(edit|status))?$~D',$path,$m)){$suffix=$m[2]??'';if($api&&$suffix==='edit'){return null;}return ['action'=>$suffix===''?'show':$suffix,'id'=>$m[1],'api'=>$api,'methods'=>$suffix==='status'?['POST']:($suffix==='edit'||$api?['GET','POST']:['GET'])];}
        }return null;
    }
    public function handle(array $route,array $user):void
    {
        $api=$route['api'];$action=$route['action'];$id=$route['id'];$errors=[];$duplicates=[];$input=[];
        try{
            $this->customers->admin($user);
            if($_SERVER['REQUEST_METHOD']==='POST'){
                $input=$_POST;
                if($api&&stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$body=json_decode(file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new CustomerException(['body'=>'Invalid JSON.'],400);}if(!$body instanceof \stdClass){throw new CustomerException(['body'=>'Send a JSON object.'],400);}$input=(array)$body;}
                $token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null)){throw new CustomerException(['csrf'=>'Refresh this page and try again.'],403);}
                if($action==='status'){$customer=$this->customers->status($user,$id,$input['version']??null,$input['is_active']??null);}
                else{$customer=$this->customers->save($user,$input,in_array($action,['create','index'],true)?null:$id);}
                if($api){Http::json(['customer'=>$customer,'currency'=>'LKR'],$action==='index'?201:200);}else{$_SESSION['customer_notice']=$action==='status'?'Customer status updated.':'Customer saved.';AuthController::redirect('/admin/customers/'.$customer['id']);}return;
            }
            if($action==='index'){$listing=$this->customers->listing($user,$_GET);if($api){Http::json($listing);return;}}
            elseif($action==='create'){$input=['customer_type'=>'OTHER','credit_terms_days'=>'0'];}
            else{$customer=$this->customers->get($user,$id);$input=$customer;if($api){Http::json(['customer'=>$customer,'currency'=>'LKR']);return;}}
        }catch(CustomerException $e){
            http_response_code($e->status);if($api||!in_array($e->status,[422,409],true)){Http::json(['errors'=>$e->errors,'duplicates'=>$e->duplicates],$e->status);return;}
            $errors=$e->errors;$duplicates=$e->duplicates;
            if($action==='index'){$listing=['customers'=>[],'total'=>0,'page'=>1,'pages'=>1];}elseif($action==='status'){$customer=$this->customers->get($user,$id);}
        }
        if($action==='index'){$filterLocations=($this->locations??new Locations(Database::connect()))->filterForm($user,$_GET);}
        if(in_array($action,['create','edit'],true)){$locationOptions=($this->locations??new Locations(Database::connect()))->form($user,$input);}
        $billingRepresentatives=Database::connect()->query("SELECT id,name,is_active FROM users WHERE role='SALES_REP' ORDER BY name,id")->fetchAll();
        $billingCustomer=$id!==null?$this->customers->get($user,$id):null;
        $historicalReturns=$action==='show'?(new \App\Services\Invoices(Database::connect()))->historicalReturnsForCustomer($user,$id):[];
        $notice=$_SESSION['customer_notice']??'';unset($_SESSION['customer_notice']);$title=$action==='index'?'Customers':($action==='create'?'Add customer':($action==='edit'?'Edit customer':'Customer details'));
        require APP_ROOT.'/app/Views/customers.php';
    }
}
