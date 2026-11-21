<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;
/**
 * PROMO_CHOICE_V1: server-authoritative promotion benefit modes.
 *
 *  - FREE_PROMOTION: keep promotional free units and monetary promotion discounts; no invoice-level percent.
 *  - PROMOTION_DISCOUNT_INSTEAD: suppress all promotional benefits; percent on qualifying BUY_X_GET_Y lines only.
 *  - ORDINARY_INVOICE_DISCOUNT: suppress all promotional benefits; percent on the whole invoice.
 *
 * All arithmetic is exact integer-cents via PromotionCalculator; rounding is half-up and
 * allocation uses the largest-remainder method with ties broken by the original line order,
 * exactly as INVOICE_PERCENT_V1 does. Snapshots never reuse INVOICE_PERCENT_V1.
 * Authority: docs/promotion-benefit-design-conflict.md.
 */
final class BenefitPolicy
{
    public const POLICY='PROMO_CHOICE_V1';
    public const MODE_FREE='FREE_PROMOTION';
    public const MODE_PROMO_DISCOUNT='PROMOTION_DISCOUNT_INSTEAD';
    public const MODE_ORDINARY='ORDINARY_INVOICE_DISCOUNT';
    public const MODES=[self::MODE_FREE,self::MODE_PROMO_DISCOUNT,self::MODE_ORDINARY];
    public const CHOICES=['0','25','30','35','custom'];
    /** Client-supplied field names that belong to the benefit checkout interface. */
    public const FIELDS=['benefit_mode','benefit_policy','promotion_discount_percent','promotion_discount_choice'];

    /** Server-side feature gate: disabled by default; isolated-test mode can never reach another database. */
    public static function enabled(PDO $pdo):bool
    {
        static $cache=null;if($cache!==null)return $cache;
        $config=require APP_ROOT.'/config/benefit-modes.php';
        $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $allowed=false;
        if(($config['enabled']??false)===true)$allowed=in_array($database,$config['databases']??[],true);
        if(!$allowed&&getenv('DHARMA_BENEFIT_TEST')==='1')$allowed=$database===(string)($config['isolated_test_database']??'dharma_sales_phase9_test');
        if($allowed){$q=$pdo->prepare('SELECT status FROM schema_migrations WHERE version=?');$q->execute(['024_promotion_benefit_modes.sql']);$allowed=$q->fetchColumn()==='applied';}
        return $cache=$allowed;
    }

    /** Validate the client benefit selection. Only the mode and the rate choice are accepted; all amounts are server-computed. */
    public static function validate(array $input,bool $oilSelected):array
    {
        if(($input['benefit_policy']??null)!==self::POLICY)throw new InventoryException(['benefit_policy'=>'Unsupported benefit policy. Refresh the application.'],409);
        $mode=$input['benefit_mode']??null;if(!is_string($mode)||!in_array($mode,self::MODES,true))throw new InventoryException(['benefit_mode'=>'Choose a listed benefit mode.']);
        $rate='0.00';$choice=null;
        if($mode!==self::MODE_FREE){
            $choice=$input['promotion_discount_choice']??'custom';if(!is_string($choice)||!in_array($choice,self::CHOICES,true))throw new InventoryException(['promotion_discount_percent'=>'Choose a listed promotion discount.']);
            $rate=InvoiceDiscount::rate($choice==='custom'?($input['promotion_discount_percent']??null):$choice);
            if($oilSelected)throw new InventoryException(['oil_rewards'=>'Mixed-oil rewards are not available with an invoice discount benefit mode.'],409);
        }
        return ['benefit_policy'=>self::POLICY,'benefit_mode'=>$mode,'benefit_rate'=>$rate,'benefit_discount_choice'=>$choice];
    }

