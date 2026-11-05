#!/usr/bin/env python3
"""
Regresijas testi neatkarīgās pārbaudes atradumiem (v1.0.1) uz lokāla OpenCart 3.0.x TESTA veikala.
Maina DB un failus — nepalaist dzīvā veikalā.

  python tests/e2e_regression.py --zip dist/product_3d_viewer_v1.0.1.ocmod.zip --glb ../models/chair.web.glb \
      --site-root /tmp/ocsite --mysql "mysql -uroot oc3" [--chromium /ceļš/uz/chrome]
"""
import argparse
import hashlib
import io
import json
import re
import shlex
import shutil
import struct
import subprocess
import zipfile
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

p = argparse.ArgumentParser()
p.add_argument("--base", default="http://127.0.0.1:8080/")
p.add_argument("--alt-base", default="http://localhost:8080/", help="tas pats veikals ar citu hostu (CORS tests)")
p.add_argument("--user", default="admin")
p.add_argument("--password", default="admin123")
p.add_argument("--zip", required=True)
p.add_argument("--glb", required=True)
p.add_argument("--site-root", required=True)
p.add_argument("--mysql", default="mysql -uroot oc3")
p.add_argument("--chromium")
a = p.parse_args()

BASE = a.base.rstrip("/") + "/"
ALT = a.alt_base.rstrip("/") + "/"
ROOT = Path(a.site_root)
D3 = ROOT / "image/catalog/3d"
results = []


def sql(q):
    return subprocess.run(shlex.split(a.mysql) + ["-N", "-e", q], capture_output=True, text=True, check=True).stdout.strip()


def step(name):
    print(f"  ✓ {name}", flush=True)
    results.append(name)


def glb_with_external_uri():
    doc = {"asset": {"version": "2.0"}, "buffers": [{"uri": "https://tracker.example/x.bin", "byteLength": 4}]}
    js = json.dumps(doc).encode()
    js += b" " * ((4 - len(js) % 4) % 4)
    total = 12 + 8 + len(js)
    return struct.pack("<4sII", b"glTF", 2, total) + struct.pack("<II", len(js), 0x4E4F534A) + js


