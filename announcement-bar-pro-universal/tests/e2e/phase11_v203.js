// OC3 2.0.3: per-message dismiss, "until end of day" checkbox, no inline <script> in the bar.
const L=require('./lib');const {execFileSync}=require('child_process');
const q=s=>{try{return execFileSync('mysql',[process.env.OCDB,'-N','-e',s],{stdio:['pipe','pipe','ignore']}).toString().trim();}catch(e){return 'SQLERR';}};
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
(async()=>{const b=await L.chromium.launch();const p=await b.newPage();const bag=[];L.errs(p,bag);
 const tok=await L.login(p);const A=r=>L.BASE+'admin/index.php?route='+r+'&user_token='+tok;
 q('DELETE FROM oc_abp_message');q('DELETE FROM oc_abp_message_description');
 await p.goto(A('extension/module/announcement_bar_pro')+'&tab=settings');await p.selectOption('#input-status','1');await p.check('#input-dismissible');await p.fill('#input-dismiss-days','1');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);
 const add=async(text,allday)=>{await p.goto(A('extension/module/announcement_bar_pro/form'));await p.fill('textarea[name="message_description[1][text]"]',text);
   if(allday){await p.click('a[href="#tab-schedule"]');await p.fill('#input-date_end','2099-06-30 00:00');await p.check('#input-end-all-day');}
   await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);};
 await add('ONE',true);await add('TWO');
 ok('"until end of day" stores 23:59:59', q("select date_end from oc_abp_message m join oc_abp_message_description d using(message_id) where d.text='ONE'")==='2099-06-30 23:59:59');
 await p.goto(A('extension/module/announcement_bar_pro/form')+'&message_id='+q("select message_id from oc_abp_message_description where text='ONE'"));
 ok('edit form shows the checkbox ticked', await p.isChecked('#input-end-all-day'));
 const s=await (await b.newContext()).newPage();L.errs(s,bag);await s.goto(L.BASE);
 const html=await s.$eval('#abp-bar',e=>e.parentElement.innerHTML);
 ok('no inline <script> (only external src)', !/<script>(?!\s*<\/script>)/.test(html.replace(/<script src=[^>]+><\/script>/g,'')) && /announcement_bar_pro_pre\.js/.test(html));
 await s.click('.abp-close');
 q("UPDATE oc_abp_message m JOIN oc_abp_message_description d USING(message_id) SET m.status=0 WHERE d.text='ONE'");
 await s.reload();
 ok('a message switched off does not bring the bar back', await s.$eval('#abp-bar',e=>getComputedStyle(e).display==='none'));
 // re-save message TWO without changes → must stay closed
 await p.goto(A('extension/module/announcement_bar_pro/form')+'&message_id='+q("select message_id from oc_abp_message_description where text='TWO'"));
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);
 await s.reload();
 ok('re-saving a message without changes keeps the bar closed', await s.$eval('#abp-bar',e=>getComputedStyle(e).display==='none'));
 await add('THREE');await s.reload();
 const items=await s.$$eval('#abp-bar .abp-item',x=>x.map(y=>y.innerText.trim()));
 ok('a new message shows the bar with only the new message', items.length===1 && items[0]==='THREE', JSON.stringify(items));
 console.log(R.join('\n'));console.log('JS errors:',bag);await b.close();})().catch(e=>{console.error(e);process.exit(1)});
