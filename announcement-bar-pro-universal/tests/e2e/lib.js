const {chromium}=require('playwright');
const BASE=process.env.OCBASE||'http://localhost:8080/';
async function login(page){
  await page.goto(BASE+'admin/');
  await page.fill('#input-username','admin'); await page.fill('#input-password','admin123');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  const tok=new URL(page.url()).searchParams.get('user_token'); if(!tok) throw new Error('login failed '+page.url());
  return tok;
}
async function uploadExt(page,tok,zip){
  await page.goto(BASE+'admin/index.php?route=marketplace/installer&user_token='+tok);
  const [fc]=await Promise.all([page.waitForEvent('filechooser'), page.click('#button-upload')]);
  await fc.setFiles(zip);
  await page.waitForFunction(()=>{const t=document.querySelector('#progress-text');return t&&/(Success|Error|Warning)/i.test(t.innerText)},{timeout:60000});
  const txt=await page.innerText('#progress-text');
  await page.goto(BASE+'admin/index.php?route=marketplace/modification/refresh&user_token='+tok);
  await page.waitForTimeout(3000); // OPcache revalidate_freq=2s: give PHP time to see the new files
  return txt.trim();
}
async function installModule(page,tok){
  await page.goto(BASE+'admin/index.php?route=extension/extension/module/install&user_token='+tok+'&extension=announcement_bar_pro');
}
function errs(page,bag){page.on('pageerror',e=>bag.push('JS: '+e.message));page.on('console',m=>{if(m.type()==='error')bag.push('console: '+m.text())});}
module.exports={chromium,BASE,login,uploadExt,installModule,errs};