with sync_playwright() as pw:
    opts = {"args": ["--use-gl=angle", "--use-angle=swiftshader", "--enable-unsafe-swiftshader"]}
    if a.chromium:
        opts["executable_path"] = a.chromium
    browser = pw.chromium.launch(**opts)

    def login(user, password):
        ctx = browser.new_context(viewport={"width": 1366, "height": 900})
        page = ctx.new_page()
        page.on("dialog", lambda d: d.accept())
        page.goto(BASE + "admin/index.php")
        page.fill("input[name=username]", user)
        page.fill("input[name=password]", password)
        page.click("button[type=submit]")
        page.wait_for_url(re.compile(r"user_token="))
        token = re.search(r"user_token=(\w+)", page.url).group(1)
        return page, (lambda route, extra="": f"{BASE}admin/index.php?route={route}&user_token={token}{extra}")

    page, admin = login(a.user, a.password)

    # --- atjaunināšana: Installer + Uninstall/Install (jaunais copyProduct notikums)
    page.goto(admin("marketplace/installer"))
    with page.expect_file_chooser() as fc:
        page.click("#button-upload")
    fc.value.set_files(a.zip)
    page.wait_for_function("() => /Success|success/.test(document.querySelector('#content').innerText)", timeout=60_000)
    for btn in ("a.btn-danger", "a.btn-success"):
        page.goto(admin("marketplace/extension", "&type=module"))
        row = page.locator("#extension tr", has_text="3D Product Viewer")
        row.locator(btn).click()
        page.wait_for_timeout(1500)
    assert sql("SELECT COUNT(*) FROM oc_event WHERE code='product_3d'") == "7"
    assert sql("SELECT COUNT(*) FROM oc_product_3d WHERE product_id=42") == "1", "dati pazuduši pēc reinstall"
    step("atjaunināšana uz 1.0.1: 7 notikumi, dati saglabāti")

    # --- S1: lietotājs tikai ar access tiesībām nevar uninstall
    perms = json.dumps({"access": ["extension/module/product_3d", "catalog/product"], "modify": []})
    sql("DELETE FROM oc_user WHERE username='p3d_limited'; DELETE FROM oc_user_group WHERE name='p3d_limited';")
    sql(f"INSERT INTO oc_user_group SET name='p3d_limited', permission='{perms}'")
    gid = sql("SELECT user_group_id FROM oc_user_group WHERE name='p3d_limited'")
    salt = "abcdefghi"
    pw_hash = hashlib.sha1((salt + hashlib.sha1((salt + hashlib.sha1(b"limited123").hexdigest()).encode()).hexdigest()).encode()).hexdigest()
    sql(f"INSERT INTO oc_user SET user_group_id={gid}, username='p3d_limited', password='{pw_hash}', salt='{salt}', "
        f"firstname='L', lastname='U', email='l@example.com', image='', code='', ip='', status=1, date_added=NOW()")
    lpage, ladmin = login("p3d_limited", "limited123")
    lpage.goto(ladmin("extension/module/product_3d/uninstall"))
    lpage.goto(ladmin("extension/module/product_3d/install"))
    assert sql("SELECT COUNT(*) FROM oc_event WHERE code='product_3d'") == "7"
    assert sql("SELECT COUNT(*) FROM oc_product_3d WHERE product_id=42") == "1"
    lpage.context.close()
    sql("DELETE FROM oc_user WHERE username='p3d_limited'; DELETE FROM oc_user_group WHERE name='p3d_limited';")
    step("S1: access-only lietotājs nevar install/uninstall")

    # --- C3: selektors ar '>'
    page.goto(admin("extension/module/product_3d"))
    page.fill("#p3d-selector", "#content .col-sm-8 > ul.thumbnails")
    page.click("button[form=form-product-3d]")
    page.wait_for_url(re.compile(r"marketplace/extension"))
    assert sql("SELECT value FROM oc_setting WHERE `key`='module_product_3d_selector'") == "#content .col-sm-8 > ul.thumbnails"
    step("C3: selektors ar '>' saglabājas")

    shop = browser.new_page(viewport={"width": 1366, "height": 900})

    def viewer_state(url):
        shop.goto(url)
        shop.wait_for_function("() => document.querySelector('#product-3d[data-placed]') || !document.querySelector('#product-3d')",
                               timeout=30_000)
        shop.wait_for_timeout(500)
        return shop.evaluate("""() => {
          const box = document.getElementById('product-3d');
          const g = document.querySelector('ul.thumbnails');
          const mv = box && box.querySelector('model-viewer');
          return { box: !!box, galleryVisible: g ? getComputedStyle(g).display !== 'none' : null,
                   width: box ? box.getBoundingClientRect().width : 0,
                   loaded: mv ? mv.loaded : false, src: mv ? mv.getAttribute('src') : null };
        }""")

    # --- C1: cits hosts (localhost vs 127.0.0.1). OpenCart <base href> norāda uz config_url, tāpēc CORS bloķē
    # arī paša OpenCart fontus/AJAX; modulim jānodrošina, ka produkta attēli NEPAZŪD.
    alt = browser.new_page()
    alt.goto(ALT + "index.php?route=product/product&product_id=42")
    alt.wait_for_timeout(3000)
    early = alt.evaluate("getComputedStyle(document.querySelector('ul.thumbnails')).display")
    alt.wait_for_timeout(14000)
    st = alt.evaluate("() => ({ box: !!document.getElementById('product-3d'), "
                      "g: getComputedStyle(document.querySelector('ul.thumbnails')).display })")
    assert early != "none" and st["g"] != "none" and st["box"] is False, (early, st)
    alt.close()
    step("C1: cits hosts (CORS) — galerija visu laiku redzama, skatītājs noņemts")

    # pareizajā hostā modelis ielādējas un galerija paslēpta
    shop.goto(BASE + "index.php?route=product/product&product_id=42")
    shop.wait_for_function("() => { const m = document.querySelector('#product-3d model-viewer'); return m && m.loaded; }", timeout=60_000)
    st = shop.evaluate("() => ({ src: document.querySelector('#product-3d model-viewer').getAttribute('src'), "
                       "g: getComputedStyle(document.querySelector('ul.thumbnails')).display })")
    assert st["src"].startswith("/image/") and st["g"] == "none", st
    step(f"pareizais hosts — modelis ielādēts, galerija aizstāta, src={st['src']}")

    # --- C1: bojāts modelis -> galerija atpakaļ
    D3.mkdir(parents=True, exist_ok=True)
    good = D3 / "regr-broken.glb"
    shutil.copyfile(a.glb, good)
    sql("REPLACE INTO oc_product_3d SET product_id=40, model_3d='catalog/3d/regr-broken.glb', ios_3d='', poster_3d='', "
        "placement='', camera_orbit='', auto_rotate='', date_modified=NOW()")
    data = bytearray(good.read_bytes())
    data[200:400] = b"\x00" * 200          # sabojā JSON daļu, galvene paliek
    good.write_bytes(bytes(data))
    shop.goto(BASE + "index.php?route=product/product&product_id=40")
    shop.wait_for_timeout(8000)
    st = shop.evaluate("() => ({ box: !!document.getElementById('product-3d'), "
                       "g: getComputedStyle(document.querySelector('ul.thumbnails')).display })")
    assert st["box"] is False and st["g"] != "none", st
    step("C1: bojāts .glb — skatītājs noņemts, galerija redzama")

    # --- C1: model-viewer skripts bloķēts -> galerija atpakaļ
    blocked = browser.new_page()
    blocked.route("**/model-viewer.min.js*", lambda r: r.abort())
    blocked.goto(BASE + "index.php?route=product/product&product_id=42")
    blocked.wait_for_timeout(16500)
    st = blocked.evaluate("() => ({ box: !!document.getElementById('product-3d'), "
                          "g: getComputedStyle(document.querySelector('ul.thumbnails')).display })")
    assert st["box"] is False and st["g"] != "none", st
    blocked.close()
    step("C1: bloķēts model-viewer skripts — galerija atjaunota pēc laika limita")

    # --- C2: produkts bez attēliem -> galvenajā kolonnā
    shutil.copyfile(a.glb, D3 / "regr-noimg.glb")
    sql("REPLACE INTO oc_product_3d SET product_id=35, model_3d='catalog/3d/regr-noimg.glb', ios_3d='', poster_3d='', "
        "placement='', camera_orbit='', auto_rotate='', date_modified=NOW()")
    shop.goto(BASE + "index.php?route=product/product&product_id=35")
    shop.wait_for_function("() => { const m = document.querySelector('#product-3d model-viewer'); return m && m.loaded; }", timeout=60_000)
    width = shop.evaluate("document.getElementById('product-3d').getBoundingClientRect().width")
    assert width > 600, width
    step(f"C2: produkts bez attēliem — skatītājs {width:.0f}px plats")

    # --- C7: produkta kopēšana kopē 3D
    before_max = int(sql("SELECT MAX(product_id) FROM oc_product"))
    page.goto(admin("catalog/product", "&filter_name=Apple%20Cinema"))
    page.check("input[name='selected[]'][value='42']")
    page.click("button[onclick*='copy'], button[formaction*='copy']")
    page.wait_for_timeout(2000)
    new_id = int(sql("SELECT MAX(product_id) FROM oc_product"))
    assert new_id > before_max
    assert sql(f"SELECT model_3d FROM oc_product_3d WHERE product_id={new_id}") == sql("SELECT model_3d FROM oc_product_3d WHERE product_id=42")
    step(f"C7: kopija #{new_id} saņēma 3D modeli")
    page.goto(admin("catalog/product", "&filter_name=Apple%20Cinema"))
    page.check(f"input[name='selected[]'][value='{new_id}']")
    page.click("button[onclick*='confirm']")
    page.wait_for_timeout(1500)
    assert sql(f"SELECT COUNT(*) FROM oc_product_3d WHERE product_id={new_id}") == "0"
    step("kopijas dzēšana dzēš arī 3D ierakstu")

    # --- C5: nederīgs ceļš nesabojā esošo
    keep = sql("SELECT model_3d FROM oc_product_3d WHERE product_id=42")
    page.goto(admin("catalog/product/edit", "&product_id=42"))
    page.click("a[href='#tab-product-3d']")
    page.fill("#p3d-model_3d", "catalog/3d/nav-tada-faila.glb")
    page.click("button[form=form-product]")
    page.wait_for_url(re.compile(r"catalog/product(&|$)"))
    assert sql("SELECT model_3d FROM oc_product_3d WHERE product_id=42") == keep
    page.goto(admin("catalog/product/edit", "&product_id=42"))
    page.click("a[href='#tab-product-3d']")
    page.fill("#p3d-model_3d", "http://example.com/shop/image/" + keep)   # pilns URL ar apakšmapi
    page.click("button[form=form-product]")
    page.wait_for_url(re.compile(r"catalog/product(&|$)"))
    assert sql("SELECT model_3d FROM oc_product_3d WHERE product_id=42") == keep
    step("C5: nederīgs ceļš / pilns URL neizdzēš 3D saiti")

    # --- C4: fails ar atstarpi un speciālām rakstzīmēm (FTP) -> admin priekšskatījums
    odd = "catalog/3d/ftp modelis #1 %.glb"
    shutil.copyfile(a.glb, ROOT / "image" / odd)
    sql(f"UPDATE oc_product_3d SET model_3d='{odd}' WHERE product_id=42")
    page.goto(admin("catalog/product/edit", "&product_id=42"))
    page.click("a[href='#tab-product-3d']")
    page.wait_for_function("() => document.getElementById('p3d-preview').loaded", timeout=60_000)
    step("C4: admin priekšskatījums ar atstarpēm/#/% nosaukumā")
    sql(f"UPDATE oc_product_3d SET model_3d='{keep}' WHERE product_id=42")

    # --- S2/S6: augšupielādes validācija
    page.goto(admin("catalog/product/edit", "&product_id=42"))
    page.click("a[href='#tab-product-3d']")
    tmp = Path(a.glb).parent

    def upload(kind, name, content):
        f = tmp / name
        f.write_bytes(content)
        row = page.locator(f".p3d-file[data-type={kind}]")
        row.locator(".p3d-msg").evaluate("e => e.textContent = ''")
        with page.expect_file_chooser() as fc:
            row.locator(".p3d-upload").click()
        fc.value.set_files(str(f))
        page.wait_for_function(f"() => document.querySelector('.p3d-file[data-type={kind}] .p3d-msg').textContent.length > 0", timeout=20_000)
        f.unlink()
        return row.locator(".p3d-msg").get_attribute("class"), row.locator(".p3d-msg").inner_text()

    from PIL import Image
    buf = io.BytesIO()
    Image.new("RGB", (64, 64), (200, 100, 50)).save(buf, "PNG")
    png = buf.getvalue()
    cls, msg = upload("poster", "png-as.jpg", png)
    assert "text-danger" in cls, msg
    cls, msg = upload("poster", "late-php.png", png + b"\0" * 6000 + b"<?php system($_GET[1]); ?>")
    assert "text-danger" in cls, msg
    cls, msg = upload("model", "ext-uri.glb", glb_with_external_uri())
    assert "text-danger" in cls, msg
    zbuf = io.BytesIO()
    with zipfile.ZipFile(zbuf, "w", zipfile.ZIP_STORED) as z:
        z.writestr("readme.txt", "not usd")
    cls, msg = upload("ios", "fake.usdz", zbuf.getvalue())
    assert "text-danger" in cls, msg
    cls, msg = upload("poster", "ok.png", png)
    assert "text-success" in cls, msg
    step("S2/S6: PNG kā .jpg, PHP aiz 5 KB, .glb ar ārēju URI, viltots .usdz — noraidīti; derīgs PNG pieņemts")

    # --- S5: .htaccess saturs
    ht = (D3 / ".htaccess").read_text()
    assert "Options -Indexes" in ht and "nosniff" in ht and "mod_authz_core" in ht
    step("S5: .htaccess atjaunināts (Indexes, nosniff, Apache 2.2/2.4)")

    # sakopšana
    sql("DELETE FROM oc_product_3d WHERE product_id IN (35, 40)")
    for f in ("regr-broken.glb", "regr-noimg.glb"):
        (D3 / f).unlink(missing_ok=True)
    (ROOT / "image" / odd).unlink(missing_ok=True)
    browser.close()

print(f"\nOK: {len(results)} regresijas pārbaudes")
