'use strict';
/* Sinhala display bindings */
const tr_promotions_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

function connectPromotionForm(doc){
    const type=doc.getElementById('type'),form=doc.getElementById('promotion-form');if(!type||!form)return;
    const refresh=()=>{for(const group of form.querySelectorAll('[data-rule-type]')){const active=group.dataset.ruleType===type.value;group.hidden=!active;for(const input of group.querySelectorAll('input')){input.disabled=!active;input.required=active;}}};
    const mixed=doc.getElementById('mixed_oil');if(mixed){const sync=()=>{const on=mixed.checked;doc.getElementById('product_id').required=!on;doc.getElementById('mixed_product_ids').disabled=!on;if(on){type.value='BUY_X_GET_Y';doc.getElementById('buy_quantity').value='6';doc.getElementById('free_quantity').value='1';}refresh();};mixed.addEventListener('change',sync);sync();}type.addEventListener('change',refresh);refresh();
}
if(typeof module!=='undefined'&&module.exports)module.exports={connectPromotionForm};
if(typeof document!=='undefined')connectPromotionForm(document);
