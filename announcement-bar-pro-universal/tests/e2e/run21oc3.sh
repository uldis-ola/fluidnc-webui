#!/bin/bash
S=${ABP_WORK:?set ABP_WORK to a work folder (OpenCart sources + test shops)}; Z=$(cd "$(dirname "$0")/../.." && pwd)/dist/v2.1.1/announcement_bar_pro.ocmod.zip; W=$S/oc21; DB=oc_210; P=8096
mysql -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4; GRANT ALL ON $DB.* TO 'oc'@'localhost';"
[ -d $W ] || cp -r $S/oc-src/upload $W
cd $W && cp config-dist.php config.php && cp admin/config-dist.php admin/config.php && chmod -R a+w . && [ -d install ] && (cd install && php cli_install.php install --db_hostname localhost --db_username oc --db_password ocpass --db_database $DB --db_driver mysqli --db_port 3306 --username admin --password admin123 --email admin@example.com --http_server http://localhost:$P/ | grep -i success) && mv install ../oc21-install-disabled
mysql --default-character-set=utf8mb4 $DB -e "INSERT INTO oc_store SET name='Store Two', url='http://127.0.0.1:$P/', \`ssl\`=''; INSERT INTO oc_user_group SET name='Viewer', permission='{\"access\":[\"extension/module/announcement_bar_pro\",\"common/dashboard\"],\"modify\":[]}'; INSERT INTO oc_user SET user_group_id=LAST_INSERT_ID(), username='viewer', salt='', password=MD5('viewer123'), firstname='V', lastname='U', email='v@example.com', image='', code='', ip='', status=1, date_added=NOW();"
(nohup php -S localhost:$P -t $W > $S/php-server21.log 2>&1 &); sleep 1
cd "$(dirname "$0")"; export OCBASE=http://localhost:$P/ OCDB=$DB OCDIR=$W
{ echo "== phase1 v1"; node phase1_v1.js $S; echo "== phase2 upgrade"; node phase2_upgrade.js $S $Z; echo "== phase3"; node phase3.js $S; echo "== phase4"; node phase4.js $S
mysql --default-character-set=utf8mb4 $DB -e "INSERT INTO oc_language SET name='Latviešu', code='lv-lv', locale='lv_LV.UTF-8,lv-lv', image='', directory='', sort_order=2, status=1;"
for d in admin/language catalog/language; do mkdir -p $W/$d/lv-lv && cp -r --update=none $W/$d/en-gb/. $W/$d/lv-lv/ && mv $W/$d/lv-lv/en-gb.php $W/$d/lv-lv/lv-lv.php && mv $W/$d/lv-lv/en-gb.png $W/$d/lv-lv/lv-lv.png; done
find $W/system/storage/cache -maxdepth 1 -name 'cache.*' -delete
mysql $DB -e "UPDATE oc_user_group SET permission='{\"access\":[\"extension/module/announcement_bar_pro\",\"common/dashboard\"],\"modify\":[]}' WHERE name='Viewer'"
echo "== phase5 lang"; node phase5_lang.js $S; echo "== phase8"; node phase8_fixes.js; echo "== phase10"; node phase10_v202.js; echo "== phase11"; node phase11_v203.js; echo "== v205"; KIND=oc3 OCDIR=$W node v205.js; echo "== adapt"; node adapt.js; } > $S/final210.out 2>&1
echo "OC3 2.1.0 PASS: $(grep -c '^PASS' $S/final210.out)  FAIL: $(grep -c '^FAIL' $S/final210.out)"; grep -E "^FAIL|name: 'Error'" $S/final210.out; awk '/^==/{h=$0} /^PASS|^FAIL/{c[h]++} END{for(k in c) print c[k], k}' $S/final210.out | sort -k3; grep "v1 bar\|store after" $S/final210.out; echo "error.log: $(wc -c < $W/system/storage/logs/error.log)"
