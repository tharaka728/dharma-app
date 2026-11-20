const fs=require('fs');const catalogue=JSON.parse(fs.readFileSync('public/assets/i18n/si-LK.json','utf8'));
fs.writeFileSync('public/assets/i18n/catalogue.js',"'use strict';\nglobalThis.DharmaTranslations="+JSON.stringify(catalogue)+';\n');
const sources=['storage/localization-view-keys.json','storage/localization-client-keys.json'];let requested=new Set();for(const file of sources)if(fs.existsSync(file))for(const key of JSON.parse(fs.readFileSync(file,'utf8')))requested.add(key);
for(const match of fs.readFileSync('public/offline/index.html','utf8').matchAll(/data-i18n(?:-placeholder)?="([^"]+)"/g))requested.add(match[1].replaceAll('&quot;','"').replaceAll('&amp;','&').replaceAll('&lt;','<').replaceAll('&gt;','>'));
if(fs.existsSync('storage/localization-report-keys.json'))for(const key of JSON.parse(fs.readFileSync('storage/localization-report-keys.json','utf8')))requested.add(key);
if(fs.existsSync('storage/localization-audit.json'))for(const {text} of JSON.parse(fs.readFileSync('storage/localization-audit.json','utf8')))requested.add(text);
const missing=[...requested].filter(key=>!Object.hasOwn(catalogue,key)).sort();fs.writeFileSync('storage/localization-missing.json',JSON.stringify(missing,null,2));console.log(JSON.stringify({dictionary:Object.keys(catalogue).length,audited:requested.size,untranslated:missing.length}));
