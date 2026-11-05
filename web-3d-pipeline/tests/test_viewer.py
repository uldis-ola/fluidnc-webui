"""index.html e2e testi pārlūkā (Playwright). Vajag: pip install playwright && playwright install chromium"""

from __future__ import annotations

import json
import shutil

import pytest

from conftest import ROOT, chromium_path, has_playwright, run_optimize

pytestmark = pytest.mark.skipif(not has_playwright(), reason="nav playwright")


@pytest.fixture(scope="module")
def site(tmp_path_factory, fixtures):
    """Pagaidu lapa: index.html + vendor/ + models/ ar 2 modeļiem."""
    if not (ROOT / "vendor" / "model-viewer.min.js").exists():
        run_optimize("--fetch-vendor")
    root = tmp_path_factory.mktemp("site")
    shutil.copy(ROOT / "index.html", root / "index.html")
    shutil.copytree(ROOT / "vendor", root / "vendor")
    models = root / "models"
    run_optimize(fixtures["vase"], "-o", models)
    run_optimize(fixtures["part"], "-o", models, "--units", "mm", "--up", "z")
    man_path = models / "vase_variants.json"
    man = json.loads(man_path.read_text(encoding="utf-8"))
    man.update(title={"lv": "Vāze", "en": "Vase"}, price=19.9,
               hotspots=[{"position": "0m 0.3m 0.12m", "normal": "0 0 1",
                          "label": {"lv": "Glazūra", "en": "Glaze"}}])
    man_path.write_text(json.dumps(man, ensure_ascii=False), encoding="utf-8")

    from optimize import _serve
    httpd = _serve(root)
    yield f"http://127.0.0.1:{httpd.server_address[1]}"
    httpd.shutdown()
    httpd.server_close()


@pytest.fixture(scope="module")
def browser():
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        opts = {"args": ["--use-gl=angle", "--use-angle=swiftshader", "--enable-unsafe-swiftshader"]}
        if chromium_path():
            opts["executable_path"] = chromium_path()
        b = p.chromium.launch(**opts)
        yield b
        b.close()


def open_page(browser, url, **ctx):
    context = browser.new_context(**ctx)
    page = context.new_page()
    errors, external = [], []
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.on("console", lambda m: m.type == "error" and not m.text.startswith("Failed to load resource")
            and errors.append(m.text))
    page.on("response", lambda r: r.status >= 400 and errors.append(f"HTTP {r.status} {r.url}"))
    page.on("request", lambda r: not r.url.startswith(url.split("/index")[0]) and not r.url.startswith(("data:", "blob:"))
            and external.append(r.url))
    page.add_init_script("window.dataLayer = []")
    page.goto(url)
    page.wait_for_function("() => /cm/.test(document.getElementById('stats').textContent)", timeout=90_000)
    return page, errors, external


def test_loads_manifest_variants_hotspots_offline(browser, site):
    page, errors, external = open_page(browser, f"{site}/index.html?model=models/vase_variants.web.glb")
    assert page.evaluate("document.documentElement.dataset.mvSource") == "local"
    assert external == [], f"ārēji pieprasījumi: {external}"
    assert page.text_content("#title") == "Vāze"
    assert "19,90" in page.text_content("#price")
    assert "26.0 × 38.0 × 26.0 cm" in page.text_content("#stats")
    assert page.locator("#variants button").all_text_contents() == ["Terakota", "Zila", "Melna"]
    page.click("#variants button:has-text('Zila')")
    assert page.evaluate("document.getElementById('viewer').variantName") == "Zila"
    assert page.locator(".hotspot").count() == 1
    events = [e["event"] for e in page.evaluate("window.dataLayer")]
    assert "web3d_model_loaded" in events and "web3d_variant_change" in events
    assert errors == []
    page.context.close()


def test_catalog_switch_language_and_controls(browser, site):
    page, errors, _ = open_page(browser, f"{site}/index.html")
    assert page.is_visible("#catalog")
    assert page.locator("#catalog option").count() == 2
    # pirmais katalogā (alfabētiski) ir detaļa: 500 × 300 × 100 mm, Z-up -> Y-up
    assert "50.0 × 10.0 × 30.0 cm" in page.text_content("#stats")
    assert page.is_hidden("#variantsGroup")
    page.select_option("#catalog", value="models/vase_variants.web.glb")
    page.wait_for_function("() => /26\\.0 × 38\\.0 × 26\\.0 cm/.test(document.getElementById('stats').textContent)",
                           timeout=60_000)
    assert page.is_visible("#variantsGroup")
    assert "model=models%2Fvase_variants.web.glb" in page.url or "model=models/vase_variants.web.glb" in page.url

    page.click("[data-lang=en]")
    assert page.text_content("[data-i18n=turntable]") == "Turntable"
    assert "Dimensions" in page.text_content("#stats")
    page.click("[data-lang=lv]")

    assert "Pauze" in page.text_content("#playPause")          # sākumā griežas
    page.click("#playPause")
    assert page.evaluate("document.getElementById('viewer').autoRotate") is False
    page.click("#shadows")
    assert page.evaluate("document.getElementById('viewer').shadowIntensity") == 0
    page.click("#dirCcw")
    assert page.evaluate("document.getElementById('viewer').rotationPerSecond") == "-30deg"
    page.click("[data-view='90']")
    # kamera animējas uz mērķi - gaidām, līdz pagriezta uz sānu (90°)
    page.wait_for_function(
        "() => Math.abs(document.getElementById('viewer').getCameraOrbit().theta * 180 / Math.PI - 90) < 0.5",
        timeout=10_000)
    assert errors == []
    page.context.close()


def test_hotspot_editor_adds_point(browser, site):
    page, errors, _ = open_page(browser, f"{site}/index.html?model=models/vase_variants.web.glb")
    page.check("#editHotspots")
    box = page.locator("#viewer").bounding_box()
    page.mouse.click(box["x"] + box["width"] / 2, box["y"] + box["height"] / 2)
    data = json.loads(page.input_value("#hotspotJson"))
    assert len(data) == 2 and data[-1]["position"].endswith("m")
    assert errors == []
    page.context.close()


def test_mobile_layout_no_horizontal_scroll(browser, site):
    page, errors, _ = open_page(browser, f"{site}/index.html?model=models/vase_variants.web.glb",
                                viewport={"width": 375, "height": 812}, is_mobile=True, has_touch=True)
    assert page.evaluate("document.documentElement.scrollWidth") <= 375
    assert page.locator("[slot=ar-button]").count() == 1
    assert errors == []
    page.context.close()
