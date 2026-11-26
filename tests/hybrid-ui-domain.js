'use strict';
const test = require('node:test'), assert = require('node:assert/strict');
const {hybridRate,hybridSelection,hybridPayload,connectHybridInvoice}=require('../public/assets/js/hybrid-invoice.js');
const values=()=>({requestId:'11111111-1111-4111-8111-111111111111',customerId:'20',payment:'CASH',received:'700.00',lines:[{productId:'101',quantity:'8',choice:'0',custom:'',replace:false}],invoiceChoice:'0',invoiceCustom:'',replacementChoice:'',replacementCustom:''});
test('zero default and independent selections use only versioned financial intent',()=>{
 const v=values();assert.deepEqual(hybridPayload(v).hybrid.lines[0],{product_id:'101',paid_quantity:'8',product_discount:{choice:'0'}});
 v.lines.push({productId:'102',quantity:'18',choice:'35',replace:false});v.invoiceChoice='other';v.invoiceCustom='12.50';const p=hybridPayload(v);
 assert.equal(p.hybrid.lines[0].product_discount.choice,'0');assert.equal(p.hybrid.lines[1].product_discount.choice,'35');assert.equal(p.hybrid.invoice_discount_percent,'12.50');
 assert.deepEqual(Object.keys(p),['request_id','customer_id','payment_type','amount_received','hybrid']);assert.ok(!JSON.stringify(p).includes('unit_price'));
});
test('manual rates accept exact boundaries and reject invalid input without clamping',()=>{
 for(const v of ['0','0.00','25','30','35','49.99','50.00'])assert.equal(hybridRate(v),v);
 for(const v of ['','-1','50.01','75','100','1.001','NaN','Infinity','1e1',' 25',25,null])assert.throws(()=>hybridRate(v));
 assert.deepEqual(hybridSelection('other','0'),{choice:'other',percent:'0'});assert.throws(()=>hybridSelection('custom','25'));
});
test('replacement has no default, uses the existing options and retains its separate rate range',()=>{
 const v=values();v.lines[0].replace=true;assert.throws(()=>hybridPayload(v),/Explicitly/);v.replacementChoice='25';assert.deepEqual(hybridPayload(v).hybrid.replacement_discount,{choice:'25'});
 v.replacementChoice='custom';v.replacementCustom='75.00';assert.deepEqual(hybridPayload(v).hybrid.replacement_discount,{choice:'custom',percent:'75.00'});
 v.lines[0].replace=false;assert.equal(hybridPayload(v).hybrid.replacement_discount,undefined);
});
test('ambiguous quantities, identities, duplicates and invalid cash fail locally',()=>{
 for(const qty of ['0','-1','1.2','01','9223372036854775808']){const v=values();v.lines[0].quantity=qty;assert.throws(()=>hybridPayload(v));}
 for(const cash of ['-1','1.001','1e4',NaN]){const v=values();v.received=cash;assert.throws(()=>hybridPayload(v));}
 const v=values();v.lines.push({...v.lines[0]});assert.throws(()=>hybridPayload(v),/aggregate/);
});
test('adapter ignores legacy screens and contains no offline persistence or financial calculator',()=>{
 assert.equal(connectHybridInvoice({getElementById:()=>null}),null);
 const src=require('node:fs').readFileSync(require.resolve('../public/assets/js/hybrid-invoice.js'),'utf8');
 assert.doesNotMatch(src,/localStorage|sessionStorage|indexedDB|serviceWorker|OfflineDomain|InvoiceDiscount\.apply/);
});
