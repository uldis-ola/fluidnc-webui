#!/usr/bin/env bash
# Builds dist/v<version>/announcement_bar_pro.ocmod.zip for OpenCart 4.
# OpenCart 4 takes the extension code from the zip FILE NAME, so it must stay "announcement_bar_pro.ocmod.zip".
set -euo pipefail
cd "$(dirname "$0")"
VERSION=$(python3 -c "import json;print(json.load(open('src/install.json'))['version'])")
OUT="dist/v${VERSION}/announcement_bar_pro.ocmod.zip"
mkdir -p "dist/v${VERSION}" && rm -f "$OUT"
(cd src && zip -r -X "../$OUT" install.json admin catalog system -x '*.DS_Store' >/dev/null)
echo "$OUT ($(du -h "$OUT" | cut -f1))"
