/* Unit tests of the embed core (no browser): node tests/unit/run.js */
'use strict';
var C = require('../../src/abp-embed-core.js');
var pass = 0, fail = 0;
function ok(name, cond, extra) { if (cond) { pass++; } else { fail++; console.log('FAIL ' + name + (extra !== undefined ? ' — ' + JSON.stringify(extra) : '')); } }
function eq(name, got, want) { ok(name, JSON.stringify(got) === JSON.stringify(want), { got: got, want: want }); }

// Same rules as build() in src/abp-embed.js: '#' = unwrapped, a link inside a link is unwrapped, text escaped.
function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function html(nodes, inLink) {
  return nodes.map(function (n) {
    if (typeof n === 'string') { return esc(n); }
    if (n.t === 'br') { return '<br>'; }
    var a = n.t === 'a', u = a && !inLink && C.url(n.href);
    if (!C.ALLOWED[n.t] || (a && !u)) { return html(n.c, inLink); }
    return '<' + n.t + (u ? ' href="' + esc(u) + '"' + (n.blank ? ' target="_blank" rel="noopener noreferrer"' : '') : '') + '>' + html(n.c, inLink || a) + '</' + n.t + '>';
  }).join('');
}
function san(s) { return html(C.parseHtml(s)); }

/* ---------------- Sanitizer (allow-list of the PHP Sanitizer) ---------------- */
eq('plain text', san('Free shipping over 50 €'), 'Free shipping over 50 €');
eq('allowed tags kept', san('<b>Bold</b> <strong>s</strong> <i>i</i> <em>e</em> <u>u</u> <s>x</s> a<br>b <span>sp</span>'), '<b>Bold</b> <strong>s</strong> <i>i</i> <em>e</em> <u>u</u> <s>x</s> a<br>b <span>sp</span>');
eq('attributes dropped', san('<b class="x" style="color:red" onclick="alert(1)">B</b>'), '<b>B</b>');
eq('script removed with content', san('a<script>alert(1)</script>b'), 'ab');
eq('style removed with content', san('a<style>body{display:none}</style>b'), 'ab');
eq('iframe / object / template removed', san('a<iframe src="//x"></iframe><object data=x>o</object><template><b>t</b></template>b'), 'ab');
eq('textarea / title / noscript raw text removed', san('a<textarea></b><script>x</script></textarea>b<title>t</title><noscript><img src=x onerror=alert(1)></noscript>c'), 'abc');
eq('img onerror removed', san('x<img src=x onerror="alert(1)">y'), 'xy');
eq('svg onload unwrapped', san('<svg onload=alert(1)><text>hi</text></svg>'), 'hi');
eq('unknown tags unwrapped', san('<div><p>Hello</p><h1>World</h1></div>'), 'HelloWorld');
eq('comment removed', san('a<!-- <script>alert(1)</script> -->b'), 'ab');
eq('doctype / pi removed', san('<!DOCTYPE html><?php echo 1 ?>x'), 'x');
eq('link http kept', san('<a href="https://example.com/x?a=1&amp;b=2">go</a>'), '<a href="https://example.com/x?a=1&amp;b=2">go</a>');
eq('link relative kept', san('<a href="/sale">s</a>'), '<a href="/sale">s</a>');
eq('target _blank gets rel', san('<a href="/s" target="_blank">s</a>'), '<a href="/s" target="_blank" rel="noopener noreferrer">s</a>');
eq('other target dropped', san('<a href="/s" target="_top">s</a>'), '<a href="/s">s</a>');
eq('javascript: link unwrapped', san('<a href="javascript:alert(1)">x</a>'), 'x');
eq('JaVaScRiPt with entities unwrapped', san('<a href="&#106;avascript:alert(1)">x</a>'), 'x');
eq('javascript with tab unwrapped', san('<a href="java\tscript:alert(1)">x</a>'), 'x');
eq('data: link unwrapped', san('<a href="data:text/html,<script>alert(1)</script>">x</a>'), 'x');
eq('vbscript link unwrapped', san('<a href="vbscript:msgbox">x</a>'), 'x');
eq('protocol-relative link unwrapped', san('<a href="//evil.example">x</a>'), 'x');
eq('link without href unwrapped', san('<a name="x">x</a>'), 'x');
eq('nested link unwrapped', san('<a href="/a">A<a href="/b">B</a></a>'), '<a href="/a">AB</a>');
eq('quoted > in attribute', san('<a href="/x" title="a>b">t</a>'), '<a href="/x">t</a>');
eq('stray < is text', san('x < 5 and 3 <4'), 'x &lt; 5 and 3 &lt;4');
eq('unclosed tag', san('<b>bold'), '<b>bold</b>');
eq('stray closing tags', san('</div></span>a</b>'), 'a');
eq('entities decoded once', san('Fish &amp; chips &lt;b&gt; &#x1F69A; &#8364;'), 'Fish &amp; chips &lt;b&gt; 🚚 €');
eq('unknown entity left as text', san('&bogus; &ampx'), '&amp;bogus; &amp;ampx');
eq('control chars removed', san('a\u0000b\u0007c'), 'abc');
eq('upper-case tags', san('<B>x</B><SCRIPT>y</SCRIPT>'), '<b>x</b>');
eq('uppercase SCRIPT with spaces in end tag', san('a<script >x</script >b'), 'ab');
eq('unterminated script swallows rest', san('a<script>alert(1)'), 'a');
eq('plain mode keeps literal text', html(C.rich('<b>x</b>', false)), '&lt;b&gt;x&lt;/b&gt;');
ok('tree never has an element outside the allow-list', JSON.stringify(C.parseHtml('<img><p><b>x</b></p>')).indexOf('"t":"img"') === -1);

