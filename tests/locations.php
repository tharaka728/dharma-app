<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Support\Database;use App\Services\Locations;use App\Services\Customers;use App\Support\CustomerException;use App\Controllers\CustomerController;use App\Controllers\LocationController;
if(PHP_SAPI!=='cli'||!in_array('--existing-app',$argv,true)||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||!in_array(getenv('DB_NAME'),['dharma_sales_phase1','dharma_sales_phase9_test'],true)){throw new RuntimeException('Exact app-only test target required.');}
$pdo=Database::connect();$locations=new Locations($pdo);$customers=new Customers($pdo);$count=0;
$check=static function(bool $ok,string $label)use(&$count){if(!$ok){throw new RuntimeException($label);}$count++;};
$reject=static function(callable $fn,int $status,string $label)use($check){try{$fn();}catch(CustomerException $e){$check($e->status===$status,$label);return;}throw new RuntimeException('Unexpectedly accepted: '.$label);};
$before=[];foreach(['customers','location_districts','location_towns','customer_code_history','customer_phone_locks','users','audit_log'] as $table){$before[$table]=$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();}
$pdo->beginTransaction();
try{
 $prefix='LOCTEST'.strtoupper(bin2hex(random_bytes(6)));
 foreach(['ADMIN','SALES_REP'] as $role){$pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['Rollback-only location test',$prefix.$role,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$role]);$actors[$role]=['id'=>$pdo->lastInsertId(),'name'=>'Rollback-only location test'];}
 $admin=$actors['ADMIN'];$rep=$actors['SALES_REP'];
 $districts=$locations->districts($admin);
 $expected=['Ampara','Anuradhapura','Badulla','Batticaloa','Colombo','Galle','Gampaha','Hambantota','Jaffna','Kalutara','Kandy','Kegalle','Kilinochchi','Kurunegala','Mannar','Matale','Matara','Moneragala','Mullaitivu','Nuwara Eliya','Polonnaruwa','Puttalam','Ratnapura','Trincomalee','Vavuniya'];
 $check(array_column($districts,'name')===$expected,'Exactly the requested 25 districts');
 foreach($districts as $district){$result=$locations->towns($admin,['district_id'=>$district['id']]);$check(count($result['towns'])>=1&&count(array_unique(array_column($result['towns'],'district_id')))===1&&$result['towns'][0]['district_id']===$district['id'],'District-specific starter towns '.$district['name']);}
 $reject(fn()=>$locations->districts($rep),403,'Rep lookup denied');$reject(fn()=>$locations->towns($rep,['district_id'=>'5']),403,'Rep town lookup denied');
 $reject(fn()=>$locations->create($rep,'5','Forbidden'),403,'Rep create denied');
 foreach(['','999999','0',5,[]] as $bad){$reject(fn()=>$locations->towns($admin,['district_id'=>$bad]),422,'Invalid district');}
 foreach(['',str_repeat('x',101),"Town\nName",[],null] as $bad){$reject(fn()=>$locations->create($admin,'5',$bad),422,'Invalid town name');}
 $new=$locations->create($admin,'5','  '.$prefix.'  ');$check($new['name']===$prefix&&$new['district_id']==='5','Town trimmed and district bound');
 $reject(fn()=>$locations->create($admin,'5',strtolower($prefix)),409,'Case duplicate');
 $reject(fn()=>$locations->create($admin,'5',"\t".$prefix."\t"),409,'Whitespace duplicate');
 $other=$locations->create($admin,'11',$prefix);$check($other['id']!==$new['id'],'Same name in another district allowed');
 $fresh=new Locations($pdo);$result=$fresh->towns($admin,['district_id'=>'5','q'=>strtolower($prefix)]);$check(count($result['towns'])===1&&$result['towns'][0]['id']===$new['id'],'Town read back from SQL through fresh service');
 $check($fresh->towns($admin,['district_id'=>'11','q'=>$prefix])['towns'][0]['id']===$other['id'],'Same-name towns remain district scoped');
 $reject(fn()=>$locations->resolve(['district_id'=>'11','town_id'=>$new['id']]),422,'Wrong district/town rejected');
 $reject(fn()=>$locations->resolve(['district_id'=>'5','town_id'=>'']),422,'New selection required');
 $reject(fn()=>$locations->resolve(['district'=>'Unknown district','city_town'=>'Colombo']),422,'Never infer district from town');
 $resolved=$locations->resolve(['district'=>' colombo ','city_town'=>' COLOMBO ']);$check($resolved['district']==='Colombo'&&$resolved['city_town']==='Colombo','Exact case/trim legacy mapping');
 $reject(fn()=>$locations->resolve(['district'=>'Colombo','city_town'=>'Unknown '.$prefix]),422,'Unknown legacy town requires review');
 $input=['customer_code'=>$prefix,'shop_name'=>'Rollback-only location customer','contact_person'=>'Rollback test','phone'=>'+999'.str_pad((string)random_int(1,999999999),9,'0',STR_PAD_LEFT),'alternate_phone'=>'','address'=>'Rollback address','district_id'=>'5','town_id'=>$new['id'],'customer_type'=>'OTHER','notes'=>'','credit_terms_days'=>'0','credit_limit'=>''];
 $customer=$customers->save($admin,$input);$id=(string)$customer['id'];$check((string)$customer['town_id']===$new['id']&&$customer['city_town']===$prefix&&$customer['district']==='Colombo','Customer persists IDs and canonical labels');
 $listFilter=['q'=>$prefix];
 $check($customers->listing($admin,$listFilter+['district_id'=>'5'])['total']===1,'District-only customer filtering');
 $check($customers->listing($admin,$listFilter+['town_id'=>$new['id']])['total']===1,'Town-only ID filtering');
 $check($customers->listing($admin,$listFilter+['district_id'=>'5','town_id'=>$new['id']])['total']===1,'Combined location filtering');
 $check($customers->listing($admin,$listFilter+['district_id'=>'','town_id'=>''])['total']===1,'All districts and all towns');
 $check($customers->listing($admin,$listFilter+['district_id'=>'11'])['total']===0,'Empty district result');
 $check($customers->listing($admin,$listFilter+['town_id'=>$other['id']])['total']===0,'Identical town names in different districts remain distinct');
 $reject(fn()=>$customers->listing($admin,['district_id'=>'11','town_id'=>$new['id']]),422,'Mismatched list filter rejected');
 foreach([['district_id'=>'999999'],['town_id'=>'999999999999999999'],['town_id'=>[]],['town_id'=>'1 OR 1=1']] as $invalid){$reject(fn()=>$customers->listing($admin,$invalid),422,'Invalid list location IDs');}
 $allTowns=$locations->towns($admin,['scope'=>'all','q'=>$prefix]);
 $check(count($allTowns['towns'])===2&&count(array_unique(array_column($allTowns['towns'],'district_id')))===2,'Cross-district lookup includes new towns and disambiguating districts');
 $check(isset($allTowns['towns'][0]['district_name']),'Cross-district towns carry district labels');
 $reject(fn()=>$locations->towns($rep,['scope'=>'all']),403,'Rep cross-district lookup denied');
 $formOptions=$locations->filterForm($admin,['town_id'=>$new['id']]);
 $check($formOptions['town_id']===$new['id']&&in_array($new['id'],array_column($formOptions['towns'],'id'),true),'Filter options retain selected persisted town');
 $_SESSION=['csrf_token'=>bin2hex(random_bytes(32))];$_SERVER['REQUEST_METHOD']='GET';$_GET=['q'=>$prefix,'district_id'=>'5','town_id'=>$new['id']];
 ob_start();try{(new CustomerController($customers,$locations))->handle(CustomerController::route('/admin/customers'),$admin);$filterHtml=ob_get_contents();}finally{ob_end_clean();}
 $check(strpos($filterHtml,'value="5" selected')!==false&&strpos($filterHtml,'value="'.$new['id'].'" data-district-id="5" selected')!==false,'List refresh preserves district and town');
 $check(strpos($filterHtml,'All districts')!==false&&strpos($filterHtml,'All cities / towns')!==false&&strpos($filterHtml,'customer-location-filters.js')!==false,'List renders dropdowns and search behavior');
 $check(strpos($filterHtml,'href="/admin/customers">Clear filters')!==false,'Clear filters resets query');
 $_GET=['q'=>$prefix,'district_id'=>'11'];ob_start();try{(new CustomerController($customers,$locations))->handle(CustomerController::route('/admin/customers'),$admin);$emptyHtml=ob_get_contents();}finally{ob_end_clean();}
 $check(strpos($emptyHtml,'No customers match your filters')!==false,'Helpful empty state');
 // Render pagination independently of database row creation: filter links must retain every selection.
 $savedInput=$input;$action='index';$user=$admin;$input=[];$errors=[];$duplicates=[];$notice='';$title='Customers';$filterLocations=$formOptions;
 $listing=['customers'=>[],'total'=>21,'page'=>1,'pages'=>2];$_GET=['q'=>$prefix,'district_id'=>'5','town_id'=>$new['id'],'status'=>'active','customer_type'=>'OTHER'];
 ob_start();try{require APP_ROOT.'/app/Views/customers.php';$pageHtml=ob_get_contents();}finally{ob_end_clean();}
 $check(strpos($pageHtml,'town_id='.$new['id'])!==false&&strpos($pageHtml,'district_id=5')!==false&&strpos($pageHtml,'page=2')!==false&&strpos($pageHtml,'status=active')!==false&&strpos($pageHtml,'customer_type=OTHER')!==false,'Pagination retains all filters');
 $input=$savedInput;$bad=$input;
 $bad['version']=(string)$customer['version'];$bad['district_id']='11';$reject(fn()=>$customers->save($admin,$bad,$id),422,'Customer district change requires compatible town');
 $bad['town_id']=$other['id'];$moved=$customers->save($admin,$bad,$id);$check($moved['district']==='Kandy'&&(string)$moved['town_id']===$other['id'],'Valid customer district/town edit');
 try{$pdo->prepare('UPDATE customers SET district_id=5 WHERE id=?')->execute([$id]);throw new RuntimeException('Cross-district FK accepted');}catch(PDOException $e){$check((int)($e->errorInfo[1]??0)===1452,'Database composite FK rejects mismatched pair');}
 try{$pdo->prepare('INSERT INTO location_towns(district_id,name) VALUES (5,?)')->execute([' '.$prefix.' ']);throw new RuntimeException('Raw duplicate accepted');}catch(PDOException $e){$check((int)($e->errorInfo[1]??0)===1062,'Database generated trimmed/case key enforces uniqueness');}
 $_SESSION=['csrf_token'=>bin2hex(random_bytes(32))];$_GET=[];$_SERVER['REQUEST_METHOD']='GET';$_POST=[];
 ob_start();try{(new CustomerController($customers,$locations))->handle(CustomerController::route('/admin/customers/'.$id.'/edit'),$admin);$html=ob_get_contents();}finally{ob_end_clean();}
 $check(strpos($html,'value="11" selected')!==false&&strpos($html,'value="'.$other['id'].'" selected')!==false,'Existing district and town preselected');
 $check(strpos($html,'+ Add New Town')!==false&&strpos($html,'Search districts')!==false&&strpos($html,'town-search')!==false,'Search controls and add-town option');
 $pdo->prepare('UPDATE customers SET district_id=NULL,town_id=NULL,district=?,city_town=? WHERE id=?')->execute(['Ambiguous legacy district','Shared old town',$id]);
 ob_start();try{(new CustomerController($customers,$locations))->handle(CustomerController::route('/admin/customers/'.$id.'/edit'),$admin);$legacyHtml=ob_get_contents();}finally{ob_end_clean();}
 $check(strpos($legacyHtml,'Legacy location needs review')!==false&&strpos($legacyHtml,'Ambiguous legacy district')!==false,'Unmapped legacy text preserved and flagged');
 // The pure-DML forward mapping is tested on legacy rows without rerunning DDL.
 $pdo->prepare('UPDATE customers SET district_id=NULL,town_id=NULL,district=?,city_town=? WHERE id=?')->execute([' colombo ',' COLOMBO ',$id]);
 foreach(explode(';',file_get_contents(APP_ROOT.'/database/migrations/008_location_mapping_collation.sql')) as $sql){if(trim($sql)!==''){$pdo->exec($sql);}}
 $mapped=$customers->get($admin,$id);$check((string)$mapped['district_id']==='5'&&(string)$mapped['town_id']===$resolved['town_id']&&$mapped['district']===' colombo ','Forward mapping adds IDs without rewriting original text');
 $pdo->prepare('UPDATE customers SET district_id=NULL,town_id=NULL,district=?,city_town=? WHERE id=?')->execute(['Unknown district',$prefix,$id]);
 foreach(explode(';',file_get_contents(APP_ROOT.'/database/migrations/008_location_mapping_collation.sql')) as $sql){if(trim($sql)!==''){$pdo->exec($sql);}}
 $unmapped=$customers->get($admin,$id);$check($unmapped['district_id']===null&&$unmapped['town_id']===null,'Same-name towns never guessed when legacy district is unknown');
 $_SERVER['REQUEST_METHOD']='POST';$_POST=['district_id'=>'5','town_name'=>$prefix.'CONTROLLER','csrf_token'=>$_SESSION['csrf_token']];http_response_code(200);
 ob_start();try{(new LocationController($locations))->handle(LocationController::route('/api/v1/admin/locations/towns'),$admin);$response=json_decode(ob_get_contents(),true,512,JSON_THROW_ON_ERROR);}finally{ob_end_clean();}
 $check(http_response_code()===201&&$locations->towns($admin,['district_id'=>'5','q'=>$prefix.'CONTROLLER'])['towns'][0]['id']===$response['town']['id'],'New-town endpoint controller writes database');
 for($i=0;$i<55;$i++){$pdo->prepare('INSERT INTO location_towns(district_id,name) VALUES (5,?)')->execute([$prefix.'PAGE'.str_pad((string)$i,2,'0',STR_PAD_LEFT)]);}
 $first=$locations->towns($admin,['district_id'=>'5','q'=>$prefix.'PAGE']);$second=$locations->towns($admin,['district_id'=>'5','q'=>$prefix.'PAGE','page'=>'2']);
 $check(count($first['towns'])===50&&$first['has_more']&&count($second['towns'])===5&&!$second['has_more'],'Large list pagination');
 $options=$locations->form($admin,['district_id'=>'5','town_id'=>$second['towns'][4]['id']]);$check(in_array($second['towns'][4]['id'],array_column($options['towns'],'id'),true),'Edit preselect includes town beyond initial page');
 $reject(fn()=>$locations->towns($admin,['district_id'=>'5','page'=>'0']),422,'Invalid town page');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}}
foreach($before as $table=>$total){$check($pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()===$total,'No retained fixtures in '.$table);}
echo "$count district/town lookup, persistence, mapping, form, permissions and pagination checks passed; all fixtures rolled back.\n";
