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
# Every @version header, VERSION constant and JS/CSS header in both kits must carry the same version.
BAD=$(grep -rHoE "@version [0-9.]+|const +VERSION += '[0-9.]+'|Announcement Bar Pro v[0-9.]+[0-9]" "$OC3/upload" "$OC4" \
	| grep -vE "(@version |VERSION += '|Announcement Bar Pro v)${V3//./\\.}'?$" || true)
[ -z "$BAD" ] || { echo "Version mismatch (expected $V3):" >&2; echo "$BAD" >&2; exit 1; }
# The storefront files are shared: they must be byte-identical in both kits.
for f in announcement_bar_pro.js announcement_bar_pro_pre.js announcement_bar_pro.css; do
	cmp -s "$OC3/upload/catalog/view/javascript/announcement_bar_pro/$f" "$OC4/catalog/view/javascript/$f" \
		|| { echo "Shared file differs between kits: $f" >&2; exit 1; }
done
# 2.4.1: the WordPress edition uses the same storefront files (its names use "-" instead of "_"). When its directory
# is next to this one, every copy must be byte-identical to the OpenCart ones.
WP=../announcement-bar-pro-wordpress/announcement-bar-pro/assets
if [ -d "$WP" ]; then
	for pair in "announcement_bar_pro.js:js/announcement-bar-pro.js" "announcement_bar_pro_pre.js:js/announcement-bar-pro-pre.js" "announcement_bar_pro.css:css/announcement-bar-pro.css"; do
		cmp -s "$OC3/upload/catalog/view/javascript/announcement_bar_pro/${pair%%:*}" "$WP/${pair#*:}" \
			|| { echo "Shared storefront file differs between OpenCart and WordPress: ${pair%%:*} / $WP/${pair#*:}" >&2; exit 1; }
	done
fi
# The admin script and styles (live preview, autocomplete, import) are shared too (2.3.0).
for f in abp_admin.js abp_admin.css; do
	cmp -s "$OC3/upload/admin/view/javascript/announcement_bar_pro/$f" "$OC4/admin/view/javascript/$f" 		|| { echo "Shared admin file differs between kits: $f" >&2; exit 1; }
done
# The feature library is shared below its "shared body" marker (the namespace header above it differs per kit):
# that part must be byte-identical, LF line endings only.
F3="$OC3/upload/system/library/announcementbarpro/features.php"
F4="$OC4/system/library/features.php"
MARK='---- shared body:'
for f in "$F3" "$F4"; do
	[ "$(grep -c -- "$MARK" "$f")" = 1 ] || { echo "Shared-body marker missing or repeated in $f" >&2; exit 1; }
	[ "$(tr -cd '\r' < "$f" | wc -c)" -eq 0 ] || { echo "CR (Windows) line endings in $f — use LF" >&2; exit 1; }
done
cmp -s <(sed "1,/$MARK/d" "$F3") <(sed "1,/$MARK/d" "$F4") \
	|| { echo "features.php differs between kits below the shared-body marker:" >&2; diff <(sed "1,/$MARK/d" "$F3") <(sed "1,/$MARK/d" "$F4") | head -20 >&2; exit 1; }
# 2.4.2: every edition next to this one (OpenCart 4 / 2.x / 1.5, WordPress) against the OpenCart 3 sources.
./check-shared.sh
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
