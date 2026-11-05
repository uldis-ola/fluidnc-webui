#!/usr/bin/env bash
# Builds dist/announcement_bar_pro_vX.Y.Z.ocmod.zip (upload/ + install.xml in zip root)
set -euo pipefail
cd "$(dirname "$0")"
VERSION=$(grep -oP '<version>\K[^<]+' install.xml)
OUT="dist/announcement_bar_pro_v${VERSION}.ocmod.zip"
mkdir -p dist && rm -f "$OUT"
zip -r -X "$OUT" upload install.xml -x '*.DS_Store' '*/.git*' >/dev/null
echo "$OUT ($(du -h "$OUT" | cut -f1))"
