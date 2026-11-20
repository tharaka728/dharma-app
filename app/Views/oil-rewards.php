<section id="oil-rewards" aria-label="<?= \App\Support\I18n::html('Free oil choices') ?>" class="my-3">
<?php foreach($review['oil_offers']??[] as $offer): if($offer['earned']==='0')continue; ?>
<h3 class="h5"><?= $e($offer['name']) ?> <?= \App\Support\I18n::html('— choose free oils') ?></h3>
<p><?= $e($offer['earned']) ?> <?= \App\Support\I18n::html('earned;') ?> <?= $e($offer['remaining']) ?> <?= \App\Support\I18n::html('still to select. Paid units qualify; free units never qualify. Each reward must fit its six-unit group\'s price ceiling.') ?></p>
<?php foreach($offer['choices'] as $choice): $ri=$choice['product_id']; ?>
<div data-oil-reward-row class="mb-2"><input type="hidden" name="oil_rewards[<?= $e($ri) ?>][product_id]" value="<?= $e($ri) ?>"><label class="form-label" for="oil-reward-<?= $e($ri) ?>"><?= $e($choice['sku'].' / '.\App\Support\I18n::product($choice).' — LKR '.$choice['unit_price']) ?> (<?= $e($choice['additional_quantity']) ?> <?= \App\Support\I18n::html('more available)') ?></label><input class="form-control" id="oil-reward-<?= $e($ri) ?>" name="oil_rewards[<?= $e($ri) ?>][quantity]" value="<?= $e($choice['selected_quantity']) ?>" inputmode="numeric" pattern="0|[1-9][0-9]*" maxlength="19" aria-label="Free quantity for <?= $e($choice['name']) ?>"></div>
<?php endforeach; endforeach; ?>
</section>
