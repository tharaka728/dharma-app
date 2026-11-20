<?php
// Presentation-only copy of the server review; never used as posting input.
$displayTotals=array_intersect_key($review,array_flip(['subtotal','discountable_subtotal','invoice_discount_base','invoice_discount_percent','invoice_discount_amount','grand_total','applied_amount','change_amount','outstanding','oil_offers']));
$displayTotals['lines']=array_map(static fn(array $line):array=>array_intersect_key($line,array_flip(['product_id','payable_amount','free_quantity'])),$review['lines']);
?>
<?php if(isset($review['return_settlement'])): $returnSettlement=$review['return_settlement']; ?>
<section class="alert alert-info" data-return-settlement-review aria-label="Historical return settlement"><strong><?= \App\Support\I18n::locale()==='en'?'Historical return settlement':'පැරණි භාණ්ඩ ආපසු ගැලපීම' ?></strong><dl class="row mb-0">
<?php foreach(['approved_value'=>['Approved return value','අනුමත ආපසු වටිනාකම'],'applied_value'=>['Noncash return adjustment','මුදල් නොවන ආපසු ගැලපීම'],'forfeited_value'=>['Forfeited value','භාවිත නොකළ වටිනාකම'],'remaining_payable'=>['Amount before cash payment','මුදල් ගෙවීමට පෙර ඉතිරිය']] as $key=>$labels): ?><dt class="col-7"><?= $e(\App\Support\I18n::locale()==='en'?$labels[0]:$labels[1]) ?></dt><dd class="col-5">LKR <?= $e($returnSettlement[$key]) ?></dd><?php endforeach; ?></dl></section>
<?php endif; ?>
<script type="application/json" id="invoice-reviewed-totals"><?= json_encode($displayTotals,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
