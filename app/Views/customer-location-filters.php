<?php use App\Support\Http;
$selectedDistrict=$filterLocations['district_id'];$selectedTown=$filterLocations['town_id']; ?>
<div class="col-sm-5" id="customer-location-filters" data-towns-url="<?= $url($townsUrl??'/api/v1/admin/locations/towns') ?>">
<label class="form-label" for="filter-district"><?= \App\Support\I18n::html('District') ?></label>
<input class="form-control mb-2" type="search" id="filter-district-search" placeholder="<?= \App\Support\I18n::html('Type to search districts') ?>" aria-label="<?= \App\Support\I18n::html('Search districts') ?>" aria-controls="filter-district" autocomplete="off" maxlength="100">
<select class="form-select" name="district_id" id="filter-district"><option value=""><?= \App\Support\I18n::html('All districts') ?></option><?php foreach($filterLocations['districts'] as $row): ?><option value="<?= Http::escape($row['id']) ?>" <?= $selectedDistrict===$row['id']?'selected':'' ?>><?= Http::escape($row['name']) ?></option><?php endforeach; ?></select>
</div>
<div class="col-sm-5">
<label class="form-label" for="filter-town"><?= \App\Support\I18n::html('City / Town') ?></label>
<input class="form-control mb-2" type="search" id="filter-town-search" placeholder="<?= \App\Support\I18n::html('Type to search cities / towns') ?>" aria-label="<?= \App\Support\I18n::html('Search cities or towns') ?>" aria-controls="filter-town" autocomplete="off" maxlength="100">
<select class="form-select" name="town_id" id="filter-town"><option value=""><?= \App\Support\I18n::html('All cities / towns') ?></option><?php foreach($filterLocations['towns'] as $row): ?><option value="<?= Http::escape((string)$row['id']) ?>" data-district-id="<?= Http::escape((string)$row['district_id']) ?>" <?= $selectedTown===(string)$row['id']?'selected':'' ?>><?= Http::escape($row['name'].' — '.$row['district_name']) ?></option><?php endforeach; ?></select>
<button type="button" class="btn btn-link btn-sm px-0" id="filter-town-more" <?= $filterLocations['has_more']?'':'hidden' ?>><?= \App\Support\I18n::html('Load more towns') ?></button>
<p class="small text-secondary mb-0 mt-1" id="filter-town-status" role="status" aria-live="polite"><?= \App\Support\I18n::html('Search towns or choose All cities / towns.') ?></p>
<noscript><?= \App\Support\I18n::html('Choose from the towns shown and press Filter. Enable JavaScript to search more towns or refresh town options when changing districts.') ?></noscript>
</div>