/* ---------------- URLs ---------------- */
eq('url https', C.url('https://shop.example/sale?x=1#top'), 'https://shop.example/sale?x=1#top');
eq('url http', C.url('http://example.com'), 'http://example.com');
eq('url path', C.url('/collections/sale'), '/collections/sale');
eq('url relative file', C.url('sale.html'), 'sale.html');
eq('url query only', C.url('?page=2'), '?page=2');
eq('url hash', C.url('#top'), '#top');
eq('url non-ascii path encoded', C.url('/akcija-šodien'), '/akcija-%C5%A1odien');
eq('url spaces encoded', C.url('/a b'), '/a%20b');
['javascript:alert(1)', 'JAVASCRIPT:alert(1)', ' javascript:alert(1)', 'data:text/html,x', 'vbscript:x', '//evil.example/x', 'ftp://x.example', 'mailto:a@b.c',
  'https://x.example/"onmouseover="alert(1)', "/a'b", '/a<b', 'https:/x', 'https://', 'file:///etc/passwd', 'java\nscript:alert(1)', '\\\\evil\\x']
  .forEach(function (u) { eq('url refused: ' + u, C.url(u), ''); });
eq('url too long', C.url('/' + new Array(600).join('a')), '');

/* ---------------- Custom CSS (port of Features::css) ---------------- */
eq('css plain kept', C.css('.abp-bar{letter-spacing:.02em}'), '.abp-bar{letter-spacing:.02em}');
eq('css </style> removed', C.css('a{}</style><script>alert(1)</script>'), 'a{}alert(1)');
eq('css @import removed', C.css('@import url(https://evil.example/x.css); a{color:red}'), 'a{color:red}');
eq('css javascript: removed (same output as the PHP filter)', C.css('a{background:url(javascript:alert(1))}'), 'a{background:none)}');
eq('css expression removed', C.css('a{width:expression(alert(1))}'), 'a{width:alert(1))}');
eq('css backslash escapes removed', C.css('a{background:u\\72l(javascript:x)}'), 'a{background:u72l(x)}');
eq('css url https kept and quoted', C.css('a{background:url(https://cdn.example/x.png)}'), 'a{background:url("https://cdn.example/x.png")}');
eq('css url data:image kept', C.css('a{background:url(data:image/png;base64,AAAA)}'), 'a{background:url("data:image/png;base64,AAAA")}');
eq('css url data:text refused', C.css('a{background:url(data:text/html,x)}'), 'a{background:none}');
eq('css -moz-binding removed', C.css('a{-moz-binding:url(x.xml#x)}'), 'a{none}');
eq('css @IMPORT / @charset removed', C.css('@IMPORT "x"; @charset "utf-8"; a{}'), 'a{}');
eq('css split javascript: removed', C.css('a{b:url(javajavascript:script:x)}'), 'a{b:none}');
ok('css max 10000 chars', C.css(new Array(20001).join('a')).length === 10000);

