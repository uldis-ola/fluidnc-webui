#!/usr/bin/env python3
"""
optimize.py - 3D produktu modeļu optimizācija tīmeklim (e-komercija, mājaslapas).

Plūsma katram failam:
  1) Konvertācija uz .glb
       .fbx                 -> FBX2glTF | Blender CLI | bpy (Blender kā Python modulis)
       .gltf                -> gltf-transform copy | trimesh
       .obj/.stl/.ply/...   -> trimesh
  2) Sagatavošana (tieši glTF JSON, bez ģeometrijas zuduma):
       mērvienības (--units/--scale), ass (--up z), centrēšana uz grīdas (--center)
  3) Optimizācija: gltf-transform | gltfpack | Python (trimesh + Pillow)
       decimācija, tekstūras WebP/JPEG/KTX2, Draco/Meshopt; --max-size automātiski
       samazina tekstūras un ģeometriju, līdz fails iekļaujas limitā
  4) Validācija: GLB struktūra + model-viewer atbalstītie paplašinājumi
       (+ Khronos glTF-Validator caur `gltf-transform validate`, ja pieejams)
  5) Papildus: poster attēls (--poster), USDZ priekš iOS (--usdz),
       manifests <nosaukums>.json lapai un <nosaukums>.embed.html veikalam
  6) Atskaite terminālī; vairākiem failiem arī report.csv

Piemēri:
  python optimize.py modelis.fbx
  python optimize.py modelis.fbx -o models/ --units cm --poster --usdz
  python optimize.py modeli/ -o models/ --max-size 3MB        # visa mape
  python optimize.py --fetch-vendor                           # lokāli JS/dekoderi (bez CDN)
"""

from __future__ import annotations

import argparse
import base64
import csv
import datetime as _dt
import functools
import http.server
import io
import json
import math
import re
import shutil
import socketserver
import struct
import subprocess
import sys
import tarfile
import tempfile
import threading
import urllib.request
from dataclasses import dataclass, field
from pathlib import Path

HERE = Path(__file__).resolve().parent
VENDOR_DIR = HERE / "vendor"

MODEL_VIEWER_VERSION = "4.0.0"
THREE_VERSION = "0.169.0"          # model-viewer 4.0.0 peerDependency
MESHOPT_VERSION = "0.21.0"
MODEL_VIEWER_CDN = f"https://ajax.googleapis.com/ajax/libs/model-viewer/{MODEL_VIEWER_VERSION}/model-viewer.min.js"
MESHOPT_CDN = f"https://cdn.jsdelivr.net/npm/meshoptimizer@{MESHOPT_VERSION}/meshopt_decoder.js"

GLTF_EXT = {".gltf", ".glb"}
TRIMESH_EXT = {".obj", ".stl", ".ply", ".off", ".dae", ".3mf"}
FBX_EXT = {".fbx"}
INPUT_EXT = GLTF_EXT | TRIMESH_EXT | FBX_EXT

UNITS = {"m": 1.0, "cm": 0.01, "mm": 0.001, "in": 0.0254, "ft": 0.3048}

# Paplašinājumi, ko model-viewer (three.js GLTFLoader) atbalsta
SUPPORTED_EXTENSIONS = {
    "KHR_draco_mesh_compression", "EXT_meshopt_compression", "KHR_mesh_quantization",
    "KHR_texture_basisu", "EXT_texture_webp", "EXT_texture_avif", "KHR_texture_transform",
    "KHR_materials_unlit", "KHR_materials_emissive_strength", "KHR_materials_clearcoat",
    "KHR_materials_ior", "KHR_materials_sheen", "KHR_materials_specular",
    "KHR_materials_transmission", "KHR_materials_volume", "KHR_materials_iridescence",
    "KHR_materials_anisotropy", "KHR_materials_dispersion", "KHR_materials_variants",
    "KHR_lights_punctual", "EXT_mesh_gpu_instancing",
}

COMPONENT_MAX = {5120: 127, 5121: 255, 5122: 32767, 5123: 65535}  # normalizētiem int tipiem


def log(msg: str) -> None:
    print(f"[optimize] {msg}", flush=True)


def human(n: float) -> str:
    for unit in ("B", "KB", "MB", "GB"):
        if n < 1024:
            return f"{n:.1f} {unit}"
        n /= 1024
    return f"{n:.1f} TB"


def parse_size(text: str) -> int:
    m = re.fullmatch(r"\s*([\d.]+)\s*([kmg]?b?)?\s*", text, re.I)
    if not m:
        raise argparse.ArgumentTypeError(f"Nesaprotams izmērs: {text} (piem. 3MB, 800KB)")
    mult = {"": 1, "b": 1, "k": 1024, "kb": 1024, "m": 1024**2, "mb": 1024**2,
            "g": 1024**3, "gb": 1024**3}[(m.group(2) or "").lower()]
    return int(float(m.group(1)) * mult)


def run(cmd: list, quiet: bool = False) -> subprocess.CompletedProcess:
    cmd = [str(c) for c in cmd]
    if not quiet:
        log("$ " + " ".join(cmd))
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        tail = (res.stdout + "\n" + res.stderr).strip().splitlines()[-15:]
        raise RuntimeError(f"{Path(cmd[0]).name} neizdevās (exit {res.returncode}):\n  " + "\n  ".join(tail))
    return res


def which(*names: str) -> list[str] | None:
    for name in names:
        path = shutil.which(name)
        if path:
            return [path]
    return None


def gltf_transform_cmd() -> list[str] | None:
    return which("gltf-transform")


@functools.lru_cache(maxsize=None)
def bpy_available() -> bool:
    res = subprocess.run([sys.executable, "-c", "import bpy"], capture_output=True)
    return res.returncode == 0


# =========================================================================== GLB I/O
def read_glb(path: Path) -> tuple[dict, bytes]:
    data = path.read_bytes()
    if len(data) < 20 or data[:4] != b"glTF":
        raise ValueError(f"{path.name}: nav derīgs GLB (nav 'glTF' galvenes)")
    version, total = struct.unpack_from("<II", data, 4)
    if version != 2:
        raise ValueError(f"{path.name}: glTF versija {version}, vajag 2")
    if total != len(data):
        raise ValueError(f"{path.name}: GLB garums galvenē ({total}) nesakrīt ar faila izmēru ({len(data)})")
    offset, doc, binchunk = 12, None, b""
    while offset < len(data):
        clen, ctype = struct.unpack_from("<II", data, offset)
        chunk = data[offset + 8: offset + 8 + clen]
        if ctype == 0x4E4F534A:
            doc = json.loads(chunk.decode("utf-8"))
        elif ctype == 0x004E4942:
            binchunk = chunk
        offset += 8 + clen
    if doc is None:
        raise ValueError(f"{path.name}: GLB bez JSON daļas")
    return doc, binchunk


