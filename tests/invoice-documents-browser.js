'use strict';
const {spawn}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const input=JSON.parse(fs.readFileSync(0,'utf8'));const profile=path.resolve('storage','phase10-browser-'+crypto.randomUUID());fs.mkdirSync(profile,{recursive:true});
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
(async()=>{
 await connect();await navigate(input.base+'/login?language=en');
 for(const format of ['receipt','a4','pdf'])check(await evaluate(`fetch('/rep/invoices/${input.id}/${format}',{redirect:'manual'}).then(r=>r.status===0||r.status===302)`),'Anonymous document denied '+format);
 await login(input.actors.rep);
 for(const format of ['receipt','a4']){
  await navigate(input.base+'/rep/invoices/'+input.id+'/'+format+'?document_lang=en');check(await evaluate(`document.querySelector('h1').textContent==='Dharma Chemical'`),'Authenticated '+format);
  check(await evaluate(`document.body.textContent.includes('201.00')&&document.body.textContent.includes('PARTIAL')`),'Partial amount '+format);
  await send('Emulation.setDeviceMetricsOverride',{width:format==='receipt'?360:420,height:900,deviceScaleFactor:1,mobile:true});
  check(await evaluate(`document.documentElement.scrollWidth<=innerWidth`),'Mobile document does not overflow '+format);
  await send('Emulation.setEmulatedMedia',{media:'print'});check(await evaluate(`getComputedStyle(document.querySelector('.document-actions')).display==='none'`),'Toolbar hidden on paper '+format);
  check(await evaluate(`document.querySelector('.invoice-sheet').scrollWidth<=document.querySelector('.invoice-sheet').clientWidth+1`),'Print sheet does not clip '+format);
  const pdf=Buffer.from((await send('Page.printToPDF',{preferCSSPageSize:true,printBackground:true,displayHeaderFooter:false})).data,'base64');check(pdf.subarray(0,5).toString()==='%PDF-','Browser print PDF '+format);
  if(format==='receipt'){
   check(/\/MediaBox\s*\[0 0 22[67]/.test(pdf.toString('latin1')),'80mm physical page width');
   const image=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.resolve('storage/phase10-receipt-test.png'),Buffer.from(image.data,'base64'));
   await evaluate(`{const original=document.querySelector('.receipt-item');for(let i=1;i<100;i++)original.parentNode.append(original.cloneNode(true));}`);
   const large=Buffer.from((await send('Page.printToPDF',{preferCSSPageSize:true,displayHeaderFooter:false})).data,'base64');check((large.toString('latin1').match(/\/Type \/Page\b/g)||[]).length>2,'100-line thermal receipt paginates');
   check(await evaluate(`document.querySelector('.invoice-sheet').scrollWidth<=document.querySelector('.invoice-sheet').clientWidth+1`),'Long thermal document stays inside paper width');
  }else{const image=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.resolve('storage/phase10-a4-test.png'),Buffer.from(image.data,'base64'));}
  await send('Emulation.setEmulatedMedia',{media:''});await evaluate(`window.printCalls=0;window.print=()=>window.printCalls++;document.querySelector('[data-print-document]').click()`);check(await evaluate('window.printCalls===1'),'User print dialog trigger '+format);
 }
 const pdfResult=await evaluate(`(async()=>{const r=await fetch('/rep/invoices/${input.id}/pdf',{cache:'no-store'});const bytes=new Uint8Array(await r.arrayBuffer());return {status:r.status,type:r.headers.get('Content-Type'),cache:r.headers.get('Cache-Control'),name:r.headers.get('Content-Disposition'),prefix:String.fromCharCode(...bytes.slice(0,5)),length:bytes.length};})()`);
 check(pdfResult.status===200&&pdfResult.type==='application/pdf'&&pdfResult.prefix==='%PDF-'&&pdfResult.length>1000,'Authorized HTTP PDF download');check(pdfResult.cache.includes('private')&&pdfResult.cache.includes('no-store')&&/filename="[A-Za-z0-9_.-]+"/.test(pdfResult.name),'PDF private headers and safe filename');
 check(await evaluate(`fetch('/rep/invoices/${input.id}/pdf',{method:'POST'}).then(r=>r.status===405)`),'Document endpoint rejects POST');
 check(await evaluate(`fetch('/admin/invoices/${input.id}/pdf').then(r=>r.status===403)`),'Rep cannot use admin PDF route');
 await navigate(input.base+'/rep/invoices');check(await evaluate(`['Print Receipt','Print A4','Download PDF','Share Invoice'].every(t=>document.body.textContent.includes(t))`),'Rep history document actions');
 await logout();await login(input.actors.other);
 for(const format of ['receipt','a4','pdf'])check(await evaluate(`fetch('/rep/invoices/${input.id}/${format}').then(r=>r.status===404)`),'Other representative denied '+format);
 await logout();await login(input.actors.admin);
 await navigate(input.base+'/admin/invoices/'+input.void_id+'/a4?document_lang=en');check(await evaluate(`document.body.textContent.includes('VOID — NOT VALID FOR PAYMENT')&&document.body.textContent.includes('Refund Due')`),'Admin void document clear');
 check(await evaluate(`fetch('/admin/invoices/${input.credit_id}/receipt?document_lang=en').then(r=>r.text()).then(h=>h.includes('CREDIT'))`),'Credit receipt');
 await navigate(input.base+'/admin/invoices');check(await evaluate(`document.body.textContent.includes('Download PDF')`),'Admin history actions');
 await navigate(input.base+'/offline/index.html');check(await evaluate(`document.body.textContent.includes('PROVISIONAL — NOT A FINAL INVOICE')`),'Offline preview label');
 await evaluate(`navigator.serviceWorker.ready.then(()=>true)`);await until(`!!navigator.serviceWorker.controller`,'serviceworker active');
 const caches=await evaluate(`(async()=>{let a=[];for(const k of await window.caches.keys())for(const r of await(await window.caches.open(k)).keys())a.push(r.url);return a;})()`);check(caches.every(u=>!u.includes('/invoices/')),'Private documents absent from service worker cache');
 console.log(`${count} real browser document authorization, mobile layout, 80mm/A4 print, PDF download and cache checks passed.`);
})().catch(e=>{console.error(e.message);process.exitCode=1;}).finally(async()=>{
 if(ws?.readyState===WebSocket.OPEN){try{await send('Browser.close',{},false);}catch{}ws.close();}browser.kill();await new Promise(r=>setTimeout(r,600));
 if(path.dirname(profile)===path.resolve('storage')&&/^phase10-browser-[a-f0-9-]+$/.test(path.basename(profile)))fs.rmSync(profile,{recursive:true,force:true,maxRetries:5,retryDelay:300});
 setTimeout(()=>process.exit(process.exitCode||0),500).unref();
});
