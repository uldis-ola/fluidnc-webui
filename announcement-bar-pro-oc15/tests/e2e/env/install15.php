<?php
// install15.php <shop dir> <db name> <http_server> — installs an OpenCart 1.5.x test shop (local test admin admin/admin123).
// Own installer: the 1.5 CLI installer needs ext/mysql and mcrypt and writes DB_DRIVER mysql.
list(, $dir, $dbname, $http) = $argv;
$dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';
$db = new mysqli('127.0.0.1', 'root', '', '', 3312);
$db->set_charset('utf8');
$db->query("DROP DATABASE IF EXISTS `$dbname`");
$db->query("CREATE DATABASE `$dbname` CHARACTER SET utf8 COLLATE utf8_general_ci");
$db->select_db($dbname);
$sql = '';
foreach (file($dir . 'install/opencart.sql') as $line) {
	if ($line && substr($line, 0, 2) != '--' && substr($line, 0, 1) != '#') {
		$sql .= $line;
		if (preg_match('/;\s*$/', $line)) {
			if (!$db->query($sql)) { fwrite(STDERR, $db->error . "\n" . substr($sql, 0, 200) . "\n"); exit(1); }
			$sql = '';
		}
	}
}
$salt = substr(md5(uniqid(rand(), true)), 0, 9);
$db->query("DELETE FROM oc_user WHERE user_id = 1");
$db->query("INSERT INTO oc_user SET user_id = 1, user_group_id = 1, username = 'admin', salt = '$salt', password = '" . sha1($salt . sha1($salt . sha1('admin123'))) . "', status = 1, email = 'admin@example.com', date_added = NOW()");
foreach (array('config_email' => 'admin@example.com', 'config_url' => $http, 'config_encryption' => md5(mt_rand())) as $k => $v) {
	$db->query("DELETE FROM oc_setting WHERE `key` = '$k'");
	$db->query("INSERT INTO oc_setting SET `group` = 'config', `key` = '$k', value = '" . $db->real_escape_string($v) . "'");
}
$db->query("UPDATE oc_product SET viewed = 0");
$conf = function ($admin) use ($dir, $http, $dbname) {
	$app = $admin ? 'admin/' : 'catalog/';
	$o  = "<?php\n";
	$o .= "define('HTTP_SERVER', '" . $http . ($admin ? 'admin/' : '') . "');\n";
	if ($admin) { $o .= "define('HTTP_CATALOG', '$http');\n"; }
	$o .= "define('HTTP_IMAGE', '{$http}image/');\n";
	if (!$admin) { $o .= "define('HTTP_ADMIN', '{$http}admin/');\n"; }
	$o .= "define('HTTPS_SERVER', '" . $http . ($admin ? 'admin/' : '') . "');\n";
	if ($admin) { $o .= "define('HTTPS_CATALOG', '$http');\n"; }
	$o .= "define('HTTPS_IMAGE', '{$http}image/');\n";
	$o .= "define('DIR_APPLICATION', '{$dir}{$app}');\n";
	$o .= "define('DIR_SYSTEM', '{$dir}system/');\n";
	$o .= "define('DIR_DATABASE', '{$dir}system/database/');\n";
	$o .= "define('DIR_LANGUAGE', '{$dir}{$app}language/');\n";
	$o .= "define('DIR_TEMPLATE', '{$dir}{$app}view/" . ($admin ? 'template/' : 'theme/') . "');\n";
	$o .= "define('DIR_CONFIG', '{$dir}system/config/');\n";
	$o .= "define('DIR_IMAGE', '{$dir}image/');\n";
	$o .= "define('DIR_CACHE', '{$dir}system/cache/');\n";
	$o .= "define('DIR_DOWNLOAD', '{$dir}download/');\n";
	$o .= "define('DIR_LOGS', '{$dir}system/logs/');\n";
	if ($admin) { $o .= "define('DIR_CATALOG', '{$dir}catalog/');\n"; }
	$o .= "define('DB_DRIVER', 'mysqli');\ndefine('DB_HOSTNAME', '127.0.0.1');\ndefine('DB_USERNAME', 'root');\ndefine('DB_PASSWORD', '');\n";
	$o .= "define('DB_DATABASE', '$dbname');\ndefine('DB_PREFIX', 'oc_');\n?>";
	file_put_contents($dir . ($admin ? 'admin/' : '') . 'config.php', $o);
};
$conf(false);
$conf(true);
echo "installed $dbname at $http\n";
