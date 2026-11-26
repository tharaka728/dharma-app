'use strict';
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'..'),dictionary=JSON.parse(fs.readFileSync(path.join(root,'public/assets/i18n/hybrid-si-LK.json'),'utf8'));
const items=[];
for(const file of ['app/Views/hybrid-returns.php']){
 const source=fs.readFileSync(path.join(root,file),'utf8').replace(/<\?[\s\S]*?\?>/g,'');
 for(const match of source.matchAll(/>([^<>]+)</g)){const text=match[1].replace(/\s+/g,' ').trim();if(/[A-Za-z]/.test(text)&&!text.startsWith('//'))items.push({file,text,status:dictionary[text]?'SCOPED_DICTIONARY':'REVIEW_REMAINING_OR_SHARED'});}
}
for(const file of ['public/assets/js/hybrid-returns.js']){
 const source=fs.readFileSync(path.join(root,file),'utf8');
 for(const match of source.matchAll(/'([^'\\]*(?:\\.[^'\\]*)*)'|"([^"\\]*(?:\\.[^"\\]*)*)"/g)){const text=match[1]??match[2];if(/[A-Za-z][a-z]+\s+[A-Za-z]/.test(text)&&!/[{}<>#]|(?:querySelector|document\.)/.test(text))items.push({file,text,status:dictionary[text]?'SCOPED_DICTIONARY':'REVIEW_REMAINING_OR_TEMPLATE'});}
 for(const match of source.matchAll(/`([^`]+)`/g))if(/[A-Za-z][a-z]+\s+[A-Za-z]/.test(match[1]))items.push({file,text:match[1],status:'DYNAMIC_TEMPLATE_REVIEW'});
}
const reportSource=fs.readFileSync(path.join(root,'app/Services/Reports.php'),'utf8');
for(const line of reportSource.split('\n'))if(/hybridreturns|Linked credits use|Original immutable benefits/.test(line))items.push({file:'app/Services/Reports.php',text:line.trim(),status:'REPORT_WORDING_REVIEW_SERVER_CATALOGUE'});
const unique=[...new Map(items.map(x=>[x.file+'\0'+x.text,x])).values()];
const result={dictionary_entries:Object.keys(dictionary).length,static_source_candidates:unique.length,note:'Candidate inventory; includes source templates and shared translations. Rendered browser residuals are authoritative for tested screens. No native approval.',items:unique};
if(process.argv[2])fs.writeFileSync(process.argv[2],JSON.stringify(result,null,2));else console.log(JSON.stringify(result,null,2));
