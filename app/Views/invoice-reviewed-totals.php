<?php
// Presentation-only copy of the server review; never used as posting input.
$displayTotals=array_intersect_key($review,array_flip(['subtotal','discountable_subtotal','invoice_discount_base','invoice_discount_percent','invoice_discount_amount','grand_total','applied_amount','change_amount','outstanding','oil_offers']));
$displayTotals['lines']=array_map(static fn(array $line):array=>array_intersect_key($line,array_flip(['product_id','product_name','sku','paid_quantity','gross_amount','automatic_discount_amount','invoice_discount_amount','discount_amount','payable_amount','free_quantity','rule_snapshot'])),$review['lines']);
?>
<?php if(isset($review['return_settlement'])): $returnSettlement=$review['return_settlement']; ?>
<section class="settlement-card" data-return-settlement-review aria-label="Historical return settlement">
  <h3 class="settlement-title"><?= \App\Support\I18n::locale()==='en'?'Historical return settlement':'පැරණි භාණ්ඩ ආපසු ගැලපීම' ?></h3>
  <dl class="settlement-rows mb-0">
    <?php foreach(['approved_value'=>['Approved return value','අනුමත ආපසු වටිනාකම'],'applied_value'=>['Noncash return adjustment','මුදල් නොවන ආපසු ගැලපීම'],'forfeited_value'=>['Forfeited value','භාවිත නොකළ වටිනාකම'],'remaining_payable'=>['Amount before cash payment','මුදල් ගෙවීමට පෙර ඉතිරිය']] as $key=>$labels): ?>
    <div class="settlement-row<?= $key==='remaining_payable'?' settlement-row-total':'' ?>">
      <dt><?= $e(\App\Support\I18n::locale()==='en'?$labels[0]:$labels[1]) ?></dt>
      <dd>LKR <?= $e($returnSettlement[$key]) ?></dd>
    </div>
    <?php endforeach; ?>
  </dl>
</section>
<?php endif; ?>
<script type="application/json" id="invoice-reviewed-totals"><?= json_encode($displayTotals,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
