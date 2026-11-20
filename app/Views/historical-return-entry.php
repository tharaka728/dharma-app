<?php
$returnInput=is_array($input['historical_return']??null)?$input['historical_return']:[];
$returnLines=is_array($returnInput['lines']??null)?array_values($returnInput['lines']):[];
$returnActive=(bool)$returnInput;
$returnSi=\App\Support\I18n::locale()!=='en';
$returnLabel=static fn($english,$sinhala)=>$returnSi?$sinhala:$english;
?>
<section class="return-entry mb-4" id="historical-return" data-price-url="<?= $url(($admin?'/api/v1/admin':'/api/v1/rep').'/historical-prices') ?>" data-intent-url="<?= $url(($admin?'/api/v1/admin':'/api/v1/rep').'/return-intents') ?>" data-invoice-base="<?= $url($base) ?>" data-active="<?= $returnActive?'1':'0' ?>">
  <div class="return-entry-head">
    <div class="return-entry-heading">
      <h2 class="h5"><?= $e($returnLabel('Product Return','භාණ්ඩ ආපසු භාරගැනීම')) ?></h2>
      <p class="return-entry-sub"><?= $e($returnLabel('Online only. No original invoice or administrator approval is required.','අන්තර්ජාල සම්බන්ධතාව අවශ්‍යයි. පැරණි බිල්පතක් හෝ පරිපාලක අනුමැතිය අවශ්‍ය නොවේ.')) ?></p>
    </div>
    <button type="button" class="btn btn-success" data-return-toggle><?= $e($returnLabel('Start new return','නව භාණ්ඩ ආපසු භාරගැනීමක් ආරම්භ කරන්න')) ?></button>
  </div>
  <p class="return-status" data-return-status role="status" aria-live="polite"></p>
  <a data-return-invoice hidden></a>
  <div class="return-recover">
    <label class="return-recover-label"><?= $e($returnLabel('Recover a recent return','මෑත ආපසු භාරගැනීමක් නැවත ලබාගන්න')) ?>
      <select class="form-select form-select-sm" data-return-recent><option value="">—</option></select>
    </label>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-return-recover><?= $e($returnLabel('Recover','නැවත ලබාගන්න')) ?></button>
  </div>
  <div data-return-panel <?= $returnActive?'':'hidden' ?>>
    <p class="small text-secondary mb-3"><?= $e($returnLabel('Search each product and year, then select the approved price.','එක් එක් භාණ්ඩය සහ වර්ෂය සොයා අනුමත මිල තෝරන්න.')) ?></p>
    <input type="hidden" name="historical_return[return_intent_id]" value="<?= $e($returnInput['return_intent_id']??'') ?>" data-return-intent data-return-common disabled>
    <?php for($ri=0;$ri<5;$ri++):$rl=$returnLines[$ri]??[]; ?>
    <fieldset class="return-line-card" data-return-line>
      <legend class="return-line-title"><?= $e($returnLabel('Returned product','ආපසු ලැබුණු භාණ්ඩය')) ?> <?= $ri+1 ?></legend>
      <div class="return-line-grid">
        <label class="form-label"><?= $e($returnLabel('Search product','භාණ්ඩය සොයන්න')) ?>
          <input type="search" class="form-control" data-return-search maxlength="100">
        </label>
        <label class="form-label return-year-label"><?= $e($returnLabel('Pricing year','මිල වර්ෂය')) ?>
          <select class="form-select" data-return-year name="historical_return[lines][<?= $ri ?>][year]" disabled>
            <option value="2024" <?= ($rl['year']??'2024')==='2024'?'selected':'' ?>>2024</option>
            <option value="2025" <?= ($rl['year']??'')==='2025'?'selected':'' ?>>2025</option>
          </select>
        </label>
        <div class="return-line-find">
          <button type="button" class="btn btn-outline-primary btn-sm" data-return-search-button><?= $e($returnLabel('Find approved prices','අනුමත මිල සොයන්න')) ?></button>
        </div>
        <label class="form-label return-product-label"><?= $e($returnLabel('Product and approved price','භාණ්ඩය සහ අනුමත මිල')) ?>
          <select class="form-select" data-return-product name="historical_return[lines][<?= $ri ?>][product_id]" data-selected="<?= $e($rl['product_id']??'') ?>" disabled>
            <option value=""><?= $e($returnLabel('Choose product','භාණ්ඩය තෝරන්න')) ?></option>
          </select>
        </label>
        <label class="form-label return-qty-label"><?= $e($returnLabel('Quantity','ප්‍රමාණය')) ?>
          <input class="form-control" type="number" min="1" step="1" inputmode="numeric" data-return-quantity name="historical_return[lines][<?= $ri ?>][quantity]" value="<?= $e($rl['quantity']??'1') ?>" disabled>
        </label>
      </div>
      <p class="return-line-value" data-return-value aria-live="polite"></p>
    </fieldset>
    <?php endfor; ?>
    <div class="return-summary">
      <span class="return-summary-label"><?= $e($returnLabel('Return value','ආපසු භාණ්ඩවල වටිනාකම')) ?></span>
      <strong class="return-summary-value">LKR <span data-return-total>0.00</span></strong>
    </div>
    <label class="form-label mt-3"><?= $e($returnLabel('Reason','හේතුව')) ?>
      <input class="form-control" name="historical_return[reason]" maxlength="500" value="<?= $e($returnInput['reason']??'') ?>" data-return-common disabled>
    </label>
    <div class="return-declarations">
      <?php foreach(['received'=>['Goods physically received','භාණ්ඩ භෞතිකව භාරගත් බව'],'unsaleable'=>['Goods unsuitable for resale','භාණ්ඩ නැවත විකිණීමට නුසුදුසු බව'],'eligible'=>['Eligible under company policy','සමාගම් ප්‍රතිපත්තියට අනුව සුදුසු බව']] as $key=>$labels): ?>
      <label class="return-check">
        <input class="form-check-input" type="checkbox" name="historical_return[<?= $key ?>]" value="1" <?= ($returnInput[$key]??'')==='1'?'checked':'' ?> data-return-common disabled>
        <span class="return-check-text"><?= $e($returnLabel($labels[0],$labels[1])) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<script src="<?= $url('/assets/js/historical-return-entry.js?v='.substr(hash_file('sha256',APP_ROOT.'/public/assets/js/historical-return-entry.js'),0,12)) ?>" defer></script>
