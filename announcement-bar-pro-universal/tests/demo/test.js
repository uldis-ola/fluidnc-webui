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
ok('desktop: phone-only message hidden',d.shown==='1,2,3,5',d.shown);
await p.check('#mobile');await p.waitForTimeout(900);d=await dev();
ok('390 px: phone text shown, normal text hidden',d.w===390&&!d.d&&d.m&&/Piegāde 0 €/.test(d.mt||''),JSON.stringify(d));
ok('390 px: phone-only message shown',d.shown==='1,2,3,4,5',d.shown);
// Rotation on a computer must skip the phone-only message: sample the active message for 13 s (interval 4 s).
await p.uncheck('#mobile');await p.click('[data-mode=rotate]');await p.waitForTimeout(700);
const seen=new Set();for(let k=0;k<26;k++){seen.add((await dev()).active);await p.waitForTimeout(500);}
ok('desktop rotation uses only messages for this screen',[...seen].sort().join()==='1,2,3,5',[...seen].join());
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
ok('stats: one view per shown message (desktop 1,2,3,5)',[...beacons].sort().join()==='view1,view2,view3,view5',beacons.join());
await p.click('[data-mode=marquee]');await p.waitForTimeout(1000);await p.click('[data-mode=stack]');await p.waitForTimeout(1000);
ok('stats: page reloads in the same session send no new views',beacons.length===4,beacons.join());
const btn=()=>fr().evaluate(()=>document.querySelector('.abp-item[data-id="1"] .abp-btn').click());await btn();await btn();await p.waitForTimeout(600);
ok('stats: button click sent once',beacons.filter(x=>x==='click1').length===1&&beacons.length===5,beacons.join());
await p.check('#mobile');await p.waitForTimeout(1200);
ok('stats: phone-only message counted once it is shown',beacons.filter(x=>x==='view4').length===1&&beacons.length===6,beacons.join());
// Do Not Track / Global Privacy Control: nothing is sent (fresh browser contexts).
for(const [name,prop,val] of [['Do Not Track','doNotTrack','1'],['Global Privacy Control','globalPrivacyControl',true]]){
 const q=await b.newPage({viewport:{width:1280,height:900}});const got=[];await watch(q,got);
 await q.addInitScript(([pr,v])=>{Object.defineProperty(Navigator.prototype,pr,{get:()=>v,configurable:true});},[prop,val]);
 await q.goto('file://'+__dirname+'/announcement-bar-demo.html');await q.waitForTimeout(600);await q.check('#stats');await q.waitForTimeout(1200);
 const f=q.frame({url:/about:srcdoc/})||q.frames()[1];const flag=await f.evaluate(pr=>String(navigator[pr]),prop);
 ok(name+': nothing sent',got.length===0&&flag===String(val),flag+' / '+got.join());await q.close();}
await p.setViewportSize({width:390,height:844});await p.waitForTimeout(600);await p.screenshot({path:'shot-mobile-text.png'});
// ---------------- 2.3.0: styles, free shipping, coupon, A/B, arrows ----------------
await p.setViewportSize({width:1280,height:900});await p.uncheck('#mobile');await p.uncheck('#stats');await p.selectOption('#theme','classic');await p.click('[data-pos=top]');await p.click('[data-mode=stack]');await p.waitForTimeout(800);
const contrast=()=>fr().evaluate(()=>{const parse=s=>[...String(s).matchAll(/rgba?\(([^)]+)\)/g)].map(m=>m[1].split(/[\s,\/]+/).filter(Boolean).map(Number));
 const lum=c=>{const v=c.slice(0,3).map(x=>{x/=255;return x<=0.03928?x/12.92:Math.pow((x+0.055)/1.055,2.4)});return 0.2126*v[0]+0.7152*v[1]+0.0722*v[2]};const ratio=(a,b)=>{const x=lum(a),y=lum(b);return (Math.max(x,y)+0.05)/(Math.min(x,y)+0.05)};
 const over=(c,b)=>{const a=c.length>3?c[3]:1;return [0,1,2].map(i=>c[i]*a+b[i]*(1-a))};let min=99;
 document.querySelectorAll('.abp-item').forEach(it=>{if(getComputedStyle(it).display==='none')return;const fg=parse(getComputedStyle(it).color)[0];let bg=parse(it.style.backgroundImage||'');if(!bg.length)bg=parse(getComputedStyle(it).backgroundColor);
  bg.forEach(c=>{(c.length>3&&c[3]<1?[over(c,[255,255,255]),over(c,[0,0,0])]:[c]).forEach(x=>{min=Math.min(min,ratio(fg,x))})})});return {min,hs:document.documentElement.scrollWidth>innerWidth,styles:[...new Set([...document.querySelectorAll('.abp-item')].map(i=>i.dataset.style))].join()}});
