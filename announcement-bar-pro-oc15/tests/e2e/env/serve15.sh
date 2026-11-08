#!/bin/bash
# serve15.sh start|stop — OWN test servers for the OpenCart 1.5 edition (never touches :3307 or other agents' ports):
#   MariaDB :3312 (db-oc15/data), PHP: :8115 OC 1.5.6.4 / PHP 5.6, :8116 OC 1.5.6.4 / PHP 7.4, :8117 OC 1.5.5.1 / PHP 5.6,
#   fake SMTP :8119 (mails → mail-oc15/*.eml)
E=/c/Users/Admin/abp-testenv
S=$E/scripts/oc15
NODE=/c/Users/Admin/tools/node/node.exe
B=$E/src/mariadb-11.4.4-winx64/bin
kill_port() { for pid in $(netstat -ano | grep -E "(127\.0\.0\.1|0\.0\.0\.0|\[::1\]|\[::\]):$1 .*LISTENING" | awk '{print $NF}' | sort -u); do taskkill //PID $pid //F >/dev/null 2>&1; done; }
if [ "$1" = stop ]; then
  for p in 8115 8116 8117 8119; do kill_port $p; done
  $B/mariadb-admin.exe -h127.0.0.1 -P3312 -uroot shutdown 2>/dev/null || kill_port 3312
  echo stopped; exit 0
fi
if ! netstat -ano | grep -q "127.0.0.1:3312 .*LISTENING"; then
  ($B/mariadbd.exe --datadir="C:\Users\Admin\abp-testenv\db-oc15\data" --port=3312 --bind-address=127.0.0.1 --sql-mode="" --character-set-server=utf8mb3 --collation-server=utf8mb3_general_ci > $E/db-oc15/run.log 2>&1 &)
  sleep 4
fi
srv() { # port shopdir phpver
  kill_port $1
  [ -d $E/shops/$2 ] || return
  (cd $E/shops/$2 && /c/Users/Admin/tools/php$3/php.exe -n -c $S/php$3.ini -S localhost:$1 -t . > $E/shops/$2-srv.log 2>&1 &)
}
srv 8115 oc1.5.6.4 56
srv 8116 oc1.5.6.4-php74 74
srv 8117 oc1.5.5.1 56
mkdir -p $E/mail-oc15; kill_port 8119
("$NODE" $E/scripts/smtp-sink.js 8119 "$E/mail-oc15" > $E/mail-oc15/smtp.log 2>&1 &)
sleep 2
netstat -ano | grep -E "127.0.0.1:(3312|8115|8116|8117|8119) .*LISTENING" | awk '{print $2}'
