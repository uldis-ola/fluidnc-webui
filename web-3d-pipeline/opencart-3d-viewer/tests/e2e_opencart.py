#!/usr/bin/env python3
"""
E2E tests uz lokāla OpenCart 3.0.x (Playwright).

  python tests/e2e_opencart.py --base http://127.0.0.1:8080/ --user admin --password admin123 \
      --zip dist/product_3d_viewer_v1.0.0.ocmod.zip --glb ../models/chair.web.glb [--chromium /ceļš/uz/chrome]

Soļi: Installer -> instalē moduli -> saglabā iestatījumus -> produkts 42: augšupielādē .glb, saglabā ->
produkta lapā pārbauda skatītāju -> pārbauda, ka citu lapu virsraksti nav bojāti.
"""
import argparse
import re
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

p = argparse.ArgumentParser()
p.add_argument("--base", default="http://127.0.0.1:8080/")
p.add_argument("--user", default="admin")
p.add_argument("--password", default="admin123")
p.add_argument("--zip", required=True)
p.add_argument("--glb", required=True)
p.add_argument("--product", type=int, default=42)
p.add_argument("--chromium")
p.add_argument("--shots", default="")
a = p.parse_args()

BASE = a.base.rstrip("/") + "/"
ok = []


def step(name):
    print(f"  ✓ {name}", flush=True)
    ok.append(name)


