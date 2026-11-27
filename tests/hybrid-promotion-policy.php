<?php
declare(strict_types=1);

// Standalone pure tests: intentionally do not bootstrap .env, sessions or PDO.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if ($class === 'App\\Support\\Database') { throw new RuntimeException('Database access forbidden in Stage 1 tests.'); }
    if (strpos($class, 'App\\') === 0) {
        $file = $root.'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (is_file($file)) { require $file; }
    }
});

use App\Services\{HybridPromotionPolicy as Hybrid, PromotionCalculator as Legacy, BenefitPolicy, InvoiceDiscount};
use App\Support\HybridCalculationException;

$checks = 0; $cases = 0;
function same(mixed $actual, mixed $expected, string $message = ''): void
{
    global $checks; $checks++;
    if ($actual !== $expected) { throw new RuntimeException($message.' expected '.json_encode($expected).' got '.json_encode($actual)); }
}
function yes(bool $value, string $message): void { same($value, true, $message); }
function runCase(string $name, callable $case): void
{
    global $cases;
    try { $case(); $cases++; echo 'PASS '.$name.PHP_EOL; }
    catch (Throwable $e) { fwrite(STDERR, 'FAIL '.$name.': '.$e->getMessage().PHP_EOL); exit(1); }
}
function product(string $id = '101', string $price = '100.00', ?string $buy = '6', string $stock = '1000', string $gift = '1'): array
{
    return ['product_id' => $id, 'price_version' => '71', 'unit_price' => $price, 'available_quantity' => $stock,
        'promotion' => $buy === null ? null : ['id' => '501', 'revision_id' => '901', 'product_id' => $id,
            'type' => 'BUY_X_GET_Y', 'buy_quantity' => $buy, 'free_quantity' => $gift]];
}
function item(string $id = '101', string $paid = '8', ?string $rate = null, bool $replace = false): array
{
    $line = ['product_id' => $id, 'paid_quantity' => $paid];
    if ($rate !== null) { $line['product_discount'] = ['choice' => 'other', 'percent' => $rate]; }
    if ($replace) { $line['replace_free_entitlement'] = true; }
    return $line;
}
function request(array $lines, string $invoiceRate = '0'): array
{
    return ['policy_version' => Hybrid::POLICY, 'channel' => 'ONLINE', 'lines' => $lines, 'invoice_discount_percent' => $invoiceRate];
}
function calculate(array $request, array $products): array
{
    $before = serialize([$request, $products]);
    try { return Hybrid::calculate($request, $products); }
    finally { same(serialize([$request, $products]), $before, 'Inputs remain unchanged even on rejection'); }
}
function rejected(array $request, array $products, string $code): void
{
    try { calculate($request, $products); }
    catch (HybridCalculationException $e) {
        same($e->findings[0]['code'], $code);
        yes(is_string($e->findings[0]['field']) && $e->findings[0]['field'] !== '', 'Structured finding has field');
        yes($e->getMessage() !== '', 'Structured finding has explanation');
        return;
    }
    throw new RuntimeException('Expected rejection '.$code);
}
function cents(string $money): int { return (int)str_replace('.', '', $money); }
function money(int $cents): string { return intdiv($cents, 100).'.'.str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT); }
function roundedPercent(int $base, int $basisPoints): int { return intdiv($base * $basisPoints + 5000, 10000); }
function assertReconciles(array $r): void
{
    $gross = $product = $replacement = $invoice = $net = 0;
    foreach ($r['lines'] as $line) {
        $gross += cents($line['gross_amount']); $product += cents($line['product_discount_amount']);
        $replacement += cents($line['replacement_discount_amount']); $invoice += cents($line['invoice_discount_amount']); $net += cents($line['payable_amount']);
        same((int)$line['required_stock'], (int)$line['paid_quantity'] + (int)$line['actual_free_quantity']);
        same((int)$line['paid_quantity'], (int)$line['qualifying_paid_quantity'] + (int)$line['discount_eligible_quantity']);
        $segmentGross = $segmentProduct = $segmentReplacement = $segmentInvoice = $segmentNet = 0;
        foreach ($line['segments'] as $segment) {
            $segmentGross += cents($segment['gross_amount']); $segmentProduct += cents($segment['product_discount_amount']);
            $segmentReplacement += cents($segment['replacement_discount_amount']); $segmentInvoice += cents($segment['invoice_discount_amount']); $segmentNet += cents($segment['payable_amount']);
            same(cents($segment['gross_amount']) - cents($segment['product_discount_amount']) - cents($segment['replacement_discount_amount']) - cents($segment['invoice_discount_amount']), cents($segment['payable_amount']));
            yes(cents($segment['payable_amount']) >= 0, 'Segment net is nonnegative');
            if ($segment['kind'] === 'PROMOTION_QUALIFIED') { same($segment['product_discount_amount'], '0.00'); }
        }
        same($segmentGross, cents($line['gross_amount'])); same($segmentProduct, cents($line['product_discount_amount']));
        same($segmentReplacement, cents($line['replacement_discount_amount'])); same($segmentInvoice, cents($line['invoice_discount_amount'])); same($segmentNet, cents($line['payable_amount']));
    }
    same($gross, cents($r['original_paid_subtotal'])); same($product, cents($r['product_discount_total']));
    same($replacement, cents($r['replacement_discount_total'])); same($invoice, cents($r['invoice_discount_amount']));
    same($product + $replacement + $invoice, cents($r['combined_monetary_discount'])); same($net, cents($r['final_payable_total']));
    same($gross - $product - $replacement - $invoice, $net); yes(2 * ($product + $replacement + $invoice) <= $gross, 'Strict monetary cap holds');
}

