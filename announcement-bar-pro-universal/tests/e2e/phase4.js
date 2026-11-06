const L=require('./lib');const S=process.argv[2];
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
const {execFileSync}=require('child_process');const q=s=>execFileSync('mysql',[process.env.OCDB||'oc305','-N','-e',s]).toString().trim();
(async()=>{const b=await L.chromium.launch();const bag=[];
 // ---- viewer (access only)
 let p=await b.newPage();L.errs(p,bag);
 await p.goto(L.BASE+'admin/');await p.fill('#input-username','viewer');await p.fill('#input-password','viewer123');
 await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);
 let tok=new URL(p.url()).searchParams.get('user_token');
 if(!tok){ok('viewer login',false,p.url());} else {
  const A=r=>L.BASE+'admin/index.php?route='+r+'&user_token='+tok;
  const before=q("select count(*) from oc_abp_message");
  await p.goto(A('extension/module/announcement_bar_pro/form'));
  await p.fill('textarea[name="message_description[1][text]"]','viewer attempt');
  await Promise.all([p.waitForNavigation(),p.click('button[form=form-message]')]);
  ok('viewer: save blocked with permission error', /permission/i.test(await p.innerText('.alert-danger').catch(()=>'')) && q("select count(*) from oc_abp_message")===before);
  // delete via direct POST
  const r=await p.request.post(A('extension/module/announcement_bar_pro/delete'),{form:{'selected[]':'1'}});
  ok('viewer: delete blocked', q("select count(*) from oc_abp_message where message_id=1")==='1');
  // no token → redirected to login
  const r2=await p.request.get(L.BASE+'admin/index.php?route=extension/module/announcement_bar_pro/form',{maxRedirects:0});
  ok('no user_token: no access', r2.status()===302 || /login/.test(await r2.text()));
 }
 await p.close();
 // ---- admin: SQLi via GET, delete, uninstall/reinstall
 p=await b.newPage();L.errs(p,bag);tok=await L.login(p);const A=r=>L.BASE+'admin/index.php?route='+r+'&user_token='+tok;
 await p.goto(A('extension/module/announcement_bar_pro/form')+'&message_id='+encodeURIComponent('1 OR 1=1'));
 ok("GET message_id='1 OR 1=1' rejected as not found (2.0.5)", /tab=messages/.test(p.url()) && /not found/i.test(await p.content()));
 await p.goto(A('extension/module/announcement_bar_pro/form')+'&message_id='+encodeURIComponent("0' UNION SELECT 1-- "));
 ok('GET message_id UNION → rejected (not found), no SQL error', !/You have an error in your SQL/i.test(await p.content()) && /not found/i.test(await p.content()));
 const cnt=q("select count(*) from oc_abp_message");
 await p.goto(A('extension/module/announcement_bar_pro')+'&tab=messages');
 const delId=q("select max(message_id) from oc_abp_message"); await p.check('input[name="selected[]"][value="'+delId+'"]');
 p.once('dialog',d=>d.accept()); await Promise.all([p.waitForNavigation(),p.click('#abp-delete')]);
 ok('admin: delete selected message', q("select count(*) from oc_abp_message")==String(cnt-1) && q("select count(*) from oc_abp_message_description where message_id="+delId)==='0');
 // uninstall keep data
 await p.goto(L.BASE+'admin/index.php?route=extension/extension/module/uninstall&user_token='+tok+'&extension=announcement_bar_pro');
 ok('uninstall: event removed', q("select count(*) from oc_event where code='announcement_bar_pro'")==='0');
 ok('uninstall: settings removed', q("select count(*) from oc_setting where code='module_announcement_bar_pro'")==='0');
 ok('uninstall (keep data): tables + messages kept', q("select count(*) from oc_abp_message")==String(cnt-1));
 const html=await (await p.request.get(L.BASE)).text();
 ok('uninstall: bar gone from store', !/abp-bar/.test(html));
 // reinstall
 await L.installModule(p,tok);
 ok('reinstall: events back (3)', q("select count(*) from oc_event where code='announcement_bar_pro'")==='3');
 ok('reinstall: messages still there', q("select count(*) from oc_abp_message")==String(cnt-1));
 ok('reinstall: module disabled by default', q("select value from oc_setting where `key`='module_announcement_bar_pro_status'")==='0');
 // install twice = idempotent
 await L.installModule(p,tok);
 ok('install twice: still three events', q("select count(*) from oc_event where code='announcement_bar_pro'")==='3');
 // delete_data on
 await p.goto(A('extension/module/announcement_bar_pro')); await p.click('a[href="#tab-settings"]'); await p.check('#input-delete-data');
 await Promise.all([p.waitForNavigation(),p.click('button[form=form-settings]')]);
 await p.goto(L.BASE+'admin/index.php?route=extension/extension/module/uninstall&user_token='+tok+'&extension=announcement_bar_pro');
 ok('uninstall (delete data): tables dropped', q("show tables like 'oc_abp_message%'")==='');
 // other data untouched
 ok('core data untouched (products, orders tables)', +q("select count(*) from oc_product")>0);
 // final: install again for screenshots
 await L.installModule(p,tok);
 ok('fresh install creates tables', q("show tables like 'oc_abp_message%'").split('\n').length===2);
 console.log(R.join('\n'));console.log('JS errors:',bag);await b.close();})().catch(e=>{console.error(e);process.exit(1)});
