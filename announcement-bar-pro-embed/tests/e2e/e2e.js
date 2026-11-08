/* E2E of the embed on local static pages (never a live site). Needs Playwright (chromium):
     NODE_PATH=<folder with playwright> node tests/e2e/e2e.js
   Screenshots: ABP_SHOTS (default C:\Users\Admin\abp-testenv\shots\embed). Run build.sh first. */
'use strict';
var path = require('path'), fs = require('fs');
var chromium = require('playwright').chromium;
var srv = require('./server.js');
var PORT = 8766, BASE = 'http://127.0.0.1:' + PORT + '/tests/e2e/pages/';
var SHOTS = process.env.ABP_SHOTS || 'C:\\Users\\Admin\\abp-testenv\\shots\\embed';
var ROOT = path.resolve(__dirname, '../..');
fs.mkdirSync(SHOTS, { recursive: true });
var R = [];
function ok(name, cond, extra) { R.push((cond ? 'PASS ' : 'FAIL ') + name + (cond || extra === undefined ? '' : ' — ' + (typeof extra === 'string' ? extra : JSON.stringify(extra)))); }
var sleep = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };

// Contrast of every visible message text and button against what is behind it (gradient stops; glass over white and black).
var CONTRAST = function () {
  function rgb(s) { var m = /rgba?\(([^)]+)\)/.exec(s || ''); if (!m) { return null; } var p = m[1].split(/[\s,\/]+/).filter(Boolean).map(parseFloat); return { c: p.slice(0, 3), a: p.length > 3 ? p[3] : 1 }; }
  function lum(c) { var x = c.map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * x[0] + 0.7152 * x[1] + 0.0722 * x[2]; }
  function ratio(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
  function over(top, under) { return top.c.map(function (v, i) { return v * top.a + under[i] * (1 - top.a); }); }
  function behind(el) {
    for (var e = el; e && e !== document.documentElement; e = e.parentElement) {
      var cs = getComputedStyle(e), bg = rgb(cs.backgroundColor), stops = (cs.backgroundImage.match(/rgba?\([^)]+\)/g) || []).map(rgb);
      if (stops.length) { return stops.map(function (s) { return [over(s, [255, 255, 255]), over(s, [0, 0, 0])]; }).reduce(function (a, b) { return a.concat(b); }, []); }
      if (bg && bg.a > 0) { return bg.a >= 1 ? [bg.c] : [over(bg, [255, 255, 255]), over(bg, [0, 0, 0])]; }
    }
    return [[255, 255, 255]];
  }
  var worst = 99, where = '';
  document.querySelectorAll('.abp .abp-item').forEach(function (it) {
    if (!it.offsetParent || getComputedStyle(it).visibility === 'hidden' || getComputedStyle(it).display === 'none') { return; }
    it.querySelectorAll('.abp-txt, .abp-btn, .abp-code').forEach(function (t) {
      if (!t.offsetParent) { return; }
      var fg = rgb(getComputedStyle(t).color).c;
      behind(t).forEach(function (b) { var r = ratio(fg, b); if (r < worst) { worst = r; where = it.getAttribute('data-id') + ' ' + t.className; } });
    });
  });
  return { worst: Math.round(worst * 100) / 100, where: where };
};
async function common(page, label) {
  var sw = await page.evaluate(function () { return [document.documentElement.scrollWidth, window.innerWidth]; });
  ok(label + ': no horizontal scroll', sw[0] <= sw[1], sw);
  var c = await page.evaluate(CONTRAST);
  ok(label + ': contrast ≥ 4.5:1', c.worst >= 4.5, c);
}
async function open(browser, url, w, opts) {
  var ctx = await browser.newContext({ viewport: { width: w, height: w < 768 ? 844 : 900 }, permissions: ['clipboard-read', 'clipboard-write'] });
  if (opts && opts.init) { await ctx.addInitScript(opts.init); }
  var page = await ctx.newPage(), errors = [];
  page.on('pageerror', function (e) { errors.push('pageerror: ' + e.message); });
  page.on('console', function (m) { if (m.type() === 'error' || m.type() === 'warning') { errors.push(m.type() + ': ' + m.text()); } });
  await page.goto(url);
  await page.waitForLoadState('load');
  await sleep(500);
  return { page: page, ctx: ctx, errors: errors };
}
var visibleIds = function () { return Array.prototype.filter.call(document.querySelectorAll('.abp .abp-item'), function (e) { return e.offsetParent !== null && getComputedStyle(e).display !== 'none'; }).map(function (e) { return +e.getAttribute('data-id'); }); };