for(const colors of ['auto','custom']){await p.selectOption('#colors',colors);
 for(const st of ['solid','gradient','soft','minimal','glass']){await p.click('[data-style='+st+']');await p.waitForTimeout(700);const c=await contrast();
  if(colors==='auto'||st!=='solid'){ok('style '+st+' ('+colors+' colours): contrast ≥ 4.5:1',c.min>=4.5&&c.styles===st,c.min.toFixed(2)+' '+c.styles);}
  if(colors==='auto')await p.screenshot({path:'shot-style-'+st+'.png',clip:{x:330,y:0,width:950,height:420}});}}
await p.selectOption('#colors','auto');await p.click('[data-style=gradient]');
for(const w of [320,390,768,1024,1920]){await p.setViewportSize({width:w,height:900});await p.waitForTimeout(500);const h=await fr().evaluate(()=>document.documentElement.scrollWidth>innerWidth);ok('no horizontal scroll at '+w+' px',!h);}
await p.setViewportSize({width:1280,height:900});await p.waitForTimeout(500);
// free shipping: real module refresh after a (simulated) AJAX add-to-cart
const fs=()=>fr().evaluate(()=>{const el=document.querySelector('.abp-item[data-id="5"]');return {t:el.innerText.replace(/\s+/g,' ').trim(),w:el.querySelector('.abp-progress-fill').style.width,done:el.hasAttribute('data-done')}});
let f0=await fs();ok('free shipping: empty cart → 50,00 € left, bar 0 %',/50,00 €/.test(f0.t)&&f0.w==='0%',JSON.stringify(f0));
await fr().click('[data-price="24.9"]');await p.waitForTimeout(900);let f1=await fs();
ok('free shipping: add to cart → text + bar update without reload',/25,10 €/.test(f1.t)&&f1.w==='49%'&&!f1.done,JSON.stringify(f1));
await fr().click('[data-price="229"]');await p.waitForTimeout(900);f1=await fs();
ok('free shipping: threshold reached → success text, 100 %',/bez maksas/.test(f1.t)&&f1.w==='100%'&&f1.done,JSON.stringify(f1));
await p.click('#emptycart');await p.waitForTimeout(800);
// coupon copy (clipboard API or execCommand fallback) + visible and announced feedback
await fr().click('.abp-item[data-id="2"] .abp-copy');await p.waitForTimeout(300);
const cp=await fr().evaluate(()=>{const c=document.querySelector('.abp-item[data-id="2"] .abp-coupon');return {label:c.querySelector('.abp-copy-label').textContent,live:c.querySelector('.abp-sr').textContent,copied:c.querySelector('.abp-copy').classList.contains('abp-copied')}});
ok('coupon: Copy shows "Nokopēts!" and announces it',cp.label==='Nokopēts!'&&cp.live==='Nokopēts!'&&cp.copied,JSON.stringify(cp));
// A/B: forced B, forced A, random stays the same after re-render
const v3=()=>fr().evaluate(()=>{const el=document.querySelector('.abp-item[data-id="3"]');return {v:el.dataset.variant,t:el.innerText.replace(/\s+/g,' ').trim()}});
await p.selectOption('#variant','b');await p.waitForTimeout(700);let a=await v3();ok('A/B: version B shows text B + its button',a.v==='b'&&/B versija/.test(a.t)&&/Pasūtīt/.test(a.t),JSON.stringify(a));
await p.selectOption('#variant','a');await p.waitForTimeout(700);a=await v3();ok('A/B: version A shows text A',a.v==='a'&&/Pasūtījumi līdz/.test(a.t),JSON.stringify(a));
await p.selectOption('#variant','');await p.waitForTimeout(700);const r1=(await v3()).v;await p.click('[data-mode=stack]');await p.waitForTimeout(700);const r2=(await v3()).v;
ok('A/B: random version stays the same for this browser',/^[ab]$/.test(r1)&&r1===r2,r1+' '+r2);
// rotate + arrows + slide
await p.click('[data-mode=rotate]');await p.waitForTimeout(700);
const act=()=>fr().evaluate(()=>document.querySelector('.abp-item.abp-active').dataset.id);const x0=await act();
await fr().click('.abp-next');await p.waitForTimeout(300);const x1=await act();await fr().focus('.abp-prev');await p.keyboard.press('Enter');await p.waitForTimeout(300);const x2=await act();
const an=await fr().evaluate(()=>getComputedStyle(document.querySelector('.abp-item.abp-active')).animationName);
ok('arrows: next / previous (keyboard), slide animation',x1!==x0&&x2===x0&&/abp-slide/.test(an),x0+x1+x2+' '+an);
await p.click('[data-mode=stack]');await p.waitForTimeout(800);await p.screenshot({path:'shot-v23.png'});

