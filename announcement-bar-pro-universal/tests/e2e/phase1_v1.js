const L=require('./lib');const S=process.argv[2];
(async()=>{const b=await L.chromium.launch();const p=await b.newPage();const bag=[];L.errs(p,bag);
 const tok=await L.login(p);
 console.log('upload v1:',await L.uploadExt(p,tok,S+'/v1/announcement-bar-pro/dist/announcement_bar_pro_v1.0.0.ocmod.zip'));
 // install via the Extensions > Modules list (real UI link)
 await p.goto(L.BASE+'admin/index.php?route=marketplace/extension&type=module&user_token='+tok);
 await p.waitForSelector('a[href*="extension=announcement_bar_pro"]',{timeout:20000});
 await p.click('a[href*="install"][href*="extension=announcement_bar_pro"]');
 await p.waitForTimeout(2000);
 await p.goto(L.BASE+'admin/index.php?route=extension/module/announcement_bar_pro&user_token='+tok);
 await p.selectOption('#input-status','1');
 await p.fill('textarea[name="module_announcement_bar_pro_text[1]"]','V1 legacy text <b>x</b>');
 await p.fill('#input-bg-color','#0055aa');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-module]')]);
 console.log('after save url:',p.url().includes('marketplace/extension'));
 await p.goto(L.BASE); const bar=await p.$('#announcement-bar-pro'); console.log('v1 bar on store:', bar?await bar.innerText():'NONE');
 console.log('errors:',bag);await b.close();})().catch(e=>{console.error(e);process.exit(1)});