(async function () {
  var server = srv.start(PORT);
  var browser = await chromium.launch();
  try {
    /* ---------------- 1. Static HTML page ---------------- */
    for (var w of [1366, 390]) {
      var o = await open(browser, BASE + 'basic.html', w), p = o.page, L = 'basic ' + w;
      var info = await p.evaluate(function () {
        var t = document.getElementById('abp-bar'), b = document.getElementById('abp-bar-bottom'), a = t && t.querySelector('.abp-item.abp-active');
        return { top: t && Math.round(t.getBoundingClientRect().top), first: document.body.firstElementChild.id, bottom: b && Math.round(innerHeight - b.getBoundingClientRect().bottom), active: a && a.getAttribute('data-id'),
          text: a && a.innerText, cd: (document.querySelector('.abp-countdown') || {}).textContent, region: t && t.getAttribute('role') + '|' + t.getAttribute('aria-label'), embed: window.ABP && window.ABP.version };
      });
      ok(L + ': top bar first in <body> at the top', info.first === 'abp-bar' && info.top === 0, info);
      ok(L + ': bottom bar at the bottom', info.bottom === 0, info);
      ok(L + ': highest priority first', info.active === '1' && /WELCOME10/.test(info.text), info);
      ok(L + ': countdown running', /\d+\s*d?\s*\d{2}:\d{2}:\d{2}/.test(info.cd || ''), info.cd);
      ok(L + ': labelled region', info.region === 'region|Announcement', info.region);
      ok(L + ': window.ABP.version', info.embed === '1.0.0', info.embed);
      await p.click('#abp-bar .abp-copy');
      await sleep(250);
      var copied = await p.evaluate(function () { return [document.querySelector('#abp-bar .abp-copy-label').textContent, document.querySelector('#abp-bar .abp-sr').textContent]; });
      var clip = await p.evaluate(function () { return navigator.clipboard.readText(); }).catch(function () { return '?'; });
      ok(L + ': coupon copied and announced', copied[0] === 'Copied!' && clip === 'WELCOME10', [copied, clip]);
      await common(p, L);
      await p.screenshot({ path: path.join(SHOTS, 'basic-' + w + '.png') });
      await p.click('#abp-bar .abp-next');
      await sleep(700);
      var second = await p.evaluate(function () { var a = document.querySelector('#abp-bar .abp-item.abp-active'); var m = a.querySelector('.abp-text-m'), d = a.querySelector('.abp-text-d'); return { id: a.getAttribute('data-id'), m: m && getComputedStyle(m).display !== 'none', d: d && getComputedStyle(d).display !== 'none', btn: (a.querySelector('.abp-btn') || {}).textContent }; });
      ok(L + ': next arrow → message 2 with its button', second.id === '2' && second.btn === 'Shop now', second);
      ok(L + ': phone text only on phones', w < 768 ? second.m && !second.d : second.d && !second.m, second);
      await common(p, L + ' (gradient)');
      await p.screenshot({ path: path.join(SHOTS, 'basic-' + w + '-gradient.png') });
      await p.click('#abp-bar .abp-pause');
      var paused = await p.evaluate(function () { var b = document.querySelector('#abp-bar .abp-pause'); return [b.getAttribute('aria-pressed'), b.getAttribute('aria-label')]; });
      ok(L + ': pause button', paused[0] === 'true' && paused[1] === 'Play messages', paused);
      if (w === 1366) {
        await p.mouse.move(5, 600);
        await p.evaluate(function () { window.scrollTo(0, 400); });
        await sleep(1300);
        var hdr = await p.evaluate(function () { var h = document.querySelector('header.site').getBoundingClientRect(), b = document.getElementById('abp-bar').getBoundingClientRect(); return [Math.round(h.top), Math.round(b.bottom)]; });
        ok(L + ': sticky header stays below the sticky bar', Math.abs(hdr[0] - hdr[1]) <= 1, hdr);
      }
      await p.click('#abp-bar .abp-close');
      await sleep(200);
      var hidden = await p.evaluate(function () { return getComputedStyle(document.getElementById('abp-bar')).display; });
      await p.reload(); await sleep(600);
      var after = await p.evaluate(function () { var b = document.getElementById('abp-bar'); return b ? getComputedStyle(b).display : 'gone'; });
      ok(L + ': close hides and is remembered', hidden === 'none' && after === 'none', [hidden, after]);
      ok(L + ': no JS errors', o.errors.length === 0, o.errors);
      await o.ctx.close();
    }
    var lv = await open(browser, BASE + 'basic.html?lang=lv', 1366);
    var lvi = await lv.page.evaluate(function () { var b = document.getElementById('abp-bar'); return [b.querySelector('.abp-item.abp-active').innerText, b.querySelector('.abp-close').getAttribute('aria-label'), b.querySelector('.abp-copy-label').textContent]; });
    ok('language from ?lang=lv: text and labels', /Laipni/.test(lvi[0]) && lvi[1] === 'Aizvērt' && lvi[2] === 'Kopēt', lvi);
    ok('lv: no JS errors', lv.errors.length === 0, lv.errors);
    await lv.ctx.close();

    /* ---------------- 2. Fake Shopify theme + cart adapter ---------------- */
    for (var w2 of [1366, 390]) {
      srv.reset();
      var s = await open(browser, BASE + 'shopify.html', w2), sp = s.page, SL = 'shopify ' + w2;
      var fsInfo = function () { return sp.evaluate(function () { var e = document.querySelector('.abp-item[data-fs="1"]'); return { text: e.querySelector('.abp-txt').innerText, shown: e.offsetParent !== null && !e.hasAttribute('data-coff'), p: e.querySelector('.abp-progress').getAttribute('data-p'), w: e.querySelector('.abp-progress-fill').style.width, done: e.getAttribute('data-done') }; }); };
      var f0 = await fsInfo();
      ok(SL + ': empty cart → full amount', f0.shown && /€50\.00/.test(f0.text) && f0.p === '0', f0);
      var hdr2 = await sp.evaluate(function () { return [Math.round(document.getElementById('shopify-section-header').getBoundingClientRect().top), Math.round(document.getElementById('abp-bar').getBoundingClientRect().bottom)]; });
      ok(SL + ': fixed theme header moved below the bar', Math.abs(hdr2[0] - hdr2[1]) <= 1, hdr2);
      await sp.click('#add'); await sleep(900);
      var f1 = await fsInfo();
      ok(SL + ': after AJAX add (17.50) → 32.50 left, 35 %', /€32\.50/.test(f1.text) && f1.p === '35' && f1.w === '35%', f1);
      await common(sp, SL);
      await sp.screenshot({ path: path.join(SHOTS, 'shopify-' + w2 + '.png') });
      await sp.click('#add'); await sp.click('#add'); await sleep(1100);
      var f2 = await fsInfo();
      ok(SL + ': 52.50 ≥ 50 → success text, 100 %', /unlocked/.test(f2.text) && f2.p === '100' && f2.done === '1', f2);
      await sp.screenshot({ path: path.join(SHOTS, 'shopify-' + w2 + '-done.png') });
      ok(SL + ': no JS errors', s.errors.length === 0, s.errors);
      await s.ctx.close();
    }

    /* ---------------- 3. Strict CSP (no unsafe-inline / unsafe-eval), config as a JSON file ---------------- */
    for (var w3 of [1366, 390]) {
      var c = await open(browser, BASE + 'csp.html', w3), cp = c.page, CL = 'csp ' + w3;
      var ci = await cp.evaluate(function () { var b = document.getElementById('abp-bar'); return b && { place: b.getAttribute('data-place'), ids: Array.prototype.map.call(b.querySelectorAll('.abp-item'), function (e) { return e.getAttribute('data-id'); }), text: b.innerText, outline: getComputedStyle(b).outlineColor, close: b.querySelector('.abp-close').getAttribute('aria-label') }; });
      ok(CL + ': bottom bar with both messages (stack)', ci && ci.place === 'bottom' && ci.ids.join() === '21,22', ci);
      ok(CL + ': page language lv from <html lang>', ci && /Stingrs CSP/.test(ci.text) && ci.close === 'Aizvērt', ci);
      ok(CL + ': custom CSS applied without unsafe-inline', ci && ci.outline === 'rgb(250, 204, 21)', ci && ci.outline);
      await cp.click('.abp-copy'); await sleep(250);
      ok(CL + ': coupon copy', (await cp.evaluate(function () { return document.querySelector('.abp-copy-label').textContent; })) === 'Nokopēts!');
      await common(cp, CL);
      await cp.screenshot({ path: path.join(SHOTS, 'csp-' + w3 + '.png') });
      ok(CL + ': no JS errors and no CSP violations', c.errors.length === 0, c.errors);
      await c.ctx.close();
    }
    var n = await open(browser, BASE + 'csp-nonce.html', 1366);
    var ni = await n.page.evaluate(function () { var b = document.getElementById('abp-bar'); return b && [b.innerText, getComputedStyle(b).outlineColor, b.querySelectorAll('b').length]; });
    ok('csp nonce: renders, custom CSS with the nonce', ni && /Nonce page works/.test(ni[0]) && ni[1] === 'rgb(250, 204, 21)' && ni[2] === 1, ni);
    ok('csp nonce: no JS errors and no CSP violations', n.errors.length === 0, n.errors);
    await n.ctx.close();

    /* ---------------- 4. Hostile config ---------------- */
    var x = await open(browser, BASE + 'xss.html', 1366);
    await x.page.mouse.move(300, 20); await sleep(1500);
    var xi = await x.page.evaluate(function () {
      var bar = document.getElementById('abp-bar'), all = bar ? bar.querySelectorAll('*') : [], bad = [];
      Array.prototype.forEach.call(all, function (e) {
        Array.prototype.forEach.call(e.attributes, function (a) { if (/^on/i.test(a.name) || /javascript:/i.test(a.value)) { bad.push(e.tagName + ' ' + a.name + '=' + a.value); } });
        if (/^(IMG|SCRIPT|IFRAME|STYLE|ANIMATE|OBJECT)$/i.test(e.tagName)) { bad.push(e.tagName); }
      });
      var styles = Array.prototype.map.call(document.querySelectorAll('style'), function (s) { return s.textContent; }).join('|');
      var sheets = (document.adoptedStyleSheets || []).map(function (s) { return Array.prototype.map.call(s.cssRules, function (r) { return r.cssText; }).join(' '); }).join('|');
      return { pwned: window.__pwned, bad: bad, text: bar && Array.prototype.map.call(bar.querySelectorAll('.abp-item'), function (e) { return e.textContent; }).join(' | '), links: bar ? Array.prototype.map.call(bar.querySelectorAll('a'), function (a) { return a.getAttribute('href'); }) : [], css: styles + sheets,
        icon: (bar.querySelector('.abp-ico') || {}).textContent, coupon: bar.querySelector('.abp-copy') ? 'button' : 'none', bg: bar.querySelector('.abp-item').style.backgroundColor, img: bar.querySelectorAll('.abp-item')[1].style.backgroundImage, close: bar.querySelector('.abp-close').getAttribute('aria-label') };
    });
    ok('xss: nothing ran', xi.pwned === 0, xi.pwned);
    ok('xss: no event handlers, scripts, images or javascript: URLs in the bar', xi.bad.length === 0, xi.bad);
    ok('xss: safe parts kept as text / allowed tags', /bold/.test(xi.text) && /<script> stays text/.test(xi.text) && xi.links.join() === '/ok', { text: xi.text, links: xi.links });
    ok('xss: hostile CSS filtered', !/script|javascript|@import/i.test(xi.css), xi.css);
    ok('xss: icon is text, bad coupon dropped, bad colour / image ignored', xi.icon === 'img src=x onerr' && xi.coupon === 'none' && xi.bg === 'rgb(34, 34, 34)' && xi.img === '', xi);
    ok('xss: hostile label is plain attribute text', xi.close === '<img src=x onerror=window.__pwned++>', xi.close);
    await x.ctx.close();

    /* ---------------- 5. Rules: pages, UTM, triggers, devices, language, schedule, visitor ---------------- */
    var r = await open(browser, BASE + 'rules.html', 1366), rp = r.page;
    var v0 = await rp.evaluate(visibleIds);
    var midnight = new Date().getUTCHours() === 0 && new Date().getUTCMinutes() === 0;
    ok('rules: start → always, not-promo, desktop, new visitor', v0.join() === (midnight ? '1,3,8,10,11' : '1,3,8,11'), v0);
    await sleep(900);
    ok('rules: delay trigger after 1 s', (await rp.evaluate(visibleIds)).indexOf(5) !== -1);
    await rp.evaluate(function () { window.scrollTo(0, document.documentElement.scrollHeight); }); await sleep(700);
    ok('rules: scroll trigger at 50 %', (await rp.evaluate(visibleIds)).indexOf(6) !== -1);
    await rp.goto(BASE + 'rules.html?promo=1&utm_source=Newsletter'); await sleep(600);
    var v1 = await rp.evaluate(visibleIds);
    ok('rules: page rule include / exclude (wildcard) + UTM', v1.indexOf(2) !== -1 && v1.indexOf(3) === -1 && v1.indexOf(4) !== -1, v1);
    ok('rules: fired triggers show at once on the next page of the visit', v1.indexOf(5) !== -1 && v1.indexOf(6) !== -1, v1);
    await rp.goto(BASE + 'rules.html?lang=de'); await sleep(600);
    var v2 = await rp.evaluate(visibleIds);
    ok('rules: language de + UTM remembered for the visit', v2.indexOf(9) !== -1 && v2.indexOf(4) !== -1, v2);
    ok('rules: no JS errors', r.errors.length === 0, r.errors);
    await r.ctx.close();
    var rm = await open(browser, BASE + 'rules.html', 390);
    var v3 = await rm.page.evaluate(visibleIds);
    ok('rules 390: phones-only shown, desktop-only hidden', v3.indexOf(7) !== -1 && v3.indexOf(8) === -1, v3);
    await common(rm.page, 'rules 390');
    ok('rules 390: no JS errors', rm.errors.length === 0, rm.errors);
    await rm.ctx.close();
    var dnt = await open(browser, BASE + 'rules.html?utm_source=newsletter', 1366, { init: function () { Object.defineProperty(Navigator.prototype, 'globalPrivacyControl', { get: function () { return true; } }); } });
    var dntInfo = await dnt.page.evaluate(function () { return { gpc: navigator.globalPrivacyControl, v: localStorage.getItem('abp_v'), utm: sessionStorage.getItem('abp_utm'), ids: Array.prototype.filter.call(document.querySelectorAll('.abp .abp-item'), function (e) { return e.offsetParent !== null; }).map(function (e) { return +e.getAttribute('data-id'); }) }; });
    ok('rules: Global Privacy Control → nothing stored, "new visitor" unknown, URL campaign still counts', dntInfo.gpc === true && dntInfo.v === null && dntInfo.utm === null && dntInfo.ids.indexOf(11) === -1 && dntInfo.ids.indexOf(4) !== -1, dntInfo);
    await dnt.ctx.close();

    /* ---------------- 6. Configurator from file:// ---------------- */
    var cfgUrl = 'file:///' + path.join(ROOT, 'configurator', 'index.html').replace(/\\/g, '/');
    for (var w4 of [1366, 390]) {
      var g = await open(browser, cfgUrl, w4), gp = g.page, GL = 'configurator ' + w4;
      await sleep(600);
      var frame = function () { return gp.frames().find(function (f) { return f !== gp.mainFrame(); }); };
      var pv = await frame().evaluate(function () { var b = document.getElementById('abp-bar'); return b && b.innerText; });
      ok(GL + ': preview runs the real runtime', !!pv && /WELCOME10/.test(pv), pv);
      await gp.click('#btn-tpl');
      await gp.click('.tpl:nth-child(2)');
      await sleep(700);
      var count = await gp.evaluate(function () { return document.querySelectorAll('#msg-list .msg').length; });
      ok(GL + ': template added', count === 2, count);
      await gp.fill('#editor textarea', 'Mega sale ends in {countdown}');
      await sleep(900);
      var pv2 = await frame().evaluate(function () { return Array.prototype.map.call(document.querySelectorAll('.abp-item'), function (e) { return e.innerText; }).join(' | '); });
      ok(GL + ': live preview follows the text', /Mega sale ends in \d/.test(pv2), pv2);
      var exp = await gp.evaluate(function () { return { json: document.getElementById('exp-json').value, a: document.getElementById('exp-a').textContent, b: document.getElementById('exp-b').textContent }; });
      var parsed = null; try { parsed = JSON.parse(exp.json); } catch (e) { parsed = null; }
      ok(GL + ': JSON export valid', parsed && parsed.messages.length === 2 && parsed.messages[1].text.en === 'Mega sale ends in {countdown}', exp.json.slice(0, 200));
      ok(GL + ': snippets', /<script type="application\/json" id="abp-config">\{.*\}<\/script>\n<script src="https:\/\/YOUR-SITE\/abp\/abp-embed\.min\.js" defer><\/script>/.test(exp.a) && /data-config="https:\/\/YOUR-SITE\/abp\/abp-config\.json"/.test(exp.b), exp.a.slice(0, 120));
      await common(gp, GL);
      if (w4 === 1366) {
        await gp.click('#pv-dev button[data-v=phone]'); await sleep(900);
        await gp.screenshot({ path: path.join(SHOTS, 'configurator-1366-phone-preview.png'), fullPage: false });
        var aio = await gp.evaluate(function () { return fetch(document.getElementById('dl-c').href).then(function (r) { return r.text(); }); });
        fs.mkdirSync(path.join(__dirname, 'out'), { recursive: true });
        fs.writeFileSync(path.join(__dirname, 'out', 'aio.html'), '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>All-in-one</title></head><body><h1>Site builder page</h1>' + aio + '</body></html>');
      }
      await gp.screenshot({ path: path.join(SHOTS, 'configurator-' + w4 + '.png'), fullPage: false });
      ok(GL + ': no JS errors', g.errors.length === 0, g.errors);
      await g.ctx.close();
    }
    var aioPage = await open(browser, 'http://127.0.0.1:' + PORT + '/tests/e2e/out/aio.html', 1366);
    var aioInfo = await aioPage.page.evaluate(function () { var b = document.getElementById('abp-bar'); return b && [b.querySelectorAll('.abp-item').length, getComputedStyle(b).position]; });
    ok('all-in-one snippet (Wix / Tilda style) works on its own', aioInfo && aioInfo[0] === 2 && /sticky|fixed|relative/.test(aioInfo[1]), aioInfo);
    ok('all-in-one: no JS errors', aioPage.errors.length === 0, aioPage.errors);
    await aioPage.page.screenshot({ path: path.join(SHOTS, 'all-in-one-1366.png') });
    await aioPage.ctx.close();
  } catch (e) {
    ok('E2E run', false, e.stack);
  } finally {
    await browser.close();
    server.close();
  }
  R.forEach(function (l) { console.log(l); });
  var failed = R.filter(function (l) { return l.indexOf('FAIL') === 0; }).length;
  console.log((failed ? 'FAILED ' : 'OK ') + (R.length - failed) + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})();
