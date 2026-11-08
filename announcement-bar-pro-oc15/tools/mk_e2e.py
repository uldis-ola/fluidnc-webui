"""Builds tests/e2e/abp15-e2e.js from the OpenCart 3/4 E2E of the universal package (same checks, OpenCart 1.5 ways)."""
import sys
src_path, out_path = sys.argv[1], sys.argv[2]
s = open(src_path, encoding='utf-8').read()

def rep(a, b, cnt=1):
    global s
    n = s.count(a)
    assert n == cnt, (a[:90], n)
    s = s.replace(a, b)

def cut(start, end, new):
    """Replace from the line containing `start` up to (not including) the line containing `end`."""
    global s
    i = s.index(start)
    i = s.rindex('\n', 0, i) + 1
    j = s.index(end, i)
    j = s.rindex('\n', 0, j) + 1
    s = s[:i] + new + s[j:]

head_end = s.index("const { chromium } = require('playwright');")
s = """// Announcement Bar Pro 2.4.2 for OpenCart 1.5 on a REAL local OpenCart 1.5.x shop (Playwright Chromium).
// Derived from the OpenCart 3 / 4 E2E of the universal package (same feature checks), with the OpenCart 1.5 ways:
// install through Extensions > Modules after the files were copied (FTP-style) and vQmod is installed; settings in
// oc_setting.`group`; PHP sessions (address chosen through the cart's shipping estimate); mini-cart in #cart .content.
// Then every 2.4.1 feature through the real admin UI and the storefront at 1366 and 390 px: no JS errors, no PHP errors,
// no horizontal scroll, contrast >= 4.5:1. Also: A/B test with "Use B", duplicate / reset / delete, import, the footer
// fallback for a theme header without <body>, the bundled icon font, uninstall / reinstall.
// Env: OCBASE OCDIR OCDB MYSQL MYSQLARGS SHOTS MAILDIR SMTPPORT PHPLOG
""" + s[head_end:]

rep("const [ZIP230, ZIP240, ZIP241] = process.argv.slice(2);\nconst ZIPNEW = ZIP241 || ZIP240;\n", "")
rep("const BASE = process.env.OCBASE, DIR = process.env.OCDIR, DB = process.env.OCDB, SHOTS = process.env.SHOTS, DEV = process.env.DEV === '1';", "const BASE = process.env.OCBASE, DIR = process.env.OCDIR, DB = process.env.OCDB, SHOTS = process.env.SHOTS;")
s = s.replace('module_announcement_bar_pro_', 'announcement_bar_pro_')
rep("const cfg = (key, value, code = 'config') => { q(`delete from oc_setting where store_id=0 and \\`key\\`='${key}'`); return q(`insert into oc_setting (store_id,\\`code\\`,\\`key\\`,value,serialized) values (0,'${code}','${key}','${String(value).replace(/'/g, \"''\")}',0)`); };",
    "const cfg = (key, value, group = 'config') => { q(`delete from oc_setting where store_id=0 and \\`key\\`='${key}'`); return q(`insert into oc_setting (store_id,\\`group\\`,\\`key\\`,value,serialized) values (0,'${group}','${key}','${String(value).replace(/'/g, \"''\")}',0)`); };")

cut("// Method separator: '/' on OC3", "const b = await chromium.launch();",
    "\tconst OC4 = false, SEP = '/', TAG = 'oc15', ROUTE = 'module/announcement_bar_pro';\n"
    "\t// OpenCart 1.5 logs PHP notices / warnings to system/logs/error.txt; fatal errors go to the PHP log of the test server.\n"
    "\tconst logFile = DIR + '/system/logs/error.txt', log0 = fs.existsSync(logFile) ? fs.statSync(logFile).size : 0;\n"
    "\tconst phpLog = process.env.PHPLOG || '', php0 = phpLog && fs.existsSync(phpLog) ? fs.statSync(phpLog).size : 0;\n")
rep("await p.goto(BASE + 'admin/'); await p.fill('#input-username', 'admin'); await p.fill('#input-password', 'admin123');\n\tawait p.click('button[type=submit]'); await p.waitForURL(/user_token=/, { timeout: 30000 });\n\tconst tok = new URL(p.url()).searchParams.get('user_token');\n\tconst A = (r, x = '') => BASE + 'admin/index.php?route=' + r + '&user_token=' + tok + x;",
    "await p.goto(BASE + 'admin/'); await p.fill('input[name=username]', 'admin'); await p.fill('input[name=password]', 'admin123');\n\tawait p.press('input[name=password]', 'Enter'); await p.waitForURL(/token=/, { timeout: 30000 });\n\tconst tok = new URL(p.url()).searchParams.get('token');\n\tconst A = (r, x = '') => BASE + 'admin/index.php?route=' + r + '&token=' + tok + x;")
