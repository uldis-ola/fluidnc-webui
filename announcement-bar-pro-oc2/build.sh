#!/usr/bin/env bash
# Builds the OpenCart 2.x packages (see the end of this file for why there are two):
#   dist/announcement_bar_pro_oc2.0-2.2_v<version>.ocmod.zip   OpenCart 2.0.0.0 – 2.2.0.0
#   dist/announcement_bar_pro_oc2.3_v<version>.ocmod.zip       OpenCart 2.3.0.0 – 2.3.0.2
#   each with:
#     upload/ + install.xml           what OpenCart's Extension Installer uses (2.x copies upload/ over FTP)
#     announcement_bar_pro.ocmod.xml   the same install.xml, ready for the installer when the files were copied by hand
#     README.md, INSTALL.md, INSTALL.lv.md, CHANGELOG.md, LICENSE.txt
# Checks first (the build stops on any problem):
#   - one version everywhere (install.xml, @version headers, VERSION constants, JS/CSS headers);
#   - shared files byte-identical with the OpenCart 3 kit (storefront + admin scripts / styles, features.php, sanitizer.php);
#   - the .tpl templates are up to date with the OpenCart 3 Twig templates (tools/twig2tpl.py --check);
#   - LF line endings in PHP; php -l with every PHP binary in $PHP_BINS (default: php56 + php74 in ~/tools if present).
set -euo pipefail
cd "$(dirname "$0")"
OC3=../announcement-bar-pro/upload
V=$(grep -oP '<version>\K[^<]+' install.xml)
PY=$(python3 -c 'import sys' >/dev/null 2>&1 && echo python3 || echo python)

BAD=$(grep -rHoE "@version [0-9.]+|const +VERSION += '[0-9.]+'|Announcement Bar Pro v[0-9.]+[0-9]" upload | grep -vE "(@version |VERSION += '|Announcement Bar Pro v)${V//./\\.}'?$" || true)
[ -z "$BAD" ] || { echo "Version mismatch (expected $V):" >&2; echo "$BAD" >&2; exit 1; }

if [ -d "$OC3" ]; then
	for f in catalog/view/javascript/announcement_bar_pro/announcement_bar_pro.js catalog/view/javascript/announcement_bar_pro/announcement_bar_pro_pre.js \
		catalog/view/javascript/announcement_bar_pro/announcement_bar_pro.css admin/view/javascript/announcement_bar_pro/abp_admin.js \
		admin/view/javascript/announcement_bar_pro/abp_admin.css system/library/announcementbarpro/features.php system/library/announcementbarpro/sanitizer.php; do
		cmp -s "$OC3/$f" "upload/$f" || { echo "Shared file differs from the OpenCart 3 kit: $f (copy it from $OC3/$f)" >&2; exit 1; }
	done
	for t in admin/view/template/extension/module/announcement_bar_pro admin/view/template/extension/module/announcement_bar_pro_form \
		catalog/view/theme/default/template/extension/module/announcement_bar_pro catalog/view/theme/default/template/extension/module/announcement_bar_pro_page; do
		$PY tools/twig2tpl.py --check "$OC3/$t.twig" "upload/$t.tpl" \
			|| { echo "Template out of date: upload/$t.tpl — run: $PY tools/twig2tpl.py $OC3/$t.twig upload/$t.tpl" >&2; exit 1; }
	done
else
	echo "note: ../announcement-bar-pro not found — shared-file and template checks skipped" >&2
fi

CR=$($PY tools/crlf_check.py || true)
[ -z "$CR" ] || { echo "CR (Windows) line endings — use LF:" >&2; echo "$CR" >&2; exit 1; }

PHP_BINS=${PHP_BINS:-"$HOME/tools/php56/php.exe $HOME/tools/php74/php.exe"}
for P in $PHP_BINS; do
	[ -x "$P" ] || continue
	while IFS= read -r f; do
		"$P" -l "$f" >/dev/null 2>&1 || { echo "php -l failed ($("$P" -r 'echo PHP_VERSION;')): $f" >&2; "$P" -l "$f" >&2; exit 1; }
	done < <(find upload -name '*.php' -o -name '*.tpl')
	echo "php -l OK: PHP $("$P" -r 'echo PHP_VERSION;')"
done

# Two packages (OpenCart 2.3 changed the extension layout):
#  - 2.0–2.2: Extensions › Modules runs admin/controller/module/announcement_bar_pro.php (entry file); it must not
#    contain admin/controller/extension/module/ (OpenCart 2.0.x would then not find its own extension/module page);
#    install.xml carries the 2.0/2.1 template-loader hook (2.0/2.1 have no view events).
#  - 2.3: WITHOUT that entry file. OpenCart 2.3's compatibility event (admin event/compatibility/controller) rewrites
#    every extension/module/announcement_bar_pro/<method> call to module/announcement_bar_pro (index) as soon as
#    admin/controller/module/announcement_bar_pro.php exists — install, save, AJAX would all open the list page.
#    Its install.xml has no file operation (2.3 uses events).
STAGE=$(mktemp -d); trap 'rm -rf "$STAGE"' EXIT
mkdir -p dist
pack() { # pack <name> <stage dir>
	local OUT="dist/$1"
	rm -f "$OUT"
	(cd "$2" && $PY - <<'PYZIP'
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
	mv "$2/_pkg.zip" "$OUT"
	echo "$OUT ($(du -h "$OUT" | cut -f1))"
}
# 2.0–2.2
A="$STAGE/oc20-22"; mkdir -p "$A"
cp -r upload install.xml README.md INSTALL.md INSTALL.lv.md CHANGELOG.md LICENSE.txt "$A/"
cp install.xml "$A/announcement_bar_pro.ocmod.xml"
# OpenCart 2.0.x resolves admin routes folder-first: an admin/controller/extension/module/ folder would hide its own
# Extensions › Modules page (extension/module.php). The 2.3 entry file is left out here (the controller is in system/library).
rm -rf "$A/upload/admin/controller/extension"
[ ! -e "$A/upload/admin/controller/extension" ] || { echo "2.0-2.2 package must not contain admin/controller/extension" >&2; exit 1; }
pack "announcement_bar_pro_oc2.0-2.2_v${V}.ocmod.zip" "$A"
# 2.3
B="$STAGE/oc23"; mkdir -p "$B"
cp -r upload README.md INSTALL.md INSTALL.lv.md CHANGELOG.md LICENSE.txt "$B/"
rm -rf "$B/upload/admin/controller/module"
for d in english latvian russian; do rm -rf "$B/upload/admin/language/$d" "$B/upload/catalog/language/$d"; done
for d in en-gb lv-lv ru-ru; do rm -rf "$B/upload/admin/language/$d/module"; done
$PY - install.xml "$B/install.xml" <<'PYXML'
import re, sys
s = open(sys.argv[1], encoding='utf-8').read()
s = re.sub(r'\s*<!--.*?-->', '', s, count=1, flags=re.S)
s = re.sub(r'\s*<file path=.*?</file>', '', s, flags=re.S)
s = s.replace('</modification>', '  <!-- OpenCart 2.3: no core file is changed. The bar is added by view events that the module registers\n       when it is installed (Extensions > Extensions > Modules > Install). -->\n</modification>')
open(sys.argv[2], 'w', encoding='utf-8', newline='\n').write(s)
PYXML
cp "$B/install.xml" "$B/announcement_bar_pro.ocmod.xml"
[ ! -e "$B/upload/admin/controller/module" ] || { echo "2.3 package must not contain admin/controller/module" >&2; exit 1; }
pack "announcement_bar_pro_oc2.3_v${V}.ocmod.zip" "$B"
