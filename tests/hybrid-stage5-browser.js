'use strict';
const {spawn}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const input=JSON.parse(fs.readFileSync(0,'utf8')),f=input.fixtures;
const profile=path.join(input.directory,'browser-profile');fs.mkdirSync(profile);
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
browser.stderr.on('data',chunk=>fs.appendFileSync(path.join(input.directory,'edge-stderr.log'),chunk));
let ws,seq=0,sessionId,checks=0;const waiting=new Map();
const check=(ok,label)=>{assert.ok(ok,label);checks++;};
async function connect(){const endpoint=await new Promise((resolve,reject)=>{let buffer='';const timer=setTimeout(()=>reject(Error('Headless Edge did not start.')),20000);browser.stderr.on('data',chunk=>{buffer+=chunk;const match=buffer.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(match){clearTimeout(timer);resolve(match[1]);}});browser.on('exit',()=>reject(Error('Browser exited.')));});ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=e=>{const d=JSON.parse(e.data);if(d.id){const p=waiting.get(d.id);if(p){waiting.delete(d.id);d.error?p.reject(Error(d.error.message)):p.resolve(d.result);}}};const target=await send('Target.createTarget',{url:'about:blank'},false);sessionId=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;await send('Page.enable');await send('Runtime.enable');await send('Network.enable');}
function send(method,params={},session=true){const id=++seq;return new Promise((resolve,reject)=>{waiting.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId}:{})}));});}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;}
async function until(expression,label,timeout=20000){const start=Date.now();while(Date.now()-start<timeout){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,80));}throw Error('Timed out: '+label+'; '+await evaluate('document.getElementById("return-message")?.textContent||document.body.textContent.slice(0,400)'));}
async function navigate(route){await send('Page.navigate',{url:input.base+route});await until("document.readyState==='complete'",'document loaded');}
async function login(actor='rep1',mode='enabled'){await evaluate(`fetch('/test/session',{method:'POST',headers:{'Content-Type':'application/json','X-Isolation-Test-Token':${JSON.stringify(input.key)}},body:JSON.stringify(${JSON.stringify({actor,mode})})}).then(r=>r.json())`);}
async function set(id,value,event='change'){await evaluate(`(()=>{const e=document.getElementById(${JSON.stringify(id)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event(${JSON.stringify(event)},{bubbles:true}));})()`);}
async function click(id){await evaluate(`document.getElementById(${JSON.stringify(id)}).click()`);}
(async()=>{
 await connect();await navigate('/rep/invoices/'+input.record.invoice+'/a4');check(await evaluate('!document.querySelector(".hybrid-sheet")'),'anonymous document denied');
 await login('rep1','feature-disabled');
 for(const kind of ['invoice','return','void'])for(const locale of ['en','si-LK'])for(const format of ['58','80','a4']){
  const suffix=kind==='invoice'?'':'/hybrid-returns/'+input.record[kind];const route='/rep/invoices/'+input.record.invoice+suffix+'/'+(format==='a4'?'a4':'receipt')+'?document_lang='+locale+'&paper='+format;
  await navigate(route);await until('!!document.querySelector(".hybrid-sheet")','document rendered');await evaluate('document.fonts.ready.then(()=>true)');
  check(await evaluate('document.documentElement.lang')===locale,'language '+kind+locale+format);
  check(await evaluate(`document.body.textContent.includes(${JSON.stringify(locale==='en'?'Actual cash refunds are not recorded by this system.':'සැබෑ මුදල් ආපසු ගෙවීම් මෙම පද්ධතියේ සටහන් නොවේ.')})`),'refund liability wording');
  check(await evaluate('document.querySelectorAll("form").length===0'),'read-only document');
 }
 await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await navigate('/rep/invoices/'+input.record.invoice+'/receipt?document_lang=si-LK&paper=58');await evaluate('document.fonts.ready.then(()=>true)');
 check(await evaluate('document.documentElement.scrollWidth<=391'),'phone no overflow');check(await evaluate('document.fonts.check("12px HybridSinhala","ග්ලූකෝස්")'),'Sinhala font loaded');
 const image=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(input.directory,'document-si-phone.png'),Buffer.from(image.data,'base64'));
 // Leave the document's deliberately restrictive CSP before testing API downloads.
 await navigate('/rep/invoices/new');await until('!!document.getElementById("invoice-form")','same-origin non-document test context');
 const pdf=await evaluate(`fetch('/rep/invoices/${input.record.invoice}/hybrid-returns/${input.record.return}/pdf?document_lang=si-LK&layout=receipt&paper=58',{cache:'no-store'}).then(async r=>({status:r.status,type:r.headers.get('content-type'),cache:r.headers.get('cache-control'),prefix:new TextDecoder().decode(new Uint8Array(await r.arrayBuffer()).slice(0,5))}))`);
 check(pdf.status===200&&pdf.type==='application/pdf'&&pdf.prefix==='%PDF-','authenticated PDF route');check(pdf.cache.includes('no-store'),'private PDF never cached');
 for(const type of ['hybridbenefits','hybridproducts','hybridreturns','hybridvoids','hybridactivity','hybridstock']){const response=await evaluate(`fetch('/rep/reports/${type}/csv',{cache:'no-store'}).then(async r=>({status:r.status,text:await r.text(),cache:r.headers.get('cache-control')}))`);check(response.status===200&&response.text.length>20,'authorized private CSV '+type);check(response.cache.includes('no-store'),'CSV private '+type);}
 await login('rep2','feature-disabled');const denied=await evaluate(`fetch('/rep/invoices/${input.record.invoice}/hybrid-returns/${input.record.return}/a4').then(r=>r.status)`);check([403,404].includes(denied),'different rep denied');
 await login('admin','feature-disabled');await navigate('/admin/invoices/'+input.record.invoice+'/hybrid-returns/'+input.record.void+'/a4?document_lang=en');await until('!!document.querySelector(".hybrid-sheet")','admin document rendered');check(await evaluate('document.body.textContent.includes("Residual void")'),'admin residual document');
 console.log('Stage 5 real Edge: '+checks+' checks passed, including 18 bilingual layouts, disabled-gate historical access, PDF route, private CSV, representative scope and phone rendering.');
})().catch(e=>{console.error(e.stack);process.exitCode=1;}).finally(async()=>{try{if(ws){await send('Browser.close',{},false);ws.close();}}catch{}browser.kill();});
