(() => {
 'use strict';
 const form=document.getElementById('invoice-form');
 const checkout=form?.querySelector('.pos-layout>.pos-panel:last-child');
 if(!checkout)return;
 const si=()=>globalThis.DharmaI18n?.locale!=='en';
 const t=(en,sinhala)=>si()?sinhala:en;
 const node=(tag,text='',cls='')=>{const n=document.createElement(tag);n.textContent=text;n.className=cls;return n;};
 const box=node('section','','benefit-simulation');box.setAttribute('aria-labelledby','benefit-simulation-title');
 const title=node('h2',t('Benefit simulation','දීමනා අනුකරණය'),'h6');title.id='benefit-simulation-title';
 const notice=node('p',t('Simulation only. It does not change checkout, prices, discounts or the amount payable.','අනුකරණයක් පමණි. මෙය බිල්පත, මිල, වට්ටම් හෝ ගෙවිය යුතු මුදල වෙනස් නොකරයි.'),'benefit-simulation-notice');
 const modeLabel=node('label',t('Simulated benefit mode','අනුකරණ දීමනා ක්‍රමය'));modeLabel.htmlFor='benefit-simulation-mode';
 const mode=node('select','','form-select');mode.id='benefit-simulation-mode';
 for(const [value,en,sinhala] of [['free','Free Product Promotion','නොමිලේ භාණ්ඩ දීමනාව'],['alternative','Promotion Discount Instead','නොමිලේ භාණ්ඩ වෙනුවට වට්ටම'],['ordinary','Ordinary Invoice Discount','සාමාන්‍ය බිල්පත් වට්ටම']])mode.add(new Option(t(en,sinhala),value));
 const rateLabel=node('label',t('Simulated percentage','අනුකරණ ප්‍රතිශතය'));rateLabel.htmlFor='benefit-simulation-rate';
 const rate=node('select','','form-select');rate.id='benefit-simulation-rate';for(const [v,label] of [['0','0%'],['25','25%'],['30','30%'],['35','35%'],['custom',t('Custom','වෙනත්')]])rate.add(new Option(label,v));
 const customLabel=node('label',t('Simulated custom percentage (0–100)','අනුකරණ ප්‍රතිශතය (0–100)'));customLabel.htmlFor='benefit-simulation-custom';
 const custom=node('input','','form-control');custom.id='benefit-simulation-custom';custom.type='text';custom.inputMode='decimal';custom.autocomplete='off';
 const details=node('div','','benefit-simulation-result');details.setAttribute('aria-live','polite');
 box.append(title,notice,modeLabel,mode,rateLabel,rate,customLabel,custom,details);
 // No named controls or business form events: this component cannot submit a benefit policy.
 for(const event of ['input','change'])box.addEventListener(event,e=>e.stopPropagation());
 checkout.insertBefore(box,checkout.querySelector('.pos-totals'));
 let data=null;
 const cents=v=>{const m=String(v??'').match(/^(\d+)\.(\d{2})$/);if(!m)throw Error('amount');return BigInt(m[1])*100n+BigInt(m[2]);};
 const money=n=>`${n/100n}.${String(n%100n).padStart(2,'0')}`;
 function render(){
  const discounted=mode.value!=='free';rateLabel.hidden=rate.hidden=!discounted;customLabel.hidden=custom.hidden=!discounted||rate.value!=='custom';details.replaceChildren();
  if(!data){details.append(node('p',t('Choose a customer and products to see a simulation.','අනුකරණය බැලීමට පාරිභෝගිකයෙකු සහ භාණ්ඩ තෝරන්න.')));return;}
  const raw=rate.value==='custom'?custom.value.trim():rate.value;
  if(discounted&&!/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/.test(raw)){details.append(node('p',t('Enter a percentage from 0 to 100, with up to two decimal places.','දශම ස්ථාන දෙකක් දක්වා 0 සිට 100 අතර ප්‍රතිශතයක් ඇතුළත් කරන්න.')));return;}
  try{
   const lines=data.lines||[],list=node('ul');let gross=0n,eligible=0n,free=0n,freeModeNet=0n;
   for(const line of lines){
    const paid=BigInt(line.paid_quantity||'0'),amount=cents(line.gross_amount),rule=line.rule_snapshot;gross+=amount;
    freeModeNet+=amount-cents(line.automatic_discount_amount??line.discount_amount??'0.00')+cents(line.invoice_discount_amount??'0.00')*(line.automatic_discount_amount===undefined?1n:0n);
    const mixed=!!(rule?.mixed_policy||rule?.mixed_product_ids);if(!mixed)free+=BigInt(line.free_quantity||'0');
    const threshold=rule?.type==='BUY_X_GET_Y'&&!mixed&&/^[1-9]\d*$/.test(String(rule.buy_quantity||''));
    const qualifies=threshold&&paid>=BigInt(rule.buy_quantity);if(qualifies)eligible+=amount;
    const reason=qualifies?t('Eligible','සුදුසුයි'):mixed?t('Excluded: mixed-oil offer','ඇතුළත් නොවේ: මිශ්‍ර තෙල් දීමනාව'):threshold?t('Excluded: below promotion threshold','ඇතුළත් නොවේ: දීමනා අවම ප්‍රමාණයට අඩුයි'):t('Excluded: no qualifying free-product promotion','ඇතුළත් නොවේ: සුදුසු නොමිලේ දීමනාවක් නැත');
    list.append(node('li',`${line.sku||line.product_name||line.product_id}: ${reason}`));
   }
   for(const offer of data.oil_offers||[])free+=BigInt(offer.earned||'0');
   const rateParts=(discounted?raw:'0').split('.'),basis=BigInt(rateParts[0])*100n+BigInt((rateParts[1]||'').padEnd(2,'0')||'0');
   const base=mode.value==='alternative'?eligible:gross,discount=discounted?(base*basis+5000n)/10000n:0n,net=discounted?gross-discount:freeModeNet;
   details.append(node('p',t('Line eligibility (alternative mode)','භාණ්ඩ සුදුසුකම (විකල්ප වට්ටම)')),list);
   details.append(node('p',`${t('Simulated eligible subtotal','අනුකරණ සුදුසු එකතුව')}: LKR ${money(eligible)}`));
   details.append(node('p',`${t('Simulated discount','අනුකරණ වට්ටම')}: LKR ${money(discount)}`));
   details.append(node('p',`${t('Free units suppressed in discount modes','වට්ටම් ක්‍රමවල ඉවත් කරන නොමිලේ ඒකක')}: ${discounted?free:'0'}`));
   details.append(node('p',`${t('Simulated merchandise total (not payable)','අනුකරණ භාණ්ඩ එකතුව (ගෙවිය යුතු මුදල නොවේ)')}: LKR ${money(net)}`,'benefit-simulation-total'));
   details.append(node('p',t('Actual checkout continues to use the existing calculation. Returns, payments and stock are not simulated.','සැබෑ බිල්පත දැනට පවතින ගණනය භාවිත කරයි. ආපසු භාණ්ඩ, ගෙවීම් සහ තොග මෙහි ගණනය නොවේ.'),'small'));
  }catch(_){details.append(node('p',t('Simulation unavailable for these values.','මෙම අගයන් සඳහා අනුකරණය ලබාගත නොහැක.')));}
 }
 mode.addEventListener('change',render);rate.addEventListener('change',render);custom.addEventListener('input',render);
 form.addEventListener('pos:totals',e=>{data=e.detail;render();});form.addEventListener('pos:invalid',()=>{data=null;render();});
 const reviewed=document.getElementById('invoice-reviewed-totals');if(reviewed){try{data=JSON.parse(reviewed.textContent);}catch(_){}}render();
})();