def write_glb(path: Path, doc: dict, binchunk: bytes) -> None:
    js = json.dumps(doc, separators=(",", ":")).encode("utf-8")
    js += b" " * ((4 - len(js) % 4) % 4)
    binchunk += b"\x00" * ((4 - len(binchunk) % 4) % 4)
    total = 12 + 8 + len(js) + (8 + len(binchunk) if binchunk else 0)
    with open(path, "wb") as f:
        f.write(struct.pack("<4sII", b"glTF", 2, total))
        f.write(struct.pack("<II", len(js), 0x4E4F534A) + js)
        if binchunk:
            f.write(struct.pack("<II", len(binchunk), 0x004E4942) + binchunk)


# =========================================================================== matricas
def mat_identity():
    return [[1.0 if i == j else 0.0 for j in range(4)] for i in range(4)]


def mat_mul(a, b):
    return [[sum(a[i][k] * b[k][j] for k in range(4)) for j in range(4)] for i in range(4)]


def mat_from_node(node: dict):
    if "matrix" in node:
        m = node["matrix"]  # column-major
        return [[m[c * 4 + r] for c in range(4)] for r in range(4)]
    tx, ty, tz = node.get("translation", [0, 0, 0])
    qx, qy, qz, qw = node.get("rotation", [0, 0, 0, 1])
    sx, sy, sz = node.get("scale", [1, 1, 1])
    r = [
        [1 - 2 * (qy * qy + qz * qz), 2 * (qx * qy - qz * qw), 2 * (qx * qz + qy * qw)],
        [2 * (qx * qy + qz * qw), 1 - 2 * (qx * qx + qz * qz), 2 * (qy * qz - qx * qw)],
        [2 * (qx * qz - qy * qw), 2 * (qy * qz + qx * qw), 1 - 2 * (qx * qx + qy * qy)],
    ]
    return [
        [r[0][0] * sx, r[0][1] * sy, r[0][2] * sz, tx],
        [r[1][0] * sx, r[1][1] * sy, r[1][2] * sz, ty],
        [r[2][0] * sx, r[2][1] * sy, r[2][2] * sz, tz],
        [0.0, 0.0, 0.0, 1.0],
    ]


def mat_to_gltf(m) -> list[float]:
    return [float(m[r][c]) for c in range(4) for r in range(4)]


def transform_point(m, p):
    return [m[i][0] * p[0] + m[i][1] * p[1] + m[i][2] * p[2] + m[i][3] for i in range(3)]


# =========================================================================== analīze
def _accessor_bounds(doc: dict, idx: int):
    acc = doc["accessors"][idx]
    mn, mx = acc.get("min"), acc.get("max")
    if mn is None or mx is None:
        return None
    if acc.get("normalized") and acc.get("componentType") in COMPONENT_MAX:
        d = COMPONENT_MAX[acc["componentType"]]
        mn = [max(v / d, -1.0) for v in mn]
        mx = [max(v / d, -1.0) for v in mx]
    return mn[:3], mx[:3]


def scene_bounds(doc: dict, root_matrix=None):
    """Pasaules koordinātu bounding box (no POSITION accessor min/max)."""
    lo = [math.inf] * 3
    hi = [-math.inf] * 3
    nodes = doc.get("nodes", [])
    scenes = doc.get("scenes") or [{"nodes": list(range(len(nodes)))}]
    scene = scenes[doc.get("scene", 0)]

    def visit(ni: int, parent):
        node = nodes[ni]
        m = mat_mul(parent, mat_from_node(node))
        if "mesh" in node:
            for prim in doc["meshes"][node["mesh"]].get("primitives", []):
                pos = prim.get("attributes", {}).get("POSITION")
                b = _accessor_bounds(doc, pos) if pos is not None else None
                if not b:
                    continue
                mn, mx = b
                for x in (mn[0], mx[0]):
                    for y in (mn[1], mx[1]):
                        for z in (mn[2], mx[2]):
                            p = transform_point(m, [x, y, z])
                            for i in range(3):
                                lo[i] = min(lo[i], p[i])
                                hi[i] = max(hi[i], p[i])
        for c in node.get("children", []):
            visit(c, m)

    root = root_matrix if root_matrix is not None else mat_identity()
    for ni in scene.get("nodes", []):
        visit(ni, root)
    if lo[0] is math.inf:
        return None
    return lo, hi


@dataclass
class Stats:
    triangles: int = 0
    meshes: int = 0
    materials: int = 0
    textures: int = 0
    texture_bytes: int = 0
    dims: tuple | None = None
    extensions: list = field(default_factory=list)
    variants: list = field(default_factory=list)


def glb_stats(path: Path) -> Stats:
    doc, _ = read_glb(path)
    st = Stats()
    st.meshes = len(doc.get("meshes", []))
    st.materials = len(doc.get("materials", []))
    st.textures = len(doc.get("images", []))
    st.extensions = sorted(doc.get("extensionsUsed", []))
    st.variants = [v.get("name", f"variant {i}") for i, v in enumerate(
        doc.get("extensions", {}).get("KHR_materials_variants", {}).get("variants", []))]
    views = doc.get("bufferViews", [])
    for img in doc.get("images", []):
        if "bufferView" in img:
            st.texture_bytes += views[img["bufferView"]].get("byteLength", 0)

    mesh_uses: dict[int, int] = {}
    for n in doc.get("nodes", []):
        if "mesh" in n:
            mesh_uses[n["mesh"]] = mesh_uses.get(n["mesh"], 0) + 1
    for mi, mesh in enumerate(doc.get("meshes", [])):
        tri = 0
        for prim in mesh.get("primitives", []):
            mode = prim.get("mode", 4)
            if "indices" in prim:
                count = doc["accessors"][prim["indices"]]["count"]
            else:
                pos = prim.get("attributes", {}).get("POSITION")
                count = doc["accessors"][pos]["count"] if pos is not None else 0
            if mode == 4:
                tri += count // 3
            elif mode in (5, 6):
                tri += max(count - 2, 0)
        st.triangles += tri * max(mesh_uses.get(mi, 1), 1)

    b = scene_bounds(doc)
    if b:
        st.dims = tuple(round(b[1][i] - b[0][i], 6) for i in range(3))
    return st


