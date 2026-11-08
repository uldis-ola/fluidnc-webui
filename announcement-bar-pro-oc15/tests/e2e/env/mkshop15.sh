#!/bin/bash
# mkshop15.sh <oc version> <port> <shop dir name> <db name> — fresh OpenCart 1.5 shop + vQmod 2.6.6 (admin/admin123, local test only)
V=$1; PORT=$2; NAME=$3; DB=$4
E=/c/Users/Admin/abp-testenv
SRC=$(find $E/src/oc-$V -maxdepth 2 -type d -name upload | head -1)
W=$E/shops/$NAME
python -I -c "import shutil,sys,time
for i in range(10):
    shutil.rmtree(sys.argv[1], ignore_errors=True)
    import os
    if not os.path.exists(sys.argv[1]): break
    time.sleep(1)" "$(cygpath -m $W)"
[ -d "$W" ] && { echo "old shop folder is in use: $W" >&2; exit 1; }
cp -r "$SRC" "$W"
cp "$W/config-dist.php" "$W/config.php"; cp "$W/admin/config-dist.php" "$W/admin/config.php"
/c/Users/Admin/tools/php56/php.exe -n -c $E/scripts/oc15/php56.ini $E/scripts/oc15/install15.php "$(cygpath -m $W)" $DB http://localhost:$PORT/ || exit 1
python -I -c "import shutil,sys; shutil.rmtree(sys.argv[1])" "$(cygpath -m $W/install)"
cp -r $E/src/vqmod-2.6.6/vqmod "$W/"
echo "shop $NAME ready (vQmod copied; run its installer over HTTP)"
