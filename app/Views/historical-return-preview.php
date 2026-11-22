<?php
$previewSi=\App\Support\I18n::locale()!=='en';
$previewText=static fn(string $en,string $si):string=>$previewSi?$si:$en;
?>
<details class="historical-return-preview card card-body my-3" id="historical-return-preview" data-product-url="<?= $url('/api/v1/rep/return-preview-products') ?>">
 <summary class="historical-return-summary">
 <h2 class="h5"><?= $e($previewText('Historical Product Return','පැරණි භාණ්ඩ ආපසු භාරගැනීම')) ?></h2>
 <p class="alert alert-info"><?= $e($previewText('Read-only verification: no return can be started or posted. Ordinary invoice checkout remains available.','පරීක්ෂා කිරීම සඳහා පමණි: ආපසු භාරගැනීමක් ආරම්භ කිරීමට හෝ සටහන් කිරීමට නොහැක. සාමාන්‍ය බිල්පත් භාවිත කළ හැක.')) ?></p></summary>
 <button type="button" class="btn btn-outline-success" data-preview-toggle aria-expanded="false" aria-controls="return-preview-panel"><?= $e($previewText('View historical return','පැරණි භාණ්ඩ ආපසු භාරගැනීම බලන්න')) ?></button>
 <div id="return-preview-panel" data-preview-panel hidden>
  <p class="small mt-3"><?= $e($previewText('Search products and select a historical year. No original invoice is required.','භාණ්ඩ සොයා පැරණි මිල වර්ෂය තෝරන්න. පැරණි බිල්පතක් අවශ්‍ය නොවේ.')) ?></p>
  <?php for($i=1;$i<=2;$i++): ?><fieldset class="return-preview-line" data-preview-line><legend><?= $e($previewText('Returned product','ආපසු ලැබුණු භාණ්ඩය')) ?> <?= $i ?></legend>
   <div class="return-preview-grid"><div class="return-preview-search"><label class="form-label" for="return-preview-search-<?= $i ?>"><?= $e($previewText('Search product','භාණ්ඩය සොයන්න')) ?></label><input id="return-preview-search-<?= $i ?>" class="form-control" type="search" maxlength="100" autocomplete="off" data-preview-search aria-describedby="return-preview-help-<?= $i ?>"><small id="return-preview-help-<?= $i ?>"><?= $e($previewText('Search by product code or name, then select a result.','භාණ්ඩ කේතයෙන් හෝ නමින් සොයා ප්‍රතිඵලයක් තෝරන්න.')) ?></small></div>
   <div><label class="form-label" for="return-preview-year-<?= $i ?>"><?= $e($previewText('Pricing year','මිල වර්ෂය')) ?></label><select id="return-preview-year-<?= $i ?>" class="form-select" data-preview-year><option value="2024">2024</option><option value="2025">2025</option></select></div></div>
   <p class="form-label return-preview-results-label" id="return-preview-results-label-<?= $i ?>"><?= $e($previewText('Matching products','ගැළපෙන භාණ්ඩ')) ?></p><div id="return-preview-products-<?= $i ?>" class="return-preview-results" role="listbox" aria-labelledby="return-preview-results-label-<?= $i ?>" data-preview-products><span class="return-preview-empty"><?= $e($previewText('Type a product code or name to search','භාණ්ඩ කේතය හෝ නම ඇතුළත් කරන්න')) ?></span></div>
   <p class="return-preview-selected" data-preview-selected><?= $e($previewText('No product selected','භාණ්ඩයක් තෝරා නැත')) ?></p>
   <p class="return-preview-price" role="status" data-preview-status aria-live="polite"></p>
   <label class="form-label" for="return-preview-quantity-<?= $i ?>"><?= $e($previewText('Quantity','ප්‍රමාණය')) ?></label><input id="return-preview-quantity-<?= $i ?>" class="form-control return-preview-quantity" type="number" min="1" step="1" value="1" form="historical-return-preview-only" data-preview-quantity>
  </fieldset><?php endfor; ?>
 </div>
</details>
<script src="<?= $url('/assets/js/historical-return-preview.js?v=2') ?>" defer></script>