/* ---------------- Tokens ---------------- */
var t = C.parseHtml('Ends in {countdown}! <b>{countdown}</b>');
ok('countdown replaced once', C.replaceToken(t, '{countdown}', { t: 'cd', end: 1, c: [] }) && JSON.stringify(t).split('"cd"').length === 2 && JSON.stringify(t).indexOf('{countdown}') === -1, t);
t = C.parseHtml('<a href="/x">{remaining}</a>');
C.replaceToken(t, '{remaining}', { t: 'rem', v: '5 €' });
ok('token replaced inside a link text', JSON.stringify(t).indexOf('"rem"') !== -1);
t = C.parseHtml('<a href="/{countdown}">x</a>');
ok('token in an attribute is not a token', !C.replaceToken(t, '{countdown}', { t: 'cd' }));

/* ---------------- Page rules ---------------- */
var loc = { pathname: '/Collections/Sale/', search: '?page=2' };
ok('contains', C.pageRule({ type: 'contains', value: 'sale' }, loc));
ok('contains query', C.pageRule({ type: 'contains', value: 'page=2' }, loc));
ok('contains no', !C.pageRule({ type: 'contains', value: 'blog' }, loc));
ok('equals path, trailing slash ignored', C.pageRule({ type: 'equals', value: '/collections/sale' }, loc));
ok('equals path+query', C.pageRule({ type: 'equals', value: '/collections/sale/?page=2' }, loc));
ok('equals no prefix match', !C.pageRule({ type: 'equals', value: '/collections' }, loc));
ok('wildcard', C.pageRule({ type: 'wildcard', value: '/collections/*' }, loc));
ok('wildcard middle', C.pageRule({ type: 'wildcard', value: '/*/sale/*' }, loc));
ok('wildcard anchored', !C.pageRule({ type: 'wildcard', value: '/sale*' }, loc));
ok('wildcard regex chars are literal', !C.pageRule({ type: 'wildcard', value: '/collections/.+' }, loc) && C.pageRule({ type: 'wildcard', value: '/a(b)[c]/*' }, { pathname: '/a(b)[c]/x', search: '' }));
ok('wildcard catastrophic input is fast (no regex)', (function () { var s = Date.now(); var hit = C.pageRule({ type: 'wildcard', value: new Array(40).join('*a') + '!' }, { pathname: '/' + new Array(300).join('a'), search: '' }); return !hit && Date.now() - s < 500; })());
ok('wildcard * matches empty', C.pageRule({ type: 'wildcard', value: '/collections/sale/*' }, loc));
ok('include mode', C.pagesOk({ mode: 'include', rules: [{ type: 'contains', value: 'sale' }] }, loc));
ok('exclude mode', !C.pagesOk({ mode: 'exclude', rules: [{ type: 'contains', value: 'sale' }] }, loc));
ok('all mode', C.pagesOk({ mode: 'all', rules: [] }, loc));

/* ---------------- Language ---------------- */
var L = ['en', 'lv', 'ru'];
eq('lang from URL segment', C.pageLang('en', { pathname: '/lv/products/x', search: '' }, L, 'en'), 'lv');
eq('lang from ?lang=', C.pageLang('en', { pathname: '/p', search: '?a=1&lang=ru' }, L, 'en'), 'ru');
eq('lang from <html lang>', C.pageLang('ru-RU', { pathname: '/p', search: '' }, L, 'en'), 'ru');
eq('lang unknown → default', C.pageLang('fr', { pathname: '/fr/', search: '' }, L, 'en'), 'en');
eq('two-letter segment that is no language', C.pageLang('lv', { pathname: '/xx/p', search: '' }, L, 'en'), 'lv');
eq('pick falls back to default language', C.pick({ en: 'Hi', lv: '' }, 'lv', 'en'), 'Hi');
eq('pick string for all languages', C.pick('Hi', 'lv', 'en'), 'Hi');

