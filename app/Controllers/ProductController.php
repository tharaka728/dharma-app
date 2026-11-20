<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Products;
use App\Support\ProductException;
use App\Support\Http;
use App\Middleware\Csrf;
final class ProductController
{
    public function __construct(private Products $products) {}
    public static function route(string $path): ?array
    {
        foreach(['/admin/products'=>false,'/api/v1/admin/products'=>true] as $base=>$api){
            if($path===$base){return ['action'=>'index','id'=>null,'api'=>$api,'methods'=>$api?['GET','POST']:['GET']];}
            if($path===$base.'/categories'){return ['action'=>'category','id'=>null,'api'=>$api,'methods'=>['POST']];}
            if(!$api && $path===$base.'/new'){return ['action'=>'create','id'=>null,'api'=>false,'methods'=>['GET','POST']];}
            if(preg_match('~^'.preg_quote($base,'~').'/([1-9][0-9]{0,18})(?:/(edit|status))?$~D',$path,$m)){
                $suffix=$m[2]??'';
                if($api && $suffix==='edit'){return null;}
                return ['action'=>$suffix===''?'show':$suffix,'id'=>$m[1],'api'=>$api,'methods'=>$suffix==='status'?['POST']:($suffix==='edit'||$api?['GET','POST']:['GET'])];
            }
        }
        return null;
    }
    public function handle(array $route,array $user): void
    {
        $api=$route['api'];$action=$route['action'];$id=$route['id'];$post=$_SERVER['REQUEST_METHOD']==='POST';
        $errors=[];$input=[];
        try {
            $this->products->admin($user);
            if($post){
                $input=$_POST;
                if($api && stripos($_SERVER['CONTENT_TYPE']??'','application/json')===0){
                    try{$decoded=json_decode(file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new ProductException(['body'=>'Invalid JSON.'],400);}
                    if(!$decoded instanceof \stdClass){throw new ProductException(['body'=>'Send a JSON object.'],400);}
                    $input=(array)$decoded;
                }
                $csrf=$input['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);
                if(!is_string($csrf)||!Csrf::valid($csrf,$_SESSION['csrf_token']??null)){throw new ProductException(['csrf'=>'Refresh this page and try again.'],403);}
                if($action==='category'){
                    $newId=$this->products->category($user,$input['category_name']??null);
                    if($api){Http::json(['category_id'=>$newId],201);}else{$_SESSION['product_notice']='Category created. You can now choose it for your product.';AuthController::redirect('/admin/products/new');}return;
                }
                $image=(!$api && $action==='create')?\App\Services\ProductImages::prepare($_FILES['product_image']??null):null;
                if($action==='status'){$product=$this->products->status($user,$id,$input['version']??null,$input['is_active']??null);}
                else{$product=$this->products->save($user,$input,($action==='create'||$action==='index')?null:$id);}
                if($api){Http::json(['product'=>$product,'currency'=>'LKR'],($action==='index')?201:200);}
                else{$_SESSION['product_notice']=$action==='status'?'Product status updated.':'Product saved.';if($image!==null&&!\App\Services\ProductImages::saveNew((string)$product['id'],$image))$_SESSION['product_notice']='Product saved, but its image could not be saved.';AuthController::redirect('/admin/products/'.$product['id']);}return;
            }
            if($action==='index'){$listing=$this->products->listing($user,$_GET);if($api){Http::json($listing);return;}}
            elseif($action==='create'){$input=['sku'=>'','name'=>'','category_id'=>'','unit_of_measure'=>'unit','selling_price'=>'','cost_price'=>'','description'=>''];}
            else{$product=$this->products->get($user,$id);$input=$product;if($api){Http::json(['product'=>$product,'currency'=>'LKR']);return;}}
        }catch(ProductException $e){
            http_response_code($e->status);
            if($api || !in_array($e->status,[422,409],true)){Http::json(['errors'=>$e->errors],$e->status);return;}
            $errors=$e->errors;
            if($action==='index'){$listing=['products'=>[],'total'=>0,'page'=>1,'pages'=>1];}
            elseif($action==='status'){$product=$this->products->get($user,$id);}
            elseif($action==='category'){$action='create';$input=['unit_of_measure'=>'unit'];}
        }
        $categories=$this->products->categories($user);
        $notice=$_SESSION['product_notice']??'';unset($_SESSION['product_notice']);
        $title=$action==='index'?'Products':($action==='create'?'Add product':($action==='edit'?'Edit product':'Product details'));
        require APP_ROOT.'/app/Views/products.php';
    }
}