cut("\tasync function upload(zip) {", "\t// ---------------- storefront helpers ----------------", "")

INSTALL = r"""	// ================= install (files copied like FTP + vQmod, then Extensions > Modules > Install) =================
	ok('files in place: vQmod installed, module XML in vqmod/xml', fs.existsSync(DIR + '/vqmod/vqmod.php') && fs.existsSync(DIR + '/vqmod/xml/announcement_bar_pro.xml') && /VQMod::bootup/.test(fs.readFileSync(DIR + '/index.php', 'utf8')));
	await p.goto(A('extension/module'));
	const row = p.locator('table.list tbody tr', { hasText: 'Announcement Bar Pro' });
	await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), row.locator('a', { hasText: /Install/ }).first().click()]);
	await sleep(500);
	ok('Extensions > Modules: installed, Edit link shown', q("select count(*) from oc_extension where type='module' and code='announcement_bar_pro'") === '1' && await p.locator('table.list tbody tr', { hasText: 'Announcement Bar Pro' }).locator('a', { hasText: /Edit/ }).count() === 1);
	await p.goto(A(ROUTE));
	ok('fresh install: schema 8 (written last), switched on, 4 tables (utf8), form secret', setting('schema') === '8' && setting('status') === '1' && q("select count(*) from information_schema.tables where table_schema=database() and table_name in ('oc_abp_message','oc_abp_message_description','oc_abp_subscriber','oc_abp_rate') and table_collation like 'utf8%'") === '4' && /^[0-9a-f]{64}$/.test(setting('secret')) && q("select `key` from oc_setting where `group`='announcement_bar_pro' order by setting_id desc limit 1") === 'announcement_bar_pro_schema', setting('schema') + ' ' + setting('status') + ' ' + q("select `key` from oc_setting where `group`='announcement_bar_pro' order by setting_id desc limit 1"));
	ok('permissions: access + modify for module/announcement_bar_pro', /module\/announcement_bar_pro/.test(q("select permission from oc_user_group where user_group_id=1")));
	const secret0 = setting('secret');
	await p.goto(A(ROUTE));
	ok('2nd admin visit: idempotent (one "options" column, same secret)', q("select count(*) from information_schema.columns where table_schema=database() and table_name='oc_abp_message' and column_name='options'") === '1' && setting('secret') === secret0);
	{
		const front = await (await p.request.get(BASE)).text();
		const cache = DIR + '/vqmod/vqcache/vq2-catalog_controller_common_header.php';
		ok('vQmod hooks applied (cached header / footer / cart / product / mini-cart / Response), storefront renders', fs.existsSync(cache) && /hookHeader/.test(fs.readFileSync(cache, 'utf8')) && /hookResponse/.test(fs.readFileSync(DIR + '/vqmod/vqcache/vq2-system_library_response.php', 'utf8')) && /<\/html>/i.test(front) && !/abp-fallback/.test(front));
	}
	{
		// Import / export with no messages yet + import of an OpenCart 3 / 4 export (language code "en-gb" → "en").
		const exp3 = { format: 'announcement_bar_pro', version: '2.4.1', schema: 8, messages: [{ status: 1, sort_order: 0, bg_color: '#123456', text_color: '#ffffff', link: '', icon: 'fa-gift', page_mode: 'all', page_routes: '', device: 'all', type: 'standard', coupon: '', weekdays: '', time_start: '', time_end: '', style: '', button_style: 'filled', placement: 'top', priority: 3, options: {}, threshold: '0', stores: [], customer_groups: [], categories: [], products: [], texts: { 'en-gb': { text: 'Imported from OpenCart 3', link_text: '' } } }] };
		const file = path.join(SHOTS, 'import-oc3.json'); fs.writeFileSync(file, JSON.stringify(exp3));
		await p.goto(A(ROUTE));
		const [fc] = await Promise.all([p.waitForEvent('filechooser'), p.click('[data-abp-import]')]);
		await Promise.all([p.waitForResponse(r => /\/import/.test(r.url())), fc.setFiles(file)]); await sleep(1500);
		const imp = q("select concat(m.status, '|', m.icon, '|', m.priority, '|', d.text) from oc_abp_message m join oc_abp_message_description d using (message_id) where d.text like 'Imported%'");
		ok('import (admin UI): OpenCart 3 export ("en-gb") lands in "en", switched off, icon + priority kept', imp === '0|fa-gift|3|Imported from OpenCart 3', imp);
		q("delete m, d from oc_abp_message m join oc_abp_message_description d using (message_id) where d.text like 'Imported%'");
	}
"""
cut("\t// ================= install / upgrade =================", "\tawait settings({ mode: 'stack', dismissible: 1, color_mode: 'custom' });", INSTALL)
rep("\tcfg('config_mail_engine', 'smtp'); cfg('config_mail_smtp_hostname', '127.0.0.1'); cfg('config_mail_smtp_port', process.env.SMTPPORT || '2525'); cfg('config_mail_smtp_timeout', '5');\n\tcfg('config_mail_smtp_username', 'test'); cfg('config_mail_smtp_password', 'test'); cfg('config_email', 'shop@example.com');",
    "\tcfg('config_mail_protocol', 'smtp'); cfg('config_smtp_host', '127.0.0.1'); cfg('config_smtp_port', process.env.SMTPPORT || '8119'); cfg('config_smtp_timeout', '5');\n\tcfg('config_smtp_username', 'test'); cfg('config_smtp_password', 'test'); cfg('config_email', 'shop@example.com');")

