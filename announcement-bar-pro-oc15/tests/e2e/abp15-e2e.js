// Announcement Bar Pro 2.4.1 for OpenCart 1.5 on a REAL local OpenCart 1.5.x shop (Playwright Chromium).
// Derived from the OpenCart 3 / 4 E2E of the universal package (same feature checks), with the OpenCart 1.5 ways:
// install through Extensions > Modules after the files were copied (FTP-style) and vQmod is installed; settings in
// oc_setting.`group`; PHP sessions (address chosen through the cart's shipping estimate); mini-cart in #cart .content.
// Then every 2.4.1 feature through the real admin UI and the storefront at 1366 and 390 px: no JS errors, no PHP errors,
// no horizontal scroll, contrast >= 4.5:1. Also: A/B test with "Use B", duplicate / reset / delete, import, the footer
// fallback for a theme header without <body>, the bundled icon font, uninstall / reinstall.
// Env: OCBASE OCDIR OCDB MYSQL MYSQLARGS SHOTS MAILDIR SMTPPORT PHPLOG
// Generated once by tools/mk_e2e.py from the universal package's abp24-e2e.js (2.4.1), then maintained here.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const sha256 = s => crypto.createHash('sha256').update(s).digest('hex');
const BASE = process.env.OCBASE, DIR = process.env.OCDIR, DB = process.env.OCDB, SHOTS = process.env.SHOTS;
const MAILDIR = process.env.MAILDIR || 'C:/Users/Admin/abp-testenv/mail';
// On Windows a mariadb client start can fail now and then under load (seen on OC4 in 2.4.0 testing and, 2.4.1, as a
// one-off "settings kept" failure on OC3). Up to 4 tries with a short pause; every failed try is printed (SQLRETRY) so a
// client failure can never again look like a module failure.
const q1 = s => { try { return execFileSync(process.env.MYSQL, [...process.env.MYSQLARGS.split(' '), DB, '-N', '-e', s], { stdio: ['pipe', 'pipe', 'pipe'] }).toString().trim(); } catch (e) { return 'SQLERR ' + String((e.stderr || '') + ' ' + e.message).replace(/\s+/g, ' ').slice(0, 200); } };
const q = s => { let r = q1(s); for (let i = 1; i < 4 && r.startsWith('SQLERR'); i++) { console.log('SQLRETRY ' + i + ': ' + r + ' | ' + s.slice(0, 80)); execFileSync(process.execPath, ['-e', 'setTimeout(()=>{},300)']); r = q1(s); } return r; };
const R = []; const ok = (n, c, x = '') => { R.push((c ? 'PASS ' : 'FAIL ') + n + (!c && x !== '' ? ' — ' + String(x).slice(0, 500) : '')); console.log(R[R.length - 1]); };
const sleep = ms => new Promise(r => setTimeout(r, ms));
const setting = k => q("select value from oc_setting where `key`='announcement_bar_pro_" + k + "'");
const cfg = (key, value, group = 'config') => { q(`delete from oc_setting where store_id=0 and \`key\`='${key}'`); return q(`insert into oc_setting (store_id,\`group\`,\`key\`,value,serialized) values (0,'${group}','${key}','${String(value).replace(/'/g, "''")}',0)`); };
const only = ids => q(`update oc_abp_message set status = (message_id in (${[].concat(ids).join(',') || 0}))`);
const lum = rgb => { const c = rgb.map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
// The mariadb client escapes backslashes in -N output ("\/" comes out as "\\/"): undo before JSON.parse.
const optOf = id => { try { return JSON.parse(q(`select options from oc_abp_message where message_id=${id}`).replace(/\\\\/g, '\\')); } catch (e) { return {}; } };
// Text of an e-mail: base64 / quoted-printable parts decoded.
const mailText = raw => raw.split(/Content-Transfer-Encoding:\s*base64\s*\r?\n\r?\n/i).slice(1).map(part => Buffer.from(part.split(/\r?\n\r?\n|\r?\n--/)[0].replace(/\s+/g, ''), 'base64').toString('utf8')).join('\n') + raw.replace(/=\r?\n/g, '').replace(/=3D/g, '=');
const contrast = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };

