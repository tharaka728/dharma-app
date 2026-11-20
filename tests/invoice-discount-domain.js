'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),D=require('../public/assets/js/offline-domain.js');
test('approved discount presets and decimal values use exact LKR arithmetic',()=>{
 for(const [rate,discount,total] of [['0','0.00','251.00'],['25','62.75','188.25'],['30','75.30','175.70'],['35','87.85','163.15'],['12.5','31.38','219.62'],['100','251.00','0.00']]){
  const items=[{payable_amount:'251.00',discount_amount:'0.00',free_quantity:'1'}],r=D.invoiceDiscount(items,rate);assert.equal(r.invoice_discount_amount,discount);assert.equal(items[0].payable_amount,total);assert.equal(items[0].free_quantity,'1');
 }
});
test('largest remainders use original order and never exceed paid net values',()=>{
 const items=Array.from({length:3},()=>({payable_amount:'0.01',discount_amount:'0.00'}));const r=D.invoiceDiscount(items,'50');assert.equal(r.invoice_discount_amount,'0.02');assert.deepEqual(items.map(i=>i.invoice_discount_amount),['0.01','0.01','0.00']);
 for(let rate=0;rate<=100;rate++){
  const lines=['0.01','12.34','99999.99'].map(value=>({payable_amount:value,discount_amount:'0.00'}));const original=lines.reduce((n,l)=>n+D.cents(l.payable_amount),0n),result=D.invoiceDiscount(lines,String(rate));
  assert.equal(lines.reduce((n,l)=>n+D.cents(l.invoice_discount_amount),0n),D.cents(result.invoice_discount_amount));assert.equal(lines.reduce((n,l)=>n+D.cents(l.payable_amount),0n)+D.cents(result.invoice_discount_amount),original);
 }
});
test('invalid percentages are rejected and maximum-size multiplication remains precise',()=>{
 for(const value of ['','-1','100.01','12.555','1e2',12.5,null])assert.throws(()=>D.invoiceDiscount([],value));
 const result=D.invoiceDiscount([{payable_amount:'9999999999999.99',discount_amount:'0.00'}],'12.5');assert.equal(result.invoice_discount_amount,'1250000000000.00');
});
test('line promotions are applied first and original line discount stays explicit',()=>{
 const lines=[{payable_amount:'75.00',discount_amount:'25.00',free_quantity:'2'}];const r=D.invoiceDiscount(lines,'25');assert.equal(r.invoice_discount_amount,'18.75');assert.equal(lines[0].discount_amount,'43.75');assert.equal(lines[0].automatic_discount_amount,'25.00');assert.equal(lines[0].payable_amount,'56.25');
});