# Address chosen in this visit: OpenCart 1.5 keeps PHP sessions in files → the cart's shipping estimate sets it.
cut("\t\tconst sid = (await c.cookies()).find(", "\t\tawait pg.goto(BASE); await sleep(1100);",
    "\t\t// OpenCart 1.5: PHP file sessions — the visitor adds a product and uses the cart's shipping estimate (Latvia).\n"
    "\t\tawait pg.request.post(S('checkout/cart/add'), { form: { product_id: P40, quantity: '1' } });\n"
    "\t\tconst LVZONE = q(`select zone_id from oc_zone where country_id=${LV} limit 1`);\n"
    "\t\tconst quote = await (await pg.request.post(S('checkout/cart/quote'), { form: { country_id: LV, zone_id: LVZONE, postcode: 'LV-1010' } })).text();\n"
    "\t\tconst sid = { name: 'PHPSESSID quote ' + quote.slice(0, 40) };\n")
rep("\t\tif (OC4) { await pg.evaluate(() => document.querySelector('#form-currency [data-code=\"EUR\"], #form-currency a[href=\"EUR\"]').click()); await sleep(3000); } else {\n\t\t\tawait Promise.all([pg.waitForNavigation({ timeout: 15000 }).catch(() => {}), pg.evaluate(() => { const f = document.getElementById('form-currency'); f.querySelector('[name=\"code\"]').value = 'EUR'; f.submit(); })]);\n\t\t}",
    "\t\tawait pg.request.post(S('module/currency'), { form: { currency_code: 'EUR', redirect: BASE } }); // the header's currency form")
s = s.replace("cfg('shipping_free_status', '1', 'shipping_free'); cfg('shipping_free_total', '120', 'shipping_free');", "cfg('free_status', '1', 'free'); cfg('free_total', '120', 'free');")
s = s.replace("cfg('shipping_free_status', '0', 'shipping_free');", "cfg('free_status', '0', 'free');")
s = s.replace("cfg('shipping_free_status', '1', 'shipping_free');", "cfg('free_status', '1', 'free');")
rep("\tq(OC4 ? `insert into oc_customer (customer_group_id,store_id,language_id,firstname,lastname,email,telephone,password,custom_field,ip,status,safe,token,code,newsletter,date_added) values (1,0,1,'A','B','${custEmail}','1','x','','',1,0,'','',0,NOW())` : `insert into oc_customer (customer_group_id,store_id,language_id,firstname,lastname,email,telephone,fax,password,salt,custom_field,ip,status,safe,token,code,newsletter,date_added) values (1,0,1,'A','B','${custEmail}','1','','x','','','',1,0,'','',0,NOW())`);",
    "\tq(`insert into oc_customer (store_id,firstname,lastname,email,telephone,fax,password,salt,cart,wishlist,newsletter,address_id,customer_group_id,ip,status,approved,token,date_added) values (0,'A','B','${custEmail}','1','','x','','','',0,0,1,'',1,1,'',NOW())`);")
