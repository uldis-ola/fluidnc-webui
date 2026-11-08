// abp-oc2-raw.js <base> <route> — raw admin response (status, length, start)
const { chromium } = require('playwright');
(async () => {
	const B = process.argv[2];
	const b = await chromium.launch(); const p = await b.newPage();
	await p.goto(B + 'admin/'); await p.fill('#input-username', 'admin'); await p.fill('#input-password', 'admin123');
	await p.click('button[type=submit]'); await p.waitForURL(/token=/);
	const tok = new URL(p.url()).searchParams.get('token');
	const r = await p.request.get(B + 'admin/index.php?route=' + process.argv[3] + '&token=' + tok, { maxRedirects: 0 });
	const t = await r.text();
	console.log(r.status(), r.headers()['location'] || '', t.length, t.slice(0, 300).replace(/\s+/g, ' '));
	await b.close();
})();
