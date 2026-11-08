#!/bin/bash
# run-oc2.sh <version> <port> — abp-oc2-e2e.js against a fresh local OpenCart 2.x shop (oc2.sh mkshop/serve first)
E=/c/Users/Admin/abp-testenv
P=/c/Users/Admin/abp-testenv/pkg-oc2/$( [[ $1 == 2.3* ]] && echo 23 || echo 20-22 )
export PATH="/c/Users/Admin/tools/node:$PATH"
export NODE_PATH='C:\Users\Admin\AppData\Local\Temp\claude\219ab07c57d28e8b\12a18630-f9c6-4111-9dc7-ccd4f10ad3d7\scratchpad\nm'
export MYSQL=$E/src/mariadb-11.4.4-winx64/bin/mariadb.exe MYSQLARGS="-h127.0.0.1 -P3311 -uroot" SHOTS="$(cygpath -w $E/shots/oc2/$1)" MAILDIR='C:/Users/Admin/abp-testenv/mail-oc2' SMTPPORT=8124
export OCBASE=http://localhost:$2/ OCDIR=$E/shops/oc2x-$1 OCDB=abp_oc2x_${1//./}
node $E/scripts/abp-oc2-e2e.js "$(cygpath -w $P/upload)" "$(cygpath -w $P/announcement_bar_pro.ocmod.xml)"
