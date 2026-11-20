<?php
declare(strict_types=1);
// Synthetic fixtures and test-only configuration target only the named isolated restore.
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$schema='dharma_sales_phase12_restore_20261118154343';
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname='.$schema.';charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$schema||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Unexpected test database');
$tag='PREVIEW'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(16));$actors=[];
foreach(['allowed','other'] as $name){$q=$pdo->prepare("INSERT INTO users(name,username,password_hash,role) VALUES (?, ?, ?, 'SALES_REP')");$q->execute(['Preview synthetic',$tag.$name,password_hash($password,PASSWORD_DEFAULT)]);$actors[$name]=['id'=>(string)$pdo->lastInsertId(),'username'=>$tag.$name];}
$q=$pdo->prepare("INSERT INTO users(name,username,password_hash,role) VALUES (?, ?, ?, 'ADMIN')");$q->execute(['Preview synthetic admin',$tag.'admin',password_hash($password,PASSWORD_DEFAULT)]);$adminId=$pdo->lastInsertId();
$q=$pdo->prepare('INSERT INTO categories(name) VALUES (?)');$q->execute([$tag]);$category=$pdo->lastInsertId();$products=[];
foreach(['P012'=>'Preview Herbal Oil','P013'=>'Preview Herbal Balm'] as $code=>$name){$q=$pdo->prepare("INSERT INTO products(sku,name,name_si,category_id,unit_of_measure,selling_price) VALUES (?,?,?,?,?,?)");$q->execute([$code.$tag,$name.' '.$tag,$code==='P012'?'පරීක්ෂණ තෙල්':'පරීක්ෂණ බාම්',$category,'unit','100.00']);$products[$code]=['id'=>(string)$pdo->lastInsertId(),'sku'=>$code.$tag,'name'=>$name.' '.$tag];}
$q=$pdo->prepare("INSERT INTO historical_return_price_revisions(product_id,price_year,revision,unit_price,status,reason,actor_user_id) VALUES (?,2024,1,'75.00','APPROVED','Isolated synthetic price',?)");$q->execute([$products['P012']['id'],$adminId]);
$config=$root.'/config/historical-returns.php';$original=file_get_contents($config);if($original===false)throw new RuntimeException('Cannot read config');
$current=require $config;if(($current['enabled']??null)!==false)throw new RuntimeException('Financial posting must initially be disabled');
$restore=static function()use($config,$original){file_put_contents($config,$original,LOCK_EX);};register_shutdown_function($restore);
$testConfig=['enabled'=>false,'preview'=>['enabled'=>true,'database'=>$schema,'representative_id'=>$actors['allowed']['id']]];
file_put_contents($config,"<?php\ndeclare(strict_types=1);\nreturn ".var_export($testConfig,true).";\n",LOCK_EX);
foreach(['DB_HOST'=>'127.0.0.1','DB_PORT'=>'3309','DB_NAME'=>$schema,'DB_USER'=>$auth['user'],'DB_PASSWORD'=>$auth['password'],'HISTORICAL_RETURN_TEST'=>'0','APP_ENV'=>'development','APP_BASE_PATH'=>''] as $key=>$value)putenv($key.'='.$value);unset($auth,$ini);
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('Cannot reserve test port');$address=stream_socket_get_name($socket,false);fclose($socket);$port=(int)substr(strrchr($address,':'),1);$base='http://127.0.0.1:'.$port;
$log=tempnam(sys_get_temp_dir(),'preview-log-');$jars=[tempnam(sys_get_temp_dir(),'preview-a-'),tempnam(sys_get_temp_dir(),'preview-b-')];$pipes=[];
$process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root.'/public',$root.'/tests/http-router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root,null,['bypass_shell'=>true,'create_no_window'=>true]);if(!is_resource($process))throw new RuntimeException('Test server unavailable');fclose($pipes[0]);
$request=static function(string $path,string $jar,?array $form=null)use($base):array{$c=curl_init($base.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>15]);if($form!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($form));}$body=curl_exec($c);if($body===false)throw new RuntimeException('HTTP request failed');$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);return [$status,$body];};
$token=static function(string $body):string{if(!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$body,$m))throw new RuntimeException('CSRF token unavailable');return $m[1];};
$count=0;$check=static function(bool $ok,string $message)use(&$count){if(!$ok)throw new RuntimeException($message);$count++;};
try{
 for($i=0;$i<50;$i++){if($s=@fsockopen('127.0.0.1',$port,$n,$e,.1)){fclose($s);break;}usleep(100000);}
 foreach(['allowed','other'] as $i=>$name){[$status,$login]=$request('/login',$jars[$i]);$check($status===200,'Login page');[$status]=$request('/login',$jars[$i],['csrf_token'=>$token($login),'username'=>$actors[$name]['username'],'password'=>$password]);$check($status===303,'Synthetic representative login');}
 $tables=['historical_customer_returns','historical_customer_return_lines','historical_return_intents','historical_return_identities','invoices','payments','payment_allocations','inventory_movements'];$counts=[];foreach($tables as $table)$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
 [$status,$page]=$request('/rep/invoices/new?language=en',$jars[0]);$check($status===200&&str_contains($page,'id="historical-return-preview"')&&!str_contains($page,'id="historical-return"'),'Authorized preview visible, posting UI absent');
 $check(str_contains($page,'value="2024"')&&str_contains($page,'value="2025"')&&str_contains($page,'Read-only verification'),'Year choices and read-only warning');
 $csrf=$token($page);[$status,$other]=$request('/rep/invoices/new',$jars[1]);$check($status===200&&!str_contains($other,'id="historical-return-preview"'),'Other representative cannot view preview');
 [$status,$si]=$request('/rep/invoices/new?language=si-LK',$jars[0]);$check($status===200&&str_contains($si,'පැරණි භාණ්ඩ ආපසු භාරගැනීම'),'Sinhala preview text');
 [$status,$data]=$request('/api/v1/rep/historical-prices?year=2024&q=NO_SUCH_PREVIEW_PRODUCT',$jars[0]);$json=json_decode($data,true,32,JSON_THROW_ON_ERROR);$check($status===200&&$json['prices']===[],'Missing historical price returns empty result');
 [$status,$data]=$request('/api/v1/rep/return-preview-products?year=2024&q='.urlencode($products['P012']['sku']),$jars[0]);$json=json_decode($data,true,32,JSON_THROW_ON_ERROR);$check($status===200&&count($json['products'])===1&&$json['products'][0]['unit_price']==='75.00','Selected representative can search active product and approved price');
 [$status]=$request('/api/v1/rep/return-preview-products?year=2024&q=P012',$jars[1]);$check($status===403,'Other representative cannot search preview products');
 $emptyJar=tempnam(sys_get_temp_dir(),'preview-unauth-');[$status]=$request('/api/v1/rep/return-preview-products?year=2024&q=P012',$emptyJar);@unlink($emptyJar);$check($status===401,'Unauthenticated product search denied');
 [$status]=$request('/api/v1/rep/return-intents',$jars[0],['csrf_token'=>$csrf,'customer_id'=>'1']);$check($status===503,'Direct intent creation blocked');
 [$status]=$request('/rep/invoices/new',$jars[0],['csrf_token'=>$csrf,'historical_return'=>['reason'=>'test']]);$check($status===409,'Direct return checkout blocked');
 [$status]=$request('/admin/historical-prices',$jars[0],['csrf_token'=>$csrf,'product_id'=>'1','price_year'=>'2024','unit_price'=>'1.00','reason'=>'test']);$check($status===403,'Representative price mutation denied');
 $stdin=tmpfile();$stdout=tmpfile();$stderr=tmpfile();fwrite($stdin,json_encode(['base'=>$base,'username'=>$actors['allowed']['username'],'password'=>$password,'products'=>$products],JSON_THROW_ON_ERROR));rewind($stdin);
 $browser=proc_open(['C:/nvm4w/nodejs/node.exe',$root.'/tests/historical-return-preview-browser.js'],[0=>$stdin,1=>$stdout,2=>$stderr],$browserPipes,$root,null,['bypass_shell'=>true,'create_no_window'=>true]);if(!is_resource($browser))throw new RuntimeException('Preview browser unavailable');$code=proc_close($browser);rewind($stdout);rewind($stderr);$browserOutput=stream_get_contents($stdout);$browserError=stream_get_contents($stderr);fclose($stdin);fclose($stdout);fclose($stderr);if($code!==0)throw new RuntimeException('Preview browser failed: '.trim($browserError));echo trim($browserOutput).PHP_EOL;
 foreach($tables as $table)$check((int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()===$counts[$table],'No mutation: '.$table);
 echo "$count isolated preview authorization, language, endpoint and no-write checks passed.\n";
}finally{proc_terminate($process);proc_close($process);foreach($jars as $jar)@unlink($jar);@unlink($log);$restore();}
