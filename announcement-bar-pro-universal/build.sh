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
V4=$(python3 -c "import json,sys;print(json.load(open(sys.argv[1]))['version'])" "$OC4/install.json")
[ "$V3" = "$V4" ] || { echo "Version mismatch: OC3 $V3 / OC4 $V4" >&2; exit 1; }
STAGE=$(mktemp -d); trap 'rm -rf "$STAGE"' EXIT
cp -r "$OC3/upload" "$OC3/install.xml" "$STAGE/"
cp -r "$OC4/install.json" "$OC4/admin" "$OC4/catalog" "$OC4/system" "$STAGE/"
cp README.md CHANGELOG.md "$OC3/LICENSE.txt" "$STAGE/"
OUT="dist/v${V3}/announcement_bar_pro.ocmod.zip"
mkdir -p "dist/v${V3}" && rm -f "$OUT"
(cd "$STAGE" && zip -r -X "$OLDPWD/$OUT" . -x '*.DS_Store' >/dev/null)
echo "$OUT ($(du -h "$OUT" | cut -f1))"
