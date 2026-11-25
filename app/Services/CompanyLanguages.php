<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;
final class CompanyLanguages
{
 public function __construct(private PDO $pdo){}
 public function get(array $actor):array{(new Invoices($this->pdo))->actor($actor,true);return $this->pdo->query('SELECT name_si,address_si,footer_si,approved,version FROM company_language_settings WHERE id=1')->fetch();}
 public function save(array $actor,array $input):void
 {
  (new Invoices($this->pdo))->actor($actor,true);$values=[];foreach(['name_si'=>200,'address_si'=>1000,'footer_si'=>1000] as $key=>$limit)$values[$key]=Inventory::text($input[$key]??'',$key,$limit);
  $approved=($input['approved']??'0')==='1'?'1':'0';if($approved==='1'&&$values['name_si']==='')throw new InventoryException(['name_si'=>'Enter an approved Sinhala business name before enabling it.']);
  $owner=!$this->pdo->inTransaction();if($owner)$this->pdo->beginTransaction();else $this->pdo->exec('SAVEPOINT company_language');try{$before=$this->pdo->query('SELECT * FROM company_language_settings WHERE id=1 FOR UPDATE')->fetch();if(($input['version']??null)!==(string)$before['version'])throw new InventoryException(['version'=>'Company settings changed. Reload before saving.'],409);
   $this->pdo->prepare('UPDATE company_language_settings SET name_si=?,address_si=?,footer_si=?,approved=?,version=version+1 WHERE id=1')->execute([$values['name_si']?:null,$values['address_si']?:null,$values['footer_si']?:null,$approved]);$values['approved']=$approved;
   $this->pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,before_json,after_json) VALUES (?,'COMPANY_LANGUAGE_SETTINGS','company_language_settings','1',?,?)")->execute([$actor['id'],json_encode($before,JSON_THROW_ON_ERROR),json_encode($values,JSON_THROW_ON_ERROR)]);if($owner)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT company_language');
  }catch(\Throwable $e){if($owner&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$owner&&$this->pdo->inTransaction())$this->pdo->exec('ROLLBACK TO SAVEPOINT company_language');throw $e;}
 }
 public function snapshot():array
 {
  $r=$this->pdo->query('SELECT name_si,address_si,footer_si,approved FROM company_language_settings WHERE id=1')->fetch();$out=['name'=>'Dharma Chemical','address'=>(string)(getenv('INVOICE_COMPANY_ADDRESS')?:''),'phone'=>(string)(getenv('INVOICE_COMPANY_PHONE')?:'0770520905'),'email'=>(string)(getenv('INVOICE_COMPANY_EMAIL')?:''),'footer'=>'Authoritative invoice snapshots. Payment and validity shown as of this document request. Retain this copy securely.'];
  if((int)$r['approved'])foreach(['name_si','address_si','footer_si'] as $key)$out[$key]=$r[$key];return $out;
 }
}
