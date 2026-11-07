#!/bin/bash
# build.sh — dist/announcement-bar-pro-<version>.zip (plugin folder only; tests, ruleset and docs stay out).
set -e
cd "$(dirname "$0")"
V=$(grep -m1 "Version:" announcement-bar-pro/announcement-bar-pro.php | awk '{print $NF}')
mkdir -p dist
python -I - "$V" <<'PY'
import os, sys, zipfile
v = sys.argv[1]
out = os.path.join('dist', 'announcement-bar-pro-%s.zip' % v)
skip = ('.map', '.DS_Store')
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk('announcement-bar-pro'):
        dirs.sort()
        for f in sorted(files):
            if f.endswith(skip):
                continue
            p = os.path.join(root, f)
            z.write(p, p.replace(os.sep, '/'))
print(out, os.path.getsize(out), 'bytes')
PY