/* ---------------- Time zones and schedule ---------------- */
var jan = Date.UTC(2026, 0, 15, 12), jul = Date.UTC(2026, 6, 15, 12);
eq('Riga winter offset', C.tzOffset('Europe/Riga', jan), 7200);
eq('Riga summer offset', C.tzOffset('Europe/Riga', jul), 10800);
eq('New York winter offset', C.tzOffset('America/New_York', jan), -18000);
eq('Kolkata offset (half hour)', C.tzOffset('Asia/Kolkata', jan), 19800);
eq('zoned Riga winter', C.zoned('2026-01-15T14:00', 'Europe/Riga'), Date.UTC(2026, 0, 15, 12));
eq('zoned Riga summer', C.zoned('2026-07-15T14:00', 'Europe/Riga'), Date.UTC(2026, 6, 15, 11));
eq('zoned DST change day (Riga, 29 Mar 2026 05:00)', C.zoned('2026-03-29T05:00', 'Europe/Riga'), Date.UTC(2026, 2, 29, 2));
eq('zoned date only end = 23:59', C.zoned('2026-01-15', 'UTC', true), Date.UTC(2026, 0, 15, 23, 59));
eq('zoned invalid', C.zoned('15.01.2026', 'UTC'), 0);
eq('recur daily rule', C.recurRule({ recur: 'daily', recur_time: '14:00', recur_skip: '67' }), 'd|14:00|67');
eq('recur weekly rule', C.recurRule({ recur: 'weekly', recur_time: '09:30', recur_day: 5 }), 'w|09:30|5');
eq('recur bad time', C.recurRule({ recur: 'daily', recur_time: '25:00' }), '');
eq('weekdays from array', C.weekdays([7, 6]), '67');
eq('weekdays all = every day', C.weekdays('1234567'), '');
eq('clock', [C.clock('09:05'), C.clock('24:00'), C.clock('9:05')], ['09:05', '', '']);

/* ---------------- Config normalisation ---------------- */
var n = C.normalize({ settings: { interval: 999, font_size: 'x', mode: 'evil', position: 'bottom', languages: ['LV', 'en', 'xxx'], default_language: 'lv', timezone: 'Europe/Riga', currency: 'usd', custom_css: '@import x; a{}' },
  messages: [{ text: 'A', link: 'javascript:alert(1)', bg_color: 'red', coupon: 'save 10', icon: '<b>', image: 'data:image/png,x', priority: 500, trigger: 'scroll', trigger_value: 900, referrer: 'https://www.Google.com/search' }, null, 'x', { id: 7, text: { lv: 'B' }, enabled: false }] });
eq('interval clamped', n.settings.interval, 60);
eq('font size default', n.settings.font_size, 14);
eq('mode default', n.settings.mode, 'rotate');
eq('position kept', n.settings.position, 'bottom');
eq('languages cleaned', n.settings.languages, ['lv', 'en']);
eq('currency must be upper case code', n.settings.currency, 'EUR');
eq('custom css filtered', n.settings.custom_css, 'a{}');
eq('bad messages dropped', n.messages.length, 2);
var m0 = n.messages[0];
eq('javascript link removed', m0.link, '');
eq('colour default', m0.bg_color, '#222222');
eq('coupon with space refused', m0.coupon, '');
eq('icon specials stripped', m0.icon, 'b');
eq('data image URL refused', m0.image, '');
eq('priority clamped', m0.priority, 100);
eq('scroll % clamped', m0.trigger_value, 100);
eq('referrer to host', m0.referrer, 'google.com');
eq('id kept', n.messages[1].id, 7);

/* ---------------- What shows (select) ---------------- */
var NOW = Date.UTC(2026, 10, 8, 10); // Sun 8 Nov 2026 12:00 in Riga
function sel(cfg, ctx) { var c = C.normalize(cfg); return C.select(c, Object.assign({ now: NOW, lang: c.settings.default_language, loc: { pathname: '/', search: '' }, cart: null }, ctx || {})); }
var cfg = { settings: { timezone: 'Europe/Riga', languages: ['en', 'lv'], default_language: 'en', position: 'top' }, messages: [
  { id: 1, text: { en: 'Low', lv: 'Zems' }, priority: 1 },
  { id: 2, text: 'High', priority: 50, placement: 'bottom' },
  { id: 3, text: 'Mid', priority: 10 },
  { id: 4, text: 'Future', date_start: '2026-11-09T00:00' },
  { id: 5, text: 'Past', date_end: '2026-11-08T11:59' },
  { id: 6, text: 'Running', date_start: '2026-11-08T11:00', date_end: '2026-11-08T12:30' },
  { id: 7, text: 'LV only', languages: ['lv'] },
  { id: 8, text: 'Sale pages', pages: { mode: 'include', rules: [{ type: 'contains', value: '/sale' }] } },
  { id: 9, text: 'Off', enabled: false },
  { id: 10, text: '   ', link_text: 'x' }
] };
var r = sel(cfg);
eq('top bar order by priority', r.top.map(function (i) { return i.id; }), [3, 1, 6]);
eq('bottom bar', r.bottom.map(function (i) { return i.id; }), [2]);
r = sel(cfg, { lang: 'lv', loc: { pathname: '/lv/sale', search: '' } });
eq('LV page: language + page rule', r.top.map(function (i) { return i.id; }), [3, 1, 6, 7, 8]);
eq('LV text used', C.plainOf(r.top[1].v.d), 'Zems');
eq('end time passed to the item', r.top[2].end, Date.UTC(2026, 10, 8, 10, 30));
ok('dismiss key is 12 hex', r.top.every(function (i) { return /^[0-9a-f]{12}$/.test(i.key); }));
var k1 = sel(cfg).top[0].key, cfg2 = JSON.parse(JSON.stringify(cfg));
cfg2.messages[2].text = 'Mid!';
ok('dismiss key changes with the text', sel(cfg2).top[0].key !== k1);
cfg2.messages[2].text = 'Mid'; cfg2.messages[2].priority = 11;
ok('dismiss key stays for priority / schedule edits', sel(cfg2).top[0].key === k1);
ok('dismiss key per language', sel(cfg, { lang: 'lv' }).top[0].key !== k1);

