"""Plūsmas testi: python -m pytest tests -v"""

from __future__ import annotations

import csv
import json
import zipfile

import pytest

from conftest import has_bpy, has_gltf_transform, has_playwright
from optimize import glb_stats, parse_size, read_glb, scene_bounds, validate

needs_gt = pytest.mark.skipif(not has_gltf_transform(), reason="nav gltf-transform")
needs_bpy = pytest.mark.skipif(not has_bpy(), reason="nav Blender/bpy")
needs_pw = pytest.mark.skipif(not has_playwright(), reason="nav playwright")


def bounds(path):
    doc, _ = read_glb(path)
    lo, hi = scene_bounds(doc)
    return [round(v, 4) for v in lo], [round(v, 4) for v in hi]


def test_parse_size():
    assert parse_size("3MB") == 3 * 1024 * 1024
    assert parse_size("800 kb") == 800 * 1024
    assert parse_size("1000") == 1000


def test_python_engine_textures(fixtures, run, tmp_path):
    out = tmp_path / "s.glb"
    run(fixtures["sphere"], "-o", out, "--engine", "python", "--texture-size", "1024")
    doc, _ = read_glb(out)
    assert out.stat().st_size < fixtures["sphere"].stat().st_size / 5
    assert "EXT_texture_webp" in doc.get("extensionsUsed", [])
    assert all(img["mimeType"] == "image/webp" for img in doc["images"])
    assert validate(out)[0] == []


def test_python_engine_jpeg(fixtures, run, tmp_path):
    out = tmp_path / "s.glb"
    run(fixtures["sphere"], "-o", out, "--engine", "python", "--texture-format", "jpeg", "--texture-size", "512")
    doc, _ = read_glb(out)
    assert {img["mimeType"] for img in doc["images"]} == {"image/jpeg"}


@needs_gt
def test_gltf_transform_decimation_and_webp(fixtures, run, tmp_path):
    out = tmp_path / "s.glb"
    run(fixtures["sphere"], "-o", out, "--ratio", "0.25", "--texture-size", "1024")
    st = glb_stats(out)
    assert st.triangles <= 81920 * 0.3
    assert "KHR_draco_mesh_compression" in st.extensions
    assert "EXT_texture_webp" in st.extensions
    assert out.stat().st_size < 1024 * 1024


@needs_gt
def test_meshopt_option(fixtures, run, tmp_path):
    out = tmp_path / "s.glb"
    run(fixtures["sphere"], "-o", out, "--compress", "meshopt", "--texture-size", "512")
    assert "EXT_meshopt_compression" in glb_stats(out).extensions


def test_units_up_axis_and_floor(fixtures, run, tmp_path):
    out = tmp_path / "p.glb"
    run(fixtures["part"], "-o", out, "--units", "mm", "--up", "z", "--engine", "python")
    lo, hi = bounds(out)
    assert lo == [-0.25, 0.0, -0.15]          # centrēts, uz grīdas
    assert hi == [0.25, 0.1, 0.15]            # 500 × 100(augstums) × 300 mm


def test_unit_warning(fixtures, run, tmp_path):
    res = run(fixtures["part"], "-o", tmp_path / "p.glb", "--engine", "python")
    assert "iespējams, avots ir cm/mm" in res.stdout


@needs_gt
def test_variants_preserved(fixtures, run, tmp_path):
    out = tmp_path / "v.glb"
    run(fixtures["vase"], "-o", out)
    assert glb_stats(out).variants == ["Terakota", "Zila", "Melna"]
    man = json.loads((tmp_path / "v.json").read_text(encoding="utf-8"))
    assert man["variants"] == ["Terakota", "Zila", "Melna"]


@needs_gt
def test_max_size_budget(fixtures, run, tmp_path):
    out = tmp_path / "s.glb"
    res = run(fixtures["sphere"], "-o", out, "--max-size", "150KB", "--strict-size")
    assert out.stat().st_size <= 150 * 1024, res.stdout


def test_max_size_strict_failure(fixtures, run, tmp_path):
    res = run(fixtures["sphere"], "-o", tmp_path / "s.glb", "--engine", "python",
              "--max-size", "1KB", "--max-attempts", "1", "--strict-size", check=False)
    assert res.returncode == 2
    assert "limitā" in res.stdout


