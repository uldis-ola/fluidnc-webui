// Full end-to-end test of Announcement Bar Pro on real OpenCart 4 (env: OCBASE, OCDB, OCDIR; argv[2] = zip).
const {chromium,devices}=require('playwright');const {execFileSync}=require('child_process');const fs=require('fs');
const BASE=process.env.OCBASE, DB=process.env.OCDB, DIR=process.env.OCDIR, ZIP=process.argv[2], SH=process.env.SHOTS||'';
const q=s=>{try{return execFileSync('mysql',['--default-character-set=utf8mb4',DB,'-N','-e',s],{stdio:['pipe','pipe','ignore']}).toString().trim();}catch(e){return 'SQLERR';}};
const LOG=DIR+'/system/storage/logs/error.log';const logSize=()=>fs.existsSync(LOG)?fs.statSync(LOG).size:0;
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
const ROUTE='extension/announcement_bar_pro/module/announcement_bar_pro';
const OCV=fs.readFileSync(DIR+'/index.php','utf8').match(/define\('VERSION', '([^']+)'/)[1];const SEP=OCV.localeCompare('4.0.2.0',undefined,{numeric:true})>=0?'.':'|';
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const b=await chromium.launch();const bag=[];
 const watch=pg=>{pg.on('pageerror',e=>bag.push('JS: '+e.message));pg.on('console',m=>{if(m.type()==='error'&&!/favicon|404|Failed to load resource/.test(m.text()))bag.push('console: '+m.text())});return pg;};
 const ctx=await b.newContext({viewport:{width:1280,height:900}});const p=watch(await ctx.newPage());
 const log0=logSize();
 const login=async(pg,u,pw)=>{await pg.goto(BASE+'admin/');await pg.fill('#input-username',u);await pg.fill('#input-password',pw);await pg.click('button[type=submit]');await pg.waitForURL(/user_token=/,{timeout:15000});return new URL(pg.url()).searchParams.get('user_token');};
 const tok=await login(p,'admin','admin123');const A=(r,x='')=>BASE+'admin/index.php?route='+r+'&user_token='+tok+x;
 const ver=await p.evaluate(()=>document.body.innerText.match(/Version\s*([\d.]+)/i)?.[1]||'');

 // ---- install through Extension Installer (real UI)
 await p.goto(A('marketplace/installer'));
 const [fc]=await Promise.all([p.waitForEvent('filechooser'),p.click('#button-upload')]);await fc.setFiles(ZIP);
 const row=p.locator('#extension tr',{hasText:'Announcement Bar Pro'});
 await row.locator('.btn-success').waitFor({timeout:30000});
 await row.locator('.btn-success').click();
 await row.locator('.btn-danger').waitFor({timeout:60000}).catch(()=>{});
 await sleep(3000); // OPcache revalidate window
 ok('installer: package installed into extension/announcement_bar_pro', fs.existsSync(DIR+'/extension/announcement_bar_pro/admin/controller/module/announcement_bar_pro.php'));
 const inst=await (await p.request.get(A('extension/module'+SEP+'install','&extension=announcement_bar_pro&code=announcement_bar_pro'))).json().catch(e=>({err:String(e)}));
 ok('module install (Extensions → Modules → Install)', !!inst.success, JSON.stringify(inst));
 ok('events registered with correct actions', q("select group_concat(action order by action) from oc_event where code='announcement_bar_pro'")===ROUTE+SEP+'injectBar,'+ROUTE+SEP+'injectBarFooter,'+ROUTE+SEP+'resolveFallback', q("select group_concat(action) from oc_event where code='announcement_bar_pro'"));
 ok('tables created', q("show tables like 'oc_abp_message%'").split('\n').length===2);

 // ---- module page + settings (AJAX)
 await p.goto(A(ROUTE));
 ok('module page opens (messages tab)', (await p.title()).includes('Announcement Bar Pro') && await p.isVisible('#tab-messages'));
 await p.click('a[href="#tab-settings"]');
 await p.fill('#input-default-bg-color','red');await p.fill('#input-interval','999');
 await p.evaluate(()=>{document.getElementById('form-settings').noValidate=true;});
 await p.click('#form-settings button[type=submit]');await p.waitForSelector('#error-default-bg-color.d-block',{timeout:10000}).catch(()=>{});
 ok('settings: invalid colour/interval → field errors', await p.isVisible('#error-default-bg-color') && await p.isVisible('#error-interval'));
 await p.fill('#input-default-bg-color','#c0392b');await p.fill('#input-interval','2');
 await p.check('#input-status');
 await p.click('#form-settings button[type=submit]');await p.waitForSelector('#alert .alert-success',{timeout:10000}).catch(()=>{});
 ok('settings: saved via AJAX', q("select value from oc_setting where `key`='module_announcement_bar_pro_interval'")==='2');

 // ---- messages via form (AJAX save → redirect)
 const add=async(o)=>{await p.goto(A(ROUTE+SEP+'form'));
   await p.fill('#input-text-1',o.text);
   if(o.link_text) await p.fill('#input-link-text-1',o.link_text);
   if(o.link) await p.fill('#input-link',o.link);
   if(o.icon) await p.fill('#input-icon',o.icon);
   if(o.bg) await p.fill('#input-bg-color',o.bg);
   if(o.sort!==undefined) await p.fill('#input-sort-order',String(o.sort));
   if(o.end||o.countdown||o.start){await p.click('a[href="#tab-schedule"]');if(o.start)await p.fill('#input-date-start',o.start);if(o.end)await p.fill('#input-date-end',o.end);if(o.countdown)await p.check('#input-countdown');await p.click('a[href="#tab-content"]');}
   if(o.routes||o.store!==undefined){await p.click('a[href="#tab-visibility"]');if(o.store!==undefined)await p.check('#input-store-'+o.store);if(o.routes){await p.selectOption('#input-page-mode','include');await p.fill('#input-page-routes',o.routes);}}
   await p.evaluate(()=>{document.getElementById('form-message').noValidate=true;});
   await Promise.all([p.waitForResponse(r=>/saveMessage/.test(decodeURIComponent(r.url()))),p.click('button[form=form-message]')]);
   await sleep(800);
   return p.$$eval('.invalid-feedback.d-block',x=>x.map(y=>y.innerText.trim()).filter(Boolean));};
 const iso=d=>new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,16);
 let e=await add({text:'x',link:'javascript:alert(1)',icon:'fa-x" onmouseover="1',countdown:true});
 ok('form: js link / bad icon / countdown without end rejected', e.length>=3, e.join(' | '));
 e=await add({text:'Bezmaksas piegāde 🚚 — beidzas pēc {countdown}',link_text:'Uz akcijām',link:'index.php?route=product/special',icon:'fa-truck',bg:'#1e8449',sort:1,end:iso(new Date(Date.now()+2*86400e3+3600e3)),countdown:true});
 ok('form: valid message saved (redirect)', e.length===0 && /tab=messages/.test(p.url()), e.join('|'));
 await add({text:'Only on product pages',routes:'product/product',sort:2});
 await add({text:'Store two only',store:0,sort:3}); // store 0 ticked → visible on default store only
 await add({text:"Quote ' & <script>alert(1)</script> test",sort:4});
 ok('admin list shows 4 messages', (await p.$$('#tab-messages tbody tr')).length===4);
 if(SH) await p.screenshot({path:SH+'oc4_admin_list.png',fullPage:true});

 // ---- storefront
 const s=watch(await (await b.newContext({viewport:{width:1280,height:800}})).newPage());
 await s.goto(BASE);await sleep(600);
 let items=await s.$$eval('#abp-bar .abp-item',x=>x.map(y=>y.innerText.replace(/\s+/g,' ').trim()));
 ok('store: bar injected after <body>', await s.evaluate(()=>document.body.firstElementChild&&document.body.firstElementChild.id==='abp-bar'));
 ok('store: home shows 3 messages (not product-only)', items.length===3 && !items.some(t=>/Only on product/.test(t)), JSON.stringify(items));
 ok('store: emoji + countdown + button rendered', items.some(t=>/🚚/.test(t)&&/\d+d\.? \d\d:\d\d:\d\d/.test(t)&&/Uz akcijām/.test(t)), JSON.stringify(items));
 ok('store: Font Awesome 6 icon class', !!(await s.$('#abp-bar i.fa-solid.fa-truck')));
 ok('store: <script> not rendered as tag', !/<script>alert/.test(await s.$eval('#abp-bar',x=>x.outerHTML)));
 const a1=await s.$eval('.abp-item.abp-active',x=>x.dataset.id);await s.mouse.move(600,700);await sleep(2600);
 ok('store: rotation works', a1!==await s.$eval('.abp-item.abp-active',x=>x.dataset.id));
 if(SH) await s.screenshot({path:SH+'oc4_store.png',clip:{x:0,y:0,width:1280,height:260}});
 const pid=q("select product_id from oc_product where status=1 limit 1");
 await s.goto(BASE+'index.php?route=product/product&product_id='+pid);
 items=await s.$$eval('#abp-bar .abp-item',x=>x.map(y=>y.innerText.trim()));
 ok('store: product page shows product-only message', items.some(t=>/Only on product/.test(t)), items.length+'');

 // ---- per-message dismiss
 await s.goto(BASE);await s.click('.abp-close');await s.reload();await sleep(300);
 ok('dismiss: hidden after reload', await s.$eval('#abp-bar',x=>getComputedStyle(x).display==='none'));
 q("UPDATE oc_abp_message SET status=0 WHERE message_id=(SELECT id FROM (SELECT MIN(message_id) id FROM oc_abp_message) t)");
 await s.reload();
 ok('dismiss: disabling a message does NOT bring the bar back', await s.$eval('#abp-bar',x=>getComputedStyle(x).display==='none'));
 await add({text:'BRAND NEW message',sort:0});
 await s.reload();await sleep(300);
 items=await s.$$eval('#abp-bar .abp-item',x=>x.map(y=>y.innerText.trim()));
 ok('dismiss: a new message brings the bar back with only the new message', items.length===1 && /BRAND NEW/.test(items[0]), JSON.stringify(items));

 // ---- HTML mode sanitising
 await p.goto(A(ROUTE,'&tab=settings'));await p.check('#input-allow-html');await p.click('#form-settings button[type=submit]');await sleep(1000);
 await add({text:'<b>Bold</b> <a href="javascript:alert(1)">bad</a> <img src=x onerror=alert(1)> <a href="https://ok.example" target="_blank" onclick="x()">ok</a>',sort:-1});
 await s.reload();const h=await s.$eval('#abp-bar',x=>x.outerHTML);
 ok('html mode: <b> kept, js link / img / onclick removed, rel added', /<b>Bold<\/b>/.test(h)&&!/javascript:|onerror|onclick|<img/.test(h)&&/rel="noopener noreferrer"/.test(h));

 // ---- delete (AJAX)
 const before=+q('select count(*) from oc_abp_message');
 await p.goto(A(ROUTE));await p.check('#tab-messages tbody input[name="selected[]"] >> nth=0');
 p.once('dialog',d=>d.accept());await Promise.all([p.waitForResponse(r=>/\.delete|\|delete/.test(r.url())),p.click('#abp-delete')]);await sleep(1200);
 ok('delete via AJAX', +q('select count(*) from oc_abp_message')===before-1);

 // ---- permissions: user group with access only
 q("INSERT INTO oc_user_group SET name='Viewer', permission='{\"access\":[\""+ROUTE+"\",\"common/dashboard\"],\"modify\":[]}'");
 const gid=q("select user_group_id from oc_user_group where name='Viewer' order by user_group_id desc limit 1");
 const hash=execFileSync('php',['-r',"echo password_hash('viewer123', PASSWORD_DEFAULT);"]).toString();
 const ins="INSERT INTO oc_user SET user_group_id="+gid+", username='viewer', password='"+hash+"', firstname='V', lastname='U', email='v@example.com', image='', ip='', status=1, date_added=NOW()";
 if(q(ins)==='SQLERR') q(ins.replace("ip=''","ip='', code=''")); // 4.0.x has a `code` column, 4.1.x does not
 const vp=watch(await (await b.newContext()).newPage());const vt=await login(vp,'viewer','viewer123').catch(()=>null);
 if(vt){const V=(r,x='')=>BASE+'admin/index.php?route='+r+'&user_token='+vt+x;
   const cnt=q('select count(*) from oc_abp_message');
   const r1=await (await vp.request.post(V(ROUTE+SEP+'saveMessage'),{form:{'message_description[1][text]':'viewer','bg_color':'#000000','text_color':'#ffffff','sort_order':'0','page_mode':'all'}})).json().catch(()=>({}));
   const r2=await (await vp.request.post(V(ROUTE+SEP+'delete'),{form:{'selected[]':'1'}})).json().catch(()=>({}));
   const r3=await (await vp.request.post(V(ROUTE+SEP+'save'),{form:{module_announcement_bar_pro_status:'0'}})).json().catch(()=>({}));
   ok('viewer (access only): save/delete/settings refused', r1.error && r2.error && r3.error && q('select count(*) from oc_abp_message')===cnt && q("select value from oc_setting where `key`='module_announcement_bar_pro_status'")==='1');
 } else ok('viewer login',false);
 const nt=await p.request.get(BASE+'admin/index.php?route='+ROUTE+SEP+'delete',{maxRedirects:0});
 ok('no user_token → no access', nt.status()!==200 || !/success/.test(await nt.text()));

 // ---- direct call of the event method from the storefront must not crash
 const d=await s.request.get(BASE+'index.php?route='+ROUTE+SEP+'injectBar');
 ok('direct URL call of injectBar is harmless', d.status()<500, 'HTTP '+d.status());

 // ---- uninstall keeps data; delete-data drops tables
 const keep=q('select count(*) from oc_abp_message');
 await p.request.get(A('extension/module'+SEP+'uninstall','&extension=announcement_bar_pro&code=announcement_bar_pro'));
 ok('uninstall: event + settings removed, messages kept', q("select count(*) from oc_event where code='announcement_bar_pro'")==='0' && q("select count(*) from oc_setting where code='module_announcement_bar_pro'")==='0' && q('select count(*) from oc_abp_message')===keep);
 ok('uninstall: bar gone from store', !/abp-bar/.test(await (await s.request.get(BASE)).text()));
 await p.request.get(A('extension/module'+SEP+'install','&extension=announcement_bar_pro&code=announcement_bar_pro'));
 ok('reinstall: messages still there, module disabled (kept messages are not auto-enabled)', q('select count(*) from oc_abp_message')===keep && q("select value from oc_setting where `key`='module_announcement_bar_pro_status'")==='0');
 await p.request.get(A('extension/module'+SEP+'install','&extension=announcement_bar_pro&code=announcement_bar_pro'));
 ok('install twice: still exactly three events', q("select count(*) from oc_event where code='announcement_bar_pro'")==='3');
 await p.goto(A(ROUTE,'&tab=settings'));await p.check('#input-delete-data');await p.click('#form-settings button[type=submit]');await sleep(1000);
 await p.request.get(A('extension/module'+SEP+'uninstall','&extension=announcement_bar_pro&code=announcement_bar_pro'));
 ok('uninstall with delete-data: tables dropped', q("show tables like 'oc_abp_message%'")==='');
 await p.request.get(A('extension/module'+SEP+'install','&extension=announcement_bar_pro&code=announcement_bar_pro'));
 ok('fresh install after drop works', q("show tables like 'oc_abp_message%'").split('\n').length===2);

 ok('PHP error.log unchanged', logSize()===log0, (logSize()-log0)+' bytes added');
 console.log('OpenCart '+OCV);console.log(R.join('\n'));console.log('JS errors:',bag);await b.close();
})().catch(e=>{console.error(e);process.exit(1)});
