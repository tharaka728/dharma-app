'use strict';
/* Server-authoritative promotion benefit modes (PROMO_CHOICE_V1). Only loaded when the server gate is open. */
const tr_invoice_benefit_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

(() => {
 const form=document.getElementById('invoice-form'),fieldset=document.getElementById('invoice-benefit-fields');if(!form||!fieldset)return;
 const modes=[...fieldset.querySelectorAll('input[name="benefit_mode"]')];
 const policy=form.querySelector('input[name="benefit_policy"]');
 const choices=[...fieldset.querySelectorAll('input[name="promotion_discount_choice"]')];
 const custom=fieldset.querySelector('#promotion_discount_percent'),customBox=fieldset.querySelector('#promotion-discount-custom'),rateRow=fieldset.querySelector('#promotion-discount-rate'),freeInfo=fieldset.querySelector('#free-promotion-info');
 const legacyBox=document.querySelector('#invoice_discount_choice')?.closest('fieldset'),legacyControls=legacyBox?[...legacyBox.querySelectorAll('select,input')]:[];
 const summary=document.getElementById('invoice-live-summary');
 const modeLabels={'FREE_PROMOTION':'Free promotion','PROMOTION_DISCOUNT_INSTEAD':'Promotion discount instead','ORDINARY_INVOICE_DISCOUNT':'Ordinary invoice discount'};
 const current=()=>modes.find(mode=>mode.checked)?.value??'';
 const chosen=()=>choices.find(option=>option.checked)?.value??'';
 function selection(){
  const active=current()!=='',discounted=active&&current()!=='FREE_PROMOTION',ordinary=current()==='ORDINARY_INVOICE_DISCOUNT';
  if(policy)policy.disabled=!active;
  for(const control of [...choices,custom])if(control)control.disabled=!discounted;
  for(const control of legacyControls)control.disabled=active;
  if(legacyBox)legacyBox.hidden=active;
  if(rateRow)rateRow.hidden=!discounted;
  if(freeInfo)freeInfo.hidden=current()!=='FREE_PROMOTION';
  const zeroChip=fieldset.querySelector('[data-rate="0"]');if(zeroChip)zeroChip.hidden=!ordinary;
  if(discounted&&!ordinary&&chosen()==='0'){const fallback=fieldset.querySelector('#promotion_discount_choice_25');if(fallback)fallback.checked=true;}
  if(customBox)customBox.hidden=!discounted||chosen()!=='custom';
  if(custom)custom.required=discounted&&chosen()==='custom';
 }
 for(const mode of modes)mode.addEventListener('change',selection);
 for(const option of choices)option.addEventListener('change',selection);
 selection();
 form.addEventListener('pos:totals',event=>{
  const data=event.detail;if(!data?.benefit_mode)return;
  const label=tr_invoice_benefit_js(modeLabels[data.benefit_mode]||data.benefit_mode);
  summary.textContent=data.benefit_mode==='FREE_PROMOTION'
   ?tr_invoice_benefit_js('Benefit: {0} · Final total LKR {1} · Applied LKR {2} · Change LKR {3} · Outstanding LKR {4}. Review before issuing.',{0:label,1:data.grand_total,2:data.applied_amount,3:data.change_amount,4:data.outstanding})
   :tr_invoice_benefit_js('Benefit: {0} {1}% on eligible LKR {2}: LKR {3} · Final total LKR {4} · Applied LKR {5} · Change LKR {6} · Outstanding LKR {7}. Review before issuing.',{0:label,1:data.benefit_rate,2:data.benefit_eligible_subtotal,3:data.benefit_discount_amount,4:data.grand_total,5:data.applied_amount,6:data.change_amount,7:data.outstanding});
 });
})();
