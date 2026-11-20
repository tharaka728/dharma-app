<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\CustomerException;
final class Locations
{
    public function __construct(private PDO $pdo) {}
    public function districts(array $actor):array
    {
        (new Customers($this->pdo))->admin($actor);
        $rows=$this->pdo->query('SELECT id,name FROM location_districts ORDER BY name')->fetchAll();
        foreach($rows as &$row){$row['id']=(string)$row['id'];}return $rows;
    }
    private function district(mixed $id):array
    {
        if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,9}$/D',$id)){throw new CustomerException(['district_id'=>'Choose a valid Sri Lankan district.']);}
        $q=$this->pdo->prepare('SELECT id,name FROM location_districts WHERE id=?');$q->execute([$id]);$row=$q->fetch();
        if(!$row){throw new CustomerException(['district_id'=>'Choose a listed Sri Lankan district.']);}return $row;
    }
    public function filterSelection(array $actor,array $filters):array
    {
        (new Customers($this->pdo))->admin($actor);
        $district=$filters['district_id']??'';$town=$filters['town_id']??'';
        foreach(['district_id'=>$district,'town_id'=>$town] as $key=>$value){if(!is_string($value)||($value!==''&&!preg_match('/^[1-9][0-9]{0,18}$/D',$value))){throw new CustomerException([$key=>'Choose a valid listed location.']);}}
        if($district!==''){$this->district($district);}
        $selected=null;
        if($town!==''){$q=$this->pdo->prepare('SELECT t.id,t.district_id,t.name,d.name district_name FROM location_towns t JOIN location_districts d ON d.id=t.district_id WHERE t.id=?');$q->execute([$town]);$selected=$q->fetch();
            if(!$selected||($district!==''&&(string)$selected['district_id']!==$district)){throw new CustomerException(['town_id'=>'Choose a town belonging to the selected district, or select All cities / towns.']);}
            $selected['id']=(string)$selected['id'];$selected['district_id']=(string)$selected['district_id'];
        }
        return ['district_id'=>$district,'town_id'=>$town,'selected'=>$selected];
    }
    public function filterForm(array $actor,array $filters):array
    {
        $districts=$this->districts($actor);
        try{$selection=$this->filterSelection($actor,$filters);$result=$this->towns($actor,['district_id'=>$selection['district_id'],'scope'=>'all']);
            if($selection['selected']&&!in_array($selection['town_id'],array_column($result['towns'],'id'),true)){array_unshift($result['towns'],$selection['selected']);}
            return $result+['districts'=>$districts]+$selection;
        }catch(CustomerException $e){if($e->status===403){throw $e;}return ['districts'=>$districts,'towns'=>[],'has_more'=>false,'district_id'=>'','town_id'=>'','selected'=>null];}
    }
    public function towns(array $actor,array $filters):array
    {
        (new Customers($this->pdo))->admin($actor);$all=($filters['scope']??null)==='all'&&($filters['district_id']??'')==='';$district=$all?null:$this->district($filters['district_id']??null);$search=$filters['q']??'';$page=$filters['page']??'1';
        if(!is_string($search)||!mb_check_encoding($search,'UTF-8')||mb_strlen($search,'UTF-8')>100||!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page)){throw new CustomerException(['search'=>'Invalid town search or page.']);}
        $like='%'.strtr(trim($search),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$params=$all?[$like]:[$district['id'],$like];$where=($all?'':'t.district_id=? AND ')."t.name LIKE ? ESCAPE '!'";
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM location_towns t WHERE '.$where);$q->execute($params);$total=(int)$q->fetchColumn();$offset=((int)$page-1)*50;
        $q=$this->pdo->prepare('SELECT t.id,t.district_id,t.name,d.name district_name FROM location_towns t JOIN location_districts d ON d.id=t.district_id WHERE '.$where.' ORDER BY t.name,d.name,t.id LIMIT 50 OFFSET '.$offset);$q->execute($params);$rows=$q->fetchAll();foreach($rows as &$row){$row['id']=(string)$row['id'];$row['district_id']=(string)$row['district_id'];}
        return ['towns'=>$rows,'total'=>$total,'page'=>(int)$page,'has_more'=>$offset+count($rows)<$total];
    }
    public function create(array $actor,mixed $districtId,mixed $name):array
    {
        (new Customers($this->pdo))->admin($actor);$district=$this->district($districtId);
        if(!is_string($name)||!mb_check_encoding($name,'UTF-8')){throw new CustomerException(['town_name'=>'Enter a valid town name.']);}
        $name=preg_replace('/^\s+|\s+$/u','',$name);
        if($name===''||mb_strlen($name,'UTF-8')>100||preg_match('/[\x00-\x1F\x7F]/',$name)){throw new CustomerException(['town_name'=>'Enter a town name of 1–100 characters without control characters.']);}
        $owner=!$this->pdo->inTransaction();if($owner){$this->pdo->beginTransaction();}else{$this->pdo->exec('SAVEPOINT town_create');}
        try{
            $this->pdo->prepare('INSERT INTO location_towns(district_id,name,created_by_user_id) VALUES (?,?,?)')->execute([$district['id'],$name,$actor['id']]);$id=$this->pdo->lastInsertId();
            $town=['id'=>$id,'district_id'=>(string)$district['id'],'name'=>$name];
            $this->pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,after_json) VALUES (?,'TOWN_CREATE','location_towns',?,?)")->execute([$actor['id'],$id,json_encode($town,JSON_THROW_ON_ERROR)]);
            if($owner){$this->pdo->commit();}else{$this->pdo->exec('RELEASE SAVEPOINT town_create');}return $town;
        }catch(\Throwable $e){if($owner&&$this->pdo->inTransaction()){$this->pdo->rollBack();}elseif(!$owner&&$this->pdo->inTransaction()){$this->pdo->exec('ROLLBACK TO SAVEPOINT town_create');}
            if($e instanceof \PDOException&&(int)($e->errorInfo[1]??0)===1062){throw new CustomerException(['town_name'=>'This town already exists in the selected district. Search for and select it.'],409);}throw $e;}
    }
    public function resolve(array $input):array
    {
        if(array_key_exists('district_id',$input)||array_key_exists('town_id',$input)){
            $district=$this->district($input['district_id']??null);$town=$input['town_id']??null;
            if(!is_string($town)||!preg_match('/^[1-9][0-9]{0,18}$/D',$town)){throw new CustomerException(['town_id'=>'Choose a town from the selected district.']);}
            $q=$this->pdo->prepare('SELECT id,name FROM location_towns WHERE id=? AND district_id=?');$q->execute([$town,$district['id']]);$match=$q->fetch();
        }else{
            // Compatibility for existing API clients: exact pair only, never infer
            // a district from a town or create lookups from untrusted legacy text.
            $d=$input['district']??'';$t=$input['city_town']??'';
            if(!is_string($d)||!is_string($t)){throw new CustomerException(['location'=>'Choose a district and town.']);}
            $q=$this->pdo->prepare('SELECT id,name FROM location_districts WHERE LOWER(name) COLLATE utf8mb4_bin=LOWER(TRIM(CONVERT(? USING utf8mb4))) COLLATE utf8mb4_bin');$q->execute([$d]);$district=$q->fetch();
            if(!$district){throw new CustomerException(['district_id'=>'Choose a listed district; the legacy address needs review.']);}
            $q=$this->pdo->prepare('SELECT id,name FROM location_towns WHERE district_id=? AND name_key COLLATE utf8mb4_bin=LOWER(TRIM(CONVERT(? USING utf8mb4))) COLLATE utf8mb4_bin');$q->execute([$district['id'],$t]);$match=$q->fetch();
        }
        if(!$match){throw new CustomerException(['town_id'=>'This town does not belong to the selected district. Select an existing town or use + Add New Town.']);}
        return ['district_id'=>(string)$district['id'],'town_id'=>(string)$match['id'],'district'=>$district['name'],'city_town'=>$match['name']];
    }
    public function form(array $actor,array $input):array
    {
        $districts=$this->districts($actor);$options=[];$more=false;
        $d=$input['district_id']??null;$t=$input['town_id']??null;
        if(is_int($d)){$d=(string)$d;}if(is_int($t)){$t=(string)$t;}
        try{$result=$this->towns($actor,['district_id'=>$d]);$options=$result['towns'];$more=$result['has_more'];
            if(is_string($t)&&preg_match('/^[1-9][0-9]{0,18}$/D',$t)){$q=$this->pdo->prepare('SELECT id,district_id,name FROM location_towns WHERE id=? AND district_id=?');$q->execute([$t,$d]);$selected=$q->fetch();if($selected&&!in_array($t,array_column($options,'id'),true)){$selected['id']=(string)$selected['id'];$selected['district_id']=(string)$selected['district_id'];array_unshift($options,$selected);}}
        }catch(CustomerException $e){if($e->status===403){throw $e;}}
        return ['districts'=>$districts,'towns'=>$options,'has_more'=>$more];
    }
}