# =========================================================================== 1. konvertācija
BLENDER_JOB = r"""
import bpy, sys
argv = sys.argv[sys.argv.index("--") + 1:] if "--" in sys.argv else sys.argv[1:]
mode, src, dst = argv[0], argv[1], argv[2]
bpy.ops.wm.read_factory_settings(use_empty=True)
if mode == "fbx2glb":
    bpy.ops.import_scene.fbx(filepath=src)
    bpy.ops.export_scene.gltf(filepath=dst, export_format="GLB", export_apply=True, export_yup=True)
elif mode == "glb2usdz":
    bpy.ops.import_scene.gltf(filepath=src)
    # parametru nosaukumi atšķiras starp Blender versijām - padodam tikai esošos
    props = set(bpy.ops.wm.usd_export.get_rna_type().properties.keys())
    wanted = {"selected_objects_only": False, "export_materials": True, "export_textures": True,
              "export_textures_mode": "NEW", "generate_preview_surface": True,
              "convert_orientation": True, "export_global_forward_selection": "NEGATIVE_Z",
              "export_global_up_selection": "Y"}
    bpy.ops.wm.usd_export(filepath=dst, **{k: v for k, v in wanted.items() if k in props})
else:
    raise SystemExit("unknown mode " + mode)
"""


def run_blender(mode: str, src: Path, dst: Path, workdir: Path) -> None:
    script = workdir / "blender_job.py"
    script.write_text(BLENDER_JOB, encoding="utf-8")
    blender = which("blender")
    if blender:
        run(blender + ["-b", "--factory-startup", "--python", script, "--", mode, src, dst])
    elif bpy_available():
        run([sys.executable, script, mode, src, dst])
    else:
        raise FileNotFoundError("blender")
    if not dst.exists():
        raise RuntimeError(f"Blender neizveidoja {dst.name}")


def convert_to_glb(src: Path, workdir: Path) -> Path:
    ext = src.suffix.lower()
    if ext == ".glb":
        return src
    dst = workdir / (src.stem + ".glb")

    if ext == ".gltf":
        gt = gltf_transform_cmd()
        if gt:
            run(gt + ["copy", src, dst])
            return dst
        import trimesh
        log("Konvertē .gltf -> .glb ar trimesh")
        trimesh.load(str(src), force="scene").export(str(dst), file_type="glb")
        return dst

    if ext in FBX_EXT:
        tool = which("FBX2glTF", "fbx2gltf")
        if tool:
            run(tool + ["--binary", "-i", src, "-o", dst.with_suffix("")])
            if dst.exists():
                return dst
        try:
            log("Konvertē FBX ar Blender")
            run_blender("fbx2glb", src, dst, workdir)
            return dst
        except FileNotFoundError:
            raise RuntimeError(
                "FBX konvertācijai vajag vienu no: FBX2glTF (PATH), Blender (PATH) vai `pip install bpy`.\n"
                "  FBX2glTF: https://github.com/godotengine/FBX2glTF/releases\n"
                "  Blender:  https://www.blender.org/download/"
            ) from None

    if ext in TRIMESH_EXT:
        import trimesh
        log(f"Konvertē {ext} -> .glb ar trimesh")
        trimesh.load(str(src), force="scene").export(str(dst), file_type="glb")
        return dst

    raise RuntimeError(f"Neatbalstīts formāts: {ext}")


# =========================================================================== 2. sagatavošana
def prepare(glb: Path, dst: Path, a: argparse.Namespace):
    """Mērogs, ass, centrēšana - pievieno jaunu saknes mezglu (ģeometrija netiek mainīta)."""
    doc, binchunk = read_glb(glb)
    scale = a.scale if a.scale else UNITS[a.units]
    m = [[scale, 0, 0, 0], [0, scale, 0, 0], [0, 0, scale, 0], [0, 0, 0, 1]]
    if a.up == "z":  # Z-up -> Y-up: rotācija -90° ap X
        m = mat_mul(m, [[1, 0, 0, 0], [0, 0, 1, 0], [0, -1, 0, 0], [0, 0, 0, 1]])

    b = scene_bounds(doc, m)
    if b is None:
        log("  Brīdinājums: nevar noteikt bounding box (nav POSITION min/max)")
        shutil.copyfile(glb, dst)
        return None
    lo, hi = b
    t = [0.0, 0.0, 0.0]
    if a.center == "bottom":
        t = [-(lo[0] + hi[0]) / 2, -lo[1], -(lo[2] + hi[2]) / 2]
    elif a.center == "center":
        t = [-(lo[i] + hi[i]) / 2 for i in range(3)]
    tm = mat_identity()
    for i in range(3):
        tm[i][3] = t[i]
    m = mat_mul(tm, m)

    dims = tuple(hi[i] - lo[i] for i in range(3))
    if all(abs(m[i][j] - (1.0 if i == j else 0.0)) < 1e-9 for i in range(4) for j in range(4)):
        shutil.copyfile(glb, dst)
        return dims

    scenes = doc.setdefault("scenes", [{"nodes": list(range(len(doc.get("nodes", []))))}])
    si = doc.get("scene", 0)
    nodes = doc.setdefault("nodes", [])
    nodes.append({"name": "web3d_root", "matrix": mat_to_gltf(m), "children": scenes[si].get("nodes", [])})
    scenes[si]["nodes"] = [len(nodes) - 1]
    doc["scene"] = si
    write_glb(dst, doc, binchunk)
    return dims


def unit_warning(dims) -> str | None:
    if not dims:
        return None
    big = max(dims)
    if big > 20:
        return f"Modelis ir {big:.1f} m liels - iespējams, avots ir cm/mm. Mēģini --units cm vai --units mm."
    if big < 0.005:
        return f"Modelis ir {big * 1000:.2f} mm mazs - iespējams, vajag --scale."
    return None


