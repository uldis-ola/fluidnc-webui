#!/usr/bin/env bash
# Builds the universal JS embed of Announcement Bar Pro:
#   dist/abp-embed-<version>.zip  — runtime (minified + source), Configurator (one HTML file), docs EN + LV
#   configurator/index.html       — the Configurator with the runtime inlined (works from file://)
# The storefront script and styles are NOT copied into this folder: they are read from the OpenCart 3 kit, which is
# the single source shared by every edition, and must be byte-identical in the OpenCart 4 and WordPress kits.
# Needs: bash, python (or python3), gzip, esbuild (ESBUILD=/path/to/esbuild, or one found below).
set -euo pipefail
cd "$(dirname "$0")"
PY=$(python3 -c 'import sys' >/dev/null 2>&1 && echo python3 || echo python)
SHARED=../announcement-bar-pro/upload/catalog/view/javascript/announcement_bar_pro
OC4=../announcement-bar-pro-oc4/src/catalog/view/javascript
WP=../announcement-bar-pro-wordpress/announcement-bar-pro/assets
VERSION=$(grep -oE "VERSION = '[0-9.]+'" src/abp-embed.js | grep -oE '[0-9.]+')
grep -q "^## $VERSION" CHANGELOG.md || { echo "CHANGELOG.md has no entry for $VERSION" >&2; exit 1; }
grep -q "$VERSION" README.md || { echo "README.md does not mention $VERSION" >&2; exit 1; }

# 1. Shared storefront files: present, and identical in every kit that is next to this one.
for f in announcement_bar_pro.js announcement_bar_pro_pre.js announcement_bar_pro.css; do
	[ -f "$SHARED/$f" ] || { echo "Missing shared file $SHARED/$f" >&2; exit 1; }
	if [ -d "$OC4" ]; then cmp -s "$SHARED/$f" "$OC4/$f" || { echo "Shared file differs OC3 / OC4: $f" >&2; exit 1; }; fi
done
if [ -d "$WP" ]; then
	for pair in "announcement_bar_pro.js:js/announcement-bar-pro.js" "announcement_bar_pro_pre.js:js/announcement-bar-pro-pre.js" "announcement_bar_pro.css:css/announcement-bar-pro.css"; do
		cmp -s "$SHARED/${pair%%:*}" "$WP/${pair#*:}" || { echo "Shared file differs OpenCart / WordPress: ${pair%%:*}" >&2; exit 1; }
	done
fi
SHARED_V=$(grep -oE 'Announcement Bar Pro v[0-9.]+[0-9]' "$SHARED/announcement_bar_pro.js" | head -1)

# 2. esbuild (minifier only; nothing is bundled from npm).
ESB=${ESBUILD:-}
for c in "$ESB" ./node_modules/.bin/esbuild ../node_modules/.bin/esbuild /c/Users/Admin/3dp-ws/globe/node_modules/.bin/esbuild "$(command -v esbuild || true)"; do
	if [ -n "$c" ] && [ -x "$c" ]; then ESB=$c; break; fi
done
[ -n "$ESB" ] && [ -x "$ESB" ] || { echo "esbuild not found: set ESBUILD=/path/to/esbuild (npm i -D esbuild)" >&2; exit 1; }

# 3. Runtime: shared pre-script + main script, then the embed (core + renderer) in one closure.
OUT=dist/abp-embed
mkdir -p "$OUT"
HEAD="/*! Announcement Bar Pro Embed $VERSION | MIT | (c) 3DP SIA */"
{
	echo "$HEAD"
	echo "/* Support: extension@3dp.lv. Source: abp-embed.js (this file, not minified) + source/ in the package."
	echo "   Contains the shared storefront script and pre-script of $SHARED_V (byte-identical in every edition). */"
	cat "$SHARED/announcement_bar_pro_pre.js"; echo
	cat "$SHARED/announcement_bar_pro.js"; echo
	echo "(function () {"
	cat src/abp-embed-core.js; echo
	cat src/abp-embed.js
	echo "})();"
} > "$OUT/abp-embed.js"
{ echo "$HEAD"; cat "$SHARED/announcement_bar_pro.css"; } > "$OUT/abp-embed.css"
"$ESB" "$OUT/abp-embed.js" --minify --target=es2017 --legal-comments=inline --log-level=warning --outfile="$OUT/abp-embed.min.js"
"$ESB" "$OUT/abp-embed.css" --minify --legal-comments=inline --log-level=warning --outfile="$OUT/abp-embed.min.css"
for f in abp-embed.js abp-embed.min.js; do
	grep -qE '\beval\(|new Function\(' "$OUT/$f" && { echo "eval / new Function found in $f" >&2; exit 1; }
