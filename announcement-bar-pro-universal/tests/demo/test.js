const {chromium}=require('playwright');
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1280,height:900}});const errs=[];
p.on('pageerror',e=>errs.push(e.message));p.on('console',m=>{if(m.type()==='error')errs.push(m.text())});
// 2.2.0 statistics: the demo sends beacons to demo.invalid; count them (body: multipart id + type) and answer 204.
const beacon=r=>{let d='';try{d=r.postData()||'';}catch(e){}if(!d){try{d=(r.postDataBuffer()||'').toString();}catch(e){}}
 return (/name="type"\r?\n\r?\n(\w+)/.exec(d)||[])[1]+(/name="id"\r?\n\r?\n(\d+)/.exec(d)||[])[1];};
const watch=(pg,list)=>{pg.on('request',r=>{if(/demo\.invalid/.test(r.url()))list.push(beacon(r));});return pg.route(/demo\.invalid/,r=>r.fulfill({status:204,body:''}));};
const beacons=[];await watch(p,beacons);
await p.goto('file://'+__dirname+'/announcement-bar-demo.html');await p.waitForTimeout(800);
const fr=()=>p.frame({url:/about:srcdoc/})||p.frames()[1];
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
const info=async()=>{const f=fr();f.on&&0;return f.evaluate(()=>{const b=document.getElementById('abp-bar');const r=b&&b.getBoundingClientRect();const h=document.querySelector('.fixedhdr');
 return {bars:document.querySelectorAll('#abp-bar').length,top:r&&r.top,h:r&&r.height,first:document.body.firstElementChild&&document.body.firstElementChild.id,bg:b&&getComputedStyle(b.querySelector('.abp-active')||b).backgroundColor,txt:b&&b.innerText.slice(0,80),cd:(document.querySelector('.abp-countdown')||{}).textContent,fixedTop:h&&h.getBoundingClientRect().top,disp:b&&getComputedStyle(b).display,icon:b?b.querySelectorAll('i.fa').length:-1}})};
let i=await info();ok('classic: bar at top',i.bars===1&&i.top===0&&i.first==='abp-bar',JSON.stringify(i));
ok('auto colour from theme (purple)',/123, 44, 191/.test(i.bg),i.bg);ok('countdown running',/\d/.test(i.cd||''),i.cd);
await p.selectOption('#theme','fixed');await p.waitForTimeout(900);i=await info();ok('fixed header shifted under bar',Math.abs(i.fixedTop-(i.top+i.h))<2,JSON.stringify(i));
await p.selectOption('#theme','nobody');await p.waitForTimeout(900);i=await info();ok('fallback bar moved to top',i.bars===1&&i.top===0&&i.first==='abp-bar',JSON.stringify(i));
await p.selectOption('#theme','classic');await p.selectOption('#colors','custom');await p.waitForTimeout(900);i=await info();ok('custom colours',/30, 132, 73/.test(i.bg),i.bg);
await p.uncheck('#fa');await p.waitForTimeout(900);i=await info();ok('no FA → icons removed',i.icon===0,String(i.icon));
await p.check('#rtl');await p.waitForTimeout(900);i=await info();ok('RTL text',/شحن/.test(i.txt),i.txt);
await fr().click('.abp-close');await p.waitForTimeout(400);i=await info();ok('dismiss hides',i.disp==='none',i.disp);
await p.click('#reset');await p.waitForTimeout(900);i=await info();ok('reset shows again',i.disp!=='none',i.disp);
await p.uncheck('#rtl');await p.check('#fa');await p.selectOption('#colors','auto');
await p.click('[data-pos=bottom]');await p.check('#cookie');await p.waitForTimeout(1200);
const bt=await fr().evaluate(()=>{const b=document.getElementById('abp-bar').getBoundingClientRect(),c=document.querySelector('.cookie').getBoundingClientRect();return [b.bottom,c.top]});ok('bottom bar above cookie',bt[0]<=bt[1]+1,JSON.stringify(bt));
await p.click('[data-mode=marquee]');await p.waitForTimeout(800);ok('marquee renders',(await info()).bars===1);
await p.screenshot({path:'shot-desktop.png'});
await p.setViewportSize({width:390,height:844});await p.check('#mobile');await p.uncheck('#cookie');await p.click('[data-pos=top]');await p.waitForTimeout(900);
const sw=await p.evaluate(()=>document.documentElement.scrollWidth);ok('no horizontal scroll on phone',sw<=390,String(sw));
await p.screenshot({path:'shot-mobile.png',fullPage:false});
await p.emulateMedia({colorScheme:'dark'});await p.setViewportSize({width:1280,height:900});await p.selectOption('#theme','fixed');await p.waitForTimeout(900);await p.screenshot({path:'shot-dark.png'});
// ---------------- 2.2.0: phone text, device targeting, statistics ----------------
await p.emulateMedia({colorScheme:'light'});await p.selectOption('#theme','classic');await p.click('[data-mode=stack]');await p.uncheck('#mobile');await p.waitForTimeout(900);
const dev=()=>fr().evaluate(()=>{const vis=e=>!!e&&getComputedStyle(e).display!=='none';const it=[...document.querySelectorAll('.abp-item')];const bar=document.getElementById('abp-bar');const h=document.querySelector('.fixedhdr');
 return {w:innerWidth,shown:it.filter(vis).map(e=>e.dataset.id).join(','),d:vis(document.querySelector('[data-id="1"] .abp-text-d')),m:vis(document.querySelector('[data-id="1"] .abp-text-m')),mt:(document.querySelector('[data-id="1"] .abp-text-m')||{}).textContent,bar:getComputedStyle(bar).display,hdr:h?Math.round(h.getBoundingClientRect().top):null,bb:Math.round(bar.getBoundingClientRect().bottom),active:(document.querySelector('.abp-item.abp-active')||{dataset:{}}).dataset.id}});
