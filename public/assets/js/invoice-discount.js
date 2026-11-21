'use strict';
/* Sinhala display bindings */
const tr_invoice_discount_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

(() => {
 const form=document.getElementById('invoice-form'),choice=document.getElementById('invoice_discount_choice');if(!form||!choice)return;
 const rate=document.getElementById('invoice_discount_percent'),custom=document.getElementById('invoice-discount-custom'),summary=document.getElementById('invoice-live-summary');
 let timer,controller,epoch=0;
 function selection(){custom.hidden=choice.value!=='custom';rate.disabled=custom.hidden;rate.required=!custom.hidden;}
 function invalidate(){form.dispatchEvent(new Event('pos:invalid'));epoch++;controller?.abort();clearTimeout(timer);form.querySelector('[data-invoice-confirmation]')?.remove();form.querySelector('[data-return-settlement-review]')?.remove();}
 async function preview(){
  const returnPanel=document.getElementById('historical-return');if(returnPanel?.dataset.pricesReady==='0'){summary.textContent=tr_invoice_discount_js('Loading approved return prices.');return;}
  if(!form.checkValidity()){summary.textContent=tr_invoice_discount_js("Complete the customer, product, quantity and discount fields to calculate totals.");return;}
  const mine=epoch;controller=new AbortController();summary.textContent=tr_invoice_discount_js("Calculating current prices and discounts…");
  try{
   const endpoint=new URL(form.action);endpoint.pathname=endpoint.pathname.replace(/\/(admin|rep)\/invoices\/new$/, '/api/v1/$1/invoices/preview');
   const response=await fetch(endpoint,{method:'POST',body:new FormData(form),credentials:'same-origin',cache:'no-store',signal:controller.signal});const data=await response.json();if(mine!==epoch)return;
   if(!response.ok)throw Error(Object.values(data.errors||{}).join(' ')||tr_invoice_discount_js("Unable to calculate. Sign in or refresh."));
   form.dispatchEvent(new CustomEvent('pos:totals',{detail:data}));
   // Benefit-mode previews are summarized by invoice-benefit.js from the same event.
   if(!data.benefit_mode)summary.textContent=tr_invoice_discount_js("After promotions LKR {0} · Invoice Discount {1}%: LKR {2} · Final total LKR {3} · Applied LKR {4} · Change LKR {5} · Outstanding LKR {6}. Review before issuing.",{0:(data.discountable_subtotal),1:(data.invoice_discount_percent),2:(data.invoice_discount_amount),3:(data.grand_total),4:(data.applied_amount),5:(data.change_amount),6:(data.outstanding)});
  }catch(error){if(mine===epoch&&error.name!=='AbortError')summary.textContent=error.message;}
 }
 function changed(){selection();invalidate();timer=setTimeout(preview,300);}
 selection();form.addEventListener('input',changed);form.addEventListener('change',changed);form.addEventListener('click',event=>{if(event.target.closest('.remove-invoice-line,#add-invoice-line'))changed();});
})();
