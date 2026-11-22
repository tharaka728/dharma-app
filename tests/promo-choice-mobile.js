'use strict';
const {spawn}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const input=JSON.parse(fs.readFileSync(0,'utf8'));const profile=path.resolve('storage','promo-choice-mobile-'+crypto.randomUUID());fs.mkdirSync(profile,{recursive:true});
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let ws,seq=0,sessionId,count=0;const waiting=new Map();
const check=(ok,label)=>{assert.ok(ok,label);count++;};
async function connect(){const endpoint=await new Promise((resolve,reject)=>{let buffer='';const timer=setTimeout(()=>reject(Error('Headless browser did not start.')),20000);browser.stderr.on('data',chunk=>{buffer+=chunk;const found=buffer.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(found){clearTimeout(timer);resolve(found[1]);}});browser.on('exit',()=>reject(Error('Headless browser exited.')));});ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=waiting.get(data.id);if(p){waiting.delete(data.id);data.error?p.reject(Error(data.error.message)):p.resolve(data.result);}}};const target=await send('Target.createTarget',{url:'about:blank'},false);sessionId=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;await send('Page.enable');await send('Runtime.enable');await send('Network.enable');}
function send(method,params={},session=true){const id=++seq;return new Promise((resolve,reject)=>{waiting.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId}:{})}));});}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error('Browser evaluation failed: '+(r.exceptionDetails.exception?.description||r.exceptionDetails.text));return r.result.value;}
async function until(expression,label,timeout=15000){const start=Date.now();while(Date.now()-start<timeout){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100));}throw Error('Timed out: '+label);}
async function navigate(url){await send('Page.navigate',{url});await until(`document.readyState==='complete'&&location.href===${JSON.stringify(url)}`,'document loaded '+url);}
async function shot(name){fs.writeFileSync(path.resolve('storage',name),Buffer.from((await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));}
async function viewport(width){await send('Emulation.setDeviceMetricsOverride',{width,height:860,deviceScaleFactor:width<500?2:1,mobile:width<500});await new Promise(r=>setTimeout(r,300));}
(async()=>{
 await connect();await navigate(input.base+'/login');
 await evaluate(`(async()=>{const h=await(await fetch('/login')).text();const csrf=h.match(/name="csrf_token" value="([a-f0-9]{64})"/)[1];await fetch('/login',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:csrf,username:${JSON.stringify(input.actors.rep.username)},password:${JSON.stringify(input.password)}})});})()`);
 await navigate(input.base+'/rep/invoices/new?language=si-LK');
 await evaluate('document.getElementById("customer_id").value='+JSON.stringify(input.customer)+';document.getElementById("customer_id").dispatchEvent(new Event("change",{bubbles:true}));');
 await evaluate('document.getElementById("pos-search").value='+JSON.stringify(input.checkout_skus[0])+';document.getElementById("pos-search").dispatchEvent(new Event("input"))');
 await until('!!document.querySelector('+JSON.stringify('.pos-product[data-product-id="'+input.products[0]+'"] button')+')','catalogue product');
 await evaluate('document.querySelector('+JSON.stringify('.pos-product[data-product-id="'+input.products[0]+'"] button')+').click()');
 await evaluate('document.querySelector(".pos-stepper input").value="4";document.querySelector(".pos-stepper input").dispatchEvent(new Event("input",{bubbles:true}))');
 await until('!document.querySelector(".pos-pay").disabled','baseline preview');
 await evaluate('document.getElementById("benefit_mode_promotion_discount_instead").click()');
 await evaluate('document.getElementById("promotion_discount_choice_25").click()');
 await until('document.getElementById("invoice-live-summary").textContent.includes("වට්ටම")','Sinhala benefit preview');
 for(const width of [320,360,393,430,768,1280]){
  await viewport(width);
  check(await evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth+1'),'No horizontal scrolling at '+width+'px');
  check(await evaluate('(()=>{const r=document.getElementById("invoice-benefit-fields").getBoundingClientRect();return r.width>0&&r.left>=-1&&r.right<=document.documentElement.clientWidth+1})()'),'Benefit mode control fits at '+width+'px');
  check(await evaluate('(()=>{const r=document.getElementById("promotion-discount-rate").getBoundingClientRect();return r.width>0&&r.right<=document.documentElement.clientWidth+1})()'),'Discount choice control fits at '+width+'px');
  check(await evaluate('(()=>{const r=document.querySelector(".pos-pay").getBoundingClientRect();return r.width>0&&r.right<=document.documentElement.clientWidth+1&&!document.querySelector(".pos-pay").disabled})()'),'Checkout button usable at '+width+'px');
  if(width===360)await shot('promo-choice-mobile-360-si.png');
  if(width===768)await shot('promo-choice-mobile-768-si.png');
 }
 check(await evaluate('document.querySelector("label[for=benefit_mode_promotion_discount_instead]").textContent.includes("නොමිලේ භාණ්ඩ වෙනුවට වට්ටම")'),'Spec Sinhala mode label on the selected option');
 check(await evaluate('document.getElementById("invoice-live-summary").textContent.includes("125.50")'),'Benefit amounts not clipped in summary');
 console.log(count+' benefit mobile layout checks passed.');
})().catch(e=>{console.error(e.message);process.exitCode=1;}).finally(async()=>{if(ws?.readyState===WebSocket.OPEN){try{await send('Browser.close',{},false);}catch{}ws.close();}browser.kill();await new Promise(r=>setTimeout(r,600));if(path.dirname(profile)===path.resolve('storage')&&/^promo-choice-mobile-[a-f0-9-]+$/.test(path.basename(profile)))await fs.promises.rm(profile,{recursive:true,force:true,maxRetries:12,retryDelay:300});setTimeout(()=>process.exit(process.exitCode||0),500).unref();});
