// 2.0.5 checks for OC3 and OC4. Env: KIND=oc3|oc4 OCBASE OCDB. Module must be installed.
const {chromium}=require('playwright');const {execFileSync}=require('child_process');
const KIND=process.env.KIND, BASE=process.env.OCBASE, DB=process.env.OCDB;
const q=s=>{try{return execFileSync('mysql',['--default-character-set=utf8mb4',DB,'-N','-e',s],{stdio:['pipe','pipe','ignore']}).toString().trim();}catch(e){return 'SQLERR';}};
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
const OC4=KIND==='oc4';const ROUTE=OC4?'extension/announcement_bar_pro/module/announcement_bar_pro':'extension/module/announcement_bar_pro';const OCV=require('fs').readFileSync(process.env.OCDIR+'/index.php','utf8').match(/define\('VERSION', '([^']+)'/)[1];const SEP=!OC4?'/':(OCV.localeCompare('4.0.2.0',undefined,{numeric:true})>=0?'.':'|');
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{const b=await chromium.launch();const bag=[];const p=await (await b.newContext()).newPage();p.on('pageerror',e=>bag.push(e.message));
 await p.goto(BASE+'admin/');await p.fill('#input-username','admin');await p.fill('#input-password','admin123');await p.click('button[type=submit]');await p.waitForURL(/user_token=/);
 const tok=new URL(p.url()).searchParams.get('user_token');const A=(r,x='')=>BASE+'admin/index.php?route='+r+'&user_token='+tok+x;
 const settings=async(o)=>{await p.goto(A(ROUTE,'&tab=settings'));
   if(OC4){await p.check('#input-status');}else{await p.selectOption('#input-status','1');}
   if(o.mode) await p.selectOption('#input-mode',o.mode);
   if(o.sticky!==undefined){ await p.selectOption('#input-position','top'); if(o.sticky) await p.check('#input-sticky'); else await p.uncheck('#input-sticky'); }
   if(o.pos) await p.selectOption('#input-position',o.pos);
   if(o.html!==undefined){ if(o.html) await p.check('#input-allow-html'); else await p.uncheck('#input-allow-html'); }
   if(OC4){await p.click('#form-settings button[type=submit]');await sleep(1200);}else{await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);}};
 const T=OC4?'#input-text-1':'textarea[name="message_description[1][text]"]';
 const save=async(text,id,extra)=>{await p.goto(A(ROUTE+SEP+'form',id?'&message_id='+id:''));if(text!==null)await p.fill(T,text);if(extra)await extra();
   if(OC4){await Promise.all([p.waitForResponse(r=>/saveMessage/.test(decodeURIComponent(r.url()))),p.click('button[form=form-message]')]);await sleep(800);}
   else{await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);}
   return p.$$eval(OC4?'.invalid-feedback.d-block':'.text-danger',x=>x.map(y=>y.innerText.trim()).filter(Boolean));};
 q('DELETE FROM oc_abp_message');q('DELETE FROM oc_abp_message_description');
 // ---- sanitizer through the UI
 await settings({mode:'stack',pos:'top',sticky:false,html:true});
 await save('a <svg/onload=alert(8)> b <i>c</i> end');
 await settings({html:false});
 await save('x < y and y > z <i>c</i>');
 const s=await (await b.newContext({viewport:{width:1280,height:700}})).newPage();s.on('pageerror',e=>bag.push(e.message));
 await s.goto(BASE);const txt=await s.$eval('#abp-bar',e=>e.textContent.replace(/\s+/g,' '));
 ok('HTML mode: unclosed <svg> keeps the rest ("b c end")', /a b c end/.test(txt), txt);
 ok('plain mode: "x < y and y > z" kept', /x < y and y > z c/.test(txt), txt);
 // ---- keyboard focus visible on close button
 await s.focus('.abp-close');await s.keyboard.press('Shift+Tab');await s.keyboard.press('Tab');
 const sh=await s.$eval('.abp-close',e=>({bs:getComputedStyle(e).boxShadow,ol:getComputedStyle(e).outlineStyle,act:document.activeElement===e}));
 ok('close button shows keyboard focus', sh.act && ((sh.bs&&sh.bs!=='none')||sh.ol!=='none'), JSON.stringify(sh));
 // ---- sticky top keeps the bar visible after scrolling
 await settings({mode:'rotate',pos:'top',sticky:true});
 const pid=q("select product_id from oc_product where status=1 limit 1");
 await s.goto(BASE+'index.php?route=product/category&path=20');await sleep(600);
 await s.evaluate(()=>window.scrollTo(0,800));await sleep(300);
 const top=await s.$eval('#abp-bar',e=>Math.round(e.getBoundingClientRect().top));
 ok('sticky top: bar stays at the top after scrolling 800px', Math.abs(top)<=2, 'bar top = '+top+' (pinned: '+(await s.$eval('#abp-bar',e=>e.classList.contains('abp-pinned')))+')');
 await s.evaluate(()=>window.scrollTo(0,0));await sleep(200);
 const overlap=await s.evaluate(()=>{const bar=document.getElementById('abp-bar').getBoundingClientRect();const nxt=[...document.body.children].find(x=>x.id!=='abp-bar'&&!x.classList.contains('abp-spacer')&&x.offsetHeight>50);return nxt?Math.round(nxt.getBoundingClientRect().top-bar.bottom):999;});
 ok('sticky top: page content starts below the bar (no overlap)', overlap>=-1, 'gap '+overlap+'px');
 // ---- bottom position: last footer line reachable
 await settings({mode:'stack',pos:'bottom',sticky:false});
 await save('Second line for a taller bar');
 await s.goto(BASE);await sleep(600);await s.evaluate(()=>window.scrollTo(0,document.documentElement.scrollHeight));await sleep(400);
 const fb=await s.evaluate(()=>{const f=document.querySelector('footer');const bar=document.getElementById('abp-bar').getBoundingClientRect();if(!f)return null;const r=f.getBoundingClientRect();return {footerBottom:Math.round(r.bottom),barTop:Math.round(bar.top)};});
 ok('bottom bar: footer end can be scrolled above the bar', fb && fb.footerBottom<=fb.barTop+1, JSON.stringify(fb));
 // ---- orphaned store protection
 if(q("INSERT INTO oc_store SET name='Temp Store', url='http://temp.example/', `ssl`=''")==='SQLERR') q("INSERT INTO oc_store SET name='Temp Store', url='http://temp.example/'");const sid=q("select max(store_id) from oc_store");
 execFileSync('find',[process.env.OCDIR+'/system/storage/cache','-maxdepth','1','-name','cache.*','-delete']); // store list is cached
 const e1=await save('ONLY TEMP STORE',null,async()=>{await p.click('a[href="#tab-visibility"]');await p.check(OC4?'#input-store-'+sid:'input[name="stores[]"][value="'+sid+'"]');});
 const mid=q("select max(message_id) from oc_abp_message");
 q("DELETE FROM oc_store WHERE store_id="+sid);
 execFileSync('find',[process.env.OCDIR+'/system/storage/cache','-maxdepth','1','-name','cache.*','-delete']); // admin store delete clears this cache too
 await p.goto(A(ROUTE+SEP+'form','&message_id='+mid));await p.click('a[href="#tab-visibility"]');
 ok('form warns about deleted target store', /no longer exist/i.test(await p.innerText('#tab-visibility')));
 const e2=await save(null,mid);
 ok('re-save of orphaned message is refused (not turned into "all stores")', e2.some(t=>/store/i.test(t)) && q("select stores from oc_abp_message where message_id="+mid)==='['+sid+']', e2.join('|')+' stores='+q("select stores from oc_abp_message where message_id="+mid));
 // ---- non-numeric message_id
 await p.goto(A(ROUTE+SEP+'form','&message_id=1%20OR%201%3D1'));
 ok('non-numeric message_id → not found, nothing loaded', /not found/i.test(await p.content()) || /tab=messages|marketplace/.test(p.url()));
 console.log(KIND+': '+R.length+' checks');console.log(R.join('\n'));console.log('JS errors:',bag);await b.close();})().catch(e=>{console.error(e);process.exit(1)});
