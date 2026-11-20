<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Support\Database;use App\Services\Customers;use App\Support\CustomerException;use App\Controllers\CustomerController;
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||!in_array(getenv('DB_NAME'),['dharma_sales_phase1','dharma_sales_phase9_test'],true)){throw new RuntimeException('Explicit app-only test target required.');}
$pdo=Database::connect();$service=new Customers($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok){throw new RuntimeException($label);}$count++;};
$reject=static function(callable $fn,int $status,string $label)use($check){try{$fn();}catch(CustomerException $e){$check($e->status===$status,$label);return;}throw new RuntimeException('Unexpectedly accepted: '.$label);};
$before=[];foreach(['customers','customer_code_history','customer_phone_locks','audit_log','users'] as $table){$before[$table]=$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();}
$pdo->beginTransaction();
try{
 $prefix='C4TEST'.strtoupper(bin2hex(random_bytes(6)));
 foreach(['ADMIN','SALES_REP'] as $role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Rollback-only test',$prefix.$role,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$role]);$actors[$role]=['id'=>$pdo->lastInsertId(),'name'=>'Rollback-only test'];}
 $admin=$actors['ADMIN'];$rep=$actors['SALES_REP'];
 $phone='+999'.str_pad((string)random_int(1,999999999),9,'0',STR_PAD_LEFT);
 $location=$pdo->query("SELECT t.id town_id,d.id district_id FROM location_towns t JOIN location_districts d ON d.id=t.district_id WHERE d.name='Colombo' AND t.name='Colombo'")->fetch();
 $input=['customer_code'=>$prefix,'shop_name'=>'Rollback-only <shop>','contact_person'=>'Test contact','phone'=>$phone,'alternate_phone'=>'','address'=>'Rollback-only address','city_town'=>'Colombo','district'=>'Colombo','district_id'=>(string)$location['district_id'],'town_id'=>(string)$location['town_id'],'customer_type'=>'PHARMACY','notes'=>'Rollback-only notes','credit_terms_days'=>'30','credit_limit'=>''];
 foreach(Customers::TYPES as $type=>$label){$candidate=$input;$candidate['customer_type']=$type;$check(Customers::validate($candidate)['customer_type']===$type,'Controlled type '.$type);}
 $check(Customers::phone('077 123 4567')===Customers::phone('+94 (77) 123-4567'),'Local/international phone equivalence');
 $check(Customers::phone('0094 77 1234567')==='94771234567','International dialing prefix');
 foreach(['','abc','123','+000000000000','9999999999999999','+12/3456789'] as $bad){$reject(fn()=>Customers::phone($bad),422,'Invalid phone');}
 foreach(['customer_code'=>'','shop_name'=>'','contact_person'=>'','phone'=>'','address'=>'','city_town'=>'','district'=>'','customer_type'=>'UNKNOWN','credit_terms_days'=>'','notes'=>str_repeat('x',5001)] as $field=>$bad){$candidate=$input;$candidate[$field]=$bad;$reject(fn()=>Customers::validate($candidate),422,'Required/controlled '.$field);}
 foreach(['customer_code'=>65,'shop_name'=>201,'contact_person'=>151,'address'=>2001,'city_town'=>101,'district'=>101] as $field=>$length){$candidate=$input;$candidate[$field]=str_repeat('x',$length);$reject(fn()=>Customers::validate($candidate),422,'Length '.$field);}
 foreach(['-1','1.5','3651','1e2',30,[]] as $days){$candidate=$input;$candidate['credit_terms_days']=$days;$reject(fn()=>Customers::validate($candidate),422,'Invalid credit days');}
 foreach(['-0.01','1.001','10000000000000.00','1e3','1,000.00',0.1,[]] as $limit){$candidate=$input;$candidate['credit_limit']=$limit;$reject(fn()=>Customers::validate($candidate),422,'Invalid credit limit');}
 $samePhone=$input;$samePhone['alternate_phone']=$phone;$reject(fn()=>Customers::validate($samePhone),422,'Same primary and alternate');
 $reject(fn()=>$service->save($rep,$input),403,'Rep create denied');
 $customer=$service->save($admin,$input);$id=(string)$customer['id'];
 $check($customer['credit_limit']===null && !(int)$customer['credit_limit_enabled'],'Optional credit limit distinct from zero');
 $check($customer['assigned_rep_id']===null,'No representative allocated');
 $reject(fn()=>$service->save($admin,$input),422,'Duplicate code');
 $lower=$input;$lower['customer_code']=strtolower($prefix);$reject(fn()=>$service->save($admin,$lower),422,'Case-insensitive duplicate code');
 $pdo->prepare('UPDATE customers SET assigned_rep_id=?,area=? WHERE id=?')->execute([$rep['id'],'Preserved territory',$id]);
 $update=$input;$update['customer_code']=$prefix.'NEW';$update['credit_limit']='9999999999999.99';$update['credit_terms_days']='3650';$update['version']=(string)$customer['version'];
 $edited=$service->save($admin,$update,$id);
 $check($edited['credit_limit']==='9999999999999.99','Maximum exact LKR credit limit');
 $check((string)$edited['assigned_rep_id']===(string)$rep['id']&&$edited['area']==='Preserved territory','Assignment/legacy area preserved');
 $reject(fn()=>$service->save($admin,$update,$id),409,'Stale edit conflicts');
 $reject(fn()=>$service->save($admin,$input),422,'Old code cannot be recycled');
 $protected=$update;$protected['assigned_rep_id']=$rep['id'];$reject(fn()=>$service->save($admin,$protected,$id),422,'Assignment injection denied');
 $reject(fn()=>$service->get($rep,$id),403,'Rep detail denied');$reject(fn()=>$service->listing($rep,[]),403,'Rep list denied');$reject(fn()=>$service->save($rep,$update,$id),403,'Rep edit denied');
 $reject(fn()=>$service->status($rep,$id,(string)$edited['version'],'0'),403,'Rep status denied');
 $inactive=$service->status($admin,$id,(string)$edited['version'],'0');
 $check((int)$inactive['is_active']===0&&$inactive['archived_at_utc']!==null&&(string)$inactive['id']===$id,'Deactivation preserves identity');
 $reject(fn()=>$service->status($admin,$id,(string)$edited['version'],'1'),409,'Stale status conflict');
 $duplicate=$input;$duplicate['customer_code']=$prefix.'SHARED';
 try{$service->save($admin,$duplicate);throw new RuntimeException('Duplicate phone silently accepted');}catch(CustomerException $e){$check($e->status===409&&(string)$e->duplicates[0]['id']===$id,'Inactive matching phone requires review');}
 $duplicate['duplicate_phone_confirmed']='1';$duplicate['duplicate_phone_reason']='short';$reject(fn()=>$service->save($admin,$duplicate),409,'Confirmation needs reason');
 $duplicate['duplicate_phone_reason']='Reviewed intentional shared business contact.';$shared=$service->save($admin,$duplicate);
 $check((string)$shared['id']!==$id,'Shared number keeps separate customer identities');
 $q=$pdo->prepare("SELECT after_json FROM audit_log WHERE action='CUSTOMER_CREATE' AND entity_key=? ORDER BY id DESC LIMIT 1");$q->execute([(string)$shared['id']]);$audit=json_decode($q->fetchColumn(),true);$check($audit['shared_phone_confirmation']['reason']===$duplicate['duplicate_phone_reason'],'Shared-number confirmation audited');
 $pdo->prepare('UPDATE customers SET phone_normalized=NULL WHERE id=?')->execute([$id]);
 $legacy=$duplicate;$legacy['customer_code']=$prefix.'LEGACY';unset($legacy['duplicate_phone_confirmed']);$reject(fn()=>$service->save($admin,$legacy),409,'Legacy unnormalized phone still checked');
 $other=$input;$other['customer_code']=$prefix.'ALT';$other['phone']='+999'.str_pad((string)random_int(1,999999999),9,'0',STR_PAD_LEFT);$other['alternate_phone']=$phone;$reject(fn()=>$service->save($admin,$other),409,'Alternate-to-primary duplicates reviewed');
 $active=$service->status($admin,$id,(string)$inactive['version'],'1');$check((int)$active['is_active']===1&&$active['archived_at_utc']===null,'Reactivation');
 $update['version']=(string)$active['version'];$update['credit_limit']='0.00';$update['duplicate_phone_confirmed']='1';$update['duplicate_phone_reason']='Shared contact reviewed again during edit.';$zero=$service->save($admin,$update,$id);
 $check($zero['credit_limit']==='0.00'&&(int)$zero['credit_limit_enabled']===1,'Explicit zero credit limit remains configured');
 $update['version']=(string)$zero['version'];$update['credit_limit']='';$unsetLimit=$service->save($admin,$update,$id);$check($unsetLimit['credit_limit']===null,'Credit limit can be cleared without losing identity');
 $result=$service->listing($admin,['q'=>$prefix,'customer_type'=>'PHARMACY','district_id'=>(string)$location['district_id'],'town_id'=>(string)$location['town_id'],'status'=>'active']);$check($result['total']===2,'Search/type/location/status filters');
 $check($service->listing($admin,['q'=>'%_'.$prefix])['total']===0,'Wildcard search escaped');
 foreach([['status'=>[]],['customer_type'=>'UNKNOWN'],['page'=>'0'],['district_id'=>[]]] as $filters){$reject(fn()=>$service->listing($admin,$filters),422,'Invalid filters');}
 try{$pdo->prepare('UPDATE customers SET credit_limit=-1 WHERE id=?')->execute([$id]);throw new RuntimeException('Negative credit accepted');}catch(PDOException $e){$check(in_array((int)($e->errorInfo[1]??0),[3819,4025],true),'Existing credit constraint preserved');}
 try{$pdo->prepare('UPDATE customers SET credit_terms_days=3651 WHERE id=?')->execute([$id]);throw new RuntimeException('Out of range credit days accepted');}catch(PDOException $e){$check(in_array((int)($e->errorInfo[1]??0),[3819,4025],true),'Credit days database bound');}
 try{$pdo->prepare('DELETE FROM customers WHERE id=?')->execute([$id]);throw new RuntimeException('Customer deletion accepted');}catch(PDOException $e){$check((int)($e->errorInfo[1]??0)===1451,'Code history prevents destructive deletion');}
 $_SESSION=['csrf_token'=>bin2hex(random_bytes(32))];$_GET=[];
 $render=static function(string $path,string $method='GET',array $form=[])use($service,$admin,$pdo):array{$_SERVER['REQUEST_METHOD']=$method;$_POST=$form;http_response_code(200);ob_start();try{(new CustomerController($service,new App\Services\Locations($pdo)))->handle(CustomerController::route($path),$admin);return ['body'=>ob_get_contents(),'status'=>http_response_code()];}finally{ob_end_clean();}};
 $form=$render('/admin/customers/'.$id.'/edit');$check($form['status']===200&&strpos($form['body'],'name="version" value="'.$unsetLimit['version'].'"')!==false,'Edit version rendered');
 $check(strpos($form['body'],'Rollback-only &lt;shop&gt;')!==false,'Customer output escaped');
 $detail=$render('/admin/customers/'.$id);$check($detail['status']===200&&strpos($detail['body'],'Not configured')!==false&&strpos($detail['body'],'Invoice history')!==false&&strpos($detail['body'],'Allow Credit Sales')!==false,'Detail retains credit settings and links actual invoice history');
 $controllerInput=$other;unset($controllerInput['alternate_phone']);$controllerInput['customer_code']=$prefix.'API';$controllerInput['csrf_token']=$_SESSION['csrf_token'];
 $created=$render('/api/v1/admin/customers','POST',$controllerInput);$payload=json_decode($created['body'],true,512,JSON_THROW_ON_ERROR);$check($created['status']===201&&$payload['customer']['credit_limit']===null&&$payload['currency']==='LKR','Customer API create and null money');
 $apiId=(string)$payload['customer']['id'];$controllerInput['version']=(string)$payload['customer']['version'];$controllerInput['credit_limit']='0.10';$edit=$render('/api/v1/admin/customers/'.$apiId,'POST',$controllerInput);$updated=json_decode($edit['body'],true);$check($edit['status']===200&&$updated['customer']['credit_limit']==='0.10','API decimal-string edit');
 $conflict=$render('/api/v1/admin/customers/'.$apiId,'POST',$controllerInput);$check($conflict['status']===409,'Controller stale version conflict');
 $status=$render('/api/v1/admin/customers/'.$apiId.'/status','POST',['csrf_token'=>$_SESSION['csrf_token'],'version'=>(string)$updated['customer']['version'],'is_active'=>'0']);$check($status['status']===200&&(int)json_decode($status['body'],true)['customer']['is_active']===0,'API deactivation');
 $candidate=$input;$candidate['customer_code']=$prefix.'REVIEW';$candidate['csrf_token']=$_SESSION['csrf_token'];$review=$render('/admin/customers/new','POST',$candidate);$check($review['status']===409&&strpos($review['body'],'Matching contact numbers')!==false&&strpos($review['body'],'duplicate_phone_reason')!==false,'Duplicate review form retains controls and matching links');
 $otherConnection=Database::connect();$otherConnection->exec('SET SESSION innodb_lock_wait_timeout=1');$otherConnection->beginTransaction();
 try{$otherConnection->prepare('INSERT INTO customer_phone_locks(phone_hash) VALUES (?) ON DUPLICATE KEY UPDATE phone_hash=phone_hash')->execute([hash('sha256',Customers::phone($phone))]);throw new RuntimeException('Concurrent phone reservation was not serialized');}catch(PDOException $e){$check((int)($e->errorInfo[1]??0)===1205,'Independent DB connection cannot bypass phone reservation lock');}finally{if($otherConnection->inTransaction()){$otherConnection->rollBack();}}
}finally{if($pdo->inTransaction()){$pdo->rollBack();}}
foreach($before as $table=>$total){$check($pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()===$total,'No retained fixtures in '.$table);}
echo "$count customer checks passed; customer/code/phone-lock/audit/user fixtures rolled back.\n";
