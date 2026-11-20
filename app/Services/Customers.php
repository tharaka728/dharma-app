<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\CustomerException;use App\Support\ProductException;
final class Customers
{
    public const TYPES=['PHARMACY'=>'Pharmacy','AYURVEDA_SHOP'=>'Ayurveda shop','GENERAL_RETAIL'=>'General retail shop','DISTRIBUTOR'=>'Distributor','OTHER'=>'Other'];
    public function __construct(private PDO $pdo) {}
    public function admin(array $actor):void
    {
        try{(new Products($this->pdo))->admin($actor);}catch(ProductException $e){throw new CustomerException($e->errors,403);}
    }
    public static function phone(string $value):string
    {
        if(!preg_match('/^\+?[0-9 ().-]+$/D',$value)){throw new CustomerException(['phone'=>'Use a phone number with digits and optional +, spaces, dots, parentheses or hyphens.']);}
        $digits=preg_replace('/[^0-9]/','',$value);
        if(strpos($digits,'00')===0){$digits=substr($digits,2);}
        elseif(strlen($digits)===10 && $digits[0]==='0'){$digits='94'.substr($digits,1);}
        if(strlen($digits)<7||strlen($digits)>15||$digits[0]==='0'){throw new CustomerException(['phone'=>'Use 7–15 international digits, or a 10-digit Sri Lankan number beginning with 0.']);}
        return $digits;
    }
    public static function validate(array $input,bool $requireLocationText=true):array
    {
        $out=[];$errors=[];
        foreach(['customer_code'=>64,'shop_name'=>200,'contact_person'=>150,'phone'=>40,'alternate_phone'=>40,'address'=>2000,'city_town'=>100,'district'=>100,'notes'=>5000] as $key=>$max){
            $value=$input[$key]??'';$optional=in_array($key,['alternate_phone','notes'],true)||(!$requireLocationText&&in_array($key,['district','city_town'],true));
            if(!is_string($value)||!mb_check_encoding($value,'UTF-8')){$errors[$key]='Enter valid text.';continue;}
            $value=trim($value);
            if((!$optional&&$value==='')||mb_strlen($value,'UTF-8')>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value)){$errors[$key]="Enter ".($optional?'up to':'1 to')." $max characters.";}
            $out[$key]=$value;
        }
        if(!isset($out['customer_code'])||!preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,63}$/D',$out['customer_code'])){$errors['customer_code']='Use 1–64 letters, numbers, dots, slashes, underscores or hyphens, beginning with a letter or number.';}else{$out['customer_code']=strtoupper($out['customer_code']);}
        foreach(['phone','alternate_phone'] as $key){if(isset($out[$key])&&$out[$key]!==''){try{$out[$key.'_normalized']=self::phone($out[$key]);}catch(CustomerException $e){$errors[$key]=$e->errors['phone'];}}else{$out[$key.'_normalized']=null;}}
        if(($out['alternate_phone_normalized']??null)!==null && $out['alternate_phone_normalized']===($out['phone_normalized']??null)){$errors['alternate_phone']='Alternate phone must differ from the primary phone.';}
        $type=$input['customer_type']??null;if(!is_string($type)||!isset(self::TYPES[$type])){$errors['customer_type']='Choose a listed customer type.';}else{$out['customer_type']=$type;}
        $days=$input['credit_terms_days']??'';if(!is_string($days)||!preg_match('/^(0|[1-9][0-9]{0,3})$/D',$days)||(int)$days>3650){$errors['credit_terms_days']='Enter a whole number from 0 to 3650 days.';}else{$out['credit_terms_days']=$days;}
        try{$out['credit_limit']=Products::money($input['credit_limit']??'',true);}catch(ProductException $e){$errors['credit_limit']='Enter a nonnegative LKR decimal with at most 13 whole digits and 2 decimal places, or leave blank.';}
        if($errors){throw new CustomerException($errors);}return $out;
    }
    private function transaction(callable $fn):mixed
    {
        $owner=!$this->pdo->inTransaction();
        for($attempt=0;;$attempt++){
            if($owner){$this->pdo->beginTransaction();}else{$this->pdo->exec('SAVEPOINT customer_command');}
            try{$result=$fn();if($owner){$this->pdo->commit();}else{$this->pdo->exec('RELEASE SAVEPOINT customer_command');}return $result;}
            catch(\Throwable $e){if($owner&&$this->pdo->inTransaction()){$this->pdo->rollBack();}elseif(!$owner&&$this->pdo->inTransaction()){$this->pdo->exec('ROLLBACK TO SAVEPOINT customer_command');}
                $code=$e instanceof \PDOException?(int)($e->errorInfo[1]??0):0;
                if($owner&&in_array($code,[1213,1205],true)&&$attempt<2){continue;}
                if(in_array($code,[1213,1205],true)){throw new CustomerException(['conflict'=>'Another customer change is in progress. Reload and retry.'],409);}
                if($code===1062){throw new CustomerException(['customer_code'=>'This customer code is already reserved. Codes cannot be reused.']);}throw $e;}
        }
    }
    private function audit(array $actor,string $action,string $id,?array $before,array $after,array $duplicateInfo=[]):void
    {
        $this->pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,before_json,after_json) VALUES (?,?,'customers',?,?,?)")->execute([$actor['id'],$action,$id,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),json_encode(['customer'=>$after,'shared_phone_confirmation'=>$duplicateInfo],JSON_THROW_ON_ERROR)]);
    }
    public function get(array $actor,string $id,bool $lock=false):array
    {
        $this->admin($actor);if(!preg_match('/^[1-9][0-9]{0,18}$/D',$id)){throw new CustomerException(['id'=>'Customer not found.'],404);}
        $q=$this->pdo->prepare('SELECT * FROM customers WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$row=$q->fetch();if(!$row){throw new CustomerException(['id'=>'Customer not found.'],404);}
        if(!(int)$row['credit_limit_enabled']){$row['credit_limit']=null;}return $row;
    }
    public function listing(array $actor,array $filters):array
    {
        $this->admin($actor);$params=[];$where=[];
        $location=(new Locations($this->pdo))->filterSelection($actor,$filters);
        foreach(['district_id','town_id'] as $key){if($location[$key]!==''){$where[]=$key.'=?';$params[]=$location[$key];}}
        $value=$filters['q']??'';
        if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value,'UTF-8')>100){throw new CustomerException(['filters'=>'Invalid search.']);}
        if(trim($value)!==''){$like='%'.strtr(trim($value),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(customer_code LIKE ? ESCAPE '!' OR shop_name LIKE ? ESCAPE '!' OR contact_person LIKE ? ESCAPE '!' OR phone LIKE ? ESCAPE '!' OR alternate_phone LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM customer_code_history h WHERE h.customer_id=customers.id AND h.customer_code LIKE ? ESCAPE '!'))";array_push($params,$like,$like,$like,$like,$like,$like);}
        $type=$filters['customer_type']??'';$status=$filters['status']??'all';$page=$filters['page']??'1';
        if(!is_string($type)||($type!==''&&!isset(self::TYPES[$type]))||!is_string($status)||!in_array($status,['all','active','inactive'],true)||!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page)){throw new CustomerException(['filters'=>'Invalid type, status or page.']);}
        if($type!==''){$where[]='customer_type=?';$params[]=$type;}if($status!=='all'){$where[]='is_active=?';$params[]=$status==='active'?'1':'0';}
        $sql=$where?' WHERE '.implode(' AND ',$where):'';$q=$this->pdo->prepare('SELECT COUNT(*) FROM customers'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,intdiv($total+19,20));$number=min((int)$page,$pages);$offset=($number-1)*20;
        $q=$this->pdo->prepare('SELECT id,customer_code,shop_name,contact_person,phone,city_town,district,customer_type,is_active,version FROM customers'.$sql.' ORDER BY shop_name,id LIMIT 20 OFFSET '.$offset);$q->execute($params);
        return ['customers'=>$q->fetchAll(),'total'=>$total,'page'=>$number,'pages'=>$pages,'currency'=>'LKR'];
    }
    private function reserveCode(string $code,string $id):void
    {
        $q=$this->pdo->prepare('SELECT customer_id FROM customer_code_history WHERE customer_code=?');$q->execute([$code]);$owner=$q->fetchColumn();if($owner!==false&&(string)$owner!==$id){throw new CustomerException(['customer_code'=>'This customer code belongs to another customer, including its history.']);}
        if($owner===false){$this->pdo->prepare('INSERT INTO customer_code_history(customer_code,customer_id) VALUES (?,?)')->execute([$code,$id]);}
    }
    private function duplicates(array $values,?string $id):array
    {
        $phones=array_values(array_filter([$values['phone_normalized'],$values['alternate_phone_normalized']]));
        $marks=implode(',',array_fill(0,count($phones),'?'));
        $q=$this->pdo->prepare("SELECT id,customer_code,shop_name,phone,alternate_phone FROM customers WHERE id<>? AND (phone_normalized IN ($marks) OR alternate_phone_normalized IN ($marks) OR phone_normalized IS NULL OR (alternate_phone<>'' AND alternate_phone_normalized IS NULL)) ORDER BY id FOR UPDATE");$q->execute(array_merge([$id??'0'],$phones,$phones));
        $matches=[];foreach($q->fetchAll() as $row){foreach(['phone','alternate_phone'] as $field){if(!empty($row[$field])){try{if(in_array(self::phone($row[$field]),$phones,true)){$matches[]=['id'=>$row['id'],'customer_code'=>$row['customer_code'],'shop_name'=>$row['shop_name']];break;}}catch(CustomerException $e){}}}}return $matches;
    }
    public function save(array $actor,array $input,?string $id=null):array
    {
        $this->admin($actor);
        $automatic=$id===null&&is_string($input['customer_code']??'')&&trim($input['customer_code']??'')==='';
        if($automatic){
            if(!is_string($input['create_token']??null)||$input['create_token']==='')throw new CustomerException(['customer_code'=>'Refresh the create form. Automatic codes require its unique submission token.']);
            try{$input['customer_code']=\App\Support\BusinessCode::generate('CUS',$input['create_token']??null);}catch(\InvalidArgumentException $e){throw new CustomerException(['customer_code'=>$e->getMessage()]);}
        }
        $values=self::validate($input,false);
        $values=array_merge($values,(new Locations($this->pdo))->resolve($input));
        foreach(['assigned_rep_id','area','is_active'] as $protected){if(array_key_exists($protected,$input)){throw new CustomerException([$protected=>'This field is not editable in Customer Management.']);}}
        return $this->transaction(function()use($actor,$input,$id,$values,$automatic){
            $previous=$id===null?null:$this->get($actor,$id);
            $phones=[$values['phone_normalized'],$values['alternate_phone_normalized']];
            if($previous){foreach(['phone','alternate_phone'] as $key){if(!empty($previous[$key])){try{$phones[]=self::phone($previous[$key]);}catch(CustomerException $e){}}}}
            $keys=[];foreach(array_filter($phones) as $phone){$keys[hash('sha256',$phone)]=true;}ksort($keys,SORT_STRING);
            foreach(array_keys($keys) as $key){$this->pdo->prepare('INSERT INTO customer_phone_locks(phone_hash) VALUES (?) ON DUPLICATE KEY UPDATE phone_hash=phone_hash')->execute([$key]);}
            $before=$id===null?null:$this->get($actor,$id,true);
            if($before&&(!is_string($input['version']??null)||$input['version']!==(string)$before['version'])){throw new CustomerException(['version'=>'This customer changed. Reload before saving.'],409);}
            if($automatic)$values['customer_code']=\App\Support\BusinessCode::next($this->pdo,'C',$input['create_token']);
            $codeCheck=$this->pdo->prepare('SELECT customer_id FROM customer_code_history WHERE customer_code=?');$codeCheck->execute([$values['customer_code']]);$codeOwner=$codeCheck->fetchColumn();
            if($codeOwner!==false&&(string)$codeOwner!==$id){throw new CustomerException(['customer_code'=>'This customer code is already reserved, including historical codes.']);}
            $duplicates=$this->duplicates($values,$id);$confirmation=[];
            if($duplicates){$reason=$input['duplicate_phone_reason']??'';
                if(($input['duplicate_phone_confirmed']??null)!=='1'||!is_string($reason)||!mb_check_encoding($reason,'UTF-8')||mb_strlen(trim($reason),'UTF-8')<10||mb_strlen($reason,'UTF-8')>500||preg_match('/[\x00-\x1F\x7F]/',$reason)){throw new CustomerException(['duplicate_phone'=>'Potential duplicate phone number. Review the matching customers, then explicitly confirm shared contact details with a reason of 10–500 characters.'],409,$duplicates);}
                $confirmation=['customer_ids'=>array_column($duplicates,'id'),'reason'=>trim($reason)];
            }
            $fields=['customer_code','shop_name','contact_person','phone','alternate_phone','phone_normalized','alternate_phone_normalized','address','city_town','district','district_id','town_id','customer_type','notes','credit_terms_days'];
            $params=[];foreach($fields as $field){$params[]=$values[$field];}$params[]=$values['credit_limit']??'0.00';$params[]=$values['credit_limit']===null?'0':'1';
            if($before){$this->reserveCode($before['customer_code'],$id);$this->reserveCode($values['customer_code'],$id);$params[]=$id;$assign=implode(',',array_map(static fn($field)=>$field.'=?',$fields));$this->pdo->prepare('UPDATE customers SET '.$assign.',credit_limit=?,credit_limit_enabled=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute($params);}
            else{$params[]=$values['city_town'];$names=implode(',',$fields).',credit_limit,credit_limit_enabled,area';$marks=implode(',',array_fill(0,count($params),'?'));$this->pdo->prepare("INSERT INTO customers($names) VALUES ($marks)")->execute($params);$id=$this->pdo->lastInsertId();$this->reserveCode($values['customer_code'],$id);}
            $after=$this->get($actor,$id);$this->audit($actor,$before?'CUSTOMER_UPDATE':'CUSTOMER_CREATE',$id,$before,$after,$confirmation);return $after;
        });
    }
    public function status(array $actor,string $id,mixed $version,mixed $active):array
    {
        $this->admin($actor);if(!is_string($version)||!in_array($active,['0','1'],true)){throw new CustomerException(['status'=>'Invalid status request.']);}
        return $this->transaction(function()use($actor,$id,$version,$active){$before=$this->get($actor,$id,true);if((string)$before['version']!==$version){throw new CustomerException(['version'=>'This customer changed. Reload before changing status.'],409);}
            $this->pdo->prepare('UPDATE customers SET is_active=?,archived_at_utc=IF(?=0,UTC_TIMESTAMP(6),NULL),version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute([$active,$active,$id]);$after=$this->get($actor,$id);$this->audit($actor,$active==='1'?'CUSTOMER_ACTIVATE':'CUSTOMER_DEACTIVATE',$id,$before,$after);return $after;});
    }
}
