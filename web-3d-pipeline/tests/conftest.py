from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path

import pytest

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
sys.path.insert(0, str(ROOT))
sys.path.insert(0, str(HERE))

import make_fixtures  # noqa: E402

OPTIMIZE = ROOT / "optimize.py"


def chromium_path() -> str | None:
    """Ceļš uz Chromium, ja playwright pārlūks nav instalēts standarta vietā (WEB3D_CHROMIUM)."""
    return os.environ.get("WEB3D_CHROMIUM") or None


def run_optimize(*args, check: bool = True) -> subprocess.CompletedProcess:
    cmd = [sys.executable, str(OPTIMIZE), *map(str, args)]
    if chromium_path() and "--poster" in cmd:
        cmd += ["--chromium", chromium_path()]
    res = subprocess.run(cmd, capture_output=True, text=True)
    if check and res.returncode != 0:
        raise AssertionError(f"optimize.py exit {res.returncode}\n{res.stdout}\n{res.stderr}")
    return res


def has_gltf_transform() -> bool:
    return shutil.which("gltf-transform") is not None


def has_bpy() -> bool:
    return subprocess.run([sys.executable, "-c", "import bpy"], capture_output=True).returncode == 0 \
        or shutil.which("blender") is not None


def has_playwright() -> bool:
    try:
        import playwright  # noqa: F401
        return True
    except ImportError:
        return False


@pytest.fixture(scope="session")
def fixtures() -> dict[str, Path]:
    return make_fixtures.make_all(HERE / "fixtures")


@pytest.fixture
def run():
    return run_optimize
