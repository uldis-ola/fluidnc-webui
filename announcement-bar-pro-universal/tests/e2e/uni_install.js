// Installs the UNIVERSAL package through the real Extension Installer of any OpenCart 3.0.x / 4.0.x / 4.1.x shop.
// Env: OCBASE OCDB OCDIR. argv[2] = zip.
const {chromium}=require('playwright');const {execFileSync}=require('child_process');const fs=require('fs');
const BASE=process.env.OCBASE, DB=process.env.OCDB, DIR=process.env.OCDIR, ZIP=process.argv[2];
const q=s=>{try{return execFileSync('mysql',['--default-character-set=utf8mb4',DB,'-N','-e',s],{stdio:['pipe','pipe','ignore']}).toString().trim();}catch(e){return 'SQLERR';}};
const R=[];const ok=(n,c,x='')=>R.push((c?'PASS ':'FAIL ')+n+(x?' — '+x:''));
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const VER=fs.readFileSync(DIR+'/index.php','utf8').match(/define\('VERSION', '([^']+)'/)[1];
 const OC4=VER.startsWith('4'), SEP=OC4&&VER.localeCompare('4.0.2.0',undefined,{numeric:true})>=0?'.':'|';
 const b=await chromium.launch();const bag=[];const p=await (await b.newContext()).newPage();
 p.on('pageerror',e=>bag.push('JS: '+e.message));
 await p.goto(BASE+'admin/');await p.fill('#input-username','admin');await p.fill('#input-password','admin123');
 await p.click('button[type=submit]');await p.waitForURL(/user_token=/,{timeout:20000});
 const tok=new URL(p.url()).searchParams.get('user_token');const A=(r,x='')=>BASE+'admin/index.php?route='+r+'&user_token='+tok+x;
 await p.goto(A('marketplace/installer'));
 const [fc]=await Promise.all([p.waitForEvent('filechooser'),p.click('#button-upload')]);await fc.setFiles(ZIP);
 if(OC4){
   const row=p.locator('#extension tr',{hasText:'Announcement Bar Pro'});
   await row.locator('.btn-success').waitFor({timeout:30000});await row.locator('.btn-success').click();
   await row.locator('.btn-danger').waitFor({timeout:60000}).catch(()=>{});
 } else {
   await p.waitForFunction(()=>{const t=document.querySelector('#progress-text');return t&&/(Success|Error|Warning)/i.test(t.innerText)},null,{timeout:60000});
   const t=(await p.innerText('#progress-text')).trim();ok('OC3 installer accepted the universal zip', /Success/.test(t), t);
   await p.goto(A('marketplace/modification/refresh'));
 }
 await sleep(3000);
 const ctrl=OC4?DIR+'/extension/announcement_bar_pro/admin/controller/module/announcement_bar_pro.php':DIR+'/admin/controller/extension/module/announcement_bar_pro.php';
 ok('the right files for OpenCart '+VER+' were installed', fs.existsSync(ctrl));
 if(OC4) ok('OC3 files inside the OC4 extension are not callable directly', (await (await p.request.get(BASE+'extension/announcement_bar_pro/upload/catalog/controller/extension/module/announcement_bar_pro.php')).text()).trim()==='');
 else ok('no OC4 files leaked into the OC3 shop', !fs.existsSync(DIR+'/extension/announcement_bar_pro') && !fs.existsSync(DIR+'/install.json'));
 const inst=OC4?A('extension/module'+SEP+'install','&extension=announcement_bar_pro&code=announcement_bar_pro'):A('extension/extension/module/install','&extension=announcement_bar_pro');
 await p.request.get(inst);await sleep(500);
 const ev=q("select concat(`trigger`,' -> ',action) from oc_event where code='announcement_bar_pro' order by `trigger` desc").split('\n');
 ok('three events (header, footer marker, page resolver)', ev.length===3 && ev.some(e=>/common\/header\/after -> .*injectBar$/.test(e)) && ev.some(e=>/common\/footer\/after -> .*injectBarFooter$/.test(e)) && ev.some(e=>/view\/\*\/after -> .*resolveFallback$/.test(e)), ev.join(' | '));
 ok('fresh install is switched on', q("select value from oc_setting where `key`='module_announcement_bar_pro_status'")==='1');
 ok('colours default to automatic', q("select value from oc_setting where `key`='module_announcement_bar_pro_color_mode'")==='auto');
 ok('tables created', q("show tables like 'oc_abp_message%'").split('\n').length===2);
 const route=OC4?'extension/announcement_bar_pro/module/announcement_bar_pro':'extension/module/announcement_bar_pro';
 await p.goto(A(route));
 ok('module page opens', /Announcement Bar Pro/.test(await p.title()) && await p.isVisible('#tab-messages'));
 const store=await (await p.request.get(BASE)).text();
 ok('storefront fine with no messages (no bar)', !/id="abp-bar"/.test(store) && /<\/html>/i.test(store));
 const log=DIR+'/system/storage/logs/error.log';
 ok('PHP error log: nothing from this module', !fs.existsSync(log) || !/announcement_bar_pro|announcementbarpro|abp_/i.test(fs.readFileSync(log,'utf8')), fs.existsSync(log)?(fs.statSync(log).size+' bytes total (core notices on old OC + PHP 8.3 not counted)'):'');
 console.log('OpenCart '+VER);console.log(R.join('\n'));console.log('JS errors:',bag);await b.close();
})().catch(e=>{console.error(e);process.exit(1)});
