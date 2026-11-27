<?php if(!empty($record['hybrid']['returns'])): ?>
<ul><?php foreach($record['hybrid']['returns']as$adjustment):$documentPath=$base.'/'.$record['id'].'/hybrid-returns/'.$adjustment['id']; ?>
<li><?= $e(\App\Services\HybridDocuments::text($adjustment['operation']==='VOID'?'Residual void':'Linked return',\App\Support\I18n::locale())) ?> <?= $e(substr($adjustment['id'],0,8)) ?> · LKR <?= $e($adjustment['net_credit']) ?>:
<a href="<?= $url($documentPath.'/receipt?paper=58&document_lang='.\App\Support\I18n::locale()) ?>">58mm</a> · <a href="<?= $url($documentPath.'/receipt?paper=80&document_lang='.\App\Support\I18n::locale()) ?>">80mm</a> · <a href="<?= $url($documentPath.'/a4?document_lang='.\App\Support\I18n::locale()) ?>">A4</a> · <a href="<?= $url($documentPath.'/pdf?document_lang='.\App\Support\I18n::locale()) ?>">PDF</a></li>
<?php endforeach; ?></ul><?php endif; ?>
