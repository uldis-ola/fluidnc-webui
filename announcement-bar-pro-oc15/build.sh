#!/usr/bin/env bash
# Builds dist/announcement_bar_pro_oc15_v<version>.zip for OpenCart 1.5.x (upload/ + docs; installed by FTP + vQmod).
# Checks first: one version everywhere, shared files byte-identical with the OpenCart 3 kit, generated templates up to date,
# PHP lint (PHP 5.6 / 7.4 when found) and the unit tests.
set -euo pipefail
cd "$(dirname "$0")"
PY=$(python3 -c 'import sys' >/dev/null 2>&1 && echo python3 || echo python)
VERSION=$(grep -oP "const VERSION\s+= '\K[^']+" upload/admin/controller/module/announcement_bar_pro.php)
OC3=../announcement-bar-pro/upload

# 1. Version: PHP headers, constants, JS / CSS headers, vQmod file.
BAD=$(grep -rHoE "@version [0-9.]+|const +VERSION += '[0-9.]+'|Announcement Bar Pro v[0-9.]+[0-9]|<version>[0-9.]+</version>" upload \
	| grep -vE "(@version |VERSION += '|Announcement Bar Pro v|<version>)${VERSION//./\\.}('|</version>)?$" || true)
[ -z "$BAD" ] || { echo "Version mismatch (expected $VERSION):" >&2; echo "$BAD" >&2; exit 1; }

# 2. Shared files: byte-identical with the OpenCart 3 kit (when it is next to this one).
if [ -d "$OC3" ]; then
	for f in announcement_bar_pro.js announcement_bar_pro_pre.js announcement_bar_pro.css; do
		cmp -s "$OC3/catalog/view/javascript/announcement_bar_pro/$f" "upload/catalog/view/javascript/announcement_bar_pro/$f" || { echo "Shared storefront file differs: $f" >&2; exit 1; }
	done
	for f in abp_admin.js abp_admin.css; do
		cmp -s "$OC3/admin/view/javascript/announcement_bar_pro/$f" "upload/admin/view/javascript/announcement_bar_pro/$f" || { echo "Shared admin file differs: $f" >&2; exit 1; }
	done
	for f in features.php sanitizer.php; do
		cmp -s "$OC3/system/library/announcementbarpro/$f" "upload/system/library/announcementbarpro/$f" || { echo "Shared library differs: $f" >&2; exit 1; }
	done
	# 3. Templates generated from the OpenCart 3 Twig templates must be current.
	TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
	T=$OC3/admin/view/template/extension/module; C=$OC3/catalog/view/theme/default/template/extension/module
	"$PY" tools/twig2tpl.py "$T/announcement_bar_pro.twig" "$TMP/a.tpl" --admin15 >/dev/null
	"$PY" tools/twig2tpl.py "$T/announcement_bar_pro_form.twig" "$TMP/f.tpl" --admin15 >/dev/null
	"$PY" tools/twig2tpl.py "$C/announcement_bar_pro_page.twig" "$TMP/p.tpl" >/dev/null
	cmp -s "$TMP/a.tpl" upload/admin/view/template/module/announcement_bar_pro.tpl || { echo "Admin list template is not current: run tools/twig2tpl.py" >&2; exit 1; }
	cmp -s "$TMP/f.tpl" upload/admin/view/template/module/announcement_bar_pro_form.tpl || { echo "Admin form template is not current: run tools/twig2tpl.py" >&2; exit 1; }
	cmp -s "$TMP/p.tpl" upload/catalog/view/theme/default/template/module/announcement_bar_pro_page.tpl || { echo "Page template is not current" >&2; exit 1; }
fi

# 4. LF line endings in PHP / templates / XML.
CR=$(grep -rlI "$(printf '\r')" upload --include='*.php' --include='*.tpl' --include='*.xml' || true)
[ -z "$CR" ] || { echo "CR line endings in: $CR" >&2; exit 1; }

# 5. PHP lint + unit tests on every PHP found (5.6 is the minimum this edition supports).
# PHP_BINS="php ..." overrides the list; PHP_INI_<n> (same order) can give an ini with mbstring / mysqli for the tests.
PHP_BINS=${PHP_BINS:-"/c/Users/Admin/tools/php56/php.exe /c/Users/Admin/tools/php74/php.exe"}
for PHP in $PHP_BINS; do
	command -v "$PHP" >/dev/null 2>&1 || continue
	PV=$("$PHP" -n -r 'echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION;')
	INI=/c/Users/Admin/abp-testenv/scripts/oc15/php$PV.ini
	ARGS=(-n); [ -f "$INI" ] && ARGS=(-n -c "$INI" -d display_errors=1 -d log_errors=0)
	while IFS= read -r f; do "$PHP" -n -l "$f" >/dev/null || { echo "PHP lint failed ($PHP): $f" >&2; exit 1; }; done < <(find upload -name '*.php' -o -name '*.tpl')
	RES=$("$PHP" "${ARGS[@]}" tests/unit/schema15_test.php | tail -1)
	echo "$RES" | grep -q ", 0 failed" || { echo "Unit tests failed ($PHP): $RES" >&2; exit 1; }
	echo "PHP $("$PHP" -n -r 'echo PHP_VERSION;'): lint OK, unit tests $RES"
done

# 6. Zip: upload/ + docs at the root.
OUT="dist/announcement_bar_pro_oc15_v${VERSION}.zip"
mkdir -p dist && rm -f "$OUT"
"$PY" - "$OUT" <<'PYZIP'
import os, sys, zipfile
out = sys.argv[1]
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for top in ('upload',):
        for root, dirs, files in os.walk(top):
            dirs.sort()
            for f in sorted(files):
                if f in ('.DS_Store',):
                    continue
                p = os.path.join(root, f)
                z.write(p, p.replace(os.sep, '/'))
    for f in ('README.md', 'INSTALL.md', 'INSTALL.lv.md', 'CHANGELOG.md', 'LICENSE.txt'):
        z.write(f, f)
PYZIP
echo "$OUT ($(du -h "$OUT" | cut -f1))"
