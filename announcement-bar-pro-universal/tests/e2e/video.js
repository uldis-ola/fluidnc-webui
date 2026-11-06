// Records a demo video on a real OpenCart shop. argv: oc3|oc4  baseUrl  db  outDir
const {chromium}=require('playwright');const {execFileSync}=require('child_process');
const [KIND,BASE,DB,OUT]=process.argv.slice(2);
const q=s=>{try{return execFileSync('mysql',['--default-character-set=utf8mb4',DB,'-N','-e',s],{stdio:['pipe','pipe','ignore']}).toString().trim();}catch(e){return 'SQLERR';}};
const ROUTE=KIND==='oc4'?'extension/announcement_bar_pro/module/announcement_bar_pro':'extension/module/announcement_bar_pro';
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 q('DELETE FROM oc_abp_message');q('DELETE FROM oc_abp_message_description');
 const b=await chromium.launch();
 const ctx=await b.newContext({viewport:{width:1280,height:720},recordVideo:{dir:OUT,size:{width:1280,height:720}}});
 const p=await ctx.newPage();
 const caption=async(t)=>{await p.evaluate(t=>{let c=document.getElementById('demo-cap');if(!c){c=document.createElement('div');c.id='demo-cap';c.style.cssText='position:fixed;left:16px;bottom:16px;z-index:99999;background:rgba(15,25,22,.88);color:#fff;font:600 20px/1.3 system-ui,sans-serif;padding:10px 16px;border-radius:8px;max-width:70vw;pointer-events:none';document.body.appendChild(c);}c.textContent=t;},t);};
 const type=async(sel,text)=>{await p.click(sel);await p.fill(sel,'');await p.type(sel,text,{delay:28});};
 // login
 await p.goto(BASE+'admin/');await caption('OpenCart '+(KIND==='oc4'?'4.1':'3.0')+' admin');await sleep(900);
 await p.fill('#input-username','admin');await p.fill('#input-password','admin123');
 await p.click('button[type=submit]');await p.waitForURL(/user_token=/);
 const tok=new URL(p.url()).searchParams.get('user_token');const A=(r,x='')=>BASE+'admin/index.php?route='+r+'&user_token='+tok+x;
 // settings
 await p.goto(A(ROUTE,'&tab=settings'));await caption('1. Iestatījumi: ieslēdz moduli, izvēlas režīmu');await sleep(1500);
 if(KIND==='oc4'){await p.check('#input-status');}else{await p.selectOption('#input-status','1');}
 await p.selectOption('#input-mode','rotate');await p.selectOption('#input-position','top');
 if(await p.isChecked('#input-sticky')) await p.uncheck('#input-sticky');
 if(await p.isChecked('#input-allow-html')) await p.uncheck('#input-allow-html');
 if(KIND==='oc4'){if(!(await p.isChecked('#input-dismissible')))await p.check('#input-dismissible');}else{if(!(await p.isChecked('#input-dismissible')))await p.check('#input-dismissible');}
 await sleep(500);
 await p.fill(KIND==='oc4'?'#input-interval':'#input-interval','4');
 if(KIND==='oc4'){await p.click('#form-settings button[type=submit]');await sleep(1800);}
 else{await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);await sleep(800);}
 // message 1
 const sep=KIND==='oc4'?'.':'/';
 await p.goto(A(ROUTE+sep+'form'));await caption('2. Jauns paziņojums: teksts, poga, ikona, krāsas');await sleep(1000);
 const T=KIND==='oc4'?'#input-text-1':'textarea[name="message_description[1][text]"]';
 const LT=KIND==='oc4'?'#input-link-text-1':'input[name="message_description[1][link_text]"]';
 await type(T,'Bezmaksas piegāde virs 50 € — beidzas pēc {countdown}');
 await type(LT,'Uz akcijām');
 await type('#input-link','index.php?route=product/special');
 await type('#input-icon','fa-truck');
 const BG=KIND==='oc4'?'#input-bg-color':'#input-bg_color';
 await p.fill(BG,'#1e8449');await p.dispatchEvent(BG,'input');await sleep(1200);
 await caption('3. Laiks: beigu datums + atpakaļskaitīšana');
 await p.click('a[href="#tab-schedule"]');await sleep(600);
 const end=new Date(Date.now()+2*86400e3+5*3600e3);
 const local=new Date(end.getTime()-end.getTimezoneOffset()*60000).toISOString().slice(0,16);
 if(KIND==='oc4'){await p.fill('#input-date-end',local);}else{await p.fill('#input-date_end',local.replace('T',' '));await p.click('#input-date_start');await p.keyboard.press('Escape');}
 await p.check('#input-countdown');await sleep(900);
 await caption('4. Redzamība: veikali, klientu grupas, lapas');
 await p.click('a[href="#tab-visibility"]');await sleep(1600);
 await p.click('a[href="#tab-content"]');await sleep(500);
 if(KIND==='oc4'){await Promise.all([p.waitForURL(/tab=messages/),p.click('button[form=form-message]')]);}
 else{await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);}
 // messages 2 and 3 (inserted quickly)
 const add=async(text,bg,fg,icon)=>{await p.goto(A(ROUTE+sep+'form'));await p.fill(T,text);await p.fill('#input-icon',icon);await p.fill(BG,bg);
   const FG=KIND==='oc4'?'#input-text-color':'#input-text_color';await p.fill(FG,fg);
   if(KIND==='oc4'){await Promise.all([p.waitForURL(/tab=messages/),p.click('button[form=form-message]')]);}else{await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);}};
 await add('Black Friday: −20 % visām frēzēm','#c0392b','#ffffff','fa-percent');
 await add('Pasūtījumi līdz 18.12. pienāks pirms svētkiem','#1f2a44','#f6d365','fa-gift');
 await caption('5. Paziņojumu saraksts');await sleep(2200);
 // storefront
 await p.goto(BASE);await caption('6. Veikals: josla maina paziņojumus');await sleep(9000);
 await p.mouse.move(640,20);await caption('Peles kursors virs joslas → maiņa apstājas');await sleep(3000);
 await p.mouse.move(640,500);
 await caption('7. Aizvēršana (×) tiek atcerēta');await sleep(800);
 await p.click('.abp-close');await sleep(1200);await p.reload();await sleep(1500);
 await caption('Pēc pārlādes josla paliek aizvērta');await sleep(2000);
 await p.evaluate(()=>{localStorage.clear();sessionStorage.clear();});
 if(KIND==='oc4'){
  await p.goto(A(ROUTE,'&tab=settings'));await caption('9. Krāsas: „Automātiski” — no tēmas');await sleep(1200);
  await p.selectOption('#input-color-mode','auto');await sleep(900);await p.click('#form-settings button[type=submit]');await sleep(1800);
  await p.goto(BASE);await caption('Josla paņem tēmas krāsu (bez iestatīšanas)');await sleep(4000);
  await p.goto(A(ROUTE,'&tab=settings'));await p.selectOption('#input-color-mode','custom');await p.click('#form-settings button[type=submit]');await sleep(1500);
  await p.goto(BASE);
  await p.evaluate(()=>{const h=document.createElement('div');h.id='demo-fixed';h.style.cssText='position:fixed;top:0;left:0;right:0;z-index:2000;background:#111;color:#fff;padding:14px 20px;font:600 16px system-ui';h.textContent='Tēmas fiksētā galvene (position: fixed)';document.body.appendChild(h);window.dispatchEvent(new Event('resize'));});
  await caption('10. Tēma ar fiksētu galveni: modulis to nobīda zem joslas');await sleep(4500);
  await p.mouse.wheel(0,600);await caption('Ritinot galvene atgriežas augšā');await sleep(2500);await p.mouse.wheel(0,-600);await sleep(1500);
 }
 await p.setViewportSize({width:390,height:720});await p.reload();await caption('8. Mobilais skats');await sleep(5000);
 await p.close();await ctx.close();await b.close();
})().catch(e=>{console.error(e);process.exit(1)});
