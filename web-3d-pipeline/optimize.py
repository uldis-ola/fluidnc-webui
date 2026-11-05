#!/usr/bin/env python3
"""
optimize.py - 3D modeļu optimizācija tīmeklim (e-komercija, mājaslapas).

Plūsma:
  1) Konvertācija uz .glb (ja ievade nav .gltf/.glb):
       .fbx            -> FBX2glTF  (ja ir PATH)  vai  Blender headless
       .obj/.stl/.ply  -> trimesh
  2) Optimizācija (pirmais pieejamais dzinējs):
       a) gltf-transform CLI  (ieteicams: meshopt decimācija, WebP tekstūras, Draco/Meshopt)
       b) gltfpack            (meshoptimizer)
       c) Python fallback     (trimesh + fast-simplification + Pillow, WebP tekstūras)
  3) Rezultāts: <nosaukums>.web.glb + atskaite terminālī.

Lietošana:
  python optimize.py modelis.fbx
  python optimize.py modelis.glb -o out/produkts.glb --ratio 0.3 --texture-size 1024
  python optimize.py modelis.gltf --engine python --texture-format jpeg
"""

from __future__ import annotations

import argparse
import io
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

GLTF_EXT = {".gltf", ".glb"}
TRIMESH_EXT = {".obj", ".stl", ".ply", ".off", ".dae", ".3mf"}
FBX_EXT = {".fbx"}

BLENDER_FBX_SCRIPT = r"""
import bpy, sys
argv = sys.argv[sys.argv.index("--") + 1:]
src, dst = argv[0], argv[1]
bpy.ops.wm.read_factory_settings(use_empty=True)
bpy.ops.import_scene.fbx(filepath=src)
bpy.ops.export_scene.gltf(filepath=dst, export_format='GLB', export_apply=True)
"""


# --------------------------------------------------------------------------- utils
def log(msg: str) -> None:
    print(f"[optimize] {msg}", flush=True)


def human(n: int) -> str:
    for unit in ("B", "KB", "MB", "GB"):
        if n < 1024:
            return f"{n:.1f} {unit}"
        n /= 1024
    return f"{n:.1f} TB"


def run(cmd: list[str]) -> None:
    log("$ " + " ".join(cmd))
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        sys.stderr.write(res.stdout + "\n" + res.stderr + "\n")
        raise RuntimeError(f"Komanda neizdevās (exit {res.returncode}): {cmd[0]}")


def find_tool(*names: str) -> list[str] | None:
    """Atgriež komandu, ja rīks ir PATH (vai pieejams caur npx)."""
    for name in names:
        path = shutil.which(name)
        if path:
            return [path]
    return None


def count_stats(glb_path: Path) -> tuple[int, int]:
    """Trijstūru un tekstūru skaits (ja trimesh pieejams)."""
    try:
        import logging
        import trimesh

        logging.getLogger("trimesh").setLevel(logging.ERROR)  # Draco/meshopt brīdinājumi
        scene = trimesh.load(str(glb_path), force="scene")
        if any(len(getattr(g, "vertices", [])) and not g.vertices.any()
               for g in scene.geometry.values()):
            return -1, -1  # saspiesta ģeometrija, trimesh to neatkodē
        tris = sum(len(g.faces) for g in scene.geometry.values() if hasattr(g, "faces"))
        textures = set()
        for g in scene.geometry.values():
            mat = getattr(getattr(g, "visual", None), "material", None)
            for attr in ("baseColorTexture", "normalTexture", "metallicRoughnessTexture",
                         "emissiveTexture", "occlusionTexture", "image"):
                img = getattr(mat, attr, None) if mat is not None else None
                if img is not None:
                    textures.add(id(img))
        return tris, len(textures)
    except Exception:
        return -1, -1


# --------------------------------------------------------------------------- 1. konvertācija
def convert_to_glb(src: Path, workdir: Path) -> Path:
    ext = src.suffix.lower()
    if ext in GLTF_EXT:
        return src

    dst = workdir / (src.stem + ".glb")

    if ext in FBX_EXT:
        fbx2gltf = find_tool("FBX2glTF", "fbx2gltf")
        if fbx2gltf:
            # FBX2glTF pats pieliek .glb paplašinājumu
            run(fbx2gltf + ["--binary", "-i", str(src), "-o", str(dst.with_suffix(""))])
            if dst.exists():
                return dst
        blender = find_tool("blender")
        if blender:
            script = workdir / "fbx_to_glb.py"
            script.write_text(BLENDER_FBX_SCRIPT, encoding="utf-8")
            run(blender + ["-b", "--factory-startup", "--python", str(script),
                           "--", str(src), str(dst)])
            if dst.exists():
                return dst
        raise RuntimeError(
            "FBX konvertācijai vajag FBX2glTF vai Blender PATH mainīgajā.\n"
            "  FBX2glTF: https://github.com/godotengine/FBX2glTF/releases\n"
            "  Blender:  https://www.blender.org/download/"
        )

    if ext in TRIMESH_EXT:
        import trimesh

        log(f"Konvertē {ext} -> .glb ar trimesh")
        scene = trimesh.load(str(src), force="scene")
        scene.export(str(dst), file_type="glb")
        return dst

    raise RuntimeError(f"Neatbalstīts formāts: {ext}")


