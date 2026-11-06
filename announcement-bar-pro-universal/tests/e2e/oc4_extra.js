// OC4 2.0.4 checks. Requires the module installed (as left by oc4_full.js). Env: OCBASE OCDB OCDIR
const {chromium}=require('playwright');const {execFileSync}=require('child_process');const fs=require('fs');
const BASE=process.env.OCBASE, DB=process.env.OCDB, DIR=process.env.OCDIR;
const q=s=>{try{return execFileSync('mysql',['--default-character-set=utf8mb4',DB,'-N','-e',s],{stdio:['pipe','pipe','ignore']}).toString().trim();}catch(e){return 'SQLERR';}};
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
const ROUTE='extension/announcement_bar_pro/module/announcement_bar_pro';
const OCV=fs.readFileSync(DIR+'/index.php','utf8').match(/define\('VERSION', '([^']+)'/)[1];const SEP=OCV.localeCompare('4.0.2.0',undefined,{numeric:true})>=0?'.':'|';const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{const b=await chromium.launch();const bag=[];const p=await (await b.newContext()).newPage();p.on('pageerror',e=>bag.push(e.message));
 await p.goto(BASE+'admin/');await p.fill('#input-username','admin');await p.fill('#input-password','admin123');await p.click('button[type=submit]');await p.waitForURL(/user_token=/);
 const tok=new URL(p.url()).searchParams.get('user_token');const A=(r,x='')=>BASE+'admin/index.php?route='+r+'&user_token='+tok+x;
 q('DELETE FROM oc_abp_message');q('DELETE FROM oc_abp_message_description');
 await p.goto(A(ROUTE,'&tab=settings'));await p.check('#input-status');await p.uncheck('#input-allow-html');await p.check('#input-dismissible');await p.click('#form-settings button[type=submit]');await sleep(1200);
 const save=async(text,id)=>{await p.goto(A(ROUTE+SEP+'form',id?'&message_id='+id:''));if(text!==null)await p.fill('#input-text-1',text);
   await Promise.all([p.waitForResponse(r=>/saveMessage/.test(decodeURIComponent(r.url()))),p.click('button[form=form-message]')]);await sleep(700);};
 await save('Emoji 🚚 and x < 5 stay');
 ok('emoji stored safely (as-is on utf8mb4, entities on 4.0.0.x)', ['Emoji 🚚 and x < 5 stay','Emoji &#x1F69A; and x < 5 stay'].includes(q("select text from oc_abp_message_description")), q("select text from oc_abp_message_description"));
 const s=await (await b.newContext()).newPage();s.on('pageerror',e=>bag.push(e.message));await s.goto(BASE);
 ok('storefront shows emoji and "x < 5"', /Emoji 🚚 and x < 5 stay/.test(await s.innerText('#abp-bar')));
 const now=+(await s.getAttribute('#abp-bar','data-now'));ok('server time passed to the bar (data-now)', Math.abs(now-Date.now())<60000, String(now));
 // HTML mode: "x < 5" kept
 await p.goto(A(ROUTE,'&tab=settings'));await p.check('#input-allow-html');await p.click('#form-settings button[type=submit]');await sleep(1200);
 await save('<b>Bold</b> and x < 5 kept');await s.reload();
 ok('HTML mode keeps "x < 5"', /Bold and x < 5 kept/.test(await s.$eval('#abp-bar',e=>e.textContent.replace(/\s+/g,' '))));
 // re-save without changes keeps dismissed
 await s.click('.abp-close');await s.reload();
 const id=q("select min(message_id) from oc_abp_message");await save(null,id);await s.reload();
 ok('re-save without changes keeps the bar closed', await s.$eval('#abp-bar',e=>getComputedStyle(e).display==='none'));
 await save('Emoji 🚚 and x < 5 stay — edited',id);await s.reload();
 ok('content edit shows the edited message again', /edited/.test(await s.innerText('#abp-bar').catch(()=>'')));
 // language without module translation → English labels
 if(q("select count(*) from oc_language where code='de-de'")==='0') q("INSERT INTO oc_language SET name='Deutsch', code='de-de', locale='de_DE.UTF-8,de-de', extension='', sort_order=3, status=1");
 for(const side of ['admin/language','catalog/language']){const src=DIR+'/'+side+'/en-gb',dst=DIR+'/'+side+'/de-de';if(!fs.existsSync(dst)){fs.cpSync(src,dst,{recursive:true});} if(fs.existsSync(dst+'/en-gb.php')&&!fs.existsSync(dst+'/de-de.php')) fs.renameSync(dst+'/en-gb.php',dst+'/de-de.php');}
 execFileSync('find',[DIR+'/system/storage/cache','-maxdepth','1','-name','cache.*','-delete']);
 const d=await (await b.newContext()).newPage();
 await d.request.post(BASE+'index.php?route=common/language.language&code=de-de',{form:{code:'de-de',redirect:BASE}}).catch(()=>{});
 await d.goto(BASE+'index.php?route=common/home&language=de-de');
 const lbl=await d.getAttribute('#abp-bar','aria-label').catch(()=>null), cl=await d.getAttribute('.abp-close','aria-label').catch(()=>null);
 ok('store language without module translation shows English labels', lbl==='Announcement' && cl==='Close', lbl+' / '+cl+' (html lang '+(await d.getAttribute('html','lang'))+')');
 // after uninstall the module page must not recreate data
 await p.request.get(A('extension/module'+SEP+'uninstall','&extension=announcement_bar_pro&code=announcement_bar_pro'));
 q("DELETE FROM oc_setting WHERE code='module_announcement_bar_pro'");
 const r=await p.goto(A(ROUTE));
 ok('module page after uninstall redirects, no settings recreated', /marketplace\/extension/.test(p.url()) && q("select count(*) from oc_setting where code='module_announcement_bar_pro'")==='0', p.url().replace(/user_token=\w+/,'T'));
 await p.request.get(A('extension/module'+SEP+'install','&extension=announcement_bar_pro&code=announcement_bar_pro'));
 ok('reinstall works (three events)', q("select count(*) from oc_event where code='announcement_bar_pro'")==='3');
 console.log(R.join('\n'));console.log('JS errors:',bag);await b.close();})().catch(e=>{console.error(e);process.exit(1)});
