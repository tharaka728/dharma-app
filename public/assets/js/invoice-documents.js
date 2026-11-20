'use strict';
/* Sinhala display bindings */
const tr_invoice_documents_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

(()=>{
 const message=document.querySelector('[data-document-message]');
 const tell=text=>{if(message)message.textContent=tr_invoice_documents_js(text);};
 document.querySelectorAll('[data-print-document]').forEach(button=>button.addEventListener('click',()=>{if(!navigator.onLine){tell('Reconnect to open a verified invoice document. Offline final-invoice printing is unavailable.');return;}window.print();}));
 document.querySelectorAll('[data-share-pdf]').forEach(button=>{
  if(!navigator.share||!navigator.canShare||!window.File)return;
  button.hidden=false;let file=null;
  window.addEventListener('pagehide',()=>{file=null;button.textContent=tr_invoice_documents_js("Prepare PDF to Share");});
  button.addEventListener('click',async()=>{
   try{
    if(!navigator.onLine)throw Error(tr_invoice_documents_js("Reconnect before preparing an invoice PDF."));
    if(file){await navigator.share({files:[file],title:'Dharma Chemical invoice'});return;}
    button.disabled=true;tell('Preparing a private PDF. No invoice data is uploaded to a sharing service.');
    const response=await fetch(button.dataset.sharePdf,{credentials:'same-origin',cache:'no-store',redirect:'error'});
    if(!response.ok||!response.headers.get('Content-Type')?.startsWith('application/pdf'))throw Error(tr_invoice_documents_js("Sign in again or use Download PDF."));
    const blob=await response.blob();const name=response.headers.get('Content-Disposition')?.match(/filename="([A-Za-z0-9_.-]+)"/)?.[1]||'Dharma-invoice.pdf';file=new File([blob],name,{type:'application/pdf'});
    if(!navigator.canShare({files:[file]})){file=null;throw Error(tr_invoice_documents_js("File sharing is unavailable in this browser. Use Download PDF."));}
    button.textContent=tr_invoice_documents_js("Share PDF now");tell('PDF ready. Tap Share PDF now to choose a recipient. Download PDF is also available.');
   }catch(error){if(error.name!=='AbortError')tell(error.message||'Use Download PDF.');}finally{button.disabled=false;}
  });
 });
 // A restored browser page must reauthorize before displaying a private document.
 window.addEventListener('pageshow',event=>{if(event.persisted)location.reload();});
})();
