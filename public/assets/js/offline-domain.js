'use strict';
/* Sinhala display bindings */
const tr_offline_domain_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

(function(root){
 const MAX=9223372036854775807n, MONEY=999999999999999n;
 const quantity=v=>{if(typeof v!=='string'||!(/^[1-9][0-9]*$/).test(v)||BigInt(v)>MAX)throw Error(tr_offline_domain_js("Enter positive whole-unit quantities within the supported range."));return BigInt(v);};
 const cents=v=>{if(typeof v!=='string'||!(/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/).test(v))throw Error(tr_offline_domain_js("Enter a nonnegative LKR amount with at most two decimal places."));const [a,b='']=v.split('.');return BigInt(a)*100n+BigInt(b.padEnd(2,'0'));};
 const money=v=>{if(v<0n||v>MONEY)throw Error(tr_offline_domain_js("LKR amount exceeds the supported range."));return `${v/100n}.${String(v%100n).padStart(2,'0')}`;};
 function line(product,paid,instant){
  const qty=quantity(paid),price=cents(product.selling_price),gross=price*qty;let discount=0n,free=0n;const rule=product.rule;const day=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Colombo',year:'numeric',month:'2-digit',day:'2-digit'}).format(instant);
  if(rule&&((rule.start_date&&day<rule.start_date)||(rule.end_date&&day>rule.end_date)))throw Error(tr_offline_domain_js("A downloaded promotion is no longer eligible. Refresh data before creating this sale."));
  if(rule?.mixed_product_ids&&rule.mixed_policy!=='MIXED_OIL_V1')throw Error(tr_offline_domain_js("Unsupported mixed oil policy. Refresh data."));
  if(rule?.mixed_policy==='MIXED_OIL_V1'){if(rule.type!=='BUY_X_GET_Y'||String(rule.buy_quantity)!=='6'||String(rule.free_quantity)!=='1'||!Array.isArray(rule.mixed_product_ids))throw Error(tr_offline_domain_js("Unsupported mixed oil rule. Refresh data."));}
  else if(rule?.type==='BUY_X_GET_Y')free=(qty/quantity(rule.buy_quantity))*quantity(rule.free_quantity);
  else if(rule?.type==='PERCENTAGE')discount=(gross*cents(rule.percent_rate)+5000n)/10000n;
  else if(rule?.type==='FIXED'){const fixed=cents(rule.fixed_discount);if(fixed>price)throw Error(tr_offline_domain_js("Promotion exceeds product price. Refresh data."));discount=fixed*qty;}
  else if(rule)throw Error(tr_offline_domain_js("Unsupported promotion. Refresh data."));
  if(qty+free>MAX||discount>gross)throw Error(tr_offline_domain_js("Invalid stock requirement or discount."));
  return {product_id:product.id,quantity:String(qty),unit_price:money(price),price_version:product.version,free_quantity:String(free),required_stock:String(qty+free),gross_amount:money(gross),discount_amount:money(discount),payable_amount:money(gross-discount)};
 }
 function mixedPlan(rule,items,products,stock,selected){
  const members=rule.mixed_product_ids,paid=items.filter(l=>members.includes(l.product_id));
  paid.sort((a,b)=>cents(a.unit_price)===cents(b.unit_price)?(BigInt(a.product_id)<BigInt(b.product_id)?-1:1):(cents(a.unit_price)<cents(b.unit_price)?-1:1));
  const total=paid.reduce((n,l)=>n+BigInt(l.quantity),0n);if(total>MAX)throw Error(tr_offline_domain_js("Combined oil quantity exceeds supported range."));const earned=total/6n,bands=[];let offset=0n;
  for(const l of paid){const end=offset+BigInt(l.quantity),first=offset/6n+(offset%6n?1n:0n),last=(end-1n)/6n+1n<earned?(end-1n)/6n+1n:earned,n=last>first?last-first:0n;if(n)bands.push({unit_price:l.unit_price,quantity:String(n)});offset=end;}
  const chosen=Object.entries(selected).filter(([id])=>members.includes(id)).map(([id,qty])=>({product_id:id,quantity:qty,unit_price:products.get(id).selling_price}));
  const count=chosen.reduce((n,l)=>n+BigInt(l.quantity),0n);if(count>earned)throw Error(tr_offline_domain_js("Selected free oils exceed the earned entitlement."));
  chosen.sort((a,b)=>cents(a.unit_price)===cents(b.unit_price)?(BigInt(a.product_id)<BigInt(b.product_id)?-1:1):(cents(a.unit_price)<cents(b.unit_price)?-1:1));const remaining=bands.map(b=>BigInt(b.quantity));
  for(const c of chosen){let need=BigInt(c.quantity);for(let i=0;i<bands.length;i++){if(cents(c.unit_price)>cents(bands[i].unit_price))continue;const take=need<remaining[i]?need:remaining[i];need-=take;remaining[i]-=take;if(!need)break;}if(need)throw Error(tr_offline_domain_js("A selected free oil costs more than the cheapest paid oil in its six-unit group."));}
  const choices=members.filter(id=>products.has(id)).map(id=>{const p=products.get(id);let capacity=0n;bands.forEach((b,i)=>{if(cents(p.selling_price)<=cents(b.unit_price))capacity+=remaining[i];});let available=BigInt(stock[id]||'0')-BigInt(selected[id]||'0');for(const l of items)if(l.product_id===id)available-=BigInt(l.required_stock);if(available<0n)available=0n;return {product_id:id,name:p.name,name_si:p.name_si||null,sku:p.sku,unit_price:p.selling_price,available_quantity:String(available),additional_quantity:String(available<capacity?available:capacity),selected_quantity:selected[id]||'0'};});
  return {policy:'MIXED_OIL_V1',promotion_id:rule.id,name:rule.name,earned:String(earned),selected:String(count),remaining:String(earned-count),groups:bands,choices};
 }
 function rawStock(state){
  const stock=Object.fromEntries(Object.entries(state.snapshot?.stock||{}).map(([k,v])=>[k,BigInt(v)]));
  for(const sale of state.sales||[])if(!sale.reconciled)for(const item of sale.payload.items)stock[item.product_id]=(stock[item.product_id]||0n)-BigInt(item.quantity)-BigInt(item.free_quantity);
  return stock;
 }
 function available(state){return Object.fromEntries(Object.entries(rawStock(state)).map(([k,v])=>[k,String(v<0n?0n:v)]));}
 function deficits(state){return Object.entries(rawStock(state)).filter(([,v])=>v<0n).map(([k])=>k);}
 function fresh(state,now=Date.now()){
  if(!state.snapshot)throw Error(tr_offline_domain_js("Sign in and register / refresh this device first."));
  if(now<state.last_local_time||now<state.local_anchor)throw Error(tr_offline_domain_js("Device clock moved backwards. Reconnect before creating new sales."));
  const trusted=Date.parse(state.snapshot.server_time)+(now-state.local_anchor);
  if(trusted>=Date.parse(state.snapshot.expires_at))throw Error(tr_offline_domain_js("The seven-day offline snapshot expired. Reconnect to refresh. Pending sales are retained."));
  return new Date(trusted);
 }
 function invoiceDiscount(items,percent){
  if(typeof percent!=='string'||!/^(0|[1-9][0-9]{0,2})(\.[0-9]{1,2})?$/.test(percent)||cents(percent)>10000n)throw Error(tr_offline_domain_js("Enter a discount from 0 to 100 with up to two decimal places."));
  const base=items.reduce((sum,item)=>sum+cents(item.payable_amount),0n),discount=(base*cents(percent)+5000n)/10000n;
  const shares=items.map((item,index)=>{const scaled=discount*cents(item.payable_amount);return {index,amount:base?scaled/base:0n,remainder:base?scaled%base:0n};});
  let left=discount-shares.reduce((sum,s)=>sum+s.amount,0n);
  const ordered=[...shares].sort((a,b)=>a.remainder===b.remainder?a.index-b.index:(a.remainder>b.remainder?-1:1));
  for(let i=0;left>0n;i++,left--)ordered[i].amount++;
  items.forEach((item,index)=>{item.automatic_discount_amount=item.discount_amount;item.invoice_discount_amount=money(shares[index].amount);item.discount_amount=money(cents(item.discount_amount)+shares[index].amount);item.payable_amount=money(cents(item.payable_amount)-shares[index].amount);});
  return {invoice_discount_percent:money(cents(percent)),invoice_discount_amount:money(discount),discountable_subtotal:money(base),discount_policy:'INVOICE_PERCENT_V1'};
 }
 function preview(state,input,now=Date.now()){
  const instant=fresh(state,now);const customer=state.snapshot.customers.find(c=>c.id===input.customer_id);if(!customer)throw Error(tr_offline_domain_js("Select an assigned customer."));
  if(!Array.isArray(input.items)||!input.items.length||input.items.length>100)throw Error(tr_offline_domain_js("Choose 1–100 products."));
  const stock=available(state),seen=new Set();const products=new Map(state.snapshot.products.map(p=>[p.id,p]));let gross=0n,discount=0n,total=0n;
  const items=input.items.map(i=>{if(seen.has(i.product_id))throw Error(tr_offline_domain_js("Each product may appear only once."));seen.add(i.product_id);const product=state.snapshot.products.find(p=>p.id===i.product_id);if(!product)throw Error(tr_offline_domain_js("Product is not authorized in this snapshot."));const l=line(product,i.quantity,instant);if(BigInt(l.required_stock)>BigInt(stock[i.product_id]||'0'))throw Error(tr_offline_domain_js("Insufficient local stock for {0}, including free units.",{0:(product.sku)}));gross+=cents(l.gross_amount);discount+=cents(l.discount_amount);total+=cents(l.payable_amount);return l;}).sort((a,b)=>BigInt(a.product_id)<BigInt(b.product_id)?-1:1);
  const selected={};if(input.oil_rewards!==undefined&&(!Array.isArray(input.oil_rewards)||input.oil_rewards.length>100))throw Error(tr_offline_domain_js("Invalid oil reward selections."));
  for(const r of input.oil_rewards||[]){if(r.quantity==='0')continue;if(selected[r.product_id])throw Error(tr_offline_domain_js("Select each free oil once."));if(!products.has(r.product_id))throw Error(tr_offline_domain_js("Free oil is not authorized in this snapshot."));selected[r.product_id]=String(quantity(r.quantity));}
  const rules=new Map();for(const l of items){const rule=products.get(l.product_id).rule;if(rule?.mixed_policy==='MIXED_OIL_V1')rules.set(rule.revision_id,rule);}
  const recognized=new Map(),offers=[];for(const rule of rules.values()){offers.push(mixedPlan(rule,items,products,stock,selected));for(const id of rule.mixed_product_ids)recognized.set(id,rule);}
  for(const [id,qty] of Object.entries(selected)){const rule=recognized.get(id);if(!rule||products.get(id).rule?.revision_id!==rule.revision_id)throw Error(tr_offline_domain_js("Selected free oil does not belong to a qualifying group."));let l=items.find(l=>l.product_id===id);if(!l){l=line(products.get(id),'1',instant);l.quantity='0';for(const key of ['gross_amount','discount_amount','payable_amount'])l[key]='0.00';items.push(l);}l.free_quantity=qty;l.required_stock=String(BigInt(l.quantity)+BigInt(qty));if(BigInt(l.required_stock)>MAX||BigInt(l.required_stock)>BigInt(stock[id]||'0'))throw Error(tr_offline_domain_js("Insufficient local stock for selected free oil."));}
  if(items.length>100)throw Error(tr_offline_domain_js("Paid and reward products together may not exceed 100."));
  let spec={};if(input.discount_policy!==undefined||input.invoice_discount_percent!==undefined){if(input.discount_policy!=='INVOICE_PERCENT_V1')throw Error(tr_offline_domain_js("Unsupported discount policy. Reopen the updated app."));const order=new Map(input.items.map((item,index)=>[item.product_id,index]));items.sort((a,b)=>(order.get(a.product_id)??Number.MAX_SAFE_INTEGER)-(order.get(b.product_id)??Number.MAX_SAFE_INTEGER)||(BigInt(a.product_id)<BigInt(b.product_id)?-1:1));spec=invoiceDiscount(items,input.invoice_discount_percent);const amount=cents(spec.invoice_discount_amount);discount+=amount;total-=amount;}
  const received=cents(input.amount_received),applied=received>total?total:received;
  return {...(offers.length?{oil_offers:offers,oil_rewards:Object.entries(selected).map(([product_id,quantity])=>({product_id,quantity}))}:{}),customer_id:customer.id,payment_type:'CASH',amount_received:money(received),items,subtotal:money(gross),discount_total:money(discount),grand_total:money(total),...spec,applied_amount:money(applied),change_amount:money(received-applied),outstanding:money(total-applied),created_at_utc:instant.toISOString().replace(/\.\d{3}Z$/,'Z')};
 }
 function reconcile(state,snapshot,now=Date.now()){
  if(state.rep_id!==snapshot.rep.id||state.device_id!==snapshot.device_id)throw Error(tr_offline_domain_js("Snapshot identity mismatch."));
  const receipts=new Map(snapshot.receipts.map(r=>[r.operation_id,r]));
  for(const sale of state.sales){const r=receipts.get(sale.payload.operation_id);if(r){sale.status=r.status;sale.receipt=r;sale.reconciled=!!r.reservation_resolved;if(sale.reconciled&&!sale.acknowledged_at)sale.acknowledged_at=Date.parse(r.accepted_at||snapshot.server_time);}}
  // Only confirmed and coherently reconciled Synced history is eligible for the 30-day retention rule.
  state.sales=state.sales.filter(s=>!(s.status==='Synced'&&s.reconciled&&s.acknowledged_at<Date.parse(snapshot.server_time)-30*86400000));
  state.snapshot=snapshot;state.local_anchor=now;state.last_local_time=now;return state;
 }
 const api={quantity,cents,money,line,mixedPlan,invoiceDiscount,available,deficits,fresh,preview,reconcile};root.OfflineDomain=api;if(typeof module!=='undefined')module.exports=api;
})(typeof globalThis!=='undefined'?globalThis:this);
