<?php
use App\Support\Http;use App\Services\Customers;
/* Customer profile presentation only. Expects $customer,$id,$csrf,$url,$billingRepresentatives,$historicalReturns from customers.php. */
$cpT=static fn(string $k):string=>\App\Support\I18n::html($k);
$cpIcons=[
'store'=>'<path d="M4 9l1.5-5h13L20 9M4 9v2a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0V9M5 13.5V20h14v-6.5M10 20v-4h4v4"/>',
'user'=>'<circle cx="12" cy="8" r="3.6"/><path d="M5 20c.6-4 3.4-6 7-6s6.4 2 7 6"/>',
'phone'=>'<path d="M6.5 3.5h3l1.5 4-2 1.5a11 11 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2 2A16 16 0 0 1 4.5 5.5a2 2 0 0 1 2-2Z"/>',
'pin'=>'<path d="M12 21s6-5.6 6-10.5A6 6 0 0 0 6 10.5C6 15.4 12 21 12 21Z"/><circle cx="12" cy="10.5" r="2.2"/>',
'tag'=>'<path d="M3.5 12.5V4.5h8l9 9-8 8-9-9Z"/><circle cx="8" cy="9" r="1.3"/>',
'map'=>'<path d="M3.5 6l5.5-2 6 2 5.5-2v14l-5.5 2-6-2-5.5 2V6ZM9 4v14M15 6v14"/>',
'doc'=>'<path d="M6 3.5h8l4 4V20.5H6V3.5ZM14 3.5v4h4M9 12h6M9 15.5h6"/>',
'brief'=>'<rect x="3.5" y="7.5" width="17" height="12" rx="2"/><path d="M9 7.5v-2h6v2M3.5 13h17"/>',
'gear'=>'<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.2M12 18.3v2.2M3.5 12h2.2M18.3 12h2.2M6 6l1.6 1.6M16.4 16.4L18 18M6 18l1.6-1.6M16.4 7.6L18 6"/>',
'rep'=>'<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.5-3.4 2.8-5 5.5-5s5 1.6 5.5 5M16 6.5a3 3 0 0 1 0 5.5M17.5 14.5c1.8.6 2.7 2 3 4.5"/>',
'card'=>'<rect x="3" y="6" width="18" height="12.5" rx="2"/><path d="M3 10.5h18M6.5 15h4"/>',
'cal'=>'<rect x="4" y="5.5" width="16" height="15" rx="2"/><path d="M4 10h16M8.5 3.5v4M15.5 3.5v4"/>',
'coins'=>'<ellipse cx="10" cy="6.5" rx="5.5" ry="2.5"/><path d="M4.5 6.5v5c0 1.4 2.5 2.5 5.5 2.5s5.5-1.1 5.5-2.5v-5M4.5 11.5v5c0 1.4 2.5 2.5 5.5 2.5M15.5 12c3 0 4.5-1 4.5-2.2V15c0 1.3-2 2.4-5 2.5"/>',
'warn'=>'<path d="M12 4L2.8 20h18.4L12 4ZM12 10v4.5M12 17.2v.1"/>',
'plus'=>'<path d="M12 5v14M5 12h14"/>',
'pencil'=>'<path d="M4 20l1-4L16.5 4.5a2 2 0 0 1 3 3L8 19l-4 1ZM14.5 6.5l3 3"/>',
];
/* Reuse the shared Workspace::icon() set where it has a matching glyph; the rest use the local paths above. */
$cpShared=['user'=>'Customers','rep'=>'Sales Representatives','doc'=>'Invoices','tag'=>'Promotions','store'=>'Inventory'];
$cpIcon=static function(string $n) use ($cpIcons,$cpShared):string{
    if(isset($cpShared[$n])){return str_replace('class="workspace-icon"','class="workspace-icon cp-icon"',\App\Support\Workspace::icon($cpShared[$n]));}
    return '<svg class="cp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.($cpIcons[$n]??'').'</svg>';
};
$cpVal=static fn(string $key):string=>Http::escape((string)($customer[$key]?:\App\Support\I18n::text('Not provided')));
$cpActive=(int)$customer['is_active']===1;
$cpType=\App\Support\I18n::html(Customers::TYPES[$customer['customer_type']]);
$cpRow=static fn(string $icon,string $label,string $valueHtml):string=>'<div class="cp-row"><dt>'.$cpIcon($icon).'<span>'.\App\Support\I18n::html($label).'</span></dt><dd>'.$valueHtml.'</dd></div>';
?>
<div class="cp-page">
<section class="cp-summary" aria-labelledby="cp-name">
 <div class="cp-identity"><span class="cp-avatar"><?= $cpIcon('store') ?></span><div class="cp-identity-text"><h2 id="cp-name" class="cp-name"><?= Http::escape($customer['shop_name']) ?></h2><p class="cp-meta"><span class="cp-meta-label"><?= $cpT('Customer code') ?>:</span> <strong><?= Http::escape((string)$customer['customer_code']) ?></strong> <span class="chip <?= $cpActive?'chip-green':'chip-gray' ?> cp-status"><?= $cpActive?$cpT('Active'):$cpT('Inactive') ?></span></p><p class="cp-meta"><span class="cp-meta-label"><?= $cpT('Customer type') ?>:</span> <?= $cpType ?></p></div></div>
 <dl class="cp-glance"><?php
  echo $cpRow('user','Contact person',$cpVal('contact_person'));
  echo $cpRow('phone','Primary phone',$cpVal('phone'));
  echo $cpRow('phone','Alternate phone',$cpVal('alternate_phone'));
  echo $cpRow('pin','Address',$cpVal('address'));
 ?></dl>
 <nav class="cp-actions" aria-label="<?= $cpT('Customer') ?>"><a class="btn btn-primary" href="<?= $url('/admin/invoices/new') ?>"><?= $cpIcon('plus') ?><span><?= $cpT('New Invoice') ?></span></a><a class="btn btn-outline-primary" href="<?= $url('/admin/invoices?customer_id='.$id) ?>"><?= $cpIcon('doc') ?><span><?= $cpT('Invoice history') ?></span></a><a class="btn btn-outline-primary" href="<?= $url('/admin/customers/'.$id.'/edit') ?>"><?= $cpIcon('pencil') ?><span><?= $cpT('Edit customer') ?></span></a></nav>
