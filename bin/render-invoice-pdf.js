'use strict';
// Trusted local Edge renderer. Invoice HTML travels only through stdin and CDP
// loopback. No remote URL, customer-data service, cookies or persistent PDF file.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),{spawn}=require('node:child_process');
let browser,ws,profile,sequence=0,sessionId;const pending=new Map();
function send(method,params={},session=true){const id=++sequence;return new Promise((resolve,reject)=>{pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId}:{})}));});}
async function main(){
 const raw=fs.readFileSync(0,'utf8');if(raw.length>2200000)throw Error('Input too large');const input=JSON.parse(raw);if(typeof input.html!=='string')throw Error('Invalid input');
 profile=fs.mkdtempSync(path.join(os.tmpdir(),'dharma-pdf-'));
 browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-extensions','--disable-sync','--disable-breakpad','--disable-crash-reporter','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
 const endpoint=await new Promise((resolve,reject)=>{let buffer='';browser.stderr.on('data',chunk=>{buffer+=chunk;const m=buffer.match(/DevTools listening on (ws:\/\/127\.0\.0\.1:[^\s]+)/);if(m)resolve(m[1]);});browser.once('error',reject);browser.once('exit',()=>reject(Error('Renderer exited')));});
 ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=e=>{const r=JSON.parse(e.data),p=pending.get(r.id);if(p){pending.delete(r.id);r.error?p.reject(Error('Renderer command failed')):p.resolve(r.result);}};
 const target=await send('Target.createTarget',{url:'about:blank'},false);sessionId=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;
 await send('Page.enable');await send('Network.enable');await send('Network.setBlockedURLs',{urls:['http://*','https://*','file://*','ftp://*','ws://*','wss://*']});await send('Emulation.setScriptExecutionDisabled',{value:true});
 const frame=(await send('Page.getFrameTree')).frameTree.frame.id;
 await send('Page.setDocumentContent',{frameId:frame,html:input.html});
 await send('Emulation.setEmulatedMedia',{media:'print'});
 const pdf=await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true,paperWidth:8.267717,paperHeight:11.692913,displayHeaderFooter:false});
 const bytes=Buffer.from(pdf.data,'base64');if(bytes.length>20971520)throw Error('PDF too large');process.stdout.write(bytes);
}
async function cleanup(){
 if(ws?.readyState===WebSocket.OPEN){try{await Promise.race([send('Browser.close',{},false),new Promise(r=>setTimeout(r,1500))]);}catch{}ws.close();}
 if(browser){browser.kill();await new Promise(r=>setTimeout(r,500));}
 if(profile){const target=path.resolve(profile),root=path.resolve(os.tmpdir());if(path.dirname(target)===root&&path.basename(target).startsWith('dharma-pdf-')){for(let i=0;i<5;i++){try{fs.rmSync(target,{recursive:true,force:true});break;}catch{await new Promise(r=>setTimeout(r,300));}}}}
}
const timer=setTimeout(()=>{process.exitCode=1;cleanup().finally(()=>process.exit(1));},35000);
main().catch(()=>{process.stderr.write('Local PDF generation failed.\n');process.exitCode=1;}).finally(async()=>{clearTimeout(timer);await cleanup();});
