#!/usr/bin/env bash
# Builds ONE package for every supported OpenCart version:
#   dist/v<version>/announcement_bar_pro.ocmod.zip
# - OpenCart 3.0.x installer uses:   install.xml + upload/   (everything else in the zip is ignored)
# - OpenCart 4.0.x/4.1.x installer:  install.json + admin/ catalog/ system/   (the OC3 files are copied
#   along unused; they cannot be called directly)
# Keep the file name: OpenCart 4 takes the extension code from it.
set -euo pipefail
cd "$(dirname "$0")"
OC3=../announcement-bar-pro
OC4=../announcement-bar-pro-oc4/src
V3=$(grep -oP '<version>\K[^<]+' "$OC3/install.xml")
# python3, or python (Windows / Git Bash)
PY=$(python3 -c 'import sys' >/dev/null 2>&1 && echo python3 || echo python)
V4=$($PY -c "import json,sys;print(json.load(open(sys.argv[1]))['version'])" "$OC4/install.json")
[ "$V3" = "$V4" ] || { echo "Version mismatch: OC3 $V3 / OC4 $V4" >&2; exit 1; }
STAGE=$(mktemp -d); trap 'rm -rf "$STAGE"' EXIT
cp -r "$OC3/upload" "$OC3/install.xml" "$STAGE/"
cp -r "$OC4/install.json" "$OC4/admin" "$OC4/catalog" "$OC4/system" "$STAGE/"
cp README.md CHANGELOG.md "$OC3/LICENSE.txt" "$STAGE/"
OUT="dist/v${V3}/announcement_bar_pro.ocmod.zip"
mkdir -p "dist/v${V3}" && rm -f "$OUT"
if command -v zip >/dev/null 2>&1; then
	(cd "$STAGE" && zip -r -X "$OLDPWD/$OUT" . -x '*.DS_Store' >/dev/null)
else
	# No "zip" (e.g. Git Bash on Windows): same archive with Python's zipfile — relative paths only, so a
	# Windows Python never has to read a /tmp path.
	(cd "$STAGE" && $PY - <<'PYZIP'
import os, zipfile
with zipfile.ZipFile('_pkg.zip', 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk('.'):
        dirs.sort()
        for d in dirs:
            z.writestr(zipfile.ZipInfo(os.path.relpath(os.path.join(root, d), '.').replace(os.sep, '/') + '/'), '')
        for f in sorted(files):
            if f in ('_pkg.zip', '.DS_Store'):
                continue
            p = os.path.join(root, f)
            z.write(p, os.path.relpath(p, '.').replace(os.sep, '/'))
PYZIP
	)
	mv "$STAGE/_pkg.zip" "$OUT"
fi
echo "$OUT ($(du -h "$OUT" | cut -f1))"
