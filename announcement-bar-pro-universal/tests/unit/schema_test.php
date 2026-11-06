<?php
/**
 * Announcement Bar Pro — schema 6 (2.2.0) migration test WITHOUT a database.
 *
 * Loads the real admin models of both kits (OpenCart 3 and OpenCart 4) on top of minimal OpenCart stubs and a
 * fake DB driver that keeps a table → columns map, answers SHOW TABLES / SHOW COLUMNS … LIKE and applies
 * CREATE TABLE / ALTER TABLE … ADD COLUMN like MySQL does (a duplicate ADD COLUMN throws, as MySQL error 1060).
 * What it proves: fresh install, upgrade from schema 5, partial upgrade, repeat runs (idempotent), LIKE escaping,
 * column whitelist, device whitelist, mobile_text escaping, duplicate and statistics reset SQL.
 * What it cannot prove: that a real MySQL/MariaDB accepts the SQL (syntax, types, charset).
 *
 * Run: php tests/unit/schema_test.php   (exit code 0 = all passed)
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('VERSION', '3.0.5.1'); // the OC4 model only uses VERSION in registerEvents(), which the test keeps "not installed"
define('DB_PREFIX', 'oc_');

$root = dirname(__DIR__, 3);

/* ------------------------------------------------------------------ fake DB */
class FakeDb {
	public $tables = array();   // name => array of column names
	public $log = array();
	public $rows = array();     // abp_message rows by id (only what the tests need)
	public $desc = array();     // abp_message_description rows
	private $last = 0;

	public function escape($value) {
		return addslashes((string)$value);
	}

	public function getLastId() {
		return $this->last;
	}

	private function res(array $rows) {
		return (object)array('num_rows' => count($rows), 'rows' => $rows, 'row' => $rows ? $rows[0] : array());
	}

	private static function likeToRegex($sql_literal) {
		$value = stripslashes($sql_literal);                 // undo escape(): \\_ → \_
		$out = '';
		for ($i = 0; $i < strlen($value); $i++) {
			$c = $value[$i];
			if ($c === '\\' && $i + 1 < strlen($value)) { $out .= preg_quote($value[++$i], '/'); continue; }
			$out .= $c === '_' ? '.' : ($c === '%' ? '.*' : preg_quote($c, '/'));
		}
		return '/^' . $out . '$/';
	}

	public function query($sql) {
		$sql = trim($sql);
		$this->log[] = $sql;

		if (preg_match('/^CREATE TABLE IF NOT EXISTS `oc_(\w+)` \((.*)\)/s', $sql, $m)) {
			if (!isset($this->tables[$m[1]])) {
				preg_match_all('/^\s*`(\w+)`/m', $m[2], $cols);
				$this->tables[$m[1]] = $cols[1];
			}
			return $this->res(array());
		}

		if (preg_match("/^SHOW TABLES LIKE '(.*)'$/", $sql, $m)) {
			$re = self::likeToRegex($m[1]);
			foreach (array_keys($this->tables) as $t) {
				if (preg_match($re, 'oc_' . $t)) { return $this->res(array(array('t' => $t))); }
			}
			return $this->res(array());
		}

		if (preg_match("/^SHOW COLUMNS FROM `oc_(\w+)` LIKE '(.*)'$/", $sql, $m)) {
			if (!isset($this->tables[$m[1]])) { throw new Exception("Table oc_{$m[1]} doesn't exist"); }
			$re = self::likeToRegex($m[2]);
			foreach ($this->tables[$m[1]] as $c) {
				if (preg_match($re, $c)) { return $this->res(array(array('Field' => $c))); }
			}
			return $this->res(array());
		}

		if (preg_match('/^ALTER TABLE `oc_(\w+)` ADD COLUMN `(\w+)` (.+)$/s', $sql, $m)) {
			if (in_array($m[2], $this->tables[$m[1]], true)) { throw new Exception("Duplicate column name '{$m[2]}'"); }
			$this->tables[$m[1]][] = $m[2];
			return $this->res(array());
		}

		if (preg_match('/^ALTER TABLE `oc_\w+` MODIFY /', $sql)) {
			return $this->res(array());
		}

		if (preg_match('/^SELECT COUNT\(\*\) AS `?total`? FROM `oc_abp_message`/', $sql)) {
			return $this->res(array(array('total' => count($this->rows))));
		}

		if (preg_match('/^INSERT INTO `oc_abp_message` SET (.*)$/s', $sql, $m)) {
			$this->last++;
			$this->rows[$this->last] = $m[1];
			return $this->res(array());
		}

		if (preg_match('/^INSERT INTO `oc_abp_message_description` SET (.*)$/s', $sql, $m)) {
			$this->desc[] = $m[1];
			return $this->res(array());
		}

		if (preg_match("/^SELECT \* FROM `oc_abp_message` WHERE `message_id` = '(\d+)'$/", $sql, $m)) {
			return $this->res($m[1] === '7' ? array(array(
				'message_id' => 7, 'status' => 1, 'sort_order' => 3, 'date_start' => null, 'date_end' => '2026-12-24 23:59:59',
				'bg_color' => '#112233', 'text_color' => '#ffffff', 'link' => '/sale', 'link_new_tab' => 1, 'icon' => 'fa-gift',
				'countdown' => 1, 'stores' => '[0]', 'customer_groups' => '[]', 'page_mode' => 'all', 'page_routes' => '',
				'device' => 'mobile', 'views' => 120, 'clicks' => 9, 'date_added' => '2026-11-01 10:00:00', 'date_modified' => '2026-11-01 10:00:00'
			)) : array());
		}

		if (preg_match("/^SELECT \* FROM `oc_abp_message_description` WHERE `message_id` = '7'$/", $sql)) {
			return $this->res(array(array('message_id' => 7, 'language_id' => 1, 'text' => "It's on", 'link_text' => 'Shop', 'mobile_text' => 'On')));
		}

		return $this->res(array());
	}
}

