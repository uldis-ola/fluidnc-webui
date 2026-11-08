/* Local static server for the E2E pages (never a live site): serves this edition's folder, a fake Shopify cart
   (/cart.js, /cart/add.js) and Content-Security-Policy headers for the CSP pages. node tests/e2e/server.js [port] */
'use strict';
var http = require('http'), fs = require('fs'), path = require('path');
var ROOT = path.resolve(__dirname, '../..');
var TYPES = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.json': 'application/json', '.png': 'image/png', '.svg': 'image/svg+xml' };
var CSP = {
  '/tests/e2e/pages/csp.html': "default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
  '/tests/e2e/pages/csp-nonce.html': "default-src 'none'; script-src 'self' 'nonce-t3st4bp'; style-src 'self' 'nonce-t3st4bp'; connect-src 'self'; img-src 'self'; base-uri 'none'"
};
var cart = { items: 0, cents: 0 };

function start(port, cb) {
  var server = http.createServer(function (req, res) {
    var u = new URL(req.url, 'http://localhost'), p = decodeURIComponent(u.pathname);
    if (p === '/cart.js') {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      return res.end(JSON.stringify({ item_count: cart.items, total_price: cart.cents, items_subtotal_price: cart.cents, currency: 'EUR' }));
    }
    if (p === '/cart/add.js' && req.method === 'POST') { cart.items++; cart.cents += 1750; res.writeHead(200, { 'Content-Type': 'application/json' }); return res.end('{"id":1}'); }
    if (p === '/cart/clear.js') { cart = { items: 0, cents: 0 }; res.writeHead(200, { 'Content-Type': 'application/json' }); return res.end('{}'); }
    var file = path.join(ROOT, p.replace(/\/$/, '/index.html'));
    if (file.indexOf(ROOT) !== 0 || !fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); return res.end('not found'); }
    var head = { 'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream', 'Cache-Control': 'no-store' };
    if (CSP[p]) { head['Content-Security-Policy'] = CSP[p]; }
    res.writeHead(200, head);
    fs.createReadStream(file).pipe(res);
  });
  server.listen(port, '127.0.0.1', function () { cb && cb(server); });
  return server;
}
module.exports = { start: start, reset: function () { cart = { items: 0, cents: 0 }; } };
if (require.main === module) { start(+process.argv[2] || 8766, function () { console.log('http://127.0.0.1:' + (+process.argv[2] || 8766) + '/'); }); }
