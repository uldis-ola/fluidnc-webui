// OpenCart 1.5 edition: extra E2E checks (A/B test + statistics, list actions, icon font, footer fallback,
// uninstall / reinstall). Called from abp15-e2e.js with its helpers: module.exports = async (h) => { ... }.
module.exports = async function extra(h) {
	const { p, q, ok, sleep, A, S, BASE, DIR, ROUTE, SHOTS, TAG, add, only, shop, info, visibleIds, setting, settings, check, both } = h;
	const fs = require('fs');

	// ---------------- A/B test with statistics, "Use B" ----------------
	let [e, idAb] = await add(async () => {
		await check('ab_test');
		await p.fill('textarea[name="message_description[1][text_b]"]', 'Variant B text');
	}, 'Variant A text');
	ok('A/B: message with variant B saved', !e && q(`select ab_test from oc_abp_message where message_id=${idAb}`) === '1' && q(`select text_b from oc_abp_message_description where message_id=${idAb} and language_id=1`) === 'Variant B text', e);
	only([idAb]);
	const seen = { a: 0, b: 0 };
	for (let i = 0; i < 8; i++) {
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(1300);
		const v = await pg.evaluate(() => { const vis = [...document.querySelectorAll('#abp-bar .abp-v')].find(x => getComputedStyle(x).display !== 'none'); return vis ? (vis.classList.contains('abp-v-b') ? 'b' : 'a') : ''; });
		if (v) { seen[v]++; }
		await c.close();
	}
	await sleep(800);
	const stats = q(`select concat(views, '|', views_b) from oc_abp_message where message_id=${idAb}`).split('|').map(Number);
	ok('A/B: visitors get variant A or B (8 visitors), views counted per variant', seen.a + seen.b === 8 && seen.a > 0 && seen.b > 0 && stats[0] === seen.a && stats[1] === seen.b, JSON.stringify(seen) + ' db=' + stats.join('/'));
	q(`update oc_abp_message set views=200, clicks=4, views_b=200, clicks_b=20 where message_id=${idAb}`);
	await p.goto(A(ROUTE));
	const row = await p.$eval(`tr[data-message="${idAb}"]`, el => el.innerText.replace(/\s+/g, ' ')).catch(() => '');
	ok('A/B list: A/B badge, CTR per variant, B marked better', /A\/B/.test(row) && /2\.0 %/.test(row) && /10\.0 %/.test(row) && (await p.$(`tr[data-message="${idAb}"] .abp-win`)) !== null, row);
	await p.screenshot({ path: `${SHOTS}/${TAG}-admin-list-ab.png`, fullPage: true });
	await Promise.all([p.waitForResponse(r => /useB/.test(r.url())), p.click(`tr[data-message="${idAb}"] [data-abp-use-b]`)]); await sleep(1500);
	ok('"Use B": B becomes the main text, test off, B counters become the main counters', q(`select concat(m.ab_test, '|', m.views, '|', m.clicks, '|', d.text, '|', d.text_b) from oc_abp_message m join oc_abp_message_description d using (message_id) where message_id=${idAb} and language_id=1`) === '0|200|20|Variant B text|');

	// ---------------- duplicate, reset statistics, delete (list buttons) ----------------
	await p.goto(A(ROUTE));
	const before = Number(q('select count(*) from oc_abp_message'));
	await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.click(`tr[data-message="${idAb}"] .abp-duplicate`)]); await sleep(600);
	const dup = q('select max(message_id) from oc_abp_message');
	ok('duplicate: a switched-off copy with zero statistics', Number(q('select count(*) from oc_abp_message')) === before + 1 && q(`select concat(status, '|', views, '|', clicks) from oc_abp_message where message_id=${dup}`) === '0|0|0' && q(`select text from oc_abp_message_description where message_id=${dup} and language_id=1`) === 'Variant B text');
	await p.goto(A(ROUTE));
	await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.click(`tr[data-message="${idAb}"] .abp-reset`)]); await sleep(600);
	ok('reset statistics (with confirmation)', q(`select concat(views, clicks, copies, views_b, clicks_b) from oc_abp_message where message_id=${idAb}`) === '00000');
	await p.goto(A(ROUTE));
	await p.check(`tr[data-message="${dup}"] input[name="selected[]"]`);
	await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.click('#abp-delete')]); await sleep(600);
	ok('delete selected (with confirmation): message and its texts gone', q(`select count(*) from oc_abp_message where message_id=${dup}`) === '0' && q(`select count(*) from oc_abp_message_description where message_id=${dup}`) === '0' && /success|modified|Success/i.test(await p.innerText('#abp-alerts').catch(() => '')));

	// ---------------- bundled icon font (OpenCart 1.5 has none) ----------------
	[e, idAb] = await add(async () => { await p.fill('#input-icon', 'fa-gift'); }, 'Message with a gift icon');
	only([idAb]);
	{
		const { c, pg } = await shop(); await pg.goto(BASE); await sleep(1500);
		const r = await pg.evaluate(() => { const i = document.querySelector('#abp-bar .abp-ico'); return { css: document.querySelectorAll('link[href*="announcement_bar_pro/fa/css/font-awesome.min.css"]').length, font: i ? getComputedStyle(i).fontFamily : '', w: i ? i.getBoundingClientRect().width : 0, loaded: document.fonts ? [...document.fonts].some(f => /FontAwesome/i.test(f.family) && f.status === 'loaded') : true }; });
		ok('icon font: bundled Font Awesome 4.7 loaded once, the icon renders with it', r.css === 1 && /FontAwesome/i.test(r.font) && r.w > 5 && r.loaded, JSON.stringify(r));
		await pg.screenshot({ path: `${SHOTS}/${TAG}-store-icon-1366.png`, clip: { x: 0, y: 0, width: 1366, height: 90 } });
		await c.close();
	}
	ok('setting "Load the icon font" off saved', (await settings({ fa_load: 0 })) === '' && setting('fa_load') === '0');
	{
		const front = await (await p.request.get(BASE)).text();
		ok('icon font off: no Font Awesome link (theme brings its own)', /abp-ico/.test(front) && !/fa\/css\/font-awesome/.test(front));
	}
	await settings({ fa_load: 1 });
	{
		q(`update oc_abp_message set icon='' where message_id=${idAb}`);
		const front = await (await p.request.get(BASE)).text();
		ok('icon font: not loaded on pages without any icon', /id="abp-bar"/.test(front) && !/fa\/css\/font-awesome/.test(front));
	}

	// ---------------- storefront script without jQuery (vanilla JS) and with OpenCart 1.5's jQuery 1.7 ----------------
	{
		const [, idX] = await add(async () => {}, 'Second rotating message');
		only([idAb, idX]);
		q(`update oc_abp_message set icon='fa-gift' where message_id=${idAb}`);
		const { c, pg } = await shop();
		const errs = []; pg.on('pageerror', x => errs.push(x.message));
		await pg.route(/jquery[^/]*\.js/i, r => r.abort());
		await pg.goto(BASE); await sleep(1500);
		const st = await pg.evaluate(() => ({ jq: typeof window.jQuery, api: !!window.AnnouncementBarPro, active: document.querySelectorAll('#abp-bar .abp-item.abp-active').length, items: document.querySelectorAll('#abp-bar .abp-item').length }));
		await pg.click('#abp-bar .abp-close'); await sleep(500);
		const closed = await pg.evaluate(() => getComputedStyle(document.getElementById('abp-bar')).display === 'none');
		const ours = errs.filter(x => /abp|AnnouncementBar|announcement_bar/i.test(x));
		// The theme's own scripts fail without jQuery ("$ is not defined"): expected here, not counted for the whole run.
		for (const x of errs) { const i = h.shopErrs ? h.shopErrs.indexOf(x) : -1; if (i >= 0) { h.shopErrs.splice(i, 1); } }
		ok('storefront script works without jQuery (all jQuery files blocked): bar started, close works, no errors from the module', st.jq === 'undefined' && st.api && st.active === 1 && st.items === 2 && closed && ours.length === 0, JSON.stringify(st) + ' ' + ours.join(' | '));
		await c.close();
		const s2 = await shop(); await s2.pg.goto(BASE); await sleep(1000);
		const jq = await s2.pg.evaluate(() => window.jQuery && window.jQuery.fn.jquery);
		ok('storefront with OpenCart 1.5\'s own jQuery ' + jq + ': bar started', /^1\.7/.test(jq) && (await s2.pg.$$('#abp-bar .abp-item.abp-active')).length === 1, jq);
		await s2.c.close();
	}

	// ---------------- footer fallback: theme header without <body> ----------------
	{
		const theme = DIR + '/catalog/view/theme/abpnobody/template/common';
		fs.mkdirSync(theme, { recursive: true });
		const hdr = fs.readFileSync(DIR + '/catalog/view/theme/default/template/common/header.tpl', 'utf8');
		fs.writeFileSync(theme + '/header.tpl', hdr.replace(/<body[^>]*>/i, '<!-- no body tag in this test theme -->').replace(/catalog\/view\/theme\/<\?php echo \$this->config->get\('config_template'\); \?>\//g, 'catalog/view/theme/default/'));
		const tpl0 = q("select value from oc_setting where `key`='config_template' and store_id=0");
		q("update oc_setting set value='abpnobody' where `key`='config_template' and store_id=0");
		const front = await (await p.request.get(BASE)).text();
		const pos = front.indexOf('id="abp-bar"'), end = front.lastIndexOf('</body>');
		ok('fallback: theme header without <body> → the bar is placed before </body> (data-fallback=1), marker removed, once', pos > 0 && pos < end && /data-fallback="1"/.test(front) && !/abp-fallback/.test(front) && (front.match(/id="abp-bar"/g) || []).length === 1, 'pos=' + pos + ' end=' + end);
		{
			const { c, pg } = await shop(); await pg.goto(BASE); await sleep(1300);
			const top = await pg.evaluate(() => { const b = document.getElementById('abp-bar'); return b ? Math.round(b.getBoundingClientRect().top + window.scrollY) : -1; });
			ok('fallback: the script moves the top bar to the top of the page', top >= 0 && top < 5, String(top));
			await c.close();
		}
		q(`update oc_setting set value='${tpl0}' where \`key\`='config_template' and store_id=0`);
		const normal = await (await p.request.get(BASE)).text();
		ok('default theme again: bar right after <body>, no marker left', /<body[^>]*>\s*<div[^>]*id="abp-bar"/i.test(normal) && !/abp-fallback/.test(normal));
	}

	// ---------------- JSON / file answers never get the footer marker ----------------
	{
		const r = await p.request.get(S(ROUTE + '/cart') + '&ids=' + idAb);
		ok('cart endpoint: clean JSON (no markup from the hooks)', /^\{"m":/.test(await r.text()), await r.text());
	}

	// ---------------- uninstall / reinstall (messages kept unless "delete data" is on) ----------------
	{
		const n = q('select count(*) from oc_abp_message');
		await p.goto(A('extension/module'));
		const row = p.locator('table.list tbody tr', { hasText: 'Announcement Bar Pro' });
		await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), row.locator('a', { hasText: /Uninstall/ }).first().click()]); await sleep(500);
		const front = await (await p.request.get(BASE)).text();
		ok('uninstall: settings removed, messages kept, storefront without bar (vQmod hooks stay idle)', q("select count(*) from oc_setting where `group`='announcement_bar_pro'") === '0' && q('select count(*) from oc_abp_message') === n && !/abp-bar|announcement_bar_pro\.js/.test(front) && /<\/html>/i.test(front));
		await p.goto(A('extension/module'));
		await Promise.all([p.waitForNavigation({ timeout: 15000 }).catch(() => {}), p.locator('table.list tbody tr', { hasText: 'Announcement Bar Pro' }).locator('a', { hasText: /Install/ }).first().click()]); await sleep(500);
		await p.goto(A(ROUTE));
		ok('reinstall: kept messages, module stays switched off until the shop owner switches it on, schema 8', q('select count(*) from oc_abp_message') === n && setting('status') === '0' && setting('schema') === '8');
		await settings({ status: 1, mode: 'stack', dismissible: 1, color_mode: 'custom' });
		ok('switched on again in Settings', setting('status') === '1');
	}
};