with sync_playwright() as pw:
    opts = {"args": ["--use-gl=angle", "--use-angle=swiftshader", "--enable-unsafe-swiftshader"]}
    if a.chromium:
        opts["executable_path"] = a.chromium
    browser = pw.chromium.launch(**opts)
    ctx = browser.new_context(viewport={"width": 1366, "height": 900}, device_scale_factor=2 if a.shots else 1)
    page = ctx.new_page()
    php_errors = []
    page.on("response", lambda r: r.status >= 500 and php_errors.append(f"{r.status} {r.url}"))

    # --- login
    page.goto(BASE + "admin/index.php")
    page.fill("input[name=username]", a.user)
    page.fill("input[name=password]", a.password)
    page.click("button[type=submit]")
    page.wait_for_url(re.compile(r"user_token="))
    token = re.search(r"user_token=([a-zA-Z0-9]+)", page.url).group(1)
    admin = lambda route, extra="": f"{BASE}admin/index.php?route={route}&user_token={token}{extra}"
    step("admin login")

    # --- Installer
    page.goto(admin("marketplace/installer"))
    with page.expect_file_chooser() as fc:
        page.click("#button-upload")
    fc.value.set_files(a.zip)
    page.wait_for_function("() => /Success|success|Veiksm|Успешно/.test(document.querySelector('#progress-text, .alert-success, #content')?.innerText || '')", timeout=60_000)
    text = page.inner_text("#content")
    assert "error" not in text.lower() or "success" in text.lower(), text[:500]
    step("Installer: pakotne augšupielādēta")

    # --- Extensions > Modules > install
    page.goto(admin("marketplace/extension", "&type=module"))
    row = page.locator("#extension tr", has_text="3D Product Viewer")
    expect(row).to_have_count(1, timeout=20_000)
    install = row.locator("a.btn-success")
    if install.count():
        install.click()
        page.wait_for_timeout(1500)
        row = page.locator("#extension tr", has_text="3D Product Viewer")
    expect(row.locator("a.btn-primary")).to_have_count(1, timeout=20_000)
    step("modulis instalēts")

    # --- settings save
    row.locator("a.btn-primary").click()
    page.wait_for_selector("#form-product-3d")
    page.select_option("#p3d-placement", "replace")
    page.fill("#p3d-height", "520")
    page.click("button[form=form-product-3d]")
    page.wait_for_url(re.compile(r"marketplace/extension"))
    assert page.locator(".alert-success").count() == 1
    step("iestatījumi saglabāti")

    # --- menu entry under Catalog
    page.goto(admin("common/dashboard"))
    assert page.locator("#menu-catalog a", has_text="3D Product Viewer").count() == 1
    assert page.title().strip() == "Dashboard", page.title()   # globālie teksti nav pārrakstīti
    step("izvēlnes ieraksts Catalog sadaļā + virsraksti nav bojāti")

    # --- product form tab + upload
    page.goto(admin("catalog/product/edit", f"&product_id={a.product}"))
    tab = page.locator("#form-product > .nav-tabs a[href='#tab-product-3d']")
    expect(tab).to_have_count(1)
    tab.click()
    row3d = page.locator(".p3d-file[data-type=model]")
    with page.expect_file_chooser() as fc:
        row3d.locator(".p3d-upload").click()
    fc.value.set_files(a.glb)
    expect(row3d.locator(".p3d-msg")).to_contain_text("KB", timeout=30_000)
    path = page.input_value("#p3d-model_3d")
    assert path.startswith("catalog/3d/") and path.endswith(".glb"), path
    step(f"augšupielāde: {path}")

    # nederīgs fails tiek noraidīts
    bad = Path(a.glb).with_name("_fake.glb")
    bad.write_bytes(b"<?php echo 1; ?>" + b"0" * 100)
    with page.expect_file_chooser() as fc:
        row3d.locator(".p3d-upload").click()
    fc.value.set_files(str(bad))
    expect(row3d.locator(".p3d-msg.text-danger")).to_be_visible(timeout=15_000)
    bad.unlink()
    assert page.input_value("#p3d-model_3d") == path
    step("viltots .glb noraidīts")

    page.wait_for_function("() => { const m = document.getElementById('p3d-preview'); return m && m.loaded; }", timeout=60_000)
    step("admin priekšskatījums ielādēts")
    if a.shots:
        page.screenshot(path=f"{a.shots}/oc-admin-tab.png")

    page.click("button[form=form-product]")
    page.wait_for_url(re.compile(r"catalog/product(&|$)"), timeout=30_000)
    assert page.locator(".alert-success").count() == 1
    page.goto(admin("catalog/product/edit", f"&product_id={a.product}"))
    assert page.input_value("#p3d-model_3d") == path
    step("produkts saglabāts, 3D ceļš saglabājas DB")

    # --- storefront
    shop = ctx.new_page()
    shop_errors = []
    shop.on("pageerror", lambda e: shop_errors.append(str(e)))
    shop.on("response", lambda r: r.status >= 400 and not r.url.endswith("favicon.ico") and shop_errors.append(f"{r.status} {r.url}"))
    external = []
    shop.on("request", lambda r: not r.url.startswith(BASE) and not r.url.startswith(("data:", "blob:")) and external.append(r.url))
    shop.goto(f"{BASE}index.php?route=product/product&product_id={a.product}")
    shop.wait_for_function("() => { const m = document.querySelector('#product-3d model-viewer'); return m && m.loaded; }", timeout=60_000)
    assert shop.is_visible("#product-3d")
    assert shop.locator("ul.thumbnails").evaluate("e => getComputedStyle(e).display") == "none"
    box = shop.locator("#product-3d").bounding_box()
    assert 500 <= box["height"] <= 600, box
    src = shop.get_attribute("#product-3d model-viewer", "src")
    assert src.endswith(path.split("/")[-1]), src
    dims = shop.evaluate("() => { const d = document.querySelector('#product-3d model-viewer').getDimensions(); return [d.x, d.y, d.z]; }")
    assert dims[1] > 0
    assert not [u for u in external if re.search(r"model-viewer|draco|meshopt|basis|ajax\.googleapis|jsdelivr", u)], external
    step(f"veikalā: skatītājs galerijas vietā, augstums {box['height']:.0f}px, izmēri {[round(v, 3) for v in dims]} m, bez CDN")
    if a.shots:
        shop.wait_for_timeout(1500)
        shop.screenshot(path=f"{a.shots}/oc-product.png")
        m = ctx.new_page()
        m.set_viewport_size({"width": 390, "height": 844})
        m.goto(f"{BASE}index.php?route=product/product&product_id={a.product}")
        m.wait_for_function("() => { const x = document.querySelector('#product-3d model-viewer'); return x && x.loaded; }", timeout=60_000)
        m.wait_for_timeout(1500)
        assert m.evaluate("document.documentElement.scrollWidth") <= 390 + 1
        m.screenshot(path=f"{a.shots}/oc-product-mobile.png")
        step("mobilais skats bez horizontālas ritināšanas")
    assert shop_errors == [], shop_errors

    # produkts bez 3D modeļa - nekas nemainās
    shop.goto(f"{BASE}index.php?route=product/product&product_id=40")
    assert shop.locator("#product-3d").count() == 0 and shop.is_visible("ul.thumbnails")
    step("produkts bez 3D: galerija neskarta")

    # --- per-product disable
    page.goto(admin("catalog/product/edit", f"&product_id={a.product}"))
    page.click("a[href='#tab-product-3d']")
    page.select_option("#p3d-placement", "disabled")
    page.click("button[form=form-product]")
    page.wait_for_url(re.compile(r"catalog/product(&|$)"))
    shop.goto(f"{BASE}index.php?route=product/product&product_id={a.product}")
    assert shop.locator("#product-3d").count() == 0
    page.goto(admin("catalog/product/edit", f"&product_id={a.product}"))
    page.click("a[href='#tab-product-3d']")
    page.select_option("#p3d-placement", "")
    page.click("button[form=form-product]")
    page.wait_for_url(re.compile(r"catalog/product(&|$)"))
    step("produktam 'Nerādīt' strādā un atjaunojas")

    assert php_errors == [], php_errors
    browser.close()

print(f"\nOK: {len(ok)} soļi")