/* ------------------------------------------------------------------ OpenCart stubs */
class FakeSetting {
	public $settings = array();
	public function getSetting($code) { return $this->settings; }
	public function editSetting($code, $data) { $this->settings = $data; }
}
class FakeExtension {
	public function getInstalled($type) { return array(); }         // OC3: module not installed → no event writes
	public function getExtensionByCode($type, $code) { return array(); } // OC4: same
}
class FakeLoader {
	private $reg;
	public function __construct($reg) { $this->reg = $reg; }
	public function model($route) {
		$key = 'model_' . str_replace('/', '_', $route);
		if (!isset($this->reg->data[$key])) {
			$this->reg->data[$key] = $route === 'setting/setting' ? new FakeSetting() : new FakeExtension();
		}
	}
}
class FakeRegistry {
	public $data = array();
}
class Model { // OpenCart 3 base
	protected $registry;
	public function __construct($registry) { $this->registry = $registry; }
	public function __get($key) { return isset($this->registry->data[$key]) ? $this->registry->data[$key] : null; }
}
// OpenCart 4 base class (same behaviour). eval of a constant string: a namespaced stub cannot be declared after
// global code in the same file otherwise; no input reaches it.
eval('namespace Opencart\System\Engine; class Model extends \Model {}');

require $root . '/announcement-bar-pro/upload/admin/model/extension/module/announcement_bar_pro.php';
require $root . '/announcement-bar-pro-oc4/src/admin/model/module/announcement_bar_pro.php';

/* ------------------------------------------------------------------ helpers */
$pass = 0;
$fail = 0;
function check($name, $cond, $extra = '') {
	global $pass, $fail;
	if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ($extra !== '' ? " — $extra" : '') . "\n"; }
}
function make($class) {
	$reg = new FakeRegistry();
	$reg->data['db'] = new FakeDb();
	$reg->data['load'] = new FakeLoader($reg);
	$reg->data['load']->model('setting/setting');
	return array(new $class($reg), $reg->data['db'], $reg->data['model_setting_setting']);
}
function alters(FakeDb $db) {
	return array_values(array_filter($db->log, function ($q) { return strpos($q, ' ADD COLUMN ') !== false; }));
}
$old_message = array('message_id', 'status', 'sort_order', 'date_start', 'date_end', 'bg_color', 'text_color', 'link', 'link_new_tab', 'icon', 'countdown', 'stores', 'customer_groups', 'page_mode', 'page_routes', 'date_added', 'date_modified');
$old_desc    = array('message_id', 'language_id', 'text', 'link_text');

