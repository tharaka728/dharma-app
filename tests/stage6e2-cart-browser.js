'use strict';
const {spawn}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const input=JSON.parse(fs.readFileSync(0,'utf8')),f=input.fixtures;
const profile=path.join(input.directory,'browser-profile');fs.mkdirSync(profile);
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
browser.stderr.on('data',chunk=>fs.appendFileSync(path.join(input.directory,'edge-stderr.log'),chunk));
fs.writeFileSync(path.join(input.directory,'browser-process.json'),JSON.stringify({pid:browser.pid,executable:'Microsoft Edge',source_worktree:process.cwd(),preview:input.base}));
let completed=false;browser.on('exit',(code)=>{if(!completed){console.error('Browser exited before milestone completion: '+code);process.exitCode=1;}});
let ws,seq=0,sessionId,checks=0;const waiting=new Map();
const check=(ok,label)=>{assert.ok(ok,label);checks++;};
async function connect(){const endpoint=await new Promise((resolve,reject)=>{let buffer='';const timer=setTimeout(()=>reject(Error('Headless Edge did not start.')),20000);browser.stderr.on('data',chunk=>{buffer+=chunk;const match=buffer.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(match){clearTimeout(timer);resolve(match[1]);}});browser.on('exit',()=>reject(Error('Browser exited.')));});ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=e=>{const d=JSON.parse(e.data);if(d.id){const p=waiting.get(d.id);if(p){waiting.delete(d.id);d.error?p.reject(Error(d.error.message)):p.resolve(d.result);}}};const target=await send('Target.createTarget',{url:'about:blank'},false);sessionId=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;await send('Page.enable');await send('Runtime.enable');await send('Network.enable');}
function send(method,params={},session=true){const id=++seq;return new Promise((resolve,reject)=>{waiting.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId}:{})}));});}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;}
async function until(expression,label,timeout=20000){const start=Date.now();while(Date.now()-start<timeout){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,80));}throw Error('Timed out: '+label+'; '+await evaluate('document.getElementById("return-message")?.textContent||document.body.textContent.slice(0,400)'));}
async function navigate(route){await send('Page.navigate',{url:input.base+route});await until("document.readyState==='complete'",'document loaded');}

async function set(selector,value,event='change'){await evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event(${JSON.stringify(event)},{bubbles:true}));})()`);}
(async()=>{
 await connect();for(const width of [1440,390]){
 await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
 await navigate('/rep/invoices/new?language=en');await until('!!document.querySelector(".pos-product button")','original product cards');
 check(await evaluate('document.querySelector("#invoice-form")?.dataset.template')==='app/Views/invoices.php','original template attestation');
 check(await evaluate('document.querySelectorAll("#invoice-form").length')===1,'one original form');
 const source=await evaluate('fetch(location.href).then(r=>r.headers.get("X-Isolated-Source"))');check(source==='hybrid-promotion-v1-stage6e2-original','served branch');
 await set('#customer_id',f.customers.rep1);await set('#pos-search','glucose','input');await until(`!!document.querySelector('.pos-product[data-product-id="${f.products.glucose}"] button')`,'Glucose original product card');
 await evaluate(`document.querySelector('.pos-product[data-product-id="${f.products.glucose}"] button').click()`);
 await until('!!document.querySelector(".pos-cart-row [data-discount-choice]")','cart discount');
 const selector='.pos-cart-row [data-discount-choice]';
 check(await evaluate(`JSON.stringify([...document.querySelector('${selector}').options].map(o=>o.value))`)==='["0","25","30","35","other"]','all five options');
 check(await evaluate(`document.querySelector('${selector}').value`)==='0','default zero');
 check(await evaluate(`(()=>{const e=document.querySelector('${selector}'),r=e.getBoundingClientRect();return !e.disabled&&r.width>100&&r.height>20&&getComputedStyle(e).visibility==='visible';})()`),'visible enabled actual cart selector');
 await set(selector,'other');check(await evaluate('!document.querySelector(".pos-cart-row [data-discount-custom]").disabled'),'custom input enabled');
 await set('.pos-cart-row [data-discount-custom]','12.50','input');await set(selector,'0');
 await evaluate('document.querySelector(".pos-cart-row [data-discount-choice]").scrollIntoView({behavior:"instant",block:"center"})');
 await until('(()=>{const r=document.querySelector(".pos-cart-row [data-discount-choice]").getBoundingClientRect();return r.top>=0&&r.bottom<innerHeight-80;})()','selector visible in screenshot viewport');
 const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(input.directory,'cart-controls-'+width+'.png'),Buffer.from(shot.data,'base64'));
 check(await evaluate('document.documentElement.scrollWidth<=innerWidth+2'),'no horizontal overflow');
 }
 fs.writeFileSync(path.join(input.directory,'result.json'),JSON.stringify({checks,original_template:'app/Views/invoices.php',base:input.base}));console.log('Original cart milestone: '+checks+' checks passed.');
})().catch(e=>{console.error(e.stack);process.exitCode=1;}).finally(async()=>{completed=true;try{if(ws?.readyState===1)await send('Browser.close',{},false);}catch{}ws?.close();browser.kill();});
