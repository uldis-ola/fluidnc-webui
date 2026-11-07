const fs=require('fs');const J=process.argv[2]||require('path').join(__dirname,'../../../announcement-bar-pro/upload/catalog/view/javascript/announcement_bar_pro');process.chdir(__dirname);
const enc=s=>JSON.stringify(s).replace(/<\//g,'<\\/').replace(/<!--/g,'<\\!--');
let t=fs.readFileSync('template.html','utf8');
t=t.replace('%MODULE_CSS%',()=>enc(fs.readFileSync(J+'/announcement_bar_pro.css','utf8')))
 .replace('%MODULE_JS%',()=>enc(fs.readFileSync(J+'/announcement_bar_pro.js','utf8')))
 .replace('%MODULE_PRE%',()=>enc(fs.readFileSync(J+'/announcement_bar_pro_pre.js','utf8')))
 .replace('%OC3_COUNT%',process.env.OC3N||'—').replace('%OC3_RESULT%','visas izietas').replace('%OC4_COUNT%',process.env.OC4N||'—').replace('%OC4_RESULT%','visas izietas');
fs.writeFileSync('announcement-bar-demo.html',t);console.log(t.length);
