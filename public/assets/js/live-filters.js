'use strict';
/* Sinhala display bindings */
const tr_live_filters_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

(() => {
  const main=document.querySelector('#workspace-content main')||document.querySelector('main');
  const form=main?.querySelector('form[method="get" i]');
  if(!form||form.dataset.liveFilters)return;
  form.dataset.liveFilters='1';
  let timer,controller,epoch=0,pending=false;
  const status=document.createElement('p');status.className='small text-secondary';status.setAttribute('role','status');status.setAttribute('aria-live','polite');form.after(status);
  const buttons=[...form.querySelectorAll('button')].filter(b=>(!b.type||b.type==='submit'));
  buttons.forEach(b=>b.hidden=true);if(![...form.querySelectorAll('a')].some(a=>/Clear|Reset/i.test(a.textContent))){const reset=document.createElement('a');reset.href=form.getAttribute('action')||location.pathname;reset.textContent=tr_live_filters_js("Reset Filters");reset.className='btn btn-link';form.append(reset);} // Native form/button remains available without JavaScript.
  let currentMain=main;
  function invalidate(){epoch++;controller?.abort();clearTimeout(timer);}
  function url(){const target=new URL(form.getAttribute('action')||location.pathname,location.href);target.search=new URLSearchParams(new FormData(form)).toString();target.searchParams.delete('page');return target;}
  function datesValid(){
    if(!form.checkValidity()){status.textContent=tr_live_filters_js("Check the filter values before applying.");return false;}
    const from=form.querySelector('[name="from"]'),to=form.querySelector('[name="to"]');
    if(from&&to&&(!from.value||!to.value||from.value>to.value)){status.textContent=tr_live_filters_js("Choose a complete date range with the start on or before the end.");return false;}return true;
  }
  async function update(target,historyMode='push'){
    invalidate();if(!datesValid())return;
    if(form.dataset.locationLoading==='1'){pending=true;return;}
    const mine=epoch;controller=new AbortController();status.textContent=tr_live_filters_js("Updating results…");currentMain.setAttribute('aria-busy','true');
    try{
      const response=await fetch(target,{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'text/html'}});
      if(mine!==epoch)return;
      if(response.status===401||response.status===403||new URL(response.url).pathname!==target.pathname){location.assign(response.url);return;}
      if(!response.ok)throw Error(tr_live_filters_js("Filters were not accepted. Check the selected values."));
      const html=await response.text();if(mine!==epoch)return;
      const doc=new DOMParser().parseFromString(html,'text/html'),next=doc.querySelector('#workspace-content main')||doc.querySelector('main');
      const nextForm=next?.querySelector('form[method="get" i]');if(!next||!nextForm)throw Error(tr_live_filters_js("Unable to update results. Retry or refresh."));
      next.querySelectorAll('script').forEach(script=>script.remove());
      if(form.querySelector('[name="period"]')?.value!=='custom')for(const name of ['from','to']){const original=form.querySelector(`[name="${name}"]`),normalized=nextForm.querySelector(`[name="${name}"]`);if(original&&normalized)original.value=normalized.value;}
      const focused=form.contains(document.activeElement)?document.activeElement:null;
      const selection=focused&&['text','search','tel','url'].includes(focused.type)?[focused.selectionStart,focused.selectionEnd]:null;
      nextForm.replaceWith(form);form.after(status);currentMain.replaceWith(next);currentMain=next;
      if(focused){focused.focus({preventScroll:true});if(selection)focused.setSelectionRange(...selection);}
      document.title=doc.title;
      if(historyMode==='push'&&target.href!==location.href)history.pushState(null,'',target);
      currentMain.removeAttribute('aria-busy');status.textContent=tr_live_filters_js("Results updated.");
      document.dispatchEvent(new CustomEvent('workspace:results'));
    }catch(error){if(mine===epoch&&error.name!=='AbortError'){status.textContent=error.message;buttons.forEach(b=>b.hidden=false);}}
    finally{if(mine===epoch)currentMain.removeAttribute('aria-busy');}
  }
  form.addEventListener('input',event=>{
    const input=event.target;if(!input.name||input.type==='date'||input.tagName==='SELECT')return;
    invalidate();timer=setTimeout(()=>form.requestSubmit(),300);
  });
  form.addEventListener('change',event=>{
    if(!event.target.name)return;invalidate();if(form.dataset.locationLoading==='1'){pending=true;return;}form.requestSubmit();
  });
  form.addEventListener('submit',event=>{if(event.defaultPrevented)return;event.preventDefault();update(url());});
  form.addEventListener('location-filter-ready',()=>{if(pending){pending=false;form.requestSubmit();}});
  document.addEventListener('click',event=>{
    const link=event.target.closest('a[href]');if(!link||!currentMain.contains(link)||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button!==0)return;
    const target=new URL(link.href,location.href);if(target.origin!==location.origin||target.pathname!==location.pathname||target.hash)return;
    if(!target.searchParams.has('page')&&!target.searchParams.has('sort')&&!/^(?:Clear(?: Filters)?|Reset Filters|Reset)$/i.test(link.textContent.trim()))return;
    // Pagination keeps controls and listeners; reset/back rebuild the dependent controls from server HTML.
    if(!target.searchParams.has('page')&&!target.searchParams.has('sort'))return;
    event.preventDefault();update(target);
  });
  window.addEventListener('popstate',()=>location.reload());
})();
