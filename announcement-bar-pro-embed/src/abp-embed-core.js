/* Announcement Bar Pro Embed — core (no DOM): config checks, HTML allow-list, URL / CSS filters, page rules,
   time zones, free-shipping goal. Pure functions, also loaded by the unit tests (node) and the Configurator. */
var ABPCore = (function () {
  'use strict';

  var str = function (v, max) { v = typeof v === 'string' ? v : (typeof v === 'number' ? String(v) : ''); return max ? v.slice(0, max) : v; };
  function clamp(v, min, max, def) { v = parseInt(v, 10); return isNaN(v) ? def : Math.max(min, Math.min(max, v)); }
  function oneOf(v, list) { return list.indexOf(v) !== -1 ? v : list[0]; }

  /* ---------------- HTML allow-list (port of the PHP Sanitizer) ----------------
     Result is a small tree, never an HTML string: text = string, element = {t, c[]} (+ href / blank for links).
     Allowed: b strong i em u s br span a[href, target=_blank]. Script-like tags go with their content; every
     other tag is unwrapped (its text stays). Links need an http(s) / relative URL and cannot nest. */
  var ALLOWED = { b: 1, strong: 1, i: 1, em: 1, u: 1, s: 1, br: 1, span: 1, a: 1 };
  // Removed together with everything up to their end tag. Any other tag that is not allowed is unwrapped (its text
  // stays; void tags such as img / input / meta have no text, so they simply disappear).
  var DROP = {}, ENT = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00a0' };
  'script style textarea title noscript iframe xmp noembed noframes plaintext object template select head'.split(' ').forEach(function (t) { DROP[t] = 1; });

  function decode(s) {
    return String(s).replace(/&(#x[0-9a-f]{1,6}|#\d{1,7}|[a-z]{2,8});/gi, function (m, e) {
      if (e.charAt(0) === '#') {
        var n = e.charAt(1).toLowerCase() === 'x' ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10);
        return n > 0 && n <= 0x10ffff ? String.fromCodePoint(n) : '\ufffd';
      }
      return ENT[e.toLowerCase()] || m; // only the six names above (2-8 letters: never a prototype key)
    });
  }
  function attrs(s) {
    var out = {}, re = /([^\s"'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g, m;
    while ((m = re.exec(s))) { var k = m[1].toLowerCase(); if (out[k] === undefined) { out[k] = decode(m[2] !== undefined ? m[2] : m[3] !== undefined ? m[3] : m[4] || ''); } }
    return out;
  }
  function pushText(list, t) { if (!t) { return; } if (typeof list[list.length - 1] === 'string') { list[list.length - 1] += t; } else { list.push(t); } }

  function parseHtml(html) {
    html = String(html).replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
    var root = { n: '#root', c: [] }, stack = [root], i = 0, len = html.length;
    while (i < len) {
      var lt = html.indexOf('<', i), top = stack[stack.length - 1];
      if (lt === -1) { pushText(top.c, decode(html.slice(i))); break; }
      pushText(top.c, decode(html.slice(i, lt)));
      i = lt;
      var rest = html.slice(i), m;
      if (/^<!-{2}/.test(rest)) { var e = html.indexOf('-->', i + 4); i = e === -1 ? len : e + 3; continue; }
      if (/^<[!?]/.test(rest) || /^<\/[^a-zA-Z>]/.test(rest)) { var g = html.indexOf('>', i); i = g === -1 ? len : g + 1; continue; }
      m = /^<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:[^>"']|"[^"]*"|'[^']*')*)>/.exec(rest);
      if (!m) { pushText(top.c, '<'); i++; continue; } // "x < 5" is text
      i += m[0].length;
      var name = m[2].toLowerCase();
      if (m[1]) { // closing tag: close the nearest open element of that name, if any
        for (var s = stack.length - 1; s > 0; s--) { if (stack[s].n === name) { stack.length = s; break; } }
        continue;
      }
      if (DROP[name]) {
        var close = new RegExp('</' + name + '\\s*>', 'i').exec(html.slice(i));
        i = close ? i + close.index + close[0].length : len;
        continue;
      }
      if (name === 'br') { top.c.push({ t: 'br', c: [] }); continue; }
      var node = { n: name, t: ALLOWED[name] ? name : '#', c: [] };
      if (name === 'a') { var a = attrs(m[3]); node.href = url(a.href); if (a.target === '_blank') { node.blank = 1; } if (!node.href) { node.t = '#'; } }
      top.c.push(node);
      stack.push(node);
    }
    return root.c; // '#' = unwrapped tag (children kept); a link inside a link is unwrapped when the DOM is built
  }
  // Text of a message: allow-listed HTML, or the literal text (plain mode).
  function rich(text, allowHtml) { text = str(text, 2000); return allowHtml ? parseHtml(text) : (text ? [text] : []); }
  function plainOf(nodes) { return nodes.map(function (n) { return typeof n === 'string' ? n : n.t === 'br' ? ' ' : n.v || plainOf(n.c); }).join(''); }
  function isBlank(nodes) { return !/\S/.test(plainOf(nodes)) && !nodes.some(function (n) { return n.t === 'cd'; }); }
  // Replaces the first {token} in text with node(s), removes the rest. Returns true when replaced.
  function replaceToken(nodes, token, repl) {
    var done = false;
    (function walk(list) {
      for (var i = 0; i < list.length; i++) {
        var n = list[i];
        if (typeof n !== 'string') { walk(n.c); continue; }
        if (n.indexOf(token) === -1) { continue; }
        var parts = n.split(token), out = [parts[0]];
        for (var p = 1; p < parts.length; p++) { if (!done && repl) { out.push(repl); done = true; } out.push(parts[p]); }
        out = out.filter(function (x) { return x !== ''; });
        list.splice.apply(list, [i, 1].concat(out));
        i += out.length - 1;
      }
    })(nodes);
    return done;
  }
  function copyNodes(nodes) { return JSON.parse(JSON.stringify(nodes)); }

  /* ---------------- URLs, colours, CSS ---------------- */
  // http(s) absolute, or relative ("/x", "x.html", "?a=b", "#top"); never "//host", "javascript:", "data:".
  function url(v) {
    v = str(v).replace(/[\r\n\t\0]/g, '').trim().replace(/ /g, '%20');
    v = v.replace(/[^\x00-\x7F]+/g, function (m) { try { return encodeURIComponent(m); } catch (e) { return ''; } });
    if (!v || v.length > 500 || !/^[A-Za-z0-9\-._~:\/?#\[\]@!$&()*+,;=%]+$/.test(v)) { return ''; }
    if (/^https?:\/\/[A-Za-z0-9.-]+(:\d{1,5})?([\/?#]|$)/i.test(v)) { return v; }
    if (/^\/\//.test(v) || /^[^\/?#]*:/.test(v)) { return ''; } // protocol-relative or any other scheme
    return v;
  }
  function color(v, def) { return /^#[0-9a-f]{6}$/i.test(str(v)) ? v.toLowerCase() : def; }
  // Custom CSS, safe inside <style>: port of Features::css(). (No literal HTML comment opener in this file: it is
  // inlined into <script> elements.)
  function css(v) {
    var c = str(v).replace(/\0/g, '').slice(0, 10000), before;
    c = c.replace(/<!-{2}|-{2}>|<\/?[a-z!][^>]*>?/gi, '').replace(/[<\\]/g, '');
    do {
      before = c;
      c = c.replace(/@(import|charset|namespace)\b[^;{}]*;?/gi, '')
        .replace(/(javascript|vbscript|livescript)\s*:/gi, '')
        .replace(/(expression|behaviou?r|-moz-binding)\s*[:(]?/gi, '')
        .replace(/url\s*\(\s*(['"]?)([\s\S]*?)\1\s*\)/gi, function (m, q, u) {
          u = u.trim();
          return /^(https?:\/\/|\/(?!\/)|data:image\/(png|gif|jpe?g|webp|svg\+xml)[;,])/i.test(u) && !/['"()\s]/.test(u) ? 'url("' + u + '")' : 'none';
        });
    } while (c !== before);
    return c.trim();
  }
  function coupon(v) { v = str(v).trim().toUpperCase(); return /^[A-Z0-9_-]{1,32}$/.test(v) ? v : ''; }
  function clock(v) { var m = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(str(v)); return m ? m[0] : ''; }
  function weekdays(v) { var out = '1234567'.replace(/\d/g, function (d) { return String(v).indexOf(d) !== -1 ? d : ''; }); return out.length === 7 ? '' : out; }
  function words(v, max) { return str(v, 500).toLowerCase().split(',').map(function (s) { return s.trim(); }).filter(Boolean).slice(0, max || 20); }

  /* ---------------- Time zones and schedules ---------------- */
  // Offset (seconds east of UTC) of an IANA zone at a moment; the browser's own zone when unknown.
  function tzOffset(tz, ms) {
    try {
      var p = {};
      new Intl.DateTimeFormat('en-US', { timeZone: tz, hourCycle: 'h23', year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric' })
        .formatToParts(new Date(ms)).forEach(function (x) { p[x.type] = parseInt(x.value, 10); });
      return Math.round((Date.UTC(p.year, p.month - 1, p.day, p.hour % 24, p.minute, p.second) - Math.floor(ms / 1000) * 1000) / 1000);
    } catch (e) { return -new Date(ms).getTimezoneOffset() * 60; }
  }
  // "2026-11-30T23:59" (a date only: 00:00, or 23:59 for an end) in the site's zone → Unix ms; 0 when empty / invalid.
  function zoned(v, tz, endOfDay) {
    var m = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?$/.exec(str(v).trim());
    if (!m) { return 0; }
    var guess = Date.UTC(+m[1], +m[2] - 1, +m[3], m[4] ? +m[4] : endOfDay ? 23 : 0, m[5] ? +m[5] : endOfDay ? 59 : 0);
    return guess - tzOffset(tz, guess - tzOffset(tz, guess) * 1000) * 1000;
  }
  // Recurring cut-off rule for the storefront script: "d|14:00|67" (daily, not Sat/Sun) or "w|14:00|5" (Fridays).
  function recurRule(m) {
    var t = clock(m.recur_time);
    if (!t) { return ''; }
    if (m.recur === 'daily') { return 'd|' + t + '|' + weekdays(m.recur_skip || []).replace(/^$/, ''); }
    return m.recur === 'weekly' ? 'w|' + t + '|' + clamp(m.recur_day, 1, 7, 5) : '';
  }

  /* ---------------- Pages and language ---------------- */
  // Rule types (path + query of the page, no host, case-insensitive): contains, equals (path; a trailing slash does not
  // matter), wildcard ("*" = anything, everything else literal). Wildcards are matched without regular expressions
  // (linear backtracking only), so no rule can hang the page.
  function glob(p, s) {
    var i = 0, j = 0, star = -1, mark = 0;
    while (j < s.length) {
      if (p[i] === '*') { star = i++; mark = j; } else if (i < p.length && p[i] === s[j]) { i++; j++; } else if (star < 0) { return false; } else { i = star + 1; j = ++mark; }
    }
    while (p[i] === '*') { i++; }
    return i === p.length;
  }
  function pageRule(rule, loc) {
    var v = str(rule && rule.value, 300).trim().toLowerCase(), path = String(loc.pathname || '/').toLowerCase(), full = path + String(loc.search || '').toLowerCase();
    var norm = function (x) { return x.length > 1 ? x.replace(/\/+$/, '') : x; };
    if (!v) { return false; }
    if (rule.type === 'equals') { return norm(v) === norm(path) || v === full; }
    if (rule.type === 'wildcard') { return glob(v, full); }
    return full.indexOf(v) !== -1;
  }
  function pagesOk(pages, loc) {
    if (!pages || pages.mode === 'all' || !pages.rules.length) { return true; }
    var hit = pages.rules.some(function (r) { return pageRule(r, loc); });
    return pages.mode === 'exclude' ? !hit : hit;
  }
  // Language of the page: the first URL segment (/lv/...), ?lang=lv, then <html lang>; only languages of the config count.
  function pageLang(htmlLang, loc, langs, def) {
    var ok = function (l) { l = String(l || '').toLowerCase().split(/[-_]/)[0]; return langs.indexOf(l) !== -1 ? l : ''; };
    return ok((/^\/([a-z]{2})(?:[-_][a-z]{2})?(?:\/|$)/i.exec(loc.pathname || '') || [])[1]) || ok((/[?&]lang=([a-z]{2})\b/i.exec(loc.search || '') || [])[1]) || ok(htmlLang) || def;
  }
  function pick(v, lang, def) {
    if (typeof v === 'string') { return v; }
    if (!v || typeof v !== 'object') { return ''; }
    return str(v[lang] || v[def]); // lang / def are checked two-letter codes
  }

  /* ---------------- Free-shipping goal (cart from the page's adapter) ---------------- */
  function money(amount, cur, lang) {
    try { return new Intl.NumberFormat(lang || 'en', { style: 'currency', currency: cur }).format(amount); } catch (e) { return amount.toFixed(2) + ' ' + cur; }
  }
  // Amount for the cart's currency: a number (in settings.currency) or {"EUR": 50, "USD": 60}. Never converted.
  // Returns null when it cannot show (no cart yet, no amount for this currency).
  function goal(threshold, cart, base, lang) {
    if (!cart) { return null; }
    var sub = +cart.subtotal || 0, cur = String(cart.currency || base).toUpperCase();
    var amt = typeof threshold === 'object' ? +threshold[cur] || 0 : cur === base ? +threshold || 0 : 0;
    return amt > 0 ? { done: sub >= amt, pct: Math.min(100, Math.floor(sub * 100 / amt)), remaining: money(Math.max(0, amt - sub), cur, lang) } : null;
  }

  // 12 hex characters (dismiss key: a visible change shows a closed message again).
  function hash12(s) {
    var a = 0x811c9dc5, b = 5381;
    for (var i = 0; i < s.length; i++) { a = Math.imul(a ^ s.charCodeAt(i), 16777619) >>> 0; b = (Math.imul(b, 33) + s.charCodeAt(i)) >>> 0; }
    return ('0000000' + a.toString(16)).slice(-8) + ('000' + (b & 0xffff).toString(16)).slice(-4);
  }

  /* ---------------- Config ---------------- */
  var LABELS = { label: 'Announcement', close: 'Close', days: 'd', copy: 'Copy', copied: 'Copied!', copy_code: 'Copy code %s', prev: 'Previous message', next: 'Next message', show: 'Show announcements', pause: 'Pause messages', play: 'Play messages' };
  var FONTS = '|system|humanist|geometric|serif|mono|rounded';
  /* Config schema: "key:kind:arg". Kinds: e = one of (first is the default), n = integer min|max|default, t = true unless
     false, f = false unless true, x = text or {lang: text}, u = URL, k = HH:MM, w = weekdays, c = colour, s = string (max
     length), l = comma list (lower case). Anything else in the JSON is ignored. */
  var SETTINGS = 'position:e:top|bottom,mode:e:rotate|marquee|stack,transition:e:fade|slide,interval:n:2|60|5,speed:n:20|300|60,font_size:n:10|30|14,' +
    'padding:n:0|30|8,sticky:t,arrows:t,dismissible:t,dismiss_days:n:0|365|1,pause_button:t,autohide:n:0|600|0,max_messages:n:0|20|0,' +
    'color_mode:e:custom|auto,font:e:' + FONTS + ',ga4:f,consent_wait:f,allow_html:t';
  var MESSAGE = 'enabled:t,priority:n:0|100|0,placement:e:|top|bottom,type:e:standard|free_shipping,text:x,mobile_text:x,link:u,link_text:x,' +
    'new_tab:f,button_style:e:outline|filled|link,bg_color:c:#222222,text_color:c:#ffffff,style:e:solid|gradient|soft|minimal|glass,' +
    'font:e:' + FONTS + ',image:u,overlay:n:0|90|55,countdown:f,date_start:s:20,date_end:s:20,recur:e:|daily|weekly,recur_time:k,' +
    'recur_day:n:1|7|5,recur_skip:w,weekdays:w,time_start:k,time_end:k,device:e:all|desktop|mobile,utm_source:l,utm_medium:l,' +
    'utm_campaign:l,referrer:l,visitor:e:all|new|returning,trigger:e:none|delay|scroll|exit,success_text:x,progress_bar:t';
  function schema(def, src) {
    var o = {};
    def.split(',').forEach(function (d) {
      d = d.split(':');
      var k = d[0], a = (d[2] || '').split('|'), v = src[k];
      var t = d[1];
      o[k] = t === 'e' ? oneOf(v, a) : t === 'n' ? clamp(v, +a[0], +a[1], +a[2]) : t === 't' ? v !== false : t === 'f' ? v === true : t === 'x' ? texts(v) :
        t === 'u' ? url(v) : t === 'k' ? clock(v) : t === 'w' ? weekdays(v || '') : t === 'c' ? color(v, a[0]) : t === 's' ? str(v, +a[0]).trim() : words(v).join(',');
    });
    return o;
  }
  function normalize(cfg) {
    cfg = cfg && typeof cfg === 'object' ? cfg : {};
    var s = cfg.settings && typeof cfg.settings === 'object' ? cfg.settings : {}, set = schema(SETTINGS, s);
    var langs = (Array.isArray(s.languages) ? s.languages : []).map(function (l) { return str(l).toLowerCase(); }).filter(function (l) { return /^[a-z]{2}$/.test(l); }).slice(0, 30);
    var def = /^[a-z]{2}$/.test(str(s.default_language)) ? s.default_language : langs[0] || 'en';
    if (langs.indexOf(def) === -1) { langs.unshift(def); }
    set.languages = langs; set.default_language = def; set.labels = s.labels && typeof s.labels === 'object' ? s.labels : {}; // strings checked when used
    set.custom_css = css(s.custom_css); set.currency = /^[A-Z]{3}$/.test(str(s.currency)) ? s.currency : 'EUR';
    set.timezone = /^[A-Za-z][\w+\/-]{1,40}$/.test(str(s.timezone)) ? s.timezone : ''; // unknown zone: the visitor's own
    return { version: 1, settings: set, messages: (Array.isArray(cfg.messages) ? cfg.messages : []).slice(0, 100).map(message).filter(Boolean) };
  }
  function texts(v) {
    if (typeof v === 'string') { return str(v, 2000); }
    var o = {};
    if (v && typeof v === 'object') { Object.keys(v).forEach(function (k) { if (/^[a-z]{2}$/.test(k)) { o[k] = str(v[k], 2000); } }); }
    return o;
  }
  function message(m, i) {
    if (!m || typeof m !== 'object') { return null; }
    var o = schema(MESSAGE, m), p = m.pages && typeof m.pages === 'object' ? m.pages : {}, t = m.threshold;
    o.id = clamp(m.id, 1, 999999, i + 1);
    o.icon = str(m.icon, 16).replace(/[<>&"'\\]/g, '').trim();
    o.trigger_value = o.trigger === 'scroll' ? clamp(m.trigger_value, 1, 100, 50) : clamp(m.trigger_value, 1, 600, 5);
    o.referrer = o.referrer.split(',').map(function (h) { return h.replace(/^(https?:\/\/)?(www\.)?/, '').split(/[\/:?#]/)[0]; }).join(',');
    o.coupon = coupon(m.coupon);
    o.languages = (Array.isArray(m.languages) ? m.languages : []).filter(function (l) { return /^[a-z]{2}$/.test(l); });
    o.pages = { mode: oneOf(p.mode, ['all', 'include', 'exclude']), rules: (Array.isArray(p.rules) ? p.rules : []).slice(0, 50).map(function (r) {
      return { type: oneOf(r && r.type, ['contains', 'equals', 'wildcard']), value: str(r && r.value, 300).trim() };
    }).filter(function (r) { return r.value; }) };
    o.threshold = {};
    if (t && typeof t === 'object') { Object.keys(t).forEach(function (k) { if (/^[A-Z]{3}$/.test(k) && +t[k] > 0) { o.threshold[k] = +t[k]; } }); } else { o.threshold = +t > 0 ? +t : 0; }
    if (o.time_start === o.time_end || !o.time_start || !o.time_end) { o.time_start = o.time_end = ''; }
    return o;
  }

  /* ---------------- What to show on this page ----------------
     ctx: {now, lang, loc, cart}. Returns {top: [item], bottom: [item]}; items carry plain data only (text as node
     trees), the DOM is built by the runtime. Device, weekly schedule, campaign, visitor, triggers and the next recurring
     cut-off are decided in the browser by the shared storefront scripts (data-* attributes), as in every other edition. */
  function variantOf(m, lang, def, allowHtml, g, end, recur) {
    var html = rich(pick(m.text, lang, def), allowHtml), html_m = rich(pick(m.mobile_text, lang, def), allowHtml);
    if (isBlank(html_m)) { html_m = []; }
    var seen = JSON.stringify(html) + (html_m.length ? '|m|' + JSON.stringify(html_m) : '');
    if (g) {
      var success = g.done ? rich(pick(m.success_text, lang, def), allowHtml) : [];
      if (!isBlank(success)) { html = success; html_m = []; } else {
        replaceToken(html, '{remaining}', g.remaining && { t: 'rem', v: g.remaining, c: [] });
        replaceToken(html_m, '{remaining}', g.remaining && { t: 'rem', v: g.remaining, c: [] });
      }
    }
    var cd = end || recur ? { t: 'cd', end: end, recur: recur, c: [] } : null;
    var rep = replaceToken(html, '{countdown}', cd), repM = html_m.length ? replaceToken(html_m, '{countdown}', cd && copyNodes(cd)) : false;
    if (isBlank(html) && isBlank(html_m) && !cd) { return null; }
    if (cd && !rep) { html.push(' ', copyNodes(cd)); }
    if (cd && html_m.length && !repM) { html_m.push(' ', copyNodes(cd)); }
    return { d: html, m: html_m, seen: seen, link_text: pick(m.link_text, lang, def).replace(/\s+/g, ' ').trim().slice(0, 80) };
  }

  function select(cfg, ctx) {
    var s = cfg.settings, out = { top: [], bottom: [] }, lang = ctx.lang, def = s.default_language;
    cfg.messages.forEach(function (m, order) {
      if (!m.enabled || (m.languages.length && m.languages.indexOf(lang) === -1) || !pagesOk(m.pages, ctx.loc)) { return; }
      var start = zoned(m.date_start, s.timezone, false), end = zoned(m.date_end, s.timezone, true);
      if ((start && start > ctx.now) || (end && end <= ctx.now)) { return; }
      var fs = m.type === 'free_shipping', g = fs ? goal(m.threshold, ctx.cart, s.currency, lang) : null;
      // A recurring countdown gets its next cut-off from the storefront script (data-recur, site time zone).
      var recur = m.countdown && m.recur ? recurRule(m) : '';
      var v = variantOf(m, lang, def, s.allow_html, g || (fs ? { remaining: '' } : null), m.countdown && !recur ? end : 0, recur);
      if (!v) { return; }
      var seen = v.seen + (m.coupon ? '|c|' + m.coupon : '') + (fs ? '|fs|' + JSON.stringify(m.threshold) + '|' + pick(m.success_text, lang, def) : '') + (m.image ? '|i|' + m.image : '');
      v.wrap = !!m.link && !v.link_text && !hasLink(v.d) && !hasLink(v.m);
      // The item: the cleaned message (m) + what this page view adds.
      out[m.placement || s.position].push({
        m: m, id: m.id, order: order, key: hash12(m.id + '|' + seen + '|' + v.link_text + '|' + m.link + '|' + m.icon + '|' + lang), v: v, end: end, fs: fs, goal: g,
        utm: m.utm_source || m.utm_medium || m.utm_campaign || m.referrer ? JSON.stringify({ s: words(m.utm_source), m: words(m.utm_medium), c: words(m.utm_campaign), r: words(m.referrer) }) : '',
        trig: m.trigger === 'delay' || m.trigger === 'scroll' ? m.trigger + ':' + m.trigger_value : m.trigger === 'exit' ? 'exit' : ''
      });
    });
    ['top', 'bottom'].forEach(function (p) { out[p].sort(function (a, b) { return b.m.priority - a.m.priority || a.order - b.order; }); });
    return out;
  }
  function hasLink(nodes) { return nodes.some(function (n) { return typeof n !== 'string' && (n.t === 'a' || hasLink(n.c)); }); }

  return {
    parseHtml: parseHtml, rich: rich, plainOf: plainOf, replaceToken: replaceToken, decode: decode, url: url, color: color, css: css, coupon: coupon,
    clock: clock, weekdays: weekdays, tzOffset: tzOffset, zoned: zoned, recurRule: recurRule, pageRule: pageRule, pagesOk: pagesOk, pageLang: pageLang,
    pick: pick, goal: goal, money: money, hash12: hash12, normalize: normalize, select: select, LABELS: LABELS, ALLOWED: ALLOWED
  };
})();
if (typeof module === 'object' && module.exports) { module.exports = ABPCore; }
