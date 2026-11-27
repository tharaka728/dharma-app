<?php $hybridDiscount=$hybridOnline??false; ?>
<?php $choice=$v('invoice_discount_choice','0'); ?>
<fieldset class="border rounded p-3 my-3"><legend class="float-none w-auto px-2 h6"><?= \App\Support\I18n::html('Invoice Discount') ?></legend>
<?php if(!$hybridDiscount): ?><input type="hidden" name="discount_policy" value="INVOICE_PERCENT_V1"><?php endif; ?>
<label for="invoice_discount_choice" class="form-label"><?= \App\Support\I18n::html('Invoice Discount') ?></label>
<select id="invoice_discount_choice" name="invoice_discount_choice" class="form-select"><?php foreach(['0'=>'No Discount — 0%','25'=>'25%','30'=>'30%','35'=>'35%','custom'=>'Other — Custom Percentage'] as $key=>$label): ?><option value="<?= $key ?>" <?= $choice===(string)$key?'selected':'' ?>><?= \App\Support\I18n::html($label) ?></option><?php endforeach; ?></select>
<div id="invoice-discount-custom" class="mt-3" <?= $hybridDiscount?'hidden':'' ?>><label for="invoice_discount_percent" class="form-label"><?= \App\Support\I18n::html($hybridDiscount?'Other invoice percentage (0–50%)':'Custom percentage (0–100)') ?></label><input id="invoice_discount_percent" name="invoice_discount_percent" <?= $hybridDiscount?'disabled':'' ?> class="form-control" type="number" min="0" max="<?= $hybridDiscount?'50':'100' ?>" step="0.01" inputmode="decimal" value="<?= $e($v('invoice_discount_percent')) ?>"></div>
<p class="small text-secondary mt-2 mb-0"><?= \App\Support\I18n::html($hybridDiscount?'Applied after product and approved replacement discounts. Combined monetary discounts must stay within the strict 50% cap.':'Applied after product promotions to paid amounts. Free units still consume stock.') ?></p>
</fieldset><p id="invoice-live-summary" class="alert alert-light border text-wrap" role="status" aria-live="polite"><?= \App\Support\I18n::html('Choose a customer and products to calculate totals.') ?></p>

<?php if($hybridDiscount): ?>
<fieldset data-replacement hidden class="border rounded p-3 my-3"><legend class="float-none w-auto px-2 h6"><?= \App\Support\I18n::html('Replacement discount') ?></legend>
<label for="replacement_discount_choice" class="form-label"><?= \App\Support\I18n::html('Explicit eligible shortage replacement') ?></label>
<select id="replacement_discount_choice" class="form-select"><option value=""><?= \App\Support\I18n::html('Select a replacement discount') ?></option><option value="25">25%</option><option value="30">30%</option><option value="35">35%</option><option value="custom"><?= \App\Support\I18n::html('Other custom percentage') ?></option></select>
<div id="replacement-custom-wrap" hidden><label for="replacement_discount_percent" class="form-label"><?= \App\Support\I18n::html('Custom replacement percentage') ?></label><input id="replacement_discount_percent" class="form-control" inputmode="decimal" maxlength="5" disabled></div></fieldset>
<?php endif; ?>
