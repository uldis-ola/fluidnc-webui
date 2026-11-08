#!/bin/bash
# run15.sh <port> <oc version> <shop dir> <db> [php log] — fresh OpenCart 1.5 shop + vQmod (installer over HTTP), module files
# copied like an FTP upload, then the Playwright E2E (tests/e2e/abp15-e2e.js). Servers: serve15.sh start.
PORT=$1; V=$2; NAME=$3; DB=$4; PLOG=$5
E=/c/Users/Admin/abp-testenv; S=$E/scripts/oc15; K=/c/Users/Admin/fluidnc-webui/announcement-bar-pro-oc15
for pid in $(netstat -ano | grep -E "(127\.0\.0\.1|\[::1\]):$PORT .*LISTENING" | awk '{print $NF}' | sort -u); do taskkill //PID $pid //F >/dev/null 2>&1; done; sleep 1
$S/mkshop15.sh $V $PORT $NAME $DB || exit 1
[ "$NAME" = oc1.5.6.4-php74 ] && cp $S/encryption-openssl.php $E/shops/$NAME/system/library/encryption.php
[ "$V" = 1.5.5.1 ] && sed -i "s/define('DB_DRIVER', 'mysqli')/define('DB_DRIVER', 'mysql')/" $E/shops/$NAME/config.php $E/shops/$NAME/admin/config.php
$S/serve15.sh start >/dev/null
curl -s http://localhost:$PORT/vqmod/install/ | grep -o "VQMOD HAS BEEN INSTALLED[^<]*\|ALREADY[^<]*"
$S/deploy15.sh $NAME
export PATH="/c/Users/Admin/tools/node:$PATH"
export NODE_PATH='C:\Users\Admin\AppData\Local\Temp\claude\219ab07c57d28e8b\12a18630-f9c6-4111-9dc7-ccd4f10ad3d7\scratchpad\nm'
export OCBASE=http://localhost:$PORT/ OCDIR=$E/shops/$NAME OCDB=$DB MYSQL=$E/src/mariadb-11.4.4-winx64/bin/mariadb.exe MYSQLARGS="-h127.0.0.1 -P3312 -uroot"
export SHOTS="C:/Users/Admin/abp-testenv/shots/oc15/$NAME" MAILDIR=C:/Users/Admin/abp-testenv/mail-oc15 SMTPPORT=8119 PHPLOG="$PLOG"
node $K/tests/e2e/abp15-e2e.js
