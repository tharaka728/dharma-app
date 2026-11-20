'use strict';
const {test}=require('node:test'),vm=require('node:vm'),assert=require('node:assert/strict'),fs=require('node:fs');
test('Changing an explicit date selects custom range',()=>{
 const handlers={},period={value:'month'},nodes={period,from:{addEventListener:(e,f)=>handlers.from=f},to:{addEventListener:(e,f)=>handlers.to=f}};
 vm.runInNewContext(fs.readFileSync('public/assets/js/reports.js','utf8'),{document:{getElementById:id=>nodes[id]}});handlers.from();assert.equal(period.value,'custom');period.value='week';handlers.to();assert.equal(period.value,'custom');
});
test('Visible-row search ignores case and whitespace and restores rows on clear',()=>{
 let listener;const rows=[{textContent:'Colombo cash 100.00'},{textContent:'Kandy credit 200.00'}];
 vm.runInNewContext(fs.readFileSync('public/assets/js/reports.js','utf8'),{document:{getElementById:id=>id==='report-table-search'?{addEventListener:(e,f)=>listener=f}:null,querySelectorAll:()=>rows}});
 listener({target:{value:' CASH '}});assert.equal(rows[0].hidden,false);assert.equal(rows[1].hidden,true);listener({target:{value:''}});assert.ok(rows.every(r=>!r.hidden));
});
