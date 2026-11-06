// Runs the OpenCart 4.0.0.x / 4.0.1.x WEB installer (their CLI installers do not work on PHP 8).
// argv: baseUrl db
const {chromium}=require('playwright');
(async()=>{const [BASE,DB]=process.argv.slice(2);const b=await chromium.launch();const p=await b.newPage();
 const log=[];p.on('pageerror',e=>log.push(e.message));
 await p.goto(BASE+'install/index.php');
 await p.click('a.btn-primary');await p.waitForLoadState('load');                       // step 1 → 2
 await p.click('input[type=submit], button[type=submit]');await p.waitForLoadState('load'); // step 2 → 3
 await p.waitForSelector('#input-db-hostname',{timeout:20000});
 const f={'#input-db-hostname':'localhost','#input-db-username':'oc','#input-db-password':'ocpass','#input-db-database':DB,'#input-db-port':'3306','#input-db-prefix':'oc_','#input-username':'admin','#input-password':'admin123','#input-email':'admin@example.com'};
 for(const [k,v] of Object.entries(f)) await p.fill(k,v);
 await p.click('#content input[type=submit], #content button[type=submit], button#button-continue').catch(async()=>{await p.click('text=Continue');});
 await p.waitForSelector('text=/Installation complete|Finished|admin/i',{timeout:60000}).catch(()=>{});
 console.log('URL after install:',p.url(),'| errors:',log.join(' ; ').slice(0,300));
 console.log((await p.innerText('body')).slice(0,300).replace(/\s+/g,' '));await b.close();})();
