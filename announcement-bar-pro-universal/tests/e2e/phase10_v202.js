// Tests for the v2.0.2 changes (needs: store_id 1, language lv-lv as language_id 2, viewer user). Env: OCBASE OCDB OCDIR.
const L=require('./lib');const {execFileSync}=require('child_process');const fs=require('fs');
const q=s=>{try{return execFileSync('mysql',[process.env.OCDB,'-N','-e',s],{stdio:['pipe','pipe','ignore']}).toString().trim();}catch(e){return 'SQLERR';}};
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
const clearCache=()=>execFileSync('find',[process.env.OCDIR+'/system/storage/cache','-maxdepth','1','-name','cache.*','-delete']);
(async()=>{const b=await L.chromium.launch();const ctx=await b.newContext({viewport:{width:1280,height:800}});const p=await ctx.newPage();const bag=[];L.errs(p,bag);
 const tok=await L.login(p);const A=r=>L.BASE+'admin/index.php?route='+r+'&user_token='+tok;
 const errs=()=>p.$$eval('.text-danger',x=>x.map(y=>y.innerText.trim()).filter(Boolean));
 const setting=async(fn)=>{await p.goto(A('extension/module/announcement_bar_pro')+'&tab=settings');await fn();await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);};
 q('DELETE FROM oc_abp_message');q('DELETE FROM oc_abp_message_description');
 await setting(async()=>{await p.selectOption('#input-status','1');await p.selectOption('#input-mode','rotate');await p.fill('#input-interval','2');await p.check('#input-dismissible');await p.fill('#input-dismiss-days','1');await p.fill('#input-font-size','14');await p.uncheck('#input-allow-html');});

 // default tab = messages
 await p.goto(A('extension/module/announcement_bar_pro'));
 ok('admin opens on Messages tab by default', await p.$eval('#tab-messages',e=>e.classList.contains('active')));

 // datepicker localisation variable
 await p.goto(A('extension/module/announcement_bar_pro/form'));
 ok('form passes datepicker language', /language: 'en(-|\\x2D|\\u002D)gb'/.test(await p.content()));

 // invalid store id rejected (not turned into "all")
 const r=await p.request.post(A('extension/module/announcement_bar_pro/form'),{form:{'message_description[1][text]':'X99','status':'1','sort_order':'0','bg_color':'#000000','text_color':'#ffffff','page_mode':'all','stores[]':'99'}});
 ok('unknown store id → validation error, nothing saved', /text-danger/.test(await r.text()) && q("select count(*) from oc_abp_message")==='0');

 // date-only end → 23:59:59
 await p.goto(A('extension/module/announcement_bar_pro/form'));
 await p.fill('textarea[name="message_description[1][text]"]','EN ONLY text');
 await p.click('a[href="#tab-schedule"]');await p.fill('#input-date_end','2099-12-31');await p.click('#input-date_start');
 const pickerVal=await p.inputValue('#input-date_end');
 await p.evaluate(()=>{document.getElementById('input-date_end').value='2099-12-31';});
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);
 ok('date-only end saved as end of day', q("select date_end from oc_abp_message")==='2099-12-31 23:59:59', q("select date_end from oc_abp_message")+' | date picker turned typed value into: '+pickerVal);

 // list flags missing language; language fallback in store
 const list=await p.$eval('#tab-messages tbody',e=>e.innerText);
 ok('admin list flags missing language (Latviešu)', /Latvi/.test(list), list.replace(/\s+/g,' ').slice(0,120));
 const s=await (await b.newContext()).newPage();L.errs(s,bag);
 await s.request.post(L.BASE+'index.php?route=common/language/language',{form:{code:'lv-lv',redirect:L.BASE}});
 await s.goto(L.BASE);
 ok('LV visitor sees default-language text when LV is empty', /EN ONLY text/.test(await s.innerText('#abp-bar').catch(()=>'')));
 ok('catalog close label from abp_ keys (lv)', await s.getAttribute('.abp-close','aria-label')==='Aizvērt');

 // edit non-existent message
 await p.goto(A('extension/module/announcement_bar_pro/form')+'&message_id=99999');
 ok('editing missing message → not-found error, no success', /not found/i.test(await p.innerText('.alert-danger').catch(()=>'')) && !(await p.$('.alert-success')));

 // contrast warning + colour picker labels
 await p.goto(A('extension/module/announcement_bar_pro/form'));
 await p.fill('#input-bg_color','#777777');await p.fill('#input-text_color','#888888');await p.dispatchEvent('#input-text_color','input');
 ok('low-contrast warning shown', await p.isVisible('#abp-contrast'));
 ok('colour pickers have aria-label', await p.$$eval('input[type=color]',x=>x.every(i=>i.getAttribute('aria-label'))));

 // routes > 50 rejected
 await p.fill('textarea[name="message_description[1][text]"]','R');await p.click('a[href="#tab-visibility"]');await p.selectOption('#input-page-mode','include');
 await p.fill('#input-page-routes',Array.from({length:51},(_,i)=>'route/r'+i).join('\n'));
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);
 ok('more than 50 routes → error', (await errs()).some(t=>/50/.test(t)));

 // plain mode: "a<b" kept, <br> → space
 const add=async(text)=>{await p.goto(A('extension/module/announcement_bar_pro/form'));await p.fill('textarea[name="message_description[1][text]"]',text);await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);return errs();};
 await add('Price a<b x < 5 <strong>st</strong><br>line2 <i class="fa fa-star"></i>ICON');
 q("UPDATE oc_abp_message SET icon='fa-truck', sort_order=-1 WHERE message_id=(SELECT id FROM (SELECT MAX(message_id) id FROM oc_abp_message) t)");
 const g=await (await b.newContext({viewport:{width:1280,height:800}})).newPage();L.errs(g,bag);
 await g.goto(L.BASE);
 const texts=await g.$$eval('#abp-bar .abp-item',x=>x.map(y=>y.innerText.replace(/\s+/g,' ').trim()));
 ok('plain mode keeps "a<b" and "x < 5", <br> becomes a space', texts.some(t=>t.includes('Price a<b x < 5 st line2')), JSON.stringify(texts));

 // icon scales with font size
 await setting(async()=>{await p.fill('#input-font-size','30');});
 await g.goto(L.BASE);
 const fs1=await g.$eval('#abp-bar i.fa',e=>getComputedStyle(e).fontSize);
 ok('icon font-size follows bar font size (30px)', fs1==='30px', fs1);
 await setting(async()=>{await p.fill('#input-font-size','14');});

 // rotation does not change page height (button vs no button)
 q("UPDATE oc_abp_message_description SET link_text='Shop now' WHERE message_id=(SELECT id FROM (SELECT MIN(message_id) id FROM oc_abp_message) t)");
 q("UPDATE oc_abp_message SET link='/sale' WHERE message_id=(SELECT id FROM (SELECT MIN(message_id) id FROM oc_abp_message) t)");
 await g.goto(L.BASE);await g.mouse.move(600,700);
 const hs=new Set();for(let i=0;i<6;i++){hs.add(await g.$eval('#abp-bar',e=>e.offsetHeight));await g.waitForTimeout(1100);}
 ok('rotate: bar height constant while rotating', hs.size===1, [...hs].join(','));

 // focus after close goes to next element, not body
 await g.focus('.abp-close');await g.keyboard.press('Enter');
 const fe=await g.evaluate(()=>document.activeElement&&document.activeElement.tagName+'#'+(document.activeElement.id||''));
 ok('keyboard close → focus moves to next element (not BODY)', fe && !/^BODY/.test(fe), fe);

 // dismissible off → stored dismissal ignored
 await setting(async()=>{await p.uncheck('#input-dismissible');});
 await g.goto(L.BASE);
 ok('close button off: earlier dismissal no longer hides bar', await g.$eval('#abp-bar',e=>getComputedStyle(e).display!=='none') && !(await g.$('.abp-close')));
 await setting(async()=>{await p.check('#input-dismissible');});

 // end time passes while page is open (no countdown)
 q("DELETE FROM oc_abp_message WHERE message_id NOT IN (SELECT id FROM (SELECT MIN(message_id) id FROM oc_abp_message) t)");
 await add('SOON GONE');await add('STAYS');
 q("UPDATE oc_abp_message m JOIN oc_abp_message_description d USING(message_id) SET m.date_end = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 8 SECOND) WHERE d.text='SOON GONE'");
 const e2=await (await b.newContext()).newPage();L.errs(e2,bag);await e2.goto(L.BASE);
 const before=await e2.$$eval('#abp-bar .abp-item',x=>x.map(y=>y.innerText.trim()));
 await e2.waitForTimeout(10000);
 const after=await e2.$$eval('#abp-bar .abp-item',x=>x.map(y=>y.innerText.trim()));
 ok('message disappears when end time passes on an open page', before.some(t=>t.includes('SOON GONE')) && !after.some(t=>t.includes('SOON GONE')) && after.some(t=>t.includes('STAYS')), JSON.stringify(before)+' → '+JSON.stringify(after));

 // <body> inside an HTML comment in the theme header is skipped
 const hdr=process.env.OCDIR+'/catalog/view/theme/default/template/common/header.twig';const orig=fs.readFileSync(hdr,'utf8');
 fs.writeFileSync(hdr,orig.replace('<head>','<head>\n<!-- <body class="fake"> -->\n<script>var x="<body>";</script>'));clearCache();
 await new Promise(r=>setTimeout(r,3000));
 const html=await (await e2.request.get(L.BASE)).text();
 fs.writeFileSync(hdr,orig);clearCache();
 const iBar=html.indexOf('id="abp-bar"'), iBody=html.search(/<body[^>]*>\s*\n?\s*<div id="abp-bar"/);
 ok('bar injected after the real <body>, not inside comment/script', iBar>0 && iBody>0 && html.indexOf('<!-- <body class="fake"> -->')<iBody && /var x="<body>";<\/script>/.test(html));

 ok('error.log empty', !fs.existsSync(process.env.OCDIR+'/system/storage/logs/error.log') || fs.statSync(process.env.OCDIR+'/system/storage/logs/error.log').size===0);
 console.log(R.join('\n'));console.log('JS errors:',bag);await b.close();})().catch(e=>{console.error(e);process.exit(1)});
