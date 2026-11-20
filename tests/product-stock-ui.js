'use strict';
const test=require('node:test'),assert=require('node:assert/strict');
const {connectProductStock}=require('../public/assets/js/product-stock.js');
test('selecting a product loads its authoritative price/version and binds the price to that product',()=>{
    const fields={product_id:{value:'2',selectedIndex:1,options:[{dataset:{}},{dataset:{price:'123.45',version:'7'}}],addEventListener(type,fn){this.change=fn;}},selling_price:{value:'1.00'},product_version:{value:'1'},price_product_id:{value:'1'}};
    connectProductStock({getElementById:id=>fields[id]});assert.equal(fields.selling_price.value,'1.00');
    fields.product_id.change();assert.equal(fields.selling_price.value,'123.45');assert.equal(fields.product_version.value,'7');assert.equal(fields.price_product_id.value,'2');
    fields.product_id.value='';fields.product_id.selectedIndex=0;fields.product_id.change();assert.equal(fields.selling_price.value,'');assert.equal(fields.product_version.value,'');assert.equal(fields.price_product_id.value,'');
});
