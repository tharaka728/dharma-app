'use strict';
/* Sinhala display bindings */
const tr_pos_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

/* UI adapter: never posts invoices or calculates financial amounts. */
function createPOS(config) {
 const {form,lines,customer,add,read,change,remove}=config;
 const el=(tag,text='',cls='')=>{const n=document.createElement(tag);n.textContent=text;n.className=cls;return n;};
 const button=(text,fn,cls='')=>{const b=el('button',text,cls);b.type='button';b.addEventListener('click',fn);return b;};
 let products=[],page=0,query='',category='',timer,epoch=0,request;
 const known=new Map();let remoteCategories=[],more=false;
 const layout=el('div','','pos-layout'),catalog=el('section','','pos-panel'),checkout=el('section','','pos-panel');
 layout.append(catalog,checkout);form.prepend(layout);
 const customerBox=el('section','','pos-customer pos-panel');customerBox.append(el('h2',tr_pos_js("Customer"),'h5'));layout.prepend(customerBox);
 const customerSearch=document.getElementById('customer-search');customer.dataset.searchManaged='true';customerSearch?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();customer.focus();}});
 for(const n of [customerSearch,customer]){const label=n?.id&&document.querySelector(`label[for="${n.id}"]`);if(label)customerBox.append(label);if(n)customerBox.append(n);}
 if(config.admin){const a=el('a',tr_pos_js("+ Add Customer"),'d-inline-block mt-2');a.href=config.customerUrl;a.target='_blank';a.rel='noopener';a.setAttribute('aria-label',tr_pos_js("Add Customer (opens in a new tab)"));customerBox.append(a);}
 const source=document.getElementById('stock_source');if(source){const box=source.closest('.col-md-6');if(box)catalog.prepend(box);}
 customerBox.prepend(el('p',config.offline?tr_pos_js("Provisional sale — official number assigned only after server acceptance."):tr_pos_js("New Invoice · Invoice number assigned on completion."),'pos-cart-caption'));
 const label=el('label',tr_pos_js("Search product by name or code…"),'form-label');const search=el('input','','form-control');search.id=config.offline?'offline-pos-search':'pos-search';search.type='search';label.htmlFor=search.id;catalog.append(label,search);
 const categories=el('nav','','pos-categories');categories.setAttribute('aria-label',tr_pos_js("Product categories"));
 const grid=el('div','','pos-grid');const status=el('p','','pos-message');status.setAttribute('role','status');
 const pages=el('div','','pos-pagination');catalog.append(categories,grid,pages,status);
 const heading=el('div','','pos-heading'),title=el('h2',tr_pos_js("Invoice Items"),'h5 mb-0');heading.append(title,button(tr_pos_js("Clear Cart"),()=>{if(!read().some(r=>r.id)||!confirm(tr_pos_js("Clear only this unposted cart? Saved sales will be preserved.")))return;for(const row of [...read()])remove(row);rewards.replaceChildren();notify();}));checkout.append(heading);
 const cart=el('div');checkout.append(cart);let rewards=document.getElementById('oil-rewards');if(!rewards){rewards=el('section','','my-3');rewards.id='oil-rewards';rewards.setAttribute('aria-label',tr_pos_js("Free oil choices"));}checkout.append(rewards);lines.classList.add('pos-sr-fields');config.addButton.classList.add('pos-sr-fields');
 // Keep the original controls and server fallback intact; only relocate the presentation.
 const controls=config.controls;for(const control of controls)if(control)checkout.append(control);
 const totals=el('section','','pos-totals');totals.setAttribute('aria-label',tr_pos_js("Live invoice totals"));totals.setAttribute('aria-live','polite');
 checkout.append(totals);
 for(const control of config.paymentControls||[])if(control)checkout.append(control);
 const pay=config.reviewButton;pay.classList.add('pos-pay');checkout.append(pay);
 if(config.review)checkout.append(config.review);
 function notify(){form.dispatchEvent(new Event('change',{bubbles:true}));renderCart();totals.replaceChildren(el('p',tr_pos_js("Updating verified totals…")));config.invalidate?.();if(!config.offline)pay.disabled=true;}
 function renderCart(){
  cart.replaceChildren();const rows=read().filter(r=>r.id);title.textContent=tr_pos_js("Invoice Items ({0})",{0:(rows.length)});
  if(!rows.length)cart.append(el('p',tr_pos_js("Your cart is empty. Choose a product to begin."),'pos-cart-empty'));
  for(const row of rows){const product=known.get(row.id)||row;const card=el('article','','pos-cart-row'),info=el('div','','pos-cart-info');info.append(el('strong',(globalThis.DharmaI18n?.product(product)||product.name)||row.label),el('small','','pos-cart-meta'));const meta=info.querySelector('small');meta.append(el('span',product.sku||'','pos-code'),el('span',tr_pos_js("LKR {0}",{0:(product.selling_price||'—')}),'pos-unit-price'));
   const step=el('div','','pos-stepper');const minus=button('−',()=>{if(!/^[1-9][0-9]*$/.test(row.quantity)||BigInt(row.quantity)<=1n)return;change(row,(BigInt(row.quantity)-1n).toString());notify();});minus.setAttribute('aria-label',tr_pos_js("Decrease {0}",{0:(product.name||'quantity')}));
   const qty=el('input');qty.value=row.quantity;qty.inputMode='numeric';qty.pattern='[1-9][0-9]*';qty.setAttribute('aria-label',tr_pos_js("Quantity for {0}",{0:((globalThis.DharmaI18n?.product(product)||product.name)||row.label)}));qty.addEventListener('input',()=>{change(row,qty.value);notifyWithoutRender();});qty.addEventListener('change',()=>renderCart());
   const plus=button('+',()=>increment(row,product));plus.setAttribute('aria-label',tr_pos_js("Increase {0}",{0:(product.name||'quantity')}));step.append(minus,qty,plus);
   const del=button(tr_pos_js("Remove"),()=>{remove(row);notify();},'pos-remove');del.setAttribute('aria-label',tr_pos_js("Remove {0}",{0:((globalThis.DharmaI18n?.product(product)||product.name)||row.label)}));card.append(info,step,del);const total=el('div',tr_pos_js("Review calculates promotions and free units."),'pos-line-total');total.dataset.productId=row.id;card.append(total);cart.append(card);
  }
  if(!config.offline)pay.disabled=!rows.length||!form.checkValidity();
 }
 function notifyWithoutRender(){form.dispatchEvent(new Event('change',{bubbles:true}));config.invalidate?.();if(!config.offline)pay.disabled=true;}
 function increment(row,product){if(!/^[1-9][0-9]*$/.test(row.quantity))return;const qty=BigInt(row.quantity)+1n;if(qty>BigInt(product.available_quantity||0)){status.textContent=tr_pos_js("Not enough available stock. Promotional free units are also checked during review.");return;}change(row,qty.toString());notify();}
 function draw(){
  if(config.load){load();return;}paint();
 }
 async function load(){const mine=++epoch;request?.abort();request=new AbortController();status.textContent=tr_pos_js("Loading products…");try{const data=await config.load({query,category,page,signal:request.signal});if(mine!==epoch)return;products=data.products;more=data.has_more;remoteCategories=data.categories;for(const p of products)known.set(p.id,p);drawCategories();paint();status.textContent='';}catch(e){if(mine===epoch&&e.name!=='AbortError'){const connection=document.getElementById('pos-connection');if(connection)connection.textContent=navigator.onLine?tr_pos_js("Server unavailable / sign-in required"):tr_pos_js("Offline");grid.replaceChildren();status.textContent=tr_pos_js("Products could not be loaded. ")+e.message;}}}
 function paint(){
  const filtered=config.load?products:products.filter(p=>(!category||p.category===category)&&`${p.name} ${p.name_si||''} ${p.sku}`.toLocaleLowerCase().includes(query));const max=config.load?page+1+(more?1:0):Math.max(1,Math.ceil(filtered.length/12));page=Math.min(page,max-1);grid.replaceChildren();
  for(const p of config.load?filtered:filtered.slice(page*12,page*12+12)){const card=el('article','','pos-product');card.dataset.productId=p.id;const icon=el('div','◇','pos-placeholder');icon.setAttribute('aria-hidden','true');if(p.image_url){const img=el('img');img.src=p.image_url;img.alt='';img.loading='lazy';img.addEventListener('error',()=>img.remove());icon.append(img);icon.classList.add('has-image');}card.append(icon,el('small',p.sku),el('h3',globalThis.DharmaI18n?.product(p)||p.name),el('div',tr_pos_js("LKR {0}",{0:(p.selling_price)}),'pos-price'),el('small',tr_pos_js("Available: {0}",{0:(p.available_quantity)})));const b=button(tr_pos_js("+ Add"),()=>{const row=read().find(r=>r.id===p.id);if(row)increment(row,p);else{if(add(p)===false){status.textContent=tr_pos_js("At most 100 different products can be added.");return;}notify();}status.textContent=tr_pos_js("{0} selected. Stock changes only when the sale is posted.",{0:(p.sku)});});b.disabled=BigInt(p.available_quantity||0)<=0n;b.setAttribute('aria-label',tr_pos_js("Add {0}",{0:(p.name)}));card.append(b);grid.append(card);}
  if(!filtered.length)grid.append(el('p',tr_pos_js("No products match this search.")));
  pages.replaceChildren(button(tr_pos_js("Previous"),()=>{page--;draw();}),el('span',config.load?tr_pos_js("Page {0}",{0:(page+1)}):tr_pos_js("{0} products · Page {1} of {2}",{0:(filtered.length),1:(page+1),2:(max)})),button(tr_pos_js("Next"),()=>{page++;draw();}));pages.firstChild.disabled=page<=0;pages.lastChild.disabled=page>=max-1;
 }
 search.addEventListener('input',()=>{epoch++;request?.abort();clearTimeout(timer);timer=setTimeout(()=>{query=search.value.trim().toLocaleLowerCase();page=0;draw();},300);});
 search.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();grid.querySelector('button:not(:disabled)')?.focus();}});
 function drawCategories(){categories.replaceChildren();for(const name of ['',...new Set(config.load?remoteCategories:products.map(p=>p.category).filter(Boolean))]){const b=button(name||tr_pos_js("All Products"),()=>{category=name;page=0;drawCategories();draw();});b.setAttribute('aria-pressed',String(name===category));categories.append(b);}}
 function setProducts(rows){products=rows;for(const p of rows)known.set(p.id,p);drawCategories();draw();renderCart();}
 function amounts(data){
  const activeReward=rewards.contains(document.activeElement)?{id:document.activeElement.id,start:document.activeElement.selectionStart,end:document.activeElement.selectionEnd}:null;
  rewards.replaceChildren();for(const offer of data.oil_offers||[]){if(offer.earned==='0')continue;rewards.append(el('h3',offer.name+tr_pos_js(" — choose free oils"),'h5'),el('p',offer.earned+tr_pos_js(" earned · ")+offer.remaining+tr_pos_js(" still to select. Free units never qualify.")));
   for(const c of offer.choices){const row=el('div','','mb-2');row.dataset.oilRewardRow='';const hidden=el('input');hidden.type='hidden';hidden.name='oil_rewards['+c.product_id+'][product_id]';hidden.value=c.product_id;const label=el('label',c.sku+' / '+(globalThis.DharmaI18n?.product(c)||c.name)+tr_pos_js(" · LKR ")+c.unit_price+' · '+c.additional_quantity+tr_pos_js(" more eligible and in stock"),'form-label'),input=el('input','','form-control');input.id='oil-reward-'+c.product_id;label.htmlFor=input.id;input.name='oil_rewards['+c.product_id+'][quantity]';input.value=c.selected_quantity;input.inputMode='numeric';input.pattern='0|[1-9][0-9]*';input.maxLength=19;input.required=true;input.setAttribute('aria-label',tr_pos_js("Free quantity for ")+c.name);if(c.selected_quantity==='0'&&c.additional_quantity==='0')input.disabled=true;row.append(hidden,label,input);rewards.append(row);}
  }

  if(activeReward){const field=document.getElementById(activeReward.id);if(field&&!field.disabled){field.focus({preventScroll:true});if(activeReward.start!==null)field.setSelectionRange(activeReward.start,activeReward.end);}}
  totals.replaceChildren();const dl=el('dl','','row');
  for(const [label,value,grand] of [['Subtotal after line promotions',data.discountable_subtotal||data.invoice_discount_base||data.subtotal],[tr_pos_js('Invoice discount')+' '+(data.invoice_discount_percent||'0')+'%',data.invoice_discount_amount||'0.00'],['Grand Total',data.grand_total,true],['Applied payment',data.applied_amount],['Change',data.change_amount],['Outstanding',data.outstanding]]){const dt=el('dt',tr_pos_js(label),'col-7'+(grand?' pos-grand':'')),dd=el('dd',tr_pos_js("LKR {0}",{0:(value??'—')}),'col-5'+(grand?' pos-grand':''));dl.append(dt,dd);}totals.append(dl);
  for(const line of data.lines||data.items||[]){const n=[...cart.querySelectorAll('[data-product-id]')].find(n=>n.dataset.productId===String(line.product_id));if(n)n.replaceChildren(el("span",tr_pos_js("LKR {0}",{0:line.payable_amount}),"pos-line-amount"),el("span",tr_pos_js("Free units")+": "+line.free_quantity,"pos-free-units"));}
  pay.textContent=(config.offline?tr_pos_js("Review provisional sale"):tr_pos_js("Pay Now — Review"))+tr_pos_js(" · LKR {0}",{0:(data.grand_total)});if(!config.offline)pay.disabled=false;
 }
 form.addEventListener('pos:totals',e=>amounts(e.detail));
 form.addEventListener('pos:invalid',()=>{totals.replaceChildren(el('p',tr_pos_js("Complete valid customer, stock, quantity and payment details to see verified totals.")));if(!config.offline)pay.disabled=true;});
 return {setProducts,amounts,renderCart,clear(){products=[];known.clear();grid.replaceChildren();cart.replaceChildren();rewards.replaceChildren();totals.replaceChildren();categories.replaceChildren();search.value='';query='';category='';}};
}
if(typeof document!=='undefined'){
 const form=document.getElementById('invoice-form');if(form){const connection=document.createElement('span');connection.id='pos-connection';connection.textContent=tr_pos_js("Checking server...");connection.setAttribute('role','status');connection.className='small';document.querySelector('.workspace-header')?.append(connection);window.addEventListener('offline',()=>{connection.textContent=tr_pos_js("Offline: use the registered offline workspace");});window.addEventListener('online',()=>{connection.textContent=tr_pos_js("Connection needs checking");});
  document.body.classList.add('pos-page');const lines=document.getElementById('invoice-lines'),addButton=document.getElementById('add-invoice-line');
  const productOptions=[...document.getElementById('invoice-line-template').content.querySelector('select').options].filter(o=>o.value);
  const products=productOptions.map(o=>({id:o.value,name:o.dataset.name||o.textContent,name_si:o.dataset.nameSi||'',sku:o.dataset.sku||'',selling_price:o.dataset.price||'',available_quantity:o.dataset.quantity,category:o.dataset.category||''}));
  const read=()=>[...lines.children].map(node=>({node,id:node.querySelector('select').value,quantity:node.querySelector('input[pattern]').value,label:node.querySelector('select').selectedOptions[0]?.textContent||''}));
  const pos=createPOS({form,lines,customer:document.getElementById('customer_id'),admin:!!document.getElementById('stock_source'),customerUrl:form.action.replace('/invoices/new','/customers/new'),addButton,read,
   async load({query,category,page,signal}){const u=new URL(form.dataset.optionsUrl,location.href);u.search=new URLSearchParams({catalogue:'1',q:query,category,page:String(page+1),stock_source:document.getElementById('stock_source')?.value||''});const r=await fetch(u,{credentials:'same-origin',cache:'no-store',signal});const data=await r.json();if(!r.ok){connection.textContent=tr_pos_js("Server unavailable / sign-in required");throw Error(Object.values(data.errors||{}).join(' ')||tr_pos_js("Sign in and retry."));}connection.textContent=tr_pos_js("Online: server reached");return data;},
   add(p){let row=read().find(r=>!r.id);if(!row){if(read().length>=100)return false;addButton.click();row=read().at(-1);}const select=row.node.querySelector('select');if(![...select.options].some(o=>o.value===p.id)){const o=new Option(`${p.sku} — ${p.name}`,p.id);select.add(o);}select.value=p.id;row.node.querySelector('input[pattern]').value='1';},change(row,q){row.node.querySelector('input[pattern]').value=q;},remove(row){if(lines.children.length>1)row.node.remove();else{row.node.querySelector('select').value='';row.node.querySelector('input[pattern]').value='1';}},
   controls:[document.getElementById('invoice_discount_choice').closest('fieldset'),document.getElementById('invoice-live-summary')],paymentControls:[document.getElementById('payment_type').closest('.row')],reviewButton:form.querySelector('button[value="review"]'),review:form.querySelector('[data-invoice-confirmation]')});
  pos.setProducts(products);const reviewed=document.getElementById('invoice-reviewed-totals');if(reviewed){pos.amounts(JSON.parse(reviewed.textContent));document.getElementById('invoice-live-summary').textContent=tr_pos_js('Review before issuing');}form.addEventListener('change',e=>{if(e.target.id==='customer_id'||e.target.id==='payment_type')form.dispatchEvent(new Event('input',{bubbles:true}));});
  // Remove presentation-only headings left behind by the progressively enhanced form.
  for(const n of form.querySelectorAll('.card-body>h2,.card-body>p.small.text-secondary'))n.hidden=true;
 }
}
if(typeof module!=='undefined')module.exports={createPOS};
