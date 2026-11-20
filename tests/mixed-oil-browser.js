'use strict';
const {spawn}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const input=JSON.parse(fs.readFileSync(0,'utf8'));const profile=path.resolve('storage','phase12-browser-'+crypto.randomUUID());fs.mkdirSync(profile,{recursive:true});
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let ws,seq=0,sessionId,count=0;const waiting=new Map();
const check=(ok,label)=>{assert.ok(ok,label);count++;};
async function connect(){const endpoint=await new Promise((resolve,reject)=>{let buffer='';const timer=setTimeout(()=>reject(Error('Headless browser did not start.')),20000);browser.stderr.on('data',chunk=>{buffer+=chunk;const found=buffer.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(found){clearTimeout(timer);resolve(found[1]);}});browser.on('exit',()=>reject(Error('Headless browser exited.')));});ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=waiting.get(data.id);if(p){waiting.delete(data.id);data.error?p.reject(Error(data.error.message)):p.resolve(data.result);}}};const target=await send('Target.createTarget',{url:'about:blank'},false);sessionId=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;await send('Page.enable');await send('Runtime.enable');await send('Network.enable');}
function send(method,params={},session=true){const id=++seq;return new Promise((resolve,reject)=>{waiting.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId}:{})}));});}
async function evaluate(expression){if(/^(?:await\b|\(await\b|OfflineDomain\.available\(await\b)/.test(expression))expression='(async()=>('+expression+'))()';const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error('Browser evaluation failed: '+(r.exceptionDetails.exception?.description||r.exceptionDetails.text));return r.result.value;}
async function until(expression,label,timeout=15000){const start=Date.now();while(Date.now()-start<timeout){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100));}throw Error('Timed out: '+label+'; '+await evaluate('document.getElementById("message")?.textContent||document.title'));}
async function login(actor){await evaluate(`(async()=>{const h=await(await fetch('/login')).text();const csrf=h.match(/name="csrf_token" value="([a-f0-9]{64})"/)[1];await fetch('/login',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:csrf,username:${JSON.stringify(actor.username)},password:${JSON.stringify(input.password)}})});})()`);}
async function logout(){await evaluate(`(async()=>{const h=await(await fetch('/rep')).text();const csrf=h.match(/name="csrf_token" value="([a-f0-9]{64})"/)[1];await fetch('/logout',{method:'POST',body:new URLSearchParams({csrf_token:csrf})});})()`);}
async function navigate(url){await send('Page.navigate',{url});await until(`document.readyState==='complete'&&location.href===${JSON.stringify(url)}`,'document loaded');}
const widths=[320,360,375,390,414,768,1024,1440];
async function viewport(width){await send('Emulation.setDeviceMetricsOverride',{width,height:950,deviceScaleFactor:1,mobile:width<768});await evaluate(`new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))`);}
async function fit(route){for(const width of widths){await viewport(width);await new Promise(r=>setTimeout(r,20));const bad=await evaluate(`({page:document.documentElement.scrollWidth,screen:innerWidth,offenders:[...document.querySelectorAll('body *')].filter(e=>!e.closest('.table-responsive')&&getComputedStyle(e).position!=='fixed'&&e.getBoundingClientRect().right>innerWidth+2).slice(0,4).map(e=>e.tagName+'.'+e.className)})`);check(bad.page<=bad.screen+1,`${route} overflow at ${width}: ${JSON.stringify(bad)}`);}}
(async()=>{
 await connect();await navigate(input.base+'/login');await login(input.actors.rep);
 await navigate(input.base+'/rep/invoices/new');
 await evaluate('document.getElementById("customer_id").value='+JSON.stringify(input.customer)+';document.getElementById("customer_id").dispatchEvent(new Event("change",{bubbles:true}));');
 for(const p of input.products_detail){
  await evaluate('document.getElementById("pos-search").value='+JSON.stringify(p.sku)+';document.getElementById("pos-search").dispatchEvent(new Event("input"));');
  await until('!!document.querySelector(\'.pos-product[data-product-id="'+p.id+'"] button\')','catalogue product');
  await evaluate('document.querySelector(\'.pos-product[data-product-id="'+p.id+'"] button\').click();');
 }
 await evaluate('for(const q of document.querySelectorAll(".pos-cart-row .pos-stepper input")){q.value="3";q.dispatchEvent(new Event("input",{bubbles:true}));q.dispatchEvent(new Event("change",{bubbles:true}));}');
 await until('document.querySelector("#oil-rewards").textContent.includes("1 earned")','combined six unit reward');
 check(await evaluate('document.querySelectorAll("#oil-rewards [data-oil-reward-row]").length===2'),'Both explicitly selected oils displayed');
 check(await evaluate('document.getElementById("oil-reward-'+input.products[0]+'").disabled'),'More expensive oil disabled by cheapest paid group price');
 await evaluate('const q=document.getElementById("oil-reward-'+input.products[1]+'");q.value="1";q.dispatchEvent(new Event("input",{bubbles:true}));');
 await until('document.querySelector("#oil-rewards").textContent.includes("0 still to select")','selected free oil validated');
 await fit('mixed oil mobile reward selector');await viewport(390);
 fs.writeFileSync(path.resolve('storage/mixed-oil-mobile.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));
 await navigate(input.base+'/offline/index.html');
 const phrase='Disposable mixed oil browser passphrase';
 await evaluate('document.getElementById("passphrase").value='+JSON.stringify(phrase)+';document.getElementById("passphrase-confirm").value='+JSON.stringify(phrase)+';document.getElementById("unlock-form").requestSubmit();');
 await until('!document.getElementById("workspace").hidden','offline unlocked');
 await evaluate('document.getElementById("provision").click()');await until('document.getElementById("message").textContent.includes("Registered and refreshed")','mixed capable snapshot');
 await evaluate('navigator.serviceWorker.ready.then(()=>true)');await until('!!navigator.serviceWorker.controller','updated offline worker');
 await send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
 await evaluate('location.hash="#new";document.getElementById("customer").value='+JSON.stringify(input.customer)+';document.getElementById("customer").dispatchEvent(new Event("change"));');
 for(const p of input.products_detail)await evaluate('document.querySelector(\'.pos-product[data-product-id="'+p.id+'"] button\').click();');
 await evaluate('for(const q of document.querySelectorAll(".pos-cart-row .pos-stepper input")){q.value="3";q.dispatchEvent(new Event("input",{bubbles:true}));q.dispatchEvent(new Event("change",{bubbles:true}));}');
 await until('document.querySelector("#oil-rewards").textContent.includes("1 earned")','offline combined reward');
 await evaluate('const q=document.getElementById("oil-reward-'+input.products[1]+'");q.value="1";q.dispatchEvent(new Event("input",{bubbles:true}));document.getElementById("sale-form").requestSubmit();');
 await until('!document.getElementById("review").hidden','offline mixed review');
 check(await evaluate('document.getElementById("review-content").textContent.includes("3 paid + 1 free")'),'Offline review separates paid and free oils');
 await fit('offline mixed oil reward selector');
 await evaluate('document.getElementById("save-sale").click()');await until('document.getElementById("message").textContent.includes("Saved locally")','encrypted mixed sale saved');
 check(await evaluate('(async()=>{const s=await OfflineStore.load();return s.sales.length===1&&s.sales[0].payload.oil_rewards[0].quantity==="1";})()'),'Signed saved sale retains free selection');
 await send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
 await evaluate('document.getElementById("sync").click()');await until('document.getElementById("message").textContent.includes("Synchronization and stock reconciliation finished")','mixed UI foreground synchronization');
 check(await evaluate('(async()=>{const s=await OfflineStore.load();return s.sales[0].status==="Synced"&&s.sales[0].reconciled;})()'),'Browser mixed sale acknowledged and stock reconciled');
 await evaluate('document.getElementById("lock").click()');await until('document.getElementById("workspace").hidden','locked');check(await evaluate('document.getElementById("oil-rewards").children.length===0'),'Lock clears reward product details');
 await logout();await login(input.actors.admin);await navigate(input.base+'/admin/promotions/'+input.promotion+'/edit');
 check(await evaluate('document.getElementById("mixed_oil").checked&&document.getElementById("mixed_product_ids").selectedOptions.length===2'),'Administrator can edit explicit group selection');
 await fit('mixed oil administrator product selector');
 console.log(count+' mixed oil browser, online/offline selector, eight-width mobile layout, encrypted save and real foreground synchronization checks passed.');
})().catch(e=>{console.error(e.message);process.exitCode=1;}).finally(async()=>{if(ws?.readyState===WebSocket.OPEN){try{await send('Browser.close',{},false);}catch{}ws.close();}browser.kill();await new Promise(r=>setTimeout(r,600));if(path.dirname(profile)===path.resolve('storage')&&/^phase12-browser-[a-f0-9-]+$/.test(path.basename(profile)))fs.rmSync(profile,{recursive:true,force:true,maxRetries:5,retryDelay:300});setTimeout(()=>process.exit(process.exitCode||0),500).unref();});
