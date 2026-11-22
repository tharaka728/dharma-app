<?php $benefitMode=$v('benefit_mode',''); ?>
<fieldset class="border rounded p-3 my-3" id="invoice-benefit-fields"><legend class="float-none w-auto px-2 h6"><?= \App\Support\I18n::html('Promotion & Discount') ?></legend>
 <div class="benefit-cards" role="radiogroup" aria-label="<?= \App\Support\I18n::html('Benefit mode') ?>">
<?php foreach(['FREE_PROMOTION'=>['FREE','Free promotion','Receive eligible free products and existing promotions.'],'PROMOTION_DISCOUNT_INSTEAD'=>['%','Promotion discount instead','Discount on qualifying promotional products only.'],'ORDINARY_INVOICE_DISCOUNT'=>['LKR','Ordinary invoice discount','Discount on the eligible whole-invoice subtotal.']] as $value=>[$icon,$label,$help]): ?>
  <label class="benefit-card" for="<?= $e('benefit_mode_'.strtolower($value)) ?>"><input type="radio" name="benefit_mode" id="<?= $e('benefit_mode_'.strtolower($value)) ?>" value="<?= $e($value) ?>"<?= ($benefitMode===$value||($benefitMode===''&&$value==='FREE_PROMOTION'))?' checked':'' ?>><span class="benefit-card-icon" aria-hidden="true"><?= $icon ?></span><span class="benefit-card-text"><strong><?= \App\Support\I18n::html($label) ?></strong><small><?= \App\Support\I18n::html($help) ?></small></span></label>
<?php endforeach; ?>
 </div>
 <p class="small text-secondary mt-2 mb-0" id="free-promotion-info" hidden><?= \App\Support\I18n::html('Eligible free products and existing promotions are calculated during Review.') ?></p>
 <div class="rate-chips mt-3" id="promotion-discount-rate" role="radiogroup" aria-label="<?= \App\Support\I18n::html('Promotion discount') ?>" hidden>
<?php foreach(['0'=>'0% — No Promotion or Discount','25'=>'25%','30'=>'30%','35'=>'35%','custom'=>'Other — Custom Percentage'] as $key=>$label): ?>
  <label class="rate-chip" for="<?= $e('promotion_discount_choice_'.$key) ?>" data-rate="<?= $e($key) ?>"><input type="radio" name="promotion_discount_choice" id="<?= $e('promotion_discount_choice_'.$key) ?>" value="<?= $e($key) ?>"<?= $v('promotion_discount_choice','0')===(string)$key?' checked':'' ?> disabled><span><?= \App\Support\I18n::html($label) ?></span></label>
<?php endforeach; ?>
 </div>
 <div id="promotion-discount-custom" class="mt-2" hidden><label for="promotion_discount_percent" class="form-label"><?= \App\Support\I18n::html('Custom percentage (0–100)') ?></label><input id="promotion_discount_percent" class="form-control form-control-sm" name="promotion_discount_percent" type="number" min="0" max="100" step="0.01" value="<?= $e($v('promotion_discount_percent','0')) ?>" disabled><p class="small text-secondary mb-0 mt-1"><?= \App\Support\I18n::html('Enter a discount from 0 to 100 with up to two decimal places.') ?></p></div>
 <input type="hidden" name="benefit_policy" value="PROMO_CHOICE_V1">
 <p class="small text-secondary mt-2 mb-0"><?= \App\Support\I18n::html('The server calculates eligibility and the discount during Review. Free units are suppressed in discount modes and mixed-oil rewards are unavailable there.') ?></p>
</fieldset>
