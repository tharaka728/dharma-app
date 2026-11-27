<?php use App\Support\{Http,Workspace};use App\Controllers\AuthController;$admin=$role==='ADMIN';$links=Workspace::links($role); ?>
<!doctype html>
<html lang="<?= \App\Support\I18n::locale() ?>">
<head>
    <?php require APP_ROOT.'/app/Views/localization-head.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $admin?\App\Support\I18n::html('Admin'):\App\Support\I18n::html('Sales representative') ?> <?= \App\Support\I18n::html('| Dharma Chemical') ?></title>
    <link rel="stylesheet" href="<?= Http::escape(AuthController::url('/assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= Http::escape(AuthController::url('/assets/css/app.css')) ?>">
    <?php require APP_ROOT.'/app/Views/pwa-head.php'; ?>
</head>
<body class="workspace">
<?php require APP_ROOT.'/app/Views/workspace-header.php'; ?>
<main class="container-fluid px-md-5 py-4 dashboard-main">
    <?php
    $si=\App\Support\I18n::locale()!=='en';
    $roleLabel=$admin?\App\Support\I18n::text('Admin'):\App\Support\I18n::text('Sales representative');
    $newInvoiceHref=AuthController::url($admin?'/admin/invoices/new':'/rep/invoices/new');
    $sectionIcon=static function(string $key):string{
        $paths=[
            'sales'=>'M6 6h15l-1.5 9h-12z M6 6L5 3H2 M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2 M18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2',
            'stock'=>'M12 3l9 5-9 5-9-5z M3 13l9 5 9-5',
            'admin'=>'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6 M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1',
            'bell'=>'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9 M13.7 21a2 2 0 0 1-3.4 0',
            'user'=>'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10 M3 22v-2a7 7 0 0 1 7-7h4a7 7 0 0 1 7 7v2',
        ];
        return '<svg class="workspace-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$paths[$key].'"></path></svg>';
    };
    ?>
    <section class="dashboard-welcome">
        <div class="dashboard-welcome-identity">
            <span class="dashboard-welcome-avatar"><?= $sectionIcon('user') ?></span>
            <div class="dashboard-welcome-text">
                <p class="dashboard-eyebrow"><?= \App\Support\I18n::html('Welcome,') ?> <?= Http::escape($user['name']) ?></p>
                <h1><?= $admin?\App\Support\I18n::html('Admin dashboard'):\App\Support\I18n::html('Sales representative dashboard') ?></h1>
                <p class="dashboard-welcome-subtitle"><?= \App\Support\I18n::html('Sales & Billing System') ?></p>
            </div>
            <span class="dashboard-role-pill"><?= $sectionIcon('user') ?><span><?= Http::escape($roleLabel) ?></span></span>
        </div>
        <a class="dashboard-primary-action" href="<?= Http::escape($newInvoiceHref) ?>">
            <?= Workspace::icon('Invoices') ?>
            <span><?= \App\Support\I18n::html('New Invoice') ?></span>
            <span aria-hidden="true">→</span>
        </a>
    </section>
    <a class="dashboard-primary-action dashboard-primary-action-mobile" href="<?= Http::escape($newInvoiceHref) ?>">
        <?= Workspace::icon('Invoices') ?>
        <span><?= \App\Support\I18n::html('New Invoice') ?></span>
        <span aria-hidden="true">→</span>
    </a>

    <?php require APP_ROOT.'/app/Views/dashboard-metrics.php'; ?>

    <?php
    unset($links['Overview']);
    $groups=[];
    if($admin){
        $groupMap=[
            'Sales Operations'=>['Invoices','Customers','Products'],
            'Stock Management'=>['Inventory','Stock Allocation','Stock Returns'],
            'Admin & Reports'=>['Promotions','Reports','Language and company details'],
        ];
        $groupIcons=['Sales Operations'=>'sales','Stock Management'=>'stock','Admin & Reports'=>'admin'];
        $used=[];
        foreach($groupMap as $groupLabel=>$labels)foreach($labels as $label)if(isset($links[$label])){$groups[$groupLabel][$label]=$links[$label];$used[]=$label;}
        foreach($links as $label=>$href)if(!in_array($label,$used,true))$groups['Admin & Reports'][$label]=$href;
    }else{
        $groupIcons=['Your workspace'=>'sales'];
        $groups=['Your workspace'=>$links];
    }
    ?>
    <div class="dashboard-groups">
        <?php if($admin): ?>
        <div class="dashboard-section-heading dashboard-groups-combined-heading">
            <p class="dashboard-eyebrow"><?= \App\Support\I18n::html('Navigation') ?></p>
            <h2><?= \App\Support\I18n::html('Your workspace') ?></h2>
        </div>
        <?php endif; ?>
        <?php foreach($groups as $groupLabel=>$groupLinks): ?>
        <section class="dashboard-group">
            <?php if(!$admin||$groupLabel!=='Your workspace'): ?>
            <div class="dashboard-section-heading dashboard-group-heading">
                <h2><?= $sectionIcon($groupIcons[$groupLabel]??'sales') ?> <?= \App\Support\I18n::html($groupLabel) ?></h2>
            </div>
            <?php endif; ?>
            <?php foreach($groupLinks as $label=>$href):
                $currentPath=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
                $isCurrent=$currentPath===AuthController::url(explode('#',$href)[0]);
            ?>
            <a class="nav-card nav-card--<?= Http::escape(trim(preg_replace('/[^a-z0-9]+/','-',strtolower($label)),'-')) ?>" href="<?= Http::escape(AuthController::url($href)) ?>" <?= $isCurrent?'aria-current="page"':'' ?>>
                <span class="module-icon"><?= Workspace::icon($label) ?></span>
                <span class="nav-card-label"><?= \App\Support\I18n::html($label) ?></span>
                <span class="stat-chevron" aria-hidden="true">›</span>
            </a>
            <?php endforeach; ?>
        </section>
        <?php endforeach; ?>
    </div>

    <?php if($admin): ?>
    <div class="dashboard-lower">
        <?php require APP_ROOT.'/app/Views/dashboard-trend.php'; ?>
        <?php require APP_ROOT.'/app/Views/dashboard-activity.php'; ?>
    </div>
    <?php endif; ?>
    <?php if(isset($assignedStock)): ?>
    <div class="mt-4"><?php require APP_ROOT.'/app/Views/rep-stock-dashboard.php'; ?></div>
    <?php endif; ?>
    <?php if(!$admin): ?>
    <p class="small text-secondary mt-4"><?= \App\Support\I18n::html('Offline sales remain provisional until accepted. Register and refresh your device online before offline use.') ?></p>
    <?php endif; ?>
</main>
<?php require APP_ROOT.'/app/Views/workspace-footer.php'; ?>
</body>
</html>
