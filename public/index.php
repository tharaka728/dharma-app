<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
// Explicit, local CLI maintenance gate. The verifier removes it in its shutdown handler.
if(is_file(dirname(__DIR__).'/storage/historical-backup-maintenance.lock')){
    http_response_code(503);header('Retry-After: 60');header('Cache-Control: no-store');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The application is temporarily unavailable for backup verification. Please try again shortly.';
    exit;
}
use App\Support\Http;
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&($chosenLanguage=\App\Support\I18n::valid($_GET['language']??null))!==null){\App\Support\I18n::set($chosenLanguage);setcookie('dharma_language',$chosenLanguage,['expires'=>time()+31536000,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>false,'samesite'=>'Lax']);$_COOKIE['dharma_language']=$chosenLanguage;}
use App\Controllers\HealthController;
use App\Controllers\AuthController;
use App\Middleware\Session;
use App\Middleware\Authorization;
use App\Services\Authentication;
use App\Support\Database;
use App\Controllers\ProductController;
use App\Services\Products;
use App\Controllers\CustomerController;
use App\Services\Customers;
use App\Controllers\LocationController;
use App\Services\Locations;
use App\Controllers\InventoryController;
use App\Services\Inventory;
use App\Services\ProductStock;
use App\Controllers\ProductStockController;
use App\Controllers\RepresentativeController;
use App\Services\Representatives;
use App\Services\RepresentativeStock;
use App\Services\Promotions;
use App\Controllers\PromotionController;
use App\Controllers\InvoiceController;
use App\Services\Invoices;
use App\Controllers\OfflineController;
use App\Services\OfflineSales;
use App\Controllers\InvoiceDocumentController;
use App\Controllers\ReportController;
use App\Services\Reports;
use App\Controllers\HistoricalReturnPricesController;
use App\Services\HistoricalReturnPrices;
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');
try {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    $base = rtrim(getenv('APP_BASE_PATH') ?: '', '/');
    if ($base !== '' && strpos($path, $base . '/') === 0) { $path = substr($path, strlen($base)); }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 1048576) {
        Http::error('REQUEST_TOO_LARGE', 'Request exceeds the size limit.', 413);
    } else {
        $routes = ['/' => ['GET'], '/login' => ['GET','POST'], '/logout' => ['POST'], '/admin' => ['GET'], '/rep' => ['GET'], '/api/v1/health' => ['GET'], '/api/v1/me' => ['GET'], '/api/v1/admin/dashboard' => ['GET'], '/api/v1/rep/dashboard' => ['GET']];
        $routes['/admin/settings/language']=['GET','POST'];
        $routes['/admin/historical-prices']=['GET','POST'];
        $routes['/api/v1/rep/historical-prices']=['GET'];
        $routes['/api/v1/rep/return-preview-products']=['GET'];
        $routes['/api/v1/admin/historical-prices']=['GET'];
        $routes['/api/v1/rep/return-intents']=['GET','POST'];
        $routes['/api/v1/admin/return-intents']=['GET','POST'];
        $productImageRoute=preg_match('~^/product-image/([1-9][0-9]{0,18})$~D',$path,$productImageMatch)===1;
        if($productImageRoute)$routes[$path]=['GET'];
        $reportRoute=ReportController::route($path);
        if($reportRoute){$routes[$path]=$reportRoute['methods'];}
        $documentRoute=InvoiceDocumentController::route($path);
        if($documentRoute){$routes[$path]=$documentRoute['methods'];}
        $offlineRoute=OfflineController::route($path);
        if($offlineRoute){$routes[$path]=$offlineRoute['methods'];}
        $invoiceRoute=InvoiceController::route($path);
        if($invoiceRoute){$routes[$path]=$invoiceRoute['methods'];}
        $productRoute=ProductController::route($path);
        if($productRoute){$routes[$path]=$productRoute['methods'];}
        $customerRoute=CustomerController::route($path);
        if($customerRoute){$routes[$path]=$customerRoute['methods'];}
        $locationRoute=LocationController::route($path);
        if($locationRoute){$routes[$path]=$locationRoute['methods'];}
        $inventoryRoute=ProductStockController::route($path);
        if($inventoryRoute){$routes[$path]=$inventoryRoute['methods'];}
        $representativeRoute=RepresentativeController::route($path);
        if($representativeRoute){$routes[$path]=$representativeRoute['methods'];}
        $promotionRoute=PromotionController::route($path);
        if($promotionRoute){$routes[$path]=$promotionRoute['methods'];}
        if (!isset($routes[$path])) { Http::error('NOT_FOUND','Route not found.',404); }
        elseif (!in_array($_SERVER['REQUEST_METHOD'],$routes[$path],true)) { header('Allow: '.implode(', ',$routes[$path])); Http::error('METHOD_NOT_ALLOWED','Method not allowed.',405); }
        elseif ($path === '/api/v1/health') { (new HealthController())->show(true); }
        elseif ($path === '/') { AuthController::redirect('/login'); }
        else {
            Session::start(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $auth = new Authentication(Database::connect());
            $controller = new AuthController($auth);
            if ($path === '/login') { $controller->login(); }
            else {
                $user = $auth->currentUser();
                $api = strpos($path,'/api/') === 0;
                $role = $reportRoute['role'] ?? $documentRoute['role'] ?? $offlineRoute['role'] ?? $invoiceRoute['role'] ?? $promotionRoute['role'] ?? ($representativeRoute['role'] ?? (($productRoute || $customerRoute || $locationRoute || $inventoryRoute || in_array($path,['/admin','/api/v1/admin/dashboard','/admin/settings/language','/admin/historical-prices','/api/v1/admin/historical-prices','/api/v1/admin/return-intents'],true)) ? 'ADMIN' : 'SALES_REP'));
                if (!$user) {
                    if ($api) { Http::error('UNAUTHENTICATED','Sign in to continue.',401); }
                    else { AuthController::redirect('/login'); }
                } elseif ($path === '/api/v1/me') { Http::json(['user'=>['id'=>(string)$user['id'],'name'=>$user['name'],'username'=>$user['username'],'role'=>$user['role']]]); }
                elseif ($path === '/logout') { $controller->logout(); }
                elseif (!Authorization::allows($user,$role)) { Http::error('FORBIDDEN','You do not have access to this workspace.',403); }
                elseif ($productImageRoute) { \App\Services\ProductImages::send($productImageMatch[1]); }
                elseif ($path==='/admin/settings/language') { \App\Controllers\CompanyLanguageController::handle($user); }
                elseif ($path==='/admin/historical-prices') { $pricePdo=Database::connect();$q=$pricePdo->prepare("SELECT status FROM schema_migrations WHERE version=?");$q->execute(['020_historical_return_prices.sql']);if($q->fetchColumn()!=='applied')Http::error('NOT_READY','Historical prices are not available until the database update is verified.',503);else (new HistoricalReturnPricesController(new HistoricalReturnPrices($pricePdo)))->handle($user); }
                elseif ($path==='/api/v1/rep/return-preview-products') { try{Http::json(['products'=>\App\Services\HistoricalReturnPreview::search(Database::connect(),$user,require APP_ROOT.'/config/historical-returns.php',$_GET)]);}catch(\App\Support\InventoryException $e){Http::json(['errors'=>$e->errors],$e->status);} }
                elseif (in_array($path,['/api/v1/rep/historical-prices','/api/v1/admin/historical-prices'],true)) { $pricePdo=Database::connect();$q=$pricePdo->prepare('SELECT status FROM schema_migrations WHERE version=?');$q->execute(['020_historical_return_prices.sql']);if($q->fetchColumn()!=='applied')Http::error('NOT_READY','Historical prices are not available until the database update is verified.',503);else try{Http::json(['prices'=>(new HistoricalReturnPrices($pricePdo))->approved($user,$_GET)]);}catch(\App\Support\InventoryException $e){Http::json(['errors'=>$e->errors],$e->status);} }
                elseif (in_array($path,['/api/v1/rep/return-intents','/api/v1/admin/return-intents'],true)) { $intentPdo=Database::connect();if(!(new Invoices($intentPdo))->historicalReturnAllowed($user))Http::error('NOT_READY','Historical return checkout is not yet available.',503);else try{$service=new \App\Services\HistoricalReturnIntents($intentPdo);if($_SERVER['REQUEST_METHOD']==='POST'){$token=$_POST['csrf_token']??null;if(!is_string($token)||!\App\Middleware\Csrf::valid($token,$_SESSION['csrf_token']??null))throw new \App\Support\InventoryException(['csrf'=>'Refresh the form and try again.'],403);$customer=$_POST['customer_id']??null;if(!is_string($customer))throw new \App\Support\InventoryException(['customer'=>'Choose a customer.']);Http::json($service->start($user,$customer),201);}elseif(!array_key_exists('id',$_GET))Http::json(['intents'=>$service->recent($user)]);else{$id=$_GET['id'];if(!is_string($id))throw new \App\Support\InventoryException(['return'=>'Return intent unavailable.'],404);Http::json($service->recover($user,$id));}}catch(\App\Support\InventoryException $e){Http::json(['errors'=>$e->errors],$e->status);} }
                elseif ($reportRoute) { (new ReportController(new Reports(Database::connect())))->handle($reportRoute,$user); }
                elseif ($documentRoute) { (new InvoiceDocumentController())->handle($documentRoute,$user); }
                elseif ($offlineRoute) { (new OfflineController(new OfflineSales(Database::connect())))->handle($offlineRoute,$user); }
                elseif ($invoiceRoute) { (new InvoiceController(new Invoices(Database::connect())))->handle($invoiceRoute,$user); }
                elseif ($promotionRoute) { (new PromotionController(new Promotions(Database::connect())))->handle($promotionRoute,$user); }
                elseif ($representativeRoute) { $repPdo=Database::connect(); (new RepresentativeController(new Representatives($repPdo),new RepresentativeStock($repPdo)))->handle($representativeRoute,$user); }
                elseif ($inventoryRoute) { (new ProductStockController(new ProductStock(Database::connect())))->handle($inventoryRoute,$user); }
                elseif ($productRoute) { (new ProductController(new Products(Database::connect())))->handle($productRoute,$user); }
                elseif ($customerRoute) { (new CustomerController(new Customers(Database::connect())))->handle($customerRoute,$user); }
                elseif ($locationRoute) { (new LocationController(new Locations(Database::connect())))->handle($locationRoute,$user); }
                elseif ($api) { Http::json(['workspace'=>$role,'modules_status'=>'planned']); }
                else { $controller->dashboard($user,$role); }
            }
        }
    }
} catch (Throwable $e) {
    error_log('Application request failed: ' . get_class($e));
    Http::error('INTERNAL_ERROR', 'Unable to complete the request.', 500);
}
