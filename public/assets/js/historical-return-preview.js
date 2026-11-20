(()=>{
 'use strict';
 const root=document.getElementById('historical-return-preview');if(!root)return;
 const panel=root.querySelector('[data-preview-panel]'),toggle=root.querySelector('[data-preview-toggle]');
 const t=(en,si)=>globalThis.DharmaI18n?.locale==='en'?en:si;
 toggle.addEventListener('click',()=>{panel.hidden=!panel.hidden;toggle.setAttribute('aria-expanded',String(!panel.hidden));});
 root.querySelectorAll('[data-preview-line]').forEach(line=>{
  const year=line.querySelector('[data-preview-year]'),query=line.querySelector('[data-preview-search]'),list=line.querySelector('[data-preview-products]'),status=line.querySelector('[data-preview-status]'),chosen=line.querySelector('[data-preview-selected]');
  let epoch=0,timer,request,selected='';
  function clearSelection(){selected='';list.dataset.selectedId='';chosen.textContent=t('No product selected','භාණ්ඩයක් තෝරා නැත');status.textContent='';}
  function message(text){const span=document.createElement('span');span.className='return-preview-empty';span.textContent=text;list.replaceChildren(span);}
  function choose(product,button){
   selected=product.id;list.dataset.selectedId=selected;list.querySelectorAll('button').forEach(b=>b.setAttribute('aria-selected',String(b===button)));
   chosen.textContent=t('Selected: ','තෝරාගත් භාණ්ඩය: ')+button.textContent;
   status.textContent=product.unit_price!==null?t('Approved historical price: LKR ','අනුමත පැරණි මිල: රු. ')+product.unit_price:t('Historical price not configured for the selected year.','තෝරාගත් වර්ෂය සඳහා පැරණි මිලක් සකසා නැත.');
  }
  async function search(restore=''){
   const term=query.value.trim(),mine=++epoch;request?.abort();
   if(!term){message(t('Type a product code or name to search','භාණ්ඩ කේතය හෝ නම ඇතුළත් කරන්න'));clearSelection();return;}
   request=new AbortController();const url=new URL(root.dataset.productUrl,location.href);url.searchParams.set('year',year.value);url.searchParams.set('q',term);
   try{
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:request.signal});if(!response.ok)throw Error('unavailable');const data=await response.json();if(mine!==epoch)return;
    list.replaceChildren();clearSelection();
    if(!data.products.length){message(t('No matching active products','ගැළපෙන සක්‍රිය භාණ්ඩ නැත'));return;}
    for(const p of data.products){
     const button=document.createElement('button');button.type='button';button.setAttribute('role','option');button.setAttribute('aria-selected','false');button.dataset.productId=p.id;button.textContent=`${p.sku} — ${globalThis.DharmaI18n?.product(p)||p.name}`;
     button.addEventListener('click',()=>choose(p,button));list.append(button);if(p.id===restore)choose(p,button);
    }
   }catch(error){if(error.name==='AbortError'||mine!==epoch)return;message(t('Product search unavailable','භාණ්ඩ සෙවීම ලබාගත නොහැක'));clearSelection();status.textContent=t('Product search unavailable. Check your connection.','භාණ්ඩ සෙවීම ලබාගත නොහැක. සම්බන්ධතාව පරීක්ෂා කරන්න.');}
  }
  query.addEventListener('input',()=>{++epoch;request?.abort();clearTimeout(timer);message(t('Searching…','සොයමින්…'));clearSelection();timer=setTimeout(()=>search(),250);});
  query.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key==='ArrowDown'){event.preventDefault();const first=list.querySelector('button');if(first)first.focus();else{clearTimeout(timer);search();}}});
  list.addEventListener('keydown',event=>{const buttons=[...list.querySelectorAll('button')],index=buttons.indexOf(document.activeElement);if(index<0)return;if(event.key==='Enter'||event.key===' '){event.preventDefault();buttons[index].click();return;}if(!['ArrowDown','ArrowUp'].includes(event.key))return;event.preventDefault();buttons[Math.max(0,Math.min(buttons.length-1,index+(event.key==='ArrowDown'?1:-1)))].focus();});
  year.addEventListener('change',()=>{const id=selected;clearTimeout(timer);search(id);});
 });
})();
