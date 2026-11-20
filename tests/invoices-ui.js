'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const {filterInvoiceOptions, connectInvoiceForm} = require('../public/assets/js/invoices.js');
test('customer/product searching ignores case and whitespace while retaining placeholder and selection', () => {
  const options = [
    {value:'', textContent:'Select a product'},
    {value:'1', textContent:'ABC — Herbal product'},
    {value:'2', textContent:'XYZ — Another product'},
    {value:'3', textContent:'Selected product', selected:true}
  ];
  filterInvoiceOptions({value:'  hERbal  '},{options});
  assert.deepEqual(options.map(o=>o.hidden),[false,false,true,false]);
  filterInvoiceOptions({value:'xyz'},{options});
  assert.deepEqual(options.map(o=>o.hidden),[false,true,false,false]);
  filterInvoiceOptions({value:''},{options});
  assert.deepEqual(options.map(o=>o.hidden),[false,false,false,false]);
});
test('invoice enhancement safely ignores other pages',()=>assert.doesNotThrow(()=>connectInvoiceForm({getElementById:()=>null})));
