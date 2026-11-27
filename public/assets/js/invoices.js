'use strict';
/* Sinhala display bindings */
const tr_invoices_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

function filterInvoiceOptions(search, select) {
  const value = search.value.trim().toLocaleLowerCase();
  for (const option of select.options) option.hidden = !!option.value && !option.selected && !option.textContent.toLocaleLowerCase().includes(value);
}
function connectInvoiceForm(document) {
  const form = document.getElementById('invoice-form');
  if (!form) return;
  if(form.dataset.hybridOnline)connectOriginalHybrid(form);
  const lines = document.getElementById('invoice-lines');
  let index = Math.max(0, ...Array.from(lines.querySelectorAll('select')).map(s => Number(s.name.match(/\[(\d+)\]/)?.[1] || 0))) + 1;
  const filter = filterInvoiceOptions;
  document.getElementById('add-invoice-line').addEventListener('click', event => {
    event.preventDefault();
    if (lines.children.length >= 100) return;
    const template = document.getElementById('invoice-line-template');
    const fragment = template.content.cloneNode(true);
    for (const node of fragment.querySelectorAll('[id],[name]')) {
      for (const attr of ['id', 'name']) if (node.hasAttribute(attr)) node.setAttribute(attr, node.getAttribute(attr).replaceAll('__INDEX__', String(index)));
    }
    for (const label of fragment.querySelectorAll('[for]')) label.htmlFor = label.htmlFor.replaceAll('__INDEX__', String(index));
    index++; lines.append(fragment); lines.lastElementChild.querySelector('input').focus();
  });
  lines.addEventListener('click', event => { if (event.target.classList.contains('remove-invoice-line') && lines.children.length > 1) event.target.closest('fieldset').remove(); });
  lines.addEventListener('input', event => { if (event.target.classList.contains('product-search')) filter(event.target, event.target.closest('fieldset').querySelector('select')); });
  const customerSearch = document.getElementById('customer-search');
  customerSearch.addEventListener('input', () => filter(customerSearch, document.getElementById('customer_id')));
  const source = document.getElementById('stock_source');
  if (source) source.addEventListener('change', () => { const button = form.querySelector('button[value="source"]'); form.requestSubmit(button); });
  form.addEventListener('submit', event => {
    if (form.dataset.hybridOnline || event.submitter?.value !== 'issue') return;
    event.submitter.disabled = true;
    // Preserve the intended action when the submit button is disabled.
    const intent = document.createElement('input'); intent.type = 'hidden'; intent.name = 'intent'; intent.value = 'issue'; form.append(intent);
  });
}
if (typeof document !== 'undefined') connectInvoiceForm(document);
if (typeof module !== 'undefined') module.exports = {filterInvoiceOptions, connectInvoiceForm};


