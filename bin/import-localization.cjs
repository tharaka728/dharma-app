const fs=require('fs');const target='public/assets/i18n/si-LK.json',values=JSON.parse(fs.readFileSync(target,'utf8'));
for(const file of process.argv.slice(2))Object.assign(values,JSON.parse(fs.readFileSync(file,'utf8')));
fs.writeFileSync(target,JSON.stringify(values,null,2)+'\n');