# =========================================================================== 3. optimizācija
def optimize_gltf_transform(src: Path, dst: Path, a: argparse.Namespace, ratio: float, tex: int) -> None:
    tool = gltf_transform_cmd()
    if tool is None:
        npx = which("npx")
        if npx is None:
            raise FileNotFoundError("gltf-transform")
        tool = npx + ["--yes", "@gltf-transform/cli@4"]
    fmt = {"webp": "webp", "jpeg": "auto", "ktx2": "ktx2", "keep": "false"}[a.texture_format]
    if fmt == "ktx2" and not which("ktx", "toktx"):
        raise RuntimeError("KTX2 tekstūrām vajag KTX-Software (`ktx` PATH): "
                           "https://github.com/KhronosGroup/KTX-Software/releases")
    run(tool + [
        "optimize", src, dst,
        "--compress", a.compress,
        "--texture-compress", fmt,
        "--texture-size", str(tex),
        "--simplify", "true" if ratio < 1.0 else "false",
        "--simplify-ratio", str(ratio),
        "--simplify-error", str(a.error),
    ])


def optimize_gltfpack(src: Path, dst: Path, a: argparse.Namespace, ratio: float, tex: int) -> None:
    tool = which("gltfpack")
    if tool is None:
        raise FileNotFoundError("gltfpack")
    cmd = tool + ["-i", src, "-o", dst, "-si", str(ratio)]
    if a.compress == "meshopt":
        cmd.append("-cc")
    if a.texture_format == "ktx2":
        cmd += ["-tc", "-tl", str(tex)]
    run(cmd)


def _resize_and_encode(img, max_size: int, fmt: str):
    from PIL import Image

    img = img.copy()
    if max(img.size) > max_size:
        img.thumbnail((max_size, max_size), Image.LANCZOS)
    has_alpha = img.mode in ("RGBA", "LA") or (img.mode == "P" and "transparency" in img.info)
    buf = io.BytesIO()
    if fmt == "jpeg" and not has_alpha:
        img.convert("RGB").save(buf, format="JPEG", quality=85, optimize=True, progressive=True)
        out_fmt = "JPEG"
    elif fmt == "jpeg":
        img.save(buf, format="PNG", optimize=True)
        out_fmt = "PNG"
    else:
        img.save(buf, format="WEBP", quality=85, method=6)
        out_fmt = "WEBP"
    out = Image.open(io.BytesIO(buf.getvalue()))
    out.load()
    out.format = out_fmt
    return out


def optimize_python(src: Path, dst: Path, a: argparse.Namespace, ratio: float, tex: int) -> None:
    import logging
    import trimesh

    logging.getLogger("trimesh").setLevel(logging.ERROR)
    doc, _ = read_glb(src)
    if {"KHR_draco_mesh_compression", "EXT_meshopt_compression"} & set(doc.get("extensionsUsed", [])):
        raise RuntimeError("Python dzinējs nevar atkodēt Draco/Meshopt ievadi - lieto gltf-transform")
    if a.texture_format == "ktx2":
        raise RuntimeError("Python dzinējs neatbalsta KTX2")

    scene = trimesh.load(str(src), force="scene")
    processed: dict[int, object] = {}
    for name, geom in list(scene.geometry.items()):
        if not isinstance(geom, trimesh.Trimesh):
            continue
        has_uv = getattr(geom.visual, "uv", None) is not None
        target = int(len(geom.faces) * ratio)
        if ratio < 1.0 and len(geom.faces) >= a.min_faces and target >= 4:
            if has_uv and not a.force_decimate:
                log(f"  {name}: izlaiž decimāciju (tīkls ar UV; Python dzinējs UV nesaglabā)")
            else:
                try:
                    simple = geom.simplify_quadric_decimation(face_count=target)
                    material = getattr(geom.visual, "material", None)
                    if material is not None:
                        simple.visual = trimesh.visual.TextureVisuals(material=material)
                    log(f"  {name}: {len(geom.faces):,} -> {len(simple.faces):,} trijstūri"
                        + (" (UV zaudētas)" if has_uv else ""))
                    scene.geometry[name] = simple
                    geom = simple
                except Exception as exc:
                    log(f"  {name}: decimācija neizdevās ({exc})")

        mat = getattr(geom.visual, "material", None)
        if mat is None or a.texture_format == "keep":
            continue
        for attr in ("baseColorTexture", "metallicRoughnessTexture", "normalTexture",
                     "emissiveTexture", "occlusionTexture", "image"):
            img = getattr(mat, attr, None)
            if img is None:
                continue
            key = id(img)
            if key not in processed:
                fmt = a.texture_format if attr in ("baseColorTexture", "emissiveTexture", "image") else "webp"
                processed[key] = _resize_and_encode(img, tex, fmt)
            setattr(mat, attr, processed[key])

    dst.write_bytes(trimesh.exchange.gltf.export_glb(scene, extension_webp=(a.texture_format == "webp")))


ENGINES = {
    "gltf-transform": optimize_gltf_transform,
    "gltfpack": optimize_gltfpack,
    "python": optimize_python,
}


def optimize_with_engines(src: Path, dst: Path, a: argparse.Namespace, ratio: float, tex: int) -> str:
    order = list(ENGINES) if a.engine == "auto" else [a.engine]
    errors = []
    for name in order:
        try:
            ENGINES[name](src, dst, a, ratio, tex)
            if dst.exists():
                return name
            errors.append(f"{name}: neizveidoja izvades failu")
        except FileNotFoundError as exc:
            errors.append(f"{name}: nav instalēts ({exc})")
        except Exception as exc:
            errors.append(f"{name}: {exc}")
            if a.engine != "auto":
                break
        log(f"  {errors[-1]}")
    raise RuntimeError("Neviens dzinējs neizdevās:\n  " + "\n  ".join(errors))


