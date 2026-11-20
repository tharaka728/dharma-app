<?php
declare(strict_types=1);
// Integration tests use only explicitly created fixture users in the backed-up app.
require dirname(__DIR__).'/app/bootstrap.php';
use App\Support\Database;
use App\Middleware\Session;
if (PHP_SAPI !== 'cli' || !in_array('--existing-app',$argv,true) || !in_array(getenv('DB_NAME'),['dharma_sales_phase1','dharma_sales_phase9_test'],true)) { throw new RuntimeException('Explicit application test target required.'); }
if(getenv('DB_NAME')==='dharma_sales_phase9_test'&&(!getenv('TEST_BASE_URL')||getenv('TEST_BASE_URL')==='http://127.0.0.1:8088'))throw new RuntimeException('An explicit isolated HTTP server is required for test-database authentication checks.');
$pdo=Database::connect();
if($pdo->query('SELECT @@port')->fetchColumn()!=3309){throw new RuntimeException('Wrong server');}
if((int)$pdo->query("SELECT GET_LOCK('dharma_authentication_tests',5)")->fetchColumn()!==1){throw new RuntimeException('Another authentication suite is running');}
$testIp='127.0.0.2';
$ipCheck=$pdo->prepare('SELECT COUNT(*) FROM auth_login_limits WHERE bucket_hash=?');$ipCheck->execute([hash('sha256','ip:'.$testIp)]);$hadTestIp=(int)$ipCheck->fetchColumn()>0;
$count=0;
$assert=static function(bool $ok,string $label)use(&$count){if(!$ok){throw new RuntimeException($label);} $count++;};
$prefix='auth_test_'.bin2hex(random_bytes(6));
$password=bin2hex(random_bytes(16));$ids=[];$jars=[];$syntheticKeys=[];
$base='http://127.0.0.1:8088';
function request(string $path,string $jar,?array $form=null,?string $json=null): array {
    $target=getenv('TEST_BASE_URL')?:'http://127.0.0.1:8088';if(!preg_match('~^http://127\.0\.0\.1:[0-9]{2,5}$~D',$target))throw new RuntimeException('Loopback HTTP test target required.');
    $curl=curl_init($target.$path); $headers=[];
    curl_setopt_array($curl,[CURLOPT_INTERFACE=>'127.0.0.2',CURLOPT_COOKIE=>'dharma_language=en',CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>15,CURLOPT_HEADERFUNCTION=>static function($c,$line)use(&$headers){$headers[]=$line;return strlen($line);}]);
    if($form!==null){curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($form));}
    if($json!==null){curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type: application/json']);curl_setopt($curl,CURLOPT_POSTFIELDS,$json);}
    $body=curl_exec($curl); if($body===false){throw new RuntimeException('Local HTTP request failed');}
    $status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    return ['status'=>$status,'body'=>$body,'headers'=>implode('',$headers)];
}
function token(array $response): string {
    if(!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$response['body'],$match)){throw new RuntimeException('CSRF token not found');}
    return $match[1];
}
try {
 foreach(['ADMIN','SALES_REP'] as $role){
  $name=$prefix.($role==='ADMIN'?'_admin':'_rep');
  $pdo->prepare('INSERT INTO users(name,username,password_hash,role) VALUES (?,?,?,?)')->execute(['<Test & user>',$name,password_hash($password,PASSWORD_DEFAULT),$role]);
  $ids[$role]=$pdo->lastInsertId();
  $jars[$role]=tempnam(sys_get_temp_dir(),'dharma-auth-');
 }
 $admin=$jars['ADMIN'];$rep=$jars['SALES_REP'];
 $assert(request('/api/v1/health',$admin)['status']===200,'Public health');
 $anonymous=request('/admin',$admin);
 $assert($anonymous['status']===303,'Anonymous page redirect');
 $assert(request('/admin/products',$admin)['status']===303,'Anonymous product page denied');
 $assert(request('/api/v1/admin/products',$admin)['status']===401,'Anonymous product API denied');
 $assert(request('/admin/customers',$admin)['status']===303,'Anonymous customer page denied');
 $assert(request('/api/v1/admin/customers',$admin)['status']===401,'Anonymous customer API denied');
 $assert(request('/api/v1/admin/locations/districts',$admin)['status']===401,'Anonymous district API denied');
 $assert(request('/api/v1/admin/locations/towns?district_id=5',$admin)['status']===401,'Anonymous town API denied');
 $assert(request('/api/v1/admin/dashboard',$admin)['status']===401,'Anonymous API denied');
 $assert(request('/admin/inventory',$admin)['status']===303,'Anonymous inventory page denied');
 $assert(request('/api/v1/admin/inventory',$admin)['status']===401,'Anonymous inventory API denied');
 $assert(request('/admin/representatives',$admin)['status']===303,'Anonymous representative management denied');
 $assert(request('/api/v1/admin/stock/representatives',$admin)['status']===401,'Anonymous representative stock API denied');
 $assert(request('/api/v1/rep/stock',$admin)['status']===401,'Anonymous own stock API denied');
 $assert(request('/admin/promotions',$admin)['status']===303,'Anonymous promotions page denied');
 $assert(request('/api/v1/rep/promotions',$admin)['status']===401,'Anonymous applicable promotions denied');
 $assert(request('/logout',$admin)['status']===405,'GET logout denied');
 $page=request('/login',$admin);$csrf=token($page);
 $assert($page['status']===200 && stripos($anonymous['headers'],'HttpOnly')!==false && stripos($anonymous['headers'],'SameSite=Lax')!==false,'Login cookie flags');
 $assert(request('/login',$admin,['username'=>$prefix.'_admin','password'=>$password])['status']===403,'Login CSRF required');
 $bad=request('/login',$admin,['csrf_token'=>$csrf,'username'=>$prefix.'_admin','password'=>'incorrect']);
 $assert($bad['status']===401,'Wrong password rejected');
 $unknown=request('/login',$admin,['csrf_token'=>$csrf,'username'=>$prefix.'_unknown','password'=>'incorrect']);
 $assert($unknown['status']===401 && strpos($unknown['body'],'Unable to sign in. Check your username and password.')!==false,'Unknown identity generic failure');
 $before=file_get_contents($admin);
 $login=request('/login',$admin,['csrf_token'=>$csrf,'username'=>$prefix.'_admin','password'=>$password]);
 $assert($login['status']===303 && strpos($login['headers'],'/admin')!==false,'Admin login redirect');
 $assert($before!==file_get_contents($admin),'Session ID changed after login');
 $oldJar=tempnam(sys_get_temp_dir(),'dharma-auth-');$jars['old']=$oldJar;file_put_contents($oldJar,$before);
 $assert(request('/api/v1/me',$oldJar)['status']===401,'Pre-login session ID cannot access identity');
 $dash=request('/admin',$admin);
 $assert($dash['status']===200 && strpos($dash['body'],'&lt;Test &amp; user&gt;')!==false,'Dashboard escapes identity');
 $assert(strpos($dash['body'],'href="/admin/reports"')!==false && strpos($dash['body'],'Reports')!==false,'Implemented Reports dashboard navigation');
 $assert(request('/admin/reports',$admin)['status']===200,'Admin Reports available');
 $assert(request('/admin/reports/sales/csv',$admin)['status']===200,'Admin report CSV protected and available');
 $assert(request('/api/v1/admin/dashboard',$admin)['status']===200,'Admin API allowed');
 foreach(['/admin/offline','/api/v1/admin/offline'] as $p)$assert(request($p,$admin)['status']===200,'Admin offline conflict/device review available');
 foreach(['/admin/offline/sales/11111111-1111-4111-8111-111111111111','/admin/offline/devices/11111111-1111-4111-8111-111111111111'] as $p)$assert(request($p,$admin,[])['status']===403,'Offline admin mutation requires CSRF');
 $assert(request('/api/v1/admin/offline?page=0',$admin)['status']===422,'Offline review pagination validated');
 foreach(['/admin/invoices','/admin/invoices/new','/admin/invoices/settings','/api/v1/admin/invoices','/api/v1/admin/invoices/options'] as $invoicePath){$assert(request($invoicePath,$admin)['status']===200,'Admin billing screen/API available');}
 $invoiceForm=request('/admin/invoices/new',$admin);$assert(strpos($invoiceForm['body'],'Review invoice')!==false&&strpos($invoiceForm['body'],'Stock source')!==false,'Explicit source and review workflow');
 foreach(['/api/v1/admin/invoices','/api/v1/admin/invoices/preview','/admin/invoices/new','/admin/invoices/settings','/admin/invoices/customer-settings','/admin/invoices/11111111-1111-4111-8111-111111111111/payment','/admin/invoices/11111111-1111-4111-8111-111111111111/void'] as $invoicePath)$assert(request($invoicePath,$admin,[])['status']===403,'Billing mutation requires CSRF');
 $assert(request('/api/v1/admin/invoices',$admin,null,'{broken')['status']===400,'Invoice malformed JSON rejected');
 $assert(request('/api/v1/admin/invoices',$admin,null,'[]')['status']===400,'Invoice JSON must be object');
 $assert(request('/api/v1/admin/invoices/preview',$admin,['csrf_token'=>token($dash)])['status']===422,'Invoice server validation rejects incomplete input');
 $billingCustomer=$pdo->query('SELECT id FROM customers ORDER BY id LIMIT 1')->fetchColumn();if($billingCustomer){foreach(['','/edit'] as $suffix){$billingPage=request('/admin/customers/'.$billingCustomer.$suffix,$admin);$assert($billingPage['status']===200&&strpos($billingPage['body'],'Allow Credit Sales')!==false&&strpos($billingPage['body'],'Assigned sales representative')!==false,'Customer billing fields visible');}}
 $existingInvoice=$pdo->query('SELECT invoice_id FROM online_invoice_details ORDER BY invoice_id LIMIT 1')->fetchColumn();if($existingInvoice){$invoiceDetail=request('/admin/invoices/'.$existingInvoice,$admin);$assert($invoiceDetail['status']===200&&strpos($invoiceDetail['body'],'Cash receipt history')!==false&&strpos($invoiceDetail['body'],'INTERNAL_ERROR')===false,'Existing invoice HTML renders safe payment history');}
 $previewCustomer=$pdo->query('SELECT id FROM customers WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();$previewProduct=$pdo->query('SELECT p.id FROM products p JOIN product_stock_balances b ON b.product_id=p.id WHERE p.is_active=1 AND b.quantity>0 ORDER BY p.id LIMIT 1')->fetchColumn();
 if($previewCustomer&&$previewProduct){
  $previewBefore=[];foreach(['invoices','invoice_items','payments','payment_allocations','inventory_transactions','inventory_movements','online_invoice_details','invoice_receipts'] as $table)$previewBefore[$table]=hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));
  $realPreview=request('/admin/invoices/new',$admin,['csrf_token'=>token($dash),'intent'=>'review','customer_id'=>(string)$previewCustomer,'stock_source'=>'warehouse','payment_type'=>'CASH','amount_received'=>'0.00','items'=>[['product_id'=>(string)$previewProduct,'quantity'=>'1']]]);
  $assert($realPreview['status']===200&&strpos($realPreview['body'],'Confirm &amp; Issue Invoice')!==false&&strpos($realPreview['body'],'INTERNAL_ERROR')===false,'HTML review renders without issuing');
  foreach($previewBefore as $table=>$hash)$assert(hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR))===$hash,'HTTP review preserves '.$table);
 }
 $productsPage=request('/admin/products',$admin);
 $assert($productsPage['status']===200 && strpos($productsPage['body'],'Search SKU or name')!==false,'Admin product list');
 $newForm=request('/admin/products/new',$admin);
 $assert($newForm['status']===200 && strpos($newForm['body'],'Unit of measure')!==false && strpos($newForm['body'],'Create a category')!==false,'Responsive add form and category setup');
 $assert(strpos($dash['body'],'/admin/products')!==false,'Admin dashboard links product module');
 $assert(request('/api/v1/admin/products',$admin)['status']===200,'Admin product API');
 $assert(request('/admin/products/new',$admin,['sku'=>'forbidden-no-csrf'])['status']===403,'Product creation CSRF');
 $assert(request('/api/v1/admin/products',$admin,['csrf_token'=>token($dash),'selling_price'=>'0.001'])['status']===422,'Product API validation');
 $json=request('/api/v1/admin/products',$admin,null,json_encode(['csrf_token'=>token($dash),'sku'=>'VALIDATION-ONLY','name'=>'Validation only','category_id'=>'1','unit_of_measure'=>'unit','selling_price'=>1.2,'cost_price'=>'','description'=>'']));
 $assert($json['status']===422 && isset(json_decode($json['body'],true)['errors']['selling_price']),'JSON numeric prices rejected; decimal strings required');
 $assert(request('/api/v1/admin/products',$admin,null,'{bad') ['status']===400,'Malformed JSON rejected');
 $assert(request('/api/v1/admin/products',$admin,null,'[]')['status']===400,'JSON list rejected');
 $assert(request('/api/v1/admin/products',$admin,null,'null')['status']===400,'JSON null rejected');
 $assert(request('/admin/products/new',$admin,['csrf_token'=>token($dash),'selling_price'=>'0.001'])['status']===422,'Product form preserves validation response');
 $assert(request('/api/v1/admin/products/999999999999999999',$admin)['status']===404,'Missing product');
 $assert(request('/api/v1/admin/products/999999999999999999/status',$admin,[])['status']===403,'Status endpoint CSRF');
 $assert(request('/admin/products?status=invalid',$admin)['status']===422,'Invalid listing filters');
 $customerPage=request('/admin/customers',$admin);$assert($customerPage['status']===200&&strpos($customerPage['body'],'Search code, shop, contact or phone')!==false,'Admin customer list');
 $customerForm=request('/admin/customers/new',$admin);$assert($customerForm['status']===200&&strpos($customerForm['body'],'Credit terms (days)')!==false&&strpos($customerForm['body'],'General retail shop')!==false,'Customer form/controlled types');
 $assert(strpos($dash['body'],'/admin/customers')!==false,'Dashboard customer navigation');
 $assert(request('/api/v1/admin/customers',$admin)['status']===200,'Admin customer API');
 $assert(request('/admin/customers/new',$admin,[])['status']===403,'Customer create CSRF');
 $assert(request('/api/v1/admin/customers/999999999999999999/status',$admin,[])['status']===403,'Customer status CSRF');
 $assert(request('/admin/customers/new',$admin,['csrf_token'=>token($dash)])['status']===422,'Customer HTML validation');
 $assert(request('/api/v1/admin/customers',$admin,['csrf_token'=>token($dash)])['status']===422,'Customer API validation');
 $assert(request('/api/v1/admin/customers/999999999999999999',$admin)['status']===404,'Missing customer');
 $assert(request('/admin/customers?customer_type=INVALID',$admin)['status']===422,'Invalid customer filter');
 $customerJson=['csrf_token'=>token($dash),'customer_code'=>'VALIDATION-ONLY','shop_name'=>'Validation only','contact_person'=>'Validation','phone'=>'+999123456789','address'=>'Validation','city_town'=>'Validation','district'=>'Validation','customer_type'=>'OTHER','credit_terms_days'=>'0','credit_limit'=>1.2];
 $json=request('/api/v1/admin/customers',$admin,null,json_encode($customerJson));$assert($json['status']===422&&isset(json_decode($json['body'],true)['errors']['credit_limit']),'JSON numeric credit rejected');
 $assert(request('/api/v1/admin/customers',$admin,null,'{bad')['status']===400,'Customer malformed JSON');
 $assert(request('/api/v1/admin/customers',$admin,null,'[]')['status']===400,'Customer JSON list rejected');
 $districtResponse=request('/api/v1/admin/locations/districts',$admin);$districtData=json_decode($districtResponse['body'],true);
 $assert($districtResponse['status']===200&&count($districtData['districts'])===25,'Admin district lookup');
 $townResponse=request('/api/v1/admin/locations/towns?district_id=5&q=colombo',$admin);$townData=json_decode($townResponse['body'],true);
 $assert($townResponse['status']===200&&count($townData['towns'])>=1&&count(array_filter($townData['towns'],static fn($row)=>$row['district_id']!=='5'))===0,'HTTP district-filtered town search');
 $assert(request('/api/v1/admin/locations/towns?district_id=999999',$admin)['status']===422,'Unknown district rejected');
 foreach(['/admin/inventory','/admin/inventory/add','/admin/inventory/receipts','/admin/inventory/adjustments','/admin/inventory/history','/admin/inventory/movements','/api/v1/admin/inventory','/api/v1/admin/inventory/history'] as $inventoryPath){$assert(request($inventoryPath,$admin)['status']===200,'Admin inventory screen/API '.$inventoryPath);}
 foreach(['/admin/inventory/batches','/admin/inventory/settings'] as $retired){$assert(request($retired,$admin)['status']===303,'Retired batch screens redirect to simple inventory');}
 $simpleStock=request('/admin/inventory/add',$admin);$assert(strpos($simpleStock['body'],'Save Stock')!==false&&strpos($simpleStock['body'],'name="selling_price"')!==false&&strpos($simpleStock['body'],'name="batch_id"')===false,'Simple add-stock HTML delivered');
 foreach(['/admin/representatives','/admin/representatives/new','/admin/representatives/'.$ids['SALES_REP'],'/admin/representatives/'.$ids['SALES_REP'].'/edit','/admin/representatives/'.$ids['SALES_REP'].'/password','/admin/stock/allocate','/admin/stock/returns','/admin/stock/corrections','/admin/stock/representatives','/admin/stock/history'] as $phase6Path){$assert(request($phase6Path,$admin)['status']===200,'Admin Phase 6 page '.$phase6Path);}
 $repProfile=request('/api/v1/admin/representatives/'.$ids['SALES_REP'],$admin);$profileData=json_decode($repProfile['body'],true);$assert($repProfile['status']===200&&!isset($profileData['representative']['password_hash'])&&strpos($repProfile['body'],$password)===false,'Profile API excludes password material');
 foreach(['/api/v1/admin/representatives','/api/v1/admin/representatives/'.$ids['SALES_REP'].'/status','/api/v1/admin/representatives/'.$ids['SALES_REP'].'/password','/api/v1/admin/stock/allocate','/api/v1/admin/stock/returns','/api/v1/admin/stock/corrections'] as $phase6Path){$assert(request($phase6Path,$admin,[])['status']===403,'Phase 6 CSRF protection');}
 $assert(request('/api/v1/admin/representatives',$admin,null,'{bad')['status']===400,'Representative API JSON validation');
 foreach(['/admin/promotions','/admin/promotions/new','/api/v1/admin/promotions'] as $promotionPath){$assert(request($promotionPath,$admin)['status']===200,'Admin promotion screen/API');}
 $assert(request('/api/v1/admin/promotions',$admin,[])['status']===403,'Promotion save requires CSRF');
 $assert(request('/api/v1/admin/promotions/preview',$admin,[])['status']===403,'Promotion preview requires CSRF');
 $assert(request('/api/v1/admin/promotions',$admin,null,'{bad')['status']===400,'Promotion JSON validation');
 $promotionProduct=$pdo->query('SELECT id FROM products WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
 if(!$promotionProduct){throw new RuntimeException('An existing active product is required for read-only preview verification.');}
 $readOnlyCounts=[];foreach(['promotions','promotion_versions','promotion_commands','product_stock_balances','representative_stock_balances','inventory_movements'] as $table){$readOnlyCounts[$table]=hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR));}
 $previewBody=['csrf_token'=>token($dash),'name'=>'Read-only preview validation','description'=>'','product_id'=>(string)$promotionProduct,'type'=>'PERCENTAGE','percent_rate'=>'10.00','is_active'=>'1','paid_quantity'=>'1'];
 $previewResponse=request('/api/v1/admin/promotions/preview',$admin,$previewBody);$previewData=json_decode($previewResponse['body'],true);$assert($previewResponse['status']===200&&$previewData['preview_only']&&is_string($previewData['payable_amount']),'HTTP exact-money read-only preview');
 $previewBody['intent']='preview';$htmlPreview=request('/admin/promotions/new',$admin,$previewBody);$assert($htmlPreview['status']===200&&strpos($htmlPreview['body'],'Read-only preview')!==false,'HTML preview before saving');
 foreach($readOnlyCounts as $table=>$hash){$assert(hash('sha256',json_encode($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(),JSON_THROW_ON_ERROR))===$hash,'Preview changes no business data '.$table);}
 $csv=request('/admin/inventory/movements.csv',$admin);$assert($csv['status']===200&&stripos($csv['headers'],'text/csv')!==false&&strpos($csv['body'],'transaction_id')!==false,'Authorized CSV download');
 foreach(['batches','receipts','adjustments','settings','threshold'] as $inventoryAction){$assert(request('/api/v1/admin/inventory/'.$inventoryAction,$admin,[])['status']===403,'Inventory endpoint CSRF '.$inventoryAction);}
 $assert(request('/admin/inventory/add?product_id=invalid',$admin)['status']===422,'Invalid stock product selection reports validation');
 $allTownResponse=request('/api/v1/admin/locations/towns?scope=all',$admin);$allTownData=json_decode($allTownResponse['body'],true);
 $assert($allTownResponse['status']===200&&count(array_unique(array_column($allTownData['towns'],'district_id')))>1&&isset($allTownData['towns'][0]['district_name']),'HTTP cross-district town search and labels');
 $listResponse=request('/admin/customers?district_id=5',$admin);
 $assert($listResponse['status']===200&&strpos($listResponse['body'],'filter-district-search')!==false&&strpos($listResponse['body'],'customer-location-filters.js')!==false,'HTTP customer filter dropdown controls');
 $assert(request('/api/v1/admin/customers?town_id=invalid',$admin)['status']===422,'HTTP customer filter ID validation');
 $assert(request('/api/v1/admin/locations/towns',$admin,[])['status']===403,'Town creation CSRF required');
 $assert(request('/api/v1/admin/locations/towns',$admin,['csrf_token'=>token($dash),'district_id'=>'5','town_name'=>''])['status']===422,'Town name required');
 $assert(request('/api/v1/admin/locations/towns',$admin,['csrf_token'=>token($dash),'district_id'=>'5','town_name'=>' colombo '])['status']===409,'Town duplicate endpoint');
 $assert(request('/api/v1/admin/locations/towns',$admin,null,'{bad')['status']===400,'Town malformed JSON');
 $assert(strpos($customerForm['body'],'name="district_id"')!==false&&strpos($customerForm['body'],'+ Add New Town')!==false&&strpos($customerForm['body'],'customer-locations.js')!==false,'Customer searchable lookup controls delivered');
 $me=request('/api/v1/me',$admin);
 $assert($me['status']===200 && strpos($me['body'],'password')===false && strpos($me['body'],'cost_price')===false,'Identity payload excludes secrets');
 $assert(request('/logout',$admin,['csrf_token'=>$csrf])['status']===403,'Pre-login CSRF invalidated');
 $replay=tempnam(sys_get_temp_dir(),'dharma-auth-');$jars['replay']=$replay;copy($admin,$replay);
 $assert(request('/logout',$admin,['csrf_token'=>token($dash)])['status']===303,'CSRF logout succeeds');
 $assert(request('/api/v1/me',$admin)['status']===401,'Logout invalidates session');
 $assert(request('/api/v1/me',$replay)['status']===401,'Logged-out cookie replay rejected server-side');
 $csrf=token(request('/login',$rep));
 $assert(request('/login',$rep,['csrf_token'=>$csrf,'username'=>$prefix.'_rep','password'=>$password])['status']===303,'Rep login');
 $repDashboard=request('/rep',$rep);$assert($repDashboard['status']===200,'Rep dashboard');
 $assert(request('/api/v1/offline/session',$rep)['status']===200,'Authenticated representative reachability and CSRF refresh');
 foreach(['/api/v1/offline/register','/api/v1/offline/bootstrap','/api/v1/offline/sales'] as $p)$assert(request($p,$rep,[])['status']===403,'Offline provisioning and sync require CSRF');
 $assert(request('/api/v1/admin/offline',$rep)['status']===403,'Representative denied admin offline review');
 foreach(['/rep/invoices','/rep/invoices/new','/rep/customers','/api/v1/rep/invoices','/api/v1/rep/invoices/options','/api/v1/rep/customers'] as $invoicePath)$assert(request($invoicePath,$rep)['status']===200,'Rep authorized billing screen/API');
 $assert(strpos($repDashboard['body'],'/rep/invoices/new')!==false,'Rep dashboard billing link');
 $assert(json_decode(request('/api/v1/rep/customers',$rep)['body'],true)['customers']===[],'Unassigned customers excluded from rep HTTP payload');
 $repInvoiceOptions=request('/api/v1/rep/invoices/options',$rep);$assert(strpos($repInvoiceOptions['body'],'cost_price')===false&&strpos($repInvoiceOptions['body'],'password')===false,'Rep invoice options exclude costs and credentials');
 foreach(['/admin/invoices','/admin/invoices/new','/admin/invoices/settings','/api/v1/admin/invoices','/api/v1/admin/invoices/options'] as $invoicePath)$assert(request($invoicePath,$rep)['status']===403,'Rep denied admin billing reads');
 foreach(['/api/v1/admin/invoices','/admin/invoices/customer-settings','/admin/invoices/settings','/rep/invoices/11111111-1111-4111-8111-111111111111/void'] as $invoicePath)$assert(request($invoicePath,$rep,['csrf_token'=>token($repDashboard)])['status']===403,'Rep denied admin posting/settings/void');
 foreach(['/rep/invoices/new','/api/v1/rep/invoices','/api/v1/rep/invoices/preview','/rep/invoices/11111111-1111-4111-8111-111111111111/payment'] as $invoicePath)$assert(request($invoicePath,$rep,[])['status']===403,'Rep billing CSRF enforced');
 $assert(request('/api/v1/rep/invoices?rep_id='.$ids['ADMIN'],$rep)['status']===403,'HTTP invoice history scope cannot be forged');
 $assert(request('/api/v1/rep/invoices/11111111-1111-4111-8111-111111111111',$rep)['status']===404,'Invoice ID guessing does not expose data');
 $assert(strpos($repDashboard['body'],'My assigned stock')!==false&&strpos($repDashboard['body'],'My allocation history')!==false,'Representative dashboard has real stock/history sections');
 foreach(['/rep/stock','/rep/stock/history','/api/v1/rep/stock','/api/v1/rep/stock/history'] as $ownPath){$assert(request($ownPath,$rep)['status']===200,'Representative own stock read allowed');}
 $assert(request('/api/v1/rep/stock?rep_id='.$ids['ADMIN'],$rep)['status']===403,'HTTP forged stock scope denied');
 $assert(request('/api/v1/rep/stock/history?rep_id='.$ids['ADMIN'],$rep)['status']===403,'HTTP forged history scope denied');
 $assert(request('/api/v1/rep/stock',$rep,[])['status']===405,'Own stock API cannot mutate balances');
 foreach(['/rep/promotions','/api/v1/rep/promotions'] as $promotionPath){$assert(request($promotionPath,$rep)['status']===200,'Rep current promotion read allowed');}
 $quoteResponse=request('/api/v1/rep/promotions/quote?product_id='.$promotionProduct.'&paid_quantity=1',$rep);$quoteData=json_decode($quoteResponse['body'],true);$assert($quoteResponse['status']===200&&is_string($quoteData['unit_price'])&&strpos($quoteResponse['body'],'cost_price')===false,'Representative read-only quote uses authoritative price without costs');
 $assert(request('/rep/promotions',$rep,[])['status']===405,'Representative promotion page cannot mutate');
 foreach(['/admin/promotions','/admin/promotions/new','/api/v1/admin/promotions/1','/api/v1/admin/promotions/quote?product_id='.$promotionProduct] as $promotionPath){$assert(request($promotionPath,$rep)['status']===403,'Rep cannot access admin promotion pages/API');}
 foreach(['/api/v1/admin/promotions','/api/v1/admin/promotions/1/edit','/api/v1/admin/promotions/1/status','/api/v1/admin/promotions/preview'] as $promotionPath){$assert(request($promotionPath,$rep,['csrf_token'=>$csrf])['status']===403,'Rep cannot create/edit/activate/preview drafts');}
 foreach(['/admin/representatives','/api/v1/admin/representatives','/admin/stock/allocate','/admin/stock/representatives','/api/v1/admin/stock/history'] as $phase6Path){$assert(request($phase6Path,$rep)['status']===403,'Rep cannot access admin Phase 6 pages/APIs');}
 foreach(['/api/v1/admin/representatives','/api/v1/admin/representatives/'.$ids['SALES_REP'].'/status','/api/v1/admin/representatives/'.$ids['SALES_REP'].'/password','/api/v1/admin/stock/allocate','/api/v1/admin/stock/returns','/api/v1/admin/stock/corrections'] as $phase6Path){$assert(request($phase6Path,$rep,['csrf_token'=>$csrf])['status']===403,'Rep cannot mutate accounts or stock');}
 $assert(request('/admin',$rep)['status']===403,'Rep denied admin page');
 foreach(['/admin/inventory','/admin/inventory/batches','/admin/inventory/receipts','/admin/inventory/adjustments','/admin/inventory/movements','/admin/inventory/settings','/admin/inventory/movements.csv','/api/v1/admin/inventory','/api/v1/admin/inventory/movements'] as $inventoryPath){$assert(request($inventoryPath,$rep)['status']===403,'Rep inventory read/export denied');}
 foreach(['batches','receipts','adjustments','settings','threshold'] as $inventoryAction){$assert(request('/api/v1/admin/inventory/'.$inventoryAction,$rep,['csrf_token'=>$csrf])['status']===403,'Rep stock mutation denied');}
 $assert(request('/api/v1/admin/dashboard',$rep)['status']===403,'Rep denied admin API');
 $assert(request('/api/v1/rep/dashboard',$rep)['status']===200,'Rep API allowed');
 foreach(['/admin/products','/admin/products/new','/api/v1/admin/products','/api/v1/admin/products/1'] as $productPath){$assert(request($productPath,$rep)['status']===403,'Rep product read denied');}
 foreach(['/admin/products/new','/admin/products/1/edit','/admin/products/1/status','/api/v1/admin/products','/api/v1/admin/products/categories'] as $productPath){$assert(request($productPath,$rep,['csrf_token'=>$csrf])['status']===403,'Rep product mutation denied');}
 foreach(['/admin/customers','/admin/customers/new','/api/v1/admin/customers','/api/v1/admin/customers/1'] as $customerPath){$assert(request($customerPath,$rep)['status']===403,'Rep customer read denied');}
 foreach(['/admin/customers/new','/admin/customers/1/edit','/admin/customers/1/status','/api/v1/admin/customers','/api/v1/admin/customers/1'] as $customerPath){$assert(request($customerPath,$rep,['csrf_token'=>$csrf])['status']===403,'Rep customer mutation denied');}
 foreach(['/api/v1/admin/locations/districts','/api/v1/admin/locations/towns?district_id=5','/api/v1/admin/locations/towns?scope=all'] as $lookupPath){$assert(request($lookupPath,$rep)['status']===403,'Rep geographic lookup denied');}
 $assert(request('/api/v1/admin/locations/towns',$rep,['csrf_token'=>$csrf,'district_id'=>'5','town_name'=>'forbidden'])['status']===403,'Rep new-town creation denied');
 $pdo->prepare('UPDATE users SET role=\'ADMIN\' WHERE id=?')->execute([$ids['SALES_REP']]);
 $assert(request('/api/v1/admin/dashboard',$rep)['status']===200,'Role reloaded from database');
 $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$ids['SALES_REP']]);
 $assert(request('/api/v1/me',$rep)['status']===401,'Password change revokes session');
 $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$ids['ADMIN']]);
 $csrf=token(request('/login',$admin));
 $assert(request('/login',$admin,['csrf_token'=>$csrf,'username'=>$prefix.'_admin','password'=>$password])['status']===401,'Inactive login denied');
 $pdo->prepare('UPDATE users SET is_active=1 WHERE id=?')->execute([$ids['ADMIN']]);
 $csrf=token(request('/login',$admin));
 $assert(request('/login',$admin,['csrf_token'=>$csrf,'username'=>$prefix.'_admin','password'=>$password])['status']===303,'Active admin login');
 $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$ids['ADMIN']]);
 $assert(request('/api/v1/me',$admin)['status']===401,'Deactivation revokes existing session');
 $csrf=token(request('/login',$rep));
 for($i=0;$i<5;$i++){$r=request('/login',$rep,['csrf_token'=>$csrf,'username'=>$prefix.'_limit','password'=>'wrong']);$assert($r['status']===401,'Rate-limit attempt '.($i+1));}
 $assert(request('/login',$rep,['csrf_token'=>$csrf,'username'=>$prefix.'_limit','password'=>'wrong'])['status']===429,'Sixth pair attempt throttled');
 $now=time();
 $assert(!Session::expired(['created_at'=>$now,'last_seen'=>$now],$now),'Fresh session valid');
 $assert(Session::expired(['created_at'=>$now-1801,'last_seen'=>$now-1800],$now),'Idle expiry');
 $assert(Session::expired(['created_at'=>$now-28800,'last_seen'=>$now],$now),'Absolute expiry');
 $assert(Session::expired(['created_at'=>$now,'last_seen'=>$now+1],$now),'Clock rollback expiry');
 $assert(request('/api/v1/not-a-route',$rep)['status']===404,'Unknown route denied');
 $limiter=new App\Services\Authentication($pdo);$syntheticIp=$prefix.'_ip';
 $syntheticKeys[]=hash('sha256','ip:'.$syntheticIp);
 for($i=0;$i<31;$i++){
  $candidate=$prefix.'_candidate'.$i;
  $syntheticKeys[]=hash('sha256','pair:'.$syntheticIp.':'.$candidate);
  $allowed=$limiter->reserveAttempt($candidate,$syntheticIp);
  $assert($allowed===($i<30),'Aggregate IP cap '.$i);
 }
 $q=$pdo->prepare('SELECT COUNT(*) FROM auth_login_limits WHERE bucket_hash=?');$q->execute([end($syntheticKeys)]);
 $assert((int)$q->fetchColumn()===0,'Throttled request creates no new bucket');
 $pdo->prepare('UPDATE auth_login_limits SET window_started_utc=? WHERE bucket_hash=?')->execute([gmdate('Y-m-d H:i:s',time()-901),$syntheticKeys[0]]);
 $assert($limiter->reserveAttempt($prefix.'_new',$syntheticIp),'IP window expires');
 $syntheticKeys[]=hash('sha256','pair:'.$syntheticIp.':'.$prefix.'_new');
 echo "$count authentication, authorization, session and route checks passed.\n";
}finally{
 foreach($ids as $id){$pdo->prepare('DELETE FROM users WHERE id=? AND username LIKE ?')->execute([$id,$prefix.'%']);}
 foreach(['_admin','_rep','_unknown','_limit'] as $suffix){$pdo->prepare('DELETE FROM auth_login_limits WHERE bucket_hash=?')->execute([hash('sha256','pair:'.$testIp.':'.$prefix.$suffix)]);}
 foreach($jars as $jar){if(is_file($jar)){unlink($jar);}}
 foreach($syntheticKeys as $key){$pdo->prepare('DELETE FROM auth_login_limits WHERE bucket_hash=?')->execute([$key]);}
 if(!$hadTestIp){$pdo->prepare('DELETE FROM auth_login_limits WHERE bucket_hash=?')->execute([hash('sha256','ip:'.$testIp)]);}
 $pdo->query("SELECT RELEASE_LOCK('dharma_authentication_tests')");
 unset($password);
}
