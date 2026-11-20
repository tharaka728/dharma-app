<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);require dirname(__DIR__).'/app/bootstrap.php';$pdo=App\Support\Database::connect();
if(getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_PORT')!=='3309'||!in_array($pdo->query('SELECT DATABASE()')->fetchColumn(),['dharma_sales_phase1','dharma_sales_phase9_test'],true))throw new RuntimeException('Exact application target required.');
$checks=[
 'Accepted offline operations have matching immutable invoices'=>"SELECT o.id FROM sync_operations o JOIN offline_sale_payloads p ON p.operation_id=o.id LEFT JOIN invoices i ON i.id=o.invoice_id WHERE o.result_status='ACCEPTED' AND (i.id IS NULL OR i.id<>o.id OR i.rep_id<>o.user_id OR i.device_id<>o.device_id OR i.posting_channel<>'SYNC')",
 'No revoked-device sale posted after revocation'=>"SELECT o.id FROM sync_operations o JOIN offline_sale_payloads p ON p.operation_id=o.id JOIN devices d ON d.id=o.device_id WHERE o.result_status='ACCEPTED' AND d.revoked_at_utc IS NOT NULL AND o.accepted_at_utc>d.revoked_at_utc",
 'Primary devices belong to their representative'=>"SELECT s.rep_id FROM rep_operating_state s JOIN devices d ON d.id=s.primary_device_id WHERE s.rep_id<>d.user_id",
 'Rejected sales retain explicit cash/stock reconciliation review'=>"SELECT o.id FROM sync_operations o JOIN offline_sale_payloads p ON p.operation_id=o.id WHERE o.result_status='REJECTED' AND NOT EXISTS(SELECT 1 FROM offline_sale_reviews r WHERE r.operation_id=o.id AND r.action='REJECT' AND LENGTH(TRIM(r.cash_reconciliation_note))>0)",
 'Offline financial posting is cash only'=>"SELECT i.id FROM invoices i WHERE i.posting_channel='SYNC' AND i.payment_type<>'CASH'"
];
foreach($checks as $label=>$sql){if($pdo->query($sql)->fetch())throw new RuntimeException($label.' FAILED');echo $label." passed.\n";}
echo 'Registered offline devices: '.$pdo->query('SELECT COUNT(*) FROM offline_device_controls')->fetchColumn().'; offline submissions: '.$pdo->query('SELECT COUNT(*) FROM offline_sale_payloads')->fetchColumn().".\n";
