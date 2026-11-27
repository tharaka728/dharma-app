<?php
use App\Support\{Http,Workspace};
use App\Controllers\AuthController;

$si=\App\Support\I18n::locale()!=='en';
$base=$admin?'/admin':'/rep';
$cards=$dashboardToday['cards']??null;

$sales=$cards['Operational net sales after returns · LKR']??$cards['Period net sales · LKR']??null;

$metrics=[
    ['label'=>$si?'අද විකුණුම්':'Today’s net sales','value'=>$sales,'icon'=>'Invoices','destination'=>'/reports/sales?period=today','money'=>true,'chip'=>null],
    ['label'=>$si?'අද ලබාදුන් වට්ටම්':'Total discounts given','value'=>$cards['Period discounts given · LKR']??null,'icon'=>'Promotions','destination'=>'/reports/sales?period=today','money'=>true,'chip'=>null],
    ['label'=>$si?'විසඳිය යුතු සමමුහුර්ත ඉල්ලීම්':'Unresolved server sync submissions','value'=>$cards['Period unresolved server submissions']??null,'icon'=>'Offline Sales','destination'=>'/reports/offline?period=today','money'=>false,'chip'=>'Offline'],
    ['label'=>$si?'නියෝජිත තොග ඒකක':'Representative stock units','value'=>$cards['Current representative units']??null,'icon'=>'Inventory','destination'=>'/reports/inventory','money'=>false,'chip'=>null],
];
?>
<div class="dashboard-metrics">
    <?php foreach($metrics as $metric):
        $destination=$admin
            ?$base.$metric['destination']
            :match($metric['destination']){
                '/reports/sales?period=today','/reports/outstanding'=>'/rep/invoices',
                '/reports/offline?period=today'=>'/offline/index.html#status',
                '/reports/inventory'=>'/rep/stock',
            };
    ?>
    <a class="stat-card" href="<?= Http::escape(AuthController::url($destination)) ?>">
        <span class="stat-icon"><?= Workspace::icon($metric['icon']) ?></span>
        <span class="stat-body">
            <span class="stat-label"><?= Http::escape($metric['label']) ?></span>
            <strong class="stat-value"><?= $metric['value']===null?($si?'ලබාගත නොහැක':'Unavailable'):($metric['money']?'LKR ':'').Http::escape((string)$metric['value']) ?></strong>
            <?php if($metric['chip']!==null): ?><span class="chip chip-amber"><?= implode(' ',array_map(static fn($w)=>'<span class="chip-word">'.$w.'</span>',explode(' ',\App\Support\I18n::html($metric['chip'])))) ?></span><?php endif; ?>
        </span>
        <span class="stat-chevron" aria-hidden="true">›</span>
    </a>
    <?php endforeach; ?>
</div>
