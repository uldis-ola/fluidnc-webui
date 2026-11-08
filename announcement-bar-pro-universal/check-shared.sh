#!/usr/bin/env bash
# check-shared.sh [--sync] — the files every Announcement Bar Pro edition shares must be byte-identical.
# Source of truth: the OpenCart 3 kit (../announcement-bar-pro). Compared with every edition folder that is present:
#   OpenCart 4 (-oc4), OpenCart 2.x (-oc2), OpenCart 1.5 (-oc15), WordPress (-wordpress, storefront JS / CSS only).
#   - storefront: announcement_bar_pro.js, announcement_bar_pro_pre.js, announcement_bar_pro.css
#   - admin:      abp_admin.js, abp_admin.css                      (OpenCart editions)
#   - library:    features.php, sanitizer.php                      (OC2 / OC15: whole file; OC4: features.php below the
#                 "---- shared body:" marker — its namespace header differs; OC4 sanitizer.php is its own port)
# The embed (../announcement-bar-pro-embed) reads the storefront files straight from the OpenCart 3 kit at build time.
# --sync copies the OpenCart 3 versions over the other copies (OC4 features.php: only the shared body), then checks.
# Exit code 0 = all identical. Called by build.sh.
set -euo pipefail
cd "$(dirname "$0")/.."
OC3=announcement-bar-pro/upload
SYNC=0; [ "${1:-}" = --sync ] && SYNC=1
MARK='---- shared body:'
pairs=()
add() { [ -d "$(dirname "$2")" ] && pairs+=("$1|$2") || true; }
for f in announcement_bar_pro.js announcement_bar_pro_pre.js announcement_bar_pro.css; do
	S=$OC3/catalog/view/javascript/announcement_bar_pro/$f
	add "$S" announcement-bar-pro-oc4/src/catalog/view/javascript/$f
	add "$S" announcement-bar-pro-oc2/upload/catalog/view/javascript/announcement_bar_pro/$f
	add "$S" announcement-bar-pro-oc15/upload/catalog/view/javascript/announcement_bar_pro/$f
	add "$S" announcement-bar-pro-wordpress/announcement-bar-pro/assets/$( [ "${f##*.}" = css ] && echo css || echo js )/${f//_/-}
done
for f in abp_admin.js abp_admin.css; do
	S=$OC3/admin/view/javascript/announcement_bar_pro/$f
	add "$S" announcement-bar-pro-oc4/src/admin/view/javascript/$f
	add "$S" announcement-bar-pro-oc2/upload/admin/view/javascript/announcement_bar_pro/$f
	add "$S" announcement-bar-pro-oc15/upload/admin/view/javascript/announcement_bar_pro/$f
done
for f in features.php sanitizer.php; do
	S=$OC3/system/library/announcementbarpro/$f
	add "$S" announcement-bar-pro-oc2/upload/system/library/announcementbarpro/$f
	add "$S" announcement-bar-pro-oc15/upload/system/library/announcementbarpro/$f
done
bad=0; n=0
for p in "${pairs[@]}"; do
	s=${p%%|*}; d=${p#*|}
	[ $SYNC = 1 ] && ! cmp -s "$s" "$d" && { cp "$s" "$d"; echo "synced $d"; }
	n=$((n + 1)); cmp -s "$s" "$d" || { echo "DIFFERS: $d (source $s)" >&2; bad=1; }
done
# OpenCart 4 feature library: identical below the shared-body marker, LF only.
F3=$OC3/system/library/announcementbarpro/features.php
F4=announcement-bar-pro-oc4/src/system/library/features.php
if [ -f "$F4" ]; then
	for f in "$F3" "$F4"; do
		[ "$(grep -c -- "$MARK" "$f")" = 1 ] || { echo "Shared-body marker missing or repeated in $f" >&2; exit 1; }
		[ "$(tr -cd '\r' < "$f" | wc -c)" -eq 0 ] || { echo "CR (Windows) line endings in $f — use LF" >&2; exit 1; }
	done
	if [ $SYNC = 1 ] && ! cmp -s <(sed "1,/$MARK/d" "$F3") <(sed "1,/$MARK/d" "$F4"); then
		{ sed -n "1,/$MARK/p" "$F4"; sed "1,/$MARK/d" "$F3"; } > "$F4.tmp" && mv "$F4.tmp" "$F4"; echo "synced $F4 (shared body)"
	fi
	n=$((n + 1)); cmp -s <(sed "1,/$MARK/d" "$F3") <(sed "1,/$MARK/d" "$F4") || { echo "DIFFERS: $F4 below the shared-body marker" >&2; bad=1; }
fi
[ $bad = 0 ] && echo "shared files: $n copies identical to the OpenCart 3 kit" || exit 1
