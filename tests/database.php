<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Support\Database;
use App\Support\Migrator;
// An explicit dedicated *_test database is mandatory; never use an operational database.
if (!preg_match('/^[a-zA-Z0-9_]+_test$/D', getenv('DB_NAME') ?: '') && !(PHP_SAPI === 'cli' && in_array('--existing-app', $argv, true) && getenv('DB_NAME') === 'dharma_sales_phase1')) {
    fwrite(STDERR, "Set DB_NAME to a dedicated empty database ending _test. No data will be deleted.\n"); exit(1);
}
try {
    $pdo = Database::connect();
    if (in_array('--existing-app', $argv, true) && (getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_PORT') !== '3309' || !in_array($pdo->query('SELECT DATABASE()')->fetchColumn(),['dharma_sales_phase1','dharma_sales_phase9_test'],true))) { throw new RuntimeException('Existing-app regression target mismatch'); }
    Migrator::run($pdo);
    Migrator::run($pdo);
    if ($pdo->query("SELECT @@session.time_zone")->fetchColumn() !== '+00:00') { throw new RuntimeException('UTC connection'); }
    if ($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) { throw new RuntimeException('Native prepares required'); }
    $pdo->beginTransaction();
    $n = bin2hex(random_bytes(6));
    $pdo->prepare("INSERT INTO users(name,username,password_hash,role) VALUES ('Test',?,?,'ADMIN')")->execute([$n,password_hash($n,PASSWORD_DEFAULT)]);
    $uid = $pdo->lastInsertId();
    $pdo->exec("INSERT INTO categories(name) VALUES ('$n')"); $cat = $pdo->lastInsertId();
    $pdo->exec("INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES ('$n','Test',$cat,1,2)"); $p = $pdo->lastInsertId();
    $pdo->exec("INSERT INTO products(sku,name,category_id,cost_price,selling_price) VALUES ('x$n','Other',$cat,1,2)"); $other = $pdo->lastInsertId();
    $pdo->exec("INSERT INTO product_batches(product_id,batch_number) VALUES ($p,'B1')"); $b = $pdo->lastInsertId();
    $pdo->exec("INSERT INTO stock_locations(type,code) VALUES ('WAREHOUSE','$n')"); $loc = $pdo->lastInsertId();
    $passed = 0;
    $reject = static function (string $sql) use ($pdo, &$passed): void {
        try { $pdo->exec($sql); } catch (PDOException $e) {
            if (!in_array((int) ($e->errorInfo[1] ?? 0), [1062,1451,1452,1644,3819,4025], true)) { throw $e; }
            $passed++; return;
        }
        throw new RuntimeException('Constraint unexpectedly allowed: ' . $sql);
    };
    $reject("INSERT INTO product_batches(product_id,batch_number) VALUES ($p,'B1')");
    $reject("INSERT INTO inventory_balances(location_id,product_id,batch_id,quantity) VALUES ($loc,$p,$b,-1)");
    $reject("INSERT INTO inventory_balances(location_id,product_id,batch_id,quantity) VALUES ($loc,$other,$b,1)");
    $reject("INSERT INTO product_batch_versions(batch_id,version_number,manufacturing_date,expiry_date,reason,recorded_by_user_id) VALUES ($b,1,'2026-11-01','2026-10-01','test',$uid)");
    $pdo->exec("INSERT INTO product_batch_versions(batch_id,version_number,manufacturing_date,expiry_date,reason,recorded_by_user_id) VALUES ($b,1,'2026-01-01','2027-01-01','test',$uid)");
    $reject("UPDATE product_batch_versions SET reason='rewrite' WHERE batch_id=$b");
    $reject("DELETE FROM product_batch_versions WHERE batch_id=$b");
    $reject("DELETE FROM products WHERE id=$p");
    $reject("INSERT INTO stock_locations(type,code,rep_id) VALUES ('WAREHOUSE','bad$n',$uid)");
    $pdo->prepare("INSERT INTO inventory_transactions(id,type,reason,created_by_user_id) VALUES (?,'OPENING','test',?)")->execute(['11111111-1111-4111-8111-111111111111',$uid]);
    $reject("UPDATE inventory_transactions SET reason='rewrite' WHERE id='11111111-1111-4111-8111-111111111111'");
    $reject("INSERT INTO devices(id,user_id,label) VALUES ('INVALID',$uid,'bad')");
    $pdo->prepare("INSERT INTO users(name,username,password_hash,role) VALUES ('Rep',?,?,'SALES_REP')")->execute(['rep'.$n,password_hash($n,PASSWORD_DEFAULT)]);
    $rep = $pdo->lastInsertId();
    $device = '22222222-2222-4222-8222-222222222222';
    $pdo->prepare("INSERT INTO devices(id,user_id,label) VALUES (?,?,'Test phone')")->execute([$device,$rep]);
    $reject("INSERT INTO rep_operating_state(rep_id,primary_device_id) VALUES ($uid,'$device')");
    $reject("INSERT INTO invoice_sequence_counters(rep_id,next_sequence) VALUES ($rep,0)");
    $allocation = '33333333-3333-4333-8333-333333333333';
    $reject("INSERT INTO invoice_number_allocations(id,rep_id,device_id,start_sequence,end_sequence) VALUES ('$allocation',$rep,'$device',20,10)");
    $pdo->prepare('INSERT INTO invoice_number_allocations(id,rep_id,device_id,start_sequence,end_sequence) VALUES (?,?,?,1,1000)')->execute([$allocation,$rep,$device]);
    $reject("UPDATE invoice_number_allocations SET start_sequence=2 WHERE id='$allocation'");
    $reject("INSERT INTO invoice_number_allocations(id,rep_id,device_id,start_sequence,end_sequence) VALUES ('44444444-4444-4444-8444-444444444444',$rep,'$device',1,2000)");
    $reject("INSERT INTO offline_policy_grants(id,rep_id,device_id,schema_version,approved_snapshot,snapshot_hash,issued_at_utc,expires_at_utc) VALUES ('55555555-5555-4555-8555-555555555555',$rep,'$device',1,'{}',REPEAT('a',64),'2026-11-01','2026-11-09')");
    $pdo->rollBack();
    echo "$passed database constraint checks passed; fixtures rolled back. Migration repeatability, UTC and native prepares passed.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, 'Database verification failed: ' . $e->getMessage() . PHP_EOL); exit(1);
}
