'use strict';
/* Sinhala display bindings */
const tr_product_stock_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

function connectProductStock(doc){
    const product=doc.getElementById('product_id'),price=doc.getElementById('selling_price'),version=doc.getElementById('product_version');
    if(!product||!price||!version)return;
    product.addEventListener('change',()=>{const option=product.options[product.selectedIndex];price.value=option?.dataset.price||'';version.value=option?.dataset.version||'';const binding=doc.getElementById('price_product_id');if(binding)binding.value=product.value;});
}
if(typeof module!=='undefined'&&module.exports)module.exports={connectProductStock};
if(typeof document!=='undefined')connectProductStock(document);
