#!/bin/bash
# run-upgrade.sh — upgrade path: the single-plugin build 1.0.0 (licence key inside, Pro data) is replaced by the free
# plugin of the same folder, then the Premium add-on is activated. Same tables and options: everything must keep working.
# Uses the local test shop of abp-testenv (wpenv.sh) and the single build from the git history (commit c3274f0).
E=/c/Users/Admin/abp-testenv
HERE=$(cd "$(dirname "$0")" && pwd)
REPO=$(cd "$HERE/../../.." && pwd)
W="bash $E/scripts/wpenv.sh"
DB="$E/src/mariadb-11.4.4-winx64/bin/mariadb.exe"
PLUG=$E/shops/wp/wp-content/plugins
TMP=$E/tmp-upgrade
$W start >/dev/null 2>&1
$W wp plugin deactivate announcement-bar-pro-premium announcement-bar-pro >/dev/null 2>&1
"$DB" -h127.0.0.1 -P3307 -uroot abp_wp -e "DROP TABLE IF EXISTS wp_abpro_messages, wp_abpro_message_texts, wp_abpro_subscribers, wp_abpro_rate; DELETE FROM wp_options WHERE option_name LIKE 'abpro\_%' OR option_name LIKE '\_transient\_abpro%' OR option_name LIKE '\_transient\_timeout\_abpro%';" 2>/dev/null
$W wp theme activate storefront >/dev/null 2>&1
# The single-plugin build 1.0.0 into plugins/announcement-bar-pro.
python -I -c "import shutil,sys; shutil.rmtree(sys.argv[1], ignore_errors=True)" "$TMP"
mkdir -p "$TMP"
git -C "$REPO" show c3274f0:announcement-bar-pro-wordpress/dist/announcement-bar-pro-1.0.0.zip > "$TMP/single.zip"
python -I -c "import zipfile,sys,shutil; shutil.rmtree(sys.argv[2], ignore_errors=True); zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[3])" "$(cygpath -w "$TMP/single.zip")" "$(cygpath -w "$PLUG/announcement-bar-pro")" "$(cygpath -w "$PLUG")"
: > $E/shops/wp/wp-content/debug.log
export WPBASE=http://localhost:8150 WPDIR=$E/shops/wp
export WPCLI="$(cygpath -w /c/Users/Admin/tools/php/php.exe) -c $(cygpath -w $E/scripts/wp-php.ini) $(cygpath -w $E/dl/wp-cli.phar) --path=$(cygpath -w $E/shops/wp)"
export MYSQL="$DB" MYSQLARGS="-h127.0.0.1 -P3307 -uroot abp_wp" SYNC="$W sync" SEED="$(cygpath -w "$HERE/upgrade-seed.php")"
/c/Users/Admin/tools/node/node.exe "$HERE/wp-upgrade-e2e.js"
