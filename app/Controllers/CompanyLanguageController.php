<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\CompanyLanguages;use App\Support\{Database,InventoryException};use App\Middleware\Csrf;
final class CompanyLanguageController
{
 public static function handle(array $user):void
 {
  $svc=new CompanyLanguages(Database::connect());$errors=[];$notice='';$input=$svc->get($user);
  if($_SERVER['REQUEST_METHOD']==='POST')try{if(!is_string($_POST['csrf_token']??null)||!Csrf::valid($_POST['csrf_token'],$_SESSION['csrf_token']??null))throw new InventoryException(['csrf'=>'Refresh the form and try again.'],403);$svc->save($user,$_POST);$input=$svc->get($user);$notice='Company language settings saved. Historical invoices were not changed.';}catch(InventoryException $e){http_response_code($e->status);$errors=$e->errors;$input=array_merge($input,$_POST);}
  require APP_ROOT.'/app/Views/company-languages.php';
 }
}
