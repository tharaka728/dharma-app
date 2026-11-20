'use strict';
/* Sinhala display bindings */
const tr_reports_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

(()=>{
 const period=document.getElementById('period');
 for(const id of ['from','to'])document.getElementById(id)?.addEventListener('change',()=>{period.value='custom';});
 function bindVisibleSearch(){const search=document.getElementById('report-table-search');if(!search||search.dataset?.reportSearchAttached)return;if(search.dataset)search.dataset.reportSearchAttached='1';search.addEventListener('input',event=>{
  const query=event.target.value.trim().toLocaleLowerCase();
  for(const row of document.querySelectorAll('#report-table tbody tr'))row.hidden=!row.textContent.toLocaleLowerCase().includes(query);
 });}
 bindVisibleSearch();document.addEventListener?.('workspace:results',bindVisibleSearch);
})();
