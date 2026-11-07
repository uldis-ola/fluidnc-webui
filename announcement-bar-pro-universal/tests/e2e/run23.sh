#!/bin/bash
# run23.sh <oc3|oc4> — runs abp23-e2e.js against the local shop (port 8130 = OC 3.0.5.1, 8141 = OC 4.1.0.4)
E=/c/Users/Admin/abp-testenv
U=/c/Users/Admin/fluidnc-webui/announcement-bar-pro-universal/dist
export PATH="/c/Users/Admin/tools/node:$PATH"
export NODE_PATH='C:\Users\Admin\AppData\Local\Temp\claude\219ab07c57d28e8b\12a18630-f9c6-4111-9dc7-ccd4f10ad3d7\scratchpad\nm'
export MYSQL=$E/src/mariadb-11.4.4-winx64/bin/mariadb.exe MYSQLARGS="-h127.0.0.1 -P3307 -uroot" SHOTS='C:\Users\Admin\abp-testenv\shots\v23'
if [ "$1" = oc4 ]; then export OCBASE=http://localhost:8141/ OCDIR=$E/shops/oc4.1.0.4 OCDB=abp_4104; else export OCBASE=http://localhost:8130/ OCDIR=$E/shops/oc3.0.5.1 OCDB=abp_3051; fi
node $E/scripts/abp23-e2e.js "$(cygpath -w $U/v2.2.0/announcement_bar_pro.ocmod.zip)" "$(cygpath -w $U/v2.3.0/announcement_bar_pro.ocmod.zip)"
