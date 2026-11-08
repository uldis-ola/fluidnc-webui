# User guide — Configurator and features

Open `configurator/index.html` in a browser. Everything you do is saved in that browser (localStorage) until you press
**Reset**. On the right you see the **live preview** (the real runtime on a sample page; Desktop / Phone, page
language, cart subtotal for free-shipping messages) and **Install on your site** (snippets and the JSON).

## Messages
- **Add message** or **From a template** (12 ready messages: free shipping, flash sale with countdown, same-day cut-off,
  welcome coupon, newsletter link, new arrivals, back in stock, holiday, weekend, exit offer, B2B, privacy). Template
  texts exist in English, Latvian and Russian; other languages start with the English text.
- The list shows colour, text, priority and badges. ⧉ duplicates, ✕ deletes. Higher **priority** (0–100) shows first.
- **Placement**: the bar's default position, the top bar or the bottom bar (both bars can be on screen).

## Content
- **Text** per language, plus an optional **shorter text for phones** (below 768 px).
- With "Allow simple HTML" (Bar settings): `<b> <strong> <i> <em> <u> <s> <br> <span> <a href>`. Everything else is
  removed by an allow-list filter in the browser; links must be `https://`, `http://` or a site path. Nothing from the
  config is ever inserted as HTML.
- **Placeholders**: `{countdown}` (timer), `{remaining}` (free shipping: amount still missing).
- **Icon**: an emoji or a short text (no icon font is needed).
- **Link** + **button text**: with a button text a button is shown; without it the whole message is the link.
- **Coupon code** with a **Copy** button (clipboard; "Copied!" is visible and announced to screen readers).
- **E-mail sign-up**: this edition has no server, so it does not collect addresses. Use the "Newsletter" template: a
  button that links to your own sign-up form (Shopify customer form, Mailchimp, MailerLite …).

## Countdown and schedule
- **Countdown** to the end date, or **every day / every week** at a cut-off time ("Order within {countdown} for
  same-day dispatch"), with days to skip.
- **Start / End**, **daily window** (e.g. 09:00–17:00; a window past midnight belongs to the day it starts) and
  **weekdays** — all in your shop's **time zone** (Bar settings), whatever the visitor's clock zone is.
  The visitor's device clock is used as "now".

## Look
- **Styles**: Solid (your colours exactly), Gradient, Soft, Minimal, Glass. All styles except Solid keep text contrast
  at least 4.5:1 automatically; for Solid the Configurator shows the contrast of your colours.
- **Colours: Automatic** (Bar settings) takes your site's main colour (buttons, menu) and a readable text colour.
- **Background image** URL with your colour laid over it (0–90 %), **system fonts** (nothing is downloaded),
  **custom CSS** (filtered: no `@import`, no `javascript:`, `url()` only for https, site paths and `data:image`).

## Who and where
- **Devices**: all, desktop (768 px and wider) or phones; the bar re-arranges itself on resize / rotation.
- **Pages**: every page, only matching pages or all except matching pages. Rules: *URL contains*, *Path equals*
  (a trailing slash does not matter), *Matches* with `*` as "anything" (every other character is literal — no regular
  expressions). Rules see the path and query (`/collections/sale?page=2`), case-insensitive.
- **Languages**: the page language comes from the URL (`/lv/…`, `?lang=lv`), then `<html lang="…">`. A message can be
  limited to some languages; missing texts fall back to the default language.
- **Campaign**: UTM source / medium / campaign and the site the visitor came from (remembered for the visit).
- **Visitors**: new (first 30 minutes) or returning — one first-party localStorage value, never with DNT / GPC.

## Show it
At once, after N seconds, after scrolling N %, or when the mouse leaves the window (desktop). A message that appeared
once is shown at once on the next pages of the same visit.

## Bar settings
Position, rotate / scrolling / all at once, fade or slide, seconds per message, scrolling speed, most messages at once,
font size, padding, sticky top bar, arrows, **pause button** (WCAG 2.2.2), close button and how long "closed" is
remembered (a message comes back when its text, link, button or icon changes), **fold into a tab** after N seconds,
languages and visitor-facing labels (built in for EN, LV, RU, DE, LT, ET, PL; editable), time zone, currency,
GA4 events (`abp_view`, `abp_click`, `abp_copy` — only when your site already loads GA4 / GTM), wait for cookie consent.

## Free-shipping progress
Message type "Free-shipping progress": `{remaining}`, a success text and a progress bar. It needs the **cart adapter**
on your site (see INSTALL.md §5). Thresholds are per currency and never converted.

## Export / import
- Snippets A, B, C and the JSON (copy or download). **Load the JSON above** imports a config you edited or saved.
- Keep a copy of your JSON: it is your backup.

## Accessibility and privacy (built in)
Labelled region, real buttons with labels, keyboard focus kept when a bar closes, `prefers-reduced-motion`
(scrolling text becomes rotation), RTL pages, no cookies, no requests to us or anybody else.
