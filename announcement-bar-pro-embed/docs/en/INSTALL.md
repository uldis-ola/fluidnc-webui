# Installing Announcement Bar Pro Embed

You need two things: the **files** (`abp-embed.min.js` + `abp-embed.min.css` from the `abp-embed/` folder of the
package) and a **config** made in the Configurator (`configurator/index.html` — open it in your browser by double-click;
it works offline and sends nothing anywhere).

There are three ways to install. Pick one:

| | What you paste | Hosting needed | Strict CSP OK |
|---|---|---|---|
| **A** (recommended) | config in a `<script type="application/json">` + one `<script src>` | the 2 files | yes |
| **B** | one `<script src … data-config="…/abp-config.json">` | the 2 files + the JSON | yes (`connect-src`) |
| **C** all-in-one | styles + config + script in one block (~70 KB) | nothing | no |

After any change in the Configurator, paste the new snippet (A / C) or upload the new JSON (B).

## 1. Host the two files (A and B)
Upload `abp-embed.min.js` and `abp-embed.min.css` **to the same folder** (the script loads the `.css` with the same
name next to itself). Any static hosting works: your own server, your platform's file/asset storage or a CDN you control.
Enter that folder URL in the Configurator ("Install on your site"); the snippets are then ready to copy.

## 2. Platform steps

### Shopify
1. *Online Store → Themes → … → Edit code → Assets → Add a new asset*: upload `abp-embed.min.js` and `abp-embed.min.css`.
2. Open `layout/theme.liquid`, paste snippet **A** just before `</body>` and replace the script line with
   `<script src="{{ 'abp-embed.min.js' | asset_url }}" defer></script>` (the CSS is found next to it automatically).
3. Free-shipping progress (optional): paste the contents of `examples/cart-adapter-shopify.js` into a
   `<script>…</script>` block in the same place. It reads Shopify's own `/cart.js` and updates the bar after AJAX cart
   changes. Set the threshold per currency in the Configurator if you sell in several currencies.

### Wix
Wix cannot host `.js` files. Use **C (all-in-one)**: *Settings → Custom Code → + Add Custom Code* → paste the code,
"All pages", place it in **Body – end**, apply. (Custom Code needs a Wix plan with a connected domain.) If Wix refuses
the length, host the two files elsewhere (e.g. your own server) and use A.

### Squarespace
1. Upload the two files: in any text block add a link → *File* → upload; Squarespace gives a `/s/…` URL per file.
   Both files must end up with the same name base in the same folder: if they do not, add `data-css="<css URL>"` to the
   script tag.
2. *Settings → Advanced → Code Injection → Footer*: paste snippet **A**. (Code Injection needs a Business plan or higher.)
   Without hosting: use **C** in the Footer box.

### Webflow
*Site settings → Custom code → Footer code*: paste snippet **A** (files hosted on your server / CDN) or **C**. Publish.
Page-level custom code works too (*Page settings → Before </body> tag*).

### Tilda
Add block **T123 (HTML)** at the end of each page (or in the footer) and paste **C**, or A with your hosted files.
Publish all pages.

### Plain HTML / any CMS
Paste **A** before `</body>` on every page (or in your shared footer template). Paths may be relative, e.g.
`<script src="/assets/abp/abp-embed.min.js" defer></script>`.

## 3. Content-Security-Policy
The runtime uses no `eval`, no inline event handlers and no inline `style` attributes (styles are set through CSSOM).
With A or B a policy like this is enough:
```
script-src 'self'; style-src 'self'; connect-src 'self'   (connect-src only for B)
```
- The inline config of A is `type="application/json"`: it is data, never executed, so it needs no `'unsafe-inline'`.
- **Custom CSS**: added as a constructed stylesheet (no `'unsafe-inline'` needed in current browsers). If your policy
  uses nonces, put the nonce on the script tag (`<script nonce="…" src=…>`) or as `data-nonce="…"`; the stylesheet link
  and custom CSS then carry it.
- C (all-in-one) is inline code and needs `'unsafe-inline'`: do not use it with a strict policy.

## 4. Script attributes
| Attribute | Meaning |
|---|---|
| `data-config="#abp-config"` | id of the inline JSON tag (this is the default) |
| `data-config="https://…/abp-config.json"` | JSON file (http/https or a site path); fetched without cookies |
| `data-css="none"` | do not load the stylesheet (you include it yourself) |
| `data-css="https://…/abp.css"` | stylesheet at another URL |
| `data-nonce="…"` | CSP nonce for the stylesheet and custom CSS |

## 5. Free-shipping progress (cart adapter)
The bar has no server and never reads a cart by itself. Free-shipping messages stay hidden until the page calls:
```js
ABP.setCart({ subtotal: 42.50, currency: 'EUR', count: 3 });
```
- Shopify: ready adapter `examples/cart-adapter-shopify.js`.
- Any other platform: `examples/cart-adapter-generic.js` shows the call; you (or your developer) need to know where your
  platform exposes the cart total. Without that, do not use free-shipping messages.
- Amounts are never converted between currencies; a cart in a currency without a threshold hides the message.

## 6. Cookie consent
With "Wait for cookie consent" on, nothing is stored or read for statistics-like features (visitor memory, campaign,
GA4) until your consent tool runs:
```js
window.abpConsent = true; document.dispatchEvent(new Event('abp:consent'));
```
Do Not Track and Global Privacy Control are always respected. Closing a message is remembered in localStorage (a
functional setting); set "Remember closed" to 0 to keep it for the browser session only.

## 7. Check
Open your site: the bar should appear within a second. If not, open the browser console: messages start with
"Announcement Bar Pro:" (no config found, bad JSON, CSS not found). Support: **extension@3dp.lv**.
