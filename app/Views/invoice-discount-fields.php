<?php $choice=$v('invoice_discount_choice','0'); ?>
<fieldset class="border rounded p-3 my-3"><legend class="float-none w-auto px-2 h6"><?= \App\Support\I18n::html('Invoice Discount') ?></legend>
<input type="hidden" name="discount_policy" value="INVOICE_PERCENT_V1">
<label for="invoice_discount_choice" class="form-label"><?= \App\Support\I18n::html('Invoice Discount') ?></label>
<select id="invoice_discount_choice" name="invoice_discount_choice" class="form-select"><?php foreach(['0'=>'No Discount — 0%','25'=>'25%','30'=>'30%','35'=>'35%','custom'=>'Other — Custom Percentage'] as $key=>$label): ?><option value="<?= $key ?>" <?= $choice===(string)$key?'selected':'' ?>><?= \App\Support\I18n::html($label) ?></option><?php endforeach; ?></select>
<div id="invoice-discount-custom" class="mt-3"><label for="invoice_discount_percent" class="form-label"><?= \App\Support\I18n::html('Custom percentage (0–100)') ?></label><input id="invoice_discount_percent" name="invoice_discount_percent" class="form-control" type="number" min="0" max="100" step="0.01" inputmode="decimal" value="<?= $e($v('invoice_discount_percent')) ?>"></div>
<p class="small text-secondary mt-2 mb-0"><?= \App\Support\I18n::html('Applied after product promotions to paid amounts. Free units still consume stock.') ?></p>
</fieldset><p id="invoice-live-summary" class="alert alert-light border text-wrap" role="status" aria-live="polite"><?= \App\Support\I18n::html('Choose a customer and products to calculate totals.') ?></p>
