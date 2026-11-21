'use strict';
/* Server-authoritative promotion benefit modes (PROMO_CHOICE_V1). Only loaded when the server gate is open. */
const tr_invoice_benefit_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

(() => {
 const form=document.getElementById('invoice-form'),mode=document.getElementById('benefit_mode');if(!form||!mode)return;
 const policy=form.querySelector('input[name="benefit_policy"]'),choice=document.getElementById('promotion_discount_choice'),custom=document.getElementById('promotion_discount_percent'),customBox=document.getElementById('promotion-discount-custom'),rateRow=document.getElementById('promotion-discount-rate');
 const legacyBox=document.querySelector('#invoice_discount_choice')?.closest('fieldset'),legacyControls=legacyBox?[...legacyBox.querySelectorAll('select,input')]:[];
 const benefitControls=[policy,choice,custom];
 const summary=document.getElementById('invoice-live-summary');
 const modeLabels={'FREE_PROMOTION':'Free promotion','PROMOTION_DISCOUNT_INSTEAD':'Promotion discount instead','ORDINARY_INVOICE_DISCOUNT':'Ordinary invoice discount'};
 function selection(){
  const active=mode.value!=='',discounted=active&&mode.value!=='FREE_PROMOTION';
  for(const control of benefitControls)if(control)control.disabled=!active;
  for(const control of legacyControls)control.disabled=active;
  if(legacyBox)legacyBox.hidden=active;
  if(rateRow)rateRow.hidden=!discounted;
  if(customBox)customBox.hidden=!discounted||choice.value!=='custom';
  if(custom)custom.required=discounted&&choice.value==='custom';
 }
 mode.addEventListener('change',selection);choice?.addEventListener('change',selection);selection();
 form.addEventListener('pos:totals',event=>{
  const data=event.detail;if(!data?.benefit_mode)return;
  const label=tr_invoice_benefit_js(modeLabels[data.benefit_mode]||data.benefit_mode);
  summary.textContent=data.benefit_mode==='FREE_PROMOTION'
   ?tr_invoice_benefit_js('Benefit: {0} · Final total LKR {1} · Applied LKR {2} · Change LKR {3} · Outstanding LKR {4}. Review before issuing.',{0:label,1:data.grand_total,2:data.applied_amount,3:data.change_amount,4:data.outstanding})
   :tr_invoice_benefit_js('Benefit: {0} {1}% on eligible LKR {2}: LKR {3} · Final total LKR {4} · Applied LKR {5} · Change LKR {6} · Outstanding LKR {7}. Review before issuing.',{0:label,1:data.benefit_rate,2:data.benefit_eligible_subtotal,3:data.benefit_discount_amount,4:data.grand_total,5:data.applied_amount,6:data.change_amount,7:data.outstanding});
 });
})();
