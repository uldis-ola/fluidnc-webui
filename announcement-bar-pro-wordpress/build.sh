#!/bin/bash
# build.sh — two zips, plugin folders only (tests, rulesets and docs stay out):
#   dist/announcement-bar-pro-<version>.zip          free plugin (wordpress.org)
#   dist/announcement-bar-pro-premium-<version>.zip  Premium add-on (sold by 3DP SIA, not for wordpress.org)
# Checks first: the shared storefront files are byte-identical to the OpenCart kits, and the unit tests pass (they
# include the check that the free plugin holds no licence or premium code).
set -e
cd "$(dirname "$0")"
PHP=${PHP:-php}
command -v "$PHP" >/dev/null 2>&1 || PHP=/c/Users/Admin/tools/php/php
OC=../announcement-bar-pro/upload/catalog/view/javascript/announcement_bar_pro
if [ -d "$OC" ]; then
	for pair in "js/announcement-bar-pro.js:announcement_bar_pro.js" "js/announcement-bar-pro-pre.js:announcement_bar_pro_pre.js" "css/announcement-bar-pro.css:announcement_bar_pro.css"; do
		cmp -s "announcement-bar-pro/assets/${pair%%:*}" "$OC/${pair##*:}" || { echo "Shared storefront file differs from the OpenCart kits: ${pair%%:*}" >&2; exit 1; }
	done
fi
"$PHP" tests/unit/run.php >/dev/null || { echo "Unit tests failed: php tests/unit/run.php" >&2; exit 1; }
mkdir -p dist
for SLUG in announcement-bar-pro announcement-bar-pro-premium; do
	V=$(grep -m1 "Version:" "$SLUG/$SLUG.php" | awk '{print $NF}')
	python -I - "$SLUG" "$V" <<'PY'
import os, sys, zipfile
slug, v = sys.argv[1], sys.argv[2]
out = os.path.join('dist', '%s-%s.zip' % (slug, v))
skip = ('.map', '.DS_Store')
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(slug):
        dirs.sort()
        for f in sorted(files):
            if f.endswith(skip):
                continue
            p = os.path.join(root, f)
            z.write(p, p.replace(os.sep, '/'))
print(out, os.path.getsize(out), 'bytes')
PY
done
