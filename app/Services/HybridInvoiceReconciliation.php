<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Services\PromotionCalculator as M;

final class HybridInvoiceReconciliation
{
    public function __construct(private PDO $pdo) {}
    public function verify(array $actor,string $invoice): array
    {
        $own=!$this->pdo->inTransaction();
        if ($own) { $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $this->pdo->beginTransaction(); }
        try { return $this->check($actor,$invoice); }
        finally { if ($own && $this->pdo->inTransaction()) { $this->pdo->rollBack(); } }
    }
    private function check(array $actor,string $invoice): array
    {
        $record=(new HybridInvoiceReader($this->pdo))->get($actor,$invoice); $r=$record['snapshot']['review'];
        $q=$this->pdo->prepare("SELECT m.product_id,m.quantity_delta,m.location_id,t.invoice_id,t.type FROM inventory_movements m JOIN inventory_transactions t ON t.id=m.transaction_id WHERE m.transaction_id=? ORDER BY m.line_number"); $q->execute([$invoice]); $movements=$q->fetchAll(PDO::FETCH_ASSOC);
        if (count($movements)!==count($r['calculation']['lines'])) { throw new \LogicException('Hybrid SALE movement count mismatch.'); }
        foreach ($r['calculation']['lines'] as $i=>$line) {
            $m=$movements[$i];
            if ((string)$m['product_id']!==$line['product_id'] || (string)$m['quantity_delta']!=='-'.$line['required_stock'] || (string)$m['location_id']!==$r['source_location_id'] || $m['invoice_id']!==$invoice || $m['type']!=='SALE') { throw new \LogicException('Hybrid SALE stock reconciliation failed.'); }
            $q=$this->pdo->prepare('SELECT COALESCE(SUM(quantity_delta),0) quantity FROM inventory_movements WHERE location_id=? AND product_id=? AND batch_id IS NULL'); $q->execute([$r['source_location_id'],$line['product_id']]); $ledger=(string)$q->fetchColumn();
            if ($r['stock_source']==='warehouse') { $q=$this->pdo->prepare('SELECT quantity FROM product_stock_balances WHERE product_id=?'); $q->execute([$line['product_id']]); }
            else { $q=$this->pdo->prepare('SELECT quantity FROM representative_stock_balances WHERE rep_id=? AND product_id=?'); $q->execute([$r['stock_source'],$line['product_id']]); }
            if ((string)$q->fetchColumn()!==$ledger) { throw new \LogicException('Hybrid current stock/ledger projection mismatch.'); }
        }
        $q=$this->pdo->prepare('SELECT received_amount,applied_amount,change_amount FROM invoice_receipts WHERE request_id=? AND invoice_id=?'); $q->execute([$invoice,$invoice]); $receipt=$q->fetch(PDO::FETCH_ASSOC);
        if (!$receipt || $receipt['received_amount']!==$r['amount_received'] || $receipt['applied_amount']!==$r['applied_amount'] || $receipt['change_amount']!==$r['change_amount']) { throw new \LogicException('Hybrid initial cash/change reconciliation failed.'); }
        $q=$this->pdo->prepare("SELECT r.applied_amount,p.amount,p.status,p.customer_id,a.amount allocation FROM invoice_receipts r LEFT JOIN payments p ON p.id=r.payment_id LEFT JOIN payment_allocations a ON a.payment_id=p.id AND a.invoice_id=r.invoice_id WHERE r.invoice_id=?"); $q->execute([$invoice]); $receiptTotal='0.00';
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $receiptTotal=M::sumMoney($receiptTotal,$row['applied_amount']);
            if ($row['applied_amount']!=='0.00' && ($row['amount']!==$row['applied_amount'] || $row['allocation']!==$row['applied_amount'] || (string)$row['customer_id']!==(string)$r['customer']['id'] || $row['status']!=='POSTED')) { throw new \LogicException('Hybrid posted receipt reconciliation failed.'); }
        }
        if ($receiptTotal!==$record['financial']['paid_amount']) { throw new \LogicException('Hybrid collections include an allocation without an authoritative receipt.'); }
        $returns=(new HybridReturnReconciliation($this->pdo))->verify($actor,$invoice);
        return ['invoice_id'=>$invoice,'policy_version'=>HybridPromotionPolicy::POLICY,'sealed'=>true,'stock'=>true,'money'=>true,'financial'=>$record['financial'],'returns'=>$returns];
    }
}
