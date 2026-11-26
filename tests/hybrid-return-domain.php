<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
spl_autoload_register(static function($c){if($c==='App\\Support\\Database')throw new RuntimeException('Database forbidden in pure return tests.');if(strpos($c,'App\\')===0){$p=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';if(is_file($p))require $p;}});
use App\Services\{HybridPromotionPolicy as H,HybridInvoiceRequest as J,HybridInvoiceSnapshots as S,HybridUnitAllocations as U,HybridReturnPolicy as R,HybridReturnRequest as Q,HybridCustodyLedger as C,PromotionCalculator as M};
$checks=0;
function sameReturn($a,$b):void{global$checks;$checks++;if($a!==$b)throw new RuntimeException('Mismatch '.json_encode([$a,$b]));}
function receiptLine(string $line,string $paid,string $free='0',string $condition='SALEABLE'):array{
    $r=['line_number'=>$line,'paid_quantity'=>$paid,'free_quantity'=>$free,'condition_reason'=>'Synthetic condition inspection','holding_reference'=>'Synthetic controlled bin A'];
    foreach(['paid'=>$paid,'free'=>$free]as$kind=>$n){$r[$kind.'_conditions']=array_fill_keys(Q::CONDITIONS,'0');$r[$kind.'_conditions'][$condition]=$n;}return $r;
}
function returnSnapshot(array $lines,array $products,string $invoice='10',?array $replacement=null):array{
    $h=['policy_version'=>H::POLICY,'channel'=>'ONLINE','lines'=>$lines,'invoice_discount_percent'=>$invoice];if($replacement)$h['replacement_discount']=$replacement;
    $c=H::calculate($h,$products);$u=U::invoice($c);$review=['calculation'=>$c,'unit_allocation_digest'=>J::digest($u)];$review['review_hash']=J::digest($review);return S::build(['hybrid'=>$h],['review'=>$review,'allocations'=>$u]);
}
function originalProduct(string $id,string $price='100.00',?string $buy='6',string $stock='1000'):array{return ['product_id'=>$id,'unit_price'=>$price,'price_version'=>'1','available_quantity'=>$stock,'promotion'=>$buy===null?null:['id'=>'50'.$id,'revision_id'=>'90'.$id,'product_id'=>$id,'type'=>'BUY_X_GET_Y','buy_quantity'=>$buy,'free_quantity'=>'1']];}
function originalLine(string $id,string $quantity,string $choice='0',bool $replace=false):array{return ['product_id'=>$id,'paid_quantity'=>$quantity,'product_discount'=>['choice'=>$choice],'replace_free_entitlement'=>$replace];}
function planReturn(array $s,array $history,array $lines,string $cash, string $operation='RETURN',bool $noncash=false):array{return R::plan($s,$history,['operation'=>$operation,'lines'=>$lines],['cash_collected'=>$cash,'paid_amount'=>$cash,'noncash'=>$noncash,'legacy_void'=>false]);}
function storedReturn(array $p):array{return array_diff_key($p,['next_state'=>true]);}
$s=returnSnapshot([originalLine('1','8','25')],[originalProduct('1')]);
$one=planReturn($s,[],[receiptLine('1','1')],'675.00');sameReturn($one['totals']['net_credit'],'67.50');sameReturn($one['refund_increment'],'67.50');
$two=planReturn($s,[storedReturn($one)],[receiptLine('1','1')],'675.00');sameReturn($two['totals']['net_credit'],'67.50');
$three=planReturn($s,[storedReturn($one),storedReturn($two)],[receiptLine('1','1','1')],'675.00');sameReturn($three['totals']['net_credit'],'90.00');sameReturn($three['findings'],[]);
$combined=planReturn($s,[],[receiptLine('1','3','1')],'675.00');sameReturn(M::sumMoney($one['totals']['net_credit'],$two['totals']['net_credit'],$three['totals']['net_credit']),$combined['totals']['net_credit']);
sameReturn($combined['lines'][0]['ranges'][0]['segment'],'1:2');sameReturn($three['lines'][0]['ranges'][0]['segment'],'1:1');
$missing=planReturn($s,[],[receiptLine('1','3')],'675.00');sameReturn(in_array('MISSING_FREE_RECOVERY',array_column($missing['findings'],'code'),true),true);
$debt=planReturn($s,[],[receiptLine('1','2')],'575.00');sameReturn($debt['debt_reduction'],'100.00');sameReturn($debt['refund_increment'],'35.00');
$replacement=returnSnapshot([originalLine('2','12','0',true)],[originalProduct('2','100.00','12','12')],'10',['choice'=>'25']);
$rp=planReturn($replacement,[],[receiptLine('1','2')],'810.00');sameReturn($rp['totals']['replacement_discount_amount'],'50.00');sameReturn($rp['totals']['invoice_discount_amount'],'15.00');sameReturn($rp['totals']['net_credit'],'135.00');sameReturn($rp['retained_discount'],'325.00');
$mixed=returnSnapshot([originalLine('1','8','25'),originalLine('2','12','0',true)],[originalProduct('1'),originalProduct('2','100.00','12','12')],'10',['choice'=>'custom','percent'=>'50']);
$cap=planReturn($mixed,[],[receiptLine('1','8','1')],'1215.00');sameReturn($cap['totals']['net_credit'],'675.00');sameReturn($cap['retained_gross'],'1200.00');sameReturn($cap['retained_discount'],'660.00');sameReturn(in_array('RETAINED_CAP_EXCEEDED',array_column($cap['findings'],'code'),true),true);
$nc=planReturn($s,[],[receiptLine('1','2')],'100.00','RETURN',true);sameReturn(in_array('NONCASH_SETTLEMENT',array_column($nc['findings'],'code'),true),true);
$v=planReturn($s,[storedReturn($combined)],[receiptLine('1','5')],'675.00','VOID');sameReturn($v['retained_gross'],'0.00');sameReturn($v['retained_discount'],'0.00');sameReturn($v['next_state']['credit'],'675.00');sameReturn($v['next_state']['refund_due'],'675.00');
$odd=returnSnapshot([originalLine('1','1'),originalLine('2','1')],[originalProduct('1','0.01',null),originalProduct('2','0.03',null)],'50');$op=planReturn($odd,[],[receiptLine('2','1')],'0.02');sameReturn(in_array('RETAINED_CAP_EXCEEDED',array_column($op['findings'],'code'),true),true);
$large=returnSnapshot([originalLine('1','1000000000')],[originalProduct('1','0.01',null,'1000000000')],'25');$lp=planReturn($large,[],[receiptLine('1','500000000')],'7500000.00');sameReturn($lp['totals']['net_credit'],'2500000.00');sameReturn(count($lp['lines'][0]['ranges'])<10,true);
$e=[['version'=>1,'type'=>'RECEIVED','data'=>['lines'=>[receiptLine('1','2','0','QUARANTINE')]]]];$p=C::rebuild('synthetic',$e);sameReturn($p['released']['paid']??0,0);sameReturn($p['lines'][1]['held']['paid']['QUARANTINE'],2);
$e[]=['version'=>2,'type'=>'SUPPLEMENT','data'=>['lines'=>[receiptLine('1','0','1','UNSALEABLE')]]];$e[]=['version'=>3,'type'=>'ACCEPTED','data'=>['return_id'=>'synthetic-return']];$e[]=['version'=>4,'type'=>'RELEASE','data'=>['line_number'=>'1','unit_kind'=>'paid','from'=>'QUARANTINE','to'=>'SALEABLE','quantity'=>'1']];$p=C::rebuild('synthetic',$e);sameReturn($p['lines'][1]['released']['paid'],1);sameReturn($p['lines'][1]['held']['free']['UNSALEABLE'],1);
foreach([function()use($s,$one){R::state($s,[storedReturn($one),storedReturn($one)]);},function()use($s,$v){planReturn($s,[storedReturn($v)],[receiptLine('1','1')],'675.00');},function(){Q::lines([receiptLine('1','2')+['position_start'=>'10']]);}]as$reject){$caught=false;try{$reject();}catch(Throwable $error){$caught=true;}sameReturn($caught,true);}
echo 'Stage 4 pure policy: '.$checks.' checks passed; no database or current prices.'.PHP_EOL;