# --------------------------------------------------------------------------- 2a. gltf-transform
def optimize_gltf_transform(src: Path, dst: Path, a: argparse.Namespace) -> None:
    tool = find_tool("gltf-transform")
    if tool is None:
        npx = find_tool("npx")
        if npx is None:
            raise FileNotFoundError("gltf-transform")
        tool = npx + ["--yes", "@gltf-transform/cli"]

    tex = "webp" if a.texture_format == "webp" else "auto"  # auto: JPEG/PNG pēc satura
    cmd = tool + [
        "optimize", str(src), str(dst),
        "--compress", a.compress,
        "--texture-compress", tex,
        "--texture-size", str(a.texture_size),
        "--simplify", "true" if a.ratio < 1.0 else "false",
        "--simplify-ratio", str(a.ratio),
        "--simplify-error", str(a.error),
    ]
    run(cmd)


# --------------------------------------------------------------------------- 2b. gltfpack
def optimize_gltfpack(src: Path, dst: Path, a: argparse.Namespace) -> None:
    tool = find_tool("gltfpack")
    if tool is None:
        raise FileNotFoundError("gltfpack")
    cmd = tool + ["-i", str(src), "-o", str(dst), "-si", str(a.ratio)]
    if a.compress == "meshopt":
        cmd.append("-cc")
    # gltfpack tekstūras saspiež tikai uz KTX2/WebP ar papildu rīkiem; atstājam oriģinālas.
    run(cmd)


# --------------------------------------------------------------------------- 2c. Python fallback
def _resize_and_encode(img, max_size: int, fmt: str):
    """Samazina PIL attēlu līdz max_size un pārkodē JPEG/WebP formātā."""
    from PIL import Image

    img = img.copy()
    if max(img.size) > max_size:
        img.thumbnail((max_size, max_size), Image.LANCZOS)

    has_alpha = img.mode in ("RGBA", "LA") or (img.mode == "P" and "transparency" in img.info)
    buf = io.BytesIO()
    if fmt == "jpeg" and not has_alpha:
        img.convert("RGB").save(buf, format="JPEG", quality=85, optimize=True, progressive=True)
        out = Image.open(io.BytesIO(buf.getvalue()))
        out.format = "JPEG"
    elif fmt == "jpeg" and has_alpha:
        img.save(buf, format="PNG", optimize=True)  # JPEG nav caurspīdīguma
        out = Image.open(io.BytesIO(buf.getvalue()))
        out.format = "PNG"
    else:
        img.save(buf, format="WEBP", quality=85, method=6)
        out = Image.open(io.BytesIO(buf.getvalue()))
        out.format = "WEBP"
    out.load()
    return out


def optimize_python(src: Path, dst: Path, a: argparse.Namespace) -> None:
    import trimesh

    scene = trimesh.load(str(src), force="scene")
    processed: dict[int, object] = {}

    for name, geom in list(scene.geometry.items()):
        if not isinstance(geom, trimesh.Trimesh):
            continue

        visual = geom.visual
        has_uv = getattr(visual, "uv", None) is not None

        # --- decimācija
        target = int(len(geom.faces) * a.ratio)
        if a.ratio < 1.0 and len(geom.faces) < a.min_faces:
            pass  # mazs tīkls (piem. kaste) - decimācija tikai sabojātu formu
        elif a.ratio < 1.0 and target >= 4:
            if has_uv and not a.force_decimate:
                log(f"  {name}: izlaiž decimāciju (tīkls ar UV; fallback dzinējs UV nesaglabā). "
                    f"Lieto gltf-transform vai --force-decimate.")
            else:
                try:
                    simple = geom.simplify_quadric_decimation(face_count=target)
                    if has_uv:
                        log(f"  {name}: UV/tekstūras zaudētas decimācijas dēļ (--force-decimate)")
                    else:
                        simple.visual = geom.visual.copy() if hasattr(geom.visual, "copy") else geom.visual
                        if getattr(simple.visual, "kind", None) == "vertex":
                            simple.visual = trimesh.visual.ColorVisuals()  # krāsas pa virsotnēm nesakrīt
                    log(f"  {name}: {len(geom.faces)} -> {len(simple.faces)} trijstūri")
                    scene.geometry[name] = simple
                    geom = simple
                except Exception as exc:  # fast-simplification nav instalēts u.c.
                    log(f"  {name}: decimācija neizdevās ({exc})")

        # --- tekstūras
        mat = getattr(geom.visual, "material", None)
        if mat is None:
            continue
        for attr in ("baseColorTexture", "metallicRoughnessTexture", "normalTexture",
                     "emissiveTexture", "occlusionTexture", "image"):
            img = getattr(mat, attr, None)
            if img is None:
                continue
            key = id(img)
            if key not in processed:
                # normal/metallic kartēm JPEG artefakti ir kaitīgi -> WebP
                fmt = a.texture_format if attr in ("baseColorTexture", "emissiveTexture", "image") else "webp"
                processed[key] = _resize_and_encode(img, a.texture_size, fmt)
            setattr(mat, attr, processed[key])

    use_webp = a.texture_format == "webp"
    data = trimesh.exchange.gltf.export_glb(scene, extension_webp=use_webp)
    dst.write_bytes(data)