foreach (array('OC3' => 'ModelExtensionModuleAnnouncementBarPro', 'OC4' => 'Opencart\Admin\Model\Extension\AnnouncementBarPro\Module\AnnouncementBarPro') as $kit => $class) {
	check("$kit SCHEMA_VERSION is 6", constant($class . '::SCHEMA_VERSION') === 6);

	// 1. Fresh install: CREATE TABLE already has the columns, nothing is altered.
	list($model, $db, $setting) = make($class);
	$model->ensureSchema();
	check("$kit fresh: abp_message has device/views/clicks", !array_diff(array('device', 'views', 'clicks'), $db->tables['abp_message']), implode(',', $db->tables['abp_message']));
	check("$kit fresh: description has mobile_text", in_array('mobile_text', $db->tables['abp_message_description'], true));
	check("$kit fresh: no ADD COLUMN", !alters($db), implode(' | ', alters($db)));
	check("$kit fresh: 4 SHOW COLUMNS checks", count(preg_grep('/^SHOW COLUMNS /', $db->log)) === 4);
	check("$kit fresh: schema setting 6", (int)$setting->settings['module_announcement_bar_pro_schema'] === 6);
	$create = implode("\n", preg_grep('/^CREATE TABLE/', $db->log));
	check("$kit fresh: column types", strpos($create, "`device` VARCHAR(8) NOT NULL DEFAULT 'all'") !== false && strpos($create, "`views` INT(10) UNSIGNED NOT NULL DEFAULT '0'") !== false && strpos($create, "`clicks` INT(10) UNSIGNED NOT NULL DEFAULT '0'") !== false && strpos($create, '`mobile_text` TEXT NOT NULL,') !== false);

	// 2. Upgrade from schema 5: existing tables without the 2.2.0 columns.
	list($model, $db, $setting) = make($class);
	$db->tables = array('abp_message' => $old_message, 'abp_message_description' => $old_desc);
	$setting->settings = array('module_announcement_bar_pro_schema' => 5, 'module_announcement_bar_pro_status' => 1, 'module_announcement_bar_pro_color_mode' => 'custom');
	$model->ensureSchema();
	$a = alters($db);
	check("$kit upgrade 5→6: exactly 4 ADD COLUMN", count($a) === 4, implode(' | ', $a));
	check("$kit upgrade: ALTER statements", in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `device` VARCHAR(8) NOT NULL DEFAULT 'all'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `views` INT(10) UNSIGNED NOT NULL DEFAULT '0'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `clicks` INT(10) UNSIGNED NOT NULL DEFAULT '0'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message_description` ADD COLUMN `mobile_text` TEXT NOT NULL", $a, true), implode(' | ', $a));
	check("$kit upgrade: no DEFAULT on TEXT (MySQL refuses it)", !preg_grep('/mobile_text` TEXT[^,]*DEFAULT/', $db->log));
	check("$kit upgrade: settings kept, schema 6", (int)$setting->settings['module_announcement_bar_pro_schema'] === 6 && $setting->settings['module_announcement_bar_pro_color_mode'] === 'custom' && (int)$setting->settings['module_announcement_bar_pro_status'] === 1);
	check("$kit upgrade: SHOW COLUMNS escapes LIKE wildcards", (bool)preg_grep("/^SHOW COLUMNS FROM `oc_abp_message_description` LIKE 'mobile\\\\\\\\_text'$/", $db->log), implode(' | ', preg_grep('/^SHOW COLUMNS/', $db->log)));

	// 3. Second run: schema is current → no DDL at all.
	$before = count($db->log);
	$model->ensureSchema();
	$ddl = preg_grep('/^(CREATE|ALTER|SHOW COLUMNS)/', array_slice($db->log, $before));
	check("$kit repeat: no DDL once schema 6", !$ddl, implode(' | ', $ddl));

	// 4. Repeat even when the schema key is lost (e.g. settings deleted on uninstall, tables kept): no duplicate column.
	$setting->settings = array();
	$before = count($db->log);
	$threw = '';
	try { $model->ensureSchema(); } catch (Exception $e) { $threw = $e->getMessage(); }
	check("$kit reinstall with kept tables: no ADD COLUMN, no error", $threw === '' && !preg_grep('/ ADD COLUMN /', array_slice($db->log, $before)), $threw);

	// 5. Partial upgrade (one column already there): only the missing ones are added.
	list($model, $db, $setting) = make($class);
	$db->tables = array('abp_message' => array_merge($old_message, array('views')), 'abp_message_description' => array_merge($old_desc, array('mobile_text')));
	$setting->settings = array('module_announcement_bar_pro_schema' => 5);
	$model->ensureSchema();
	$a = alters($db);
	check("$kit partial: only device + clicks added", count($a) === 2 && strpos($a[0], '`device`') !== false && strpos($a[1], '`clicks`') !== false, implode(' | ', $a));

	// 6. addColumnIfMissing: whitelist of names, return values.
	check("$kit addColumnIfMissing refuses unsafe names", $model->addColumnIfMissing('abp_message`; DROP TABLE x; --', 'a', 'INT') === false && $model->addColumnIfMissing('abp_message', 'x` INT, ADD `y', 'INT') === false);
	check("$kit addColumnIfMissing existing column → false", $model->addColumnIfMissing('abp_message', 'device', "VARCHAR(8) NOT NULL DEFAULT 'all'") === false);

	// 7. Writes: device whitelist, mobile_text escaped, statistics never written by add/edit.
	list($model, $db) = make($class);
	$db->tables = array('abp_message' => array(), 'abp_message_description' => array());
	$data = array('status' => 1, 'sort_order' => 0, 'date_start' => '', 'date_end' => '', 'bg_color' => '#000000', 'text_color' => '#ffffff', 'link' => '', 'link_new_tab' => 0, 'icon' => '',
		'countdown' => 0, 'stores' => array(), 'customer_groups' => array(), 'page_mode' => 'all', 'page_routes' => '', 'device' => "mobile' OR '1'='1",
		'message_description' => array(1 => array('text' => 'A', 'link_text' => '', 'mobile_text' => "x'y")));
	$model->addMessage($data);
	check("$kit addMessage: unknown device stored as 'all'", strpos($db->rows[1], "`device` = 'all'") !== false, $db->rows[1]);
	check("$kit addMessage: no views/clicks in INSERT", strpos($db->rows[1], '`views`') === false && strpos($db->rows[1], '`clicks`') === false);
	check("$kit addMessage: mobile_text escaped", strpos($db->desc[0], "`mobile_text` = 'x\\'y'") !== false, $db->desc[0]);
	$data['device'] = 'desktop';
	unset($data['message_description'][1]['mobile_text']);
	$model->addMessage($data);
	check("$kit addMessage: valid device kept, missing mobile_text → ''", strpos($db->rows[2], "`device` = 'desktop'") !== false && strpos($db->desc[1], "`mobile_text` = ''") !== false);

	// 8. Duplicate: copy switched off, everything else copied, statistics start at 0.
	list($model, $db) = make($class);
	$new = $model->duplicateMessage(7);
	check("$kit duplicate returns new id", $new === 1);
	check("$kit duplicate: status 0, device + fields copied", isset($db->rows[1]) && strpos($db->rows[1], "`status` = '0'") !== false && strpos($db->rows[1], "`device` = 'mobile'") !== false && strpos($db->rows[1], "`date_end` = '2026-12-24 23:59:59'") !== false && strpos($db->rows[1], "`sort_order` = '3'") !== false && strpos($db->rows[1], '`views`') === false, isset($db->rows[1]) ? $db->rows[1] : '');
	check("$kit duplicate: description copied incl. mobile_text", isset($db->desc[0]) && strpos($db->desc[0], "`text` = 'It\\'s on'") !== false && strpos($db->desc[0], "`mobile_text` = 'On'") !== false);
	check("$kit duplicate of a missing message → 0, nothing written", $model->duplicateMessage(8) === 0 && count($db->rows) === 1);

	// 9. Reset statistics.
	list($model, $db) = make($class);
	$model->resetStatistics($kit === 'OC3' ? '5 OR 1=1' : 5); // OC4 has an int type hint
	check("$kit resetStatistics: cast to int", end($db->log) === "UPDATE `oc_abp_message` SET `views` = '0', `clicks` = '0' WHERE `message_id` = '5'", end($db->log));
}

// 10. Catalog track(): same SQL in both kits, only enabled messages, capped counter, column whitelist.
$sql = array();
foreach (array('OC3' => '/announcement-bar-pro/upload/catalog/model/extension/module/announcement_bar_pro.php', 'OC4' => '/announcement-bar-pro-oc4/src/catalog/model/module/announcement_bar_pro.php') as $kit => $file) {
	$src = file_get_contents($root . $file);
	preg_match('/function track\(.*?\n\t}/s', $src, $m);
	$sql[$kit] = isset($m[0]) ? $m[0] : '';
	check("$kit catalog track(): whitelisted column, status = 1, (int) id, overflow cap", strpos($sql[$kit], "\$type === 'click' ? 'clicks' : 'views'") !== false && strpos($sql[$kit], "AND `status` = '1'") !== false && strpos($sql[$kit], "'\" . (int)\$message_id . \"'") !== false && strpos($sql[$kit], '< 4294967295') !== false);
	check("$kit catalog getActiveMessages(): 2.1 fallback when schema < 6", strpos($src, "'all' AS `device`, '' AS `mobile_text`") !== false);
}
check('catalog track() UPDATE identical in both kits', preg_replace('/\s+|: void|int |string /', '', preg_replace('/^.*?\{/s', '', $sql['OC3'])) === preg_replace('/\s+|: void|int |string /', '', preg_replace('/^.*?\{/s', '', $sql['OC4'])));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