</section>
<div class="cp-grid">
<section class="cp-card" aria-labelledby="cp-contact-h"><h2 id="cp-contact-h" class="cp-card-title"><?= $cpIcon('user') ?><span><?= $cpT('Contact Details') ?></span></h2><dl class="cp-rows"><?php
  echo $cpRow('user','Contact person',$cpVal('contact_person'));
  echo $cpRow('phone','Primary phone',$cpVal('phone'));
  echo $cpRow('phone','Alternate phone',$cpVal('alternate_phone'));
  echo $cpRow('pin','Address',$cpVal('address'));
?></dl></section>
<section class="cp-card" aria-labelledby="cp-business-h"><h2 id="cp-business-h" class="cp-card-title"><?= $cpIcon('brief') ?><span><?= $cpT('Business Details') ?></span></h2><dl class="cp-rows"><?php
  echo $cpRow('tag','Customer code',Http::escape((string)$customer['customer_code']));
  echo $cpRow('store','Customer type',$cpType);
  echo $cpRow('pin','City / town',$cpVal('city_town'));
  echo $cpRow('map','District',$cpVal('district'));
  echo $cpRow('doc','Notes',$cpVal('notes'));
?></dl></section>
<section class="cp-card" aria-labelledby="cp-billing-h"><h2 id="cp-billing-h" class="cp-card-title"><?= $cpIcon('gear') ?><span><?= $cpT('Billing Settings') ?></span></h2>
<form method="post" action="<?= $url('/admin/invoices/customer-settings') ?>" class="cp-billing-form"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="customer_id" value="<?= Http::escape((string)($billingCustomer['id']??$id)) ?>"><input type="hidden" name="version" value="<?= Http::escape((string)$billingCustomer['version']) ?>">
<div class="cp-rows">
<div class="cp-row"><div class="cp-label"><?= $cpIcon('rep') ?><label for="assigned_rep_id"><?= $cpT('Assigned sales representative') ?></label></div><div class="cp-value"><select class="form-select" name="assigned_rep_id" id="assigned_rep_id"><option value=""><?= $cpT('Unassigned (admin access only)') ?></option><?php foreach($billingRepresentatives as $r): if(!(int)$r['is_active']&&(string)($billingCustomer['assigned_rep_id']??'')!==(string)$r['id'])continue; ?><option value="<?= Http::escape((string)$r['id']) ?>" <?= (string)($billingCustomer['assigned_rep_id']??'')===(string)$r['id']?'selected':'' ?>><?= Http::escape($r['name'].(!(int)$r['is_active']?\App\Support\I18n::text(' (inactive — choose another representative)'):'')) ?></option><?php endforeach; ?></select></div></div>
<div class="cp-row"><div class="cp-label"><?= $cpIcon('card') ?><label for="allow_credit_sales"><?= $cpT('Allow Credit Sales') ?></label></div><div class="cp-value"><select class="form-select" name="allow_credit_sales" id="allow_credit_sales"><option value="0" <?= !(int)($billingCustomer['allow_credit_sales']??0)?'selected':'' ?>><?= $cpT('Disabled') ?></option><option value="1" <?= (int)($billingCustomer['allow_credit_sales']??0)?'selected':'' ?>><?= $cpT('Enabled') ?></option></select></div></div>
<div class="cp-row"><div class="cp-label"><?= $cpIcon('cal') ?><span><?= $cpT('Credit terms') ?></span></div><div class="cp-value"><?= Http::escape((string)$customer['credit_terms_days']) ?> <?= $cpT('days') ?></div></div>
<div class="cp-row"><div class="cp-label"><?= $cpIcon('coins') ?><span><?= $cpT('Credit limit (LKR)') ?></span></div><div class="cp-value"><?= Http::escape($customer['credit_limit']??\App\Support\I18n::text('Not configured')) ?></div></div>
</div>
<p class="cp-hint"><?= $cpT('Saved separately from contact details. Reassignment immediately changes representative access. Credit is disabled unless explicitly enabled; a blank credit limit is unlimited and an explicit 0.00 permits no outstanding credit.') ?></p>
<button class="btn btn-primary cp-save"><?= $cpT('Save billing settings') ?></button></form></section>
</div>
<p class="cp-note"><a class="cp-back" href="<?= $url('/admin/customers') ?>"><?= $cpT('Customers') ?></a> <span><?= $cpT('Use Invoice history to review issued invoices and outstanding amounts.') ?></span></p>
<?php if($historicalReturns): ?><section class="card card-body mb-4"><h2 class="h5"><?= \App\Support\I18n::locale()==='en'?'Historical Return History':'පැරණි භාණ්ඩ ආපසු භාරගැනීමේ ඉතිහාසය' ?></h2><p class="small"><?= \App\Support\I18n::locale()==='en'?'Transaction totals repeat for each returned line.':'එක් එක් භාණ්ඩ පේළියේ ගනුදෙනු එකතු නැවත පෙන්වයි.' ?></p><?php foreach($historicalReturns as $hr): ?><div class="border-bottom py-2"><a href="<?= $url('/admin/invoices/'.$hr['invoice_id']) ?>"><?= Http::escape($hr['invoice_number']) ?></a> · <?= Http::escape($hr['business_date'].' · '.$hr['representative'].' · '.$hr['validity']) ?><br><?= Http::escape($hr['sku'].' · '.\App\Support\I18n::product($hr).' · '.$hr['price_year'].' · '.$hr['quantity'].' × '.$hr['unit_price'].' LKR · '.$hr['disposition']) ?><br><?= Http::escape('Approved '.$hr['approved_value'].' · Applied '.$hr['applied_value'].' · Forfeited '.$hr['forfeited_value'].' LKR') ?></div><?php endforeach; ?><p class="small mt-2">First 100 product lines. <a href="<?= $url('/admin/reports/returns?'.http_build_query(['period'=>'custom','from'=>'1900-01-01','to'=>'2099-12-31','customer_id'=>$id])) ?>">View full return report</a></p></section><?php endif; ?>
<section class="cp-danger <?= $cpActive?'':'cp-reactivate' ?>" aria-labelledby="cp-danger-h"><span class="cp-danger-icon"><?= $cpIcon($cpActive?'warn':'store') ?></span><div class="cp-danger-text"><h2 id="cp-danger-h"><?= $cpActive?$cpT('Deactivate customer'):$cpT('Reactivate customer') ?></h2><p><?= $cpT('Customer IDs, code history and all historical relationships are preserved. No records are deleted.') ?></p></div><form method="post" action="<?= $url('/admin/customers/'.$id.'/status') ?>"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="version" value="<?= Http::escape((string)$customer['version']) ?>"><input type="hidden" name="is_active" value="<?= $cpActive?'0':'1' ?>"><button class="btn btn-outline-<?= $cpActive?'danger':'success' ?>"><?= $cpActive?$cpT('Confirm deactivation'):$cpT('Reactivate customer') ?></button></form></section>
</div>