# --------------------------------------------------------------------------- main
ENGINES = {
    "gltf-transform": optimize_gltf_transform,
    "gltfpack": optimize_gltfpack,
    "python": optimize_python,
}


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="Optimizē 3D modeli (.fbx/.gltf/.glb/.obj/.stl/.ply) tīmeklim -> viegls .glb",
    )
    p.add_argument("input", type=Path, help="Ievades fails, piem. modelis.fbx")
    p.add_argument("-o", "--output", type=Path, help="Izvades .glb (noklusējums: <nosaukums>.web.glb)")
    p.add_argument("--engine", choices=["auto", *ENGINES], default="auto",
                   help="Optimizācijas dzinējs (noklusējums: auto)")
    p.add_argument("--ratio", type=float, default=0.5,
                   help="Paturamā ģeometrijas daļa 0-1 (noklusējums 0.5; 1 = bez decimācijas)")
    p.add_argument("--error", type=float, default=0.001,
                   help="Pieļaujamā decimācijas kļūda (gltf-transform, noklusējums 0.001)")
    p.add_argument("--texture-size", type=int, default=2048,
                   help="Maks. tekstūras izmērs px (noklusējums 2048)")
    p.add_argument("--texture-format", choices=["webp", "jpeg"], default="webp",
                   help="Tekstūru formāts (noklusējums webp)")
    p.add_argument("--compress", choices=["meshopt", "draco", "quantize", "false"], default="draco",
                   help="Ģeometrijas saspiešana (gltf-transform; noklusējums draco - "
                        "model-viewer to atbalsta bez papildu konfigurācijas)")
    p.add_argument("--force-decimate", action="store_true",
                   help="Python dzinējā decimēt arī tīklus ar UV (tekstūras tiks zaudētas)")
    p.add_argument("--min-faces", type=int, default=1000,
                   help="Python dzinējā nedecimēt tīklus ar mazāk trijstūriem (noklusējums 1000)")
    p.add_argument("--keep-temp", action="store_true", help="Nedzēst starpfailus")
    a = p.parse_args(argv)

    if not 0.0 < a.ratio <= 1.0:
        p.error("--ratio jābūt intervālā (0, 1]")
    if a.texture_size < 16:
        p.error("--texture-size pārāk mazs")
    return a


def main(argv: list[str] | None = None) -> int:
    a = parse_args(argv)
    src: Path = a.input.resolve()
    if not src.is_file():
        log(f"Fails nav atrasts: {src}")
        return 1

    dst: Path = (a.output or src.with_name(src.stem + ".web.glb")).resolve()
    dst.parent.mkdir(parents=True, exist_ok=True)

    workdir = Path(tempfile.mkdtemp(prefix="web3d_"))
    try:
        glb = convert_to_glb(src, workdir)
        before_tris, _ = count_stats(glb)

        order = list(ENGINES) if a.engine == "auto" else [a.engine]
        last_err: Exception | None = None
        for name in order:
            try:
                log(f"Dzinējs: {name}")
                ENGINES[name](glb, dst, a)
                break
            except FileNotFoundError as exc:
                log(f"  {exc} nav instalēts - mēģina nākamo")
                last_err = exc
            except Exception as exc:
                log(f"  {name} kļūda: {exc}")
                last_err = exc
                if a.engine != "auto":
                    break
        else:
            raise RuntimeError(f"Neviens dzinējs neizdevās: {last_err}")
        if not dst.exists():
            raise RuntimeError(f"Optimizācija neizdevās: {last_err}")

        after_tris, after_tex = count_stats(dst)
        s_in, s_out = src.stat().st_size, dst.stat().st_size
        print()
        print("=" * 56)
        print(f" Ievade : {src.name:<30} {human(s_in):>12}")
        print(f" Izvade : {dst.name:<30} {human(s_out):>12}")
        if s_in:
            print(f" Izmēra samazinājums: {100 * (1 - s_out / s_in):.1f}%")
        if before_tris > 0 and after_tris > 0:
            print(f" Trijstūri: {before_tris:,} -> {after_tris:,}")
        elif before_tris > 0:
            print(f" Trijstūri pirms: {before_tris:,} (pēc - saspiests, skaties: gltf-transform inspect)")
        if after_tex >= 0:
            print(f" Tekstūras: {after_tex}")
        print("=" * 56)
        print(f" Gatavs: {dst}")
        return 0
    except Exception as exc:
        log(f"KĻŪDA: {exc}")
        return 2
    finally:
        if a.keep_temp:
            log(f"Starpfaili: {workdir}")
        else:
            shutil.rmtree(workdir, ignore_errors=True)


if __name__ == "__main__":
    sys.exit(main())
