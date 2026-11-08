#!/bin/bash
# oc2.sh — local OpenCart 2.x test shops for Announcement Bar Pro (OC 2.0–2.3 edition).
# Own MariaDB on :3311 (datadir db-oc2) — never touches the shared :3307 instance. Ports 8120–8124 only.
#   oc2.sh db-start | db-stop
#   oc2.sh mkshop <version> <port> <php56|php74>     fresh shop (DB abp_oc2x_<ver>, local test admin admin/admin123)
#   oc2.sh serve <version> <port> <php56|php74>       PHP built-in server for that shop
#   oc2.sh smtp <port>                                fake SMTP (mails → mail-oc2/*.eml)
#   oc2.sh stop <port>                                stops whatever listens on that port (own ports only)
E=/c/Users/Admin/abp-testenv
MB=$E/src/mariadb-11.4.4-winx64/bin
DATA='C:\Users\Admin\abp-testenv\db-oc2'
M="$MB/mariadb.exe -h127.0.0.1 -P3311 -uroot"
phpbin() { [ "$1" = php56 ] && echo /c/Users/Admin/tools/php56/php.exe || echo /c/Users/Admin/tools/php74/php.exe; }
phpext() { [ "$1" = php56 ] && echo 'C:\Users\Admin\tools\php56\ext' || echo 'C:\Users\Admin\tools\php74\ext'; }
phpargs() {
	local X; X=$(phpext "$1")
	echo "-d extension_dir=$X -d extension=php_mysqli.dll -d extension=php_gd2.dll -d extension=php_mbstring.dll -d extension=php_openssl.dll -d extension=php_curl.dll -d mysqli.default_port=3311 -d memory_limit=512M -d upload_max_filesize=20M -d post_max_size=24M -d display_errors=0 -d log_errors=1 -d date.timezone=Europe/Riga -d opcache.enable=0"
}
kill_port() { for pid in $(netstat -ano | grep -E "(127\.0\.0\.1|0\.0\.0\.0|\[::1\]|\[::\]):$1 .*LISTENING" | awk '{print $NF}' | sort -u); do taskkill //PID $pid //F >/dev/null 2>&1; done; }
case "$1" in
db-start)
	if [ ! -d "$E/db-oc2/mysql" ]; then "$MB/mariadb-install-db.exe" --datadir="$DATA" --port=3311 >"$E/db-oc2-install.log" 2>&1 || { echo "install-db failed"; exit 1; }; fi
	if ! netstat -ano | grep -q "127.0.0.1:3311 .*LISTENING"; then
		("$MB/mariadbd.exe" --datadir="$DATA" --port=3311 --bind-address=127.0.0.1 --sql-mode=NO_ENGINE_SUBSTITUTION --character-set-server=utf8mb4 > "$E/db-oc2-run.log" 2>&1 &)
		for i in 1 2 3 4 5 6 7 8 9 10; do sleep 1; netstat -ano | grep -q "127.0.0.1:3311 .*LISTENING" && break; done
	fi
	netstat -ano | grep -q "127.0.0.1:3311 .*LISTENING" && echo "db :3311 up" || echo "db :3311 FAILED";;
db-stop)
	"$MB/mariadb-admin.exe" -h127.0.0.1 -P3311 -uroot shutdown 2>/dev/null || kill_port 3311; echo "db :3311 stopped";;
mkshop)
	V=$2; PORT=$3; P=$(phpbin "$4"); A=$(phpargs "$4")
	DB=abp_oc2x_${V//./}; W=$E/shops/oc2x-$V
	SRC=$(find $E/src/oc-$V -maxdepth 3 -type d -name upload | head -1)
	kill_port $PORT
	$M -e "CREATE USER IF NOT EXISTS 'oc'@'localhost' IDENTIFIED BY 'ocpass'; CREATE USER IF NOT EXISTS 'oc'@'127.0.0.1' IDENTIFIED BY 'ocpass'; DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8; GRANT ALL ON $DB.* TO 'oc'@'localhost'; GRANT ALL ON $DB.* TO 'oc'@'127.0.0.1';"
	[ -d "$W" ] && python -c "import shutil,sys; shutil.rmtree(sys.argv[1])" "$(cygpath -w "$W")"
	cp -r "$SRC" "$W"; cd "$W"
	[ -f config-dist.php ] && cp config-dist.php config.php
	[ -f admin/config-dist.php ] && cp admin/config-dist.php admin/config.php
	touch config.php admin/config.php
	(cd install && $P $A cli_install.php install --db_hostname 127.0.0.1 --db_username oc --db_password ocpass --db_database $DB --db_driver mysqli --db_port 3311 --db_prefix oc_ --username admin --password admin123 --email admin@example.com --http_server http://localhost:$PORT/ 2>&1 | tail -3)
	if grep -q DB_DATABASE config.php; then
		[ -d "$W-install-disabled" ] && python -c "import shutil,sys; shutil.rmtree(sys.argv[1])" "$(cygpath -w "$W-install-disabled")"
		mv install "$W-install-disabled"
		$M $DB -e "UPDATE oc_setting SET value='0' WHERE \`key\`='config_maintenance'" # 2.0.3.1 installs in maintenance mode
		echo "OpenCart $V ready (port $PORT, db $DB)"
	else echo "INSTALL FAILED"; fi;;
serve)
	V=$2; PORT=$3; P=$(phpbin "$4"); A=$(phpargs "$4")
	kill_port $PORT
	(cd $E/shops/oc2x-$V && "$P" $A -S localhost:$PORT -t . > $E/shops/oc2x-php$PORT.log 2>&1 &)
	sleep 1; netstat -ano | grep -q ":$PORT .*LISTENING" && echo "serving $V on :$PORT ($4)" || echo "serve FAILED";;
smtp)
	mkdir -p $E/mail-oc2; kill_port $2
	("/c/Users/Admin/tools/node/node.exe" $E/scripts/smtp-sink.js $2 "$E/mail-oc2" > $E/mail-oc2/smtp.log 2>&1 &)
	sleep 1; echo "smtp :$2";;
stop) kill_port $2; echo "stopped :$2";;
*) sed -n 2,9p "$0";;
esac
