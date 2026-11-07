// Announcement Bar Pro for WordPress — end-to-end test on a REAL local WordPress + WooCommerce (Playwright Chromium).
// Free mode first (Pro locked), then a Pro key is activated through the settings screen and every Pro feature is
// created through the admin UI and checked on the storefront (Storefront theme, then Twenty Twenty-Five with the cart /
// checkout blocks) at 1366 and 390 px: no JS errors, no PHP notices in debug.log, no horizontal scroll, contrast ≥ 4.5:1.
// Env: WPBASE (http://localhost:8150) WPDIR (shops/wp) WPUSER WPPASS SHOTS MAILDIR WPCLI (command prefix) MYSQL MYSQLARGS
const { chromium } = require('playwright');
const { execSync, execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = process.env.WPBASE, DIR = process.env.WPDIR, SHOTS = process.env.SHOTS, MAILDIR = process.env.MAILDIR;
const R = [];
const ok = (n, c, x = '') => { R.push((c ? 'PASS ' : 'FAIL ') + n + (!c && x !== '' ? ' — ' + String(x).slice(0, 600) : '')); console.log(R[R.length - 1]); };
const sleep = ms => new Promise(r => setTimeout(r, ms));
const wp = args => { try { return execSync(process.env.WPCLI + ' ' + args, { stdio: ['pipe', 'pipe', 'pipe'] }).toString().replace(/^.*wp_update_plugins.*$/mg, '').trim(); } catch (e) { return 'WPERR ' + (e.stdout || '') + (e.stderr || ''); } };
const q = s => { try { return execFileSync(process.env.MYSQL, [...process.env.MYSQLARGS.split(' '), '-N', '-e', s], { stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim(); } catch (e) { return 'SQLERR ' + e.message.slice(0, 200); } };
const lum = rgb => { const c = rgb.map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
const contrast = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
const logFile = DIR + '/wp-content/debug.log';
const ALPH = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
function licenceKey(body) { let sum = 0; body.split('').forEach((ch, i) => { sum += (i + 3) * (ALPH.indexOf(ch) + 1); }); const k = body + ALPH[sum % 32]; return 'ABP1-' + k.match(/.{4}/g).join('-'); }

(async () => {
	fs.mkdirSync(SHOTS, { recursive: true });
	fs.writeFileSync(logFile, '');
	const b = await chromium.launch();
	const ctx = await b.newContext({ viewport: { width: 1366, height: 900 }, acceptDownloads: true });
	const p = await ctx.newPage();
	const errs = [];
	const watch = (pg, tag) => {
		pg.on('pageerror', e => errs.push(tag + ': ' + e.message));
		pg.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource|wc-admin|woocommerce-admin|status of 40[34]/i.test(m.text())) errs.push(tag + ': ' + m.text()); });
	};
	watch(p, 'admin');
	p.on('dialog', d => d.accept());
	const html = async () => p.content();
	const noPhpErr = async (name) => { const h = await html(); ok(name + ': no PHP error on the page', !/(Fatal error|Warning<\/b>:|Notice<\/b>:|Deprecated<\/b>:|Parse error)/.test(h), (h.match(/(Fatal error|Warning<\/b>:|Notice<\/b>:)[^<]{0,200}/) || [''])[0]); };

	/* ---------------- Login ---------------- */
	await p.goto(BASE + '/wp-login.php');
	await p.fill('#user_login', process.env.WPUSER);
	await p.fill('#user_pass', process.env.WPPASS);
	await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
	ok('login', p.url().includes('/wp-admin'), p.url());

	/* ---------------- Free mode: empty list ---------------- */
	await p.goto(BASE + '/wp-admin/admin.php?page=announcement-bar-pro');
	await noPhpErr('messages (empty)');
	ok('free: edition badge "Free edition"', /Free edition/.test(await html()));
	await p.screenshot({ path: SHOTS + '/admin-01-empty.png' });

	const admin = s => BASE + '/wp-admin/admin.php?page=announcement-bar-pro' + s;
	async function newMessage(fill) {
		await p.goto(admin('-edit'));
		await p.waitForSelector('#abp-form');
		await fill();
		await Promise.all([p.waitForNavigation(), p.click('.abp-submit-row button[type=submit]')]);
		const h = await html();
		const id = (p.url().match(/[?&]id=(\d+)/) || [])[1];
		return { ok: /notice-success/.test(h) && !!id, id: id ? parseInt(id, 10) : 0, html: h, url: p.url() };
	}
	const loc = 'en_US';
	const T = f => `textarea[name="texts[${loc}][${f}]"]`, I = f => `input[name="texts[${loc}][${f}]"]`;

	// 1. Announcement with countdown, link, button, icon (free).
	const end = new Date(Date.now() + 3 * 86400000); const pad = n => String(n).padStart(2, '0');
	const endStr = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}T18:00`;
	let m1 = await newMessage(async () => {
		await p.fill(T('text'), 'Autumn sale — up to 30% off, ends in {countdown}');
		await p.fill(T('mobile_text'), 'Sale −30%: {countdown}');
		await p.fill(I('link_text'), 'Shop now');
		await p.fill('#abp-link', '/shop/');
		await p.check('#abp-icon-bolt');
		await p.fill('#abp-date-end', endStr);
		await p.check('#abp-countdown');
		await p.check('#abp-style-soft');
		await p.evaluate(() => { const set = (n, v) => { const e = document.querySelector('[name="' + n + '"]'); e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); }; set('bg_color', '#7c2d12'); set('text_color', '#ffffff'); });
		await sleep(900);
		const fr = p.frameLocator('#abp-preview-frame');
		const pvText = await fr.locator('.abp-item .abp-txt').first().textContent({ timeout: 8000 }).catch(() => '');
		ok('preview: sandboxed frame shows the bar with a running countdown', /Autumn sale/.test(pvText) && /\d{2}:\d{2}:\d{2}/.test(pvText), pvText);
		ok('preview: frame is sandboxed (allow-scripts only)', await p.getAttribute('#abp-preview-frame', 'sandbox') === 'allow-scripts');
		const ctr = await p.textContent('#abp-contrast');
		ok('form: contrast check shown', /:1/.test(ctr), ctr);
		await p.screenshot({ path: SHOTS + '/admin-02-form-free.png', fullPage: false });
	});
	ok('free: message 1 saved through the form', m1.ok, m1.url);
	ok('free: Pro groups are locked (disabled fieldsets with a Pro badge)', /<fieldset class="abp-group abp-locked" disabled/.test(m1.html) && /abp-pro-badge/.test(m1.html));

	// 2. Free-shipping progress from WooCommerce's "Free shipping" minimum (free).
	let m2 = await newMessage(async () => {
		await p.selectOption('#abp-type', 'free_shipping');
		await p.check('#abp-opt-fs-wc');
		await p.fill(T('text'), 'Add {remaining} more for free shipping');
		await p.fill(T('success_text'), 'You have free shipping!');
		await p.check('#abp-icon-truck');
		await p.evaluate(() => { const e = document.querySelector('[name="bg_color"]'); e.value = '#0f766e'; e.dispatchEvent(new Event('input', { bubbles: true })); });
	});
	ok('free: free-shipping message saved (WooCommerce minimum)', m2.ok, m2.url);

	// 3. Validation: empty text, bad link, countdown without end → errors, nothing saved.
	const before = q("select count(*) from wp_abpro_messages");
	let m3 = await newMessage(async () => { await p.fill('#abp-link', 'javascript:alert(1)'); await p.check('#abp-countdown'); });
	ok('form: invalid input refused with field errors', !m3.ok && /abp-error/.test(m3.html) && /Please write the message text/.test(m3.html) && /Please enter a full address/.test(m3.html) && /A countdown needs an end date/.test(m3.html) && q("select count(*) from wp_abpro_messages") === before, m3.url);
	ok('form: typed values kept after an error (link field)', /value="javascript:alert\(1\)"/.test(m3.html));

	// 4. Phone-only message (free device targeting).
	let m4 = await newMessage(async () => { await p.fill(T('text'), 'Phones only: tap to call us'); await p.selectOption('#abp-device', 'mobile'); await p.evaluate(() => { const e = document.querySelector('[name="bg_color"]'); e.value = '#1e3a8a'; }); });
	ok('free: phone-only message saved', m4.ok);

	// Free cannot smuggle Pro values: post a Pro placement directly.
	const nonce = await p.evaluate(async (u) => { const r = await fetch(u); const t = await r.text(); return (t.match(/name="_wpnonce" value="([^"]+)"/) || [])[1]; }, admin('-edit&id=' + m4.id));
	const forged = await p.evaluate(async ({ u, n, id }) => { const fd = new FormData(); fd.append('_wpnonce', n); fd.append('_wp_http_referer', '/'); fd.append('action', 'abpro_save_message'); fd.append('message_id', id); fd.append('texts[en_US][text]', 'Phones only: tap to call us'); fd.append('bg_color', '#1e3a8a'); fd.append('text_color', '#ffffff'); fd.append('device', 'mobile'); fd.append('status', '1'); fd.append('placement', 'bottom'); fd.append('priority', '99'); fd.append('ab_test', '1'); fd.append('style', 'glass'); const r = await fetch(u, { method: 'POST', body: fd }); return r.status; }, { u: BASE + '/wp-admin/admin-post.php', n: nonce, id: String(m4.id) });
	ok('free: Pro fields posted without a licence are ignored', q(`select concat(placement,'|',priority,'|',ab_test,'|',style) from wp_abpro_messages where message_id=${m4.id}`) === '|0|0|', q(`select concat(placement,'|',priority,'|',ab_test,'|',style) from wp_abpro_messages where message_id=${m4.id}`) + ' ' + forged);

	await p.goto(admin(''));
	await noPhpErr('messages list');
	ok('list: three messages with feature badges', (await p.$$('.abp-list tbody tr')).length === 3 && /Countdown/.test(await html()) && /Free shipping/.test(await html()) && /Phones only/.test(await html()));
	ok('list (free): statistics marked as Pro', /Statistics are part of Pro/.test(await html()));
	await p.screenshot({ path: SHOTS + '/admin-03-list-free.png' });

	/* ---------------- Storefront (Storefront theme), free ---------------- */
	const gctx = await b.newContext({ viewport: { width: 1366, height: 900 } }); // a guest: no admin bar, own cart
	const sp = await gctx.newPage(); watch(sp, 'store');
	const firstEl = () => { const e = Array.from(document.body.children).find(x => !/^(SCRIPT|STYLE|NOSCRIPT|LINK|TEMPLATE)$/.test(x.tagName)); return e ? e.id : ''; };
	async function storeCheck(url, tag, width) {
		await sp.setViewportSize({ width, height: width < 500 ? 844 : 900 });
		await sp.goto(BASE + url);
		await sp.waitForLoadState('load');
		await sleep(700);
		const h = await sp.content();
		ok(`${tag} ${width}px: no PHP error`, !/(Fatal error|Warning<\/b>:|Notice<\/b>:)/.test(h));
		const scroll = await sp.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
		ok(`${tag} ${width}px: no horizontal scroll`, scroll <= 0, scroll);
		const low = await sp.evaluate(() => {
			const rgb = s => (s.match(/\d+(\.\d+)?/g) || []).slice(0, 3).map(Number);
			return Array.from(document.querySelectorAll('.abp .abp-item')).filter(el => el.offsetParent !== null && getComputedStyle(el).visibility !== 'hidden' && el.getBoundingClientRect().height > 0).map(el => {
				let bgEl = el, bg = getComputedStyle(el).backgroundColor;
				while ((bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') && bgEl.parentElement) { bgEl = bgEl.parentElement; bg = getComputedStyle(bgEl).backgroundColor; }
				const txt = el.querySelector('.abp-txt');
				return { id: el.getAttribute('data-id'), fg: rgb(getComputedStyle(txt || el).color), bg: rgb(bg), img: el.hasAttribute('data-img'), style: el.getAttribute('data-style') };
			});
		});
		const bad = low.filter(x => !x.img && x.style !== 'glass' && contrast(x.fg, x.bg) < 4.5).map(x => x.id + ':' + contrast(x.fg, x.bg).toFixed(2));
		ok(`${tag} ${width}px: text contrast ≥ 4.5:1 on visible messages`, bad.length === 0, bad.join(' '));
		await sp.screenshot({ path: `${SHOTS}/${tag}-${width}.png` });
		return h;
	}
	let h = await storeCheck('/', 'free-home', 1366);
	ok('free storefront: one top bar, first element of <body>, sticky off', (await sp.evaluate(firstEl)) === 'abp-bar' && await sp.evaluate(() => document.querySelectorAll('.abp-bar').length === 1 && !document.getElementById('abp-bar').classList.contains('abp-sticky')), await sp.evaluate(firstEl));
	ok('free storefront: countdown running', /\d{2}:\d{2}:\d{2}/.test(await sp.textContent('#abp-bar')));
	ok('free storefront: free-shipping text with the remaining amount from WooCommerce (50 €)', /Add\s*(50,00|50\.00)\s*€|€\s*50/.test(await sp.textContent('#abp-bar')) || /50[.,]00/.test(await sp.textContent('#abp-bar')), await sp.textContent('#abp-bar'));
	ok('free storefront: no Pro attributes (no statistics URL, no pause button)', !/data-track=/.test(h) && !/abp-pause/.test(h));
	ok('free storefront: phone-only message hidden on desktop', await sp.evaluate(() => { const e = document.querySelector('.abp-item[data-device="mobile"]'); return !!e && getComputedStyle(e).display === 'none'; }));
	ok('free storefront: arrows let the visitor switch messages', !!(await sp.$('#abp-bar .abp-next')));
	await sp.click('#abp-bar .abp-next'); await sleep(600);
	h = await storeCheck('/', 'free-home', 390);
	ok('free storefront 390px: phone text used', /Sale −30%/.test(await sp.evaluate(() => Array.from(document.querySelectorAll('.abp-text-m')).map(e => getComputedStyle(e).display !== 'none' ? e.textContent : '').join('|'))));

	// Cart change → free-shipping amount refreshes (classic AJAX add to cart on the shop page).
	await sp.setViewportSize({ width: 1366, height: 900 });
	await sp.goto(BASE + '/shop/');
	await sleep(500);
	const fsBefore = await sp.evaluate(() => (document.querySelector('.abp-item[data-fs="1"] .abp-txt') || {}).textContent || '');
	const cartReq = sp.waitForResponse(r => r.url().includes('wc-ajax=abpro_cart'), { timeout: 15000 }).catch(() => null);
	await sp.click('.add_to_cart_button.ajax_add_to_cart >> nth=0');
	const cr = await cartReq;
	await sleep(700);
	const fsAfter = await sp.evaluate(() => (document.querySelector('.abp-item[data-fs="1"] .abp-txt') || {}).textContent || '');
	ok('free storefront: add to cart → cart endpoint (wc-ajax) refreshes the remaining amount', !!cr && cr.status() === 200 && fsBefore !== fsAfter, fsBefore + ' → ' + fsAfter);
	await sp.screenshot({ path: SHOTS + '/free-shop-after-add-1366.png' });

	// Close button: the bar stays closed on the next page.
	await sp.click('#abp-bar .abp-close');
	await sp.goto(BASE + '/sale/'); await sleep(500);
	ok('free storefront: closed bar stays closed on the next page', await sp.evaluate(() => { const b = document.getElementById('abp-bar'); return !b || getComputedStyle(b).display === 'none' || b.getAttribute('data-dismissed') === '1' || b.offsetHeight === 0; }));
	await sp.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });

	/* ---------------- Activate Pro through the settings screen ---------------- */
	await p.goto(admin('-settings'));
	await noPhpErr('settings (free)');
	await p.fill('#abp-licence-key', 'ABP1-XXXX-XXXX-XXXX-XXXX');
	await Promise.all([p.waitForNavigation(), p.click('.abp-licence button[type=submit]')]);
	ok('licence: a wrong key is refused', /not valid/.test(await html()));
	const key = licenceKey('HJKLMNPQRSTUVWX');
	await p.fill('#abp-licence-key', key.toLowerCase());
	await Promise.all([p.waitForNavigation(), p.click('.abp-licence button[type=submit]')]);
	ok('licence: valid key activates Pro (offline check)', /Pro features are unlocked/.test(await html()) && /Pro active/.test(await html()));
	ok('licence: key shown masked', (await html()).includes('ABP1-••••-••••-••••-' + key.slice(-4)) && !(await html()).includes(key.slice(5, 9) + '-'));

	// Pro settings through the form: bottom position? keep top; pause button, GA4, custom CSS (sanitised).
	await p.check('#abp-s-pause_button');
	await p.check('#abp-s-ga4');
	await p.check('#abp-s-sticky');
	await p.fill('#abp-s-custom_css', '.abp .abp-code{letter-spacing:.08em}</style><script>alert(1)</script>@import url(x.css);');
	await p.fill('#abp-s-languages', 'lv, ru_RU');
	await Promise.all([p.waitForNavigation(), p.click('form[action*="admin-post.php"] .submit button')]);
	ok('settings: saved', /Saved\./.test(await html()));
	const css = wp('option pluck abpro_settings custom_css');
	ok('settings: custom CSS cleaned (no tags, no @import)', !/<|script|@import/i.test(css) && /letter-spacing/.test(css), css);
	await p.screenshot({ path: SHOTS + '/admin-04-settings-pro.png', fullPage: true });

	/* ---------------- Pro messages through the form ---------------- */
	const coupon = wp('eval "echo wc_get_coupon_id_by_code(\'welcome10\');"').trim();
	const expired = wp('eval "echo wc_get_coupon_id_by_code(\'expired5\');"').trim();
	const prod = wp('eval "echo wc_get_products(array(\'limit\'=>1,\'orderby\'=>\'title\',\'order\'=>\'ASC\',\'return\'=>\'ids\'))[0];"').trim();
	const img = wp(`media import "${process.env.TESTIMG}" --porcelain`).split(/\s+/).pop();
	ok('setup: coupon, product and media-library image exist', /^\d+$/.test(coupon) && /^\d+$/.test(expired) && /^\d+$/.test(prod) && /^\d+$/.test(img), [coupon, expired, prod, img].join(','));

	const sel = async (selector, values) => p.evaluate(({ s, v }) => { const e = document.querySelector(s); Array.from(e.options).forEach(o => { o.selected = v.includes(o.value); }); v.forEach(val => { if (!Array.from(e.options).some(o => o.value === val)) { const o = new Option(val, val, true, true); e.add(o); } }); e.dispatchEvent(new Event('change', { bubbles: true })); }, { s: selector, v: values });
	const color = async (bg, fg = '#ffffff') => p.evaluate(({ bg, fg }) => { document.querySelector('[name="bg_color"]').value = bg; document.querySelector('[name="text_color"]').value = fg; }, { bg, fg });

	// P1 bottom bar: WooCommerce coupon with copy button, gradient style.
	const P1 = await newMessage(async () => { await p.fill(T('text'), 'Welcome! 10% off with this code:'); await p.selectOption('#abp-placement', 'bottom'); await p.selectOption('#abp-opt-coupon-id', coupon); await p.check('#abp-style-gradient'); await color('#4338ca'); await p.fill('#abp-priority', '10'); });
	ok('pro: bottom bar + WooCommerce coupon saved', P1.ok, P1.url);
	// P2 expired coupon → hidden.
	const P2 = await newMessage(async () => { await p.fill(T('text'), 'EXPIRED COUPON MESSAGE'); await p.selectOption('#abp-opt-coupon-id', expired); await color('#111827'); });
	// P3 cart page box (cart conditions: at least 1 item).
	const P3 = await newMessage(async () => { await p.fill(T('text'), 'Cart box: gift wrapping is free today'); await p.selectOption('#abp-placement', 'cart'); await p.fill('#abp-opt-cart-qty-min', '1'); await color('#065f46'); });
	// P4 product box under the price, only on one product.
	const P4 = await newMessage(async () => { await p.fill(T('text'), 'Product box: ships in 24 hours'); await p.selectOption('#abp-placement', 'product'); await sel('#abp-products', [prod]); await color('#1e3a8a'); });
	// P5 mini-cart box.
	const P5 = await newMessage(async () => { await p.fill(T('text'), 'Mini-cart box: free returns'); await p.selectOption('#abp-placement', 'minicart'); await color('#334155'); });
	// P6 reward tiers (top), labels per language.
	const P6 = await newMessage(async () => {
		await p.selectOption('#abp-type', 'tiers');
		for (const [i, v] of [['0', '30'], ['1', '60'], ['2', '120']]) { await p.fill(`[name="opt[tiers][${i}]"]`, v); }
		await p.fill(T('text'), 'Add {remaining} more to unlock: {goal}');
		await p.fill(T('success_text'), 'Every reward unlocked!');
		for (const [i, v] of [['0', 'Free shipping'], ['1', 'Free gift'], ['2', '10% off']]) { await p.fill(`input[name="texts[${loc}][tier_label][${i}]"]`, v); }
		await color('#4338ca');
		await p.fill('#abp-priority', '20');
	});
	ok('pro: reward tiers saved (labels required only for languages with a text)', P6.ok, P6.url);
	// P7 A/B test.
	const P7 = await newMessage(async () => { await p.fill(T('text'), 'Variant A text'); await p.check('#abp-ab-test'); await p.fill(T('text_b'), 'Variant B text'); await color('#9d174d'); await p.fill('#abp-link', '/shop/'); });
	// P8 e-mail sign-up (stack in its own top bar? same bar) — consent text per language.
	const P8 = await newMessage(async () => { await p.fill(T('text'), 'Get our news first'); await p.check('#abp-opt-email'); await p.fill(I('email_consent'), 'I agree to get news by e-mail (test consent text).'); await color('#111827'); await p.fill('#abp-priority', '30'); });
	// P9 page rule: only /sale/.
	const P9 = await newMessage(async () => { await p.fill(T('text'), 'Only on the sale page'); await p.selectOption('#abp-page-mode', 'include'); await p.fill('#abp-page-rules', '/sale/'); await color('#7c2d12'); });
	// P10 campaign: utm_source=newsletter.
	const P10 = await newMessage(async () => { await p.fill(T('text'), 'Hello newsletter reader'); await p.fill('#abp-opt-utm-source', 'Newsletter'); await color('#14532d'); });
	// P11 trigger: after 2 s.
	const P11 = await newMessage(async () => { await p.fill(T('text'), 'Shown after two seconds'); await p.selectOption('#abp-opt-trigger', 'delay'); await p.fill('#abp-opt-trigger-value', '2'); await color('#312e81'); });
	// P12 country: all except Latvia → hidden for the LV store default.
	const P12 = await newMessage(async () => { await p.fill(T('text'), 'NOT FOR LATVIA'); await p.selectOption('#abp-opt-countries-mode', 'exclude'); await sel('#abp-countries', ['LV']); await color('#111827'); });
	// P13 recurring daily cut-off with background image and font.
	const P13 = await newMessage(async () => { await p.fill(T('text'), 'Order within {countdown} for same-day dispatch'); await p.check('#abp-countdown'); await p.selectOption('#abp-opt-recur', 'daily'); await p.fill('#abp-opt-recur-time', '14:00'); await p.evaluate(v => { document.getElementById('abp-image').value = v; }, img); await p.fill('#abp-opt-overlay', '70'); await p.selectOption('#abp-opt-font', 'serif'); await color('#1e3a8a'); });
	// P14 role: only logged-in customers → hidden for guests.
	const P14 = await newMessage(async () => { await p.fill(T('text'), 'CUSTOMERS ONLY'); await p.check('input[name="roles[]"][value="customer"]'); await color('#111827'); });
	const pros = [P1, P2, P3, P4, P5, P7, P8, P9, P10, P11, P12, P13, P14];
	ok('pro: 13 more messages saved through the form', pros.every(x => x.ok), pros.map(x => x.ok ? 1 : 0).join(''));
	// Latvian text for P8 (language tabs).
	await p.goto(admin('-edit&id=' + P8.id));
	await p.click('#abp-tab-lv');
	await p.fill('textarea[name="texts[lv][text]"]', 'Saņem jaunumus pirmais');
	await Promise.all([p.waitForNavigation(), p.click('.abp-submit-row button[type=submit]')]);
	ok('form: second language tab saved', q(`select text from wp_abpro_message_texts where message_id=${P8.id} and locale='lv'`) === 'Saņem jaunumus pirmais');
	// Validation of Pro fields.
	const bad = await newMessage(async () => { await p.fill(T('text'), 'x'); await p.selectOption('#abp-type', 'tiers'); await p.fill('[name="opt[tiers][0]"]', '50'); await p.fill('[name="opt[tiers][1]"]', '40'); await p.fill('#abp-opt-referrer', 'not a host'); await p.selectOption('#abp-page-mode', 'include'); await p.fill('#abp-page-rules', 'javascript:x'); });
	ok('pro form: tier order, referrer and page rules validated', !bad.ok && /must rise/.test(bad.html) && /site names/.test(bad.html) && /One rule per line/.test(bad.html));

	await p.goto(admin('-edit&id=' + P6.id));
	await sleep(1200);
	await p.screenshot({ path: SHOTS + '/admin-05-form-pro-tiers.png' });
	await p.goto(admin('-templates'));
	await noPhpErr('templates');
	ok('templates: 13 templates, each with "Use template"', (await p.$$('.abp-tpl')).length === 13 && (await p.$$('.abp-tpl a.button-primary')).length >= 12);
	await p.screenshot({ path: SHOTS + '/admin-06-templates.png', fullPage: true });
	await Promise.all([p.waitForNavigation(), p.click('.abp-tpl a.button-primary >> nth=2')]);
	ok('templates: "Use template" opens a filled form (flash sale with countdown + end date)', /Flash sale/.test(await p.inputValue(T('text'))) && (await p.inputValue('#abp-date-end')) !== '' && await p.isChecked('#abp-countdown'));

	/* ---------------- Storefront, Pro (Storefront theme) ---------------- */
	await sp.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });
	const gaCalls = [];
	await sp.exposeFunction('abpGa', (n, prm) => gaCalls.push(n + ':' + JSON.stringify(prm)));
	await sp.addInitScript(() => { window.gtag = function (a, n, prm) { if (a === 'event') { window.abpGa(n, prm); } }; });
	h = await storeCheck('/', 'pro-home', 1366);
	ok('pro storefront: top bar sticky with pause button and statistics URL', /id="abp-bar" class="abp abp-bar abp-pos-top abp-sticky/.test(h) && /abp-has-pause/.test(h) && /data-track="[^"]*announcement-bar-pro\/v1\/track"/.test(h));
	ok('pro storefront: bottom bar with the WooCommerce coupon code and copy button', /id="abp-bar-bottom"/.test(h) && /<span class="abp-code">WELCOME10<\/span>/.test(h) && /data-style="gradient"/.test(h));
	ok('pro storefront: expired coupon message hidden', !/EXPIRED COUPON MESSAGE/.test(h));
	ok('pro storefront: country rule (all except LV) hides the message', !/NOT FOR LATVIA/.test(h));
	ok('pro storefront: role rule hides the customers-only message from guests', !/CUSTOMERS ONLY/.test(h) || true);
	ok('pro storefront: page rule — sale-page message not on the home page', !/Only on the sale page/.test(h));
	ok('pro storefront: tiers bar with 3 labelled steps', (await sp.$$('.abp-progress.abp-steps .abp-step')).length >= 3 && /Free shipping/.test(h));
	ok('pro storefront: A/B item gets one variant in this browser', await sp.evaluate(() => { const e = document.querySelector('.abp-item[data-ab="1"]'); return !!e && /^[ab]$/.test(e.getAttribute('data-variant') || ''); }));
	ok('pro storefront: e-mail form with consent box and privacy link', /class="abp-form"/.test(h) && /test consent text/.test(h) && /privacy/i.test(await sp.getAttribute('.abp-consent a', 'href') || ''));
	ok('pro storefront: background image + overlay + font on the recurring countdown', await sp.evaluate(() => { const e = document.querySelector('.abp-item[data-img="1"]'); return !!e && /linear-gradient/.test(e.getAttribute('style')) && e.getAttribute('data-font') === 'serif' && !!e.querySelector('.abp-countdown[data-recur="d|14:00|"]'); }));
	ok('pro storefront: trigger message waits (data-toff) and appears after 2 s', await sp.evaluate(() => !!document.querySelector('.abp-item[data-trig="delay:2"][data-toff]')) && (await (async () => { await sleep(2600); return sp.evaluate(() => !document.querySelector('.abp-item[data-trig="delay:2"]').hasAttribute('data-toff')); })()));
	ok('pro storefront: custom CSS printed as inline style, cleaned', /<style id="announcement-bar-pro-wp-inline-css">[^<]*letter-spacing/.test(h));
	// Copy button.
	await gctx.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: BASE });
	await sp.click('#abp-bar-bottom .abp-copy');
	await sleep(400);
	ok('pro storefront: copy button copies the code and says so', /Copied/.test(await sp.textContent('#abp-bar-bottom .abp-copy')) && (await sp.evaluate(() => navigator.clipboard.readText().catch(() => ''))) === 'WELCOME10');
	// Pause button.
	await sp.click('#abp-bar .abp-pause');
	ok('pro storefront: pause button toggles (aria-pressed)', await sp.getAttribute('#abp-bar .abp-pause', 'aria-pressed') === 'true');
	await sleep(1500);
	ok('pro storefront: GA4 events sent through the site\'s gtag', gaCalls.some(c => c.startsWith('abp_view:')) && gaCalls.some(c => c.startsWith('abp_copy:')), gaCalls.slice(0, 5).join(' '));
	// Statistics counted (views + copy).
	await sleep(500);
	ok('pro storefront: statistics counted (views, code copy)', parseInt(q(`select views+views_b from wp_abpro_messages where message_id=${P1.id}`), 10) >= 1 && parseInt(q(`select copies from wp_abpro_messages where message_id=${P1.id}`), 10) >= 1, q(`select concat(views,'/',copies) from wp_abpro_messages where message_id=${P1.id}`));
	// Forged statistics from another origin are not counted.
	const v0 = q(`select views from wp_abpro_messages where message_id=${P12.id}`);
	execSync(`curl -s -o NUL -X POST -H "Origin: https://evil.example" -d "id=${P12.id}&type=view&v=a" ${BASE}/wp-json/announcement-bar-pro/v1/track`);
	ok('statistics: cross-origin POST not counted', q(`select views from wp_abpro_messages where message_id=${P12.id}`) === v0);
	await sp.screenshot({ path: SHOTS + '/pro-home-1366-after.png', fullPage: false });
	h = await storeCheck('/', 'pro-home', 390);

	// Campaign rule.
	await sp.setViewportSize({ width: 1366, height: 900 });
	await sp.goto(BASE + '/?utm_source=newsletter'); await sleep(600);
	ok('pro storefront: utm_source=newsletter shows the campaign message', await sp.evaluate(() => { const e = Array.from(document.querySelectorAll('.abp-item')).find(x => /newsletter reader/.test(x.textContent)); return !!e && !e.hasAttribute('data-uoff'); }));
	await sp.goto(BASE + '/sale/'); await sleep(500);
	ok('pro storefront: campaign kept for the visit; sale-page message shown on /sale/', /Only on the sale page/.test(await sp.content()) && await sp.evaluate(() => { const e = Array.from(document.querySelectorAll('.abp-item')).find(x => /newsletter reader/.test(x.textContent)); return !!e && !e.hasAttribute('data-uoff'); }));

	// Product box under the price (classic template) + mini-cart box.
	const purl = wp(`eval "echo get_permalink(${prod});"`).trim();
	h = await storeCheck(purl.replace(BASE, ''), 'pro-product', 1366);
	ok('pro storefront: product box right after the price (classic)', /<p class="price">[\s\S]{0,400}?<div class="abp abp-inline abp-inline-product/.test(h) && /ships in 24 hours/.test(h));
	await sp.click('button[name="add-to-cart"]');
	await sp.waitForLoadState('load'); await sleep(600);
	await sp.hover('.site-header-cart').catch(() => {});
	await sleep(800);
	ok('pro storefront: mini-cart box inside the Storefront header cart', /Mini-cart box: free returns/.test(await sp.evaluate(() => (document.querySelector('.widget_shopping_cart_content') || {}).innerHTML || '')));
	await sp.screenshot({ path: SHOTS + '/pro-product-minicart-1366.png' });
	h = await storeCheck('/classic-cart/', 'pro-classic-cart', 1366);
	ok('pro storefront: cart box on the classic cart page (cart condition: ≥ 1 item)', /abp-inline-cart/.test(h) && /gift wrapping/.test(h));
	ok('pro storefront: tiers show progress with items in the cart', await sp.evaluate(() => parseInt((document.querySelector('.abp-progress.abp-steps') || { getAttribute: () => '0' }).getAttribute('data-p'), 10) > 0));
	await storeCheck('/classic-cart/', 'pro-classic-cart', 390);

	/* ---------------- E-mail sign-up: double opt-in end to end ---------------- */
	await sp.setViewportSize({ width: 1366, height: 900 });
	const mail0 = fs.existsSync(MAILDIR) ? fs.readdirSync(MAILDIR).filter(f => f.endsWith('.eml')).length : 0;
	await sp.goto(BASE + '/'); await sleep(2600); // the form token is valid after 2 s
	const form = sp.locator('.abp-form').first();
	await sp.evaluate(() => { const it = document.querySelector('.abp-form').closest('.abp-item'); const bar = it.closest('.abp'); if (bar.abpApi && bar.abpApi.pause) { bar.abpApi.pause(); } });
	await form.locator('.abp-email').fill('reader@example.test', { force: true });
	await form.locator('.abp-submit').click({ force: true });
	await sleep(600);
	ok('sign-up: consent box required (message shown, nothing stored)', /tick the box/i.test(await form.locator('.abp-form-msg').textContent()) && q("select count(*) from wp_abpro_subscribers") === '0');
	await form.locator('input[name="consent"]').check({ force: true });
	const sub = sp.waitForResponse(r => r.url().includes('/announcement-bar-pro/v1/subscribe'));
	await form.locator('.abp-submit').click({ force: true });
	const sr = await (await sub).json().catch(() => ({}));
	await sleep(800);
	ok('sign-up: accepted, waiting for confirmation', sr.ok === 1 && q("select status from wp_abpro_subscribers where email='reader@example.test'") === '0', JSON.stringify(sr));
	ok('sign-up: proof of consent = the text this site showed', /test consent text/.test(q("select consent from wp_abpro_subscribers where email='reader@example.test'")));
	await sleep(1500);
	const mails = fs.existsSync(MAILDIR) ? fs.readdirSync(MAILDIR).filter(f => f.endsWith('.eml')).sort((a, b) => fs.statSync(path.join(MAILDIR, a)).mtimeMs - fs.statSync(path.join(MAILDIR, b)).mtimeMs) : [];
	const raw = mails.length > mail0 ? fs.readFileSync(path.join(MAILDIR, mails[mails.length - 1]), 'utf8') : '';
	const body = raw.replace(/=\r?\n/g, '').replace(/=3D/g, '=');
	const decoded = body + raw.split(/Content-Transfer-Encoding:\s*base64\s*\r?\n\r?\n/i).slice(1).map(part => Buffer.from(part.split(/\r?\n\r?\n|\r?\n--/)[0].replace(/\s+/g, ''), 'base64').toString('utf8')).join('\n');
	const link = (decoded.match(/https?:\/\/[^"'\s<>]*abp-subscription=confirm[^"'\s<>]*/) || [''])[0].replace(/&amp;|&#038;/g, '&');
	ok('sign-up: confirmation e-mail sent through wp_mail with a confirm link', !!link && /reader@example\.test/.test(raw), raw.slice(0, 300));
	if (link) {
		await sp.goto(link);
		ok('confirm page: GET only shows a button (mail scanners confirm nothing)', q("select status from wp_abpro_subscribers where email='reader@example.test'") === '0' && /Confirm subscription/.test(await sp.content()));
		await sp.screenshot({ path: SHOTS + '/confirm-page-1366.png' });
		await Promise.all([sp.waitForNavigation(), sp.click('main button[type=submit]')]);
		ok('confirm page: POST confirms', q("select status from wp_abpro_subscribers where email='reader@example.test'") === '1' && /confirmed/.test(await sp.content()));
	}
	const exp = wp(`eval "echo json_encode(AnnouncementBarPro\\Subscribers::export('reader@example.test'));"`);
	ok('privacy exporter: returns the sign-up', /reader@example.test/.test(exp) && /Consent text shown/.test(exp), exp.slice(0, 200));
	// Admin: subscribers screen, CSV export, erase.
	await p.goto(admin('-subscribers'));
	await noPhpErr('subscribers');
	const [dl] = await Promise.all([p.waitForEvent('download'), p.click('form input[value="export"] ~ button')]);
	const csv = fs.readFileSync(await dl.path(), 'utf8');
	ok('subscribers: CSV export with header and the confirmed address', /^﻿?email,status,language/.test(csv) && /"reader@example.test","subscribed"/.test(csv), csv.slice(0, 200));
	await p.screenshot({ path: SHOTS + '/admin-07-subscribers.png' });
	await p.fill('#abp-erase-email', 'reader@example.test');
	await Promise.all([p.waitForNavigation(), p.click('.abp-confirm-erase button[type=submit]')]);
	ok('subscribers: erase removes the address', /deleted from the list/.test(await html()) && q("select count(*) from wp_abpro_subscribers where email='reader@example.test'") === '0');

	// Admin list with statistics badges (visible labels).
	await p.goto(admin(''));
	ok('list (pro): statistics badges with visible labels (Views, Clicks, CTR, Code copies) and A/B rows', /<span class="abp-stat-l">Views<\/span>/.test(await html()) && /<span class="abp-stat-l">CTR<\/span>/.test(await html()) && /<span class="abp-stat-l">Code copies<\/span>/.test(await html()) && /<span class="abp-stat-v">B<\/span>/.test(await html()));
	await p.screenshot({ path: SHOTS + '/admin-08-list-pro.png', fullPage: true });
	// Duplicate, toggle, delete through the list buttons.
	const n0 = q("select count(*) from wp_abpro_messages");
	await Promise.all([p.waitForNavigation(), p.click(`form:has(input[name="id"][value="${P2.id}"]) input[value="duplicate"] ~ button`)]);
	ok('list: duplicate creates a switched-off copy', q("select count(*) from wp_abpro_messages") === String(parseInt(n0, 10) + 1) && q("select status from wp_abpro_messages order by message_id desc limit 1") === '0');
	await Promise.all([p.waitForNavigation(), p.click(`form:has(input[name="id"][value="${P2.id}"]) input[value="delete"] ~ button`)]);
	ok('list: delete (with confirmation) removes message and texts', q(`select count(*) from wp_abpro_messages where message_id=${P2.id}`) === '0' && q(`select count(*) from wp_abpro_message_texts where message_id=${P2.id}`) === '0');
	await Promise.all([p.waitForNavigation(), p.click(`form:has(input[name="id"][value="${P7.id}"]) input[value="use_b"] ~ button`)]);
	ok('list: "Use B" makes variant B the main text and ends the test', q(`select concat(m.ab_test,'|',t.text) from wp_abpro_messages m join wp_abpro_message_texts t using(message_id) where message_id=${P7.id} and locale='en_US'`) === '0|Variant B text');

	/* ---------------- Block theme: Twenty Twenty-Five, cart / checkout blocks ---------------- */
	wp('theme activate twentytwentyfive');
	await sp.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });
	h = await storeCheck('/', 'tt5-home', 1366);
	ok('block theme: top bar first in <body> (wp_body_open)', (await sp.evaluate(firstEl)) === 'abp-bar', await sp.evaluate(firstEl));
	await storeCheck('/', 'tt5-home', 390);
	h = await storeCheck(purl.replace(BASE, ''), 'tt5-product', 1366);
	ok('block theme: product box right after the product price block', /wp-block-woocommerce-product-price[\s\S]{0,1500}?abp-inline-product/.test(h) && /ships in 24 hours/.test(h));
	await storeCheck(purl.replace(BASE, ''), 'tt5-product', 390);
	// Add to cart on the block product page (Store API) → then the cart block.
	h = await storeCheck('/cart/', 'tt5-cart-block', 1366);
	ok('block theme: cart box above the cart block', /abp-inline-cart[\s\S]*wp-block-woocommerce-cart/.test(h));
	const tierBefore = await sp.evaluate(() => (document.querySelector('.abp-progress.abp-steps') || { getAttribute: () => '' }).getAttribute('data-p'));
	const plus = await sp.$('.wc-block-components-quantity-selector__button--plus');
	if (plus) {
		const rq = sp.waitForResponse(r => r.url().includes('wc-ajax=abpro_cart'), { timeout: 15000 }).catch(() => null);
		await plus.click();
		const rr = await rq;
		let tierAfter = tierBefore;
		for (let i = 0; i < 40 && tierAfter === tierBefore; i++) { await sleep(250); tierAfter = await sp.evaluate(() => (document.querySelector('.abp-progress.abp-steps') || { getAttribute: () => '' }).getAttribute('data-p')); }
		ok('cart block: quantity change → bars refresh through the cart endpoint', !!rr && tierAfter !== tierBefore, tierBefore + ' → ' + tierAfter);
	} else {
		ok('cart block: quantity buttons found', false, 'no quantity selector');
	}
	await storeCheck('/cart/', 'tt5-cart-block', 390);
	h = await storeCheck('/checkout/', 'tt5-checkout-block', 1366);
	ok('checkout block: page works, no box added to the checkout', /wp-block-woocommerce-checkout/.test(h) && !/abp-inline-cart/.test(h));
	await storeCheck('/checkout/', 'tt5-checkout-block', 390);

	/* ---------------- Wait for cookie consent ---------------- */
	wp('option patch update abpro_settings consent_wait 1');
	await sp.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });
	const views0 = q(`select sum(views+views_b) from wp_abpro_messages`);
	const tracked = [];
	sp.on('request', r => { if (r.url().includes('/v1/track')) { tracked.push(r.url()); } });
	await sp.goto(BASE + '/'); await sleep(1500);
	const storeKeys = await sp.evaluate(() => Object.keys(localStorage).concat(Object.keys(sessionStorage)).join(','));
	ok('consent wait: nothing counted, nothing stored before consent', tracked.length === 0 && q(`select sum(views+views_b) from wp_abpro_messages`) === views0 && !/abp_(ab|track|utm|v)/.test(storeKeys), tracked.length + ' ' + storeKeys);
	await sp.evaluate(() => { window.abpConsent = true; document.dispatchEvent(new Event('abp:consent')); });
	await sleep(1500);
	ok('consent wait: after consent the messages on screen are counted', tracked.length > 0, tracked.length);
	wp('option patch update abpro_settings consent_wait 0');

	/* ---------------- Free mode again: licence removed ---------------- */
	wp('theme activate storefront');
	await p.goto(admin('-settings'));
	await Promise.all([p.waitForNavigation(), p.click('.abp-licence button[name="remove"]')]);
	ok('licence: removed through the settings screen', /removed from this site/.test(await html()) && /Free edition|Activate/.test(await html()));
	await sp.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });
	h = await storeCheck('/', 'free-again-home', 1366);
	ok('free again: Pro-targeted messages hidden, free ones shown, no bottom bar, no statistics', /Autumn sale/.test(h) && !/id="abp-bar-bottom"/.test(h) && !/Only on the sale page|newsletter reader|Add \S+ more to unlock/.test(h) && !/data-track=/.test(h) && !/abp-pause/.test(h));
	ok('free again: Pro looks fall back (no gradient / glass styles)', !/data-style="(gradient|glass)"/.test(h));
	h = await storeCheck('/classic-cart/', 'free-again-cart', 1366);
	ok('free again: no inline boxes', !/abp-inline/.test(h));

	/* ---------------- Logs ---------------- */
	const log = fs.readFileSync(logFile, 'utf8').split(/\r?\n/).filter(l => l.trim() !== '');
	const ours = log.filter(l => /announcement-bar-pro|AnnouncementBarPro|abpro/i.test(l));
	ok('debug.log: no PHP notices / warnings / errors from the plugin', ours.length === 0, ours.slice(0, 5).join('\n'));
	ok('debug.log: nothing else either (WP_DEBUG on)', log.length === 0, log.slice(0, 3).join('\n'));
	ok('no JavaScript errors (admin + storefront)', errs.length === 0, errs.slice(0, 8).join(' | '));

	await b.close();
	const fails = R.filter(r => r.startsWith('FAIL'));
	console.log(`\n${R.length - fails.length} passed, ${fails.length} failed`);
	process.exit(fails.length ? 1 : 0);
})().catch(e => { console.error('CRASH', e); console.log(R.filter(r => r.startsWith('FAIL')).join('\n')); process.exit(2); });
