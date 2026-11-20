<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Locations;use App\Services\Customers;use App\Support\CustomerException;use App\Support\Http;use App\Middleware\Csrf;
final class LocationController
{
    public static function route(string $path):?array
    {
        if($path==='/api/v1/admin/locations/districts'){return ['action'=>'districts','methods'=>['GET']];}
        if($path==='/api/v1/admin/locations/towns'){return ['action'=>'towns','methods'=>['GET','POST']];}return null;
    }
    public function __construct(private Locations $locations) {}
    public function handle(array $route,array $user):void
    {
        try{
            if($_SERVER['REQUEST_METHOD']==='GET'){Http::json($route['action']==='districts'?['districts'=>$this->locations->districts($user)]:$this->locations->towns($user,$_GET));return;}
            $input=$_POST;
            if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){try{$decoded=json_decode(file_get_contents('php://input'),false,16,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new CustomerException(['body'=>'Invalid JSON.'],400);}if(!$decoded instanceof \stdClass){throw new CustomerException(['body'=>'Send a JSON object.'],400);}$input=(array)$decoded;}
            $token=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);
            if(!is_string($token)||!Csrf::valid($token,$_SESSION['csrf_token']??null)){throw new CustomerException(['csrf'=>'Refresh the customer form and try again.'],403);}
            Http::json(['town'=>$this->locations->create($user,$input['district_id']??null,$input['town_name']??null)],201);
        }catch(CustomerException $e){Http::json(['errors'=>$e->errors],$e->status);}
    }
}