(async () => {
	fs.mkdirSync(SHOTS, { recursive: true });
	const VER = fs.readFileSync(DIR + '/index.php', 'utf8').match(/define\('VERSION', '([^']+)'/)[1];
	const OC4 = false, SEP = '/', TAG = 'oc15', ROUTE = 'module/announcement_bar_pro';
	// OpenCart 1.5 logs PHP notices / warnings to system/logs/error.txt; fatal errors go to the PHP log of the test server.
	const logFile = DIR + '/system/logs/error.txt', log0 = fs.existsSync(logFile) ? fs.statSync(logFile).size : 0;
	const phpLog = process.env.PHPLOG || '', php0 = phpLog && fs.existsSync(phpLog) ? fs.statSync(phpLog).size : 0;
	const b = await chromium.launch();
	const ctx = await b.newContext({ viewport: { width: 1366, height: 900 }, acceptDownloads: true });
	const p = await ctx.newPage();
	const adminErrs = []; p.on('pageerror', e => adminErrs.push(e.message));
	p.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/i.test(m.text())) adminErrs.push(m.text()); });
	p.on('dialog', d => d.accept());
	await p.goto(BASE + 'admin/'); await p.fill('input[name=username]', 'admin'); await p.fill('input[name=password]', 'admin123');
	await p.press('input[name=password]', 'Enter'); await p.waitForURL(/token=/, { timeout: 30000 });
	const tok = new URL(p.url()).searchParams.get('token');
	const A = (r, x = '') => BASE + 'admin/index.php?route=' + r + '&token=' + tok + x;
	const S = r => BASE + 'index.php?route=' + r;

	// ---------------- storefront helpers ----------------
	const shopErrs = [];
	async function shop(opts = {}, init) {
		const headers = opts.shopHeaders; delete opts.shopHeaders;
		const c = await b.newContext(Object.assign({ viewport: { width: 1366, height: 900 } }, opts));
		if (init) { await c.addInitScript(init); }
		// Extra request headers (CloudFlare country) only for the shop itself, like a real CDN would add them.
		if (headers) { await c.route(u => u.href.startsWith(BASE), r => r.continue({ headers: Object.assign({}, r.request().headers(), headers) })); }
		const pg = await c.newPage();
		pg.on('pageerror', e => shopErrs.push(e.message));
		pg.on('console', m => { if (m.type() === 'error' && !/favicon|404|Failed to load resource/i.test(m.text())) shopErrs.push(m.text()); });
		return { c, pg };
	}
	const info = (pg, sel = '.abp[data-abp]') => pg.evaluate(s => [...document.querySelectorAll(s)].map(bar => ({
		id: bar.id, place: bar.dataset.place, shown: getComputedStyle(bar).display !== 'none',
		items: [...bar.querySelectorAll('.abp-item')].map(i => ({ id: i.dataset.id, vis: getComputedStyle(i).display !== 'none', txt: i.innerText.replace(/\s+/g, ' ').trim() }))
	})), sel);
	const visibleIds = async pg => (await info(pg)).filter(x => x.shown).flatMap(x => x.items.filter(i => i.vis).map(i => i.id));
	const visText = async pg => (await info(pg)).filter(x => x.shown).flatMap(x => x.items.filter(i => i.vis).map(i => i.txt)).join(' || ');
	// Layout + contrast audit of what is on screen.
	async function audit(pg, label) {
		const r = await pg.evaluate(() => {
			const parse = c => { const m = /rgba?\(([^)]+)\)/.exec(c || ''); if (!m) return null; const p = m[1].split(/[\s,\/]+/).filter(Boolean).map(parseFloat); return p.length > 3 && p[3] === 0 ? null : p.slice(0, 3); };
			// Horizontal overflow caused by the module: any bar / box / tab element sticking out of the viewport
			// (the default OpenCart 4 theme's own home-page carousel arrows overflow by a few px at 390 px).
			const W = window.innerWidth, ours = [...document.querySelectorAll('.abp, .abp *, .abp-tab')].filter(e => { const r = e.getBoundingClientRect(); const box = e.closest('.abp-inline'); /* OpenCart 1.5's default theme is a fixed 980 px layout: an inline box counts only when it sticks out of its theme column */ const lim = box && box.parentElement ? Math.max(W, box.parentElement.getBoundingClientRect().right) : W; return r.width > 0 && getComputedStyle(e).display !== 'none' && (r.right > lim + 1 || r.left < -1) && !e.closest('.abp-mode-marquee .abp-track') && !e.closest('.abp-hp'); });
			const out = { hscroll: ours.length > 0 ? ours.slice(0, 3).map(e => e.className + ':' + Math.round(e.getBoundingClientRect().right)) : false, page: document.documentElement.scrollWidth > W + 1, pairs: [] };
			document.querySelectorAll('.abp[data-abp]').forEach(bar => {
				if (getComputedStyle(bar).display === 'none') return;
				bar.querySelectorAll('.abp-item').forEach(it => {
					if (getComputedStyle(it).display === 'none' || it.hasAttribute('data-img') || bar.classList.contains('abp-glass')) return;
					const fg = parse(getComputedStyle(it).color), bg = parse(getComputedStyle(it).backgroundColor) || parse(getComputedStyle(bar).backgroundColor);
					if (fg && bg) out.pairs.push({ id: it.dataset.id, fg, bg });
				});
			});
			return out;
		});
		const low = r.pairs.filter(x => contrast(x.fg, x.bg) < 4.5).map(x => x.id + ':' + contrast(x.fg, x.bg).toFixed(2));
		ok(`${label}: no horizontal scroll, contrast ≥ 4.5:1 (${r.pairs.length} messages)`, !r.hscroll && !low.length, JSON.stringify({ h: r.hscroll, low }));
	}
	async function both(url, label, check, opts = {}) {
		for (const w of [1366, 390]) {
			const { c, pg } = await shop(Object.assign({ viewport: { width: w, height: w > 400 ? 900 : 844 } }, opts.ctx || {}), opts.init);
			await pg.goto(url); await sleep(opts.wait || 1300);
			if (check) { await check(pg, w); }
			await audit(pg, `${label} @${w}`);
			await pg.screenshot({ path: `${SHOTS}/${TAG}-${label.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}-${w}.png`, fullPage: false });
			await c.close();
		}
	}

	// ---------------- admin helpers (real UI) ----------------
	const FORM = id => A(ROUTE + SEP + 'form', id ? '&message_id=' + id : '');
	async function saveForm(id, fill) {
		await p.goto(FORM(id));
		await p.waitForSelector('#form-message');
		await fill();
		await Promise.all([p.waitForURL(/tab=messages/, { timeout: 15000 }).catch(() => {}), p.click('button[form="form-message"]')]);
		await sleep(700);
		if (/tab=messages/.test(p.url())) return '';
		return (await p.$$eval('.text-danger, .invalid-feedback.d-block, .alert-danger', es => es.map(e => e.textContent.trim()).filter(Boolean).join(' | '))) || 'not saved';
	}
	const newId = () => q('select max(message_id) from oc_abp_message');
	async function add(fill, text) {
		const e = await saveForm(0, async () => { await p.fill('textarea[name="message_description[1][text]"]', text); await fill(); });
		return [e, newId()];
	}
	const check = async (name, on = true) => { await sleep(350); await p.setChecked(`[name="${name}"]`, on, { force: true }); };
	const pickChip = async (boxSel, typed, re) => {
		await p.fill(boxSel + ' .abp-ac-input', typed);
		const opt = p.locator(boxSel + ' .abp-ac-opt', { hasText: re });
		await opt.first().waitFor({ timeout: 8000 });
		await opt.first().dispatchEvent('mousedown');
		await sleep(200);
	};
	async function settings(changes) {
		await p.goto(A(ROUTE, '&tab=settings'));
		await p.waitForSelector('#form-settings');
		// The server validates every value; switch off the browser's own checks so out-of-range values reach it.
		await p.evaluate(() => { document.getElementById('form-settings').noValidate = true; });
		for (const [name, v] of Object.entries(changes)) {
			const full = 'announcement_bar_pro_' + name;
			const el = await p.$(`[name="${full}"]:not([type=hidden])`);
			const type = await el.evaluate(e => e.type);
			if (type === 'radio') { await p.click(`label[for="input-${name}-${v}"]`); }
			else if (type === 'checkbox') { await p.setChecked(`[name="${full}"][type=checkbox]`, !!v, { force: true }); }
			else if (type === 'select-one') { await p.selectOption(`[name="${full}"]`, String(v)); }
			else { await p.fill(`[name="${full}"]`, String(v)); }
		}
		if (OC4) {
			const [resp] = await Promise.all([p.waitForResponse(r => /\.save&|\.save$|%7Csave|\|save/.test(r.url()) && r.request().method() === 'POST'), p.click('#form-settings button[type=submit]')]);
			const j = await resp.json().catch(() => ({}));
			await sleep(300);
			return j.success ? '' : JSON.stringify(j.error || j);
		}
		await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.click('button[form="form-settings"]')]);
		await sleep(400);
		return p.$$eval('.text-danger, .alert-danger', es => es.map(e => e.textContent.trim()).filter(Boolean).join(' | '));
	}

	// ================= install (files copied like FTP + vQmod, then Extensions > Modules > Install) =================
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
	await settings({ mode: 'stack', dismissible: 1, color_mode: 'custom' });
	cfg('config_mail_protocol', 'smtp'); cfg('config_smtp_host', '127.0.0.1'); cfg('config_smtp_port', process.env.SMTPPORT || '8119'); cfg('config_smtp_timeout', '5');
	cfg('config_smtp_username', 'test'); cfg('config_smtp_password', 'test'); cfg('config_email', 'shop@example.com');
	q('delete from oc_abp_rate');

	// ================= templates (one click fills a new message) =================
	await p.goto(FORM(0));
	await p.waitForSelector('#abp-templates .abp-tpl');
	const tplCount = await p.$$eval('#abp-templates .abp-tpl', l => l.length);
	ok('templates: 10–15 ready-made templates on a new message', tplCount >= 10 && tplCount <= 15, String(tplCount));
	await p.click('#abp-templates .abp-tpl >> text=/Reward tiers/');
	await sleep(500);
	const tplVals = await p.evaluate(() => ({ type: document.querySelector('[name="type"]').value, text: document.querySelector('[name="message_description[1][text]"]').value, tiers: [...document.querySelectorAll('[name="opt[tiers][]"]')].map(i => i.value).join(','), labels: [...document.querySelectorAll('[name="message_description[1][tier_label][]"]')].map(i => i.value).filter(Boolean).join(',') }));
	ok('template "Reward tiers" fills type, text, 3 tier amounts + labels', tplVals.type === 'tiers' && /\{remaining\}/.test(tplVals.text) && tplVals.tiers.startsWith('50,100,150') && tplVals.labels.split(',').length === 3, JSON.stringify(tplVals));
	await sleep(1200);
	const pvFrame = () => p.frames().find(f => f !== p.mainFrame() && f.parentFrame() === p.mainFrame());
	let pv = await pvFrame().evaluate(() => ({ steps: document.querySelectorAll('.abp-step').length, txt: document.querySelector('.abp').innerText })).catch(e => ({ err: e.message }));
	ok('preview: tiered goal with 3 markers', pv.steps === 3 && /Free shipping/.test(pv.txt), JSON.stringify(pv));
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-template-tiers.png` });
	await Promise.all([p.waitForURL(/tab=messages/, { timeout: 15000 }).catch(() => {}), p.click('button[form="form-message"]')]); await sleep(700);
	const idTiers = newId();
	ok('template saved as a message (type tiers, 3 tiers, labels in extra)', q(`select type from oc_abp_message where message_id=${idTiers}`) === 'tiers' && /"tiers":\["50.0000","100.0000","150.0000"\]/.test(q(`select options from oc_abp_message where message_id=${idTiers}`)) && /Free shipping/.test(q(`select extra from oc_abp_message_description where message_id=${idTiers}`)), q(`select options from oc_abp_message where message_id=${idTiers}`));

	// ================= list: badges with visible labels =================
	let e, id;
	[e, id] = await add(async () => { await pickChip('#input-categories', 'Desk', /Desktops/); }, 'Targeted by category');
	const idCat = id;
	await p.goto(A(ROUTE));
	const badge = await p.$eval(`tr[data-message="${idCat}"] .abp-b-target`, el => el.innerText.trim()).catch(() => '');
	ok('list: targeting badge shows a visible label (not icon only)', !e && badge.length >= 3, e + ' badge=' + badge);
	const tiersBadge = await p.$eval(`tr[data-message="${idTiers}"] .abp-badges`, el => el.innerText).catch(() => '');
	ok('list: tiers badge', /3/.test(tiersBadge), tiersBadge);
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-list.png`, fullPage: true });

	// ================= 4. tiered goals in the shop (+ AJAX cart) =================
	only([idTiers]);
	const P40 = '40', price40 = parseFloat(q('select price from oc_product where product_id=40'));
	{
		const { c, pg } = await shop();
		await pg.goto(S('product/product&product_id=' + P40)); await sleep(1300);
		const t = () => pg.$eval(`.abp-item[data-id="${idTiers}"]`, el => ({ t: el.innerText.replace(/\s+/g, ' '), steps: el.querySelectorAll('.abp-step').length, done: el.querySelectorAll('.abp-step-done').length, w: el.querySelector('.abp-progress-fill').style.width }));
		const t0 = await t();
		ok('tiers: empty cart → $50.00 to the first reward, 3 markers, none done', /Add \$50\.00 more to unlock: Free shipping/.test(t0.t) && t0.steps === 3 && t0.done === 0, JSON.stringify(t0));
		await pg.click('#button-cart'); await sleep(2800);
		const t1 = await t();
		const left = (150 - price40 * 1).toFixed(2);
		ok('tiers: AJAX add-to-cart → first two tiers reached, next reward + amount updated without reload', t1.done === 2 && t1.t.includes('$' + left) && /10% off/.test(t1.t), JSON.stringify(t1));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-tiers.png`, clip: { x: 0, y: 0, width: 1366, height: 160 } });
		await pg.click('#button-cart'); await sleep(2800);
		const t2 = await t();
		ok('tiers: all reached → success text, all markers done', /Every reward unlocked/.test(t2.t) && t2.done === 3, JSON.stringify(t2));
		await c.close();
	}

	// ================= 1. country targeting + 2. per-country / per-currency amounts =================
	const LV = q("select country_id from oc_country where iso_code_2='LV'"), store = q("select value from oc_setting where `key`='config_country_id' and store_id=0");
	[e, id] = await add(async () => {
		await p.selectOption('[name="opt[countries_mode]"]', 'include');
		await pickChip('#input-countries', 'Latv', /Latvia/);
	}, 'Only for Latvia');
	const idLv = id;
	ok('country targeting: saved via the form (include Latvia)', !e && new RegExp('"countries_mode":"include","countries":\\[' + LV + '\\]').test(q(`select options from oc_abp_message where message_id=${idLv}`)), e + ' ' + q(`select options from oc_abp_message where message_id=${idLv}`));
	[e, id] = await add(async () => {
		await p.selectOption('[name="type"]', 'free_shipping');
		await p.fill('[name="threshold"]', '200');
		await p.fill('textarea[name="message_description[1][success_text]"]', 'Free shipping!');
		await p.selectOption('[name="opt[fs_zones][0][country_id]"]', LV);
		await sleep(800);
		await p.fill('[name="opt[fs_zones][0][amount]"]', '50');
		await p.fill('[name="opt[fs_currencies][EUR]"]', '100');
	}, 'Add {remaining} more for free shipping');
	const idFs = id;
	ok('free shipping overrides saved (LV 50, EUR 100)', !e && /"fs_zones":\[\{"country_id":\d+,"zone_id":0,"amount":"50.0000"\}\],"fs_currencies":\{"EUR":"100.0000"\}/.test(q(`select options from oc_abp_message where message_id=${idFs}`)), e + ' ' + q(`select options from oc_abp_message where message_id=${idFs}`));
	only([idLv, idFs]);
	{
		let { c, pg } = await shop();
		await pg.goto(BASE); await sleep(1200);
		let v = await visibleIds(pg), txt = await visText(pg);
		ok('country: guest, store country (not Latvia) → Latvia-only message hidden; default amount $200.00', !v.includes(idLv) && /\$200\.00/.test(txt), v.join() + ' ' + txt);
		await c.close();
		await settings({ geo_cf: 1 });
		({ c, pg } = await shop({ shopHeaders: { 'cf-ipcountry': 'LV' } }));
		await pg.goto(BASE); await sleep(1200);
		v = await visibleIds(pg); txt = await visText(pg);
		// 2.4.0 review: CF-IPCountry counts only when REMOTE_ADDR is one of CloudFlare's ranges (unit-tested); this
		// request comes from 127.0.0.1, so a forged header must change nothing even with the setting on.
		ok('country: forged CF-IPCountry LV from a non-CloudFlare address (setting on) → ignored, default amount', !v.includes(idLv) && /\$200\.00/.test(txt), v.join() + ' ' + txt);
		await c.close();
		await settings({ geo_cf: 0 });
		({ c, pg } = await shop({ shopHeaders: { 'cf-ipcountry': 'LV' } }));
		await pg.goto(BASE); await sleep(1000);
		ok('country: CF header ignored while the setting is off', !(await visibleIds(pg)).includes(idLv));
		await c.close();
		// Latvian visitor through the address chosen in this visit (session) → Latvian amount.
		({ c, pg } = await shop());
		await pg.goto(BASE); await sleep(600);
		// OpenCart 1.5: PHP file sessions — the visitor adds a product and uses the cart's shipping estimate (Latvia).
		await pg.request.post(S('checkout/cart/add'), { form: { product_id: P40, quantity: '1' } });
		const LVZONE = q(`select zone_id from oc_zone where country_id=${LV} limit 1`);
		const quote = await (await pg.request.post(S('checkout/cart/quote'), { form: { country_id: LV, zone_id: LVZONE, postcode: 'LV-1010' } })).text();
		const sid = { name: 'PHPSESSID quote ' + quote.slice(0, 40) };
		await pg.goto(BASE); await sleep(1100);
		v = await visibleIds(pg); txt = await visText(pg);
		ok('country: shipping address Latvia (cart estimate, this visit) → shown; Latvian amount 50 (cart 101 > 50 → success text, not $99 to the default 200)', v.includes(idLv) && /Free shipping!/.test(txt) && !/\$99/.test(txt), v.join() + ' ' + txt + ' sid=' + (sid ? sid.name : 'none'));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-country-lv.png`, clip: { x: 0, y: 0, width: 1366, height: 160 } });
		await c.close();
		({ c, pg } = await shop());
		await pg.goto(BASE); await sleep(800);
		// visitor's currency EUR: fixed amount 100 € (no conversion); OC 4.0.0.x–4.0.1.x: <a href="EUR">, later data-code
		await pg.request.post(S('module/currency'), { form: { currency_code: 'EUR', redirect: BASE } }); // the header's currency form
		await pg.goto(BASE); await sleep(1000);
		txt = await visText(pg);
		ok('per-currency: EUR visitor sees the fixed 100.00€ (not a conversion of $200)', /100\.00\s?€|€\s?100\.00/.test(txt), txt);
		await c.close();
		await settings({ geo_fallback: 'none' });
		({ c, pg } = await shop()); await pg.goto(BASE); await sleep(1000);
		ok('country: fallback "unknown" → "only in" message hidden, default amount', !(await visibleIds(pg)).includes(idLv) && /\$200\.00/.test(await visText(pg)));
		await c.close();
		await settings({ geo_fallback: 'store' });
	}

	// ================= 3. OpenCart coupon + OpenCart free-shipping amount =================
	q("delete from oc_coupon where code in ('ABP24','ABP24USED')");
	q("insert into oc_coupon (name,code,type,discount,logged,shipping,total,date_start,date_end,uses_total,uses_customer,status,date_added) values ('ABP test','ABP24','P',10,0,0,0,'2020-01-01','2099-12-31',0,0,1,NOW())");
	const cid = q("select coupon_id from oc_coupon where code='ABP24'");
	[e, id] = await add(async () => { await pickChip('#input-coupon-id', 'ABP', /ABP24/); }, 'Use this code at checkout:');
	const idCp = id;
	ok('OpenCart coupon chosen with autocomplete and saved', !e && new RegExp('"coupon_id":' + cid + '\\b').test(q(`select options from oc_abp_message where message_id=${idCp}`)), e);
	cfg('free_status', '1', 'free'); cfg('free_total', '120', 'free');
	[e, id] = await add(async () => { await p.selectOption('[name="type"]', 'free_shipping'); await check('opt[fs_oc]'); await p.fill('textarea[name="message_description[1][success_text]"]', 'Free shipping (OpenCart)!'); }, 'Only {remaining} to free shipping');
	const idOc = id;
	ok('free shipping from OpenCart\'s own method: saved without own amount', !e && /"fs_oc":1/.test(q(`select options from oc_abp_message where message_id=${idOc}`)), e);
	only([idCp, idOc]);
	{
		const shot = async () => { const { c, pg } = await shop(); await pg.goto(BASE); await sleep(1100); const r = { v: await visibleIds(pg), t: await visText(pg) }; await c.close(); return r; };
		let r = await shot();
		ok('coupon: valid OpenCart coupon → code ABP24 shown with Copy', r.v.includes(idCp) && /ABP24/.test(r.t), JSON.stringify(r));
		ok('free shipping: amount from OpenCart Free Shipping (120) → $120.00', r.v.includes(idOc) && /\$120\.00/.test(r.t), JSON.stringify(r));
		q(`update oc_coupon set status=0 where coupon_id=${cid}`); r = await shot();
		ok('coupon disabled in OpenCart → message hides itself', !r.v.includes(idCp), JSON.stringify(r));
		q(`update oc_coupon set status=1, date_end='2020-12-31' where coupon_id=${cid}`); r = await shot();
		ok('coupon expired → hidden', !r.v.includes(idCp));
		// "Today" in the SHOP's time zone (PHP / OpenCart DB session, from the bar's data-tz), not MariaDB's own clock:
		// the shop runs in UTC, so between 00:00 and 02:00 Riga time the DB server's date is already "tomorrow" for the shop.
		const tzCp = parseInt(((await (await p.request.get(BASE)).text()).match(/data-tz="(-?\d+)"/) || [0, '0'])[1], 10);
		const SHOPDAY = `DATE(DATE_ADD(UTC_TIMESTAMP(), INTERVAL ${tzCp} SECOND))`;
		q(`update oc_coupon set date_end=${SHOPDAY} where coupon_id=${cid}`); r = await shot();
		ok('coupon on its end date → hidden (OpenCart refuses it that day too: date_end > NOW())', !r.v.includes(idCp), JSON.stringify(r) + ' end=' + q(`select date_end from oc_coupon where coupon_id=${cid}`));
		q(`update oc_coupon set date_start=${SHOPDAY}, date_end='2099-12-31' where coupon_id=${cid}`); r = await shot();
		ok('coupon from its start date (today) → shown (date_start < NOW())', r.v.includes(idCp), JSON.stringify(r));
		q(`update oc_coupon set date_start=DATE_ADD(${SHOPDAY}, INTERVAL 1 DAY) where coupon_id=${cid}`); r = await shot();
		ok('coupon starting tomorrow → hidden', !r.v.includes(idCp), JSON.stringify(r));
		q(`update oc_coupon set date_start='2020-01-01' where coupon_id=${cid}`);
		q(`update oc_coupon set date_end='2099-12-31', uses_total=1 where coupon_id=${cid}`); q(`insert into oc_coupon_history (coupon_id,order_id,customer_id,amount,date_added) values (${cid},1,0,-1,NOW())`); r = await shot();
		ok('coupon used up (uses_total reached) → hidden', !r.v.includes(idCp));
		q(`delete from oc_coupon_history where coupon_id=${cid}`); q(`update oc_coupon set uses_total=0 where coupon_id=${cid}`);
		cfg('free_status', '0', 'free'); r = await shot();
		ok('OpenCart Free Shipping switched off → that message hides', !r.v.includes(idOc) && r.v.includes(idCp), JSON.stringify(r));
		cfg('free_status', '1', 'free');
	}

	// ================= 5. cart conditions (AJAX) =================
	[e, id] = await add(async () => { await p.fill('[name="opt[cart_qty_min]"]', '1'); await pickChip('#input-cart-products', 'iPh', /iPhone/); }, 'Thanks for choosing the iPhone - add a case?');
	const idCart = id;
	ok('cart conditions saved (min 1 item, cart contains iPhone)', !e && /"cart_qty_min":"1"/.test(q(`select options from oc_abp_message where message_id=${idCart}`)) && /"cart_products":\[40\]/.test(q(`select options from oc_abp_message where message_id=${idCart}`)), e + q(`select options from oc_abp_message where message_id=${idCart}`));
	[e] = await add(async () => { await p.fill('[name="opt[cart_qty_min]"]', '5'); await p.fill('[name="opt[cart_qty_max]"]', '2'); }, 'Bad');
	ok('cart conditions: max lower than min rejected', /lower|zemāks|меньше/i.test(e), e);
	only([idCart]);
	{
		const { c, pg } = await shop();
		await pg.goto(S('product/product&product_id=' + P40)); await sleep(1200);
		ok('cart conditions: empty cart → hidden', !(await visibleIds(pg)).includes(idCart));
		await pg.click('#button-cart'); await sleep(3000);
		ok('cart conditions: iPhone added (AJAX) → shown without reload', (await visibleIds(pg)).includes(idCart), JSON.stringify(await info(pg)));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-cart-conditions.png`, clip: { x: 0, y: 0, width: 1366, height: 140 } });
		await c.close();
	}

	// ================= 6. UTM / referrer / new vs returning =================
	[e, id] = await add(async () => { await p.fill('[name="opt[utm_source]"]', 'Newsletter'); }, 'Welcome, newsletter reader!');
	const idUtm = id;
	[e, id] = await add(async () => { await p.fill('[name="opt[referrer]"]', 'https://www.facebook.com/somepage'); }, 'Hello from Facebook');
	const idRef = id;
	[e, id] = await add(async () => { await p.selectOption('[name="opt[visitor]"]', 'returning'); }, 'Welcome back!');
	const idRet = id;
	[e, id] = await add(async () => { await p.selectOption('[name="opt[visitor]"]', 'new'); }, 'First time here? Have a look around');
	const idNew = id;
	ok('campaign / visitor rules saved (lower-cased source, referrer reduced to the domain)', optOf(idUtm).utm_source === 'newsletter' && optOf(idRef).referrer === 'facebook.com' && optOf(idRet).visitor === 'returning', [idUtm, optOf(idUtm).utm_source, idRef, optOf(idRef).referrer, idRet, optOf(idRet).visitor].join(' '));
	only([idUtm, idRef, idRet, idNew]);
	{
		let { c, pg } = await shop();
		await pg.goto(BASE); await sleep(1000);
		let v = await visibleIds(pg);
		ok('new visitor, no campaign: only the "new visitor" message', v.includes(idNew) && !v.includes(idUtm) && !v.includes(idRet) && !v.includes(idRef), v.join());
		await pg.goto(BASE + '?utm_source=newsletter&utm_medium=email'); await sleep(1000);
		ok('utm_source=newsletter → campaign message shown', (await visibleIds(pg)).includes(idUtm));
		await pg.goto(S('information/contact')); await sleep(1000);
		ok('campaign remembered for the rest of the visit (next page without UTM)', (await visibleIds(pg)).includes(idUtm));
		await pg.evaluate(() => localStorage.setItem('abp_v', String(Date.now() - 86400000)));
		await pg.goto(BASE); await sleep(1000);
		v = await visibleIds(pg);
		ok('first visit a day ago → "returning" message, not "new"', v.includes(idRet) && !v.includes(idNew), v.join());
		await c.close();
		({ c, pg } = await shop());
		await pg.goto(BASE, { referer: 'https://m.facebook.com/story' }); await sleep(1000);
		ok('referrer m.facebook.com (sub-domain) → referrer message shown', (await visibleIds(pg)).includes(idRef));
		await c.close();
		({ c, pg } = await shop({}, () => { Object.defineProperty(navigator, 'globalPrivacyControl', { get: () => true }); }));
		await pg.goto(BASE + '?utm_source=newsletter'); await sleep(1000);
		v = await visibleIds(pg);
		const stored = await pg.evaluate(() => ({ v: localStorage.getItem('abp_v'), u: sessionStorage.getItem('abp_utm') }));
		ok('GPC: visitor unknown (neither new nor returning shown), nothing stored; URL campaign still counts on this page', !v.includes(idNew) && !v.includes(idRet) && v.includes(idUtm) && stored.v === null && stored.u === null, v.join() + JSON.stringify(stored));
		await pg.goto(S('information/contact')); await sleep(900);
		ok('GPC: campaign not remembered on the next page', !(await visibleIds(pg)).includes(idUtm));
		await c.close();
		({ c, pg } = await shop({}, () => { window.abpConsent = false; }));
		await pg.goto(BASE); await sleep(900);
		ok('window.abpConsent = false: no visitor flag stored', (await pg.evaluate(() => localStorage.getItem('abp_v'))) === null);
		await c.close();
	}

	// ================= 7. triggers =================
	[e, id] = await add(async () => { await p.selectOption('[name="opt[trigger]"]', 'delay'); await p.fill('[name="opt[trigger_value]"]', '2'); }, 'Shown after 2 seconds');
	const idDelay = id;
	[e, id] = await add(async () => { await p.selectOption('[name="opt[trigger]"]', 'scroll'); await p.fill('[name="opt[trigger_value]"]', '40'); await p.click('label[for="input-placement-bottom"]'); }, 'You scrolled down - here is 5% off');
	const idScroll = id;
	[e, id] = await add(async () => { await p.selectOption('[name="opt[trigger]"]', 'exit'); }, 'Leaving already? Take 10% off');
	const idExit = id;
	[e] = await add(async () => { await p.selectOption('[name="opt[trigger]"]', 'delay'); await p.fill('[name="opt[trigger_value]"]', '9999'); }, 'Bad delay');
	ok('trigger: delay > 600 s rejected', /600/.test(e), e);
	only([idDelay, idScroll, idExit]);
	{
		const { c, pg } = await shop();
		await pg.goto(S('product/category&path=20')); await sleep(600);
		ok('delay trigger: not shown at first', !(await visibleIds(pg)).includes(idDelay));
		await sleep(2400);
		ok('delay trigger: shown after 2 s', (await visibleIds(pg)).includes(idDelay));
		ok('scroll trigger: not shown before scrolling', !(await visibleIds(pg)).includes(idScroll));
		await pg.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight)); await sleep(900);
		const bottom = await info(pg, '.abp-pos-bottom');
		ok('scroll trigger (40 %) → shown in the bottom bar', (await visibleIds(pg)).includes(idScroll) && bottom.length === 1 && bottom[0].shown, JSON.stringify(bottom));
		await pg.evaluate(() => window.scrollTo(0, 0)); await sleep(300);
		ok('exit intent: not shown before', !(await visibleIds(pg)).includes(idExit));
		await sleep(1600);
		await pg.mouse.move(600, 300); await pg.mouse.move(600, 2);
		await pg.evaluate(() => document.dispatchEvent(new MouseEvent('mouseout', { bubbles: true, clientX: 600, clientY: -2, relatedTarget: null })));
		await sleep(700);
		ok('exit intent (mouse leaves through the top) → shown', (await visibleIds(pg)).includes(idExit));
		await pg.goto(BASE); await sleep(700);
		const v = await visibleIds(pg);
		ok('fired triggers show at once on the next page of the visit', v.includes(idDelay) && v.includes(idExit), v.join());
		await c.close();
	}

	// ================= 8. inline placements + 10. multiple bars with priority =================
	[e, id] = await add(async () => { await p.click('label[for="input-placement-cart"]'); }, 'Cart box: free gift wrapping on every order');
	const idInCart = id;
	[e, id] = await add(async () => { await p.click('label[for="input-placement-product"]'); await p.fill('#input-icon', 'fa-truck'); }, 'Product box: ships within 24 hours');
	const idInProd = id;
	[e, id] = await add(async () => { await p.click('label[for="input-placement-minicart"]'); }, 'Mini-cart box: you are close to free shipping');
	const idInMini = id;
	[e, id] = await add(async () => { await p.click('label[for="input-placement-bottom"]'); }, 'Bottom bar message');
	const idBottom = id;
	[e, id] = await add(async () => { await p.fill('#input-priority', '90'); }, 'High priority top message');
	const idHigh = id;
	[e, id] = await add(async () => { await p.fill('#input-priority', '5'); }, 'Low priority top message');
	const idLow = id;
	ok('placements + priority saved', q(`select group_concat(placement order by message_id) from oc_abp_message where message_id in (${idInCart},${idInProd},${idInMini},${idBottom})`) === 'cart,product,minicart,bottom' && q(`select priority from oc_abp_message where message_id=${idHigh}`) === '90');
	only([idInCart, idInProd, idInMini, idBottom, idHigh, idLow]);
	await both(S('product/product&product_id=' + P40), 'store-product-inline', async (pg, w) => {
		const bars = await info(pg);
		const top = bars.find(x => x.id === 'abp-bar'), bot = bars.find(x => x.id === 'abp-bar-bottom'), prod = bars.find(x => x.place === 'product');
		if (w === 1366) {
			ok('multiple bars: top + bottom bar at once', top && bot && top.shown && bot.shown && bot.items[0].id === idBottom, JSON.stringify(bars.map(x => x.id + x.place)));
			ok('priority: higher priority message comes first in the top bar', top && top.items[0].id === idHigh, top && top.items.map(i => i.id).join());
			const where = await pg.evaluate(() => { const box = document.querySelector('.abp-inline-product'); if (!box) return 'none'; const h1 = document.querySelector('#content h1, h1'); const prev = box.previousElementSibling; return (h1 && (h1.compareDocumentPosition(box) & 4)) ? (prev && prev.classList.contains('price') ? 'after-h1' : 'after-h1-not-price:' + (prev ? prev.className : '')) : 'before'; });
			ok('inline box under the product price (after the product title)', prod && prod.shown && /ships within 24 hours/.test(prod.items[0].txt) && where === 'after-h1', where + JSON.stringify(prod));
		}
	});
	{
		const { c, pg } = await shop();
		await pg.goto(S('product/product&product_id=' + P40)); await sleep(800);
		await pg.click('#button-cart'); await sleep(2500);
		await pg.click('#cart .heading a'); await sleep(2500);
		const mini = await pg.evaluate(() => { const box = document.querySelector('#cart .content .abp-inline-minicart'); return box ? { txt: box.innerText, shown: getComputedStyle(box).display !== 'none', started: !!box.querySelector('.abp-item.abp-active, .abp-item[data-id]') , boxes: document.querySelectorAll('.abp-inline-minicart').length } : null; });
		ok('mini-cart box in the drop-down content, there after the AJAX reload (module/cart), started by the 1.5 adapter', mini && mini.shown && mini.boxes === 1 && /close to free shipping/.test(mini.txt), JSON.stringify(mini));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-minicart-1366.png`, clip: { x: 600, y: 0, width: 766, height: 420 } });
		await pg.goto(S('checkout/cart')); await sleep(1200);
		const cart = await pg.evaluate(() => { const box = document.querySelector('.abp-inline-cart'); return box ? { txt: box.innerText, inContent: !!box.closest('#content') } : null; });
		ok('cart page box in the content area', cart && cart.inContent && /gift wrapping/.test(cart.txt), JSON.stringify(cart));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-cart-inline-1366.png`, fullPage: false });
		const assets = await pg.evaluate(() => ({ css: document.querySelectorAll('link[href*="announcement_bar_pro.css"]').length, js: document.querySelectorAll('script[src*="announcement_bar_pro.js"]').length }));
		ok('styles + script printed once per page with several bars / boxes', assets.css === 1 && assets.js === 1, JSON.stringify(assets));
		await c.close();
	}
	await settings({ max_messages: 1 });
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(1000);
		const top = (await info(pg)).find(x => x.id === 'abp-bar');
		ok('"Messages per bar" = 1 → only the highest-priority message in the top bar', top.items.filter(i => i.vis).length === 1 && top.items.find(i => i.vis).id === idHigh, JSON.stringify(top.items));
		await c.close();
	}
	await settings({ max_messages: 0, mode: 'rotate', arrows: 1 });

	// ================= pause button + auto-hide (14) =================
	only([idHigh, idLow]);
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(1200);
		const pb = await pg.$('#abp-bar .abp-pause');
		ok('pause button present when messages rotate', !!pb && await pb.isVisible());
		await pb.click(); await sleep(200);
		const act1 = await pg.$eval('#abp-bar .abp-item.abp-active', el => el.dataset.id);
		await pg.mouse.move(10, 600); await sleep(6000);
		const act2 = await pg.$eval('#abp-bar .abp-item.abp-active', el => el.dataset.id);
		ok('pause button: aria-pressed, rotation stops', act1 === act2 && (await pb.getAttribute('aria-pressed')) === 'true', act1 + '→' + act2);
		await c.close();
	}
	await settings({ autohide: 3 });
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(1000);
		await pg.mouse.move(10, 700);
		ok('auto-hide: bar visible at first', (await info(pg)).find(x => x.id === 'abp-bar').shown);
		await sleep(3500);
		const st = await pg.evaluate(() => ({ bar: getComputedStyle(document.getElementById('abp-bar')).display, tab: !!document.querySelector('.abp-tab:not([hidden])'), label: (document.querySelector('.abp-tab') || {}).ariaLabel || document.querySelector('.abp-tab') && document.querySelector('.abp-tab').getAttribute('aria-label') }));
		ok('auto-hide after 3 s → folded, labelled tab shown', st.bar === 'none' && st.tab && !!st.label, JSON.stringify(st));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-autohide-tab.png`, clip: { x: 0, y: 0, width: 1366, height: 120 } });
		await pg.goto(S('information/contact')); await sleep(900);
		ok('auto-hide: stays folded on the next page (tab visible)', await pg.evaluate(() => getComputedStyle(document.getElementById('abp-bar')).display === 'none' && !!document.querySelector('.abp-tab:not([hidden])')));
		await pg.click('.abp-tab'); await sleep(500);
		ok('tab opens the bar again', await pg.evaluate(() => getComputedStyle(document.getElementById('abp-bar')).display !== 'none'));
		await c.close();
	}
	await settings({ autohide: 0, arrows: 0, mode: 'stack' });

	// ================= 11. background image, font, custom CSS =================
	const img = q("select image from oc_product where product_id=40");
	[e, id] = await add(async () => {
		await p.evaluate(v => { const i = document.getElementById('input-image'); i.value = v; i.dispatchEvent(new Event('change', { bubbles: true })); }, img); // what the image manager writes
		await p.fill('#input-overlay', '70').catch(() => {});
		await p.evaluate(() => { const r = document.getElementById('input-overlay'); r.value = '70'; r.dispatchEvent(new Event('input', { bubbles: true })); });
		await p.selectOption('[name="opt[font]"]', 'serif');
		await sleep(1500);
		const pvi = await pvFrame().evaluate(() => { const it = document.querySelector('.abp-item'); return { img: it.style.backgroundImage, font: getComputedStyle(it).fontFamily }; });
		ok('preview: background image with overlay + serif font', /url\(/.test(pvi.img) && /gradient/.test(pvi.img) && /Georgia|Charter|serif/i.test(pvi.font), JSON.stringify(pvi));
		await p.screenshot({ path: `${SHOTS}/${TAG}-admin-form-image-font.png` });
	}, 'Image background with serif font');
	const idImg = id;
	ok('image + font saved', !e && optOf(idImg).image === img.replace(/^data\//, 'catalog/') && optOf(idImg).overlay === 70 && optOf(idImg).font === 'serif', e + q(`select options from oc_abp_message where message_id=${idImg}`));
	const evilCss = '#abp-bar{letter-spacing:1px}</style><script>alert(1)</script> @import url(//evil.example/x.css); .abp{background:url(javascript:alert(1))} .abp{width:expression(alert(1))}';
	ok('custom CSS saved', (await settings({ custom_css: evilCss, font: 'mono' })) === '');
	const storedCss = setting('custom_css');
	ok('custom CSS sanitised (no <, @import, javascript:, expression)', !/[<]|@import|javascript:|expression/i.test(storedCss) && /letter-spacing:1px/.test(storedCss), storedCss);
	only([idImg, idHigh]);
	await both(BASE, 'store-image-font-css', async (pg, w) => {
		if (w !== 1366) return;
		const r = await pg.evaluate(id => { const it = document.querySelector(`.abp-item[data-id="${id}"]`), bar = document.getElementById('abp-bar'); const st = document.getElementById('abp-custom-css'); return { img: it ? it.style.backgroundImage : '', font: getComputedStyle(bar).fontFamily, msgFont: it ? getComputedStyle(it).fontFamily : '', css: st ? st.textContent : '', ls: getComputedStyle(bar).letterSpacing, scripts: document.querySelectorAll('script:not([src])').length }; }, idImg);
		ok('storefront: image + overlay, message font serif, module font mono, custom CSS applied safely', /url\(/.test(r.img) && /serif|Georgia/i.test(r.msgFont) && /monospace|Consolas/i.test(r.font) && r.ls === '1px' && !/script/i.test(r.css), JSON.stringify(r));
	});
	await settings({ custom_css: '', font: '' });

	// ================= 12. recurring countdown =================
	// Store time (PHP time zone of the shop, from the bar's data-tz) + 2 hours.
	const tzs = parseInt((await (await p.request.get(BASE)).text()).match(/data-tz="(-?\d+)"/)[1], 10);
	const at = new Date(Date.now() + tzs * 1000 + 2 * 3600000), pad2 = n => String(n).padStart(2, '0');
	const nowStore = pad2(at.getUTCHours()) + ':' + pad2(at.getUTCMinutes());
	[e, id] = await add(async () => { await check('countdown'); await p.selectOption('[name="opt[recur]"]', 'daily'); await p.fill('[name="opt[recur_time]"]', nowStore); }, 'Order within {countdown} for same-day dispatch');
	const idRecur = id;
	ok('recurring countdown saved without an end date', !e && /"recur":"daily","recur_time":"\d\d:\d\d"/.test(q(`select options from oc_abp_message where message_id=${idRecur}`)), e);
	[e] = await add(async () => { await check('countdown'); await p.selectOption('[name="opt[recur]"]', 'daily'); await p.fill('[name="opt[recur_time]"]', '14:00'); for (const d of [1, 2, 3, 4, 5, 6, 7]) await p.setChecked(`[name="opt[recur_skip][]"][value="${d}"]`, true, { force: true }); }, 'All days skipped');
	ok('recurring countdown: skipping all 7 days rejected', e !== '', e);
	only([idRecur]);
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(1600);
		const t = await pg.$eval(`.abp-item[data-id="${idRecur}"] .abp-countdown`, el => ({ t: el.textContent, r: el.dataset.recur }));
		ok('recurring countdown: ~2 h to the daily cut-off (store time)', /^(01:5\d|02:00):\d\d$/.test(t.t) && /^d\|/.test(t.r), JSON.stringify(t));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-recurring.png`, clip: { x: 0, y: 0, width: 1366, height: 100 } });
		// roll over: put the end in the past, the script must start the next cut-off (not remove the message)
		await pg.$eval(`.abp-item[data-id="${idRecur}"] .abp-countdown`, el => el.setAttribute('data-end', String(Date.now() - 1000)));
		await sleep(1500);
		const t2 = await pg.$eval(`.abp-item[data-id="${idRecur}"] .abp-countdown`, el => el.textContent).catch(() => 'gone');
		ok('recurring countdown restarts for the next cut-off instead of hiding', /^(1d |\d{2}:)/.test(t2) && t2 !== 'gone', t2);
		await c.close();
	}

	// ================= 9. e-mail capture (double opt-in) =================
	const privacyId = q('select information_id from oc_information order by information_id limit 1');
	ok('settings: privacy page + double opt-in saved', (await settings({ privacy_id: privacyId, double_optin: 1 })) === '' && setting('privacy_id') === privacyId);
	[e, id] = await add(async () => { await check('opt[email]'); await p.fill('[name="message_description[1][email_button]"]', 'Join'); }, 'Get our news first');
	const idMail = id;
	ok('e-mail capture saved (custom button text)', !e && /"email":1/.test(q(`select options from oc_abp_message where message_id=${idMail}`)) && /Join/.test(q(`select extra from oc_abp_message_description where message_id=${idMail}`)), e);
	only([idMail]);
	q("delete from oc_abp_subscriber"); q('delete from oc_abp_rate');
	const custEmail = 'abp.customer@example.com';
	q(`delete from oc_customer where email='${custEmail}'`);
	q(`insert into oc_customer (store_id,firstname,lastname,email,telephone,fax,password,salt,cart,wishlist,newsletter,address_id,customer_group_id,ip,status,approved,token,date_added) values (0,'A','B','${custEmail}','1','','x','','','',0,0,1,'',1,1,'',NOW())`);
	const mails0 = fs.existsSync(MAILDIR) ? fs.readdirSync(MAILDIR).filter(f => f.endsWith('.eml')).length : 0;
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(1200);
		const form = await pg.evaluate(() => { const f = document.querySelector('.abp-form'); if (!f) return null; const cb = f.querySelector('input[name="consent"]'); return { consent: !!cb, checked: cb.checked, privacy: !!f.querySelector('.abp-consent a[href*="information"]'), hp: !!f.querySelector('input[name="website"]'), btn: f.querySelector('.abp-submit').textContent, label: f.querySelector('.abp-email').getAttribute('aria-label') }; });
		ok('form: e-mail field (labelled), unticked consent box, privacy link, honeypot', form && form.consent && !form.checked && form.privacy && form.hp && form.btn === 'Join' && !!form.label, JSON.stringify(form));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-email-form-1366.png`, clip: { x: 0, y: 0, width: 1366, height: 130 } });
		await pg.fill('.abp-email', 'not-an-email'); await pg.click('.abp-submit'); await sleep(400);
		ok('invalid address → message, field marked invalid, nothing sent', /valid/i.test(await pg.$eval('.abp-form-msg', el => el.textContent)) && (await pg.$eval('.abp-email', el => el.getAttribute('aria-invalid'))) === 'true');
		await pg.fill('.abp-email', custEmail); await pg.click('.abp-submit'); await sleep(400);
		ok('consent not ticked → message, nothing sent', /tick|agree/i.test(await pg.$eval('.abp-form-msg', el => el.textContent)) && q('select count(*) from oc_abp_subscriber') === '0');
		await sleep(1500); // the form token must be at least 2 s old
		await pg.check('.abp-consent input'); await pg.click('.abp-submit'); await sleep(2500);
		const done = await pg.$eval('.abp-form-done', el => el.textContent).catch(() => '');
		ok('valid sign-up → thank-you text, pending row (no address in statistics)', /confirm/i.test(done) && q(`select concat(status,'|',length(token)) from oc_abp_subscriber where email='${custEmail}'`) === '0|64' && q(`select subscribes from oc_abp_message where message_id=${idMail}`) === '0', done + ' ' + q('select concat(email,status) from oc_abp_subscriber'));
		await c.close();
	}
	await sleep(1000);
	const mails = fs.existsSync(MAILDIR) ? fs.readdirSync(MAILDIR).filter(f => f.endsWith('.eml')).sort() : [];
	const mail = mails.length > mails0 ? mailText(fs.readFileSync(path.join(MAILDIR, mails[mails.length - 1]), 'utf8')) : '';
	const link = (mail.match(/https?:\/\/[^\s"<>]+confirm[^\s"<>]*t=([0-9a-f]{64})/) || [])[0] || '';
	ok('double opt-in e-mail sent through the shop\'s SMTP settings, with a confirm link', mails.length > mails0 && !!link && /To: .*abp\.customer@example\.com/i.test(mail), 'mails ' + mails0 + '→' + mails.length + ' link=' + link);
	{
		const { c, pg } = await shop();
		await pg.goto(link.replace(/&amp;/g, '&')); await sleep(800);
		ok('confirm link (GET) only shows a button — nothing confirmed yet (mail scanners)', (await pg.$('#abp-page form button')) !== null && q(`select status from oc_abp_subscriber where email='${custEmail}'`) === '0');
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-confirm-page.png` });
		await pg.click('#abp-page form button'); await sleep(1200);
		ok('POST confirm → confirmed, customer account newsletter on, counter +1', q(`select status from oc_abp_subscriber where email='${custEmail}'`) === '1' && q(`select newsletter from oc_customer where email='${custEmail}'`) === '1' && q(`select subscribes from oc_abp_message where message_id=${idMail}`) === '1', q(`select concat(status) from oc_abp_subscriber`) + ' nl=' + q(`select newsletter from oc_customer where email='${custEmail}'`));
		await pg.goto(link.replace(/&amp;/g, '&')); await sleep(600);
		ok('confirm link cannot be used twice', (await pg.$('#abp-page form button')) === null);
		await c.close();
	}
	// endpoint security: cross-site, bad token, honeypot, rate limit
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(2500);
		const sub = S(ROUTE + SEP + 'subscribe');
		const tokv = await pg.$eval('.abp-form', f => f.dataset.tok);
		const post = (fields, headers) => pg.evaluate(async ([u, f, h]) => { const fd = new FormData(); Object.entries(f).forEach(([k, v]) => fd.append(k, v)); const r = await fetch(u, { method: 'POST', body: fd, headers: h || {}, credentials: 'same-origin' }); return r.text(); }, [sub, fields, headers]);
		const base = { email: 'guest1@example.com', consent: '1', tok: tokv, id: idMail, website: '' };
		const bad = await post(Object.assign({}, base, { tok: '123.abc' }));
		ok('sign-up: forged token rejected', /error/.test(bad) && q("select count(*) from oc_abp_subscriber where email='guest1@example.com'") === '0', bad);
		const hp = await post(Object.assign({}, base, { website: 'http://spam' }));
		ok('sign-up: honeypot filled → "ok" but nothing stored', /"ok"/.test(hp) && q("select count(*) from oc_abp_subscriber where email='guest1@example.com'") === '0', hp);
		const cross = await (await fetch(sub, { method: 'POST', headers: { Origin: 'http://evil.example', 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'email=x%40example.com&consent=1&id=' + idMail + '&tok=' + tokv })).text();
		ok('sign-up: cross-site POST (foreign Origin) rejected', /error/.test(cross) && q("select count(*) from oc_abp_subscriber where email='x@example.com'") === '0', cross);
		const g = await post(Object.assign({}, base, { email: 'guest2@example.com' }));
		ok('sign-up: guest stored as pending (module list)', /"ok"/.test(g) && q("select status from oc_abp_subscriber where email='guest2@example.com'") === '0', g);
		let last = '';
		for (let i = 0; i < 12; i++) { last = await post(Object.assign({}, base, { email: `rate${i}@example.com` })); }
		ok('sign-up: rate limit per visitor (10 per hour)', /many/i.test(last), last);
		q('delete from oc_abp_rate');
		// Header / SMTP injection through a quoted local part (FILTER_VALIDATE_EMAIL accepts it): refused, nothing stored or sent.
		const mBefore = fs.readdirSync(MAILDIR).filter(f => f.endsWith('.eml')).length;
		const inj = await post(Object.assign({}, base, { email: '"a\\\nX-Injected:1"@a.co' }));
		const inj2 = await post(Object.assign({}, base, { email: 'victim@example.com\r\nBcc: x@evil.example' }));
		await sleep(800);
		ok('sign-up: injection addresses ("a\\<LF>X-Injected:1"@a.co, CR/LF + Bcc) refused, nothing stored, no mail', /"field":"email"/.test(inj) && /"field":"email"/.test(inj2) && q("select count(*) from oc_abp_subscriber where email like '%Injected%' or email like '%evil%'") === '0' && fs.readdirSync(MAILDIR).filter(f => f.endsWith('.eml')).length === mBefore, inj + ' ' + inj2);
		await c.close();
		q('delete from oc_abp_rate');
	}
	// admin: export CSV + erase (POST only)
	await p.goto(A(ROUTE, '&tab=settings'));
	const counts = await p.$eval('.abp-subs', el => el.innerText).catch(() => '');
	ok('settings: sign-up counts shown', /1/.test(counts), counts);
	const csv = await (await p.request.get(A(ROUTE + SEP + 'exportSubscribers'))).text();
	ok('export CSV: header + confirmed customer address, no token hashes', /^\uFEFF?"email","status"/.test(csv) && csv.includes(custEmail) && csv.includes('confirmed') && !/[0-9a-f]{64}/.test(csv), csv.slice(0, 200));
	const getErase = await (await p.request.get(A(ROUTE + SEP + 'eraseSubscriber', '&email=' + encodeURIComponent(custEmail)))).text();
	ok('erase: GET refused (POST only)', /error/.test(getErase) && q(`select count(*) from oc_abp_subscriber where email='${custEmail}'`) === '1', getErase);
	await p.fill('#input-erase-email', custEmail);
	await Promise.all([p.waitForResponse(r => /eraseSubscriber/.test(r.url())), p.click('[data-abp-erase]')]); await sleep(500);
	ok('erase through the settings tab removes the address', q(`select count(*) from oc_abp_subscriber where email='${custEmail}'`) === '0' && /erased/i.test(await p.$eval('#abp-alerts', el => el.innerText).catch(() => '')));
	// unsubscribe link of the e-mail
	{
		const leave = (mail.match(/https?:\/\/[^\s"<>]+unsubscribe[^\s"<>]*t=[0-9a-f]{64}/) || [])[0] || '';
		ok('confirmation e-mail has an unsubscribe link', !!leave, leave);
	}
	// configurable limits (settings) and at most 3 confirmation e-mails per address in 24 h
	ok('settings: sign-up limit 0 per visitor refused (range 1–1000)', /1.*1000/.test(await settings({ signup_limit_ip: 0 })));
	ok('settings: sign-up limits saved (3 per visitor, 500 per shop)', (await settings({ signup_limit_ip: 3, signup_limit_total: 500 })) === '' && setting('signup_limit_ip') === '3' && setting('signup_limit_total') === '500');
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(2500);
		const sub = S(ROUTE + SEP + 'subscribe');
		const tokv = await pg.$eval('.abp-form', f => f.dataset.tok);
		const post = (fields) => pg.evaluate(async ([u, f]) => { const fd = new FormData(); Object.entries(f).forEach(([k, v]) => fd.append(k, v)); const r = await fetch(u, { method: 'POST', body: fd, credentials: 'same-origin' }); return r.text(); }, [sub, fields]);
		const base = { consent: '1', tok: tokv, id: idMail, website: '' };
		const res = [];
		for (let i = 0; i < 4; i++) { res.push(await post(Object.assign({}, base, { email: `lim${i}@example.com` }))); }
		ok('sign-up: per-visitor limit from the settings (3 per hour) → 4th refused', /"ok"/.test(res[2]) && /many/i.test(res[3]), res.join(' '));
		q('delete from oc_abp_rate');
		const count = addr => fs.readdirSync(MAILDIR).filter(f => f.endsWith('.eml')).filter(f => fs.readFileSync(path.join(MAILDIR, f), 'utf8').includes(addr)).length;
		const addr = 'peraddress@example.com', r4 = [];
		const c0 = count(addr); // the mail folder keeps earlier runs
		for (let i = 0; i < 4; i++) {
			r4.push(await post(Object.assign({}, base, { email: addr })));
			// past the "one mail per 10 minutes" rule; the per-IP limit is cleared so only the per-address limit counts
			q(`update oc_abp_subscriber set date_added = DATE_SUB(NOW(), INTERVAL 1 DAY) where email='${addr}'`);
			q('delete from oc_abp_rate where expires < UNIX_TIMESTAMP() + 7200'); // hourly buckets only; the 24 h one stays
		}
		await sleep(1200);
		ok('sign-up: at most 3 confirmation e-mails per address in 24 h; the 4th answer looks the same', count(addr) - c0 === 3 && r4.every(x => x === r4[0]) && /"ok":1/.test(r4[3]), (count(addr) - c0) + ' mails; ' + r4.join(' '));
		await c.close();
		q('delete from oc_abp_rate');
	}
	await settings({ signup_limit_ip: 10, signup_limit_total: 200 });
	// double opt-in OFF: customer accounts never changed, unsubscribed addresses never re-subscribed, same answer
	ok('settings: double opt-in switched off', (await settings({ double_optin: 0 })) === '' && setting('double_optin') === '0');
	{
		q(`update oc_customer set newsletter=0 where email='${custEmail}'`);
		q(`delete from oc_abp_subscriber where email in ('${custEmail}','gone@example.com','fresh@example.com')`);
		q("insert into oc_abp_subscriber (email,store_id,language_id,message_id,status,token,unsubscribe,consent,date_added) values ('gone@example.com',0,1,0,2,'','" + 'a'.repeat(64) + "','x',DATE_SUB(NOW(), INTERVAL 2 DAY))");
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(2500);
		const sub = S(ROUTE + SEP + 'subscribe');
		const tokv = await pg.$eval('.abp-form', f => f.dataset.tok);
		const post = (fields) => pg.evaluate(async ([u, f]) => { const fd = new FormData(); Object.entries(f).forEach(([k, v]) => fd.append(k, v)); const r = await fetch(u, { method: 'POST', body: fd, credentials: 'same-origin' }); return r.text(); }, [sub, fields]);
		const base = { consent: '1', tok: tokv, id: idMail, website: '' };
		const fresh = await post(Object.assign({}, base, { email: 'fresh@example.com' }));
		const gone = await post(Object.assign({}, base, { email: 'gone@example.com' }));
		const cust = await post(Object.assign({}, base, { email: custEmail }));
		ok('opt-in off: new address subscribed at once (module list)', /"ok":1/.test(fresh) && q("select status from oc_abp_subscriber where email='fresh@example.com'") === '1', fresh);
		ok('opt-in off: unsubscribed address stays unsubscribed, same answer as a new address', gone === fresh && q("select status from oc_abp_subscriber where email='gone@example.com'") === '2', gone + ' / ' + fresh);
		ok('opt-in off: customer account newsletter flag NOT changed (module list only), same answer', cust === fresh && q(`select newsletter from oc_customer where email='${custEmail}'`) === '0' && q(`select status from oc_abp_subscriber where email='${custEmail}'`) === '1', cust + ' nl=' + q(`select newsletter from oc_customer where email='${custEmail}'`));
		await c.close();
		q(`delete from oc_abp_subscriber where email in ('${custEmail}','gone@example.com','fresh@example.com')`); q('delete from oc_abp_rate');
	}
	await settings({ double_optin: 1 });
	await both(BASE, 'store-email-form', null);

	// ================= 2.4.1: re-subscribe rule (a third party cannot overwrite an unsubscribe record) =================
	const mailFiles = () => fs.existsSync(MAILDIR) ? fs.readdirSync(MAILDIR).filter(f => f.endsWith('.eml')).sort() : [];
	const lastMailTo = (addr, seen) => { const l = mailFiles().filter(f => !seen.includes(f) && fs.readFileSync(path.join(MAILDIR, f), 'utf8').includes(addr)); return l.length ? mailText(fs.readFileSync(path.join(MAILDIR, l[l.length - 1]), 'utf8')) : ''; };
	const linkIn = (m, kind) => ((m.match(new RegExp('https?://[^\\s"<>]+' + kind + '[^\\s"<>]*t=[0-9a-f]{64}')) || [])[0] || '').replace(/&amp;/g, '&');
	const tokOf = url => (url.match(/t=([0-9a-f]{64})/) || [])[1] || '';
	// Opens a confirm / unsubscribe link like the visitor: GET, then the button (POST) when there is one.
	const pressLink = async (url, press = true) => {
		const { c, pg } = await shop(); await pg.goto(url); await sleep(700);
		const has = (await pg.$('#abp-page form button')) !== null;
		if (has && press) { await pg.click('#abp-page form button'); await sleep(1200); }
		const txt = (await pg.innerText('#abp-page').catch(() => '')).replace(/\s+/g, ' ');
		await c.close();
		return { has, txt };
	};
	// Sign-up from a fresh visitor (own browser context = "someone else") through the real endpoint.
	const signUpAs = async (email, extra = {}) => {
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(2500);
		const tokv = await pg.$eval('.abp-form', f => f.dataset.tok);
		const r = await pg.evaluate(async ([u, f]) => { const fd = new FormData(); Object.entries(f).forEach(([k, v]) => fd.append(k, v)); const x = await fetch(u, { method: 'POST', body: fd, credentials: 'same-origin' }); return x.text(); }, [S(ROUTE + SEP + 'subscribe'), Object.assign({ email, consent: '1', tok: tokv, id: idMail, website: '' }, extra)]);
		await c.close();
		return r;
	};
	{
		const addr = 'resub@example.com';
		q(`delete from oc_abp_subscriber where email='${addr}'`); q('delete from oc_abp_rate');
		const rowOf = () => q(`select concat_ws('|', status, unsubscribe, consent, date_added, language_id, message_id, ifnull(date_confirmed, '-')) from oc_abp_subscriber where email='${addr}'`);
		let seen = mailFiles();
		const r1 = await signUpAs(addr); await sleep(1200);
		const m1 = lastMailTo(addr, seen), confirm1 = linkIn(m1, 'confirm'), leave1 = linkIn(m1, 'unsubscribe'), t1 = tokOf(confirm1);
		ok('2.4.1: confirmation token = creation time (first 8 hex digits ≈ now) + random part', /"ok":1/.test(r1) && !!t1 && Math.abs(parseInt(t1.slice(0, 8), 16) - Date.now() / 1000) < 600, r1 + ' ' + t1);
		await pressLink(confirm1); await pressLink(leave1);
		const before = rowOf();
		ok('2.4.1 re-subscribe: own sign-up confirmed, then unsubscribed through the e-mail link (status 2)', /^2\|[0-9a-f]{64}\|/.test(before), before);
		const subs0 = q(`select subscribes from oc_abp_message where message_id=${idMail}`);
		seen = mailFiles();
		const r2 = await signUpAs(addr); await sleep(1200);
		const after = rowOf(), tokHash = q(`select token from oc_abp_subscriber where email='${addr}'`);
		ok('2.4.1 re-subscribe: a third party signs the unsubscribed address up again → same answer; status 2, unsubscribe hash, consent, date_added, language, message, date_confirmed unchanged; only a new token hash', r2 === r1 && after === before && /^[0-9a-f]{64}$/.test(tokHash), r1 + ' / ' + r2 + ' | ' + before + ' → ' + after);
		const m2 = lastMailTo(addr, seen), confirm2 = linkIn(m2, 'confirm'), leave2 = linkIn(m2, 'unsubscribe'), t2 = tokOf(confirm2);
		ok('2.4.1 re-subscribe: the owner gets a confirmation e-mail with a new token and a new (derived) unsubscribe link', !!t2 && t2 !== t1 && sha256(t2) === tokHash && !!leave2 && leave2 !== leave1, confirm2 + ' ' + leave2);
		const old = await pressLink(leave1);
		const leaveEarly = await pressLink(leave2, false);
		ok('2.4.1 re-subscribe: before confirming, the old unsubscribe link says "unsubscribed", the new one offers nothing; row unchanged', !old.has && /unsubscribed/i.test(old.txt) && !leaveEarly.has && rowOf() === before, old.txt + ' / ' + leaveEarly.txt);
		// A status-2 token older than 7 days, or from the future, is refused (the time is inside the hashed token).
		const now = Math.floor(Date.now() / 1000), mk = t => t.toString(16).padStart(8, '0') + crypto.randomBytes(28).toString('hex');
		const res = [];
		for (const t of [mk(now - 8 * 86400), mk(now + 3600)]) {
			q(`update oc_abp_subscriber set token='${sha256(t)}' where email='${addr}'`);
			res.push(await pressLink(confirm2.replace(/t=[0-9a-f]{64}/, 't=' + t)));
		}
		q(`update oc_abp_subscriber set token='${tokHash}' where email='${addr}'`);
		ok('2.4.1 re-subscribe: confirmation token older than 7 days or from the future refused, row unchanged', res.every(x => !x.has && /invalid|expired/i.test(x.txt)) && rowOf() === before, JSON.stringify(res));
		const get2 = await pressLink(confirm2, false);
		ok('2.4.1 re-subscribe: confirm link (GET) only shows the button — still status 2', get2.has && rowOf() === before);
		await pressLink(confirm2);
		const derived = crypto.createHmac('sha256', setting('secret')).update('unsub|' + t2).digest('hex');
		const now1 = q(`select concat_ws('|', status, token, unsubscribe, consent, date_added, ifnull(date_confirmed, '-')) from oc_abp_subscriber where email='${addr}'`).split('|'), b = before.split('|');
		ok('2.4.1 re-subscribe: owner confirms → status 1, token used up, unsubscribe = sha256(HMAC("unsub|" + token)), consent + date_added kept, counter +1', now1[0] === '1' && now1[1] === '' && now1[2] === sha256(derived) && now1[2] !== b[1] && now1[3] === b[2] && now1[4] === b[3] && now1[5] !== '-' && Number(q(`select subscribes from oc_abp_message where message_id=${idMail}`)) === Number(subs0) + 1, now1.join('|'));
		const again = await pressLink(confirm2, false);
		await pressLink(leave2);
		ok('2.4.1 re-subscribe: the confirm link works once; the new unsubscribe link of that e-mail unsubscribes (status 2)', !again.has && q(`select status from oc_abp_subscriber where email='${addr}'`) === '2', again.txt);
		q(`delete from oc_abp_subscriber where email='${addr}'`); q('delete from oc_abp_rate');
	}

	// ================= 2.4.1: Cloudflare Turnstile (Cloudflare's official dummy keys, real siteverify) =================
	const TS_SITE = '1x00000000000000000000AA', TS_PASS = '1x0000000000000000000000000000000AA', TS_FAIL = '2x0000000000000000000000000000000AA';
	const cfLog = pg => { const l = []; pg.on('request', r => { if (/challenges\.cloudflare\.com/.test(r.url())) l.push(r.url()); }); return l; };
	const tsToken = pg => pg.waitForFunction(() => { const i = document.querySelector('.abp-form [name="cf-turnstile-response"]'); return i && i.value ? i.value : null; }, null, { timeout: 30000 }).then(h => h.jsonValue()).catch(() => '');
	const tsState = pg => pg.evaluate(() => { const f = document.querySelector('.abp-form'); return { form: !!f, ts: f ? f.getAttribute('data-ts') : null, wait: f ? f.getAttribute('data-ts-wait') : null, err: f ? f.getAttribute('data-err-ts') : null, box: !!document.querySelector('.abp-form .abp-ts + .abp-form-msg'), script: !!document.querySelector('script[src*="challenges.cloudflare.com"]') }; });
	q('delete from oc_abp_rate');
	{
		const { c, pg } = await shop(); const cf = cfLog(pg); await pg.goto(BASE); await sleep(2000);
		const st = await tsState(pg);
		ok('Turnstile off (default): no data-ts, no widget box, no Cloudflare script or request', st.form && st.ts === null && !st.box && !st.script && cf.length === 0, JSON.stringify(st) + ' cf=' + cf.length);
		await c.close();
	}
	ok('Turnstile settings: wrong site key format refused, nothing saved', /format/i.test(await settings({ ts_site: 'bad key!', ts_secret: TS_PASS })) && !setting('ts_secret'));
	ok('Turnstile settings: site key without a secret refused', /secret/i.test(await settings({ ts_site: TS_SITE })) && !setting('ts_site'));
	ok('Turnstile settings: both keys saved (Cloudflare\'s dummy keys pass the format check)', (await settings({ ts_site: TS_SITE, ts_secret: TS_PASS })) === '' && setting('ts_site') === TS_SITE && setting('ts_secret') === TS_PASS);
	ok('Turnstile settings: saving again with the secret field empty keeps the saved secret', (await settings({ ts_site: TS_SITE })) === '' && setting('ts_secret') === TS_PASS);
	{
		await p.goto(A(ROUTE, '&tab=settings')); await sleep(300);
		const adminHtml = await p.content();
		const field = await p.evaluate(() => { const i = document.querySelector('[name="announcement_bar_pro_ts_secret"]'); return { type: i.type, value: i.value, remove: !!document.querySelector('[name="announcement_bar_pro_ts_secret_remove"]') }; });
		const pages = [adminHtml, await (await p.request.get(A(ROUTE, '&tab=settings'))).text(), await (await p.request.get(A(ROUTE))).text(), await (await p.request.get(FORM(idMail))).text(), await (await p.request.get(A(ROUTE + SEP + 'export'))).text(), await (await p.request.get(A(ROUTE + SEP + 'exportSubscribers'))).text()];
		const front = await (await p.request.get(BASE)).text();
		const js = await (await p.request.get(new URL(((front.match(/src="([^"]*announcement_bar_pro\.js[^"]*)"/) || [])[1] || '').replace(/&amp;/g, '&'), BASE).href)).text();
		ok('Turnstile secret never printed: admin settings / list / message form / export / CSV, storefront HTML and JS; password field empty with "saved" hint + remove box; site key in the form', !pages.concat(front, js).some(h => h.includes(TS_PASS)) && field.type === 'password' && field.value === '' && field.remove && /saved/i.test(adminHtml) && front.includes('data-ts="' + TS_SITE + '"') && /data-ts-wait="[^"]+"/.test(front) && /data-err-ts="[^"]+"/.test(front), JSON.stringify(field));
		// Screenshot of the two Turnstile rows (best effort: never fails the run).
		await p.locator('#abp-newsletter').screenshot({ path: `${SHOTS}/${TAG}-admin-turnstile.png`, timeout: 5000 }).catch(() => {});
	}
	q('delete from oc_abp_rate'); q("delete from oc_abp_subscriber where email like 'ts-%@example.com'");
	{
		const { c, pg } = await shop(); const cf = cfLog(pg); await pg.goto(BASE);
		const token = await tsToken(pg); const st = await tsState(pg);
		ok('Turnstile on: form carries data-ts + texts and the widget box; Cloudflare script loaded (render=explicit); the widget gave a token', st.ts === TS_SITE && !!st.wait && !!st.err && st.box && cf.some(u => /turnstile\/v0\/api\.js\?render=explicit/.test(u)) && !!token, JSON.stringify(st) + ' token=' + token + ' cf=' + cf.length);
		const tokv = await pg.$eval('.abp-form', f => f.dataset.tok);
		const post = fields => pg.evaluate(async ([u, f]) => { const fd = new FormData(); Object.entries(f).forEach(([k, v]) => fd.append(k, v)); const r = await fetch(u, { method: 'POST', body: fd, credentials: 'same-origin' }); return r.text(); }, [S(ROUTE + SEP + 'subscribe'), fields]);
		const none = await post({ email: 'ts-none@example.com', consent: '1', tok: tokv, id: idMail, website: '' });
		// (Cloudflare's always-pass test secret accepts any token, so a forged token is checked with the failing secret below.)
		ok('Turnstile on: POST without a token → "wait a moment" (field form), nothing stored', /wait a moment/i.test(none) && /"field":"form"/.test(none) && q("select count(*) from oc_abp_subscriber where email='ts-none@example.com'") === '0', none);
		await pg.fill('.abp-email', 'ts-pass@example.com'); await pg.check('.abp-consent input'); await pg.click('.abp-submit'); await sleep(4000);
		const done = await pg.$eval('.abp-form-done', el => el.textContent).catch(() => '');
		ok('Turnstile pass (sitekey 1x…AA + secret 1x…AA, real siteverify): sign-up accepted, pending row', /confirm/i.test(done) && q("select status from oc_abp_subscriber where email='ts-pass@example.com'") === '0', done + ' ' + (await pg.$eval('.abp-form-msg', el => el.textContent).catch(() => '')));
		await c.close();
	}
	{
		const { c, pg } = await shop({ viewport: { width: 390, height: 844 } }); await pg.goto(BASE); await tsToken(pg); await sleep(500);
		await audit(pg, 'Turnstile form @390');
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-turnstile-390.png`, fullPage: false });
		await c.close();
	}
	ok('Turnstile settings: failing dummy secret 2x…AA saved', (await settings({ ts_site: TS_SITE, ts_secret: TS_FAIL })) === '' && setting('ts_secret') === TS_FAIL);
	q('delete from oc_abp_rate');
	{
		const { c, pg } = await shop(); await pg.goto(BASE);
		const token = await tsToken(pg); await sleep(500);
		await pg.fill('.abp-email', 'ts-fail@example.com'); await pg.check('.abp-consent input'); await pg.click('.abp-submit'); await sleep(4000);
		const msg = await pg.$eval('.abp-form-msg', el => el.textContent).catch(() => '');
		ok('Turnstile fail (secret 2x…AA): sign-up refused with a clear message, form stays, no subscriber row', !!token && /anti-bot check failed/i.test(msg) && !(await pg.$('.abp-form-done')) && q("select count(*) from oc_abp_subscriber where email='ts-fail@example.com'") === '0', msg);
		q('delete from oc_abp_rate');
		const tokv = await pg.$eval('.abp-form', f => f.dataset.tok);
		const forged = await pg.evaluate(async ([u, f]) => { const fd = new FormData(); Object.entries(f).forEach(([k, v]) => fd.append(k, v)); const r = await fetch(u, { method: 'POST', body: fd, credentials: 'same-origin' }); return r.text(); }, [S(ROUTE + SEP + 'subscribe'), { email: 'ts-forged@example.com', consent: '1', tok: tokv, id: idMail, website: '', 'cf-turnstile-response': 'forged-token-123' }]);
		ok('Turnstile fail: a forged token posted directly → "check failed" (field form), nothing stored', /anti-bot check failed/i.test(forged) && /"field":"form"/.test(forged) && q("select count(*) from oc_abp_subscriber where email='ts-forged@example.com'") === '0', forged);
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-turnstile-failed-1366.png`, clip: { x: 0, y: 0, width: 1366, height: 160 } });
		await c.close();
	}
	ok('Turnstile + "Wait for cookie consent" saved', (await settings({ ts_site: TS_SITE, ts_secret: TS_PASS, consent_wait: 1 })) === '' && setting('consent_wait') === '1');
	{
		const { c, pg } = await shop(); const cf = cfLog(pg); await pg.goto(BASE); await sleep(3000);
		const box = await pg.$eval('.abp-ts', el => el.textContent).catch(() => ''), st = await tsState(pg);
		ok('Turnstile + consent wait: no Cloudflare script or request before window.abpConsent = true; the box shows the consent text', cf.length === 0 && !st.script && !!st.wait && box === st.wait, box + ' cf=' + cf.length);
		await pg.evaluate(() => { window.abpConsent = true; document.dispatchEvent(new Event('abp:consent')); });
		const token = await tsToken(pg);
		ok('Turnstile + consent wait: after consent the widget loads and gives a token', cf.length > 0 && !!token, 'cf=' + cf.length + ' token=' + token);
		await c.close();
	}
	ok('Turnstile switched off: saved secret removed, site key cleared', (await settings({ ts_site: '', ts_secret_remove: 1, consent_wait: 0 })) === '' && !setting('ts_site') && !setting('ts_secret'));
	{
		const front = await (await p.request.get(BASE)).text();
		ok('Turnstile off again: no data-ts / widget box on the storefront', /abp-form/.test(front) && !/data-ts=|class="abp-ts"/.test(front));
	}
	q("delete from oc_abp_subscriber where email like 'ts-%@example.com'"); q('delete from oc_abp_rate');

	// ================= 15. GA4 events =================
	only([idHigh, idCp]);
	q(`update oc_coupon set status=1 where coupon_id=${cid}`);
	{
		const rec = () => { window.__ga = []; window.gtag = function () { window.__ga.push([].slice.call(arguments)); }; };
		let { c, pg } = await shop({ permissions: ['clipboard-read', 'clipboard-write'] }, rec);
		await pg.goto(BASE); await sleep(1500);
		await pg.click(`.abp-item[data-id="${idCp}"] .abp-copy`).catch(async () => { await pg.click('#abp-bar .abp-next').catch(() => {}); });
		await sleep(600);
		const ev = await pg.evaluate(() => window.__ga);
		const view = ev.find(x => x[1] === 'abp_view');
		ok('GA4: abp_view sent through gtag with message id + variant, no personal data', view && view[2].abp_message_id > 0 && view[2].abp_variant === 'a' && Object.keys(view[2]).every(k => /^abp_(message_id|variant|placement)$/.test(k)), JSON.stringify(ev));
		await c.close();
		({ c, pg } = await shop({}, () => { window.__ga = []; window.gtag = function () { window.__ga.push([].slice.call(arguments)); }; Object.defineProperty(navigator, 'globalPrivacyControl', { get: () => true }); }));
		await pg.goto(BASE); await sleep(1500);
		ok('GA4: nothing sent with Global Privacy Control', (await pg.evaluate(() => window.__ga.length)) === 0);
		await c.close();
		({ c, pg } = await shop({}, () => { window.dataLayer = []; }));
		await pg.goto(BASE); await sleep(1500);
		ok('GA4: dataLayer push when only GTM is present', (await pg.evaluate(() => window.dataLayer.filter(x => x.event === 'abp_view').length)) >= 1);
		await c.close();
	}
	await settings({ ga4: 0 });
	{
		const { c, pg } = await shop({}, () => { window.__ga = []; window.gtag = function () { window.__ga.push(1); }; });
		await pg.goto(BASE); await sleep(1300);
		ok('GA4 switched off in settings → no events', (await pg.evaluate(() => window.__ga.length)) === 0);
		await c.close();
	}
	await settings({ ga4: 1 });

	// ================= "Wait for cookie consent" =================
	ok('settings: "Wait for cookie consent" saved (default off)', setting('consent_wait') === '0' && (await settings({ consent_wait: 1 })) === '' && setting('consent_wait') === '1');
	only([idHigh, idCp, idNew]);
	{
		const rec = () => { window.__ga = []; window.gtag = function () { window.__ga.push([].slice.call(arguments)); }; };
		let { c, pg } = await shop({}, rec);
		const tracks = []; pg.on('request', rq => { if (/track/.test(rq.url()) && rq.method() === 'POST') tracks.push(rq.url()); });
		await pg.goto(BASE); await sleep(1500);
		let st = await pg.evaluate(() => ({ ga: window.__ga.length, v: localStorage.getItem('abp_v'), ab: localStorage.getItem('abp_ab'), tr: sessionStorage.getItem('abp_track'), attr: !!document.querySelector('.abp[data-consent="1"]') }));
		let v = await visibleIds(pg);
		ok('consent wait: before consent no GA4 event, no statistics request, nothing stored; "new visitor" message waits', st.attr && st.ga === 0 && tracks.length === 0 && st.v === null && st.ab === null && st.tr === null && !v.includes(idNew) && v.includes(idHigh), JSON.stringify(st) + ' tracks=' + tracks.length + ' v=' + v.join());
		await pg.evaluate(() => { window.abpConsent = true; document.dispatchEvent(new Event('abp:consent')); });
		await sleep(1500);
		st = await pg.evaluate(() => ({ ga: window.__ga.filter(x => x[1] === 'abp_view').length, v: localStorage.getItem('abp_v') }));
		v = await visibleIds(pg);
		ok('consent wait: after window.abpConsent = true + "abp:consent" → GA4 view, statistics request, visitor remembered, "new visitor" message shown', st.ga >= 1 && tracks.length >= 1 && st.v !== null && v.includes(idNew), JSON.stringify(st) + ' tracks=' + tracks.length + ' v=' + v.join());
		await c.close();
		({ c, pg } = await shop({}, () => { window.abpConsent = true; window.__ga = []; window.gtag = function () { window.__ga.push([].slice.call(arguments)); }; }));
		await pg.goto(BASE); await sleep(1500);
		ok('consent wait: consent already given before the script → works at once', (await pg.evaluate(() => window.__ga.filter(x => x[1] === 'abp_view').length)) >= 1 && (await visibleIds(pg)).includes(idNew));
		await c.close();
	}
	await settings({ consent_wait: 0 });
	{
		const { c, pg } = await shop({}, () => { window.__ga = []; window.gtag = function () { window.__ga.push([].slice.call(arguments)); }; });
		await pg.goto(BASE); await sleep(1300);
		ok('consent wait off (default): events without any consent flag, no data-consent attribute', (await pg.evaluate(() => window.__ga.length)) >= 1 && !(await pg.$('.abp[data-consent]')));
		await c.close();
	}

	// ================= import / export keep the 2.4.0 fields =================
	{
		const exp = JSON.parse(await (await p.request.get(A(ROUTE + SEP + 'export'))).text());
		const m = exp.messages.find(x => x.options && x.options.tiers && x.options.tiers.length === 3);
		ok('export: placement, priority, options and tier labels included', exp.schema === 8 && !!m && Array.isArray(m.texts['en'].tier_label) && 'placement' in m && 'priority' in m, JSON.stringify(m || exp.messages[0]).slice(0, 300));
	}

	// ================= all features on one page (overview shots) =================
	only([idTiers, idHigh, idBottom, idInProd, idCp, idMail]);
	await both(S('product/product&product_id=' + P40), 'store-overview', null);

	// ================= OpenCart 1.5 edition extras (tests/e2e/abp15-extra.js) =================
	await require(path.join(__dirname, 'abp15-extra.js'))({ p, q, ok, sleep, A, S, BASE, DIR, ROUTE, SHOTS, TAG, add, only, shop, info, visibleIds, setting, settings, check, both, shopErrs });

	// ================= admin: no JS errors; PHP log clean =================
	await p.goto(A(ROUTE)); await p.goto(FORM(idTiers)); await sleep(1500);
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-form-full.png`, fullPage: true });
	await p.goto(A(ROUTE, '&tab=settings')); await sleep(400);
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-settings.png`, fullPage: true });
	{
		const pm = await (await b.newContext({ viewport: { width: 390, height: 844 } })).newPage();
		await pm.context().addCookies(await ctx.cookies());
		await pm.goto(FORM(idTiers)); await sleep(1200);
		await pm.screenshot({ path: `${SHOTS}/${TAG}-admin-form-390.png`, fullPage: false });
		await pm.context().close();
	}
	ok('admin: no JavaScript errors', adminErrs.length === 0, adminErrs.join(' | '));
	ok('storefront: no JavaScript errors', shopErrs.length === 0, [...new Set(shopErrs)].join(' | '));
	const log = fs.existsSync(logFile) ? fs.readFileSync(logFile, 'utf8').slice(log0) : '';
	ok('OpenCart error log (system/logs/error.txt): no new entries', log.trim() === '', log.slice(0, 600));
	// The PHP log of the test server: only entries about this module count (OpenCart 1.5 core itself is noisy on PHP 7).
	const plog = phpLog && fs.existsSync(phpLog) ? fs.readFileSync(phpLog, 'utf8').slice(php0).split(/\r?\n/).filter(l => /announcement_bar_pro|announcementbarpro|abp15|Fatal/i.test(l)) : [];
	ok('PHP log: no errors from this module (and no fatal errors at all)', plog.length === 0, plog.slice(0, 5).join(' | '));

	await b.close();
	const failed = R.filter(r => r.startsWith('FAIL'));
	console.log(`\n${TAG} ${VER}: ${R.length - failed.length}/${R.length} passed`);
	fs.writeFileSync(path.join(SHOTS, `${TAG}-results.txt`), R.join('\n') + `\n${R.length - failed.length}/${R.length}\n`);
	process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
