# Licensing — why MIT for the embed edition

**Recommendation: MIT** (chosen in `LICENSE.txt`). The OpenCart and WordPress editions stay GPL.

## Why not GPL here
- An embed is **delivered to every visitor's browser**. Under the GPL that is conveying object code (the minified
  file), with the duty to offer the Corresponding Source to each recipient. For a shop owner that is an unusual legal
  chore for a banner script, and agencies often reject GPL front-end code in client sites.
- The GPL on the OpenCart / WordPress editions follows from those platforms (GPL code that links into them). A
  standalone script tag has no such reason.
- MIT is the norm for embeddable widgets; buyers recognise it without legal review.

## Is MIT allowed for code shared with the GPL editions?
Yes, **provided 3DP SIA is the only copyright holder** of the shared storefront files (`announcement_bar_pro.js`,
`announcement_bar_pro_pre.js`, `announcement_bar_pro.css`): a copyright holder may release the same code under several
licences, and the GPL editions are unaffected. **Before selling, confirm that no outside contributor's code is in the
shared files** (otherwise their consent or a rewrite is needed).

## Consequence to accept
MIT allows anyone to copy, resell or modify the embed (in practice the same holds for GPL software). What 3DP SIA sells
is the ready package, the Configurator, documentation, updates and support. No licence key and no enforcement are built
in (none would survive in client-side JavaScript anyway).

## Suggested commercial terms (README only, adjustable)
$29 one-time per domain, including 1.x updates and e-mail support (extension@3dp.lv). Purely contractual.

## Alternative
GPL-3.0-or-later keeps the embed consistent with the other editions; the package already ships the unminified
`abp-embed.js` and `source/`, so the source-offer duty is easy to meet. Choose it only if copyleft matters more than
buyer friction.