runCase('corrected rules repeat; no names are consulted', function (): void {
    foreach ([['101','6',['5'=>'0','6'=>'1','8'=>'1','12'=>'2','18'=>'3']], ['102','12',['11'=>'0','12'=>'1','18'=>'1','24'=>'2','36'=>'3']]] as [$id,$buy,$examples]) {
        foreach ($examples as $paid => $free) {
            $r = calculate(request([item($id,(string)$paid)]), [product($id,'100.00',$buy)]);
            same($r['lines'][0]['actual_free_quantity'], $free); same($r['lines'][0]['qualifying_paid_quantity'], (string)(intdiv((int)$paid,(int)$buy)*(int)$buy));
            same($r['lines'][0]['promotion_revision_id'], '901'); same($r['product_discount_total'], '0.00'); assertReconciles($r);
        }
    }
    // Generic versioned configuration, not a hidden product-name/ID mapping.
    same(calculate(request([item('303','15')]), [product('303','1.00','7','100','2')])['lines'][0]['actual_free_quantity'], '4');
});
runCase('zero is the initial product/invoice discount; explicit fixed dropdown choices', function (): void {
    $r = calculate(request([item()]), [product()]);
    same($r['lines'][0]['product_discount_choice'],'0'); same($r['lines'][0]['product_discount_percent'],'0.00'); same($r['product_discount_total'],'0.00');
    foreach (['0'=>'0.00','25'=>'50.00','30'=>'60.00','35'=>'70.00'] as $choice=>$expected) {
        $line=item();$line['product_discount']=['choice'=>(string)$choice];
        $r=calculate(request([$line]),[product()]);same($r['product_discount_total'],$expected);assertReconciles($r);
    }
});
runCase('below-threshold, remainder and nonpromotional quantities receive independent discounts', function (): void {
    $r=calculate(request([item('101','8','25'),item('102','18','35'),item('103','3','30')]),[product(),product('102','100.00','12'),product('103','50.00',null)]);
    same(array_column($r['lines'],'product_discount_amount'),['50.00','210.00','45.00']);same(array_column($r['lines'],'discount_eligible_quantity'),['2','6','3']);assertReconciles($r);
    $r=calculate(request([item('101','5','50')]),[product()]);same($r['product_discount_total'],'250.00');same($r['lines'][0]['actual_free_quantity'],'0');assertReconciles($r);
    $r=calculate(request([item('101','6','50')]),[product()]);same($r['product_discount_total'],'0.00');same($r['lines'][0]['actual_free_quantity'],'1');
});
runCase('Other accepts exact zero, maximum and two decimal rates; invalid rates never clamp', function (): void {
    foreach (['0'=>'0.00','50'=>'100.00','12.50'=>'25.00'] as $rate=>$discount) {
        same(calculate(request([item('101','8',(string)$rate)]),[product()])['product_discount_total'],$discount);
    }
    foreach (['','-1','50.01','100','12.345','1e1','NaN','INF',' 25','25%','+25','025',null,25,25.0,[],true] as $rate) {
        $line=item();$line['product_discount']=['choice'=>'other','percent'=>$rate];rejected(request([$line]),[product()],'INVALID_RATE');
        $req=request([item()]);$req['invoice_discount_percent']=$rate;rejected($req,[product()],'INVALID_RATE');
    }
    foreach ([null,'25',['choice'=>'50'],['choice'=>'25','percent'=>'25'],['choice'=>'other']] as $choice) {
        $line=item();$line['product_discount']=$choice;rejected(request([$line]),[product()],is_array($choice)&&($choice['choice']??'')==='other'?'INVALID_RATE':'INVALID_PRODUCT_DISCOUNT');
    }
});
runCase('product rounding is retained while promoted lines receive no invoice discount', function (): void {
    $r=calculate(request([item('101','1','25')],'10'),[product('101','251.00',null)]);
    same($r['product_discount_total'],'62.75');same($r['invoice_discount_base'],'0.00');same($r['invoice_discount_amount'],'0.00');same($r['final_payable_total'],'188.25');assertReconciles($r);
    $r=calculate(request([item('101','8','25')],'10'),[product()]);same($r['final_payable_total'],'750.00');same(array_column($r['lines'][0]['segments'],'invoice_discount_amount'),['0.00','0.00']);assertReconciles($r);
    $r=calculate(request([item('101','1','25')]),[product('101','0.02',null)]);same($r['product_discount_total'],'0.01');assertReconciles($r);
});
runCase('strict cap permits exactly/below and rejects one cent above; odd cents do not round ceiling up', function (): void {
    foreach (['49.99'=>'49.99','50'=>'50.00'] as $rate=>$discount) {
        $r=calculate(request([item('101','1',(string)$rate)]),[product('101','100.00',null)]);same($r['combined_monetary_discount'],$discount);assertReconciles($r);
    }
    $r=calculate(request([item('101','1','50'),item('102','1')]),[product('101','100.00',null),product('102','0.01',null)]);same($r['original_paid_subtotal'],'100.01');same($r['combined_monetary_discount'],'50.00');same($r['monetary_cap_limit'],'50.00');assertReconciles($r);
    rejected(request([item('101','1','50')]),[product('101','100.01',null)],'COMBINED_DISCOUNT_CAP_EXCEEDED');
    rejected(request([item('101','1')],'50'),[product('101','100.01',null)],'COMBINED_DISCOUNT_CAP_EXCEEDED');
    foreach (['0.02','50'] as $rate) {
        $r=calculate(request([item('101','1','50')],$rate),[product('101','100.00',null)]);
        same($r['combined_monetary_discount'],'50.00');same($r['invoice_discount_amount'],'0.00');assertReconciles($r);
    }
});
runCase('actual free-unit value is excluded from the monetary cap', function (): void {
    $r=calculate(request([item('101','6')],'50'),[product()]);same($r['combined_monetary_discount'],'0.00');same($r['lines'][0]['actual_free_quantity'],'1');assertReconciles($r);
});
runCase('replacement retains its full-line discount and is excluded from invoice discount', function (): void {
    $req=request([item('102','12',null,true)],'10');$req['replacement_discount']=['choice'=>'25'];
    $r=calculate($req,[product('102','100.00','12','12')]);
    same($r['replacement_discount_total'],'300.00');same($r['invoice_discount_amount'],'0.00');same($r['final_payable_total'],'900.00');
    same($r['lines'][0]['actual_free_quantity'],'0');same($r['lines'][0]['substituted_free_quantity'],'1');same($r['lines'][0]['required_stock'],'12');
    same($r['replacement_policy'],BenefitPolicy::POLICY);same($r['replacement_mode'],BenefitPolicy::MODE_PROMO_DISCOUNT);same($r['replacement_discount_percent'],'25.00');
    same($r['validation_findings'][0]['code'],'FULL_ENTITLEMENT_REPLACED');assertReconciles($r);
});
runCase('replacement aggregate rounding and input-order ties match unchanged approved policy', function (): void {
    $products=[product('101','0.01','6','6'),product('102','0.01','12','12')];
    $req=request([item('101','6',null,true),item('102','12',null,true)]);$req['replacement_discount']=['choice'=>'25'];
    $r=calculate($req,$products);same($r['replacement_discount_total'],'0.05');same(array_column($r['lines'],'replacement_discount_amount'),['0.02','0.03']);assertReconciles($r);
    $req=request([item('102','6',null,true),item('101','6',null,true)]);$req['replacement_discount']=['choice'=>'25'];
    $r=calculate($req,[product('101','0.01','6','6'),product('102','0.01','6','6')]);
    same(array_column($r['lines'],'product_id'),['102','101']);same(array_column($r['lines'],'replacement_discount_amount'),['0.02','0.01']);assertReconciles($r);
});
runCase('replacement preserves unrelated free lines and separate manual discounts', function (): void {
    $req=request([item('101','8','25'),item('102','12',null,true),item('103','1','30')],'10');$req['replacement_discount']=['choice'=>'25'];
    $r=calculate($req,[product(),product('102','100.00','12','12'),product('103','100.00',null)]);
    same(array_column($r['lines'],'actual_free_quantity'),['1','0','0']);same($r['product_discount_total'],'80.00');same($r['replacement_discount_total'],'300.00');
    same($r['invoice_discount_amount'],'0.00');same($r['final_payable_total'],'1720.00');assertReconciles($r);
});
runCase('replacement selection has no default and validates existing options independently', function (): void {
    $base=request([item('101','6',null,true)]);$products=[product('101','100.00','6','6')];
    rejected($base,$products,'REPLACEMENT_RATE_REQUIRED');
    foreach ([['choice'=>'0'],['choice'=>'other','percent'=>'25'],['choice'=>'25','percent'=>'25'],['choice'=>'custom','percent'=>'100.01'],['choice'=>'custom','percent'=>null],['choice'=>'custom','percent'=>'1e1']] as $selection) {
        $req=$base;$req['replacement_discount']=$selection;rejected($req,$products,'INVALID_REPLACEMENT_RATE');
    }
    $req=$base;$req['replacement_discount']=['choice'=>'custom','percent'=>'0'];
    same(calculate($req,$products)['replacement_discount_total'],'0.00'); // existing custom-zero semantics, no new preset
    $req=$base;$req['replacement_discount']=['choice'=>'35'];same(calculate($req,$products)['replacement_discount_total'],'210.00');
    $req=$base;$req['replacement_discount']=['choice'=>'30'];same(calculate($req,$products)['replacement_discount_total'],'180.00');
});
runCase('replacement money counts in cap, including retained legacy custom rate semantics', function (): void {
    $req=request([item('101','6',null,true)],'0.02');$req['replacement_discount']=['choice'=>'custom','percent'=>'50'];
    same(calculate($req,[product('101','100.00','6','6')])['combined_monetary_discount'],'300.00');
    $req['invoice_discount_percent']='0';same(calculate($req,[product('101','100.00','6','6')])['combined_monetary_discount'],'300.00');
    $req['replacement_discount']=['choice'=>'custom','percent'=>'75'];rejected($req,[product('101','100.00','6','6')],'COMBINED_DISCOUNT_CAP_EXCEEDED');
    // No invented replacement-rate cap: the original legacy custom rate remains unchanged,
    // but every replacement cent participates in the strict aggregate Hybrid cap.
    $req=request([item('101','6',null,true),item('102','6')]);$req['replacement_discount']=['choice'=>'custom','percent'=>'75'];
    $r=calculate($req,[product('101','100.00','6','6'),product('102','100.00',null)]);same($r['replacement_discount_total'],'450.00');assertReconciles($r);
});
runCase('all unsupported stock and replacement cases fail closed', function (): void {
    rejected(request([item('101','6')]),[product('101','100.00','6','5')],'INSUFFICIENT_PAID_STOCK');
    rejected(request([item('101','6')]),[product('101','100.00','6','6')],'FREE_STOCK_SHORTAGE');
    rejected(request([item('101','8','25',true)]),[product('101','100.00','6','8')],'REPLACEMENT_REMAINDER_UNSUPPORTED');
    rejected(request([item('101','8',null,true)]),[product('101','100.00','6','8')],'REPLACEMENT_REMAINDER_UNSUPPORTED');
    rejected(request([item('101','6','25',true)]),[product('101','100.00','6','6')],'REPLACEMENT_PRODUCT_OVERLAP');
    rejected(request([item('101','12',null,true)]),[product('101','100.00','6','13')],'PARTIAL_ENTITLEMENT_SHORTAGE');
    rejected(request([item('101','6',null,true)]),[product()],'REPLACEMENT_NOT_REQUIRED');
    rejected(request([item('101','5',null,true)]),[product()],'REPLACEMENT_NOT_REQUIRED');
    $req=request([item()]);$req['replacement_discount']=['choice'=>'25'];rejected($req,[product()],'UNUSED_REPLACEMENT_SELECTION');
    foreach ([null,1,'1','true'] as $value) { $line=item();$line['replace_free_entitlement']=$value;rejected(request([$line]),[product()],'INVALID_REPLACEMENT_SELECTION'); }
});
runCase('automatic percentage/fixed conflicts and all pooled mixed-oil markers are rejected', function (): void {
    foreach (['PERCENTAGE','FIXED'] as $type) {
        $p=product();$p['promotion']['type']=$type;rejected(request([item()]),[$p],'AUTOMATIC_MONETARY_PROMOTION_CONFLICT');
    }
    foreach (['mixed_product_ids'=>['101','102'],'mixed_policy'=>'MIXED_OIL_V1'] as $key=>$value) {
        $p=product();$p['promotion'][$key]=$value;rejected(request([item()]),[$p],'MIXED_OIL_EXCLUDED');
    }
});
runCase('authoritative identities, quantities and strict request fields reject ambiguous claims', function (): void {
    $base=request([item()]);$req=$base;$req['policy_version']='PROMO_CHOICE_V1';rejected($req,[product()],'UNSUPPORTED_POLICY');
    foreach ([null,'OFFLINE','SYNC'] as $channel) { $req=$base;$req['channel']=$channel;rejected($req,[product()],'ONLINE_ONLY'); }
    foreach (['grand_total','stock','historical_return','benefit_mode'] as $key) { $req=$base;$req[$key]='0';rejected($req,[product()],'UNSUPPORTED_FIELD'); }
    $line=item();$line['free_quantity']='99';rejected(request([$line]),[product()],'UNSUPPORTED_FIELD');
    rejected(request([item(),item()]),[product()],'DUPLICATE_PRODUCT');rejected($base,[],'UNKNOWN_PRODUCT');rejected($base,[product(),product()],'DUPLICATE_CONFIGURATION');
    foreach (['0','-1','1.5','01',null,8,'9223372036854775808'] as $quantity) { $line=item();$line['paid_quantity']=$quantity;rejected(request([$line]),[product()],'INVALID_QUANTITY'); }
    $p=product();$p['promotion']['product_id']='999';rejected($base,[$p],'PROMOTION_PRODUCT_MISMATCH');
    $p=product();unset($p['promotion']['revision_id']);rejected($base,[$p],'INVALID_ID');
    $p=product();unset($p['promotion']);rejected($base,[$p],'INVALID_CONFIGURATION');
    $p=product();$p['promotion']=[product()['promotion']];rejected($base,[$p],'UNSUPPORTED_FIELD');
    $p=product();$p['promotion']['type']='UNRECOGNIZED';rejected($base,[$p],'UNSUPPORTED_PROMOTION');
    foreach (['-1','1e2',null,100.00] as $price) { $p=product();$p['unit_price']=$price;rejected($base,[$p],'INVALID_MONEY'); }
    $req=$base;$req['lines']=[];rejected($req,[product()],'INVALID_LINES');
    $req=$base;$req['lines']=array_fill(0,101,item());rejected($req,[product()],'INVALID_LINES');
    $line=item();$line['product_id']='Glucose';rejected(request([$line]),[product()],'INVALID_ID');
});
runCase('zero prices, huge exact amounts and quantity overflow are bounded', function (): void {
    $r=calculate(request([item('101','8','50')],'50'),[product('101','0.00')]);same($r['final_payable_total'],'0.00');assertReconciles($r);
    $r=calculate(request([item('101','1','12.5')],'12.5'),[product('101','9999999999999.99',null)]);
    same($r['product_discount_total'],'1250000000000.00');same($r['invoice_discount_amount'],'0.00');same($r['final_payable_total'],'8749999999999.99');
    rejected(request([item('101','2')]),[product('101','9999999999999.99',null)],'QUANTITY_OR_AMOUNT_LIMIT');
    rejected(request([item('101','1'),item('102','1')]),[product('101','9999999999999.99',null),product('102','0.01',null)],'AMOUNT_LIMIT');
    rejected(request([item('101',(string)PHP_INT_MAX)]),[product('101','0.00','1',(string)PHP_INT_MAX)],'QUANTITY_OR_AMOUNT_LIMIT');
});
runCase('approved Glucose and unpromoted Replacement example totals LKR 1200', function (): void {
    $r=calculate(request([item('101','8','25'),item('102','5')],'10'),[product(),product('102','100.00',null)]);
    same(array_column($r['lines'],'product_id'),['101','102']);same(array_column($r['lines'],'line_number'),[1,2]);
    same(array_column($r['lines'],'invoice_discount_amount'),['0.00','50.00']);
    same(array_column($r['lines'],'payable_amount'),['750.00','450.00']);
    same($r['invoice_discount_base'],'500.00');same($r['final_payable_total'],'1200.00');assertReconciles($r);
});
runCase('free-only, discount-only, both and no-promotion lines have independent eligibility', function (): void {
    $r=calculate(request([item('101','6'),item('102','2','25'),item('103','8','25'),item('104','5')],'10'),
        [product(),product('102','100.00',null),product('103'),product('104','100.00',null)]);
    same(array_column($r['lines'],'invoice_discount_amount'),['0.00','0.00','0.00','50.00']);
    same(array_column($r['lines'],'payable_amount'),['600.00','150.00','750.00','450.00']);assertReconciles($r);
});
runCase('zero selections permit invoice discount only without free entitlement', function (): void {
    foreach ([['choice'=>'0'],['choice'=>'other','percent'=>'0.00']] as $selection) {
        $lines=[item('101','6'),item('102','5'),item('103','5')];
        foreach ($lines as &$line) { $line['product_discount']=$selection; } unset($line);
        $r=calculate(request($lines,'10'),[product(),product('102'),product('103','100.00',null)]);
        same(array_column($r['lines'],'invoice_discount_amount'),['0.00','50.00','50.00']);assertReconciles($r);
    }
    $req=request([item('101','6',null,true),item('102','5')],'10');$req['replacement_discount']=['choice'=>'custom','percent'=>'0'];
    $r=calculate($req,[product('101','100.00','6','6'),product('102','100.00',null)]);
    same(array_column($r['lines'],'invoice_discount_amount'),['0.00','50.00']);assertReconciles($r);
});
runCase('sparse eligible indexes preserve half-up rounding, remainder priority and original-order ties', function (): void {
    $req=request([item('101','6'),item('104','1'),item('102','1','25'),item('103','1')],'50');
    $products=[product(),product('102','1.00',null),product('103','0.01',null),product('104','0.01',null)];
    $r=calculate($req,$products);same(array_column($r['lines'],'line_number'),[1,2,3,4]);
    same(array_column($r['lines'],'invoice_discount_amount'),['0.00','0.01','0.00','0.00']);
    same($r['invoice_discount_base'],'0.02');assertReconciles($r);same(calculate($req,array_reverse($products)),$r);
    $products[2]['unit_price']='0.02';
    $r=calculate($req,$products);same($r['invoice_discount_amount'],'0.02');
    same(array_column($r['lines'],'invoice_discount_amount'),['0.00','0.01','0.00','0.01']);assertReconciles($r);
});
runCase('mixed replacement and invoice amounts still enforce strict combined 50 percent cap', function (): void {
    $req=request([item('101','6',null,true),item('102','6')],'25');$req['replacement_discount']=['choice'=>'custom','percent'=>'75'];
    $products=[product('101','100.00','6','6'),product('102','100.00',null)];
    $r=calculate($req,$products);same($r['combined_monetary_discount'],'600.00');assertReconciles($r);
    $req['invoice_discount_percent']='25.01';rejected($req,$products,'COMBINED_DISCOUNT_CAP_EXCEEDED');
});
runCase('synthetic Buy 6 Get 1 and Buy 12 Get 2 exclude whole entitled lines', function (): void {
    // Generic pure-policy fixtures only; do not change approved product bindings.
    foreach ([['6','1'],['12','2']] as [$buy,$gift]) {
        foreach ([(int)$buy-1,(int)$buy,(int)$buy+2,2*(int)$buy] as $paid) {
            foreach (['0','25'] as $rate) {
                $r=calculate(request([item('101',(string)$paid,$rate),item('102','5')],'10'),
                    [product('101','100.00',$buy,'1000',$gift),product('102','100.00',null)]);
                $entitled=$paid >= (int)$buy;
                $product=roundedPercent(($paid % (int)$buy)*10000,(int)$rate*100);
                $invoice=(!$entitled && $product===0) ? $paid*1000 : 0;
                same($r['lines'][0]['free_entitlement'],(string)(intdiv($paid,(int)$buy)*(int)$gift));
                same($r['lines'][0]['actual_free_quantity'],$r['lines'][0]['free_entitlement']);
                same($r['lines'][0]['product_discount_amount'],money($product));
                same($r['lines'][0]['invoice_discount_amount'],money($invoice));
                same($r['lines'][0]['payable_amount'],money($paid*10000-$product-$invoice));
                same($r['lines'][1]['invoice_discount_amount'],'50.00');
                same($r['invoice_discount_base'],money(50000+((!$entitled && $product===0)?$paid*10000:0)));
                if ($entitled) { same(array_unique(array_column($r['lines'][0]['segments'],'invoice_discount_amount')),['0.00']); }
                assertReconciles($r);
            }
        }
    }
});
runCase('input-order allocation ties are deterministic across repeated runs', function (): void {
    $req=request([item('102','1'),item('101','1')],'50');$products=[product('101','0.01',null),product('102','0.01',null)];
    $r=calculate($req,$products);same(array_column($r['lines'],'invoice_discount_amount'),['0.01','0.00']);assertReconciles($r);
    same(calculate($req,array_reverse($products)),$r);same(calculate($req,$products),$r);
});
runCase('independent integer oracle: quantity/rate/rounding/cap matrix', function (): void {
    foreach ([6,12] as $buy) foreach ([1,5,6,8,11,12,18,24,36] as $paid) foreach ([0,1250,2500,3500,5000] as $rate) foreach ([0,1000,2500,5000] as $invoiceRate) {
        $price=137;$eligible=$paid%$buy;$s=$price*$paid;$p=roundedPercent($price*$eligible,$rate);$i=($paid >= $buy || $p > 0) ? 0 : roundedPercent($s,$invoiceRate);
        $req=request([item('101',(string)$paid,money($rate))],money($invoiceRate));$products=[product('101','1.37',(string)$buy)];
        if (2*($p+$i)>$s) { rejected($req,$products,'COMBINED_DISCOUNT_CAP_EXCEEDED');continue; }
        $r=calculate($req,$products);same($r['product_discount_total'],money($p));same($r['invoice_discount_amount'],money($i));
        same($r['final_payable_total'],money($s-$p-$i));same($r['lines'][0]['actual_free_quantity'],(string)intdiv($paid,$buy));assertReconciles($r);
    }
});
runCase('independent multi-line oracle: replacement cohorts and sequential amounts reconcile', function (): void {
    for ($seed=0;$seed<120;$seed++) {
        $paidA=6*(1+$seed%3);$paidB=12*(1+$seed%2);$paidC=1+$seed%5;
        $priceA=1+($seed*37)%999;$priceB=1+($seed*53)%999;$priceC=1+($seed*29)%999;
        $replaceA=$seed%2===0;$replaceB=$seed%3===0;$productRate=($seed%4)*1250;$invoiceRate=($seed%4)*1000;$replacementRate=2500+($seed%3)*500;
        $s=$paidA*$priceA+$paidB*$priceB+$paidC*$priceC;
        $p=roundedPercent($paidC*$priceC,$productRate);
        $replacementBase=($replaceA?$paidA*$priceA:0)+($replaceB?$paidB*$priceB:0);
        $r=roundedPercent($replacementBase,$replacementRate);$i=$p > 0 ? 0 : roundedPercent($paidC*$priceC,$invoiceRate);
        $lines=[item('101',(string)$paidA,null,$replaceA),item('102',(string)$paidB,null,$replaceB),item('103',(string)$paidC,money($productRate))];
        if($seed%2===1)$lines=array_reverse($lines);
        $req=request($lines,money($invoiceRate));if($replaceA||$replaceB)$req['replacement_discount']=['choice'=>'custom','percent'=>money($replacementRate)];
        $products=[product('101',money($priceA),'6',$replaceA?(string)$paidA:'1000'),product('102',money($priceB),'12',$replaceB?(string)$paidB:'1000'),product('103',money($priceC),null)];
        if(2*($p+$r+$i)>$s){rejected($req,$products,'COMBINED_DISCOUNT_CAP_EXCEEDED');continue;}
        $result=calculate($req,$products);
        same($result['original_paid_subtotal'],money($s));same($result['product_discount_total'],money($p));same($result['replacement_discount_total'],money($r));
        same($result['invoice_discount_amount'],money($i));same($result['final_payable_total'],money($s-$p-$r-$i));assertReconciles($result);
        same(calculate($req,array_reverse($products)),$result,'Authoritative catalogue ordering must not change invoice allocation');
    }
});
runCase('legacy calculators and results remain unchanged before and after Hybrid calls', function () use ($root): void {
    $files=['app/Services/BenefitPolicy.php','app/Services/InvoiceDiscount.php','app/Services/PromotionCalculator.php','config/benefit-modes.php'];
    $hashes=[];foreach($files as $file){$hashes[$file]=hash_file('sha256',$root.'/'.$file);}
    $rule=product()['promotion'];$quote=Legacy::calculate('100.00','8',$rule)+['product_id'=>'101','promotion_id'=>'501','promotion_version_id'=>'901','rule_snapshot'=>$rule];
    $quotes=[$quote];$legacyFree=BenefitPolicy::apply($quotes,BenefitPolicy::MODE_FREE,'0.00');
    $legacyReplacement=BenefitPolicy::apply($quotes,BenefitPolicy::MODE_PROMO_DISCOUNT,'25.00');
    $legacyInvoice=InvoiceDiscount::apply($quotes,'25');$saved=serialize([$quote,$quotes,$legacyFree,$legacyReplacement,$legacyInvoice]);
    same($legacyReplacement['benefit_discount_amount'],'200.00'); // original full 8-paid line, NOT only the 6 qualifying units
    same($legacyFree['lines'][0]['free_quantity'],'1');same($legacyInvoice['invoice_discount_amount'],'200.00');
    calculate(request([item('101','8','25')],'10'),[product()]);
    same(serialize([$quote,$quotes,$legacyFree,$legacyReplacement,$legacyInvoice]),$saved);
    same(BenefitPolicy::apply($quotes,BenefitPolicy::MODE_FREE,'0.00'),$legacyFree);
    same(BenefitPolicy::apply($quotes,BenefitPolicy::MODE_PROMO_DISCOUNT,'25.00'),$legacyReplacement);
    same(InvoiceDiscount::apply($quotes,'25'),$legacyInvoice);same(Legacy::calculate('100.00','8',$rule)+['product_id'=>'101','promotion_id'=>'501','promotion_version_id'=>'901','rule_snapshot'=>$rule],$quote);
    foreach($files as $file){same(hash_file('sha256',$root.'/'.$file),$hashes[$file]);}
});

echo "Stage 1: $cases cases, $checks assertions passed; pure synthetic unit tests only; no database bootstrap or posting.".PHP_EOL;
