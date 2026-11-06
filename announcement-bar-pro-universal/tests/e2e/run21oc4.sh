#!/bin/bash
S=${ABP_WORK:?set ABP_WORK to a work folder (OpenCart sources + test shops)}; Z=$(cd "$(dirname "$0")/../.." && pwd)/dist/v2.1.1/announcement_bar_pro.ocmod.zip; cd "$(dirname "$0")"
for v in "8113 u_old3 uold3" "8114 u_old4 uold4" "8115 u_old5 uold5" "8102 u_4023 u4023" "8103 u_4104 u4104"; do set -- $v
 ./oc4_reset.sh $2 $S/$3 >/dev/null; mysql $2 -e "DELETE FROM oc_language WHERE code='de-de'"; : > $S/$3/system/storage/logs/error.log
 E="OCBASE=http://localhost:$1/ OCDB=$2 OCDIR=$S/$3"
 env OCBASE=http://localhost:$1/ OCDB=$2 OCDIR=$S/$3 timeout 400 node oc4_full_any.js $Z > $S/r21_full_$3.out 2>&1
 env OCBASE=http://localhost:$1/ OCDB=$2 OCDIR=$S/$3 timeout 200 node oc4_extra.js > $S/r21_extra_$3.out 2>&1
 env KIND=oc4 OCBASE=http://localhost:$1/ OCDB=$2 OCDIR=$S/$3 timeout 300 node v205.js > $S/r21_v205_$3.out 2>&1
 env OCBASE=http://localhost:$1/ OCDB=$2 OCDIR=$S/$3 timeout 300 node adapt.js > $S/r21_adapt_$3.out 2>&1
 V=$(grep -oP "define\('VERSION', '\K[^']+" $S/$3/index.php)
 echo "OC $V: full $(grep -c '^PASS' $S/r21_full_$3.out)/$(grep -c '^PASS\|^FAIL' $S/r21_full_$3.out)  extra $(grep -c '^PASS' $S/r21_extra_$3.out)/$(grep -c '^PASS\|^FAIL' $S/r21_extra_$3.out)  v205 $(grep -c '^PASS' $S/r21_v205_$3.out)/$(grep -c '^PASS\|^FAIL' $S/r21_v205_$3.out)  adapt $(grep -c '^PASS' $S/r21_adapt_$3.out)/$(grep -c '^PASS\|^FAIL' $S/r21_adapt_$3.out)  module-log-lines: $(grep -ci 'announcement_bar_pro\|announcementbarpro' $S/$3/system/storage/logs/error.log)"
 grep -h "^FAIL\|Error:" $S/r21_*_$3.out | head -6
done
