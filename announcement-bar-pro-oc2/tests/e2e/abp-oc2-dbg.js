// abp-oc2-dbg.js <base> <route,route,...> [shot] — log in to an OpenCart 2.x admin, open routes, print the page text.
const { chromium } = require('playwright');
(async () => {
	const B = process.argv[2], routes = process.argv[3];
	const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1366, height: 900 } });
	p.on('pageerror', e => console.log('JSERR', e.message));
	await p.goto(B + 'admin/'); await p.fill('#input-username', 'admin'); await p.fill('#input-password', 'admin123');
	await p.click('button[type=submit]'); await p.waitForURL(/token=/);
	const tok = new URL(p.url()).searchParams.get('token');
	for (const r of routes.split(',')) {
		await p.goto(B + 'admin/index.php?route=' + r + '&token=' + tok);
		console.log('== ' + r + '\n' + (await p.innerText('body')).slice(0, 600).replace(/\s+/g, ' '));
	}
	await p.screenshot({ path: process.argv[4] || 'C:/Users/Admin/abp-testenv/shots/oc2/dbg.png', fullPage: true });
	await b.close();
})();