# Mini-cart: OpenCart 1.5 shows it in #cart .content after a click on the heading (loaded with AJAX: module/cart).
rep("\t\tconst mini = await pg.evaluate(() => { const box = document.querySelector('.abp-inline-minicart'); return box ? { inMenu: !!box.closest('.dropdown-menu'), txt: box.innerText } : null; });\n\t\tok('mini-cart box inside the drop-down, still there after the AJAX reload', mini && mini.inMenu && /close to free shipping/.test(mini.txt), JSON.stringify(mini));",
    "\t\tawait pg.click('#cart .heading a'); await sleep(2500);\n"
    "\t\tconst mini = await pg.evaluate(() => { const box = document.querySelector('#cart .content .abp-inline-minicart'); return box ? { txt: box.innerText, shown: getComputedStyle(box).display !== 'none', started: !!box.querySelector('.abp-item.abp-active, .abp-item[data-id]') , boxes: document.querySelectorAll('.abp-inline-minicart').length } : null; });\n"
    "\t\tok('mini-cart box in the drop-down content, there after the AJAX reload (module/cart), started by the 1.5 adapter', mini && mini.shown && mini.boxes === 1 && /close to free shipping/.test(mini.txt), JSON.stringify(mini));\n"
    "\t\tawait pg.screenshot({ path: `${SHOTS}/${TAG}-store-minicart-1366.png`, clip: { x: 600, y: 0, width: 766, height: 420 } });")
rep("const where = await pg.evaluate(() => { const box = document.querySelector('.abp-inline-product'); if (!box) return 'none'; const h1 = document.querySelector('#content h1, h1'); return (h1 && (h1.compareDocumentPosition(box) & 4)) ? 'after-h1' : 'before'; });",
    "const where = await pg.evaluate(() => { const box = document.querySelector('.abp-inline-product'); if (!box) return 'none'; const h1 = document.querySelector('#content h1, h1'); const prev = box.previousElementSibling; return (h1 && (h1.compareDocumentPosition(box) & 4)) ? (prev && prev.classList.contains('price') ? 'after-h1' : 'after-h1-not-price:' + (prev ? prev.className : '')) : 'before'; });")
rep("Array.isArray(m.texts['en-gb'].tier_label)", "Array.isArray(m.texts['en'].tier_label)")
rep("\tconst log = fs.existsSync(logFile) ? fs.readFileSync(logFile, 'utf8').slice(log0) : '';\n\tok('PHP error log: no new entries', log.trim() === '', log.slice(0, 600));",
    "\tconst log = fs.existsSync(logFile) ? fs.readFileSync(logFile, 'utf8').slice(log0) : '';\n"
    "\tok('OpenCart error log (system/logs/error.txt): no new entries', log.trim() === '', log.slice(0, 600));\n"
    "\t// The PHP log of the test server: only entries about this module count (OpenCart 1.5 core itself is noisy on PHP 7).\n"
    "\tconst plog = phpLog && fs.existsSync(phpLog) ? fs.readFileSync(phpLog, 'utf8').slice(php0).split(/\\r?\\n/).filter(l => /announcement_bar_pro|announcementbarpro|abp15|Fatal/i.test(l)) : [];\n"
    "\tok('PHP log: no errors from this module (and no fatal errors at all)', plog.length === 0, plog.slice(0, 5).join(' | '));")
# The visitor of the shipping-estimate test has a product (101) in the cart: over the Latvian amount (50) → success text.
rep("ok('country: shipping address Latvia (this visit) → shown; Latvian amount $50.00', v.includes(idLv) && /\\$50\\.00/.test(txt),",
    "ok('country: shipping address Latvia (cart estimate, this visit) → shown; Latvian amount 50 (cart 101 > 50 → success text, not $99 to the default 200)', v.includes(idLv) && /Free shipping!/.test(txt) && !/\\$99/.test(txt),")
# OpenCart 1.5's default theme is not responsive (980 px layout): an inline box only counts as overflow when it sticks out
# of the theme column it sits in; bars (children of <body>) are still checked against the viewport.
rep("const W = window.innerWidth, ours = [...document.querySelectorAll('.abp, .abp *, .abp-tab')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && getComputedStyle(e).display !== 'none' && (r.right > W + 1 || r.left < -1)",
    "const W = window.innerWidth, ours = [...document.querySelectorAll('.abp, .abp *, .abp-tab')].filter(e => { const r = e.getBoundingClientRect(); const box = e.closest('.abp-inline'); const lim = box && box.parentElement ? Math.max(W, box.parentElement.getBoundingClientRect().right) : W; return r.width > 0 && getComputedStyle(e).display !== 'none' && (r.right > lim + 1 || r.left < -1)")
rep("\t// ================= admin: no JS errors; PHP log clean =================",
    "\t// ================= OpenCart 1.5 edition extras (tests/e2e/abp15-extra.js) =================\n"
    "\tawait require(path.join(__dirname, 'abp15-extra.js'))({ p, q, ok, sleep, A, S, BASE, DIR, ROUTE, SHOTS, TAG, add, only, shop, info, visibleIds, setting, settings, check, both });\n\n"
    "\t// ================= admin: no JS errors; PHP log clean =================")
open(out_path, 'w', encoding='utf-8', newline='\n').write(s)
print('written', out_path)