let d=await dev();ok('desktop: normal text shown, phone text hidden',d.w>=768&&d.d&&!d.m,JSON.stringify(d));
ok('desktop: phone-only message hidden',d.shown==='1,2,3',d.shown);
await p.check('#mobile');await p.waitForTimeout(900);d=await dev();
ok('390 px: phone text shown, normal text hidden',d.w===390&&!d.d&&d.m&&/Piegāde 0 €/.test(d.mt||''),JSON.stringify(d));
ok('390 px: phone-only message shown',d.shown==='1,2,3,4',d.shown);
// Rotation on a computer must skip the phone-only message: sample the active message for 13 s (interval 4 s).
await p.uncheck('#mobile');await p.click('[data-mode=rotate]');await p.waitForTimeout(700);
const seen=new Set();for(let k=0;k<26;k++){seen.add((await dev()).active);await p.waitForTimeout(500);}
ok('desktop rotation uses only messages for this screen',[...seen].sort().join()==='1,2,3',[...seen].join());
// Crossing 768 px without reload: no message fits → bar hidden and fixed header restored; back → shown again.
await p.selectOption('#theme','fixed');await p.click('[data-mode=stack]');await p.check('#mobile');await p.waitForTimeout(900);d=await dev();
ok('phone: fixed header under the bar',d.bar!=='none'&&Math.abs(d.hdr-d.bb)<2,JSON.stringify(d));
await fr().evaluate(()=>document.querySelectorAll('.abp-item').forEach(e=>e.setAttribute('data-device','mobile')));
await p.evaluate(()=>{document.getElementById('shop').style.width='900px';});await p.waitForTimeout(800);d=await dev();
ok('to desktop, nothing fits: bar hidden, header offset restored',d.w>=768&&d.bar==='none'&&d.hdr===0,JSON.stringify(d));
await p.evaluate(()=>{document.getElementById('shop').style.width='';});await p.waitForTimeout(800);d=await dev();
ok('back to phone: bar shown, header under it again',d.w===390&&d.bar!=='none'&&Math.abs(d.hdr-d.bb)<2,JSON.stringify(d));
// Statistics: views once per message per session, click once, nothing before the URL is set.
ok('no statistics requests without data-track',beacons.length===0,beacons.join());
await p.selectOption('#theme','classic');await p.uncheck('#mobile');await p.waitForTimeout(500);
await p.check('#stats');await p.waitForTimeout(1200);
ok('stats: one view per shown message (desktop 1,2,3)',[...beacons].sort().join()==='view1,view2,view3',beacons.join());
await p.click('[data-mode=marquee]');await p.waitForTimeout(1000);await p.click('[data-mode=stack]');await p.waitForTimeout(1000);
ok('stats: page reloads in the same session send no new views',beacons.length===3,beacons.join());
const btn=()=>fr().evaluate(()=>document.querySelector('.abp-item[data-id="1"] .abp-btn').click());await btn();await btn();await p.waitForTimeout(600);
ok('stats: button click sent once',beacons.filter(x=>x==='click1').length===1&&beacons.length===4,beacons.join());
await p.check('#mobile');await p.waitForTimeout(1200);
ok('stats: phone-only message counted once it is shown',beacons.filter(x=>x==='view4').length===1&&beacons.length===5,beacons.join());
// Do Not Track / Global Privacy Control: nothing is sent (fresh browser contexts).
for(const [name,prop,val] of [['Do Not Track','doNotTrack','1'],['Global Privacy Control','globalPrivacyControl',true]]){
 const q=await b.newPage({viewport:{width:1280,height:900}});const got=[];await watch(q,got);
 await q.addInitScript(([pr,v])=>{Object.defineProperty(Navigator.prototype,pr,{get:()=>v,configurable:true});},[prop,val]);
 await q.goto('file://'+__dirname+'/announcement-bar-demo.html');await q.waitForTimeout(600);await q.check('#stats');await q.waitForTimeout(1200);
 const f=q.frame({url:/about:srcdoc/})||q.frames()[1];const flag=await f.evaluate(pr=>String(navigator[pr]),prop);
 ok(name+': nothing sent',got.length===0&&flag===String(val),flag+' / '+got.join());await q.close();}
await p.setViewportSize({width:390,height:844});await p.waitForTimeout(600);await p.screenshot({path:'shot-mobile-text.png'});
console.log(R.join('\n'));console.log('errors',errs);await b.close();})();
