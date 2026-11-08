# Local test environment (OpenCart 1.5)

Scripts used for the E2E runs (Windows + Git Bash; paths point to `C:\Users\Admin\abp-testenv`). Own MariaDB on port 3312
(`db-oc15/data`), PHP built-in servers 8115 (1.5.6.4 / PHP 5.6), 8116 (1.5.6.4 / PHP 7.4), 8117 (1.5.5.1 / PHP 5.6), SMTP sink 8119.

```
serve15.sh start
run15.sh 8115 1.5.6.4 oc1.5.6.4 abp_oc15_1564 'C:\Users\Admin\abp-testenv\shops\oc15-php56.log'
run15.sh 8116 1.5.6.4 oc1.5.6.4-php74 abp_oc15_1564b 'C:\Users\Admin\abp-testenv\shops\oc15-php74.log'
run15.sh 8117 1.5.5.1 oc1.5.5.1 abp_oc15_1551 'C:\Users\Admin\abp-testenv\shops\oc15-php56.log'
serve15.sh stop
```
`run15.sh` makes a fresh shop (`install15.php`, test admin admin/admin123 — local only), runs the vQmod installer over HTTP,
copies `upload/` like an FTP upload and runs `tests/e2e/abp15-e2e.js`. The PHP 7.4 shop gets an OpenSSL `encryption.php`
(OpenCart 1.5 needs mcrypt). Test-only credentials; nothing here is used by the module.
