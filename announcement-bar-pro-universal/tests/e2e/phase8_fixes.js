// Regression tests for the v2.0.1 fixes, on real OpenCart (OCBASE / OCDB / OCDIR env).
const L=require('./lib');const {execFileSync}=require('child_process');const fs=require('fs');
const q=s=>{try{return execFileSync('mysql',[process.env.OCDB,'-N','-e',s],{stdio:['pipe','pipe','ignore']}).toString().trim();}catch(e){return 'SQLERR';}};
const LOG=process.env.OCDIR+'/system/storage/logs/error.log';const logSize=()=>fs.existsSync(LOG)?fs.statSync(LOG).size:0;
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
(async()=>{const b=await L.chromium.launch();const ctx=await b.newContext();const p=await ctx.newPage();const bag=[];L.errs(p,bag);
 const tok=await L.login(p);const A=r=>L.BASE+'admin/index.php?route='+r+'&user_token='+tok;
 const setting=async(fn)=>{await p.goto(A('extension/module/announcement_bar_pro'));await p.click('a[href="#tab-settings"]');await fn();await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);};
 const add=async(o)=>{await p.goto(A('extension/module/announcement_bar_pro/form'));
   await p.fill('textarea[name="message_description[1][text]"]',o.text);
   if(o.link_text) await p.fill('input[name="message_description[1][link_text]"]',o.link_text);
   if(o.link) await p.fill('#input-link',o.link);
   if(o.routes){await p.click('a[href="#tab-visibility"]');await p.selectOption('#input-page-mode','include');await p.fill('#input-page-routes',o.routes);}
   await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);
   return p.$$eval('.text-danger',x=>x.map(y=>y.innerText.trim()).filter(Boolean));};
 const clear=async()=>{q('DELETE FROM oc_abp_message');q('DELETE FROM oc_abp_message_description');};
 const log0=logSize();
 await clear();
 await setting(async()=>{await p.selectOption('#input-status','1');await p.selectOption('#input-mode','stack');await p.fill('#input-dismiss-days','1');await p.uncheck('#input-allow-html');});

 // 1. emoji
 let e=await add({text:'🚚 Bezmaksas piegāde 🎁',link_text:'Iepirkties 🛒',link:'/akcija-šodien'});
 ok('emoji + non-ASCII link accepted', e.length===0, e.join('|'));
 ok('emoji stored as entities (not ????)', /&#x1F69A;/.test(q("select text from oc_abp_message_description")) && !/\?\?/.test(q("select concat(text,link_text) from oc_abp_message_description")), q("select concat(text,' | ',link_text) from oc_abp_message_description"));
 const s=await ctx.newPage();L.errs(s,bag);await s.goto(L.BASE);
 let t=(await s.innerText('#abp-bar')).replace(/\s+/g,' ').trim();
 ok('emoji rendered in store', t.includes('🚚 Bezmaksas piegāde 🎁') && t.includes('Iepirkties 🛒'), t);
 ok('non-ASCII link percent-encoded', await s.getAttribute('.abp-btn','href')==='/akcija-%C5%A1odien');
 await p.goto(A('extension/module/announcement_bar_pro/form')+'&message_id='+q('select max(message_id) from oc_abp_message'));
 ok('emoji shown back in admin form', (await p.inputValue('textarea[name="message_description[1][text]"]'))==='🚚 Bezmaksas piegāde 🎁');

 // 2. double encoding: HTML on, text without tags; then HTML off
 await clear();
 await setting(async()=>{await p.check('#input-allow-html');});
 await add({text:'Tom & Jerry "50%"'});
 await s.goto(L.BASE);t=(await s.innerText('#abp-bar')).trim();
 ok('HTML on, no tags: shown once-encoded', t.startsWith('Tom & Jerry "50%"'), t);
 await add({text:'<b>Bold & "q"</b>'});
 await setting(async()=>{await p.uncheck('#input-allow-html');});
 await s.goto(L.BASE);t=(await s.$$eval('#abp-bar .abp-item',x=>x.map(y=>y.innerText.trim())));
 ok('HTML stored, then switched off: no &amp; visible', t.includes('Tom & Jerry "50%"') && t.includes('Bold & "q"'), JSON.stringify(t));
 await p.goto(A('extension/module/announcement_bar_pro')+'&tab=messages');
 const list=await p.$$eval('#tab-messages tbody td:nth-child(2)',x=>x.map(y=>y.innerText.trim()));
 ok('admin list: no &amp; visible', list.some(t=>t.startsWith('Tom & Jerry "50%"')) && list.some(t=>t.startsWith('Bold & "q"')) && !list.some(t=>/&amp;/.test(t)), JSON.stringify(list));

 // 3. {countdown} inside href stays, text one replaced
 await setting(async()=>{await p.check('#input-allow-html');});
 await p.goto(A('extension/module/announcement_bar_pro/form'));
 await p.fill('textarea[name="message_description[1][text]"]','<a href="/x?a={countdown}">go</a> left {countdown}');
 await p.click('a[href="#tab-schedule"]');await p.fill('#input-date_end',new Date(Date.now()+86400e3*3).toISOString().slice(0,16).replace('T',' '));await p.check('#input-countdown');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);
 await s.goto(L.BASE);
 const cdHtml=await s.$eval('#abp-bar',x=>x.outerHTML);
 ok('{countdown}: never inside an attribute, text placeholder replaced by timer', !/="[^"]*(<span|\{countdown\})/.test(cdHtml) && /left <span class="abp-countdown"/.test(cdHtml) && !/\{countdown\}/.test(cdHtml.replace(/<[^>]*>/g,'')));

 // 4. dismiss applies across pages
 await clear();
 await add({text:'ALL pages'});await add({text:'PRODUCT only',routes:'product/*'});
 const d=await (await b.newContext()).newPage();L.errs(d,bag);
 await d.goto(L.BASE+'index.php?route=product/product&product_id=40');
 ok('product page shows 2 messages', (await d.$$('#abp-bar .abp-item')).length===2);
 await d.click('.abp-close');await d.goto(L.BASE);
 ok('dismissed on product page → hidden on home too', await d.$eval('#abp-bar',x=>getComputedStyle(x).display==='none'));

 // 5. stale localStorage must not override sessionStorage dismiss
 await setting(async()=>{await p.fill('#input-dismiss-days','0');});
 await d.evaluate(()=>{localStorage.clear();sessionStorage.clear();localStorage.setItem('abp_dismiss2',JSON.stringify({k:['stale-key'],t:Date.now()+86400000}));});
 await d.goto(L.BASE);await d.click('.abp-close');await d.reload();
 ok('session dismiss works with stale localStorage entry', await d.$eval('#abp-bar',x=>getComputedStyle(x).display==='none'));
 ok('stale localStorage entry removed on dismiss', await d.evaluate(()=>localStorage.getItem('abp_dismiss2')===null && !!sessionStorage.getItem('abp_dismiss2')));

 // 6. array POST values → validation errors, no PHP notices
 const before=logSize();
 const r=await p.request.post(A('extension/module/announcement_bar_pro/form'),{multipart:{
   'link[]':'x','icon[]':'fa-x','date_start[]':'2026','date_end[a]':'b','sort_order[]':'1','page_mode':'include','page_routes[]':'a',
   'bg_color[]':'#000000','status[]':'1','stores':'notarray','message_description':'notarray'}});
 const body=await r.text();
 ok('array POST: handled (200, validation shown)', r.status()===200 && /text-danger/.test(body), 'status '+r.status());
 ok('array POST: no "Array to string" notice / warning', logSize()===before && !/Array to string|<b>(Warning|Notice|Deprecated)<\/b>/.test(body), 'log +'+(logSize()-before)+' bytes');

 // 7. viewer without modify must not run migrations
 q("UPDATE oc_setting SET value='2' WHERE `key`='module_announcement_bar_pro_schema'");
 const v=await (await b.newContext()).newPage();
 await v.goto(L.BASE+'admin/');await v.fill('#input-username','viewer');await v.fill('#input-password','viewer123');
 await Promise.all([v.waitForNavigation(),v.click('button[type=submit]')]);
 const vt=new URL(v.url()).searchParams.get('user_token');
 q("UPDATE oc_user_group SET permission='{\"access\":[\"extension/module/announcement_bar_pro\",\"common/dashboard\",\"marketplace/extension\"],\"modify\":[]}' WHERE name='Viewer'");
 await v.goto(L.BASE+'admin/index.php?route=extension/module/announcement_bar_pro&user_token='+vt);
 ok('viewer GET with outdated schema: no migration run', q("select value from oc_setting where `key`='module_announcement_bar_pro_schema'")==='2');
 ok('viewer redirected away from module page', !/route=extension\/module\/announcement_bar_pro/.test(v.url()), v.url().replace(/user_token=\w+/,'T'));
 await p.goto(A('extension/module/announcement_bar_pro'));
 ok('admin with modify: migration runs → current schema', +q("select value from oc_setting where `key`='module_announcement_bar_pro_schema'")>=5);

 ok('error.log unchanged during whole run', logSize()===log0, 'start '+log0+' end '+logSize());
 console.log(R.join('\n'));console.log('JS errors:',bag);await b.close();})().catch(e=>{console.error(e);process.exit(1)});