    /**
     * Apply the chosen benefit mode to reviewed lines (which already carry the
     * authoritative promotional pricing). Lines keep their original input order so
     * largest-remainder ties break exactly as the seller reviewed them.
     */
    public static function apply(array $lines,string $mode,string $rate):array
    {
        if($mode===self::MODE_FREE){
            foreach($lines as &$line){$line['benefit_eligible']=false;$line['benefit_eligibility_reason']='FREE_PROMOTION_MODE';$line['benefit_eligible_subtotal']='0.00';$line['suppressed_free_quantity']='0';$line['suppressed_monetary_discount']='0.00';}unset($line);
            return ['lines'=>$lines,'benefit_policy'=>self::POLICY,'benefit_mode'=>$mode,'benefit_rate'=>'0.00','benefit_eligible_subtotal'=>'0.00','benefit_discount_amount'=>'0.00','benefit_suppressed_free_quantity'=>'0','benefit_suppressed_monetary_discount'=>'0.00','benefit_promotion_revisions'=>self::revisions($lines)];
        }
        $rate=InvoiceDiscount::rate($rate);
        // Suppress every promotional benefit before computing the invoice discount.
        $suppressedFree='0';$suppressedMonetary='0.00';
        foreach($lines as &$line){
            $suppressedFree=PromotionCalculator::add($suppressedFree,(string)(int)$line['free_quantity']);
            $suppressedMonetary=PromotionCalculator::sumMoney($suppressedMonetary,$line['discount_amount']);
            $line['suppressed_free_quantity']=(string)(int)$line['free_quantity'];
            $line['suppressed_monetary_discount']=$line['discount_amount'];
            $line['free_quantity']='0';$line['required_stock']=$line['paid_quantity'];
            $line['payable_amount']=$line['gross_amount'];$line['discount_amount']='0.00';
        }unset($line);
        $eligibleSubtotal='0.00';
        foreach($lines as &$line){
            [$eligible,$reason]=self::eligibility($line,$mode);
            $line['benefit_eligible']=$eligible;$line['benefit_eligibility_reason']=$reason;
            $line['benefit_eligible_subtotal']=$eligible?$line['gross_amount']:'0.00';
            if($eligible)$eligibleSubtotal=PromotionCalculator::sumMoney($eligibleSubtotal,$line['gross_amount']);
        }unset($line);
        $base=PromotionCalculator::cents($eligibleSubtotal);
        [$discount]=InvoiceDiscount::divide(PromotionCalculator::add(PromotionCalculator::multiply($base,PromotionCalculator::cents($rate)),'5000'),'10000');
        $allocated='0';$shares=[];$remainders=[];
        foreach($lines as $index=>$line){
            if(!$line['benefit_eligible']||$base==='0'){$shares[$index]='0';continue;}
            [$share,$remainder]=InvoiceDiscount::divide(PromotionCalculator::multiply($discount,PromotionCalculator::cents($line['gross_amount'])),$base);
            $shares[$index]=$share;$remainders[]=['index'=>$index,'remainder'=>$remainder];$allocated=PromotionCalculator::add($allocated,$share);
        }
        usort($remainders,static fn($a,$b)=>-PromotionCalculator::compare($a['remainder'],$b['remainder'])?:($a['index']<=>$b['index']));
        $left=(int)PromotionCalculator::subtract($discount,$allocated);for($i=0;$i<$left;$i++){$index=$remainders[$i]['index'];$shares[$index]=PromotionCalculator::add($shares[$index],'1');}
        foreach($lines as $index=>&$line){
            $line['automatic_discount_amount']='0.00';
            $line['invoice_discount_amount']=PromotionCalculator::money($shares[$index]);
            $line['discount_amount']=$line['invoice_discount_amount'];
            $line['payable_amount']=PromotionCalculator::subtractMoney($line['gross_amount'],$line['invoice_discount_amount']);
        }unset($line);
        return ['lines'=>$lines,'benefit_policy'=>self::POLICY,'benefit_mode'=>$mode,'benefit_rate'=>$rate,'benefit_eligible_subtotal'=>$eligibleSubtotal,'benefit_discount_amount'=>PromotionCalculator::money($discount),'benefit_suppressed_free_quantity'=>$suppressedFree,'benefit_suppressed_monetary_discount'=>$suppressedMonetary,'benefit_promotion_revisions'=>self::revisions($lines)];
    }

    /** Eligibility per line. PROMOTION_DISCOUNT_INSTEAD: non-mixed BUY_X_GET_Y rules at or above the paid threshold only. */
    private static function eligibility(array $line,string $mode):array
    {
        if($mode===self::MODE_ORDINARY)return [true,'ORDINARY_MODE'];
        $rule=$line['rule_snapshot']??null;
        if($rule===null)return [false,'NO_PROMOTION'];
        if(isset($rule['mixed_product_ids']))return [false,'MIXED_OIL_EXCLUDED'];
        if(($rule['type']??null)!=='BUY_X_GET_Y')return [false,'NOT_BUY_X_GET_Y'];
        if((int)$line['paid_quantity']<(int)($rule['buy_quantity']??PHP_INT_MAX))return [false,'BELOW_THRESHOLD'];
        return [true,'QUALIFIES'];
    }

    /** Distinct promotion/revision pairs the reviewed lines were priced from. */
    private static function revisions(array $lines):array
    {
        $seen=[];foreach($lines as $line){if(($line['promotion_version_id']??null)===null)continue;$key=$line['promotion_id'].'/'.$line['promotion_version_id'];$seen[$key]=['promotion_id'=>(string)$line['promotion_id'],'revision_id'=>(string)$line['promotion_version_id'],'mode'=>(string)($line['rule_snapshot']['type']??'')];}
        return array_values($seen);
    }
}
