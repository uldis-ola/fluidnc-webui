const L=require('./lib');const S=process.argv[2];const SH=S+'/shots/';
const fmt=d=>d.toISOString().slice(0,16).replace('T',' ');
const R=[];const ok=(name,cond,extra='')=>{R.push((cond?'PASS ':'FAIL ')+name+(extra?' — '+extra:''));};
(async()=>{const b=await L.chromium.launch();const ctx=await b.newContext({viewport:{width:1280,height:800}});const p=await ctx.newPage();const bag=[];L.errs(p,bag);
 const tok=await L.login(p); const A=r=>L.BASE+'admin/index.php?route='+r+'&user_token='+tok;
 const errText=async()=> (await p.$$eval('.text-danger',e=>e.map(x=>x.innerText.trim()).filter(Boolean)));

 // ---- settings validation
 await p.goto(A('extension/module/announcement_bar_pro')+'&tab=settings');
 await p.click('a[href="#tab-settings"]');
 await p.evaluate(()=>{document.getElementById('form-settings').noValidate=true;});
 await p.fill('#input-default_bg_color','red'); await p.fill('#input-interval','999');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);
 let e=await errText(); ok('settings: invalid colour + interval rejected', e.length>=2, e.join(' | '));
 await p.click('a[href="#tab-settings"]');
 await p.fill('#input-default_bg_color','#c0392b'); await p.fill('#input-interval','3');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);
 ok('settings: valid save', (await p.$('.alert-success'))!==null);

 // ---- message form validation
 await p.goto(A('extension/module/announcement_bar_pro/form'));
 await p.fill('textarea[name="message_description[1][text]"]','x');
 await p.fill('#input-link','javascript:alert(1)');
 await p.click('a[href="#tab-schedule"]'); await p.check('#input-countdown'); await p.click('a[href="#tab-content"]');
 await p.evaluate(()=>{document.getElementById('form-message').noValidate=true;document.getElementById('input-sort-order').type='text';});
 await p.fill('#input-sort-order',"1' OR 1=1");
 await p.click('a[href="#tab-visibility"]'); await p.selectOption('#input-page-mode','include'); await p.fill('#input-page-routes','../../etc/passwd'); await p.click('a[href="#tab-content"]');
 await p.fill('#input-icon','fa-x" onmouseover="alert(1)');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);
 e=await errText(); ok('form: js link, countdown w/o end, SQLi sort, bad route, bad icon all rejected', e.length>=5, e.join(' | '));
 ok('form: nothing saved on error', (await p.$('#form-message'))!==null);

 const now=new Date();
 async function addMsg(o){
  await p.goto(A('extension/module/announcement_bar_pro/form'));
  await p.fill('textarea[name="message_description[1][text]"]',o.text);
  if(o.link_text) await p.fill('input[name="message_description[1][link_text]"]',o.link_text);
  if(o.link) await p.fill('#input-link',o.link);
  if(o.icon) await p.fill('#input-icon',o.icon);
  if(o.bg) await p.fill('#input-bg_color',o.bg);
  if(o.fg) await p.fill('#input-text_color',o.fg);
  await p.fill('#input-sort-order',String(o.sort||0));
  await p.click('a[href="#tab-schedule"]');
  if(o.start) await p.fill('#input-date_start',o.start);
  if(o.end) await p.fill('#input-date_end',o.end);
  if(o.countdown) await p.check('#input-countdown');
  await p.click('a[href="#tab-visibility"]');
  if(o.store) await p.check(`input[name="stores[]"][value="${o.store}"]`);
  if(o.mode){await p.selectOption('#input-page-mode',o.mode); if(o.routes) await p.fill('#input-page-routes',o.routes);}
  await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);
  const e=await errText(); if(e.length) throw new Error('addMsg '+o.text+': '+e.join('|'));
 }
 await addMsg({text:'Bezmaksas piegāde virs 50 € — beidzas pēc {countdown}',link:'index.php?route=product/special',link_text:'Skatīt akcijas',icon:'fa-truck',bg:'#1e8449',fg:'#ffffff',sort:1,end:fmt(new Date(now.getTime()+2*86400e3+3*3600e3)),countdown:true});
 await addMsg({text:'Future scheduled message',sort:2,start:fmt(new Date(now.getTime()+86400e3))});
 await addMsg({text:'Only on product pages',sort:3,mode:'include',routes:'product/*'});
 await addMsg({text:'Other store only',sort:4,store:'1'});
 await addMsg({text:'Hidden on home',sort:5,mode:'exclude',routes:'common/home'});
 await addMsg({text:"Quote ' test \" & <script>alert(1)</script> plain",sort:6,link:'https://example.com/a?b=1&c=2'});
 const rows=await p.$$eval('#tab-messages tbody tr',r=>r.map(x=>x.innerText.replace(/\s+/g,' ').trim()));
 ok('list shows 7 messages', rows.length===7, rows.length+'');
 ok('list state: scheduled shown', rows.some(r=>/Future scheduled.*Scheduled/.test(r)));
 await p.screenshot({path:SH+'admin_list.png'});

 // ---- storefront home (rotate mode)
 const sp=await ctx.newPage(); L.errs(sp,bag);
 await sp.goto(L.BASE); await sp.waitForTimeout(500);
 let ids=await sp.$$eval('#abp-bar .abp-item',e=>e.map(x=>x.innerText.replace(/\s+/g,' ').trim()));
 ok('home: v1 msg + countdown msg + quote msg visible', ids.length===3, JSON.stringify(ids));
 ok('home: scheduled/product-only/other-store/exclude-home hidden', !ids.some(t=>/Future|Only on product|Other store|Hidden on home/.test(t)));
 const html=await sp.$eval('#abp-bar',e=>e.outerHTML);
 ok('home: <script> removed (plain mode strips tags)', !/<script>alert/.test(html));
 const cd=await sp.$eval('.abp-countdown',e=>e.textContent);
 ok('countdown renders d + hh:mm:ss', /^2\D+ 0?2:5\d:\d\d$|^2\D+ 03:00:0\d$|^2\D* \d\d:\d\d:\d\d$/.test(cd), cd);
 ok('button link present', await sp.$eval('.abp-btn',e=>e.getAttribute('href')).then(h=>/route=product\/special/.test(h)));
 ok('icon present', (await sp.$('.abp-item i.fa.fa-truck'))!==null);
 const active1=await sp.$eval('.abp-item.abp-active',e=>e.dataset.id);
 await sp.mouse.move(5,700); await sp.waitForTimeout(3500);
 const active2=await sp.$eval('.abp-item.abp-active',e=>e.dataset.id);
 ok('rotate: active message changes after interval', active1!==active2, active1+'→'+active2);
 await sp.screenshot({path:SH+'store_rotate.png',clip:{x:0,y:0,width:1280,height:260}});

 // ---- product page: include rule
 await sp.goto(L.BASE+'index.php?route=product/product&product_id=40');
 ids=await sp.$$eval('#abp-bar .abp-item',e=>e.map(x=>x.innerText.trim()));
 ok('product page: product-only + exclude-home shown', ids.some(t=>/Only on product/.test(t)) && ids.some(t=>/Hidden on home/.test(t)), ids.length+'');

 // ---- dismiss
 await sp.goto(L.BASE); await sp.click('.abp-close');
 ok('dismiss hides bar', await sp.$eval('#abp-bar',e=>getComputedStyle(e).display==='none'));
 await sp.reload(); await sp.waitForTimeout(300);
 ok('dismiss remembered after reload', await sp.$eval('#abp-bar',e=>getComputedStyle(e).display==='none'));
 await sp.evaluate(()=>{localStorage.clear();sessionStorage.clear();});

 // ---- settings: marquee + bottom + allow html
 await p.goto(A('extension/module/announcement_bar_pro')); await p.click('a[href="#tab-settings"]');
 await p.selectOption('#input-mode','marquee'); await p.selectOption('#input-position','bottom'); await p.check('#input-allow-html');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);
 await addMsg({text:'<b>Bold</b> <a href="javascript:alert(1)">bad</a> <a href="https://ok.example" onclick="x()" target="_blank">ok</a> <img src=x onerror=alert(1)> <span style="color:red">s</span>',sort:7});
 await sp.goto(L.BASE); await sp.waitForTimeout(800);
 const h2=await sp.$eval('#abp-bar',e=>e.outerHTML);
 ok('html: <b> kept', /<b>Bold<\/b>/.test(h2));
 ok('html: javascript: link removed, onclick/style/img removed', !/javascript:|onclick|onerror|<img|style="color:red"/.test(h2));
 ok('html: safe link kept with rel', /href="https:\/\/ok.example" target="_blank" rel="noopener noreferrer"/.test(h2));
 ok('marquee: animation running', await sp.$eval('.abp-track',e=>e.classList.contains('abp-run') && getComputedStyle(e).animationName==='abp-marquee'));
 ok('bottom: fixed + body padding', await sp.evaluate(()=>getComputedStyle(document.getElementById('abp-bar')).position==='fixed' && parseInt(document.body.style.paddingBottom)>0));
 await sp.screenshot({path:SH+'store_marquee_bottom.png'});

 // ---- stack + sticky top, mobile
 await p.goto(A('extension/module/announcement_bar_pro')); await p.click('a[href="#tab-settings"]');
 await p.selectOption('#input-mode','stack'); await p.selectOption('#input-position','top'); await p.check('#input-sticky');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);
 const m=await b.newContext({...require('playwright').devices['iPhone 13']}); const mp=await m.newPage(); L.errs(mp,bag);
 await mp.goto(L.BASE); await mp.waitForTimeout(500);
 ok('mobile: no horizontal scroll', await mp.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1));
 await mp.evaluate(()=>window.scrollTo(0,600)); await mp.waitForTimeout(200);
 ok('sticky: bar stays at top when scrolled', await mp.$eval('#abp-bar',e=>Math.abs(e.getBoundingClientRect().top)<2));
 await mp.evaluate(()=>window.scrollTo(0,0));
 await mp.screenshot({path:SH+'store_mobile_stack.png'});
 // back to rotate for final shots
 await p.goto(A('extension/module/announcement_bar_pro')); await p.click('a[href="#tab-settings"]');
 await p.selectOption('#input-mode','rotate'); await p.uncheck('#input-sticky');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);
 await p.screenshot({path:SH+'admin_messages.png',fullPage:true});
 await p.click('a[href="#tab-settings"]'); await p.screenshot({path:SH+'admin_settings.png',fullPage:true});
 await p.goto(A('extension/module/announcement_bar_pro/form')+'&message_id=2'); await p.waitForTimeout(300); await p.screenshot({path:SH+'admin_form.png',fullPage:true});

 console.log(R.join('\n')); console.log('JS errors:',bag); await b.close();})().catch(e=>{console.error(e);process.exit(1)});
