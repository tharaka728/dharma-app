<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);require dirname(__DIR__).'/app/bootstrap.php';
$pdo=App\Support\Database::connect();if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1'||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Exact application target required.');
$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try{
 $reserved=[];foreach(['SELECT sku code FROM product_sku_history','SELECT customer_code code FROM customer_code_history','SELECT code FROM business_code_registry'] as $sql)foreach($pdo->query($sql) as $row)$reserved[strtoupper($row['code'])]=true;
 $counters=$pdo->query('SELECT prefix,next_number FROM business_code_counters ORDER BY prefix')->fetchAll();$next=array_column($counters,'next_number','prefix');$mapping=[];
 $sources=[['P','products','SELECT id entity_key,sku old_reference FROM products ORDER BY id'],['C','customers','SELECT id entity_key,customer_code old_reference FROM customers ORDER BY id'],['S','inventory_transactions',"SELECT id entity_key,id old_reference FROM inventory_transactions WHERE type IN ('RECEIPT','ADJUSTMENT') ORDER BY posted_at_utc,id"],['A','stock_transfers','SELECT id entity_key,id old_reference FROM stock_transfers ORDER BY posted_at_utc,id'],['M','promotions','SELECT id entity_key,name old_reference FROM promotions ORDER BY id'],['R','users',"SELECT id entity_key,username old_reference FROM users WHERE role='SALES_REP' ORDER BY id"]];
 foreach($sources as [$prefix,$entity,$sql])foreach($pdo->query($sql) as $row){
  if($prefix==='P'&&!preg_match('/^PRD-[A-F0-9]{32}$/D',$row['old_reference']))continue;
  if($prefix==='C'&&!preg_match('/^CUS-[A-F0-9]{32}$/D',$row['old_reference']))continue;
  if(!in_array($prefix,['P','C'],true)&&App\Support\BusinessCode::lookup($pdo,$prefix,(string)$row['entity_key']))continue;
  do{$code=App\Support\BusinessCode::format($prefix,(string)$next[$prefix]);$next[$prefix]=(string)((int)$next[$prefix]+1);}while(isset($reserved[$code]));$reserved[$code]=true;
  $mapping[]=['entity'=>$entity,'internal_key_unchanged'=>(string)$row['entity_key'],'old_reference'=>$row['old_reference'],'proposed_code'=>$code,'operation'=>in_array($prefix,['P','C'],true)?'master_code_only_preserve_code_history':'add_display_alias_only'];
 }
 $report=['created_at_utc'=>gmdate('c'),'source'=>'dharma_sales_phase1','status'=>'DRY RUN ONLY — owner review required; no codes reserved or changed','counters'=>$counters,'mapping'=>$mapping,'immutable_invoice_or_audit_rewrites'=>false,'manual_master_codes_preserved'=>true,'revalidation_required_before_execution'=>true];
 $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
$path='C:/mariadb-clean/ayurveda-phase1-local/private/code-map-'.gmdate('YmdHis').'.json';$handle=fopen($path,'x');if(!$handle)throw new RuntimeException('Cannot create new private mapping report');fwrite($handle,json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));fclose($handle);
echo json_encode(['dry_run'=>true,'records'=>count($mapping),'report'=>$path,'sha256'=>hash_file('sha256',$path),'live_records_changed'=>0],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
