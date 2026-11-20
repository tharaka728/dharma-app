<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Representatives;use App\Services\RepresentativeStock;use App\Support\InventoryException;use App\Support\Http;use App\Middleware\Csrf;
final class RepresentativeController
{
    public function __construct(private Representatives $reps,private RepresentativeStock $stock){}
    public static function route(string $path):?array
    {
        foreach(['/admin/representatives'=>false,'/api/v1/admin/representatives'=>true] as $base=>$api){
            if($path===$base)return ['kind'=>'accounts','action'=>'index','id'=>null,'role'=>'ADMIN','api'=>$api,'methods'=>$api?['GET','POST']:['GET']];
            if($path===$base.'/new')return ['kind'=>'accounts','action'=>'create','id'=>null,'role'=>'ADMIN','api'=>$api,'methods'=>['GET','POST']];
            if(preg_match('~^'.preg_quote($base,'~').'/([1-9][0-9]{0,18})(?:/(edit|status|password))?$~D',$path,$m)){$action=$m[2]??'show';return ['kind'=>'accounts','action'=>$action,'id'=>$m[1],'role'=>'ADMIN','api'=>$api,'methods'=>$action==='status'?['POST']:($action==='show'?['GET']:['GET','POST'])];}
        }
        foreach(['/admin/stock'=>false,'/api/v1/admin/stock'=>true] as $base=>$api){foreach(['allocate','returns','corrections','representatives','history'] as $action){if($path===$base.'/'.$action)return ['kind'=>'stock','action'=>$action,'id'=>null,'role'=>'ADMIN','api'=>$api,'methods'=>in_array($action,['allocate','returns','corrections'],true)?['GET','POST']:['GET']];}}
        foreach(['/rep/stock'=>false,'/api/v1/rep/stock'=>true] as $base=>$api){if($path===$base||$path===$base.'/history')return ['kind'=>'stock','action'=>$path===$base?'own':'own-history','id'=>null,'role'=>'SALES_REP','api'=>$api,'methods'=>['GET']];}return null;
    }
    public function handle(array $route,array $user):void
    {
        $action=$route['action'];$kind=$route['kind'];$api=$route['api'];$id=$route['id'];$own=$route['role']==='SALES_REP';$input=[];$errors=[];$notice=$_SESSION['representative_notice']??'';unset($_SESSION['representative_notice']);
        try{
            if(!$own)$this->reps->admin($user);
            if($_SERVER['REQUEST_METHOD']==='POST'){
                $input=$_POST;if($api&&stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$body=json_decode(file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new InventoryException(['body'=>'Invalid JSON.'],400);}if(!$body instanceof \stdClass)throw new InventoryException(['body'=>'Send a JSON object.'],400);$input=(array)$body;}
                $token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null))throw new InventoryException(['csrf'=>'Refresh this page and try again.'],403);
                if($kind==='accounts'){
                    if($action==='status'){$result=$this->reps->status($user,$id,$input);}
                    elseif($action==='password'){$this->reps->reset($user,$id,$input);$result=['id'=>$id];}
                    else{$result=$this->reps->save($user,$input,in_array($action,['create','index'],true)?null:$id);}
                    $message=$action==='password'?'Password reset. Existing sessions are revoked; share the new password privately.':'Representative saved.';$destination='/admin/representatives/'.$result['id'];
                }else{$command=['allocate'=>'ALLOCATE','returns'=>'RETURN','corrections'=>'CORRECTION'][$action];$result=$this->stock->post($user,$input,$command);$message=$result['replayed']?'This request was already posted; no duplicate stock change.':'Stock '.($action==='allocate'?'allocated':($action==='returns'?'returned':'correction recorded')).'.';$destination='/admin/stock/representatives?'.http_build_query(['rep_id'=>$input['rep_id']]);}
                unset($input['password'],$input['password_confirmation']);
                if($api){Http::json($result,$kind==='accounts'&&in_array($action,['create','index'],true)?201:200);return;}$_SESSION['representative_notice']=$message;AuthController::redirect($destination);return;
            }
            if($kind==='accounts'){
                if($action==='index'){$listing=$this->reps->listing($user,$_GET);if($api){Http::json($listing);return;}}
                elseif($action!=='create'){$record=$this->reps->get($user,$id);$input=$record;if($api){Http::json(['representative'=>$record]);return;}}
            }elseif(in_array($action,['history','own-history'],true)){$listing=$this->stock->history($user,$_GET,$own);if($api){Http::json($listing);return;}}
            elseif(in_array($action,['representatives','own'],true)){$listing=$this->stock->balances($user,$_GET,$own);if($api){Http::json($listing);return;}}
            elseif($api){Http::json($this->stock->options($user));return;}
        }catch(InventoryException $e){unset($input['password'],$input['password_confirmation']);http_response_code($e->status);if($api||!in_array($e->status,[422,409],true)||$_SERVER['REQUEST_METHOD']!=='POST'){Http::json(['errors'=>$e->errors],$e->status);return;}$errors=$e->errors;if($kind==='accounts'&&$action==='index')$action='create';if($action==='status'){$action='show';$record=$this->reps->get($user,$id);$input=$record;}}
        if($kind==='accounts'&&$action==='password'){$target=$record??$this->reps->get($user,$id);$input+=['name'=>$target['name'],'username'=>$target['username']];}
        $options=$own?[]:$this->stock->options($user);$mismatches=$own?[]:$this->stock->reconciliation($user);
        require APP_ROOT.'/app/Views/representatives.php';
    }
}
