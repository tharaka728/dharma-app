'use strict';
const test=require('node:test'),assert=require('node:assert/strict');const {connectPromotionForm}=require('../public/assets/js/promotions.js');
test('promotion type enables only its required rule fields while preserving editable draft values',()=>{
    const groups=['BUY_X_GET_Y','BUY_X_GET_Y','PERCENTAGE','FIXED'].map(ruleType=>({dataset:{ruleType},inputs:[{value:'12.50'}],querySelectorAll(){return this.inputs;}}));
    const type={value:'PERCENTAGE',addEventListener(event,fn){this.change=fn;}};const form={querySelectorAll(){return groups;}};connectPromotionForm({getElementById:id=>id==='type'?type:id==='promotion-form'?form:null});
    assert.deepEqual(groups.map(g=>g.hidden),[true,true,false,true]);assert.equal(groups[2].inputs[0].required,true);assert.equal(groups[3].inputs[0].disabled,true);
    type.value='BUY_X_GET_Y';type.change();assert.deepEqual(groups.map(g=>g.hidden),[false,false,true,true]);assert.equal(groups[2].inputs[0].value,'12.50');assert.equal(groups[2].inputs[0].disabled,true);
});
test('mixed oil requires explicit selection and fixed six-for-one values without changing legacy forms',()=>{
 const fields={type:{value:'PERCENTAGE',addEventListener(){}},'promotion-form':{querySelectorAll(){return [];}},mixed_oil:{checked:true,addEventListener(event,fn){this.change=fn;}},product_id:{required:true},mixed_product_ids:{disabled:true},buy_quantity:{value:'12'},free_quantity:{value:'2'}};
 connectPromotionForm({getElementById:id=>fields[id]||null});
 assert.equal(fields.type.value,'BUY_X_GET_Y');assert.equal(fields.buy_quantity.value,'6');assert.equal(fields.free_quantity.value,'1');assert.equal(fields.product_id.required,false);assert.equal(fields.mixed_product_ids.disabled,false);
 fields.mixed_oil.checked=false;fields.mixed_oil.change();assert.equal(fields.product_id.required,true);assert.equal(fields.mixed_product_ids.disabled,true);
});
