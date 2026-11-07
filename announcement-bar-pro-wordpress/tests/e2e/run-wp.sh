#!/bin/bash
# run-wp.sh — E2E of the WordPress edition on the local test shop (abp-testenv/scripts/wpenv.sh).
#   MK=1: fresh WordPress + WooCommerce + Storefront first (slow). Otherwise: plugin data reset, plugin re-synced.
# The admin login of the local test shop is the one wpenv.sh creates (local test values only).
E=/c/Users/Admin/abp-testenv
HERE=$(cd "$(dirname "$0")" && pwd)
W="bash $E/scripts/wpenv.sh"
DB="$E/src/mariadb-11.4.4-winx64/bin/mariadb.exe"
[ "$MK" = 1 ] && $W mk >/dev/null 2>&1
$W start >/dev/null 2>&1
$W wp plugin deactivate announcement-bar-pro >/dev/null 2>&1
"$DB" -h127.0.0.1 -P3307 -uroot abp_wp -e "DROP TABLE IF EXISTS wp_abpro_messages, wp_abpro_message_texts, wp_abpro_subscribers, wp_abpro_rate; DELETE FROM wp_options WHERE option_name LIKE 'abpro\_%' OR option_name LIKE '\_transient\_abpro%' OR option_name LIKE '\_transient\_timeout\_abpro%';" 2>/dev/null
$W wp eval-file $E/scripts/wp/seed.php >/dev/null 2>&1
$W wp theme activate storefront >/dev/null 2>&1
$W wp eval 'WC()->session = null; global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_sessions");' >/dev/null 2>&1
$W sync
$W wp plugin activate announcement-bar-pro >/dev/null 2>&1
mkdir -p $E/shots/wp
IMG=$E/shots/wp/test-bg.png
/c/Users/Admin/tools/php/php.exe -c $E/scripts/wp-php.ini -r '$i=imagecreatetruecolor(1200,200);for($x=0;$x<1200;$x+=40){imagefilledrectangle($i,$x,0,$x+19,200,imagecolorallocate($i,200,120+$x%100,90));}imagepng($i,$argv[1]);' "$IMG"
: > $E/shops/wp/wp-content/debug.log
export WPBASE=http://localhost:8150 WPDIR=$E/shops/wp WPUSER=admin WPPASS=abp-test-admin-1 SHOTS=$E/shots/wp MAILDIR=$E/mail TESTIMG=$(cygpath -w "$IMG")
export WPCLI="$(cygpath -w /c/Users/Admin/tools/php/php.exe) -c $(cygpath -w $E/scripts/wp-php.ini) $(cygpath -w $E/dl/wp-cli.phar) --path=$(cygpath -w $E/shops/wp)"
export MYSQL="$DB" MYSQLARGS="-h127.0.0.1 -P3307 -uroot abp_wp"
NODE_PATH=/c/Users/Admin/AppData/Local/Temp/claude/219ab07c57d28e8b/12a18630-f9c6-4111-9dc7-ccd4f10ad3d7/scratchpad/nm /c/Users/Admin/tools/node/node.exe "$HERE/wp-e2e.js"
