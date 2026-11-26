<?php
use App\Controllers\AuthController;
use App\Support\Http;
$e=static fn($v)=>Http::escape((string)$v);
$url=static fn($p)=>Http::escape(AuthController::url($p));
$asset=static fn($p)=>$url($p.'?v='.substr(hash_file('sha256',APP_ROOT.'/public'.$p),0,12));
?>
<!doctype html>
<html lang="<?= $e(\App\Support\I18n::locale()) ?>">
<head>
<?php require APP_ROOT.'/app/Views/localization-head.php'; ?>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hybrid Promotion V1 | Dharma Chemical</title>
<link rel="stylesheet" href="<?= $url('/assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
<link rel="stylesheet" href="<?= $asset('/assets/css/hybrid-invoice.css') ?>">
<script src="<?= $asset('/assets/js/hybrid-invoice.js') ?>" defer></script>
<script src="<?= $asset('/assets/js/hybrid-localization.js') ?>" data-hybrid-locale="<?= $e(\App\Support\I18n::locale()) ?>" defer></script>
</head>
<body class="workspace">
<?php require APP_ROOT.'/app/Views/workspace-header.php'; ?>
<main class="container py-4 hybrid-checkout">
<nav class="d-flex flex-wrap gap-3 mb-3" aria-label="Billing navigation">
<a href="<?= $url('/rep') ?>">Dashboard</a><a href="<?= $url('/rep/invoices') ?>">Invoice history</a><a href="<?= $url('/rep/invoices/new') ?>">Legacy checkout</a>
</nav>
<header class="mb-4"><p class="text-primary fw-semibold mb-1">ONLINE CHECKOUT</p><h1 class="h2">Hybrid Promotion V1</h1>
<p>Free offers and discounts are checked by the server. Your official invoice number is assigned only after confirmation.</p></header>
<noscript><p class="alert alert-warning"><?= \App\Support\HybridReportText::html('Hybrid checkout requires JavaScript and an online server connection. No invoice can be confirmed from this page without a verified preview.') ?></p></noscript>
<div id="hybrid-message" class="alert alert-info" role="status" tabindex="-1">Select a customer and add products to begin.</div>
<?php if(!$hybridAvailability['posting']): ?><p class="alert alert-warning" id="hybrid-posting-hold">Review is available. Invoice posting is currently disabled.</p><?php endif; ?>
<form id="hybrid-invoice-form" method="post" action="<?= $url('/api/v1/rep/invoices/preview') ?>"
 data-preview-url="<?= $url('/api/v1/rep/invoices/preview') ?>" data-issue-url="<?= $url('/api/v1/rep/invoices') ?>"
 data-eligibility-url="<?= $url('/api/v1/rep/invoices/hybrid/eligibility') ?>" data-invoice-base="<?= $url('/rep/invoices/') ?>"
 data-posting-enabled="<?= $hybridAvailability['posting']?'true':'false' ?>" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
