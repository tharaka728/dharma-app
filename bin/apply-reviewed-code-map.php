<?php
declare(strict_types=1);
// Explicit owner-approved data migration. Never an HTTP route or automatic migration.
if(PHP_SAPI!=='cli')exit(1);require dirname(__DIR__).'/app/bootstrap.php';
use App\Support\BusinessCode;
$test=in_array('--isolated-test',$argv,true);$execute=in_array('--execute-approved',$argv,true);
if(!$test&&!$execute)throw new RuntimeException('Use --isolated-test first, then --execute-approved only for the reviewed owner-approved map.');
$path='C:/mariadb-clean/ayurveda-phase1-local/private/code-map-20261111165911.json';
$expected='160aa891fde94f630b5a81223c93077070ad2fa32e7d03ab5be625ff9b61a8ce';
if(!hash_equals($expected,hash_file('sha256',$path)))throw new RuntimeException('Approved mapping checksum mismatch.');
$backup='C:/mariadb-clean/ayurveda-phase1-local/private/phase12-20261111171030/application.sql';
if(!hash_equals('d4708215511b7efac18ff8b4c33f0f38e381ec8b55c05af71fa20e21435b6272',hash_file('sha256',$backup)))throw new RuntimeException('Pre-migration backup mismatch.');
$map=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);if($map['source']!=='dharma_sales_phase1'||count($map['mapping'])!==8)throw new RuntimeException('Approved scope mismatch.');
$schema=$test?'dharma_sales_phase12_restore_20261111171030':'dharma_sales_phase1';
$receipt=$path.($test?'.isolated-applied.json':'.applied.json');if(is_file($receipt))throw new RuntimeException('Execution receipt already exists; no migration repeated.');
$ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$credentials=$ini['client']??$ini;
$pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname='.$schema.';charset=utf8mb4',$credentials['user'],$credentials['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$pdo->exec("SET time_zone='+00:00'");
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$schema||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Exact target mismatch.');
$protected=['invoices','invoice_items','invoice_discount_snapshots','payments','payment_allocations','invoice_receipts','invoice_voids','inventory_transactions','inventory_movements','stock_transfers','product_stock_balances','representative_stock_balances','devices','sync_operations','offline_sale_payloads','offline_sale_reviews','invoice_sequence_counters','invoice_number_allocations','users'];
$digest=static function(string $table)use($pdo):string{$keys=$pdo->query("SHOW KEYS FROM `$table` WHERE Key_name='PRIMARY'")->fetchAll();usort($keys,fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);$order=implode(',',array_map(fn($r)=>'`'.$r['Column_name'].'`',$keys));$hash=hash_init('sha256');foreach($pdo->query('SELECT * FROM `'.$table.'`'.($order?' ORDER BY '.$order:'')) as $row)hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");return hash_final($hash);};
$prefixes=['products'=>'P','customers'=>'C','inventory_transactions'=>'S','stock_transfers'=>'A','promotions'=>'M','users'=>'R'];
$pdo->beginTransaction();$changed=0;
try{
 $pdo->query('SELECT id FROM inventory_settings WHERE id=1 FOR UPDATE')->fetchColumn();$pdo->query('SELECT id FROM promotion_write_gate WHERE id=1 FOR UPDATE')->fetchColumn();
 foreach($map['mapping'] as $row){$table=$row['entity'];if(!isset($prefixes[$table]))throw new RuntimeException('Unknown entity.');$q=$pdo->prepare("SELECT id FROM `$table` WHERE id=? FOR UPDATE");$q->execute([$row['internal_key_unchanged']]);if($q->fetchColumn()===false)throw new RuntimeException('Mapping target missing.');}
 $counters=$pdo->query('SELECT prefix,next_number FROM business_code_counters ORDER BY prefix FOR UPDATE')->fetchAll();if($counters!==$map['counters'])throw new RuntimeException('Counters changed. Regenerate and review the mapping; no changes made.');
 $before=[];foreach($protected as $table)$before[$table]=$digest($table);$auditBefore=$pdo->query('SELECT * FROM audit_log ORDER BY id')->fetchAll();
 foreach($map['mapping'] as $row){
  $table=$row['entity'];$id=$row['internal_key_unchanged'];$prefix=$prefixes[$table];$master=in_array($prefix,['P','C'],true);
  $column=$prefix==='P'?'sku':($prefix==='C'?'customer_code':($prefix==='R'?'username':($prefix==='M'?'name':'id')));
  $q=$pdo->prepare("SELECT `$column` FROM `$table` WHERE id=?");$q->execute([$id]);if($q->fetchColumn()!==$row['old_reference'])throw new RuntimeException('A source reference changed; no migration applied.');
  $key=$master?'legacy:'.$id:$id;$code=BusinessCode::next($pdo,$prefix,$key);if($code!==$row['proposed_code'])throw new RuntimeException('Proposed code changed; no migration applied.');
  if($master){$history=$prefix==='P'?'product_sku_history':'customer_code_history';$owner=$prefix==='P'?'product_id':'customer_id';
   foreach([$row['old_reference'],$code] as $value){$q=$pdo->prepare("SELECT `$owner` FROM `$history` WHERE `$column`=?");$q->execute([$value]);$old=$q->fetchColumn();if($old!==false&&(string)$old!==$id)throw new RuntimeException('Code history belongs to another record.');if($old===false)$pdo->prepare("INSERT INTO `$history`(`$column`,`$owner`) VALUES (?,?)")->execute([$value,$id]);}
   $pdo->prepare("UPDATE `$table` SET `$column`=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?")->execute([$code,$id]);
  }
  $pdo->prepare("INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,reason,before_json,after_json) VALUES (NULL,'APPROVED_SHORT_CODE_MIGRATION',?,?,?, ?,?)")->execute([$table,$id,'Owner approved reviewed map SHA256 '.$expected,json_encode(['reference'=>$row['old_reference']],JSON_THROW_ON_ERROR),json_encode(['reference'=>$code,'internal_id_preserved'=>true,'display_alias_only'=>!$master],JSON_THROW_ON_ERROR)]);$changed++;
 }
 foreach($protected as $table)if(!hash_equals($before[$table],$digest($table)))throw new RuntimeException('Protected table changed: '.$table);
 $oldAudits=$pdo->query('SELECT * FROM audit_log ORDER BY id LIMIT '.count($auditBefore))->fetchAll();if($oldAudits!==$auditBefore)throw new RuntimeException('Existing audit history changed.');
 $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
$result=['target'=>$schema,'approved_map_sha256'=>$expected,'records_applied'=>$changed,'protected_tables_unchanged'=>count($protected),'existing_audit_rows_unchanged'=>count($auditBefore),'historical_invoice_rewrites'=>0,'old_master_codes_retained'=>true,'receipt'=>$receipt];
$file=fopen($receipt,'x');if(!$file)throw new RuntimeException('Migration committed; cannot write receipt. Inspect registry/audit before any retry.');fwrite($file,json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($file);echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
