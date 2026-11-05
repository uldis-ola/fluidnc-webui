#!/usr/bin/env python3
"""
Tēmu neatkarības tests: tas pats modulis uz vairākām krasi atšķirīgām tēmām (TESTA veikalā).

Tēmas (catalog/view/theme/<nosaukums>/template/product/product.twig) jāizveido iepriekš — skat. README.
Katrai tēmai × novietojumam (replace/before/after) × produktam (ar attēliem / bez) pārbauda:
  skatītājs ielādēts un redzams, nepārklāj produkta nosaukumu/cenu/pogu, galerija paslēpta tikai "replace",
  nav horizontālas ritināšanas (dators un telefons), nav JS kļūdu.

  python tests/e2e_themes.py --themes default,tA_flex,tB_grid,tC_nofooter,tD_stacked --mysql "mysql -uroot oc3" \
      [--chromium /ceļš/uz/chrome] [--shots /mape]
"""
import argparse
import shlex
import subprocess

from playwright.sync_api import sync_playwright

p = argparse.ArgumentParser()
p.add_argument("--base", default="http://127.0.0.1:8080/")
p.add_argument("--themes", default="default")
p.add_argument("--mysql", default="mysql -uroot oc3")
p.add_argument("--with-images", type=int, default=42)
p.add_argument("--without-images", type=int, default=35)
p.add_argument("--chromium")
p.add_argument("--shots", default="")
a = p.parse_args()
BASE = a.base.rstrip("/") + "/"


def sql(q):
    return subprocess.run(shlex.split(a.mysql) + ["-N", "-e", q], capture_output=True, text=True, check=True).stdout.strip()


original_theme = sql("SELECT value FROM oc_setting WHERE `key`='theme_default_directory'")
original_place = sql("SELECT value FROM oc_setting WHERE `key`='module_product_3d_placement'")
model = sql(f"SELECT model_3d FROM oc_product_3d WHERE product_id={a.with_images}")
assert model, "produktam ar attēliem jābūt 3D modelim"
had_noimg = sql(f"SELECT COUNT(*) FROM oc_product_3d WHERE product_id={a.without_images}") == "1"
sql(f"REPLACE INTO oc_product_3d SET product_id={a.without_images}, model_3d='{model}', ios_3d='', poster_3d='', "
    "placement='', camera_orbit='', auto_rotate='', date_modified=NOW()")

CHECK = r"""(mode) => {
  const box = document.getElementById('product-3d');
  if (!box) return {error: 'nav #product-3d'};
  const mv = box.querySelector('model-viewer');
  const r = box.getBoundingClientRect();
  const vis = el => { if (!el) return false; const b = el.getBoundingClientRect(), s = getComputedStyle(el);
    return b.width > 0 && b.height > 0 && s.display !== 'none' && s.visibility !== 'hidden' &&
      !el.closest('[data-product-3d-hidden]'); };
  const overlap = el => { if (!vis(el)) return false; const b = el.getBoundingClientRect();
    return !(b.right <= r.left || b.left >= r.right || b.bottom <= r.top || b.top >= r.bottom); };
  const info = [...document.querySelectorAll('h1, #button-cart, .price, .price-new, .product-price, .pb-name, [onclick*="cart.add"]')]
    .filter(e => !box.contains(e));
  const keys = (box.dataset.imageKeys || '').split('|').filter(Boolean);
  const imgs = [...document.querySelectorAll('img, [style*="background-image"]')].filter(e => !box.contains(e) &&
    keys.some(k => (e.getAttribute('src') + ' ' + e.getAttribute('data-src') + ' ' + e.getAttribute('style')).toLowerCase().includes(k)));
  return {
    loaded: !!(mv && mv.loaded), placed: box.dataset.placed === '1', gallery: box.dataset.gallery || '',
    visible: vis(box), width: Math.round(r.width), height: Math.round(r.height),
    infoVisible: info.filter(vis).length, infoTotal: info.length,
    overlaps: info.filter(overlap).map(e => e.tagName + (e.id ? '#' + e.id : '') + '.' + e.className),
    productImagesVisible: imgs.filter(vis).length, productImagesTotal: imgs.length,
    hscroll: document.documentElement.scrollWidth > window.innerWidth + 1
  };
}"""

failures = []
rows = []