<input type="hidden" name="request_id" value="<?= $e(\App\Services\Inventory::uuid()) ?>">
<input type="hidden" name="hybrid[policy_version]" value="HYBRID_PROMOTION_V1">
<fieldset id="hybrid-editor" disabled>
<div class="hybrid-columns">
<section class="card border-0 shadow-sm"><div class="card-body">
<h2 class="h5">Customer and products</h2>
<label for="hybrid-customer" class="form-label">Assigned customer</label>
<select id="hybrid-customer" class="form-select mb-3" required><option value="">Select a customer</option>
<?php foreach($options['customers'] as $c): ?><option value="<?= $e($c['id']) ?>"><?= $e($c['customer_code'].' — '.$c['shop_name']) ?></option><?php endforeach; ?>
</select>
<label for="hybrid-product-search" class="form-label">Search products by name or code</label>
<input id="hybrid-product-search" type="search" class="form-control mb-2" aria-controls="hybrid-product">
<label for="hybrid-product" class="form-label">Product</label>
<select id="hybrid-product" class="form-select mb-2" data-search-managed="true"><option value="">Select a product</option>
<?php foreach($options['products'] as $p): ?><option value="<?= $e($p['id']) ?>"><?= $e($p['sku'].' — '.$p['name']) ?></option><?php endforeach; ?>
</select>
<button type="button" id="hybrid-add" class="btn btn-outline-primary">Add product</button>
<p class="small text-secondary mt-2">Use one line per product. Paid and actual free units both consume your assigned stock.</p>
</div></section>
<section class="card border-0 shadow-sm"><div class="card-body">
<h2 class="h5">Payment and invoice discount</h2>
<label for="hybrid-payment" class="form-label">Sale type</label>
<select id="hybrid-payment" class="form-select mb-3"><option value="CASH">Cash</option><option value="CREDIT">Credit — subject to customer permission and limit</option></select>
<label for="hybrid-received" class="form-label">Cash received now (LKR)</label>
<input id="hybrid-received" class="form-control mb-3" inputmode="decimal" maxlength="16" value="0.00" required>
<label for="hybrid-invoice-choice" class="form-label">Whole-invoice discount (optional)</label>
<select id="hybrid-invoice-choice" class="form-select"><option value="0">No Discount (0%)</option><option value="25">25%</option><option value="30">30%</option><option value="35">35%</option><option value="other">Other Discount</option></select>
<div id="hybrid-invoice-custom-wrap" class="mt-2" hidden><label for="hybrid-invoice-custom" class="form-label">Other invoice discount (0–50%)</label><input id="hybrid-invoice-custom" class="form-control" inputmode="decimal" maxlength="5" disabled></div>
<p class="small text-secondary mt-2">Applied after product and approved replacement discounts. Combined monetary discounts must stay within the strict 50% cap.</p>
</div></section>
</div>
<section class="mt-4" aria-labelledby="hybrid-items-heading"><h2 id="hybrid-items-heading" class="h4">Invoice items</h2><div id="hybrid-lines"><p class="text-secondary" id="hybrid-empty">No products added.</p></div></section>
<section id="hybrid-replacement" class="card border-warning my-3" hidden><div class="card-body">
<h2 class="h5">Selected free-stock replacements</h2><p>Replacement is explicit and covers a complete free entitlement. Product discount on a replaced line must be zero. One replacement rate applies to all selected replacement lines.</p>
<label for="hybrid-replacement-choice" class="form-label">Replacement discount rate</label>
<select id="hybrid-replacement-choice" class="form-select" disabled><option value="">Select a rate</option><option value="25">25%</option><option value="30">30%</option><option value="35">35%</option><option value="custom">Other Discount</option></select>
<div id="hybrid-replacement-custom-wrap" class="mt-2" hidden><label for="hybrid-replacement-custom" class="form-label">Other replacement percentage (existing policy: 0–100%)</label><input id="hybrid-replacement-custom" class="form-control" inputmode="decimal" maxlength="6" disabled></div>
<p class="small text-secondary mt-2">Replacement money is included in the combined 50% monetary cap. No replacement is applied automatically.</p>
</div></section>
<button type="submit" id="hybrid-preview" class="btn btn-primary my-3">Review invoice</button>
</fieldset>
<section id="hybrid-review" class="card border-primary my-3" aria-label="Server-verified invoice review" hidden>
<div class="card-body"><h2 class="h4">Review before confirming</h2><p id="hybrid-review-context"></p><div id="hybrid-review-lines"></div><dl id="hybrid-totals" class="hybrid-totals"></dl>
<p id="hybrid-cap" class="alert alert-success"></p><p>Confirm only these reviewed selections. Any change requires a new server preview.</p></div>
</section>
<button type="button" id="hybrid-confirm" class="btn btn-success" disabled>Confirm and issue invoice</button>
<div id="hybrid-result" class="mt-3" role="status"></div>
</form>
</main>
<?php require APP_ROOT.'/app/Views/workspace-footer.php'; ?>
</body></html>
