<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;

/** Server-side return valuation. Call under the invoice posting gate. */
final class HistoricalCustomerReturns
{
    public function __construct(private PDO $pdo) {}
    public static function normalize(mixed $data): array
    {
        if(!is_array($data)||($data['received']??null)!=='1'||($data['unsaleable']??null)!=='1'||($data['eligible']??null)!=='1')throw new InventoryException(['return'=>'Confirm receipt, eligibility and unsaleable condition.']);
        $identity=$data['return_intent_id']??null;if(!is_string($identity)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$identity))throw new InventoryException(['return'=>'Start the return again to obtain a valid return identity.']);
        $reason=Inventory::text($data['reason']??null,'return_reason',500,true);$lines=$data['lines']??null;
        if(!is_array($lines)||count($lines)<1||count($lines)>100)throw new InventoryException(['return'=>'Add between 1 and 100 returned product lines.']);
        $result=[];
        foreach($lines as $line){if(!is_array($line))throw new InventoryException(['return'=>'Invalid returned product line.']);$product=Representatives::id($line['product_id']??null,'product_id');$year=$line['year']??null;if(!is_string($year)||!in_array($year,['2024','2025'],true))throw new InventoryException(['return'=>'Choose a supported historical year.']);$quantity=(string)Inventory::quantity($line['quantity']??null);$key=$product.':'.$year;if(isset($result[$key]))throw new InventoryException(['return'=>'Combine duplicate product and year lines.']);$result[$key]=['product_id'=>$product,'year'=>$year,'quantity'=>$quantity];}
        ksort($result,SORT_STRING);return ['return_intent_id'=>$identity,'reason'=>$reason,'lines'=>array_values($result)];
    }
    public function value(array $return): array
    {
        $total='0.00';$lines=[];$q=$this->pdo->prepare("SELECT id,unit_price,status FROM historical_return_price_revisions WHERE product_id=? AND price_year=? ORDER BY revision DESC LIMIT 1 FOR UPDATE");
        foreach($return['lines'] as $line){$q->execute([$line['product_id'],$line['year']]);$price=$q->fetch(PDO::FETCH_ASSOC);if(!$price||$price['status']!=='APPROVED')throw new InventoryException(['return'=>'An approved historical price is missing or disabled.'],409);$value=ProductReturnCalculation::historicalLine($price['unit_price'],$line['quantity']);$total=PromotionCalculator::sumMoney($total,$value);$lines[]=$line+['price_revision_id'=>(string)$price['id'],'unit_price'=>$price['unit_price'],'line_value'=>$value];}
        return ['reason'=>$return['reason'],'lines'=>$lines,'approved_value'=>$total];
    }
    public function post(string $invoice,string $customer,string $actor,array $valuation,array $settlement): void
    {
        if(!$this->pdo->inTransaction())throw new \LogicException('Return posting requires the invoice transaction.');
        (new Invoices($this->pdo))->requireHistoricalReturnAccess(['id'=>$actor]);
        $q=$this->pdo->prepare('INSERT INTO historical_customer_returns(invoice_id,customer_id,actor_user_id,reason,approved_value,applied_value,forfeited_value) VALUES (?,?,?,?,?,?,?)');$q->execute([$invoice,$customer,$actor,$valuation['reason'],$settlement['approved_value'],$settlement['applied_value'],$settlement['forfeited_value']]);
        $q=$this->pdo->prepare('INSERT INTO historical_customer_return_lines(invoice_id,line_number,product_id,price_revision_id,price_year,unit_price,quantity,line_value) VALUES (?,?,?,?,?,?,?,?)');
        foreach($valuation['lines'] as $i=>$line)$q->execute([$invoice,$i+1,$line['product_id'],$line['price_revision_id'],$line['year'],$line['unit_price'],$line['quantity'],$line['line_value']]);
    }
}
