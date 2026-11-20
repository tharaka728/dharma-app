<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli'||getenv('DB_NAME')!=='dharma_sales_phase9_test')throw new RuntimeException('Dedicated test target required.');
$pdo=App\Support\Database::connect();$actor=$pdo->query("SELECT id FROM users WHERE role='ADMIN' AND is_active=1 ORDER BY id LIMIT 1")->fetch();$category=$pdo->query('SELECT id FROM categories WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();if(!$actor||!$category)throw new RuntimeException('Run isolated fixtures first.');
$tag='Short-code race '.App\Services\Inventory::uuid();$workers=[];
for($i=0;$i<2;$i++){
 $pipes=[];$process=proc_open([PHP_BINARY,APP_ROOT.'/tests/phase12-code-worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true]);
 if(!is_resource($process))throw new RuntimeException('Cannot start code-race worker.');
 fwrite($pipes[0],json_encode(['actor'=>$actor,'input'=>['create_token'=>App\Services\Inventory::uuid(),'sku'=>'','name'=>$tag,'category_id'=>(string)$category,'unit_of_measure'=>'unit','selling_price'=>'1.00','cost_price'=>'','description'=>'Dedicated test database only']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];
}
foreach($workers as [$process,$pipes]){$answer=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0||$answer!=='CREATED'||$error!=='')throw new RuntimeException('Distinct-token concurrent creation failed.');}
$q=$pdo->prepare('SELECT sku FROM products WHERE name=?');$q->execute([$tag]);$codes=$q->fetchAll(PDO::FETCH_COLUMN);if(count($codes)!==2||count(array_unique($codes))!==2)throw new RuntimeException('Concurrent codes are not unique.');
foreach($codes as $code)if(!preg_match('/^P[0-9]{3,}$/D',$code))throw new RuntimeException('Invalid short code.');
echo "2 distinct-token concurrent product creation and uniqueness checks passed in the dedicated test database.\n";
