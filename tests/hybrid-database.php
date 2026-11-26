<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php'; require __DIR__.'/hybrid-fixtures.php';
use App\Services\{HybridInvoiceReader,HybridInvoiceReconciliation,HybridInvoiceRequest,HybridInvoiceSnapshots,HybridDatabaseScope,HybridInvoiceCheckout,PdoHybridCheckoutStore,Invoices};
$m=HybridIsolationHarness::manifest($argv[1]??''); $pdo=HybridIsolationHarness::connection($m);
HybridDatabaseScope::verify($pdo,$m);
$fixturePath=$m['directory'].'/fixtures-'.bin2hex(random_bytes(6)).'.json';
$f=hybridFixtures($pdo,$m);
file_put_contents($fixturePath,json_encode($f,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
file_put_contents($m['directory'].'/fixtures.json',json_encode($f,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$service=hybridTestService($pdo,$m,$f); $rep=['id'=>$f['users']['rep1']]; $admin=['id'=>$f['users']['admin']]; $checks=0; $cases=0;
function dbSame(mixed $a,mixed $b,string $label=''): void { global $checks; $checks++; if ($a!==$b) { throw new RuntimeException($label.' expected '.json_encode($b).' got '.json_encode($a)); } }
function dbReject(callable $fn,string $label=''): void { global $checks; $checks++; try { $fn(); } catch (Throwable $e) {
    if ($e instanceof App\Support\InventoryException || ($e instanceof PDOException && in_array((int)($e->errorInfo[1]??0),[1044,1049,1142,1227],true)) || strpos($e->getMessage(),'Injected ')===0) { return; }
    throw $e;
} throw new RuntimeException('Expected rejection '.$label); }
function dbSqlReject(callable $fn,array $codes): void { global $checks; $checks++; try { $fn(); } catch (PDOException $e) { if (in_array((int)($e->errorInfo[1]??0),$codes,true)) { return; } throw $e; } throw new RuntimeException('Expected SQL constraint rejection.'); }
function dbCase(string $name,callable $fn): void { global $cases; $fn(); $cases++; echo 'PASS '.$name.PHP_EOL; }
function businessDigest(PDO $pdo): string {
    $tables=['hybrid_commands','hybrid_command_results','invoices','invoice_items','online_invoice_details','invoice_sequence_counters','inventory_transactions','inventory_movements','representative_stock_balances','product_stock_balances','payments','payment_allocations','invoice_receipts','hybrid_invoice_snapshots','hybrid_invoice_line_snapshots','hybrid_invoice_segments','hybrid_unit_allocation_runs','hybrid_invoice_seals','rep_operating_state','sync_stream_state','change_log','audit_log','invoice_company_snapshots','invoice_language_snapshots','invoice_product_language_snapshots'];
    $data=[]; foreach ($tables as $table) { $rows=$pdo->query('SELECT * FROM '.$table)->fetchAll(); usort($rows,fn($a,$b)=>strcmp(HybridInvoiceRequest::json($a),HybridInvoiceRequest::json($b))); $data[$table]=$rows; } return HybridInvoiceRequest::digest($data);
}
function issueSynthetic(Invoices $service,array $actor,array $input): array { $review=$service->review($actor,$input); $input['review_hash']=$review['review_hash']; return [$service->issue($actor,$input),$input,$review]; }
function cloneSyntheticRow(PDO $pdo,string $table,string $source,array $overrides): void {
    if (!in_array($table,['hybrid_invoice_snapshots','hybrid_invoice_line_snapshots','hybrid_invoice_segments','hybrid_unit_allocation_runs'],true)) { throw new LogicException('Unsupported test projection.'); }
    $q=$pdo->prepare('SELECT * FROM '.$table.' WHERE invoice_id=? ORDER BY 1 LIMIT 1'); $q->execute([$source]); $row=array_merge($q->fetch(PDO::FETCH_ASSOC),$overrides);
    $pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
}
function competingRequests(PDO $pdo,array $m,array $inputs): array {
    $pdo->beginTransaction(); $pdo->query('SELECT id FROM inventory_settings WHERE id=1 FOR UPDATE'); $workers=[]; $paths=[];
    try {
        foreach ($inputs as $input) {
            $path=$m['directory'].'/concurrent-'.bin2hex(random_bytes(6)).'.json'; file_put_contents($path,json_encode($input,JSON_THROW_ON_ERROR)); $paths[]=$path;
            $pipes=[]; $process=proc_open([PHP_BINARY,__DIR__.'/hybrid-post-worker.php',$m['directory'].'/manifest.json',$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true,'create_new_console'=>false]);
            if (!is_resource($process)) { throw new RuntimeException('Worker launch failed.'); } fclose($pipes[0]); $workers[]=[$process,$pipes];
        }
        $ready=0; for ($i=0;$i<100;$i++) { $ready=0; foreach ($paths as $path) { $ready+=count(glob($path.'.ready.*')); } if ($ready===count($inputs)) { break; } usleep(20000); }
        dbSame($ready,count($inputs)); usleep(100000); $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    $out=[];
    foreach ($workers as [$process,$pipes]) { $stdout=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process); $row=json_decode(trim($stdout),true,512,JSON_THROW_ON_ERROR); if (!in_array($exit,[0,2],true)) { throw new RuntimeException('Worker transport failed: '.$stderr); } $out[]=$row; }
    return $out;
}

dbCase('physical scope, schema grants and migration 025 checksum',function () use ($pdo,$m) {
    dbSame((int)$pdo->query('SELECT @@port')->fetchColumn(),$m['port']); dbSame($pdo->query('SELECT DATABASE()')->fetchColumn(),$m['database']);
    dbReject(fn()=>$pdo->query('SELECT * FROM dharma_sales_phase1.users'),'operational schema denied');
    dbReject(fn()=>$pdo->exec('CREATE DATABASE forbidden_hybrid_escape'),'runtime DDL denied');
    $q=$pdo->query("SELECT checksum,status FROM schema_migrations WHERE version='025_hybrid_invoice_snapshots.sql'"); $row=$q->fetch(); dbSame($row['status'],'applied'); dbSame($row['checksum'],hash_file('sha256',APP_ROOT.'/database/migrations/025_hybrid_invoice_snapshots.sql'));
    $bad=$m; $bad['port']=3309; dbReject(fn()=>HybridDatabaseScope::verify($pdo,$bad),'wrong server');
});
dbCase('real authoritative review and atomic official invoice posting',function () use ($pdo,$service,$rep,$f,&$first) {
    $before=businessDigest($pdo); $input=hybridInput($f); $review=$service->review($rep,$input); dbSame(businessDigest($pdo),$before,'review has no writes'); dbSame($review['calculation']['final_payable_total'],'675.00');
    $input['review_hash']=$review['review_hash']; $first=$service->issue($rep,$input); dbSame($first['replayed'],false);
    $record=$service->get($rep,$first['invoice_id']); dbSame($record['grand_total'],'675.00'); dbSame($record['hybrid']['policy_version'],'HYBRID_PROMOTION_V1'); dbSame($record['items'][0]['free_quantity'],'1');
    dbSame($record['receipts'][0]['change_amount'],'9325.00'); dbSame($record['hybrid']['financial']['outstanding'],'0.00');
    $proof=(new HybridInvoiceReconciliation($pdo))->verify($rep,$first['invoice_id']); dbSame($proof['sealed'],true);
    $once=businessDigest($pdo); dbSame($service->issue($rep,$input)['replayed'],true); dbSame(businessDigest($pdo),$once);
    $input['amount_received']='9999.00'; dbReject(fn()=>$service->issue($rep,$input),'payload collision'); dbSame(businessDigest($pdo),$once);
});
dbCase('Suwada repeating groups, product presets and zero default',function () use ($pdo,$service,$rep,$f) {
    foreach ([['glucose','6','1'],['glucose','12','2'],['glucose','18','3'],['suwada','12','1'],['suwada','24','2'],['suwada','36','3']] as [$product,$quantity,$free]) {
        $in=hybridInput($f,$product,$quantity); unset($in['hybrid']['lines'][0]['product_discount'],$in['hybrid']['invoice_discount_percent']); $r=$service->review($rep,$in); dbSame($r['calculation']['lines'][0]['actual_free_quantity'],$free); dbSame($r['calculation']['combined_monetary_discount'],'0.00');
    }
    foreach (['0','25','30','35','other'] as $choice) { $in=hybridInput($f,'ordinary','1'); $in['hybrid']['lines'][0]['product_discount']=['choice'=>$choice]; if ($choice==='other') { $in['hybrid']['lines'][0]['product_discount']['percent']='50'; } $in['hybrid']['invoice_discount_percent']='0'; $r=$service->review($rep,$in); dbSame($r['calculation']['product_discount_total'],($choice==='other'?'50':$choice).'.00'); }
});
dbCase('limited complete replacement, partial shortage, conflicts and strict combined cap',function () use ($pdo,$service,$rep,$f) {
    $in=hybridInput($f,'replacement','24'); $in['hybrid']['lines'][0]['product_discount']=['choice'=>'0']; $in['hybrid']['lines'][0]['replace_free_entitlement']=true; $in['hybrid']['replacement_discount']=['choice'=>'25'];
    [$out,$posted,$review]=issueSynthetic($service,$rep,$in); dbSame($review['calculation']['replacement_discount_total'],'600.00'); dbSame($review['calculation']['lines'][0]['actual_free_quantity'],'0'); dbSame((new HybridInvoiceReconciliation($pdo))->verify($rep,$out['invoice_id'])['money'],true);
    $in=hybridInput($f,'partial','12'); $in['hybrid']['lines'][0]['product_discount']=['choice'=>'0']; $in['hybrid']['lines'][0]['replace_free_entitlement']=true; $in['hybrid']['replacement_discount']=['choice'=>'25']; dbReject(fn()=>$service->review($rep,$in),'partial entitlement shortage');
    foreach (['monetary','oil1'] as $product) { dbReject(fn()=>$service->review($rep,hybridInput($f,$product,'6')),$product); }
    $in=hybridInput($f,'ordinary','1'); $in['hybrid']['lines'][0]['product_discount']=['choice'=>'other','percent'=>'50']; $in['hybrid']['invoice_discount_percent']='50'; dbReject(fn()=>$service->review($rep,$in),'combined cap');
});
dbCase('multiple representatives, source spoofing and customer reassignment',function () use ($pdo,$service,$rep,$f) {
    $other=['id'=>$f['users']['rep2']]; [$out]=issueSynthetic($service,$other,hybridInput($f,'glucose','8','rep2')); dbSame($service->get($other,$out['invoice_id'])['rep_id'],$other['id']);
    dbReject(fn()=>$service->get($rep,$out['invoice_id']),'cross-rep invoice'); dbReject(fn()=>$service->review($other,hybridInput($f)),'customer assignment');
    $in=hybridInput($f); $in['stock_source']=$other['id']; dbReject(fn()=>$service->review($rep,$in),'source spoof');
    $in=hybridInput($f); $in['review_hash']=$service->review($rep,$in)['review_hash'];
    $pdo->beginTransaction(); try { $pdo->prepare('UPDATE customers SET assigned_rep_id=? WHERE id=?')->execute([$other['id'],$f['customers']['rep1']]); dbReject(fn()=>$service->issue($rep,$in),'reassignment'); } finally { $pdo->rollBack(); }
});
dbCase('real rollback at every posting boundary preserves all prior business rows',function () use ($pdo,$m,$f,$rep) {
    foreach (['claim','number','invoice','items','stock','payment','snapshot_parent','snapshot_lines','snapshot_segments','snapshot_runs','seal','streams','audit','result'] as $step) {
        $in=hybridInput($f); $service=hybridTestService($pdo,$m,$f,static function ($name) use ($step) { if ($name===$step) { throw new RuntimeException('Injected '.$step); } });
        $in['review_hash']=$service->review($rep,$in)['review_hash']; $before=businessDigest($pdo); dbReject(fn()=>$service->issue($rep,$in),'injection '.$step); dbSame(businessDigest($pdo),$before,$step.' rolled back');
    }
});
dbCase('stale price and customer terms fail before numbering',function () use ($pdo,$service,$rep,$f) {
    $in=hybridInput($f); $in['review_hash']=$service->review($rep,$in)['review_hash'];
    $pdo->beginTransaction(); try { $pdo->prepare('UPDATE products SET selling_price=100.01,version=version+1 WHERE id=?')->execute([$f['products']['glucose']]); $before=businessDigest($pdo); dbReject(fn()=>$service->issue($rep,$in),'stale price'); dbSame(businessDigest($pdo),$before); } finally { $pdo->rollBack(); }
    $pdo->beginTransaction(); try { $pdo->prepare('UPDATE customers SET credit_terms_days=14,version=version+1 WHERE id=?')->execute([$f['customers']['rep1']]); $before=businessDigest($pdo); dbReject(fn()=>$service->issue($rep,$in),'stale customer'); dbSame(businessDigest($pdo),$before); } finally { $pdo->rollBack(); }
});
dbCase('immutable SQL evidence, late-child insertion and foreign keys reject',function () use ($pdo,$first) {
    $id=$pdo->quote($first['invoice_id']);
    foreach (['hybrid_invoice_snapshots','hybrid_invoice_line_snapshots','hybrid_invoice_segments','hybrid_unit_allocation_runs','hybrid_invoice_seals'] as $table) {
        dbSqlReject(fn()=>$pdo->exec('DELETE FROM '.$table.' WHERE invoice_id='.$id),[1644]);
        dbSqlReject(fn()=>$pdo->exec('UPDATE '.$table.' SET invoice_id=invoice_id WHERE invoice_id='.$id),[1644]);
    }
    dbSqlReject(fn()=>$pdo->exec('INSERT INTO hybrid_unit_allocation_runs SELECT invoice_id,line_number,segment_number,position_start+100,position_count,gross_amount,product_discount_amount,replacement_discount_amount,invoice_discount_amount,payable_amount FROM hybrid_unit_allocation_runs WHERE invoice_id='.$id),[1048]);
    dbSqlReject(fn()=>$pdo->exec("INSERT INTO hybrid_command_results(request_id,invoice_id,result_json) VALUES ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','{}')"),[1452]);
});
dbCase('current payments, outstanding and Refund Due use posted collections only',function () use ($pdo,$service,$rep,$admin,$f) {
    $in=hybridInput($f); $in['payment_type']='CREDIT'; $in['amount_received']='100.00'; [$out]=issueSynthetic($service,$rep,$in); $id=$out['invoice_id'];
    dbSame($service->get($rep,$id)['hybrid']['financial']['outstanding'],'575.00');
    $service->pay($rep,$id,['request_id'=>hybridUuid(),'amount_received'=>'200.00']); dbSame($service->get($rep,$id)['hybrid']['financial']['outstanding'],'375.00');
    dbSame($service->outstanding($rep,$f['customers']['rep1']),'375.00');
    dbReject(fn()=>$service->void($admin,$id,['request_id'=>hybridUuid(),'reason'=>'Synthetic unreturned full void']),'Hybrid requires explicit residual return conditions');
    $r=$service->get($rep,$id); dbSame($r['hybrid']['financial']['refund_due'],'0.00'); dbSame($r['hybrid']['financial']['outstanding'],'375.00'); dbSame($r['hybrid']['financial']['actual_cash_refunds'],'UNAVAILABLE');
    dbSame((new HybridInvoiceReconciliation($pdo))->verify($rep,$id)['money'],true);
});
dbCase('disabled gates reject new commands but scoped accepted replay remains safe',function () use ($pdo,$m,$f,$rep,$service) {
    [$out,$input]=issueSynthetic($service,$rep,hybridInput($f)); $before=businessDigest($pdo);
    $config=hybridTestConfig($m,$f); $config['enabled']=false; $disabled=new HybridInvoiceCheckout(new PdoHybridCheckoutStore($pdo,$m,true),$config);
    $pdo->beginTransaction(); try {
        $pdo->exec('UPDATE hybrid_operating_state SET enabled=0,sale_posting=0 WHERE id=1');
        dbSame($disabled->issue($rep,$input)['replayed'],true);
        $new=hybridInput($f); $new['review_hash']=$input['review_hash']; dbReject(fn()=>$disabled->issue($rep,$new),'new disabled');
    } finally { $pdo->rollBack(); }
    dbSame(businessDigest($pdo),$before);
    dbReject(fn()=>(new Invoices($pdo))->review($rep,hybridInput($f)),'operational defaults remain disabled');
    $held=new HybridInvoiceCheckout(new PdoHybridCheckoutStore($pdo,$m,true),hybridTestConfig($m,$f)); $new=hybridInput($f); $new['review_hash']=$service->review($rep,$new)['review_hash']; dbReject(fn()=>$held->issue($rep,$new),'independent posting hold');
});
dbCase('overlapping physical unit runs cannot be accepted or sealed',function () use ($pdo,$m,$f,$rep) {
    $in=hybridInput($f); $in['review_hash']=hybridTestService($pdo,$m,$f)->review($rep,$in)['review_hash']; $before=businessDigest($pdo); $inserted=false;
    $service=hybridTestService($pdo,$m,$f,function ($step) use ($pdo,$in,&$inserted) {
        if ($step==='snapshot_runs' && !$inserted) {
            $inserted=true; $q=$pdo->prepare('INSERT INTO hybrid_unit_allocation_runs SELECT invoice_id,line_number,segment_number,position_start+1,1,gross_amount,product_discount_amount,replacement_discount_amount,invoice_discount_amount,payable_amount FROM hybrid_unit_allocation_runs WHERE invoice_id=? LIMIT 1'); $q->execute([$in['request_id']]);
        }
    });
    try { $service->issue($rep,$in); throw new RuntimeException('Overlapping ranges were sealed.'); } catch (LogicException $e) { dbSame(strpos($e->getMessage(),'child count mismatch')!==false,true); }
    dbSame($inserted,true); dbSame(businessDigest($pdo),$before);
});
dbCase('real concurrent duplicate commands issue once with official sequence continuity',function () use ($pdo,$m,$f,$rep,$service) {
    $in=hybridInput($f); $in['review_hash']=$service->review($rep,$in)['review_hash'];
    $path=$m['directory'].'/concurrent-'.bin2hex(random_bytes(6)).'.json'; file_put_contents($path,json_encode($in,JSON_THROW_ON_ERROR));
    $before=$pdo->prepare('SELECT quantity FROM representative_stock_balances WHERE rep_id=? AND product_id=?'); $before->execute([$rep['id'],$f['products']['glucose']]); $quantity=(int)$before->fetchColumn();
    $pdo->beginTransaction(); $pdo->query('SELECT id FROM inventory_settings WHERE id=1 FOR UPDATE'); $workers=[];
    try {
        for ($i=0;$i<2;$i++) {
            $pipes=[]; $process=proc_open([PHP_BINARY,__DIR__.'/hybrid-post-worker.php',$m['directory'].'/manifest.json',$path],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,null,['bypass_shell'=>true,'create_new_console'=>false]);
            if (!is_resource($process)) { throw new RuntimeException('Worker launch failed.'); } fclose($pipes[0]); $workers[]=[$process,$pipes];
        }
        for ($i=0;$i<100 && count(glob($path.'.ready.*'))<2;$i++) { usleep(20000); }
        dbSame(count(glob($path.'.ready.*')),2); $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    $replays=[]; foreach ($workers as [$process,$pipes]) {
        $stdout=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
        if ($exit!==0) { throw new RuntimeException('Worker failed: '.$stdout.' '.$stderr); } $response=json_decode(trim($stdout),true,512,JSON_THROW_ON_ERROR); dbSame($response['ok'],true); $replays[]=$response['result']['replayed'];
    }
    sort($replays); dbSame($replays,[false,true]);
    $before->execute([$rep['id'],$f['products']['glucose']]); dbSame((int)$before->fetchColumn(),$quantity-9);
    $q=$pdo->prepare('SELECT COUNT(*) FROM invoices WHERE id=?'); $q->execute([$in['request_id']]); dbSame((int)$q->fetchColumn(),1);
    dbSame((new HybridInvoiceReconciliation($pdo))->verify($rep,$in['request_id'])['sealed'],true);
});
dbCase('concurrent different requests cannot oversell paid-plus-free stock',function () use ($pdo,$m,$f,$rep,$service) {
    $one=hybridInput($f,'limited','8'); $one['review_hash']=$service->review($rep,$one)['review_hash']; $two=hybridInput($f,'limited','8'); $two['review_hash']=$service->review($rep,$two)['review_hash'];
    $responses=competingRequests($pdo,$m,[$one,$two]); $accepted=array_values(array_filter($responses,fn($r)=>$r['ok'])); $rejected=array_values(array_filter($responses,fn($r)=>!$r['ok']));
    dbSame(count($accepted),1); dbSame(count($rejected),1); dbSame($rejected[0]['class'],App\Support\InventoryException::class);
    $q=$pdo->prepare('SELECT quantity FROM representative_stock_balances WHERE rep_id=? AND product_id=?'); $q->execute([$rep['id'],$f['products']['limited']]); dbSame((int)$q->fetchColumn(),0);
    $q=$pdo->prepare('SELECT COUNT(*) FROM hybrid_commands WHERE request_id IN (?,?)'); $q->execute([$one['request_id'],$two['request_id']]); dbSame((int)$q->fetchColumn(),1);
});
dbCase('immutable reader ignores current offers/prices and rejects deactivated actors',function () use ($pdo,$service,$rep,$f,$first) {
    $pdo->beginTransaction(); try {
        $pdo->prepare('UPDATE products SET selling_price=999.99,is_active=0,version=version+1 WHERE id=?')->execute([$f['products']['glucose']]);
        $record=$service->get($rep,$first['invoice_id']); dbSame($record['hybrid']['calculation']['final_payable_total'],'675.00'); dbSame($record['hybrid']['financial']['linked_return_status'],App\Services\HybridInvoiceBalances::schema($pdo)?'AVAILABLE':'NOT_IMPLEMENTED');
        $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$rep['id']]); dbReject(fn()=>$service->get($rep,$first['invoice_id']),'deactivated actor');
    } finally { $pdo->rollBack(); }
});
dbCase('odd-cent strict cap rejects after rounding without changing ordinary legacy rates',function () use ($pdo,$service,$rep,$f) {
    $pdo->beginTransaction(); try {
        $pdo->prepare('UPDATE products SET selling_price=100.01,version=version+1 WHERE id=?')->execute([$f['products']['ordinary']]);
        $in=hybridInput($f,'ordinary','1'); $in['hybrid']['lines'][0]['product_discount']=['choice'=>'0']; $in['hybrid']['invoice_discount_percent']='50'; dbReject(fn()=>$service->review($rep,$in),'50.01 exceeds exact cap');
        $in['hybrid']['invoice_discount_percent']='49.99'; dbSame($service->review($rep,$in)['calculation']['combined_monetary_discount'],'49.99');
    } finally { $pdo->rollBack(); }
});
dbCase('reconciliation detects a posted allocation without an authoritative receipt',function () use ($pdo,$service,$rep,$f) {
    $pdo->beginTransaction(); try {
        $in=hybridInput($f); $in['amount_received']='100.00'; [$out]=issueSynthetic($service,$rep,$in); $extra=hybridUuid();
        $pdo->prepare("INSERT INTO payments(id,customer_id,received_by_user_id,method,amount,received_at_client_utc,accepted_at_utc,status) VALUES (?,?,?,'CASH',1.00,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),'POSTED')")->execute([$extra,$f['customers']['rep1'],$rep['id']]);
        $pdo->prepare('INSERT INTO payment_allocations(payment_id,invoice_id,customer_id,amount) VALUES (?,?,?,1.00)')->execute([$extra,$out['invoice_id'],$f['customers']['rep1']]);
        try { (new HybridInvoiceReconciliation($pdo))->verify($rep,$out['invoice_id']); throw new RuntimeException('Missing receipt was accepted.'); }
        catch (LogicException $e) { dbSame(strpos($e->getMessage(),'without an authoritative receipt')!==false,true); }
    } finally { $pdo->rollBack(); }
});
dbCase('migration 025 enforces rates, exact cap, unit cents, quantities and parent identity in SQL',function () use ($pdo,$rep,$f,$first) {
    $pdo->beginTransaction(); try {
        $legacy=new Invoices($pdo); $input=['request_id'=>hybridUuid(),'customer_id'=>$f['customers']['rep1'],'payment_type'=>'CASH','amount_received'=>'10000.00','items'=>[['product_id'=>$f['products']['ordinary'],'quantity'=>'1']]];
        [$out]=issueSynthetic($legacy,$rep,$input); $id=$out['invoice_id']; $source=$first['invoice_id'];
        dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_invoice_snapshots',$source,['invoice_id'=>$id,'original_subtotal'=>'100.01','product_discount'=>'0.00','replacement_discount'=>'0.00','invoice_discount_base'=>'100.01','invoice_percent'=>'50.00','invoice_discount'=>'50.01','combined_discount'=>'50.01','payable'=>'50.00']),[4025,3819]);
        dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_invoice_snapshots',$source,['invoice_id'=>$id,'invoice_percent'=>'50.01']),[4025,3819]);
        dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_invoice_snapshots',$source,['invoice_id'=>$id,'policy_version'=>'PROMO_CHOICE_V1']),[4025,3819]);
        dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_invoice_snapshots',$source,['invoice_id'=>hybridUuid()]),[1452]);
        cloneSyntheticRow($pdo,'hybrid_invoice_snapshots',$source,['invoice_id'=>$id]);
        dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_invoice_line_snapshots',$source,['invoice_id'=>$id,'product_percent'=>'50.01']),[4025,3819]);
        cloneSyntheticRow($pdo,'hybrid_invoice_line_snapshots',$source,['invoice_id'=>$id]); cloneSyntheticRow($pdo,'hybrid_invoice_segments',$source,['invoice_id'=>$id]);
        dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_unit_allocation_runs',$source,['invoice_id'=>$id,'payable_amount'=>'89.99']),[4025,3819]);
        dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_unit_allocation_runs',$source,['invoice_id'=>$id,'position_count'=>0]),[4025,3819]);
        dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_unit_allocation_runs',$source,['invoice_id'=>$id,'segment_number'=>999]),[1452]);
        dbReject(fn()=>$legacy->get($rep,$id),'unsealed invoice cannot become a valid Hybrid reader result');
    } finally { $pdo->rollBack(); }
    dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_invoice_line_snapshots',$first['invoice_id'],['line_number'=>999]),[1048]);
    dbSqlReject(fn()=>cloneSyntheticRow($pdo,'hybrid_invoice_segments',$first['invoice_id'],['segment_number'=>999]),[1048]);
    $q=$pdo->prepare("UPDATE hybrid_commands SET payload_hash=REPEAT('0',64) WHERE request_id=?"); dbSqlReject(fn()=>$q->execute([$first['invoice_id']]),[1644]);
    $q=$pdo->prepare('DELETE FROM hybrid_command_results WHERE request_id=?'); dbSqlReject(fn()=>$q->execute([$first['invoice_id']]),[1644]);
});
echo 'Stage 2C database: '.$cases.' cases, '.$checks.' checks passed against a physically isolated server.'.PHP_EOL;