with sync_playwright() as pw:
    opts = {"args": ["--use-gl=angle", "--use-angle=swiftshader", "--enable-unsafe-swiftshader"]}
    if a.chromium:
        opts["executable_path"] = a.chromium
    browser = pw.chromium.launch(**opts)
    try:
        for theme in a.themes.split(","):
            sql(f"UPDATE oc_setting SET value='{theme}' WHERE `key`='theme_default_directory'")
            for mode in ("replace", "before", "after"):
                sql(f"UPDATE oc_setting SET value='{mode}' WHERE `key`='module_product_3d_placement'")
                for pid, label in ((a.with_images, "ar attēliem"), (a.without_images, "bez attēliem")):
                    for vw, vh, dev in ((1366, 900, "dators"), (390, 844, "telefons")):
                        if dev == "telefons" and mode != "replace":
                            continue
                        ctx = browser.new_context(viewport={"width": vw, "height": vh}, is_mobile=dev == "telefons",
                                                  has_touch=dev == "telefons")
                        page = ctx.new_page()
                        errors = []
                        page.on("pageerror", lambda e: errors.append(str(e)))
                        page.goto(f"{BASE}index.php?route=product/product&product_id={pid}")
                        try:
                            page.wait_for_function("() => { const m = document.querySelector('#product-3d model-viewer');"
                                                   " return m && m.loaded; }", timeout=60_000)
                        except Exception:
                            pass
                        page.wait_for_timeout(1200)
                        st = page.evaluate(CHECK, mode)
                        problems = []
                        if st.get("error"):
                            problems.append(st["error"])
                        else:
                            if not (st["loaded"] and st["visible"] and st["placed"]):
                                problems.append("nav ielādēts/redzams")
                            if st["width"] < min(280, vw - 40):
                                problems.append(f"par šauru ({st['width']}px)")
                            if st["overlaps"]:
                                problems.append(f"pārklāj: {st['overlaps']}")
                            if st["infoTotal"] and st["infoVisible"] == 0:
                                problems.append("produkta info paslēpta")
                            if pid == a.with_images and st["productImagesTotal"]:
                                if mode == "replace" and st["productImagesVisible"] > 0 and dev == "dators":
                                    # replace: galvenajai galerijai jābūt paslēptai (sīktēli citur drīkst palikt)
                                    big = page.evaluate("""() => { const b = document.getElementById('product-3d');
                                        const k = (b.dataset.imageKeys||'').split('|').filter(Boolean);
                                        return [...document.querySelectorAll('img,[style*="background-image"]')].filter(e => !b.contains(e) &&
                                          k.some(x => (e.getAttribute('src')+' '+e.getAttribute('data-src')+' '+e.getAttribute('style')).toLowerCase().includes(x)) &&
                                          !e.closest('[data-product-3d-hidden]') && e.getBoundingClientRect().width >= 200).length; }""")
                                    if big:
                                        problems.append(f"replace: {big} lieli produkta attēli joprojām redzami")
                                if mode != "replace" and st["productImagesVisible"] == 0:
                                    problems.append("before/after: galerija pazudusi")
                            tabs_hidden = page.evaluate("() => [...document.querySelectorAll('.nav-tabs, .tab-content')]"
                                                        ".some(e => getComputedStyle(e).display === 'none' || e.closest('[data-product-3d-hidden]'))")
                            if tabs_hidden:
                                problems.append("apraksta cilnes paslēptas")
                            if st["hscroll"]:
                                problems.append("horizontāla ritināšana")
                        if errors:
                            problems.append(f"JS kļūdas: {errors[:2]}")
                        tag = f"{theme:12} {mode:7} {label:12} {dev:8}"
                        status = "OK " if not problems else "ERR"
                        print(f"  {status} {tag} {st.get('width', 0):>4}px  {st.get('gallery', '')}  {'; '.join(problems)}", flush=True)
                        if problems:
                            failures.append((tag, problems))
                        if a.shots and mode == "replace" and pid == a.with_images:
                            page.screenshot(path=f"{a.shots}/theme-{theme}-{dev}.png", full_page=False)
                        ctx.close()
    finally:
        sql(f"UPDATE oc_setting SET value='{original_theme}' WHERE `key`='theme_default_directory'")
        sql(f"UPDATE oc_setting SET value='{original_place}' WHERE `key`='module_product_3d_placement'")
        if not had_noimg:
            sql(f"DELETE FROM oc_product_3d WHERE product_id={a.without_images}")
        browser.close()

print(f"\n{'OK' if not failures else 'KĻŪDAS'}: {len(failures)} problēmas")
raise SystemExit(1 if failures else 0)
