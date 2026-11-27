<?php
declare(strict_types=1);
// Pure controller/route tests: no bootstrap, .env, sockets or real PDO constructor.
define('APP_ROOT',dirname(__DIR__));
spl_autoload_register(static function(string $class):void{
    if($class==='App\\Support\\Database')throw new RuntimeException('Database access forbidden.');
    if(str_starts_with($class,'App\\'))require APP_ROOT.'/app/'.str_replace('\\','/',substr($class,4)).'.php';
});
final class RemovalPdo extends PDO {
    public array $actor=['id'=>'42','name'=>'Synthetic','role'=>'SALES_REP','is_active'=>1];
    public int $queries=0;
    public function __construct(){}
    public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false{
        if($query!=='SELECT DATABASE()')throw new RuntimeException('Unexpected query: '.$query);
        return new RemovalStatement(['database'=>'pure_ui_removal_test']);
    }
    public function prepare(string $query,array $options=[]):PDOStatement|false{
        if($query!=='SELECT id,name,role,is_active FROM users WHERE id=?')throw new RuntimeException('Unexpected business query: '.$query);
        $this->queries++;return new RemovalStatement($this->actor);
    }
}
final class RemovalStatement extends PDOStatement {
    public function __construct(private array $actor){}
    public function execute(?array $params=null):bool{return true;}
    public function fetchColumn(int $column=0):mixed{return array_values($this->actor)[$column]??false;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0):mixed{return $this->actor;}
}
use App\Controllers\InvoiceController as C;
use App\Support\HybridWorkerClient as W;
$count=0;$check=static function(bool $ok,string $label)use(&$count):void{if(!$ok)throw new RuntimeException($label);$count++;};
putenv('DHARMA_BENEFIT_TEST=0');App\Support\I18n::set('en');
$p=new RemovalPdo();$controller=new C(new App\Services\Invoices($p));
$_SESSION=['csrf_token'=>str_repeat('a',64)];$_GET=$_POST=[];
ob_start();
$check(C::route('/rep/invoices/hybrid/new')===null,'Separate alias removed');
$check(C::route('/api/v1/rep/invoices/hybrid/new')===null,'No API editor alias');
foreach(['/rep/invoices/new','/api/v1/rep/invoices/new']as$path){
    $_SERVER['REQUEST_METHOD']='GET';ob_start();$controller->handle(C::route($path),$p->actor);$body=ob_get_clean();
    $check(http_response_code()===409&&!str_contains($body,'<form')&&str_contains($body,'approved original-form online runtime'),'Eligible GET fails closed');
}
foreach(['/rep/invoices/new','/rep/invoices/preview','/api/v1/rep/invoices','/api/v1/rep/invoices/preview']as$path){
    $_SERVER['REQUEST_METHOD']='POST';$_SERVER['CONTENT_TYPE']='application/x-www-form-urlencoded';$_POST=['intent'=>'review'];ob_start();$controller->handle(C::route($path),$p->actor);$body=ob_get_clean();
    $check(http_response_code()===409&&!str_contains($body,'<form')&&str_contains($body,'No legacy fallback'),'Unversioned POST fails closed before preview/writer');
}
$p->actor['is_active']=0;$_SERVER['REQUEST_METHOD']='GET';ob_start();$controller->handle(C::route('/rep/invoices/new'),$p->actor);ob_end_clean();$check(http_response_code()===403,'Current actor remains authorized server-side');
foreach(['GET','POST']as$method){try{W::route('/rep/invoices/hybrid/new',$method,[]);throw new LogicException('Alias accepted');}catch(RuntimeException $e){$check($e->getMessage()==='ROUTE_DENIED','Worker denies removed alias');}}
$check(W::route('/rep/invoices/new','GET',[])['action']==='create','Original route retained');
try{W::route('/rep/invoices/new','POST',[]);throw new LogicException('Writer accepted');}catch(RuntimeException $e){$check($e->getMessage()==='ROUTE_DENIED','HTML writer stays denied');}
$check(C::route('/api/v1/rep/invoices/hybrid/eligibility')['action']==='hybrid-eligibility','Reusable eligibility preserved');
$check(C::route('/rep/invoices/hybrid/returns')['action']==='hybrid-return-page','Linked returns preserved');
$check(C::route('/admin/invoices/new')['role']==='ADMIN','Admin original route preserved');
$config=require APP_ROOT.'/config/hybrid-promotions.php';
$check($config['enabled']===false&&$config['sale_posting']===false&&$config['return_posting']===false&&$config['financial_posting_hold']===true&&$config['bindings']===[]&&$config['databases']===[],'Activation OFF and posting held');
$source=file_get_contents(APP_ROOT.'/app/Views/invoices.php');
$check(str_contains($source,'id="invoice-form"')&&!str_contains($source,'hybrid-invoice')&&!str_contains($source,'hybrid/new'),'Only original invoice editor remains');
foreach(['app/Views/hybrid-invoice.php','app/Views/hybrid-invoice-fields.php','public/assets/js/hybrid-invoice.js','public/assets/css/hybrid-invoice.css','public/assets/css/hybrid-unified.css','tests/hybrid-ui-preview.ps1']as$file)$check(!file_exists(APP_ROOT.'/'.$file),'Exclusive UI removed: '.$file);
ob_end_clean();echo "UI removal: $count checks passed; fake PDO reads only, zero database connections.\n";
