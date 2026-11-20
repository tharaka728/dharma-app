<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\ProductException;
use PDO;
final class Products
{
    public function __construct(private PDO $pdo) {}
    public static function money(mixed $value, bool $optional=false): ?string
    {
        if ($optional && ($value === '' || $value === null)) { return null; }
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D',$value,$m)) { throw new ProductException(['prices'=>'Enter nonnegative decimal prices with at most 13 whole digits and 2 decimal places.']); }
        return $m[1].'.'.str_pad($m[2] ?? '',2,'0');
    }
    public static function validate(array $input): array
    {
        $out=[];$errors=[];if(array_key_exists('name_si',$input)){$si=$input['name_si'];if(!is_string($si)||!mb_check_encoding($si,'UTF-8')||mb_strlen(trim($si),'UTF-8')>200||preg_match('/[\x00-\x1F\x7F]/',$si))$errors['name_si']='Enter a valid Sinhala product name of up to 200 characters.';else $out['name_si']=trim($si)===''?null:trim($si);}
        foreach(['sku'=>100,'name'=>200,'unit_of_measure'=>40,'description'=>5000] as $key=>$max){
            $value=$input[$key] ?? '';
            if(!is_string($value) || !mb_check_encoding($value,'UTF-8')){$errors[$key]='Enter valid text.';continue;}
            $value=trim($value);
            if(($key!=='description' && $value==='') || mb_strlen($value,'UTF-8')>$max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value)){$errors[$key]="Required text must fit within $max characters.";}
            if($key==='sku' && !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._\/-]{0,99}$/D',$value)){$errors['sku']='Use 1–100 letters, numbers, dots, slashes, hyphens or underscores.';}
            $out[$key]=$key==='sku'?strtoupper($value):$value;
        }
        $cat=$input['category_id'] ?? '';
        if(!is_string($cat) || !preg_match('/^[1-9][0-9]{0,18}$/D',$cat)){$errors['category_id']='Choose a category.';}
        else{$out['category_id']=$cat;}
        foreach(['selling_price','cost_price'] as $key){try{$out[$key]=self::money($input[$key] ?? '',$key==='cost_price');}catch(ProductException $e){$errors[$key]=$e->errors['prices'];}}
        if($errors){throw new ProductException($errors);}
        return $out;
    }
    public function admin(array $actor): void
    {
        $q=$this->pdo->prepare('SELECT role,is_active FROM users WHERE id=?');$q->execute([$actor['id']??'0']);$user=$q->fetch();
        if(!$user || $user['role']!=='ADMIN' || !(int)$user['is_active']){throw new ProductException(['access'=>'Administrator access required.'],403);}
    }
    private function transaction(callable $fn): mixed
    {
        $owner=!$this->pdo->inTransaction();
        // Savepoints also keep a failed command atomic inside rollback-only tests.
        if($owner){$this->pdo->beginTransaction();}else{$this->pdo->exec('SAVEPOINT product_command');}
        try{$result=$fn();if($owner){$this->pdo->commit();}else{$this->pdo->exec('RELEASE SAVEPOINT product_command');}return $result;}
        catch(\Throwable $e){if($owner && $this->pdo->inTransaction()){$this->pdo->rollBack();}elseif(!$owner){$this->pdo->exec('ROLLBACK TO SAVEPOINT product_command');}
            if($e instanceof \PDOException && (int)($e->errorInfo[1]??0)===1062){throw new ProductException(['sku'=>'This SKU or category already exists. SKUs cannot be reused.']);}throw $e;}
    }
    private function audit(array $actor,string $action,string $entity,string $id,?array $before,array $after): void
    {
        $this->pdo->prepare('INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,before_json,after_json) VALUES (?,?,?,?,?,?)')->execute([$actor['id'],$action,$entity,$id,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);
    }
    public function categories(array $actor): array
    {
        $this->admin($actor);return $this->pdo->query('SELECT id,name,is_active FROM categories ORDER BY name,id')->fetchAll();
    }
    public function category(array $actor,mixed $name): string
    {
        $this->admin($actor);
        if(!is_string($name) || !mb_check_encoding($name,'UTF-8') || trim($name)==='' || mb_strlen(trim($name),'UTF-8')>100 || preg_match('/[\x00-\x1F\x7F]/',$name)){throw new ProductException(['category_name'=>'Enter a category name of 1–100 characters.']);}
        return $this->transaction(function()use($actor,$name){$this->pdo->prepare('INSERT INTO categories(name) VALUES (?)')->execute([trim($name)]);$id=$this->pdo->lastInsertId();$this->audit($actor,'CATEGORY_CREATE','categories',$id,null,['name'=>trim($name)]);return $id;});
    }
    public function get(array $actor,string $id,bool $lock=false): array
    {
        $this->admin($actor);
        if(!preg_match('/^[1-9][0-9]{0,18}$/D',$id)){throw new ProductException(['id'=>'Product not found.'],404);}
        $q=$this->pdo->prepare('SELECT p.*,c.name category_name,c.is_active category_active FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$row=$q->fetch();
        if(!$row){throw new ProductException(['id'=>'Product not found.'],404);}return $row;
    }
    public function listing(array $actor,array $filters): array
    {
        $this->admin($actor);$where=[];$params=[];
        $search=$filters['q']??'';$status=$filters['status']??'all';$category=$filters['category_id']??'';$page=$filters['page']??'1';
        if(!is_string($search)||!mb_check_encoding($search,'UTF-8')||mb_strlen($search,'UTF-8')>100||!is_string($status)||!in_array($status,['all','active','inactive'],true)||!is_string($category)||($category!==''&&!preg_match('/^[1-9][0-9]{0,18}$/D',$category))||!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page)){throw new ProductException(['filters'=>'Invalid search or filter.']);}
        if(trim($search)!==''){$where[]="(p.sku LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!' OR p.name_si LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM product_sku_history h WHERE h.product_id=p.id AND h.sku LIKE ? ESCAPE '!'))";$like='%'.strtr(trim($search),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$params[]=$like;$params[]=$like;$params[]=$like;$params[]=$like;}
        if($status!=='all'){$where[]='p.is_active=?';$params[]=$status==='active'?'1':'0';}
        if($category!==''){$where[]='p.category_id=?';$params[]=$category;}
        $sql=$where?' WHERE '.implode(' AND ',$where):'';
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM products p'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();
        $pages=max(1,intdiv($total+19,20));$number=min((int)$page,$pages);$offset=($number-1)*20;
        $q=$this->pdo->prepare('SELECT p.id,p.sku,p.name,p.name_si,p.unit_of_measure,p.selling_price,p.cost_price,p.is_active,p.version,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id'.$sql.' ORDER BY p.name,p.id LIMIT 20 OFFSET '.$offset);$q->execute($params);
        return ['products'=>$q->fetchAll(),'total'=>$total,'page'=>$number,'pages'=>$pages,'currency'=>'LKR'];
    }
    private function reserveSku(string $sku,string $id): void
    {
        $q=$this->pdo->prepare('SELECT product_id FROM product_sku_history WHERE sku=?');$q->execute([$sku]);$old=$q->fetchColumn();
        if($old!==false && (string)$old!==$id){throw new ProductException(['sku'=>'This SKU was already used by another product.']);}
        if($old===false){$this->pdo->prepare('INSERT INTO product_sku_history(sku,product_id) VALUES (?,?)')->execute([$sku,$id]);}
    }
    public function save(array $actor,array $input,?string $id=null): array
    {
        $this->admin($actor);
        $automatic=$id===null&&is_string($input['sku']??'')&&trim($input['sku']??'')==='';
        if($id===null&&is_string($input['sku']??'')&&trim($input['sku']??'')===''){
            if(!is_string($input['create_token']??null)||$input['create_token']==='')throw new ProductException(['sku'=>'Refresh the create form. Automatic codes require its unique submission token.']);
            try{$input['sku']=\App\Support\BusinessCode::generate('PRD',$input['create_token']??null);}catch(\InvalidArgumentException $e){throw new ProductException(['sku'=>$e->getMessage()]);}
        }
        $values=self::validate($input);
        return $this->transaction(function()use($actor,$input,$id,$values,$automatic){
            $before=$id===null?null:$this->get($actor,$id,true);
            if($before && (!is_string($input['version']??null) || $input['version']!==(string)$before['version'])){throw new ProductException(['version'=>'This product changed. Reload it before saving.'],409);}
            $q=$this->pdo->prepare('SELECT is_active FROM categories WHERE id=? FOR UPDATE');$q->execute([$values['category_id']]);$active=$q->fetchColumn();
            if($active===false || (!(int)$active && (!$before || (string)$before['category_id']!==$values['category_id']))){throw new ProductException(['category_id'=>'Choose an active category.']);}
            if($automatic)$values['sku']=\App\Support\BusinessCode::next($this->pdo,'P',$input['create_token']);
            $params=[$values['sku'],$values['name'],$values['category_id'],$values['unit_of_measure'],$values['selling_price'],$values['cost_price'],$values['description']];
            $onlyTranslation=$before!==null;foreach(['sku','name','category_id','unit_of_measure','selling_price','cost_price','description'] as $field)if((string)($before[$field]??'')!==(string)$values[$field])$onlyTranslation=false;
            if($before&&!$onlyTranslation){$this->reserveSku($before['sku'],$id);$this->reserveSku($values['sku'],$id);$params[]=$id;$this->pdo->prepare('UPDATE products SET sku=?,name=?,category_id=?,unit_of_measure=?,selling_price=?,cost_price=?,description=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute($params);}
            elseif(!$before){$this->pdo->prepare('INSERT INTO products(sku,name,category_id,unit_of_measure,selling_price,cost_price,description) VALUES (?,?,?,?,?,?,?)')->execute($params);$id=$this->pdo->lastInsertId();$this->reserveSku($values['sku'],$id);}
            if(array_key_exists('name_si',$values))$this->pdo->prepare('UPDATE products SET name_si=? WHERE id=?')->execute([$values['name_si'],$id]);
            $after=$this->get($actor,$id);$this->audit($actor,$before?'PRODUCT_UPDATE':'PRODUCT_CREATE','products',$id,$before,$after);return $after;
        });
    }
    public function status(array $actor,string $id,mixed $version,mixed $active): array
    {
        $this->admin($actor);
        if(!is_string($version)||!in_array($active,['0','1'],true)){throw new ProductException(['status'=>'Invalid status request.']);}
        return $this->transaction(function()use($actor,$id,$version,$active){$before=$this->get($actor,$id,true);if((string)$before['version']!==$version){throw new ProductException(['version'=>'This product changed. Reload before changing its status.'],409);}
            $this->pdo->prepare('UPDATE products SET is_active=?,archived_at_utc=IF(?=0,UTC_TIMESTAMP(6),NULL),version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute([$active,$active,$id]);$after=$this->get($actor,$id);$this->audit($actor,$active==='1'?'PRODUCT_ACTIVATE':'PRODUCT_DEACTIVATE','products',$id,$before,$after);return $after;});
    }
}