// ---------------- 2.4.0: several bars + box, trigger, tiers, pause, "messages per bar", auto-hide, GA4, form, cut-off ----------------
await fr().evaluate(()=>{
 window.__ga=[];window.gtag=function(){window.__ga.push([].slice.call(arguments));};
 document.querySelectorAll('.abp,.abp-tab').forEach(e=>e.remove());
 const now=Date.now(),item=(id,txt,extra,inner)=>'<div class="abp-item" data-id="'+id+'" data-key="'+String(id).padStart(12,'0')+'" data-style="solid" data-c="#1e3a8a" data-t="#ffffff" '+(extra||'')+' style="background-color:#1e3a8a;color:#ffffff;--abp-btn-bg:#ffffff;--abp-btn-fg:#1e3a8a;--abp-accent:#ffffff;padding:8px 0;"><div class="abp-inner"><span class="abp-v abp-v-a"><span class="abp-body"><span class="abp-txt"><span class="abp-text">'+txt+'</span></span></span></span>'+(inner||'')+'</div></div>';
 const bar=(id,cls,attrs,items,btns)=>'<div id="'+id+'" class="abp '+cls+'" data-abp="'+(cls.indexOf('abp-inline')>=0?'inline':'bar')+'" data-place="'+(cls.match(/abp-pos-(\w+)|abp-inline-(\w+)/)||[]).slice(1).filter(Boolean)[0]+'" role="region" aria-label="Paziņojums" data-mode="'+(cls.match(/abp-mode-(\w+)/)||[])[1]+'" data-interval="2" data-now="'+now+'" data-tz="7200" data-color="custom" data-dismissible="0" data-days-label="d." data-ga="1" '+attrs+' style="font-size:15px;background-color:#1e3a8a;color:#fff;"><div class="abp-viewport"><div class="abp-track">'+items+'</div></div>'+(btns||'')+'</div>';
 const steps='<span class="abp-progress abp-steps" aria-hidden="true" data-p="40" data-k="1"><span class="abp-progress-fill" style="width:40%"></span><span class="abp-step abp-step-done" style="left:33.33%"><span class="abp-step-label">Piegāde</span></span><span class="abp-step" style="left:66.67%"><span class="abp-step-label">Dāvana</span></span><span class="abp-step abp-step-last" style="left:100%"><span class="abp-step-label">−10%</span></span></span>';
 const pause='<button type="button" class="abp-pause" aria-pressed="false" aria-label="Apturēt"><svg class="abp-i-pause" viewBox="0 0 16 16"></svg><svg class="abp-i-play" viewBox="0 0 16 16"></svg></button>';
 const form='<form class="abp-form" action="https://demo.invalid/sub" method="post" novalidate data-tok="1.x" data-err-email="Nederīga adrese" data-err-consent="Atzīmē piekrišanu" data-err-failed="Kļūda"><span class="abp-fields"><input type="email" name="email" class="abp-email" aria-label="E-pasts" placeholder="E-pasts"><button type="submit" class="abp-submit">Pieteikties</button></span><label class="abp-consent"><input type="checkbox" name="consent" value="1"> <span>Piekrītu</span></label><span class="abp-form-msg" role="status"></span></form>';
 document.body.insertAdjacentHTML('afterbegin',
  bar('abp-x-top','abp-bar abp-pos-top abp-mode-rotate abp-has-pause','data-autohide="2" data-l-show="Rādīt" data-l-pause="Apturēt" data-l-play="Turpināt"',
   item(11,'Augšējā josla A')+item(12,'Augšējā josla B')+item(13,'Parādās pēc 1 s','data-trig="delay:1" data-toff="1"'),pause)
  +bar('abp-x-bottom','abp-bar abp-pos-bottom abp-mode-stack','data-max="1"',item(21,'Apakšā: pirmais')+item(22,'Apakšā: otrais (virs limita)'))
  +bar('abp-x-inline','abp-inline abp-inline-product abp-mode-stack','',item(31,'Pievieno vēl <span class="abp-remaining">30,00 €</span> un saņem: <span class="abp-goal">Dāvana</span>','data-fs="1"',form)
   .replace('</div></div>',' <span class="abp-countdown" data-end="'+(now-1000)+'" data-recur="d|14:00|"></span></div>'+steps+'</div>')));
 window.abpPre();window.AnnouncementBarPro.scan();
});
await p.waitForTimeout(500);
const s24=()=>fr().evaluate(()=>{const vis=e=>!!e&&getComputedStyle(e).display!=='none';const q=s=>document.querySelector(s);
 return {top:vis(q('#abp-x-top')),bottom:vis(q('#abp-x-bottom')),inline:vis(q('#abp-x-inline')),b1:vis(q('[data-id="21"]')),b2:vis(q('[data-id="22"]')),trig:!q('[data-id="13"]').hasAttribute('data-toff'),
  steps:document.querySelectorAll('#abp-x-inline .abp-step').length,labels:[...document.querySelectorAll('#abp-x-inline .abp-step-label')].filter(vis).length,cd:(q('#abp-x-inline .abp-countdown')||{}).textContent,
  pause:q('#abp-x-top .abp-pause')&&q('#abp-x-top .abp-pause').getAttribute('aria-pressed'),tab:vis(q('.abp-tab')),ga:window.__ga.filter(x=>x[1]==='abp_view').map(x=>x[2].abp_message_id+':'+x[2].abp_placement)}});