def optimize_to_budget(src: Path, dst: Path, a: argparse.Namespace):
    ratio, tex = a.ratio, a.texture_size
    attempts = 1 + (a.max_attempts if a.max_size else 0)
    engine = ""
    for attempt in range(1, attempts + 1):
        log(f"Optimizē (ratio={ratio:.3g}, texture-size={tex})")
        engine = optimize_with_engines(src, dst, a, ratio, tex)
        size = dst.stat().st_size
        if not a.max_size or size <= a.max_size:
            return engine, ratio, tex
        log(f"  {human(size)} > limits {human(a.max_size)} - samazina kvalitāti")
        if tex <= 256 and ratio <= 0.05:
            break
        tex = max(256, tex // 2)
        ratio = max(0.05, round(ratio * 0.6, 4))
    raise_msg = f"Neizdevās iekļauties {human(a.max_size)} limitā (paliek {human(dst.stat().st_size)})"
    if a.strict_size:
        raise RuntimeError(raise_msg)
    log("  BRĪDINĀJUMS: " + raise_msg)
    return engine, ratio, tex


# =========================================================================== 4. validācija
def validate(path: Path) -> tuple[list[str], list[str]]:
    errors: list[str] = []
    warnings: list[str] = []
    try:
        doc, binchunk = read_glb(path)
    except Exception as exc:
        return [str(exc)], []
    if doc.get("asset", {}).get("version") != "2.0":
        errors.append("asset.version nav 2.0")
    for ext in doc.get("extensionsRequired", []):
        if ext not in SUPPORTED_EXTENSIONS:
            errors.append(f"Obligātais paplašinājums {ext} - model-viewer to neatbalsta")
    for ext in doc.get("extensionsUsed", []):
        if ext not in SUPPORTED_EXTENSIONS and ext not in doc.get("extensionsRequired", []):
            warnings.append(f"Paplašinājums {ext} tiks ignorēts")
    for i, bv in enumerate(doc.get("bufferViews", [])):
        if bv.get("buffer", 0) == 0 and bv.get("byteOffset", 0) + bv["byteLength"] > len(binchunk):
            errors.append(f"bufferView {i} iziet ārpus BIN daļas")
    for i, b in enumerate(doc.get("buffers", [])):
        if "uri" in b and not b["uri"].startswith("data:"):
            errors.append(f"buffer {i} atsaucas uz ārēju failu {b['uri']}")
    if not doc.get("meshes"):
        errors.append("Failā nav neviena tīkla (mesh)")

    gt = gltf_transform_cmd()
    if gt:
        res = subprocess.run(gt + ["validate", str(path)], capture_output=True, text=True)
        out = re.sub(r"\x1b\[[0-9;]*m", "", res.stdout + res.stderr)
        m = re.search(r"\bERROR\b(.*?)\bWARNING\b", out, re.S)
        if res.returncode != 0 or (m and "No errors found" not in m.group(1)):
            detail = m.group(1).strip()[:600] if m else out[-600:]
            errors.append("Khronos glTF-Validator atrada kļūdas:\n" + detail)
    return errors, warnings


# =========================================================================== 5. papildus
class _QuietHandler(http.server.SimpleHTTPRequestHandler):
    extensions_map = {**http.server.SimpleHTTPRequestHandler.extensions_map,
                      ".wasm": "application/wasm", ".js": "text/javascript", ".glb": "model/gltf-binary"}

    def log_message(self, *args):
        pass


def _serve(directory: Path):
    handler = functools.partial(_QuietHandler, directory=str(directory))
    httpd = socketserver.ThreadingTCPServer(("127.0.0.1", 0), handler)
    httpd.daemon_threads = True
    threading.Thread(target=httpd.serve_forever, daemon=True).start()
    return httpd


POSTER_HTML = """<!doctype html><html><head><meta charset="utf-8">
<style>html,body{{margin:0;background:{bg}}}model-viewer{{width:{w}px;height:{h}px;background:{bg}}}</style>
<script>
  self.ModelViewerElement = self.ModelViewerElement || {{}};
  {decoders}
</script>
<script type="module" src="{mv}"></script></head><body>
<model-viewer id="mv" src="model.glb" camera-orbit="{orbit}" shadow-intensity="1"
  environment-image="neutral" exposure="1" interaction-prompt="none"></model-viewer>
</body></html>"""


def render_poster(glb: Path, dst: Path, a: argparse.Namespace) -> None:
    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        raise RuntimeError("Poster attēlam vajag: pip install playwright && playwright install chromium") from None

    with tempfile.TemporaryDirectory(prefix="web3d_poster_") as tmp:
        tmpd = Path(tmp)
        shutil.copyfile(glb, tmpd / "model.glb")
        if (VENDOR_DIR / "model-viewer.min.js").exists():
            shutil.copytree(VENDOR_DIR, tmpd / "vendor")
            mv = "vendor/model-viewer.min.js"
            decoders = ("self.ModelViewerElement.dracoDecoderLocation='vendor/draco/';"
                        "self.ModelViewerElement.meshoptDecoderLocation='vendor/meshopt_decoder.js';"
                        "self.ModelViewerElement.ktx2TranscoderLocation='vendor/basis/';")
        else:
            mv = MODEL_VIEWER_CDN
            decoders = f"self.ModelViewerElement.meshoptDecoderLocation='{MESHOPT_CDN}';"
        w, h = a.poster_size
        (tmpd / "index.html").write_text(POSTER_HTML.format(
            bg=a.poster_bg, w=w, h=h, mv=mv, decoders=decoders, orbit=a.poster_orbit), encoding="utf-8")

        httpd = _serve(tmpd)
        try:
            with sync_playwright() as p:
                launch = {"args": ["--use-gl=angle", "--use-angle=swiftshader", "--enable-unsafe-swiftshader"]}
                if a.chromium:
                    launch["executable_path"] = a.chromium
                browser = p.chromium.launch(**launch)
                try:
                    page = browser.new_page(viewport={"width": w, "height": h})
                    page.goto(f"http://127.0.0.1:{httpd.server_address[1]}/index.html")
                    page.wait_for_function(
                        "() => { const m = document.getElementById('mv');"
                        " return m && m.loaded && m.modelIsVisible; }", timeout=120_000)
                    page.wait_for_timeout(1000)
                    data_url = page.evaluate(
                        "async () => { const m = document.getElementById('mv');"
                        " const b = await m.toBlob({mimeType: 'image/webp', qualityArgument: 0.85});"
                        " return await new Promise(r => { const f = new FileReader();"
                        " f.onload = () => r(f.result); f.readAsDataURL(b); }); }")
                finally:
                    browser.close()
        finally:
            httpd.shutdown()
            httpd.server_close()
        raw = base64.b64decode(data_url.split(",", 1)[1])
        if a.poster_bg.lower() == "transparent":
            dst.write_bytes(raw)
            return
        from PIL import Image
        img = Image.open(io.BytesIO(raw)).convert("RGBA")
        bg = Image.new("RGBA", img.size, a.poster_bg)
        Image.alpha_composite(bg, img).convert("RGB").save(dst, format="WEBP", quality=85, method=6)


def export_usdz(glb: Path, dst: Path, workdir: Path) -> None:
    try:
        run_blender("glb2usdz", glb, dst, workdir)
    except FileNotFoundError:
        raise RuntimeError("USDZ eksportam vajag Blender (PATH) vai `pip install bpy`") from None


MANIFEST_USER_FIELDS = ("title", "price", "description", "hotspots", "orbit", "notes")


def write_manifest(path: Path, data: dict) -> dict:
    old = {}
    if path.exists():
        try:
            old = json.loads(path.read_text(encoding="utf-8"))
        except Exception:
            old = {}
    for k in MANIFEST_USER_FIELDS:
        if k in old:
            data[k] = old[k]  # lietotāja rediģētos laukus nepārraksta
    # šoreiz neģenerēti, bet fails jau ir (iepriekšējā palaišanā) - saglabā atsauci
    defaults = {"poster": path.stem + ".poster.webp", "iosSrc": path.stem + ".usdz"}
    for k, default in defaults.items():
        if not data.get(k):
            for cand in (old.get(k), default):
                if cand and (path.parent / cand).exists():
                    data[k] = cand
                    break
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return data


EMBED_TEMPLATE = """<!-- 3D modelis: {title} | ģenerēts ar web-3d-pipeline/optimize.py -->
<!-- Šos divus skriptus ielādē VIENU reizi lapā (piem. tēmas <head>): -->
<script>
  self.ModelViewerElement = self.ModelViewerElement || {{}};
  self.ModelViewerElement.meshoptDecoderLocation = '{meshopt}';
</script>
<script type="module" src="{mv}"></script>

<model-viewer
  src="{base}{model}"{poster}{ios}
  alt="{alt}"
  camera-controls touch-action="pan-y"
  auto-rotate auto-rotate-delay="1500" rotation-per-second="30deg"
  shadow-intensity="1" environment-image="neutral"
  ar ar-modes="webxr scene-viewer quick-look" ar-scale="auto" ar-placement="floor"
  loading="lazy" reveal="auto"
  style="width:100%;height:480px;max-height:70vh;background:#f5f6f8;border-radius:12px">
  <button slot="ar-button" style="position:absolute;left:50%;bottom:16px;transform:translateX(-50%);padding:12px 20px;border:0;border-radius:999px;background:#2563eb;color:#fff;font:600 15px system-ui,sans-serif">
    Skatīties telpā (AR)
  </button>
</model-viewer>
"""


def write_embed(path: Path, man: dict, base_url: str) -> None:
    def esc(s) -> str:
        return str(s or "").replace("&", "&amp;").replace('"', "&quot;").replace("<", "&lt;")

    title = man.get("title")
    if isinstance(title, dict):
        title = title.get("lv") or next(iter(title.values()), "")
    poster = f'\n  poster="{esc(base_url + man["poster"])}"' if man.get("poster") else ""
    ios = f'\n  ios-src="{esc(base_url + man["iosSrc"])}"' if man.get("iosSrc") else ""
    path.write_text(EMBED_TEMPLATE.format(
        title=esc(title), alt=esc(title or "3D modelis"),
        mv=MODEL_VIEWER_CDN, meshopt=MESHOPT_CDN, base=esc(base_url), model=esc(man["model"]),
        poster=poster, ios=ios), encoding="utf-8")


# =========================================================================== vendor
VENDOR_FILES = [
    # (npm pakete, versija, ceļš tarbolā, mērķis vendor/ mapē)
    ("@google/model-viewer", MODEL_VIEWER_VERSION, "package/dist/model-viewer.min.js", "model-viewer.min.js"),
    ("meshoptimizer", MESHOPT_VERSION, "package/meshopt_decoder.js", "meshopt_decoder.js"),
    ("three", THREE_VERSION, "package/examples/jsm/libs/draco/gltf/draco_decoder.js", "draco/draco_decoder.js"),
    ("three", THREE_VERSION, "package/examples/jsm/libs/draco/gltf/draco_decoder.wasm", "draco/draco_decoder.wasm"),
    ("three", THREE_VERSION, "package/examples/jsm/libs/draco/gltf/draco_wasm_wrapper.js", "draco/draco_wasm_wrapper.js"),
    ("three", THREE_VERSION, "package/examples/jsm/libs/basis/basis_transcoder.js", "basis/basis_transcoder.js"),
    ("three", THREE_VERSION, "package/examples/jsm/libs/basis/basis_transcoder.wasm", "basis/basis_transcoder.wasm"),
]


def fetch_vendor(dest: Path = VENDOR_DIR) -> None:
    """Lejupielādē JS un dekoderus no npm reģistra -> vendor/ (lapa strādā bez ārējiem CDN)."""
    by_pkg: dict[tuple[str, str], list[tuple[str, str]]] = {}
    for pkg, ver, inner, target in VENDOR_FILES:
        by_pkg.setdefault((pkg, ver), []).append((inner, target))
    for (pkg, ver), files in by_pkg.items():
        url = f"https://registry.npmjs.org/{pkg}/-/{pkg.split('/')[-1]}-{ver}.tgz"
        log(f"Lejupielādē {pkg}@{ver}")
        with urllib.request.urlopen(url, timeout=180) as resp:
            blob = resp.read()
        with tarfile.open(fileobj=io.BytesIO(blob), mode="r:gz") as tar:
            for inner, target in files:
                member = tar.getmember(inner)
                out = dest / target
                out.parent.mkdir(parents=True, exist_ok=True)
                out.write_bytes(tar.extractfile(member).read())
                log(f"  -> vendor/{target} ({human(member.size)})")
    (dest / "VERSIONS.txt").write_text("\n".join(f"{p}@{v}" for (p, v) in by_pkg) + "\n", encoding="utf-8")
    log(f"Gatavs: {dest}")


# =========================================================================== galvenā plūsma
@dataclass
class Result:
    source: Path
    output: Path | None = None
    ok: bool = False
    error: str = ""
    engine: str = ""
    size_in: int = 0
    size_out: int = 0
    tris_in: int = 0
    tris_out: int = 0
    dims: tuple | None = None
    ratio: float = 0
    texture_size: int = 0
    poster: Path | None = None
    usdz: Path | None = None
    manifest: Path | None = None
    warnings: list = field(default_factory=list)
    seconds: float = 0


def output_path_for(src: Path, a: argparse.Namespace, batch: bool) -> Path:
    if a.output is None:
        return src.with_name(src.stem + ".web.glb")
    if batch or a.output.suffix.lower() != ".glb":
        return a.output / (src.stem + ".web.glb")
    return a.output


def process(src: Path, a: argparse.Namespace, batch: bool) -> Result:
    t0 = _dt.datetime.now()
    r = Result(source=src, size_in=src.stat().st_size)
    dst = output_path_for(src, a, batch).resolve()
    dst.parent.mkdir(parents=True, exist_ok=True)
    stem = dst.name[:-len(".web.glb")] if dst.name.endswith(".web.glb") else dst.stem
    workdir = Path(tempfile.mkdtemp(prefix="web3d_"))
    try:
        glb = convert_to_glb(src, workdir)
        r.tris_in = glb_stats(glb).triangles

        prepared = workdir / "prepared.glb"
        r.dims = prepare(glb, prepared, a)
        w = unit_warning(r.dims)
        if w:
            r.warnings.append(w)

        r.engine, r.ratio, r.texture_size = optimize_to_budget(prepared, dst, a)
        r.output = dst
        r.size_out = dst.stat().st_size
        st = glb_stats(dst)
        r.tris_out = st.triangles

        if not a.no_validate:
            errs, warns = validate(dst)
            r.warnings += warns
            if errs:
                raise RuntimeError("Validācija neizdevās:\n  " + "\n  ".join(errs))

        if a.usdz:
            try:
                r.usdz = dst.with_name(stem + ".usdz")
                export_usdz(prepared, r.usdz, workdir)
            except Exception as exc:
                r.usdz = None
                r.warnings.append(f"USDZ: {exc}")
        if a.poster:
            try:
                r.poster = dst.with_name(stem + ".poster.webp")
                render_poster(prepared, r.poster, a)
            except Exception as exc:
                r.poster = None
                r.warnings.append(f"Poster: {str(exc).splitlines()[0]}")

        if not a.no_manifest:
            dims = st.dims or r.dims
            r.manifest = dst.with_name(stem + ".json")
            man = write_manifest(r.manifest, {
                "title": stem.replace("_", " ").replace("-", " ").strip().capitalize(),
                "price": None,
                "description": "",
                "model": dst.name,
                "poster": r.poster.name if r.poster else None,
                "iosSrc": r.usdz.name if r.usdz else None,
                "dimensions_m": {"x": round(dims[0], 4), "y": round(dims[1], 4),
                                 "z": round(dims[2], 4)} if dims else None,
                "triangles": st.triangles,
                "textures": st.textures,
                "sizeBytes": r.size_out,
                "variants": st.variants,
                "hotspots": [],
                "source": src.name,
                "generated": _dt.datetime.now().isoformat(timespec="seconds"),
            })
            write_embed(dst.with_name(stem + ".embed.html"), man, a.base_url)
        r.ok = True
    except Exception as exc:
        r.error = str(exc)
    finally:
        r.seconds = (_dt.datetime.now() - t0).total_seconds()
        if a.keep_temp:
            log(f"Starpfaili: {workdir}")
        else:
            shutil.rmtree(workdir, ignore_errors=True)
    return r


def print_result(r: Result) -> None:
    print()
    print("=" * 64)
    if not r.ok:
        print(f" KĻŪDA  {r.source.name}\n {r.error}")
        print("=" * 64)
        return
    print(f" Ievade : {r.source.name:<36} {human(r.size_in):>12}")
    print(f" Izvade : {r.output.name:<36} {human(r.size_out):>12}")
    if r.size_in:
        print(f" Samazinājums: {100 * (1 - r.size_out / r.size_in):.1f}%   dzinējs: {r.engine}"
              f"   ratio={r.ratio:.3g}  tekstūras ≤{r.texture_size}px")
    print(f" Trijstūri: {r.tris_in:,} -> {r.tris_out:,}")
    if r.dims:
        print(f" Izmēri: {r.dims[0] * 100:.1f} × {r.dims[1] * 100:.1f} × {r.dims[2] * 100:.1f} cm (P × A × Dz)")
    if r.poster:
        print(f" Poster: {r.poster.name}")
    if r.usdz:
        print(f" USDZ  : {r.usdz.name}")
    for w in r.warnings:
        print(f" ! {w}")
    print("=" * 64)
    print(f" Gatavs: {r.output}")


def update_catalog(results: list[Result]) -> list[Path]:
    """Atjaunina <izvades mape>/catalog.json - modeļu saraksts index.html izvēlnei."""
    written = []
    by_dir: dict[Path, list[Result]] = {}
    for r in results:
        if r.ok and r.output:
            by_dir.setdefault(r.output.parent, []).append(r)
    for folder, items in by_dir.items():
        path = folder / "catalog.json"
        cat = {"models": []}
        if path.exists():
            try:
                cat = json.loads(path.read_text(encoding="utf-8"))
            except Exception:
                pass
        models = {m.get("model"): m for m in cat.get("models", []) if (folder / m.get("model", "")).exists()}
        for r in items:
            man = json.loads(r.manifest.read_text(encoding="utf-8")) if r.manifest else {}
            models[r.output.name] = {"model": r.output.name, "title": man.get("title") or r.output.stem,
                                     "poster": man.get("poster")}
        cat["models"] = sorted(models.values(), key=lambda m: str(m["model"]))
        path.write_text(json.dumps(cat, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        written.append(path)
    return written


def collect_inputs(paths: list[Path], recursive: bool) -> list[Path]:
    files: list[Path] = []
    for p in paths:
        if p.is_dir():
            it = p.rglob("*") if recursive else p.glob("*")
            files += sorted(f for f in it if f.is_file() and f.suffix.lower() in INPUT_EXT
                            and not f.name.endswith(".web.glb"))
        elif p.is_file():
            files.append(p)
        else:
            log(f"Nav atrasts: {p}")
    seen, unique = set(), []
    for f in files:
        rf = f.resolve()
        if rf not in seen:
            seen.add(rf)
            unique.append(rf)
    return unique


def write_report(path: Path, results: list[Result]) -> None:
    with open(path, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["source", "output", "ok", "error", "engine", "size_in", "size_out", "reduction_pct",
                    "triangles_in", "triangles_out", "width_cm", "height_cm", "depth_cm",
                    "ratio", "texture_size", "poster", "usdz", "warnings", "seconds"])
        for r in results:
            d = [f"{v * 100:.1f}" for v in r.dims] if r.dims else ["", "", ""]
            w.writerow([
                r.source.name, r.output.name if r.output else "", r.ok, r.error.replace("\n", " "), r.engine,
                r.size_in, r.size_out,
                f"{100 * (1 - r.size_out / r.size_in):.1f}" if r.ok and r.size_in else "",
                r.tris_in, r.tris_out, *d, r.ratio, r.texture_size,
                r.poster.name if r.poster else "", r.usdz.name if r.usdz else "",
                " | ".join(r.warnings), f"{r.seconds:.1f}",
            ])


def _poster_size(text: str) -> tuple[int, int]:
    try:
        w, h = (int(v) for v in text.lower().split("x"))
        return w, h
    except Exception:
        raise argparse.ArgumentTypeError("formāts: 1200x1200") from None


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="Optimizē 3D modeļus (.fbx/.gltf/.glb/.obj/.stl/.ply) tīmeklim -> viegls .glb",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter,
    )
    p.add_argument("inputs", nargs="*", type=Path, help="Faili un/vai mapes")
    p.add_argument("-o", "--output", type=Path,
                   help="Izvades .glb fails vai mape (vairākiem failiem - mape). Noklusējums: blakus oriģinālam")
    p.add_argument("-r", "--recursive", action="store_true", help="Mapēs meklēt arī apakšmapēs")

    g = p.add_argument_group("optimizācija")
    g.add_argument("--engine", choices=["auto", *ENGINES], default="auto")
    g.add_argument("--ratio", type=float, default=0.5, help="Paturamā ģeometrijas daļa (1 = bez decimācijas)")
    g.add_argument("--error", type=float, default=0.001, help="Decimācijas kļūdas robeža (gltf-transform)")
    g.add_argument("--texture-size", type=int, default=2048, help="Maks. tekstūras izmērs px")
    g.add_argument("--texture-format", choices=["webp", "jpeg", "ktx2", "keep"], default="webp")
    g.add_argument("--compress", choices=["draco", "meshopt", "quantize", "false"], default="draco",
                   help="Ģeometrijas saspiešana (gltf-transform)")
    g.add_argument("--max-size", type=parse_size, help="Faila limits, piem. 3MB - automātiski samazina kvalitāti")
    g.add_argument("--max-attempts", type=int, default=5, help="Papildu mēģinājumi --max-size sasniegšanai")
    g.add_argument("--strict-size", action="store_true", help="Kļūda, ja --max-size netiek sasniegts")
    g.add_argument("--force-decimate", action="store_true",
                   help="Python dzinējā decimēt arī tīklus ar UV (tekstūras tiks zaudētas)")
    g.add_argument("--min-faces", type=int, default=1000, help="Python dzinējā mazākus tīklus nedecimē")

    g = p.add_argument_group("mērogs un orientācija")
    g.add_argument("--units", choices=list(UNITS), default="m", help="Avota mērvienības (glTF = metri)")
    g.add_argument("--scale", type=float, help="Patvaļīgs mēroga koeficients (aizstāj --units)")
    g.add_argument("--up", choices=["y", "z"], default="y", help="Avota augšas ass (CAD/STL bieži Z)")
    g.add_argument("--center", choices=["bottom", "center", "none"], default="bottom",
                   help="bottom = centrēt un nolikt uz grīdas (AR)")

    g = p.add_argument_group("papildus faili")
    g.add_argument("--poster", action="store_true", help="Renderēt <nosaukums>.poster.webp (vajag playwright)")
    g.add_argument("--poster-size", type=_poster_size, default=(1200, 1200), help="Poster izmērs, piem. 1200x1200")
    g.add_argument("--poster-orbit", default="30deg 75deg auto", help="Kameras leņķis posterim")
    g.add_argument("--poster-bg", default="#ffffff", help="Poster fona krāsa (#rrggbb) vai 'transparent'")
    g.add_argument("--chromium", help="Ceļš uz Chromium (ja playwright pārlūks nav instalēts)")
    g.add_argument("--usdz", action="store_true", help="Eksportēt <nosaukums>.usdz iOS AR (vajag Blender/bpy)")
    g.add_argument("--no-manifest", action="store_true", help="Neveidot .json manifestu un .embed.html")
    g.add_argument("--base-url", default="", help="URL prefikss embed kodā, piem. /image/3d/")
    g.add_argument("--no-validate", action="store_true", help="Izlaist validāciju")
    g.add_argument("--report", type=Path, help="CSV atskaite (vairākiem failiem noklusējumā <izvade>/report.csv)")
    g.add_argument("--keep-temp", action="store_true", help="Nedzēst starpfailus")

    p.add_argument("--fetch-vendor", action="store_true",
                   help="Lejupielādēt model-viewer un dekoderus uz vendor/ (lapa strādā bez ārējiem CDN)")
    a = p.parse_args(argv)

    if not a.fetch_vendor and not a.inputs:
        p.error("norādi vismaz vienu failu vai mapi (vai --fetch-vendor)")
    if not 0.0 < a.ratio <= 1.0:
        p.error("--ratio jābūt intervālā (0, 1]")
    if a.texture_size < 16:
        p.error("--texture-size pārāk mazs")
    if a.scale is not None and a.scale <= 0:
        p.error("--scale jābūt > 0")
    return a


def main(argv: list[str] | None = None) -> int:
    a = parse_args(argv)
    if a.fetch_vendor:
        try:
            fetch_vendor()
        except Exception as exc:
            log(f"KĻŪDA: {exc}")
            return 2
        if not a.inputs:
            return 0

    files = collect_inputs(a.inputs, a.recursive)
    if not files:
        log("Nav atrasts neviens atbalstīts fails (" + ", ".join(sorted(INPUT_EXT)) + ")")
        return 1
    batch = len(files) > 1 or any(p.is_dir() for p in a.inputs)
    if batch and a.output and a.output.suffix.lower() == ".glb":
        log("Vairākiem failiem -o jābūt mapei")
        return 1

    results = []
    for i, f in enumerate(files, 1):
        if batch:
            log(f"[{i}/{len(files)}] {f.name}")
        r = process(f, a, batch)
        print_result(r)
        results.append(r)

    if not a.no_manifest:
        for c in update_catalog(results):
            log(f"Katalogs: {c}")

    report = a.report or ((a.output or files[0].parent) / "report.csv" if batch else None)
    if report:
        report.parent.mkdir(parents=True, exist_ok=True)
        write_report(report, results)
        log(f"Atskaite: {report}  ({sum(r.ok for r in results)}/{len(results)} veiksmīgi)")
    return 0 if all(r.ok for r in results) else 2


if __name__ == "__main__":
    sys.exit(main())
