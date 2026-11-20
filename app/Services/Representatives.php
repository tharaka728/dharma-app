<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;
final class Representatives
{
    public function __construct(private PDO $pdo){}
    public function admin(array $actor):void{(new Inventory($this->pdo))->admin($actor);}
    private function one(string $sql,array $params=[]):?array{$q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetch()?:null;}
    public static function id(mixed $id,string $field='rep_id'):string{if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,18}$/D',$id))throw new InventoryException([$field=>'Choose a valid record.']);return $id;}
    public function get(array $actor,string $id,bool $lock=false):array
    {
        $this->admin($actor);self::id($id);return $this->one("SELECT (SELECT code FROM business_code_registry WHERE prefix=0x52 AND request_key=CAST(users.id AS CHAR)) reference_code,id,name,username,phone,assigned_area,is_active,version FROM users WHERE id=? AND role='SALES_REP'".($lock?' FOR UPDATE':''),[$id])??throw new InventoryException(['representative'=>'Sales representative not found.'],404);
    }
    public function listing(array $actor,array $filters):array
    {
        $this->admin($actor);$search=Inventory::text($filters['q']??'','q',100);$status=$filters['status']??'all';$page=$filters['page']??'1';
        if(!is_string($status)||!in_array($status,['all','active','inactive'],true)||!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['filters'=>'Invalid status or page.']);
        $sql=" FROM users WHERE role='SALES_REP'";$params=[];if($search!==''){$like='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$sql.=" AND (name LIKE ? ESCAPE '!' OR username LIKE ? ESCAPE '!' OR phone LIKE ? ESCAPE '!' OR assigned_area LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM business_code_registry b WHERE b.prefix=0x52 AND b.request_key=CAST(users.id AS CHAR) AND b.code LIKE ? ESCAPE '!'))";$params=[$like,$like,$like,$like,$like];}if($status!=='all'){$sql.=' AND is_active=?';$params[]=$status==='active'?'1':'0';}
        $q=$this->pdo->prepare('SELECT COUNT(*)'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,intdiv($total+24,25));$page=min((int)$page,$pages);$q=$this->pdo->prepare('SELECT (SELECT code FROM business_code_registry WHERE prefix=0x52 AND request_key=CAST(users.id AS CHAR)) reference_code,id,name,username,phone,assigned_area,is_active,version'.$sql.' ORDER BY name,id LIMIT 25 OFFSET '.(($page-1)*25));$q->execute($params);return ['rows'=>$q->fetchAll(),'total'=>$total,'pages'=>$pages,'page'=>$page];
    }
    private function atomic(callable $fn):mixed
    {
        $own=!$this->pdo->inTransaction();if($own){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$this->pdo->beginTransaction();}else $this->pdo->exec('SAVEPOINT representative_command');
        try{$this->pdo->query('SELECT id FROM inventory_settings WHERE id=1 FOR UPDATE')->fetchColumn();$result=$fn();if($own)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT representative_command');return $result;}
        catch(\Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$own&&$this->pdo->inTransaction())$this->pdo->exec('ROLLBACK TO SAVEPOINT representative_command');$code=$e instanceof \PDOException?(int)($e->errorInfo[1]??0):0;if($code===1062)throw new InventoryException(['username'=>'Username is already in use, including inactive accounts.'],409);if(in_array($code,[1205,1213],true))throw new InventoryException(['conflict'=>'Another account/stock change is in progress. Reload and retry.'],409);throw $e;}
    }
    private function audit(array $actor,string $action,string $id,?array $before,array $after):void
    {
        // Whitelisted profiles only. Passwords/hashes are deliberately never audited.
        $this->pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,before_json,after_json) VALUES (?,?,'users',?,?,?)")->execute([$actor['id'],$action,$id,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);
    }
    private function password(array $input):string
    {
        $password=$input['password']??null;$confirm=$input['password_confirmation']??null;
        if(!is_string($password)||strlen($password)<12||strlen($password)>72||strpos($password,"\0")!==false||!is_string($confirm)||!hash_equals($password,$confirm))throw new InventoryException(['password'=>'Enter matching passwords of 12–72 bytes.']);
        return password_hash($password,PASSWORD_DEFAULT);
    }
    public function save(array $actor,array $input,?string $id=null):array
    {
        $this->admin($actor);foreach(['role','is_active','password_hash'] as $blocked)if(array_key_exists($blocked,$input))throw new InventoryException([$blocked=>'This field cannot be set here.']);
        $name=Inventory::text($input['name']??'','name',150,true);$username=strtolower(Inventory::text($input['username']??'','username',100,true));if(!preg_match('/^[a-z0-9_.-]{3,100}$/D',$username))throw new InventoryException(['username'=>'Use 3–100 letters, digits, dots, underscores or hyphens.']);
        $phone=Inventory::text($input['phone']??'','phone',40,true);try{Customers::phone($phone);}catch(\App\Support\CustomerException $e){throw new InventoryException(['phone'=>'Enter a valid contact phone number.']);}$area=Inventory::text($input['assigned_area']??'','assigned_area',150);
        $hash=$id===null?$this->password($input):null;
        return $this->atomic(function()use($actor,$input,$id,$name,$username,$phone,$area,$hash){
            $before=$id===null?null:$this->get($actor,$id,true);if($before&&($input['version']??null)!==(string)$before['version'])throw new InventoryException(['version'=>'Representative changed. Reload before saving.'],409);
            if($before){$this->pdo->prepare('UPDATE users SET name=?,username=?,phone=?,assigned_area=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute([$name,$username,$phone,$area,$id]);}
            else{$this->pdo->prepare("INSERT INTO users(name,username,phone,assigned_area,password_hash,role) VALUES (?,?,?,?,?,'SALES_REP')")->execute([$name,$username,$phone,$area,$hash]);$id=$this->pdo->lastInsertId();\App\Support\BusinessCode::next($this->pdo,'R',(string)$id);}
            $after=$this->get($actor,$id);$this->audit($actor,$before?'REP_UPDATE':'REP_CREATE',$id,$before,$after);return $after;
        });
    }
    public function status(array $actor,string $id,array $input):array
    {
        $this->admin($actor);$active=$input['is_active']??null;if(!in_array($active,['0','1'],true))throw new InventoryException(['status'=>'Choose active or inactive.']);
        return $this->atomic(function()use($actor,$id,$input,$active){$before=$this->get($actor,$id,true);if(($input['version']??null)!==(string)$before['version'])throw new InventoryException(['version'=>'Representative changed. Reload before changing status.'],409);$this->pdo->prepare('UPDATE users SET is_active=?,archived_at_utc=IF(?=0,UTC_TIMESTAMP(6),NULL),version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute([$active,$active,$id]);$after=$this->get($actor,$id);$this->audit($actor,'REP_STATUS',$id,$before,$after);return $after;});
    }
    public function reset(array $actor,string $id,array $input):void
    {
        $this->admin($actor);$hash=$this->password($input);
        $this->atomic(function()use($actor,$id,$input,$hash){$before=$this->get($actor,$id,true);if(($input['version']??null)!==(string)$before['version'])throw new InventoryException(['version'=>'Representative changed. Reload before resetting the password.'],409);$this->pdo->prepare('UPDATE users SET password_hash=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute([$hash,$id]);$this->audit($actor,'REP_PASSWORD_RESET',$id,null,['sessions_revoked'=>true]);});
    }
}
