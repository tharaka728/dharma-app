<?php
// Recent activity panel: verifiable current status only, sourced from the same
// authoritative dashboard overview data as the KPI cards. No event log exists,
// so no invented events or timestamps - every row is labeled with its real basis.
use App\Support\{Http,Workspace};
use App\Controllers\AuthController;

$si=\App\Support\I18n::locale()!=='en';
$activityCards=$dashboardToday['cards']??null;
$activityBase=$admin?'/admin':'/rep';
$activityRows=[];
if($activityCards!==null){
    $activityRows=[
        ['icon'=>'Offline Sales','label'=>$si?'විසඳිය යුතු සමමුහුර්ත ඉල්ලීම්':'Unresolved server sync submissions','value'=>(string)($activityCards['Period unresolved server submissions']??'0').' '.($si?'පොරොත්තුවේ':'pending'),'chip'=>'Offline','destination'=>'/reports/offline?period=today'],
        ['icon'=>'Inventory','label'=>$si?'නියෝජිත තොග ඒකක':'Representative stock units','value'=>$si?'ඒකක '.(string)($activityCards['Current representative units']??'0'):'Stock units: '.(string)($activityCards['Current representative units']??'0'),'chip'=>null,'destination'=>'/reports/inventory'],
        ['icon'=>'Invoices','label'=>$si?'අද විකුණුම්':'Today’s net sales','value'=>'LKR '.(string)($activityCards['Operational net sales after returns · LKR']??$activityCards['Period net sales · LKR']??'0.00'),'chip'=>null,'destination'=>'/reports/sales?period=today'],
    ];
}
?>
<section class="dashboard-activity">
    <div class="dashboard-activity-header">
        <h2><svg class="workspace-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9 M13.7 21a2 2 0 0 1-3.4 0"></path></svg> <?= $si?'මෑත ක්‍රියාකාරකම්':'Recent activity' ?></h2>
    </div>
    <?php if($activityRows): ?>
    <p class="dashboard-activity-note"><?= $si?'අද වන විට සජීවී තත්ත්වය':'Live status as of today' ?></p>
    <ul class="dashboard-activity-list">
        <?php foreach($activityRows as $row): ?>
        <li>
            <a class="dashboard-activity-row" href="<?= Http::escape(AuthController::url($activityBase.$row['destination'])) ?>">
                <span class="stat-icon"><?= Workspace::icon($row['icon']) ?></span>
                <span class="dashboard-activity-body">
                    <strong><?= Http::escape($row['label']) ?></strong>
                    <span class="dashboard-activity-detail"><?= Http::escape($row['value']) ?><?php if($row['chip']!==null): ?> <span class="chip chip-amber"><?= implode(' ',array_map(static fn($w)=>'<span class="chip-word">'.$w.'</span>',explode(' ',\App\Support\I18n::html($row['chip'])))) ?></span><?php endif; ?></span>
                </span>
                <span class="dashboard-activity-when"><?= \App\Support\I18n::html('Today') ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p><?= $si?'සජීවී තත්ත්වයක් ලබාගත නොහැක.':'No live status available.' ?></p>
    <?php endif; ?>
</section>
