#!/usr/bin/env bash
# Runs the unit tests with every PHP given (default: the local PHP 5.6 and 7.4), bare (no php.ini) and, when the test
# environment's ini exists, also with mbstring / openssl loaded. Exit code 0 = all passed everywhere.
cd "$(dirname "$0")/../.."
PHPS=${*:-"/c/Users/Admin/tools/php56/php.exe /c/Users/Admin/tools/php74/php.exe"}
rc=0
for PHP in $PHPS; do
	command -v "$PHP" >/dev/null 2>&1 || { echo "skip: $PHP not found"; continue; }
	V=$("$PHP" -n -r 'echo PHP_VERSION;'); PV=$("$PHP" -n -r 'echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION;')
	for f in tests/unit/schema15_test.php tests/unit/oc15_test.php; do
		out=$("$PHP" "$f" 2>&1); code=$?
		echo "PHP $V (plain)    $f: $(echo "$out" | tail -1) [exit $code]"; [ $code -eq 0 ] || { echo "$out" | grep -v '^PASS' | tail -8; rc=1; }
	done
	INI=/c/Users/Admin/abp-testenv/scripts/oc15/php$PV.ini
	if [ -f "$INI" ]; then
		out=$("$PHP" -n -c "$INI" -d display_errors=1 -d log_errors=0 tests/unit/schema15_test.php 2>&1); code=$?
		echo "PHP $V (with ini) tests/unit/schema15_test.php: $(echo "$out" | tail -1) [exit $code]"; [ $code -eq 0 ] || rc=1
	fi
done
exit $rc
