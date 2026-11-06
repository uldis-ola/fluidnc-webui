#!/usr/bin/env python3
"""Būvē OpenCart instalācijas pakotni: dist/product_3d_viewer_v<versija>.ocmod.zip

  python build.py            # pārbauda PHP sintaksi (ja php ir PATH) un izveido zip
"""
import re
import shutil
import subprocess
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
UPLOAD = ROOT / "upload"
CONTROLLER = UPLOAD / "admin/controller/extension/module/product_3d.php"
REQUIRED = [
    "admin/controller/extension/module/product_3d.php",
    "admin/model/extension/module/product_3d.php",
    "admin/view/template/extension/module/product_3d.twig",
    "admin/view/template/extension/module/product_3d_tab.twig",
    "catalog/controller/extension/module/product_3d.php",
    "catalog/model/extension/module/product_3d.php",
    "catalog/view/theme/default/template/extension/module/product_3d.twig",
    "catalog/view/theme/default/template/extension/module/product_3d_assets.twig",
    "catalog/view/javascript/product_3d/model-viewer.min.js",
    "catalog/view/javascript/product_3d/product_3d.js",
    "catalog/view/javascript/product_3d/draco/draco_decoder.wasm",
]


def main() -> int:
    version = re.search(r"const VERSION = '([\d.]+)'", CONTROLLER.read_text(encoding="utf-8")).group(1)
    missing = [p for p in REQUIRED if not (UPLOAD / p).is_file()]
    if missing:
        print("Trūkst failu:\n  " + "\n  ".join(missing))
        return 1

    if shutil.which("php"):
        for f in sorted(UPLOAD.rglob("*.php")):
            res = subprocess.run(["php", "-l", str(f)], capture_output=True, text=True)
            if res.returncode != 0:
                print(res.stdout, res.stderr)
                return 1
        print("php -l: OK")

    dist = ROOT / "dist"
    dist.mkdir(exist_ok=True)
    out = dist / f"product_3d_viewer_v{version}.ocmod.zip"
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
        # dokumentācija zip saknē (OpenCart instalētājs apstrādā tikai upload/ un install.xml)
        for extra, name in ((ROOT / "docs" / "USER_GUIDE.md", "USER_GUIDE.md"),
                            (ROOT / "docs" / "CHANGELOG_EN.md", "CHANGELOG.md"),
                            (ROOT / "LICENSE.txt", "LICENSE.txt")):
            if extra.is_file():
                z.write(extra, name)
        for f in sorted(UPLOAD.rglob("*")):
            if f.is_file() and f.name not in (".DS_Store", "Thumbs.db"):
                z.write(f, "upload/" + f.relative_to(UPLOAD).as_posix())
    print(f"{out.relative_to(ROOT)}  ({out.stat().st_size / 1024:.0f} KB, {len(z.namelist())} faili)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
