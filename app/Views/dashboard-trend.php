<?php use App\Support\{Http,Workspace};use App\Controllers\AuthController; ?>
<section class="dashboard-chart">
    <div class="dashboard-chart-header">
        <h2><?= Workspace::icon('Reports') ?> <?= $si?'විකුණුම් සාරාංශය':'Sales overview' ?></h2>
        <a href="<?= Http::escape(AuthController::url($base.'/reports/sales')) ?>"><?= $si?'වාර්තාව බලන්න':'View report' ?> ›</a>
    </div>
    <p class="chart-period-pill"><?= $si?'පසුගිය දින 7 · වලංගු ඉන්වොයිසි':'Last 7 days · valid invoices' ?></p>
    <?php if($dashboardTrend!==null): ?>
    <?php
        $chartRows=[];
        foreach($dashboardTrend['rows'] as $chartRow)$chartRows[$chartRow['period_start']]=$chartRow;
        $chartMax=0.0;
        foreach($chartRows as $chartRow)$chartMax=max($chartMax,(float)$chartRow['operational_net_after_returns_lkr']);
        $chartDay=new DateTimeImmutable('today',new DateTimeZone('Asia/Colombo'));
        $compactMoney=static function(string $value):string{
            $n=(float)$value;
            if(abs($n)>=1000){
                $k=$n/1000;
                return (abs($k)>=100?(string)(int)round($k):rtrim(rtrim(number_format($k,1,'.',''),'0'),'.')).'K';
            }
            return number_format($n,0,'.','');
        };
    ?>
    <?php
        $axisStepCandidates=[100,250,500,1000,2500,5000,10000,25000,50000,100000,250000,500000,1000000,2500000,5000000,10000000];
        $axisStep=end($axisStepCandidates);
        foreach($axisStepCandidates as $candidate)if($chartMax<=$candidate*6){$axisStep=$candidate;break;}
        $axisMax=$axisStep*6;
    ?>
    <div class="dashboard-chart-plot">
        <div class="dashboard-axis" aria-hidden="true">
            <?php for($level=6;$level>=0;$level--): ?>
            <span><?= Http::escape($compactMoney((string)($axisStep*$level))) ?></span>
            <?php endfor; ?>
        </div>
        <div class="dashboard-bars" role="list" aria-label="<?= $si?'දිනපතා විකුණුම්':'Daily sales' ?>">
        <?php for($i=6;$i>=0;$i--):
            $d=$chartDay->modify('-'.$i.' days');
            $value=(string)($chartRows[$d->format('Y-m-d')]['operational_net_after_returns_lkr']??'0.00');
            $height=$axisMax>0?max(0,(float)$value/$axisMax*100):0;
        ?>
        <div class="dashboard-bar" role="listitem">
            <span class="dashboard-bar-value" title="LKR <?= Http::escape($value) ?>"><?= Http::escape($compactMoney($value)) ?></span>
            <svg class="dashboard-bar-track" viewBox="0 0 36 100" preserveAspectRatio="none" aria-hidden="true"><rect x="3" y="<?= 100-(int)$height ?>" width="30" height="<?= (int)$height ?>" rx="3" /></svg>
            <span><?= Http::escape($d->format('d M')) ?></span>
        </div>
        <?php endfor; ?>
        </div>
    </div>
    <?php else: ?>
    <p><?= $si?'විකුණුම් දත්ත ලබාගත නොහැක.':'Sales data is unavailable.' ?></p>
    <?php endif; ?>
</section>
