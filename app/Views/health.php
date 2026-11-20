<!doctype html>
<html lang="<?= \App\Support\I18n::locale() ?>"><head><?php require APP_ROOT.'/app/Views/localization-head.php'; ?><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= \App\Support\I18n::html('Dharma Sales — Foundation') ?></title><link rel="stylesheet" href="<?= $base ?>/assets/vendor/bootstrap/bootstrap.min.css"></head>
<body class="bg-light"><main class="container py-5"><div class="row justify-content-center"><div class="col-12 col-md-8 col-lg-6">
<section class="card shadow-sm"><div class="card-body p-4"><h1 class="h3"><?= \App\Support\I18n::html('Dharma Sales') ?></h1><p class="text-secondary"><?= \App\Support\I18n::html('Ayurveda sales and billing · LKR') ?></p>
<div class="alert <?= $ready ? 'alert-success' : 'alert-warning' ?>" role="status"><?= \App\Support\Http::escape($status) ?></div>
<p><?= \App\Support\I18n::html('Phase 1 foundation. Follow the project README to configure the database and run migrations.') ?></p>
<a class="btn btn-outline-primary" href="<?= $base ?>/api/v1/health"><?= \App\Support\I18n::html('Check system status') ?></a></div></section>
</div></div></main></body></html>