@needs_bpy
def test_fbx_conversion(fixtures, run, tmp_path):
    if "chair" not in fixtures:
        pytest.skip("nav chair.fbx")
    out = tmp_path / "chair.web.glb"
    run(fixtures["chair"], "-o", out)
    lo, hi = bounds(out)
    assert hi[1] == pytest.approx(0.97, abs=0.005)      # krēsla augstums metros
    assert lo[1] == pytest.approx(0.0, abs=1e-4)
    assert glb_stats(out).textures == 1
    assert validate(out)[0] == []


@needs_bpy
def test_usdz_export(fixtures, run, tmp_path):
    run(fixtures["vase"], "-o", tmp_path / "v.glb", "--usdz", "--engine", "python")
    usdz = tmp_path / "v.usdz"
    assert usdz.exists()
    with zipfile.ZipFile(usdz) as z:
        names = z.namelist()
        assert names[0].endswith((".usdc", ".usda"))   # USDZ: pirmajam jābūt USD failam
        assert all(i.compress_type == zipfile.ZIP_STORED for i in z.infolist())
    assert json.loads((tmp_path / "v.json").read_text(encoding="utf-8"))["iosSrc"] == "v.usdz"


@needs_pw
def test_poster(fixtures, run, tmp_path):
    from PIL import Image
    run(fixtures["vase"], "-o", tmp_path / "v.glb", "--poster", "--poster-size", "400x300", "--engine", "python")
    img = Image.open(tmp_path / "v.poster.webp")
    assert img.size == (400, 300)
    colors = img.convert("RGB").getcolors(maxcolors=1_000_000)
    assert len(colors) > 50                               # nav tukšs attēls


def test_batch_report_catalog_and_manifest_merge(fixtures, run, tmp_path):
    src = tmp_path / "in"
    src.mkdir()
    for k in ("part", "vase"):
        (src / fixtures[k].name).write_bytes(fixtures[k].read_bytes())
    out = tmp_path / "out"
    run(src, "-o", out, "--engine", "python")

    rows = list(csv.DictReader(open(out / "report.csv", encoding="utf-8")))
    assert {r["source"] for r in rows} == {"part_mm_zup.stl", "vase_variants.glb"}
    assert all(r["ok"] == "True" for r in rows)
    cat = json.loads((out / "catalog.json").read_text(encoding="utf-8"))
    assert {m["model"] for m in cat["models"]} == {"part_mm_zup.web.glb", "vase_variants.web.glb"}

    # lietotāja lauki manifestā saglabājas pēc atkārtotas palaišanas
    man_path = out / "vase_variants.json"
    man = json.loads(man_path.read_text(encoding="utf-8"))
    man.update(title={"lv": "Vāze", "en": "Vase"}, price=19.9,
               hotspots=[{"position": "0m 0.3m 0m", "label": "Augša"}])
    man_path.write_text(json.dumps(man, ensure_ascii=False), encoding="utf-8")
    run(src / "vase_variants.glb", "-o", out, "--engine", "python")
    man2 = json.loads(man_path.read_text(encoding="utf-8"))
    assert man2["title"] == {"lv": "Vāze", "en": "Vase"} and man2["price"] == 19.9
    assert len(man2["hotspots"]) == 1
    embed = (out / "vase_variants.embed.html").read_text(encoding="utf-8")
    assert 'src="vase_variants.web.glb"' in embed and 'alt="Vāze"' in embed


def test_missing_file_and_bad_input(run, tmp_path):
    assert run(tmp_path / "nav.fbx", check=False).returncode == 1
    bad = tmp_path / "bad.glb"
    bad.write_bytes(b"not a glb at all")
    res = run(bad, "-o", tmp_path / "o.glb", check=False)
    assert res.returncode == 2 and "nav derīgs GLB" in res.stdout


def test_validate_detects_unsupported_required_extension(fixtures, tmp_path):
    from optimize import write_glb
    doc, binchunk = read_glb(fixtures["vase"])
    doc["extensionsRequired"] = ["EXT_unknown_thing"]
    doc.setdefault("extensionsUsed", []).append("EXT_unknown_thing")
    p = tmp_path / "x.glb"
    write_glb(p, doc, binchunk)
    errors, _ = validate(p)
    assert any("EXT_unknown_thing" in e for e in errors)