let z=await s24();
ok('2.4.0: top bar, bottom bar and product box at once',z.top&&z.bottom&&z.inline,JSON.stringify(z));
ok('2.4.0: "messages per bar" = 1 → second bottom message waits',z.b1&&!z.b2,JSON.stringify(z));
ok('2.4.0: delay trigger not fired yet',!z.trig,JSON.stringify(z));
ok('2.4.0: tiers: 3 markers with reward names (computer)',z.steps===3&&z.labels===3,JSON.stringify(z));
ok('2.4.0: daily cut-off countdown restarts instead of hiding the message',/^\d{2}:\d{2}:\d{2}$|^\dd\. /.test(z.cd||''),z.cd);
ok('2.4.0: GA4 abp_view through gtag with id + placement',z.ga.indexOf('31:product')!==-1&&z.ga.indexOf('21:bottom')!==-1,z.ga.join());
await fr().click('#abp-x-top .abp-pause');z=await s24();ok('2.4.0: pause button → aria-pressed true',z.pause==='true',z.pause);
await fr().click('#abp-x-top .abp-pause');
await p.waitForTimeout(900);z=await s24();ok('2.4.0: delay trigger fired after 1 s',z.trig,JSON.stringify(z));
await fr().click('#abp-x-inline .abp-submit');await p.waitForTimeout(200);
const fm=await fr().evaluate(()=>({msg:document.querySelector('.abp-form-msg').textContent,inv:document.querySelector('.abp-email').getAttribute('aria-invalid'),focus:document.activeElement&&document.activeElement.className}));
ok('2.4.0: e-mail form checks the address first (message, aria-invalid, focus)',fm.msg==='Nederīga adrese'&&fm.inv==='true'&&/abp-email/.test(fm.focus),JSON.stringify(fm));
await fr().fill('#abp-x-inline .abp-email','a@b.lv');await fr().click('#abp-x-inline .abp-submit');await p.waitForTimeout(200);
ok('2.4.0: consent box required',(await fr().evaluate(()=>document.querySelector('.abp-form-msg').textContent))==='Atzīmē piekrišanu');
const ec=await fr().evaluate(()=>{const e=document.querySelector('.abp-email'),s=getComputedStyle(e);return [s.color,s.backgroundColor]});
const rgb=c=>c.match(/\d+/g).slice(0,3).map(Number),L=a=>{a=a.map(v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)});return .2126*a[0]+.7152*a[1]+.0722*a[2]},cr=(a,b)=>{const x=L(rgb(a)),y=L(rgb(b));return (Math.max(x,y)+.05)/(Math.min(x,y)+.05)};
ok('2.4.0: e-mail field contrast ≥ 4.5:1',cr(ec[0],ec[1])>=4.5,ec.join(' / '));
await fr().evaluate(()=>{const f=document.querySelector('#abp-x-top');f.dispatchEvent(new PointerEvent('pointerleave',{pointerType:'mouse'}));document.activeElement&&document.activeElement.blur();});
await p.waitForTimeout(2600);z=await s24();ok('2.4.0: auto-hide folds the bar into a tab',!z.top&&z.tab,JSON.stringify(z));
await p.screenshot({path:'shot-v24.png'});
await fr().click('.abp-tab');await p.waitForTimeout(300);z=await s24();ok('2.4.0: the tab opens the bar again',z.top&&!z.tab,JSON.stringify(z));
await p.setViewportSize({width:390,height:844});await p.waitForTimeout(700);z=await s24();
const sw24=await fr().evaluate(()=>document.documentElement.scrollWidth);
ok('2.4.0 @390: reward names hidden under the bar, no horizontal scroll',z.labels===0&&sw24<=390,z.labels+' '+sw24);
await p.screenshot({path:'shot-v24-mobile.png'});

console.log(R.join('\n'));console.log('errors',errs);console.log(R.filter(x=>x.startsWith('PASS')).length+' passed, '+R.filter(x=>x.startsWith('FAIL')).length+' failed');await b.close();})();
