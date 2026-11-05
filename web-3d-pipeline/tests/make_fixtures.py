#!/usr/bin/env python3
"""
Ģenerē testa modeļus mapē tests/fixtures/ (vai norādītajā mapē).

  sphere_textured.glb   - sfēra ar 4096 px tekstūru (UV), ~82k trijstūri
  part_mm_zup.stl       - kaste 500 × 300 × 100 mm, Z ass uz augšu (CAD stils)
  vase_variants.glb     - vāze ar 2 materiālu variantiem (KHR_materials_variants)
  chair.fbx             - FBX ar vairākiem objektiem un tekstūru (tikai ja ir bpy)
"""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent))

import numpy as np  # noqa: E402
import trimesh  # noqa: E402
from PIL import Image  # noqa: E402

from optimize import read_glb, write_glb  # noqa: E402


def checker(size: int, cells: int = 16, seed: int = 1) -> Image.Image:
    rng = np.random.default_rng(seed)
    y, x = np.mgrid[0:size, 0:size]
    board = (((x * cells) // size + (y * cells) // size) % 2).astype(np.float32)
    noise = rng.random((size, size)).astype(np.float32) * 0.15
    r = (0.85 - 0.45 * board + noise) * 255
    g = (0.55 - 0.25 * board + noise) * 255
    b = (0.30 - 0.10 * board + noise) * 255
    return Image.fromarray(np.clip(np.dstack([r, g, b]), 0, 255).astype("uint8"))


def sphere_textured(out: Path) -> None:
    m = trimesh.creation.icosphere(subdivisions=6, radius=0.2)
    v = m.vertices / 0.2
    uv = np.c_[np.arctan2(v[:, 1], v[:, 0]) / (2 * np.pi) + 0.5, v[:, 2] * 0.5 + 0.5]
    m.visual = trimesh.visual.TextureVisuals(
        uv=uv, material=trimesh.visual.material.PBRMaterial(baseColorTexture=checker(4096)))
    trimesh.Scene([m]).export(str(out))


def part_mm_zup(out: Path) -> None:
    box = trimesh.creation.box(extents=[500, 300, 100])
    box.apply_translation([250, 150, 50])  # stūris origin, kā CAD
    box.export(str(out))


def vase_variants(out: Path) -> None:
    prof = np.array([[0.0, 0], [0.09, 0], [0.11, 0.05], [0.13, 0.15], [0.10, 0.27],
                     [0.06, 0.33], [0.07, 0.38], [0.0, 0.38]])
    vase = trimesh.creation.revolve(prof, sections=96)
    vase.apply_transform(trimesh.transformations.rotation_matrix(-np.pi / 2, [1, 0, 0]))  # Z->Y
    vase.visual = trimesh.visual.TextureVisuals(
        material=trimesh.visual.material.PBRMaterial(baseColorFactor=[200, 90, 60, 255],
                                                     metallicFactor=0.0, roughnessFactor=0.5))
    tmp = out.with_suffix(".tmp.glb")
    trimesh.Scene([vase]).export(str(tmp))
    doc, binchunk = read_glb(tmp)
    tmp.unlink()
    doc["materials"][0]["name"] = "Terakota"
    doc["materials"].append({"name": "Zila glazūra", "pbrMetallicRoughness": {
        "baseColorFactor": [0.12, 0.3, 0.75, 1], "metallicFactor": 0.1, "roughnessFactor": 0.2}})
    doc["materials"].append({"name": "Melna", "pbrMetallicRoughness": {
        "baseColorFactor": [0.05, 0.05, 0.05, 1], "metallicFactor": 0.0, "roughnessFactor": 0.6}})
    doc.setdefault("extensionsUsed", []).append("KHR_materials_variants")
    doc.setdefault("extensions", {})["KHR_materials_variants"] = {
        "variants": [{"name": "Terakota"}, {"name": "Zila"}, {"name": "Melna"}]}
    for mesh in doc["meshes"]:
        for prim in mesh["primitives"]:
            prim["material"] = 0
            prim.setdefault("extensions", {})["KHR_materials_variants"] = {"mappings": [
                {"material": 0, "variants": [0]}, {"material": 1, "variants": [1]},
                {"material": 2, "variants": [2]}]}
    write_glb(out, doc, binchunk)


FBX_SCRIPT = r"""
import bpy, sys
out, tex = sys.argv[-2], sys.argv[-1]
bpy.ops.wm.read_factory_settings(use_empty=True)
img = bpy.data.images.load(tex)
mat = bpy.data.materials.new("Koks"); mat.use_nodes = True
bsdf = mat.node_tree.nodes["Principled BSDF"]
t = mat.node_tree.nodes.new("ShaderNodeTexImage"); t.image = img
mat.node_tree.links.new(t.outputs["Color"], bsdf.inputs["Base Color"])
metal = bpy.data.materials.new("Metals"); metal.use_nodes = True
mb = metal.node_tree.nodes["Principled BSDF"]
mb.inputs["Base Color"].default_value = (0.2, 0.2, 0.22, 1); mb.inputs["Metallic"].default_value = 1.0
mb.inputs["Roughness"].default_value = 0.35

def add(obj, m):
    obj.data.materials.append(m)
    bpy.ops.object.shade_smooth()

# sēdeklis 45 x 45 x 4 cm, augstumā 45 cm (Blender: Z uz augšu, metri)
bpy.ops.mesh.primitive_cube_add(size=1, location=(0, 0, 0.45)); s = bpy.context.object
s.scale = (0.45, 0.45, 0.04); bpy.ops.object.transform_apply(scale=True)
bpy.ops.object.modifier_add(type="BEVEL"); s.modifiers[-1].width = 0.01; s.modifiers[-1].segments = 4
bpy.ops.object.modifier_add(type="SUBSURF"); s.modifiers[-1].levels = 2
bpy.ops.object.mode_set(mode="EDIT"); bpy.ops.mesh.select_all(action="SELECT")
bpy.ops.uv.smart_project(); bpy.ops.object.mode_set(mode="OBJECT")
add(s, mat)
# atzveltne
bpy.ops.mesh.primitive_cube_add(size=1, location=(0, 0.205, 0.72)); b = bpy.context.object
b.scale = (0.45, 0.04, 0.5); bpy.ops.object.transform_apply(scale=True)
bpy.ops.object.modifier_add(type="BEVEL"); b.modifiers[-1].width = 0.01; b.modifiers[-1].segments = 4
bpy.ops.object.mode_set(mode="EDIT"); bpy.ops.mesh.select_all(action="SELECT")
bpy.ops.uv.smart_project(); bpy.ops.object.mode_set(mode="OBJECT")
add(b, mat)
# kājas
for x in (-0.19, 0.19):
    for y in (-0.19, 0.19):
        bpy.ops.mesh.primitive_cylinder_add(vertices=48, radius=0.018, depth=0.43, location=(x, y, 0.215))
        add(bpy.context.object, metal)
bpy.ops.export_scene.fbx(filepath=out, path_mode="COPY", embed_textures=True, apply_unit_scale=True)
"""


def chair_fbx(out: Path) -> bool:
    if subprocess.run([sys.executable, "-c", "import bpy"], capture_output=True).returncode != 0:
        print("  (izlaiž chair.fbx - nav bpy)")
        return False
    tex = out.with_name("chair_wood.png")
    checker(1024, cells=8, seed=3).save(tex)
    script = out.with_name("_make_fbx.py")
    script.write_text(FBX_SCRIPT, encoding="utf-8")
    res = subprocess.run([sys.executable, str(script), str(out), str(tex)], capture_output=True, text=True)
    script.unlink()
    if res.returncode != 0 or not out.exists():
        print(res.stdout[-2000:], res.stderr[-2000:])
        raise RuntimeError("FBX ģenerēšana neizdevās")
    return True


def make_all(dest: Path) -> dict[str, Path]:
    dest.mkdir(parents=True, exist_ok=True)
    files = {
        "sphere": dest / "sphere_textured.glb",
        "part": dest / "part_mm_zup.stl",
        "vase": dest / "vase_variants.glb",
        "chair": dest / "chair.fbx",
    }
    if not files["sphere"].exists():
        sphere_textured(files["sphere"])
    if not files["part"].exists():
        part_mm_zup(files["part"])
    if not files["vase"].exists():
        vase_variants(files["vase"])
    if not files["chair"].exists() and not chair_fbx(files["chair"]):
        files.pop("chair")
    return files


if __name__ == "__main__":
    target = Path(sys.argv[1]) if len(sys.argv) > 1 else HERE / "fixtures"
    for k, v in make_all(target).items():
        print(f"{k:7} {v}  ({v.stat().st_size / 1024:.0f} KB)")
