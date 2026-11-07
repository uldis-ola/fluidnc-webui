// Announcement Bar Pro 2.3.0 on a REAL local OpenCart.
// OC 3.0.5.1: install 2.2.0 with 2 messages (like the live shop), upgrade to 2.3.0 through the Extension Installer,
// migration on first admin visit, then every 2.3.0 feature through the real admin UI and storefront.
// OC 4.x: fresh 2.3.0 install, same feature checks.
// Env: OCBASE OCDIR OCDB MYSQL MYSQLARGS SHOTS. argv: zip220 zip230
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const [ZIP220, ZIP230] = process.argv.slice(2);
const BASE = process.env.OCBASE, DIR = process.env.OCDIR, DB = process.env.OCDB, SHOTS = process.env.SHOTS;
const q = s => { try { return execFileSync(process.env.MYSQL, [...process.env.MYSQLARGS.split(' '), DB, '-N', '-e', s], { stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim(); } catch (e) { return 'SQLERR ' + e.message.slice(0, 160); } };
const R = []; const ok = (n, c, x = '') => { R.push((c ? 'PASS ' : 'FAIL ') + n + (!c && x !== '' ? ' — ' + String(x).slice(0, 400) : '')); console.log(R[R.length - 1]); };
const sleep = ms => new Promise(r => setTimeout(r, ms));
const setting = k => q("select value from oc_setting where `key`='module_announcement_bar_pro_" + k + "'");

(async () => {
	fs.mkdirSync(SHOTS, { recursive: true });
	const VER = fs.readFileSync(DIR + '/index.php', 'utf8').match(/define\('VERSION', '([^']+)'/)[1];
	const OC4 = VER.startsWith('4'), SEP = OC4 ? '.' : '/', TAG = OC4 ? 'oc4' : 'oc3';
	const ROUTE = OC4 ? 'extension/announcement_bar_pro/module/announcement_bar_pro' : 'extension/module/announcement_bar_pro';
	const logFile = DIR + '/system/storage/logs/error.log', log0 = fs.existsSync(logFile) ? fs.statSync(logFile).size : 0;
	const b = await chromium.launch();
	const ctx = await b.newContext({ viewport: { width: 1366, height: 900 }, acceptDownloads: true });
	const p = await ctx.newPage();
	const adminErrs = []; p.on('pageerror', e => adminErrs.push(e.message));
	p.on('console', m => { if (m.type() === 'error' && /abp|announcement/i.test(m.text())) adminErrs.push(m.text()); });
	await p.goto(BASE + 'admin/'); await p.fill('#input-username', 'admin'); await p.fill('#input-password', 'admin123');
	await p.click('button[type=submit]'); await p.waitForURL(/user_token=/, { timeout: 30000 });
	const tok = new URL(p.url()).searchParams.get('user_token');
	const A = (r, x = '') => BASE + 'admin/index.php?route=' + r + '&user_token=' + tok + x;

	async function upload(zip) {
		await p.goto(A('marketplace/installer'));
		const [fc] = await Promise.all([p.waitForEvent('filechooser'), p.click('#button-upload')]); await fc.setFiles(zip);
		if (OC4) {
			const row = p.locator('#extension tr', { hasText: 'Announcement Bar Pro' });
			await row.locator('.btn-success').waitFor({ timeout: 60000 }); await row.locator('.btn-success').click();
			await row.locator('.btn-danger').waitFor({ timeout: 60000 }).catch(() => {});
		} else {
			await p.waitForFunction(() => { const t = document.querySelector('#progress-text'); return t && /(Success|Error|Warning)/i.test(t.innerText); }, null, { timeout: 60000 });
			ok('OC3 installer accepted ' + zip.split(/[\\/]/).slice(-2).join('/'), /Success/.test(await p.innerText('#progress-text')), await p.innerText('#progress-text'));
			await p.goto(A('marketplace/modification/refresh'));
		}
		await sleep(3000);
	}

	// ---------------- storefront helpers ----------------
	const shopErrs = [];
	async function shop(opts = {}) {
		const c = await b.newContext(Object.assign({ viewport: { width: 1366, height: 900 } }, opts));
		const pg = await c.newPage();
		pg.on('pageerror', e => shopErrs.push(e.message));
		pg.on('console', m => { if (m.type() === 'error' && !/favicon|404|Failed to load resource/i.test(m.text())) shopErrs.push(m.text()); });
		return { c, pg };
	}
	const barInfo = pg => pg.evaluate(() => {
		const bar = document.getElementById('abp-bar');
		if (!bar) return { bar: false };
		const vis = e => getComputedStyle(e).display !== 'none';
		const items = [...bar.querySelectorAll('.abp-item')];
		return {
			bar: true, shown: getComputedStyle(bar).display !== 'none',
			items: items.map(i => ({ id: i.dataset.id, key: i.dataset.key, vis: vis(i), off: i.hasAttribute('data-off'), variant: i.dataset.variant || '', style: i.dataset.style, txt: i.innerText.replace(/\s+/g, ' ').trim() })),
			cart: bar.getAttribute('data-cart') || '', track: bar.getAttribute('data-track') || '',
			hscroll: document.documentElement.scrollWidth > window.innerWidth
		};
	});

	// ---------------- admin helpers (real UI) ----------------
	const FORM = id => A(ROUTE + SEP + 'form', id ? '&message_id=' + id : '');
	async function saveForm(id, fill) {
		await p.goto(FORM(id));
		await p.waitForSelector('#form-message');
		await fill();
		await Promise.all([p.waitForURL(/tab=messages/, { timeout: 15000 }).catch(() => {}), p.click('button[form="form-message"]')]);
		await sleep(600);
		if (/tab=messages/.test(p.url())) return '';
		return (await p.$$eval('.text-danger, .invalid-feedback.d-block, .alert-danger', es => es.map(e => e.textContent.trim()).filter(Boolean).join(' | '))) || 'not saved';
	}
	const setVal = (sel, v) => p.evaluate(([s, val]) => { const el = document.querySelector(s); el.value = val; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }, [sel, v]);
	async function settings(changes) {
		await p.goto(A(ROUTE, '&tab=settings'));
		await p.waitForSelector('#form-settings');
		for (const [name, v] of Object.entries(changes)) {
			const full = 'module_announcement_bar_pro_' + name;
			const el = await p.$(`[name="${full}"]:not([type=hidden])`);
			const type = await el.evaluate(e => e.type);
			if (type === 'radio') { await p.click(`label[for="input-${name}-${v}"]`); }
			else if (type === 'checkbox') { await p.setChecked(`[name="${full}"][type=checkbox]`, !!v); }
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
		const err = await p.$$eval('.text-danger, .alert-danger', es => es.map(e => e.textContent.trim()).filter(Boolean).join(' | '));
		return err;
	}

	// ================= install / upgrade =================
	let keysBefore = [];
	if (!OC4) {
		await upload(ZIP220);
		await p.request.get(A('extension/extension/module/install', '&extension=announcement_bar_pro')); await sleep(500);
		await p.goto(A(ROUTE));
		const add22 = async (o) => {
			await p.goto(FORM(0));
			await p.fill('textarea[name="message_description[1][text]"]', o.text);
			if (o.link_text) await p.fill('input[name="message_description[1][link_text]"]', o.link_text);
			if (o.link) await p.fill('#input-link', o.link);
			if (o.icon) await p.fill('#input-icon', o.icon);
			await p.evaluate(() => document.querySelector('button[form="form-message"]').click());
			await sleep(2500);
		};
		await add22({ text: 'Orders open soon - prices and the calculator already work.', link_text: 'Contact us', link: 'index.php?route=information/contact' });
		await add22({ text: 'Delivery across Europe - parcel locker, courier, post.', icon: 'fa-truck' });
		ok('2.2.0: 2 messages, schema 6', q('select count(*) from oc_abp_message') === '2' && setting('schema') === '6', q('select count(*) from oc_abp_message') + '/' + setting('schema'));
		// 2.2.0 settings the upgrade must keep
		await settings({ mode: 'stack', dismiss_days: '7' });
		ok('2.2.0 settings changed (stack, 7 days)', setting('mode') === 'stack' && setting('dismiss_days') === '7');
		var colorBefore = setting('color_mode');
		const s0 = await shop(); await s0.pg.goto(BASE); await s0.pg.waitForSelector('#abp-bar'); await sleep(800);
		keysBefore = (await barInfo(s0.pg)).items.map(i => i.key).sort(); await s0.c.close();
		ok('2.2.0 storefront: 2 messages with dismiss keys', keysBefore.length === 2 && keysBefore.every(k => /^[0-9a-f]{12}$/.test(k)), keysBefore.join());

		await upload(ZIP230);
		const before = await (await p.request.get(BASE)).text();
		ok('after file upgrade, before admin visit: storefront works on schema 6 (bar, no cart endpoint)', /id="abp-bar"/.test(before) && !/data-cart=/.test(before) && /<\/html>/i.test(before) && setting('schema') === '6');
		await p.goto(A(ROUTE));
		ok('admin opened: schema 7', setting('schema') === '7', setting('schema'));
		const cols = q("select count(*) from information_schema.columns where table_schema=database() and table_name='oc_abp_message' and column_name in ('type','threshold','progress_bar','coupon','copies','ab_test','views_b','clicks_b','copies_b','weekdays','time_start','time_end','categories','products','style','button_style')")
			+ '/' + q("select count(*) from information_schema.columns where table_schema=database() and table_name='oc_abp_message_description' and column_name in ('success_text','text_b','mobile_text_b','link_text_b')");
		ok('migration 6→7: 16 + 4 new columns', cols === '16/4', cols);
		ok('messages kept (2, texts unchanged, button look kept as outline)', q('select count(*) from oc_abp_message') === '2' && /Delivery across Europe/.test(q('select group_concat(text) from oc_abp_message_description')) && q("select group_concat(distinct button_style) from oc_abp_message") === 'outline');
		ok('settings kept (stack, 7 days, colour mode) + new defaults (solid, fade)', setting('mode') === 'stack' && setting('dismiss_days') === '7' && setting('color_mode') === colorBefore && setting('style') === 'solid' && setting('transition') === 'fade', colorBefore + ' → ' + setting('color_mode'));
		await p.goto(A(ROUTE));
		ok('2nd admin visit: idempotent (no duplicate column)', setting('schema') === '7' && q("select count(*) from information_schema.columns where table_schema=database() and table_name='oc_abp_message' and column_name='coupon'") === '1');
		const s1 = await shop(); await s1.pg.goto(BASE); await s1.pg.waitForSelector('#abp-bar'); await sleep(800);
		const keysAfter = (await barInfo(s1.pg)).items.map(i => i.key).sort(); await s1.c.close();
		ok('dismiss keys unchanged by the upgrade (closed messages stay closed)', JSON.stringify(keysAfter) === JSON.stringify(keysBefore), keysBefore.join() + ' → ' + keysAfter.join());
	} else {
		await upload(ZIP230);
		await p.request.get(A('extension/module.install', '&extension=announcement_bar_pro&code=announcement_bar_pro')); await sleep(500);
		await p.goto(A(ROUTE));
		ok('fresh 2.3.0: schema 7, switched on, Automatic colours', setting('schema') === '7' && setting('status') === '1' && setting('color_mode') === 'auto');
		let e1 = await saveForm(0, async () => { await p.fill('textarea[name="message_description[1][text]"]', 'Orders open soon - prices and the calculator already work.'); await p.fill('input[name="message_description[1][link_text]"]', 'Contact us'); await p.fill('#input-link', 'index.php?route=information/contact'); });
		let e2 = await saveForm(0, async () => { await p.fill('textarea[name="message_description[1][text]"]', 'Delivery across Europe - parcel locker, courier, post.'); await p.fill('#input-icon', 'fa-truck'); });
		ok('2 messages added through the new form', !e1 && !e2 && q('select count(*) from oc_abp_message') === '2', e1 + ' ' + e2);
		ok('settings: stack mode saved through the settings tab (AJAX)', (await settings({ mode: 'stack', dismiss_days: '7' })) === '' && setting('mode') === 'stack');
	}
	const id1 = q('select message_id from oc_abp_message order by message_id limit 1');
	const id2 = q('select message_id from oc_abp_message order by message_id limit 1,1');

	// ================= admin form: sections + live preview =================
	await p.goto(FORM(id1));
	await p.waitForSelector('#abp-preview-frame');
	await sleep(1500);
	const pvFrame = () => p.frames().find(f => f !== p.mainFrame() && f.parentFrame() === p.mainFrame());
	let pvf = pvFrame();
	let pv = pvf ? await pvf.evaluate(() => { const b = document.getElementById('abp-bar'); return { bar: !!b, txt: b ? b.innerText : '', js: !!(b && b.abpInit), css: !!(b && getComputedStyle(b).overflow === 'hidden') }; }).catch(e => ({ err: e.message })) : { err: 'no frame' };
	ok('form: live preview loads the real storefront CSS + JS from the catalog', pv.bar && pv.js && pv.css && /Orders open soon/.test(pv.txt), JSON.stringify(pv));
	const sections = await p.$$eval('.abp-card .abp-card-title', es => es.map(e => e.textContent.trim().replace(/\s+/g, ' ')));
	ok('form: grouped sections (preview, content, button & coupon, free shipping, A/B, schedule, targeting, appearance)', sections.length >= 8, sections.join(' | '));
	await p.fill('textarea[name="message_description[1][text]"]', 'Live preview check 123');
	await sleep(1200); pvf = pvFrame();
	pv = await pvf.evaluate(() => document.getElementById('abp-bar').innerText).catch(e => e.message);
	ok('form: preview updates on input', /Live preview check 123/.test(pv), pv);
	await p.click('[data-abp-pv="device"][data-value="phone"]'); await sleep(1000);
	const phoneW = await p.$eval('#abp-preview-frame', f => f.getBoundingClientRect().width);
	ok('form: phone preview ≈ 390 px wide', phoneW > 300 && phoneW <= 390, String(phoneW));
	await p.click('[data-abp-pv="device"][data-value="desktop"]'); await sleep(300);
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-form-preview.png`, fullPage: false });
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-form-full.png`, fullPage: true });

	// ================= 1. Free-shipping progress =================
	const P40 = '40', price40 = parseFloat(q('select price from oc_product where product_id=40'));
	let e = await saveForm(0, async () => {
		await p.selectOption('[name="type"]', 'free_shipping');
		await p.fill('textarea[name="message_description[1][text]"]', 'Add {remaining} more for free shipping');
		await p.fill('[name="threshold"]', '200');
		await p.fill('textarea[name="message_description[1][success_text]"]', 'You have free shipping!');
		await p.fill('#input-icon', 'fa-truck');
	});
	await sleep(800);
	const idFs = q('select max(message_id) from oc_abp_message');
	ok('free shipping: saved via the form (type, threshold 200, success text)', !e && q('select concat(type,"|",threshold,"|",progress_bar) from oc_abp_message where message_id=' + idFs) === 'free_shipping|200.0000|1' && q('select success_text from oc_abp_message_description where message_id=' + idFs + ' and language_id=1') === 'You have free shipping!', e || (idFs + ' ' + q('select concat(type,"|",threshold,"|",progress_bar) from oc_abp_message where message_id=' + idFs)));
	e = await saveForm(0, async () => { await p.selectOption('[name="type"]', 'free_shipping'); await p.fill('textarea[name="message_description[1][text]"]', 'X {remaining}'); await p.fill('[name="threshold"]', 'abc'); });
	ok('free shipping: invalid threshold + missing success text rejected', /0|amount|summ/i.test(e) && q('select count(*) from oc_abp_message') === '3', e);

	{
		const { c, pg } = await shop();
		await pg.goto(BASE + 'index.php?route=product/product&product_id=' + P40); await pg.waitForSelector('#abp-bar'); await sleep(1200);
		const fsTxt = () => pg.$eval(`.abp-item[data-id="${idFs}"]`, el => ({ t: el.innerText.replace(/\s+/g, ' ').trim(), w: (el.querySelector('.abp-progress-fill') || {}).style ? el.querySelector('.abp-progress-fill').style.width : '', done: el.hasAttribute('data-done') }));
		let t0 = await fsTxt();
		ok('free shipping: empty cart shows the full amount + progress bar 0 %', /Add \$200\.00 more for free shipping/.test(t0.t) && t0.w === '0%', JSON.stringify(t0));
		const ajax = [];
		pg.on('request', r => { if (/announcement_bar_pro(\/|\.|%7C|\|)cart/.test(r.url())) ajax.push(r.method() + ' ' + r.url()); });
		await pg.click('#button-cart'); await sleep(2500);
		const t1 = await fsTxt();
		const left = (200 - price40).toFixed(2);
		ok('free shipping: AJAX add-to-cart updates the remaining amount without reload', t1.t.includes('$' + left) && parseFloat(t1.w) > 0 && ajax.length >= 1 && ajax.every(x => x.startsWith('GET')), JSON.stringify(t1) + ' ' + ajax.join());
		await pg.click('#button-cart'); await sleep(2500);
		const t2 = await fsTxt();
		ok('free shipping: threshold reached → success text, bar 100 %', /You have free shipping!/.test(t2.t) && t2.w === '100%' && t2.done, JSON.stringify(t2));
		await pg.reload(); await pg.waitForSelector('#abp-bar'); await sleep(800);
		ok('free shipping: server render after reload matches (success)', /You have free shipping!/.test((await fsTxt()).t));
		// endpoint: same-origin only, read-only JSON
		const direct = await (await p.request.get(BASE + 'index.php?route=' + ROUTE + SEP + 'cart&ids=' + idFs)).text();
		ok('free shipping endpoint: request without browser origin headers → empty JSON, no error output', direct.trim() === '{"m":{}}', direct.slice(0, 200));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-free-shipping.png`, clip: { x: 0, y: 0, width: 1366, height: 200 } });
		await c.close();
	}

	// ================= 2. Coupon + copy =================
	e = await saveForm(id2, async () => { await p.fill('#input-coupon', 'save-10'); await p.click('label[for="input-button-style-outline"]'); });
	ok('coupon: saved uppercase', !e && q('select coupon from oc_abp_message where message_id=' + id2) === 'SAVE-10', e || q('select coupon from oc_abp_message where message_id=' + id2));
	e = await saveForm(id2, async () => { await p.fill('#input-coupon', 'bad code!'); });
	ok('coupon: invalid code rejected', !!e && q('select coupon from oc_abp_message where message_id=' + id2) === 'SAVE-10', e);
	{
		const { c, pg } = await shop({ permissions: ['clipboard-read', 'clipboard-write'] });
		await pg.goto(BASE); await pg.waitForSelector('#abp-bar'); await sleep(1200);
		const btn = pg.locator(`.abp-item[data-id="${id2}"] .abp-copy`);
		await btn.click(); await sleep(400);
		const st = await pg.$eval(`.abp-item[data-id="${id2}"] .abp-coupon`, el => ({ label: el.querySelector('.abp-copy-label').textContent, live: el.querySelector('.abp-sr').textContent, aria: el.querySelector('.abp-sr').getAttribute('aria-live'), copied: el.querySelector('.abp-copy').classList.contains('abp-copied') }));
		const clip = await pg.evaluate(() => navigator.clipboard.readText()).catch(e => 'ERR ' + e.message);
		await btn.click(); await sleep(1500);
		ok('coupon: Copy puts the code on the clipboard, shows "Copied!" (aria-live)', clip === 'SAVE-10' && /Copied/.test(st.label) && /Copied/.test(st.live) && st.aria === 'polite' && st.copied, clip + ' ' + JSON.stringify(st));
		ok('coupon: copy counted once per session', q('select copies from oc_abp_message where message_id=' + id2) === '1', q('select copies from oc_abp_message where message_id=' + id2));
		await c.close();
	}

	// ================= 3. A/B test =================
	e = await saveForm(id1, async () => {
		await p.fill('textarea[name="message_description[1][text]"]', 'Orders open soon - prices and the calculator already work.');
		await p.check('#input-ab-test');
		await p.fill('textarea[name="message_description[1][text_b]"]', 'Version B: order today, ship tomorrow.');
		await p.fill('input[name="message_description[1][link_text_b]"]', 'Write to us');
	});
	ok('A/B: test switched on with text B', !e && q('select ab_test from oc_abp_message where message_id=' + id1) === '1', e);
	e = await saveForm(id1, async () => { await p.fill('textarea[name="message_description[1][text_b]"]', ''); });
	ok('A/B: empty text B rejected while the test is on', !!e, e);
	await q(`update oc_abp_message set views=0,clicks=0,copies=0,views_b=0,clicks_b=0,copies_b=0 where message_id=${id1}`);
	{
		const seen = [];
		for (let i = 0; i < 12; i++) {
			const { c, pg } = await shop();
			await pg.goto(BASE); await pg.waitForSelector('#abp-bar'); await sleep(700);
			const v1 = (await barInfo(pg)).items.find(x => x.id === id1);
			await pg.reload(); await sleep(500);
			const v2 = (await barInfo(pg)).items.find(x => x.id === id1);
			seen.push([v1.variant, v2.variant, v1.txt]);
			await sleep(300); await c.close();
		}
		const stable = seen.every(s => s[0] === s[1] && /^[ab]$/.test(s[0]));
		const nb = seen.filter(s => s[0] === 'b').length;
		ok('A/B: variant stable per browser across reloads', stable, JSON.stringify(seen.map(s => s[0] + s[1])));
		ok('A/B: both variants handed out (12 browsers)', nb > 0 && nb < 12, 'B=' + nb);
		ok('A/B: variant B shows the B text and button', seen.filter(s => s[0] === 'b').every(s => /Version B/.test(s[2]) && /Write to us/.test(s[2])) && seen.filter(s => s[0] === 'a').every(s => /Orders open soon/.test(s[2]) && /Contact us/.test(s[2])), JSON.stringify(seen.slice(0, 3)));
		await sleep(1500);
		const st = q(`select concat(views,'/',views_b) from oc_abp_message where message_id=${id1}`).split('/').map(Number);
		ok('A/B: views counted per variant (once per browser session)', st[0] === 12 - nb && st[1] === nb, st.join('/') + ' expected ' + (12 - nb) + '/' + nb);
	}
	q(`update oc_abp_message set views=150,clicks=3,views_b=150,clicks_b=9 where message_id=${id1}`);
	await p.goto(A(ROUTE));
	const row = await p.$eval(`tr[data-message="${id1}"]`, tr => ({ a: tr.querySelector('[data-abp-ctr="a"]').textContent.trim(), b: tr.querySelector('[data-abp-ctr="b"]').textContent.trim(), winB: tr.querySelector('[data-abp-ctr="b"]').classList.contains('abp-win'), winA: tr.querySelector('[data-abp-ctr="a"]').classList.contains('abp-win') }));
	ok('A/B list: both CTRs, better one (B, 6 %) marked after 100 views each', /2\.0/.test(row.a) && /6\.0/.test(row.b) && row.winB && !row.winA, JSON.stringify(row));
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-list.png`, fullPage: true });
	p.on('dialog', d => d.accept());
	const useBreqs = []; p.on('request', r => { if (/useB/.test(r.url())) useBreqs.push(r.method()); });
	await Promise.all([p.waitForResponse(r => /useB/.test(r.url()), { timeout: 15000 }).catch(() => {}), p.locator(`tr[data-message="${id1}"] [data-abp-use-b]`).dblclick()]);
	await p.waitForLoadState('load'); await sleep(1500);
	ok('A/B "Use B": double click sends one request', useBreqs.length === 1, useBreqs.join());
	ok('A/B "Use B": B text becomes main, test off, B counters kept as main', q(`select concat(ab_test,'|',views,'|',clicks,'|',views_b) from oc_abp_message where message_id=${id1}`) === '0|150|9|0' && q(`select concat(text,'|',link_text,'|',text_b) from oc_abp_message_description where message_id=${id1} and language_id=1`) === 'Version B: order today, ship tomorrow.|Write to us|', q(`select concat(ab_test,'|',views,'|',clicks,'|',views_b) from oc_abp_message where message_id=${id1}`) + ' ' + q(`select concat(text,'|',link_text,'|',text_b) from oc_abp_message_description where message_id=${id1} and language_id=1`));
	const forgedB = await p.request.post(A(ROUTE + SEP + 'useB'), { form: { message_id: id1 } });
	ok('A/B "Use B" again (test off) → error JSON, nothing changed', /error/.test(await forgedB.text()));

	// ================= 4. Weekly schedule =================
	const nowInfo = async () => { const { c, pg } = await shop(); await pg.goto(BASE); await pg.waitForSelector('#abp-bar'); const r = await pg.$eval('#abp-bar', bb => ({ now: +bb.dataset.now, tz: +bb.dataset.tz })); await c.close(); return r; };
	let ni = await nowInfo();
	const storeDate = new Date(ni.now + ni.tz * 1000), today = storeDate.getUTCDay() || 7;
	e = await saveForm(0, async () => {
		await p.fill('textarea[name="message_description[1][text]"]', 'Weekend only sale');
		for (let d = 1; d <= 7; d++) { if (d !== today) await p.click(`.abp-day:nth-child(${d}) span`); }
	});
	const idSch = q('select max(message_id) from oc_abp_message');
	ok('schedule: weekday pills saved', !e && q('select weekdays from oc_abp_message where message_id=' + idSch) === [1, 2, 3, 4, 5, 6, 7].filter(d => d !== today).join(''), e || q('select weekdays from oc_abp_message where message_id=' + idSch));
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await pg.waitForSelector('#abp-bar'); await sleep(800);
		const it = (await barInfo(pg)).items.find(x => x.id === idSch);
		ok('schedule: message hidden on a day that is not ticked (server + script)', it && it.off && !it.vis, JSON.stringify(it));
		await c.close();
	}
	const hm = m => String(Math.floor(((m % 1440) + 1440) % 1440 / 60)).padStart(2, '0') + ':' + String(((m % 60) + 60) % 60).padStart(2, '0');
	ni = await nowInfo();
	const sd = new Date(ni.now + ni.tz * 1000), mins = sd.getUTCHours() * 60 + sd.getUTCMinutes();
	e = await saveForm(idSch, async () => {
		for (let d = 1; d <= 7; d++) { await p.setChecked(`.abp-day:nth-child(${d}) input`, false, { force: true }); }
		await p.fill('#input-time-start', hm(mins - 30)); await p.fill('#input-time-end', hm(mins + 2));
	});
	ok('schedule: daily window saved', !e && q('select concat(weekdays,"|",time_start,"|",time_end) from oc_abp_message where message_id=' + idSch) === '|' + hm(mins - 30) + '|' + hm(mins + 2), e || q('select concat(weekdays,"|",time_start,"|",time_end) from oc_abp_message where message_id=' + idSch));
	await p.goto(A(ROUTE));
	const schBadge = await p.$eval(`tr[data-message="${idSch}"] .abp-b-schedule`, x => x.textContent.trim()).catch(() => '');
	ok('list: schedule badge shows the time window', schBadge.includes(hm(mins - 30) + '–' + hm(mins + 2)), schBadge);
	{
		const c = await b.newContext({ viewport: { width: 1366, height: 900 } });
		const pg = await c.newPage(); pg.on('pageerror', x => shopErrs.push(x.message));
		await pg.clock.install({ time: Date.now() });
		await pg.goto(BASE); await pg.waitForSelector('#abp-bar'); await pg.clock.runFor(1500);
		let it = (await barInfo(pg)).items.find(x => x.id === idSch);
		ok('schedule: shown inside today\'s time window', it && !it.off && it.vis, JSON.stringify(it));
		await pg.clock.runFor('04:00');
		it = (await barInfo(pg)).items.find(x => x.id === idSch);
		ok('schedule: script hides it when the window ends while the page stays open', it && it.off && !it.vis, JSON.stringify(it));
		await c.close();
	}
	e = await saveForm(idSch, async () => { await p.fill('#input-time-start', '10:00'); await p.fill('#input-time-end', '10:00'); });
	ok('schedule: equal start/end time rejected', !!e, e);

	// ================= 5. Category / product targeting =================
	const inCat = q("select p2c.product_id from oc_product_to_category p2c join oc_product p on p.product_id=p2c.product_id where p2c.category_id=20 and p.status=1 and p2c.product_id<>40 order by p2c.product_id limit 1");
	const target = q("select p.product_id from oc_product p where p.status=1 and p.product_id not in (select product_id from oc_product_to_category where category_id=20) order by p.product_id limit 1");
	const none = q(`select p.product_id from oc_product p where p.status=1 and p.product_id<>${target} and p.product_id not in (select product_id from oc_product_to_category where category_id=20) order by p.product_id desc limit 1`);
	const targetName = q(`select name from oc_product_description where product_id=${target} and language_id=1`);
	const catName = q('select name from oc_category_description where category_id=20 and language_id=1');
	e = await saveForm(0, async () => {
		await p.fill('textarea[name="message_description[1][text]"]', 'Targeted message');
		await p.fill('#input-categories .abp-ac-input', catName.slice(0, 4)); await p.waitForSelector('#input-categories .abp-ac-opt', { timeout: 8000 });
		await p.locator('#input-categories .abp-ac-opt', { hasText: catName }).first().dispatchEvent('mousedown');
		await p.fill('#input-products .abp-ac-input', targetName.slice(0, 5)); await p.waitForSelector('#input-products .abp-ac-opt', { timeout: 8000 });
		await p.locator('#input-products .abp-ac-opt', { hasText: targetName }).first().dispatchEvent('mousedown');
		await sleep(300);
	});
	const idT = q('select max(message_id) from oc_abp_message');
	ok('targeting: category + product chosen with autocomplete chips', !e && q('select concat(categories,"|",products) from oc_abp_message where message_id=' + idT) === `[20]|[${target}]`, e || q('select concat(categories,"|",products) from oc_abp_message where message_id=' + idT));
	await p.goto(FORM(idT));
	ok('targeting: chips show the names again when editing', (await p.$$eval('.abp-chip-text', es => es.map(x => x.textContent))).join('|').includes(catName));
	e = await saveForm(idT, async () => { await p.evaluate(() => { const h = document.createElement('input'); h.type = 'hidden'; h.name = 'categories[]'; h.value = '999999'; document.getElementById('form-message').appendChild(h); }); });
	ok('targeting: unknown category ID rejected', !!e && q('select categories from oc_abp_message where message_id=' + idT) === '[20]', e);
	{
		const { c, pg } = await shop();
		const vis = async url => { await pg.goto(BASE + url); await pg.waitForLoadState('domcontentloaded'); await sleep(500); const i = await barInfo(pg); const it = i.bar && i.items.find(x => x.id === idT); return !!(it && it.vis); };
		const r = { cat: await vis('index.php?route=product/category&path=20'), sub: await vis('index.php?route=product/category&path=18'), prodInCat: await vis('index.php?route=product/product&product_id=' + inCat), prod: await vis('index.php?route=product/product&product_id=' + target), other: await vis('index.php?route=product/product&product_id=' + none), home: await vis('') };
		ok('targeting: category page, product in category, chosen product → shown; other pages → hidden', r.cat && r.prodInCat && r.prod && !r.sub && !r.other && !r.home, JSON.stringify(r));
		await c.close();
	}

	// ================= 6. Export → import =================
	await p.goto(A(ROUTE));
	const [dl] = await Promise.all([p.waitForEvent('download'), p.click('a[href*="announcement_bar_pro"][href*="export"]')]);
	const expFile = path.join(SHOTS, `${TAG}-export.json`); await dl.saveAs(expFile);
	const exp = JSON.parse(fs.readFileSync(expFile, 'utf8'));
	const total0 = +q('select count(*) from oc_abp_message');
	ok('export: all messages, texts keyed by language code, no statistics', exp.format === 'announcement_bar_pro' && exp.messages.length === total0 && exp.messages.every(m => m.texts && m.texts['en-gb'] && !('views' in m) && !('message_id' in m)), JSON.stringify(exp).slice(0, 300));
	// add an unknown language to one message: it must be skipped
	exp.messages[0].texts['xx-yy'] = { text: 'unknown language' };
	fs.writeFileSync(expFile, JSON.stringify(exp));
	const [fc] = await Promise.all([p.waitForEvent('filechooser'), p.click('[data-abp-import]')]);
	await Promise.all([p.waitForURL(/tab=messages/, { timeout: 20000 }).catch(() => {}), fc.setFiles(expFile)]);
	await sleep(2000);
	const total1 = +q('select count(*) from oc_abp_message');
	await p.waitForSelector('#abp-alerts .alert-success', { timeout: 10000 }).catch(() => {});
	const msg = await p.$eval('#abp-alerts', el => el.innerText).catch(() => '');
	ok('import: every message created, switched off', total1 === total0 * 2 && q(`select count(*) from oc_abp_message where message_id>${q('select max(message_id) from oc_abp_message') - total0} and status=0`) === String(total0), total0 + ' → ' + total1 + ' ' + msg);
	ok('import: report shows counts and the skipped language', new RegExp(total0 + '.*0').test(msg) && /xx-yy/.test(msg), msg);
	const F = "concat(type,'|',threshold,'|',coupon,'|',weekdays,'|',time_start,'|',categories,'|',products,'|',progress_bar,'|',button_style)";
	const copyOf = id => q(`select m.message_id from oc_abp_message m join oc_abp_message_description d on d.message_id=m.message_id and d.language_id=1 where d.text=(select text from oc_abp_message_description where message_id=${id} and language_id=1) and m.message_id<>${id} order by m.message_id desc limit 1`);
	const rt = [idFs, idT, id2].map(id => [q(`select ${F} from oc_abp_message where message_id=${id}`), q(`select ${F} from oc_abp_message where message_id=${copyOf(id)}`)]);
	ok('import: round trip keeps type, threshold, coupon, schedule, targeting and texts', rt.every(x => x[0] === x[1] && x[0] !== '') && q("select count(*) from oc_abp_message_description where success_text='You have free shipping!'") === '2', JSON.stringify(rt));
	fs.writeFileSync(path.join(SHOTS, 'bad.json'), '{"format":"something else"}');
	const [fc2] = await Promise.all([p.waitForEvent('filechooser'), p.click('[data-abp-import]')]);
	await fc2.setFiles(path.join(SHOTS, 'bad.json')); await sleep(2000);
	ok('import: foreign file refused, nothing created', +q('select count(*) from oc_abp_message') === total1 && /not an Announcement Bar Pro export/i.test(await p.innerText('#abp-alerts')), await p.innerText('#abp-alerts'));
	const forged = await p.request.post(A(ROUTE + SEP + 'import'), { multipart: { file: { name: 'x.json', mimeType: 'application/json', buffer: Buffer.from('x'.repeat(1100000)) } } });
	ok('import: file over 1 MB refused', /error/.test(await forged.text()) && +q('select count(*) from oc_abp_message') === total1);
	// keep the storefront readable: remove the imported copies (switched off anyway) — through the list UI
	await p.goto(A(ROUTE));
	await p.$$eval('input[name="selected[]"]', (els, min) => els.forEach(e => { if (+e.value >= min) e.checked = true; }), +q('select min(message_id) from oc_abp_message') + total0);
	if (OC4) { await p.click('#abp-delete'); await sleep(2500); } else { await Promise.all([p.waitForNavigation().catch(() => {}), p.click('#abp-delete')]); await sleep(1000); }
	ok('list: imported copies deleted again', +q('select count(*) from oc_abp_message') === total0, q('select count(*) from oc_abp_message'));

	// ================= 7. Styles, transitions, arrows =================
	await saveForm(idSch, async () => { await p.fill('#input-time-start', ''); await p.fill('#input-time-end', ''); });
	ok('styles: Automatic colours selected', (await settings({ color_mode: 'auto' })) === '' && setting('color_mode') === 'auto');
	const measure = pg => pg.evaluate(() => {
		const parse = s => [...String(s).matchAll(/rgba?\(([^)]+)\)/g)].map(m => m[1].split(/[\s,\/]+/).filter(Boolean).map(Number));
		const lum = c => { const v = c.slice(0, 3).map(x => { x /= 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); }); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
		const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
		const over = (c, base) => { const a = c.length > 3 ? c[3] : 1; return [0, 1, 2].map(i => c[i] * a + base[i] * (1 - a)); };
		const out = [];
		document.querySelectorAll('#abp-bar .abp-item').forEach(it => {
			if (getComputedStyle(it).display === 'none') return;
			const cs = getComputedStyle(it), fg = parse(cs.color)[0];
			let bgs = parse(it.style.backgroundImage || '');
			if (!bgs.length) bgs = parse(cs.backgroundColor);
			const backs = [];
			bgs.forEach(c => { if (c.length > 3 && c[3] < 1) { backs.push(over(c, [255, 255, 255]), over(c, [0, 0, 0])); } else backs.push(c); });
			out.push({ id: it.dataset.id, min: Math.min(...backs.map(bk => ratio(fg, bk))), style: it.dataset.style, bg: cs.backgroundColor, img: it.style.backgroundImage.slice(0, 40), fg: cs.color });
		});
		return { items: out, hscroll: document.documentElement.scrollWidth > innerWidth, glass: document.getElementById('abp-bar').classList.contains('abp-glass') };
	});
	for (const style of ['solid', 'gradient', 'soft', 'minimal', 'glass']) {
		const er = await settings({ style });
		const res = {};
		for (const [dev, vp] of [['desktop', { width: 1366, height: 900 }], ['phone', { width: 390, height: 844 }]]) {
			const { c, pg } = await shop(dev === 'phone' ? { viewport: vp, isMobile: true, hasTouch: true } : { viewport: vp });
			await pg.goto(BASE); await pg.waitForSelector('#abp-bar'); await sleep(1500);
			res[dev] = await measure(pg);
			await pg.screenshot({ path: `${SHOTS}/${TAG}-store-${style}-${dev}.png`, clip: { x: 0, y: 0, width: vp.width, height: dev === 'phone' ? 330 : 230 } });
			await c.close();
		}
		const mins = [...res.desktop.items, ...res.phone.items].map(x => x.min);
		ok(`style ${style}: every message ≥ 4.5:1 (desktop 1366 + phone 390), no horizontal scroll`, !er && mins.length >= 4 && Math.min(...mins) >= 4.5 && !res.desktop.hscroll && !res.phone.hscroll && res.desktop.items.every(x => x.style === style), er + ' min ' + Math.min(...mins).toFixed(2) + ' ' + JSON.stringify(res.desktop.items.slice(0, 2)));
	}
	// derived styles also keep contrast with a mid-tone custom colour (white on #3b82f6 is only 3.7:1)
	await settings({ color_mode: 'custom' });
	await saveForm(id2, async () => { await setVal('#input-bg_color, #input-bg-color', '#3b82f6'); await setVal('#input-text_color, #input-text-color', '#ffffff'); });
	{
		const lows = [];
		for (const style of ['gradient', 'soft', 'minimal', 'glass']) {
			await settings({ style });
			const { c, pg } = await shop(); await pg.goto(BASE); await pg.waitForSelector('#abp-bar'); await sleep(1200);
			const m = (await measure(pg)).items.find(x => x.id === id2); lows.push(style + ' ' + (m ? m.min.toFixed(2) : 'n/a'));
			await c.close();
		}
		ok('custom mid-tone colour: gradient / soft / minimal / glass all ≥ 4.5:1', lows.every(x => parseFloat(x.split(' ')[1]) >= 4.5), lows.join(', '));
	}
	// per-message style override
	await settings({ style: 'solid' });
	await saveForm(id2, async () => { await p.click('label[for="input-style-soft"]'); });
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await pg.waitForSelector('#abp-bar'); await sleep(1200);
		const st = (await barInfo(pg)).items.reduce((o, x) => (o[x.id] = x.style, o), {});
		ok('per-message style overrides the module style', st[id2] === 'soft' && st[id1] === 'solid', JSON.stringify(st));
		await c.close();
	}
	// rotate + slide + arrows
	ok('settings: rotate, slide, arrows saved', (await settings({ mode: 'rotate', transition: 'slide', arrows: 1 })) === '' && setting('transition') === 'slide' && setting('arrows') === '1');
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await pg.waitForSelector('#abp-bar'); await sleep(1200);
		const act = () => pg.$eval('#abp-bar .abp-item.abp-active', x => x.dataset.id);
		const a0 = await act();
		const nav = await pg.$$eval('#abp-bar .abp-nav', ns => ns.map(n => ({ label: n.getAttribute('aria-label'), hidden: n.hidden, type: n.type })));
		await pg.click('#abp-bar .abp-next'); await sleep(400); const a1 = await act();
		await pg.focus('#abp-bar .abp-prev'); await pg.keyboard.press('Enter'); await sleep(400); const a2 = await act();
		const anim = await pg.$eval('#abp-bar .abp-item.abp-active', x => getComputedStyle(x).animationName);
		ok('arrows: labelled buttons, click → next, keyboard Enter → previous', nav.length === 2 && nav.every(n => n.label && !n.hidden && n.type === 'button') && a1 !== a0 && a2 === a0, JSON.stringify({ nav, a0, a1, a2 }));
		ok('slide transition animates the new message', /abp-slide/.test(anim), anim);
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-rotate-arrows.png`, clip: { x: 0, y: 0, width: 1366, height: 140 } });
		await c.close();
		const rm = await b.newContext({ viewport: { width: 1366, height: 900 }, reducedMotion: 'reduce' });
		const rp = await rm.newPage(); await rp.goto(BASE); await rp.waitForSelector('#abp-bar'); await sleep(800);
		await rp.click('#abp-bar .abp-next'); await sleep(200);
		ok('prefers-reduced-motion: no animation', (await rp.$eval('#abp-bar .abp-item.abp-active', x => getComputedStyle(x).animationName)) === 'none');
		await rm.close();
	}
	// statistics endpoint: still same-origin only, and only for known types
	const forgedT = await p.request.post(BASE + 'index.php?route=' + ROUTE + SEP + 'track', { form: { id: id2, type: 'copy', v: 'b' } });
	await sleep(400);
	ok('statistics endpoint: forged request → 204, not counted', forgedT.status() === 204 && q('select copies_b from oc_abp_message where message_id=' + id2) === '0');

	// ================= admin list screenshot + checks =================
	await p.goto(A(ROUTE));
	const badges = await p.$$eval('.abp-badge', bs => bs.map(x => x.className + ':' + x.textContent.trim()).join(' | '));
	ok('list: free-shipping / coupon / targeting badges', /abp-b-fs/.test(badges) && /SAVE-10/.test(badges) && /abp-b-target/.test(badges), badges.slice(0, 300));
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-list.png`, fullPage: true });
	await p.goto(A(ROUTE, '&tab=settings')); await sleep(500);
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-settings.png`, fullPage: true });
	await p.goto(FORM(idFs)); await sleep(2000);
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-form-fs.png`, fullPage: false });

	// ================= final checks =================
	const log = fs.existsSync(logFile) ? fs.readFileSync(logFile, 'utf8').slice(log0) : '';
	ok('PHP error log: nothing from the module', !/announcement_bar_pro|announcementbarpro|abp_/i.test(log), log.split('\n').filter(l => /abp|announcement/i.test(l)).slice(0, 4).join(' | '));
	ok('storefront: no JS errors', shopErrs.length === 0, [...new Set(shopErrs)].slice(0, 5).join(' | '));
	ok('admin: no JS errors', adminErrs.length === 0, [...new Set(adminErrs)].slice(0, 5).join(' | '));
	console.log('\nOpenCart ' + VER + `: ${R.filter(x => x.startsWith('PASS')).length} passed, ${R.filter(x => x.startsWith('FAIL')).length} failed`);
	await b.close();
})().catch(e => { console.error('ABORT', e.stack); console.log(R.filter(x => x.startsWith('FAIL')).join('\n')); process.exit(2); });