/** Original New Invoice policy adapter. Sends intent only; all money/benefits come from the server. */
function connectOriginalHybrid(form) {
 const tr=(s,v={})=>globalThis.DharmaI18n?.t(s,v)??s.replace(/\{([0-9]+)\}/g,(m,k)=>v[k]??m);
 const q=s=>form.querySelector(s),panel=q('[data-invoice-confirmation]'),message=q('#invoice-live-summary');
 const reviewButton=q('button[value="review"]'),confirm=q('[data-hybrid-confirm]'),retry=q('[data-hybrid-retry]');
 const api=new URL(form.action,location.href);api.pathname=api.pathname.replace(/\/rep\/invoices\/new$/, '/api/v1/rep/invoices');
 let epoch=0,timer,aborter,review=null,pending=null,locked=[],inert=[];
 const rows=()=>[...q('#invoice-lines').children].filter(n=>n.querySelector('.invoice-product')?.value).map(node=>({node,id:node.querySelector('.invoice-product').value,quantity:node.querySelector('input[pattern]').value,controls:node._hybridControls||node.querySelector('[data-product-discount]')}));
 const rate=(value)=>{if(!/^(?:0|[1-9][0-9]?)(?:\.[0-9]{1,2})?$/.test(value)||Number(value)>50)throw Error(tr('Enter a percentage from 0 to 50 with at most two decimals.'));return value;};
 const selection=(choice,custom,replacement=false)=>{const allowed=replacement?['25','30','35','custom']:['0','25','30','35','other'];if(!allowed.includes(choice))throw Error(tr('Select an explicit discount option.'));return choice===(replacement?'custom':'other')?{choice,percent:replacement?custom:rate(custom)}:{choice};};
 function payload(){
  const selected=rows();if(!selected.length)throw Error(tr('Add a product before review.'));
  if(!navigator.onLine)throw Error(tr('Hybrid V1 is online only.'));
  const lines=selected.map(r=>{const c=r.controls,replace=c.querySelector('[data-replace-free]').checked;return {product_id:r.id,paid_quantity:r.quantity,product_discount:selection(c.querySelector('[data-discount-choice]').value,c.querySelector('[data-discount-custom]').value),...(replace?{replace_free_entitlement:true}:{})};});
  const invoice=q('#invoice_discount_choice'),h={policy_version:'HYBRID_PROMOTION_V1',lines,invoice_discount_percent:rate(invoice.value==='custom'?q('#invoice_discount_percent').value:invoice.value)};
  if(lines.some(l=>l.replace_free_entitlement))h.replacement_discount=selection(q('#replacement_discount_choice').value,q('#replacement_discount_percent').value,true);
  return {request_id:q('[name="request_id"]').value,customer_id:q('#customer_id').value,payment_type:q('#payment_type').value,amount_received:q('#amount_received').value,csrf_token:q('[name="csrf_token"]').value,hybrid:h};
 }
 function controls(){
  const custom=q('#invoice_discount_choice').value==='custom';q('#invoice-discount-custom').hidden=!custom;q('#invoice_discount_percent').disabled=!custom;
  let replaced=false;for(const r of rows()){const c=r.controls,replace=c.querySelector('[data-replace-free]').checked,choice=c.querySelector('[data-discount-choice]');if(replace)choice.value='0';choice.disabled=replace;const other=!replace&&choice.value==='other';c.querySelector('[data-discount-custom-wrap]').hidden=!other;c.querySelector('[data-discount-custom]').disabled=!other;replaced ||= replace;}
  q('[data-replacement]').hidden=!replaced;const rc=replaced&&q('#replacement_discount_choice').value==='custom';q('#replacement-custom-wrap').hidden=!rc;q('#replacement_discount_percent').disabled=!rc;
  reviewButton.disabled=!rows().length||!q('#customer_id').value;
 }
 function invalidate(){if(pending)return;epoch++;aborter?.abort();clearTimeout(timer);review=null;panel.hidden=true;delete panel.dataset.reviewHash;form.dataset.commandState='editing';confirm.disabled=true;form.dispatchEvent(new Event('pos:invalid'));controls();for(const r of rows()){r.controls.querySelector('[data-verified-line]').replaceChildren();r.controls.querySelector('[data-replace-free]').disabled=true;}
  timer=setTimeout(()=>verify(false),300);
 }
 function notify(text,error=false){message.textContent=text;message.setAttribute('role',error?'alert':'status');}
 async function request(suffix,body,signal){if(!navigator.onLine)throw Error(tr('Hybrid V1 is online only.'));const response=await fetch(api.href+suffix,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:JSON.stringify(body),signal});const data=await response.json();if(!response.ok)throw Object.assign(Error(Object.values(data.errors||{}).join(' ')||tr('Request could not be completed.')),{status:response.status});return data;}
 function facts(parent,entries){const dl=document.createElement('dl');dl.className='row mb-0';for(const [label,value]of entries){const dt=document.createElement('dt'),dd=document.createElement('dd');dt.className='col-7';dd.className='col-5';dt.textContent=tr(label);dd.textContent=String(value);dl.append(dt,dd);}parent.replaceChildren(dl);}
 function show(result,explicit){const c=result.calculation;form.dispatchEvent(new CustomEvent('pos:totals',{detail:result}));
  for(const line of c.lines){const r=rows().find(r=>r.id===line.product_id);if(r)facts(r.controls.querySelector('[data-verified-line]'),[['Paid quantity',line.paid_quantity],['Qualified paid quantity',line.qualifying_paid_quantity],['Discount-eligible remainder',line.discount_eligible_quantity],['Actual free quantity',line.actual_free_quantity],['Product discount',line.product_discount_amount],['Replacement discount',line.replacement_discount_amount],['Invoice discount',line.invoice_discount_amount],['Payable',line.payable_amount]]);}
  notify(tr('Server-verified payable: LKR {0}',{0:c.final_payable_total}));
  if(explicit){review=result;panel.dataset.reviewHash=result.review_hash;panel.hidden=false;form.dataset.commandState='reviewed';facts(q('[data-hybrid-review]'),[['Customer',result.customer.shop_name],['Original paid subtotal',c.original_paid_subtotal],['Product discount',c.product_discount_total],['Replacement discount',c.replacement_discount_total],['Invoice discount',c.invoice_discount_amount],['Payable',c.final_payable_total],['Applied payment',result.applied_amount],['Change',result.change_amount],['Outstanding',result.outstanding]]);q('[data-posting-hold]').hidden=form.dataset.posting==='1';confirm.disabled=form.dataset.posting!=='1';}
 }
 async function verify(explicit){if(pending)return;clearTimeout(timer);aborter?.abort();aborter=new AbortController();const mine=epoch;controls();if(!q('#customer_id').value||!rows().length)return;
  try{const body=payload();notify(tr('Calculating current prices and discounts...'));const discovery=await request('/hybrid/eligibility',body,aborter.signal);if(mine!==epoch)return;
   for(const l of discovery.lines){const r=rows().find(r=>r.id===l.product_id);if(r){r.controls.querySelector('[data-replacement-line]').hidden=!l.replacement_supported;r.controls.querySelector('[data-replace-free]').disabled=!l.replacement_supported;}}
   const result=await request('/preview',body,aborter.signal);if(mine!==epoch)return;show(result,explicit);
  }catch(e){if(mine!==epoch||e.name==='AbortError')return;review=null;panel.hidden=true;confirm.disabled=true;form.dispatchEvent(new Event('pos:invalid'));notify(e.message,true);}
 }
 function lock(){locked=[...form.querySelectorAll('input,select,textarea,button')].filter(n=>n!==confirm&&n!==retry).map(n=>[n,n.disabled]);for(const [n]of locked)n.disabled=true;inert=[q('.pos-grid')?.closest('section'),q('.pos-customer')].filter(Boolean);for(const n of inert)n.inert=true;confirm.disabled=true;}
 function unlock(){for(const [n,was]of locked)n.disabled=was;for(const n of inert)n.inert=false;locked=[];inert=[];}
 async function issue(){
  if(!pending){if(!review||form.dataset.posting!=='1')return;try{pending={...payload(),review_hash:review.review_hash};}catch(e){notify(e.message,true);return;}lock();}
  retry.hidden=true;retry.disabled=true;form.dataset.commandState='dispatched';
  try{const result=await request('',pending);if(!result.invoice_id)throw Error(tr('Outcome unavailable. Retry the same request.'));form.dataset.commandState='succeeded';form.dataset.invoiceId=result.invoice_id;pending=null;notify(tr('Invoice issued.'));location.assign(form.action.replace(/\/new$/, '/')+result.invoice_id);
  }catch(e){if(e.status&&e.status<500){pending=null;unlock();invalidate();notify(e.message,true);}else{form.dataset.commandState='unknown';retry.hidden=false;retry.disabled=false;notify(tr('Outcome unavailable. Retry the same request.'),true);}}
 }
 form.addEventListener('input',invalidate);form.addEventListener('change',invalidate);form.addEventListener('pos:ready',invalidate);
 form.addEventListener('submit',e=>{e.preventDefault();if(!pending)verify(true);});confirm.addEventListener('click',issue);retry.addEventListener('click',issue);
 window.addEventListener('offline',()=>{if(!pending){invalidate();notify(tr('Hybrid V1 is online only.'),true);}});
 window.addEventListener('beforeunload',e=>{if(pending){e.preventDefault();e.returnValue='';}});
 document.addEventListener('click',e=>{if(pending&&e.target.closest('a')){e.preventDefault();notify(tr('Outcome unavailable. Retry the same request.'),true);}},true);
 form.addEventListener('click',e=>{if(e.target.closest('#add-invoice-line,.remove-invoice-line'))queueMicrotask(invalidate);});
 controls();
}
