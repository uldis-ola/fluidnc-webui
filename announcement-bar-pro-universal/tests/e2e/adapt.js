// Automatic theme adaptation scenarios on a real OpenCart 3 / 4 shop (module installed by uni_install.js).
// Each scenario rewrites the shop's header template like a different theme would, then checks the bar.
// Env: OCBASE OCDB OCDIR [SHOTS]
const {chromium}=require('playwright');const {execFileSync}=require('child_process');const fs=require('fs');
const BASE=process.env.OCBASE, DB=process.env.OCDB, DIR=process.env.OCDIR, SH=process.env.SHOTS||'';
const q=s=>{try{return execFileSync('mysql',['--default-character-set=utf8mb4',DB,'-N','-e',s],{stdio:['pipe','pipe','ignore']}).toString().trim();}catch(e){return 'SQLERR';}};
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const VER=fs.readFileSync(DIR+'/index.php','utf8').match(/define\('VERSION', '([^']+)'/)[1], OC4=VER.startsWith('4');
const HDR=OC4?DIR+'/catalog/view/template/common/header.twig':DIR+'/catalog/view/theme/default/template/common/header.twig';
const ORIG=fs.readFileSync(HDR,'utf8');
function clearCache(){ for(const d of [DIR+'/system/storage/cache']) { if(fs.existsSync(d)) execFileSync('find',[d,'-mindepth','1','-not','-name','index.html','-delete']); } }
function theme(fn){ fs.writeFileSync(HDR, fn(ORIG)); clearCache(); }
function setting(k,v){ q("UPDATE oc_setting SET value='"+v+"' WHERE `key`='module_announcement_bar_pro_"+k+"'"); }
(async()=>{
 const b=await chromium.launch();const bag=[];
 // one message for every scenario
 q('DELETE FROM oc_abp_message');q('DELETE FROM oc_abp_message_description');
 q("INSERT INTO oc_abp_message SET status=1, sort_order=0, bg_color='#1e8449', text_color='#ffffff', link='/x', link_new_tab=0, icon='"+(OC4?'fa-solid fa-truck':'fa-truck')+"', countdown=0, stores='[]', customer_groups='[]', page_mode='all', page_routes='', date_added=NOW(), date_modified=NOW()");
 const mid=q('select max(message_id) from oc_abp_message');
 q("INSERT INTO oc_abp_message_description SET message_id="+mid+", language_id=1, text='Adaptive bar test message', link_text='Go'");
 setting('status','1');setting('mode','rotate');setting('position','top');setting('sticky','0');setting('color_mode','custom');setting('dismissible','1');
 const page=async(w=1280)=>{const ctx=await b.newContext({viewport:{width:w,height:800}});const pg=await ctx.newPage();pg.on('pageerror',e=>bag.push('JS: '+e.message));await pg.goto(BASE);await pg.waitForLoadState('load');await sleep(500);return pg;};
 const rect=(pg,sel)=>pg.$eval(sel,e=>{const r=e.getBoundingClientRect();return {top:Math.round(r.top),bottom:Math.round(r.bottom),h:Math.round(r.height)};});
 const barOnTop=pg=>pg.evaluate(()=>{const bar=document.getElementById('abp-bar');const r=bar.getBoundingClientRect();const el=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);return !!el&&bar.contains(el);});
 try{
 // 0. plain default theme
 theme(h=>h);let pg=await page();
 ok('default theme: bar is first in <body> and visible', await pg.evaluate(()=>document.body.firstElementChild.id==='abp-bar') && await barOnTop(pg));
 const raw=await (await pg.request.get(BASE)).text();
 ok('default theme: server puts the bar right after <body> (no fallback, no leftover marker)', /<body[^>]*>\s*<div id="abp-bar"[^>]*data-fallback="0"/.test(raw) && raw.indexOf('abp-fallback')===-1);
 const pr=await (await pg.request.get(BASE+'index.php?route=product/category&path=20')).text();
 ok('category page: also injected after <body>, no marker', /<body[^>]*>\s*<div id="abp-bar"/.test(pr) && pr.indexOf('abp-fallback')===-1);
 await pg.close();

 // 1. fixed header covering the top (no padding)
 theme(h=>h.replace(/<body([^>]*)>/i,'<body$1>\n<div id="fixhdr" style="position:fixed;top:0;left:0;right:0;height:60px;background:#333;color:#fff;z-index:2000">FIXED HEADER</div>'));
 pg=await page();
 let bar=await rect(pg,'#abp-bar'),fh=await rect(pg,'#fixhdr');
 ok('fixed header: moved below the bar, bar not covered', fh.top===bar.bottom && await barOnTop(pg), 'bar '+JSON.stringify(bar)+' header '+JSON.stringify(fh));
 await pg.evaluate(()=>window.scrollTo(0,600));await sleep(400);
 fh=await rect(pg,'#fixhdr');
 ok('fixed header: back to top 0 once the (non-sticky) bar scrolled away', fh.top===0, JSON.stringify(fh));
 if(SH) await pg.evaluate(()=>window.scrollTo(0,0)), await sleep(300), await pg.screenshot({path:SH+'fixed-header.png',clip:{x:0,y:0,width:1280,height:200}});
 await pg.click('.abp-close');await sleep(200);
 fh=await rect(pg,'#fixhdr');
 ok('fixed header: restored to its own position after closing the bar', fh.top===0 && await pg.$eval('#fixhdr',e=>e.style.top==='0px' && e.style.getPropertyPriority('top')==='' && !e.hasAttribute('data-abp-top')));
 await pg.close();

 // 2. fixed header that the theme already pads content for → leave it
 theme(h=>h.replace(/<body([^>]*)>/i,'<body$1 style="padding-top:60px">\n<div id="fixhdr" style="position:fixed;top:0;left:0;right:0;height:60px;background:#333;color:#fff;z-index:2000">FIXED HEADER</div>'));
 pg=await page();
 bar=await rect(pg,'#abp-bar');fh=await rect(pg,'#fixhdr');
 ok('padded fixed header: stays at top, bar visible under it', fh.top===0 && bar.top>=60 && await barOnTop(pg), 'bar '+JSON.stringify(bar));
 await pg.close();

 // 3. sticky header + sticky bar
 setting('sticky','1');
 // a real theme puts its sticky header inside its page wrapper (OpenCart 4.1: #container)
 const ST='<div id="sthdr" style="position:sticky;top:0;height:50px;background:#444;color:#fff;z-index:1500">STICKY HEADER</div>';
 theme(h=>/<div id="container">/.test(h)?h.replace('<div id="container">','<div id="container">\n'+ST):h.replace(/<body([^>]*)>/i,'<body$1>\n'+ST));
 pg=await page();await pg.evaluate(()=>window.scrollTo(0,900));await sleep(500);
 bar=await rect(pg,'#abp-bar');fh=await rect(pg,'#sthdr');
 ok('sticky header + sticky bar: header sticks right below the bar', bar.top===0 && fh.top===bar.bottom && await barOnTop(pg), 'bar '+JSON.stringify(bar)+' header '+JSON.stringify(fh));
 await pg.close();

 // 4. header that becomes fixed only after scrolling (theme JS)
 theme(h=>h.replace(/<body([^>]*)>/i,'<body$1>\n<div id="lthdr" style="height:50px;background:#555;color:#fff;width:100%">LATE FIXED</div><script>window.addEventListener("scroll",function(){var e=document.getElementById("lthdr");if(scrollY>200){e.style.position="fixed";e.style.left=0;e.style.right=0;e.style.zIndex=3000;}else{e.style.position="";}});</script>'));
 pg=await page();await pg.evaluate(()=>window.scrollTo(0,800));await sleep(600);
 bar=await rect(pg,'#abp-bar');fh=await rect(pg,'#lthdr');
 ok('header fixed later by theme JS: placed below the sticky bar, bar above it', fh.top===bar.bottom && await barOnTop(pg), 'bar '+JSON.stringify(bar)+' header '+JSON.stringify(fh));
 await pg.close();
 setting('sticky','0');

 // 5. theme without <body> in the header template → footer fallback
 theme(h=>h.replace(/<body([^>]*)>/i,'<!-- body tag moved by theme -->'));
 pg=await page();
 const info=await pg.evaluate(()=>({n:document.querySelectorAll('#abp-bar').length,first:document.body.firstElementChild&&document.body.firstElementChild.id,fb:document.getElementById('abp-bar')&&document.getElementById('abp-bar').getAttribute('data-fallback')}));
 ok('header without <body>: bar via footer fallback, moved to the top, only one bar', info.n===1 && info.first==='abp-bar' && info.fb==='1', JSON.stringify(info));
 await pg.close();

 // 6. header rendered twice → one bar
 theme(h=>h.replace(/<body([^>]*)>/i,'<body$1>\n<div id="dup"></div>'));
 pg=await page();
 await pg.evaluate(()=>{});
 const html=(await (await pg.request.get(BASE)).text());
 ok('bar markup injected once per page', (html.match(/id="abp-bar"/g)||[]).length===1);
 await pg.close();

 // 7. fixed cookie banner at the bottom + bottom bar
 setting('position','bottom');
 theme(h=>h.replace(/<body([^>]*)>/i,'<body$1>\n<div id="cookie" style="position:fixed;bottom:0;left:0;right:0;height:56px;background:#222;color:#fff;z-index:5000">COOKIES</div>'));
 pg=await page();
 bar=await rect(pg,'#abp-bar');const ck=await rect(pg,'#cookie');
 ok('cookie banner at the bottom: bottom bar lifted above it, both visible', bar.bottom<=ck.top && await barOnTop(pg), 'bar '+JSON.stringify(bar)+' cookie '+JSON.stringify(ck));
 if(SH) await pg.screenshot({path:SH+'cookie-bottom.png',clip:{x:0,y:600,width:1280,height:200}});
 await pg.close();
 setting('position','top');

 // 8. RTL
 theme(h=>h.replace(/<html([^>]*?)dir="[^"]*"/gi,'<html$1dir="rtl"'));
 pg=await page();
 const cl=await pg.$eval('.abp-close',e=>{const r=e.getBoundingClientRect();return Math.round(r.left);});
 ok('RTL: close button on the left', cl<100, 'left='+cl);
 await pg.close();

 // 9. no Font Awesome in the theme → icon dropped
 theme(h=>h.replace(/<link[^>]*font-?awesome[^>]*>/gi,'').replace(/<link[^>]*fontawesome[^>]*>/gi,'').replace(/<link[^>]*\{\{ icons \}\}[^>]*>/gi,''));
 pg=await page();
 ok('theme without Font Awesome: icon removed (no empty box)', !(await pg.$('#abp-bar i[class*="fa-"]')));
 await pg.close();
 theme(h=>h);
 pg=await page();
 ok('theme with Font Awesome: icon kept', !!(await pg.$('#abp-bar i[class*="fa-"]')));
 await pg.close();

 // 10. automatic colours from the theme
 setting('color_mode','auto');
 theme(h=>h.replace(/<\/head>/i,'<style>.btn-primary,#menu,.navbar,header nav{background:#7b2cbf !important;background-color:#7b2cbf !important;border-color:#7b2cbf !important;background-image:none !important}</style></head>'));
 pg=await page();
 const col=await pg.evaluate(()=>{const bar=document.getElementById('abp-bar');const cs=getComputedStyle(bar);return {bg:cs.backgroundColor,fg:cs.color,vis:cs.visibility,pending:bar.classList.contains('abp-auto-pending')};});
 ok('automatic colours: bar takes the theme primary colour (#7b2cbf), readable text, visible', col.bg==='rgb(123, 44, 191)' && col.fg==='rgb(255, 255, 255)' && col.vis==='visible' && !col.pending, JSON.stringify(col));
 if(SH) await pg.screenshot({path:SH+'auto-colour.png',clip:{x:0,y:0,width:1280,height:140}});
 await pg.close();
 theme(h=>h);
 pg=await page();
 const col2=await pg.evaluate(()=>getComputedStyle(document.getElementById('abp-bar')).backgroundColor);
 ok('automatic colours on the stock theme: not the default grey', col2!=='rgb(34, 34, 34)', col2);
 await pg.close();
 setting('color_mode','custom');

 // 11. mobile width
 pg=await page(375);
 ok('mobile: no horizontal scroll caused by the bar', await pg.evaluate(()=>document.getElementById('abp-bar').scrollWidth<=window.innerWidth+1));
 await pg.close();
 } finally { fs.writeFileSync(HDR,ORIG); clearCache(); }
 const log=DIR+'/system/storage/logs/error.log';
 ok('PHP error log: nothing from this module', !fs.existsSync(log) || !/announcement_bar_pro|announcementbarpro|abp_/i.test(fs.readFileSync(log,'utf8')), fs.existsSync(log)?(fs.statSync(log).size+' bytes total (core notices on old OC + PHP 8.3 not counted)'):'');
 console.log('OpenCart '+VER+': '+R.filter(r=>r.startsWith('PASS')).length+'/'+R.length);console.log(R.join('\n'));console.log('JS errors:',bag);await b.close();
})().catch(e=>{try{fs.writeFileSync(HDR,ORIG);}catch(x){}console.error(e);process.exit(1)});