var cd = sel({ settings: { timezone: 'UTC' }, messages: [
  { id: 1, text: 'Ends in {countdown}!', countdown: true, date_end: '2026-11-10T00:00' },
  { id: 2, text: 'No token', countdown: true, date_end: '2026-11-10T00:00' },
  { id: 3, text: 'Order within {countdown}', countdown: true, recur: 'daily', recur_time: '14:00', recur_skip: '67' },
  { id: 4, text: 'Pc', mobile_text: 'Phone {countdown}', countdown: true, date_end: '2026-11-10T00:00' }
] }).top;
eq('countdown in place', cd[0].v.d.map(function (x) { return typeof x === 'string' ? x : x.t + ':' + x.end; }), ['Ends in ', 'cd:' + Date.UTC(2026, 10, 10), '!']);
eq('countdown after text without token', cd[1].v.d.map(function (x) { return typeof x === 'string' ? x : x.t; }), ['No token', ' ', 'cd']);
eq('recurring countdown carries the rule (cut-off computed by the storefront script)', [cd[2].v.d[1].recur, cd[2].v.d[1].end], ['d|14:00|67', 0]);
ok('phone text with its own countdown', cd[3].v.m.some(function (x) { return x.t === 'cd'; }) && cd[3].v.d.some(function (x) { return x.t === 'cd'; }));

var fsCfg = { settings: { currency: 'EUR', timezone: 'UTC' }, messages: [{ id: 1, type: 'free_shipping', threshold: { EUR: 50, USD: 60 }, text: 'Add {remaining} for free shipping', success_text: 'Free shipping unlocked!' }] };
var f = sel(fsCfg).top[0];
ok('free shipping waits for the cart', f.fs && f.goal === null && C.plainOf(f.v.d).indexOf('{remaining}') === -1);
f = sel(fsCfg, { cart: { subtotal: 20, currency: 'EUR' }, lang: 'en' }).top[0];
eq('remaining amount', [f.goal.pct, f.goal.done, C.plainOf(f.v.d)], [40, false, 'Add €30.00 for free shipping']);
f = sel(fsCfg, { cart: { subtotal: 75, currency: 'USD' }, lang: 'en' }).top[0];
eq('own currency amount, never converted', [f.goal.pct, f.goal.done, C.plainOf(f.v.d)], [100, true, 'Free shipping unlocked!']);
f = sel(fsCfg, { cart: { subtotal: 10, currency: 'GBP' } }).top[0];
ok('unknown currency: hidden', f.goal === null);
f = sel({ settings: { currency: 'EUR' }, messages: [{ type: 'free_shipping', threshold: 40, text: 'Only {remaining}' }] }, { cart: { subtotal: 15.5, currency: 'EUR' }, lang: 'lv' }).top[0];
eq('money in the page language', C.plainOf(f.v.d).replace(/ /g, ' '), 'Only 24,50 €');

var lk = sel({ messages: [{ text: 'Whole message', link: '/x' }, { text: 'With button', link: '/x', link_text: 'Go' }, { text: 'Own <a href="/y">link</a>', link: '/x' }] }).top;
eq('link wraps message only without button / inner link', lk.map(function (i) { return i.v.wrap; }), [true, false, false]);

var u = sel({ messages: [{ text: 'x', utm_source: 'News, FB', referrer: 'google.com', visitor: 'new', trigger: 'delay', trigger_value: 3 }] }).top[0];
eq('client rules for the storefront script', [JSON.parse(u.utm), u.m.visitor, u.trig], [{ s: ['news', 'fb'], m: [], c: [], r: ['google.com'] }, 'new', 'delay:3']);

console.log((fail ? 'FAILED ' : 'OK ') + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
