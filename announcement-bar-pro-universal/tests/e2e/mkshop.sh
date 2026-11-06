#!/bin/bash
# mkshop.sh <source upload dir> <port> <db> <shop dir>  — fresh OpenCart shop served by php -S
SRC=$1; PORT=$2; DB=$3; W=$4; S=${ABP_WORK:?set ABP_WORK to a work folder (OpenCart sources + test shops)}
pkill -f "php -S localhost:$PORT " 2>/dev/null; sleep 0.5
mysql -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4; GRANT ALL ON $DB.* TO 'oc'@'localhost';"
[ -d "$W" ] && find "$W" -delete
cp -r "$SRC" "$W"; cd "$W"; cp config-dist.php config.php; cp admin/config-dist.php admin/config.php; chmod -R a+w .
VER=$(grep -oP "define\('VERSION', '\K[^']+" index.php)
if [[ $VER == 4* ]]; then
  (cd install && php cli_install.php install --username admin --email admin@example.com --password admin123 --http_server http://localhost:$PORT/ --db_driver mysqli --db_hostname localhost --db_username oc --db_password ocpass --db_database $DB --db_port 3306 --db_prefix oc_ 2>&1 | tail -1)
else
  (cd install && php cli_install.php install --db_hostname localhost --db_username oc --db_password ocpass --db_database $DB --db_driver mysqli --db_port 3306 --username admin --password admin123 --email admin@example.com --http_server http://localhost:$PORT/ 2>&1 | tail -1)
fi
if [ -s config.php ] && grep -q DB_DATABASE config.php; then [ -d "$W-install-disabled" ] && find "$W-install-disabled" -delete; mv install "$W-install-disabled"; else echo "INSTALL FAILED"; fi
(cd "$W" && nohup php -S localhost:$PORT -t "$W" > "$W.log" 2>&1 < /dev/null &); sleep 1
echo "OpenCart $VER on $PORT: $(curl -s -o /dev/null -w '%{http_code}' http://localhost:$PORT/) admin: $(curl -s -o /dev/null -w '%{http_code}' http://localhost:$PORT/admin/)"