done
GZ_JS=$(gzip -9 -c "$OUT/abp-embed.min.js" | wc -c | tr -d ' ')
GZ_CSS=$(gzip -9 -c "$OUT/abp-embed.min.css" | wc -c | tr -d ' ')
[ "$GZ_JS" -le 20480 ] || { echo "Size budget: abp-embed.min.js is $GZ_JS bytes gzipped (max 20480)" >&2; exit 1; }
echo "runtime: abp-embed.min.js $GZ_JS B gzip, abp-embed.min.css $GZ_CSS B gzip, together $((GZ_JS + GZ_CSS)) B"

# 4. Configurator: template + core (validation) + runtime (live preview), one self-contained HTML file.
$PY - "$VERSION" <<'PYCFG'
import sys, json
v = sys.argv[1]
t = open('configurator/template.html', encoding='utf-8').read()
core = open('src/abp-embed-core.js', encoding='utf-8').read()
js = open('dist/abp-embed/abp-embed.min.js', encoding='utf-8').read()
css = open('dist/abp-embed/abp-embed.min.css', encoding='utf-8').read()
for name, body in (('core', core), ('runtime', js), ('css', css)):
    if '</script' in body.lower() or '<!--' in body:
        sys.exit('"</script" or "<!--" inside ' + name + ' (it is inlined into <script> elements)')
for k in ('/*@@CORE@@*/', '/*@@RUNTIME_JS@@*/', '/*@@RUNTIME_CSS@@*/', '@@VERSION@@'):
    if k not in t:
        sys.exit('placeholder missing in configurator/template.html: ' + k)
t = t.replace('/*@@CORE@@*/', core).replace('/*@@RUNTIME_JS@@*/', js).replace('/*@@RUNTIME_CSS@@*/', css).replace('@@VERSION@@', v)
open('configurator/index.html', 'w', encoding='utf-8', newline='\n').write(t)
PYCFG

# 5. Package.
ZIP="dist/abp-embed-$VERSION.zip"
rm -f "$ZIP"
$PY - "$ZIP" "$VERSION" <<'PYZIP'
import sys, zipfile, os
out, v = sys.argv[1], sys.argv[2]
root = 'announcement-bar-pro-embed-' + v + '/'
files = [
    ('dist/abp-embed/abp-embed.min.js', 'abp-embed/abp-embed.min.js'),
    ('dist/abp-embed/abp-embed.min.css', 'abp-embed/abp-embed.min.css'),
    ('dist/abp-embed/abp-embed.js', 'abp-embed/abp-embed.js'),
    ('dist/abp-embed/abp-embed.css', 'abp-embed/abp-embed.css'),
    ('src/abp-embed-core.js', 'source/abp-embed-core.js'),
    ('src/abp-embed.js', 'source/abp-embed.js'),
    ('configurator/index.html', 'configurator/index.html'),
    ('README.md', 'README.md'), ('CHANGELOG.md', 'CHANGELOG.md'), ('LICENSE.txt', 'LICENSE.txt'), ('LICENSING.md', 'LICENSING.md'),
]
for d in ('docs/en', 'docs/lv', 'examples'):
    for f in sorted(os.listdir(d)):
        files.append((d + '/' + f, d + '/' + f))
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for src, dst in files:
        z.write(src, root + dst)
print(out, os.path.getsize(out), 'bytes,', len(files), 'files')
PYZIP
