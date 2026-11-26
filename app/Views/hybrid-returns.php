<?php
use App\Controllers\AuthController;use App\Support\Http;
$e=static fn($v)=>Http::escape((string)$v);$url=static fn($p)=>Http::escape(AuthController::url($p));
?>
<!doctype html><html lang="<?= $e(\App\Support\I18n::locale()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hybrid linked returns</title>
<?php require APP_ROOT.'/app/Views/localization-head.php'; ?>
<script defer data-hybrid-locale="<?= $e(\App\Support\I18n::locale()) ?>" src="<?= $url('/assets/js/hybrid-localization.js?v='.substr(hash_file('sha256',APP_ROOT.'/public/assets/js/hybrid-localization.js'),0,12)) ?>"></script>
<link rel="stylesheet" href="<?= $url('/assets/vendor/bootstrap/bootstrap.min.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
<link rel="stylesheet" href="<?= $url('/assets/css/hybrid-returns.css?v='.substr(hash_file('sha256',APP_ROOT.'/public/assets/css/hybrid-returns.css'),0,12)) ?>">
<script defer src="<?= $url('/assets/js/hybrid-returns.js?v='.substr(hash_file('sha256',APP_ROOT.'/public/assets/js/hybrid-returns.js'),0,12)) ?>"></script></head>
<body class="workspace"><?php require APP_ROOT.'/app/Views/workspace-header.php'; ?><main class="container py-4">
<a href="<?= $url($home.'/invoices') ?>">Invoice history</a><h1 class="h2 mt-3">Hybrid linked returns</h1>
<p>Online only. Credits use original invoice allocations. Refund Due records a liability; this screen does not pay cash. Pending custody holds physical goods without reducing debt or increasing saleable stock.</p>
<noscript><?= \App\Support\HybridReportText::html('This workflow requires JavaScript and an online server connection.') ?></noscript>
<div id="return-message" class="alert alert-info" role="status" tabindex="-1">Loading authorized invoices…</div>
<form id="hybrid-return-form" data-api="<?= $url('/api/v1'.$home.'/invoices/hybrid/returns/') ?>" data-posting="<?= $capabilities['posting']?'1':'0' ?>" data-custody="<?= $capabilities['custody']?'1':'0' ?>" data-manager="<?= $capabilities['manager']?'1':'0' ?>">
<input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']??'') ?>">
<label class="form-label" for="return-invoice">Original Hybrid invoice</label><select class="form-select mb-3" id="return-invoice"><option value="">Choose an invoice</option></select>
<p id="return-balances"></p><div class="table-responsive"><table class="table"><thead><tr><th>Original line / remaining paid + free</th><th>Paid received<br>Saleable / Quarantine / Unsaleable</th><th>Free received<br>Saleable / Quarantine / Unsaleable</th><th>Condition evidence and holding location</th></tr></thead><tbody id="return-lines"></tbody></table></div>
<label for="return-reason" class="form-label">Return / physical receipt reason</label><textarea id="return-reason" class="form-control mb-3" maxlength="500" required></textarea>
<label for="return-operation" class="form-label">Financial action</label><select id="return-operation" class="form-select mb-3"><option value="RETURN">Linked return</option><?php if($admin): ?><option value="VOID">Residual full void — all remaining goods</option><?php endif; ?></select>
<div class="d-flex flex-wrap gap-2"><button type="button" id="return-preview" class="btn btn-primary">Review original-net credit</button><button type="button" id="return-post" class="btn btn-success" disabled>Confirm reviewed credit</button><button type="button" id="return-receive" class="btn btn-outline-secondary">Record pending physical custody</button><button type="button" id="return-supplement" class="btn btn-outline-secondary" disabled>Add goods to pending receipt</button><button type="button" id="return-retry" class="btn btn-warning" hidden>Retry same request</button></div>
<section class="my-4" aria-label="Return review"><h2 class="h5">Server review</h2><div id="return-review">No current review.</div></section>
<section class="my-4"><h2 class="h5">Physical custody and manager review</h2><p id="return-pending">No invoice selected.</p><label for="return-custody" class="form-label">Recorded receipt</label><select id="return-custody" class="form-select mb-3"><option value="">Choose a receipt</option></select><pre id="return-custody-detail" class="border rounded p-2 text-wrap"></pre>
<?php if($admin): ?><fieldset id="return-manager"><legend class="h6">Manager disposition</legend><p>Review and rejection never waive financial blocks. Release, reclassification and disposal require financially accepted custody.</p>
<label>Action <select id="return-manager-action" class="form-select"><option>REVIEW</option><option>REJECT</option><option>RECLASSIFY</option><option>RELEASE</option><option>DISPOSE</option></select></label>
<label>Original line <input id="return-manager-line" class="form-control" type="number" min="1" value="1"></label><label>Unit kind <select id="return-manager-kind" class="form-select"><option value="paid">Paid</option><option value="free">Free</option></select></label>
<label>Current held condition <select id="return-manager-from" class="form-select"><option>QUARANTINE</option><option>UNSALEABLE</option></select></label><label>Reclassify to <select id="return-manager-to" class="form-select"><option>UNSALEABLE</option><option>QUARANTINE</option></select></label>
<label>Quantity <input id="return-manager-quantity" class="form-control" type="number" min="1" value="1"></label><label class="d-block">Manager reason <textarea id="return-manager-reason" class="form-control" maxlength="500"></textarea></label><button type="button" id="return-manage" class="btn btn-outline-primary mt-2">Record manager action</button></fieldset><?php endif; ?>
</section></form></main></body></html>
