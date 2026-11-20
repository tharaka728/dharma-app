'use strict';
/* Sinhala display bindings */
const tr_customer_location_filters_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

function createCustomerLocationFilters(doc, request) {
    const get=id=>doc.getElementById(id), root=get('customer-location-filters');
    if(!root) return;
    const district=get('filter-district'),town=get('filter-town'),ds=get('filter-district-search'),ts=get('filter-town-search');
    const more=get('filter-town-more'),status=get('filter-town-status'),form=root.closest('form');
    const districts=Array.from(district.options).filter(o=>o.value).map(o=>({id:o.value,name:o.textContent}));
    let rows=new Map(Array.from(town.options).filter(o=>o.value).map(o=>[o.value,{id:o.value,district_id:o.dataset.districtId,label:o.textContent}]));
    let page=1,epoch=0,controller=null,timer=null,loading=false,failed=false;
    function option(row){const o=doc.createElement('option');o.value=String(row.id);o.textContent=row.label||`${row.name} — ${row.district_name}`;o.dataset.districtId=String(row.district_id);return o;}
    function render(selected){const all=doc.createElement('option');all.value='';all.textContent=tr_customer_location_filters_js("All cities / towns");town.replaceChildren(all);for(const row of rows.values())town.appendChild(option(row));town.value=rows.has(selected)?selected:'';}
    function cancel(){epoch++;if(controller)controller.abort();}
    async function load(append=false){
        cancel();const run=epoch,d=district.value,selected=town.value,retained=rows.get(selected),next=append?page+1:1;
        controller=new AbortController();if(form.dataset)form.dataset.locationLoading='1';loading=true;failed=false;more.disabled=true;status.textContent=tr_customer_location_filters_js("Loading towns…");
        try{
            const query=new URLSearchParams({district_id:d,scope:'all',q:ts.value.trim(),page:String(next)});
            const response=await request(root.dataset.townsUrl+'?'+query,{credentials:'same-origin',headers:{Accept:'application/json'},signal:controller.signal});
            const data=await response.json();if(run!==epoch||d!==district.value)return;
            if(!response.ok)throw new Error(response.status===401?tr_customer_location_filters_js("Your session expired. Sign in and refresh."):Object.values(data.errors||{}).join(' ')||tr_customer_location_filters_js("Unable to load towns."));
            const result=append?new Map(rows):new Map();for(const row of data.towns)result.set(String(row.id),row);
            if(retained)result.set(selected,retained);rows=result;render(selected);page=next;more.hidden=!data.has_more;
            status.textContent=tr_customer_location_filters_js("{0} matching towns. Choose a town or All cities / towns, results update automatically.",{0:(data.total)});
        }catch(error){if(run!==epoch)return;failed=true;status.textContent=error.message+tr_customer_location_filters_js(" Type to retry, or select All cities / towns.");}
        finally{if(run===epoch){loading=false;more.disabled=false;if(form.dataset)form.dataset.locationLoading='0';if(doc.defaultView?.CustomEvent)form.dispatchEvent(new doc.defaultView.CustomEvent('location-filter-ready'));}}
    }
    district.addEventListener('change',()=>{if(form.dataset)form.dataset.locationLoading='1';
        clearTimeout(timer);cancel();const selected=town.value;
        rows=new Map([...rows].filter(([,row])=>!district.value||String(row.district_id)===district.value));render(selected);
        ts.value='';more.hidden=true;return load();
    });
    ds.addEventListener('input',()=>{const selected=district.value,q=ds.value.trim().toLowerCase(),all=doc.createElement('option');all.value='';all.textContent=tr_customer_location_filters_js("All districts");district.replaceChildren(all);for(const row of districts){if(row.name.toLowerCase().includes(q)||row.id===selected){const o=doc.createElement('option');o.value=row.id;o.textContent=row.name;district.appendChild(o);}}district.value=selected;});
    ts.addEventListener('input',()=>{clearTimeout(timer);cancel();loading=true;more.disabled=true;timer=setTimeout(()=>load(),250);});
    more.addEventListener('click',()=>load(true));
    for(const [input,select] of [[ds,district],[ts,town]])input.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();select.focus();}});
    form.addEventListener('submit',event=>{if(loading||(failed&&town.value)){event.preventDefault();status.textContent=loading?tr_customer_location_filters_js("Wait for towns to finish loading, then press Filter."):tr_customer_location_filters_js("Retry town search or choose All cities / towns before filtering.");}});
    return {load};
}
if(typeof module!=='undefined'&&module.exports)module.exports={createCustomerLocationFilters};
if(typeof document!=='undefined')createCustomerLocationFilters(document,fetch);
