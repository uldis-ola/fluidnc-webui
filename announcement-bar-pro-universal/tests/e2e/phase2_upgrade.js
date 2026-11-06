const L=require('./lib');const S=process.argv[2];
(async()=>{const b=await L.chromium.launch();const p=await b.newPage();const bag=[];L.errs(p,bag);
 const tok=await L.login(p);
 console.log('upload v2:',await L.uploadExt(p,tok,process.argv[3]));
 // before admin opens: storefront legacy fallback
 await p.goto(L.BASE); let bar=await p.$('#abp-bar'); console.log('store before admin visit:', bar?await bar.innerText():'NONE');
 await p.goto(L.BASE+'admin/index.php?route=extension/module/announcement_bar_pro&user_token='+tok);
 console.log('admin title:',await p.title());
 console.log('list rows:',(await p.$$eval('#tab-messages tbody tr',r=>r.map(x=>x.innerText.replace(/\s+/g,' ').trim()))));
 await p.goto(L.BASE); bar=await p.$('#abp-bar'); console.log('store after migration:', bar?(await bar.innerText()).trim():'NONE');
 console.log('errors:',bag);await b.close();})().catch(e=>{console.error(e);process.exit(1)});
