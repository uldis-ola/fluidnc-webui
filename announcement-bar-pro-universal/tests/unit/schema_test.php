<?php
/**
 * Announcement Bar Pro — schema 7 (2.3.0) migration test WITHOUT a database.
 *
 * Loads the real admin models of both kits (OpenCart 3 and OpenCart 4) on top of minimal OpenCart stubs and a
 * fake DB driver that keeps a table → columns map, answers SHOW TABLES / SHOW COLUMNS … LIKE and applies
 * CREATE TABLE / ALTER TABLE … ADD COLUMN like MySQL does (a duplicate ADD COLUMN throws, as MySQL error 1060).
 * What it proves: fresh install, upgrade 5→7 and 6→7, interrupted upgrade, repeat runs (idempotent), LIKE escaping,
 * column and value whitelists, text escaping, duplicate, statistics reset, "use B" SQL and the shared sanitizer helpers
 * (weekly schedule incl. midnight, coupon, ID lists, amounts).
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
				'device' => 'mobile', 'views' => 120, 'clicks' => 9, 'date_added' => '2026-11-01 10:00:00', 'date_modified' => '2026-11-01 10:00:00',
				'type' => 'free_shipping', 'threshold' => '50.0000', 'progress_bar' => 1, 'coupon' => 'XMAS', 'copies' => 4, 'ab_test' => 1, 'views_b' => 100, 'clicks_b' => 12, 'copies_b' => 2,
				'weekdays' => '12345', 'time_start' => '09:00', 'time_end' => '17:00', 'categories' => '[20,27]', 'products' => '[]', 'style' => 'soft', 'button_style' => 'filled'
			)) : array());
		}

		if (preg_match("/^SELECT \* FROM `oc_abp_message_description` WHERE `message_id` = '7'$/", $sql)) {
			return $this->res(array(array('message_id' => 7, 'language_id' => 1, 'text' => "It's on", 'link_text' => 'Shop', 'mobile_text' => 'On', 'success_text' => 'Free!', 'text_b' => 'B on', 'mobile_text_b' => '', 'link_text_b' => 'Go')));
		}

		if (preg_match("/^SELECT `ab_test` FROM `oc_abp_message` WHERE `message_id` = '(\d+)'$/", $sql, $m)) {
			return $this->res($m[1] === '7' ? array(array('ab_test' => 1)) : array());
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
// 2.2.0 (schema 6) tables
$s6_message = array_merge($old_message, array('device', 'views', 'clicks'));
$s6_desc    = array_merge($old_desc, array('mobile_text'));
$new_message = array('type', 'threshold', 'progress_bar', 'coupon', 'copies', 'ab_test', 'views_b', 'clicks_b', 'copies_b', 'weekdays', 'time_start', 'time_end', 'categories', 'products', 'style', 'button_style');
$new_desc    = array('success_text', 'text_b', 'mobile_text_b', 'link_text_b');

foreach (array('OC3' => 'ModelExtensionModuleAnnouncementBarPro', 'OC4' => 'Opencart\Admin\Model\Extension\AnnouncementBarPro\Module\AnnouncementBarPro') as $kit => $class) {
	check("$kit SCHEMA_VERSION is 7", constant($class . '::SCHEMA_VERSION') === 7);

	// 1. Fresh install: CREATE TABLE already has every column, nothing is altered.
	list($model, $db, $setting) = make($class);
	$model->ensureSchema();
	check("$kit fresh: abp_message has the 2.2.0 + 2.3.0 columns", !array_diff(array_merge(array('device', 'views', 'clicks'), $new_message), $db->tables['abp_message']), implode(',', $db->tables['abp_message']));
	check("$kit fresh: description has mobile_text + 2.3.0 texts", !array_diff(array_merge(array('mobile_text'), $new_desc), $db->tables['abp_message_description']));
	check("$kit fresh: no ADD COLUMN", !alters($db), implode(' | ', alters($db)));
	check("$kit fresh: 24 SHOW COLUMNS checks", count(preg_grep('/^SHOW COLUMNS /', $db->log)) === 24, (string)count(preg_grep('/^SHOW COLUMNS /', $db->log)));
	check("$kit fresh: schema setting 7, new settings defaults", (int)$setting->settings['module_announcement_bar_pro_schema'] === 7 && $setting->settings['module_announcement_bar_pro_style'] === 'solid' && $setting->settings['module_announcement_bar_pro_transition'] === 'fade' && (int)$setting->settings['module_announcement_bar_pro_arrows'] === 0);
	$create = implode("\n", preg_grep('/^CREATE TABLE/', $db->log));
	check("$kit fresh: column types", strpos($create, "`device` VARCHAR(8) NOT NULL DEFAULT 'all'") !== false && strpos($create, "`views` INT(10) UNSIGNED NOT NULL DEFAULT '0'") !== false && strpos($create, "`threshold` DECIMAL(15,4) NOT NULL DEFAULT '0.0000'") !== false && strpos($create, "`copies_b` INT(10) UNSIGNED NOT NULL DEFAULT '0'") !== false && strpos($create, '`categories` TEXT NOT NULL,') !== false && strpos($create, '`link_text_b` TEXT NOT NULL,') !== false);
	check("$kit fresh: no DEFAULT on any TEXT column (MySQL refuses it)", !preg_match('/` TEXT NOT NULL DEFAULT/', $create));

	// 2. Upgrade from schema 5 (2.1.x): existing tables without the 2.2.0 / 2.3.0 columns.
	list($model, $db, $setting) = make($class);
	$db->tables = array('abp_message' => $old_message, 'abp_message_description' => $old_desc);
	$setting->settings = array('module_announcement_bar_pro_schema' => 5, 'module_announcement_bar_pro_status' => 1, 'module_announcement_bar_pro_color_mode' => 'custom');
	$model->ensureSchema();
	$a = alters($db);
	check("$kit upgrade 5→7: exactly 24 ADD COLUMN", count($a) === 24, count($a) . ': ' . implode(' | ', $a));
	check("$kit upgrade 5→7: 2.2.0 ALTER statements", in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `device` VARCHAR(8) NOT NULL DEFAULT 'all'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `views` INT(10) UNSIGNED NOT NULL DEFAULT '0'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message_description` ADD COLUMN `mobile_text` TEXT NOT NULL", $a, true), implode(' | ', $a));
	check("$kit upgrade 5→7: settings kept, schema 7", (int)$setting->settings['module_announcement_bar_pro_schema'] === 7 && $setting->settings['module_announcement_bar_pro_color_mode'] === 'custom' && (int)$setting->settings['module_announcement_bar_pro_status'] === 1);
	check("$kit upgrade: SHOW COLUMNS escapes LIKE wildcards", (bool)preg_grep("/^SHOW COLUMNS FROM `oc_abp_message_description` LIKE 'mobile\\\\\\\\_text'$/", $db->log), implode(' | ', preg_grep('/^SHOW COLUMNS/', $db->log)));

	// 3. Upgrade from schema 6 (2.2.0, like the live shop): only the 2.3.0 columns are added.
	list($model, $db, $setting) = make($class);
	$db->tables = array('abp_message' => $s6_message, 'abp_message_description' => $s6_desc);
	$setting->settings = array('module_announcement_bar_pro_schema' => 6, 'module_announcement_bar_pro_status' => 1, 'module_announcement_bar_pro_color_mode' => 'custom', 'module_announcement_bar_pro_mode' => 'stack', 'module_announcement_bar_pro_dismiss_days' => 7);
	$model->ensureSchema();
	$a = alters($db);
	check("$kit upgrade 6→7: exactly 20 ADD COLUMN", count($a) === 20, count($a) . ': ' . implode(' | ', $a));
	check("$kit upgrade 6→7: ALTER statements", in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `type` VARCHAR(16) NOT NULL DEFAULT 'standard'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `threshold` DECIMAL(15,4) NOT NULL DEFAULT '0.0000'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `coupon` VARCHAR(32) NOT NULL DEFAULT ''", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `weekdays` VARCHAR(7) NOT NULL DEFAULT ''", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `categories` TEXT NOT NULL", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `button_style` VARCHAR(16) NOT NULL DEFAULT 'outline'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message_description` ADD COLUMN `text_b` TEXT NOT NULL", $a, true), implode(' | ', $a));
	check("$kit upgrade 6→7: no 2.2.0 column added twice", !preg_grep('/ADD COLUMN `(device|views|clicks|mobile_text)`/', $a));
	check("$kit upgrade 6→7: settings kept, new defaults added, schema 7", (int)$setting->settings['module_announcement_bar_pro_schema'] === 7 && $setting->settings['module_announcement_bar_pro_mode'] === 'stack' && (int)$setting->settings['module_announcement_bar_pro_dismiss_days'] === 7 && $setting->settings['module_announcement_bar_pro_style'] === 'solid' && $setting->settings['module_announcement_bar_pro_color_mode'] === 'custom');
	check("$kit upgrade 6→7: existing messages keep the 2.2.0 button look (button_style default 'outline')", (bool)preg_grep("/ADD COLUMN `button_style` VARCHAR\(16\) NOT NULL DEFAULT 'outline'/", $a));

	// 4. Second run: schema is current → no DDL at all.
	$before = count($db->log);
	$model->ensureSchema();
	$ddl = preg_grep('/^(CREATE|ALTER|SHOW COLUMNS)/', array_slice($db->log, $before));
	check("$kit repeat: no DDL once schema 7", !$ddl, implode(' | ', $ddl));

	// 5. Repeat even when the schema key is lost (e.g. settings deleted on uninstall, tables kept): no duplicate column.
	$setting->settings = array();
	$before = count($db->log);
	$threw = '';
	try { $model->ensureSchema(); } catch (Exception $e) { $threw = $e->getMessage(); }
	check("$kit re-run with kept tables: no ADD COLUMN, no error", $threw === '' && !preg_grep('/ ADD COLUMN /', array_slice($db->log, $before)), $threw);

	// 6. Re-run 6→7 after an interrupted migration (half of the columns already there): only the rest is added.
	list($model, $db, $setting) = make($class);
	$db->tables = array('abp_message' => array_merge($s6_message, array_slice($new_message, 0, 8)), 'abp_message_description' => array_merge($s6_desc, array('success_text')));
	$setting->settings = array('module_announcement_bar_pro_schema' => 6);
	$model->ensureSchema();
	$a = alters($db);
	check("$kit interrupted 6→7: only the 11 missing columns added", count($a) === 11 && !preg_grep('/`(type|threshold|copies|success_text)`/', $a), count($a) . ': ' . implode(' | ', $a));

	// 7. addColumnIfMissing: whitelist of names, return values.
	check("$kit addColumnIfMissing refuses unsafe names", $model->addColumnIfMissing('abp_message`; DROP TABLE x; --', 'a', 'INT') === false && $model->addColumnIfMissing('abp_message', 'x` INT, ADD `y', 'INT') === false);
	check("$kit addColumnIfMissing existing column → false", $model->addColumnIfMissing('abp_message', 'device', "VARCHAR(8) NOT NULL DEFAULT 'all'") === false);

	// 8. Writes: whitelists and escaping, statistics never written by add/edit.
	list($model, $db) = make($class);
	$db->tables = array('abp_message' => array(), 'abp_message_description' => array());
	$data = array('status' => 1, 'sort_order' => 0, 'date_start' => '', 'date_end' => '', 'bg_color' => '#000000', 'text_color' => '#ffffff', 'link' => '', 'link_new_tab' => 0, 'icon' => '',
		'countdown' => 0, 'stores' => array(), 'customer_groups' => array(), 'page_mode' => 'all', 'page_routes' => '', 'device' => "mobile' OR '1'='1",
		'type' => "free_shipping' --", 'threshold' => "12.5'", 'coupon' => "x' OR 1", 'weekdays' => "1'3", 'time_start' => "09:00'", 'time_end' => '17:00', 'style' => 'glass"', 'button_style' => "filled'",
		'categories' => array('3', "4' OR 1"), 'products' => array(7),
		'message_description' => array(1 => array('text' => 'A', 'link_text' => '', 'mobile_text' => "x'y", 'text_b' => "b'b")));
	$model->addMessage($data);
	$r = $db->rows[1];
	check("$kit addMessage: unknown device / type / style / button style → safe defaults", strpos($r, "`device` = 'all'") !== false && strpos($r, "`type` = 'standard'") !== false && strpos($r, "`style` = ''") !== false && strpos($r, "`button_style` = 'outline'") !== false, $r);
	check("$kit addMessage: threshold number, coupon / time whitelisted, weekdays digits only", strpos($r, "`threshold` = '12.5000'") !== false && strpos($r, "`coupon` = ''") !== false && strpos($r, "`time_start` = ''") !== false && strpos($r, "`time_end` = '17:00'") !== false && strpos($r, "`weekdays` = '13'") !== false, $r);
	check("$kit addMessage: category / product lists as JSON integers", strpos($r, "`categories` = '[3,4]'") !== false && strpos($r, "`products` = '[7]'") !== false, $r);
	check("$kit addMessage: no counters in INSERT", !preg_match('/`(views|clicks|copies)(_b)?`/', $r));
	check("$kit addMessage: texts escaped, missing texts → ''", strpos($db->desc[0], "`mobile_text` = 'x\\'y'") !== false && strpos($db->desc[0], "`text_b` = 'b\\'b'") !== false && strpos($db->desc[0], "`success_text` = ''") !== false && strpos($db->desc[0], "`link_text_b` = ''") !== false, $db->desc[0]);
	$data['device'] = 'desktop'; $data['type'] = 'free_shipping'; $data['style'] = 'soft'; $data['button_style'] = 'link'; $data['coupon'] = 'SPRING-10';
	unset($data['message_description'][1]['mobile_text']);
	$model->addMessage($data);
	check("$kit addMessage: valid values kept", strpos($db->rows[2], "`device` = 'desktop'") !== false && strpos($db->rows[2], "`type` = 'free_shipping'") !== false && strpos($db->rows[2], "`style` = 'soft'") !== false && strpos($db->rows[2], "`button_style` = 'link'") !== false && strpos($db->rows[2], "`coupon` = 'SPRING-10'") !== false && strpos($db->desc[1], "`mobile_text` = ''") !== false);
	// The v1 migration passes no 2.3.0 keys: neutral values, no notices.
	$legacy = $data; foreach (array('type', 'threshold', 'coupon', 'weekdays', 'time_start', 'time_end', 'style', 'button_style', 'categories', 'products', 'device') as $k) { unset($legacy[$k]); }
	$model->addMessage($legacy);
	check("$kit addMessage without 2.3.0 keys: neutral values", strpos($db->rows[3], "`type` = 'standard'") !== false && strpos($db->rows[3], "`categories` = '[]'") !== false && strpos($db->rows[3], "`progress_bar` = '1'") !== false && strpos($db->rows[3], "`button_style` = 'outline'") !== false, $db->rows[3]);

	// 9. Duplicate: copy switched off, everything else copied, statistics start at 0.
	list($model, $db) = make($class);
	$new = $model->duplicateMessage(7);
	check("$kit duplicate returns new id", $new === 1);
	check("$kit duplicate: status 0, fields copied incl. 2.3.0", isset($db->rows[1]) && strpos($db->rows[1], "`status` = '0'") !== false && strpos($db->rows[1], "`device` = 'mobile'") !== false && strpos($db->rows[1], "`date_end` = '2026-12-24 23:59:59'") !== false && strpos($db->rows[1], "`sort_order` = '3'") !== false && strpos($db->rows[1], "`coupon` = 'XMAS'") !== false && strpos($db->rows[1], "`categories` = '[20,27]'") !== false && strpos($db->rows[1], "`ab_test` = '1'") !== false && !preg_match('/`(views|clicks|copies)(_b)?`/', $db->rows[1]), isset($db->rows[1]) ? $db->rows[1] : '');
	check("$kit duplicate: description copied incl. phone, success and B texts", isset($db->desc[0]) && strpos($db->desc[0], "`text` = 'It\\'s on'") !== false && strpos($db->desc[0], "`mobile_text` = 'On'") !== false && strpos($db->desc[0], "`text_b` = 'B on'") !== false && strpos($db->desc[0], "`success_text` = 'Free!'") !== false);
	check("$kit duplicate of a missing message → 0, nothing written", $model->duplicateMessage(8) === 0 && count($db->rows) === 1);

	// 10. Reset statistics: all six counters, (int) id.
	list($model, $db) = make($class);
	$model->resetStatistics($kit === 'OC3' ? '5 OR 1=1' : 5); // OC4 has an int type hint
	check("$kit resetStatistics: all counters, cast to int", end($db->log) === "UPDATE `oc_abp_message` SET `views` = '0', `clicks` = '0', `copies` = '0', `views_b` = '0', `clicks_b` = '0', `copies_b` = '0' WHERE `message_id` = '5'", end($db->log));

	// 11. Use B: only for a message with an A/B test; B texts become A, B counters become the main counters.
	list($model, $db) = make($class);
	$ok = $model->useVariantB(7);
	$tail = implode("\n", array_slice($db->log, -3));
	check("$kit useVariantB: copies B → A only where B has text, clears B, test off, B counters → main", $ok === true
		&& strpos($tail, "SET `text` = `text_b`, `mobile_text` = `mobile_text_b`, `link_text` = IF(`link_text_b` <> '', `link_text_b`, `link_text`) WHERE `message_id` = '7' AND `text_b` <> ''") !== false
		&& strpos($tail, "SET `text_b` = '', `mobile_text_b` = '', `link_text_b` = '' WHERE `message_id` = '7'") !== false
		&& strpos($tail, "SET `ab_test` = '0', `views` = `views_b`, `clicks` = `clicks_b`, `copies` = `copies_b`, `views_b` = '0'") !== false, $tail);
	$n = count($db->log);
	check("$kit useVariantB without a test → false, nothing written", $model->useVariantB(8) === false && count($db->log) === $n + 1);
}

// 12. Catalog track(): same SQL in both kits, only enabled messages, capped counter, column whitelist, B only while testing.
$sql = array();
foreach (array('OC3' => '/announcement-bar-pro/upload/catalog/model/extension/module/announcement_bar_pro.php', 'OC4' => '/announcement-bar-pro-oc4/src/catalog/model/module/announcement_bar_pro.php') as $kit => $file) {
	$src = file_get_contents($root . $file);
	preg_match('/function track\(.*?\n\t}/s', $src, $m);
	$sql[$kit] = isset($m[0]) ? $m[0] : '';
	check("$kit catalog track(): whitelisted columns, status = 1, (int) id, overflow cap, B only with ab_test", strpos($sql[$kit], "'view'  => array('a' => 'views', 'b' => 'views_b')") !== false && strpos($sql[$kit], "'copy'  => array('a' => 'copies', 'b' => 'copies_b')") !== false && strpos($sql[$kit], "AND `status` = '1'") !== false && strpos($sql[$kit], "'\" . (int)\$message_id . \"'") !== false && strpos($sql[$kit], '< 4294967295') !== false && strpos($sql[$kit], "AND `ab_test` = '1'") !== false);
	check("$kit catalog getActiveMessages(): 2.1 / 2.2 fallbacks when schema < 6 / < 7", strpos($src, "'all' AS `device`, '' AS `mobile_text`") !== false && strpos($src, "'standard' AS `type`") !== false && strpos($src, "'' AS `text_b`") !== false);
}
check('catalog track() identical in both kits', preg_replace('/\s+|: void|int |string /', '', preg_replace('/^.*?\{/s', '', $sql['OC3'])) === preg_replace('/\s+|: void|int |string /', '', preg_replace('/^.*?\{/s', '', $sql['OC4'])));

// 13. Shared sanitizer helpers (schedule, coupon, lists, amounts) — same file logic in both kits.
require $root . '/announcement-bar-pro/upload/system/library/announcementbarpro/sanitizer.php';
require $root . '/announcement-bar-pro-oc4/src/system/library/sanitizer.php';
foreach (array('OC3' => '\Announcementbarpro\Sanitizer', 'OC4' => '\Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer') as $kit => $cls) {
	$s = new $cls();
	$mon10 = strtotime('2026-11-09 10:00:00'); // a Monday
	$sat01 = strtotime('2026-11-14 01:30:00'); // Saturday night after Friday
	check("$kit scheduleOpen: no schedule = always", $s->scheduleOpen('', '', '', $mon10));
	check("$kit scheduleOpen: weekdays", $s->scheduleOpen('12345', '', '', $mon10) && !$s->scheduleOpen('67', '', '', $mon10));
	check("$kit scheduleOpen: daily window", $s->scheduleOpen('', '09:00', '17:00', $mon10) && !$s->scheduleOpen('', '10:01', '17:00', $mon10) && !$s->scheduleOpen('', '08:00', '10:00', $mon10));
	check("$kit scheduleOpen: window across midnight belongs to its start day", $s->scheduleOpen('5', '22:00', '02:00', $sat01) && !$s->scheduleOpen('6', '22:00', '02:00', $sat01) && $s->scheduleOpen('', '22:00', '02:00', $sat01) && !$s->scheduleOpen('', '22:00', '02:00', $mon10));
	check("$kit weekdays(): sorted digits, all 7 = every day", $s->weekdays(array('3', '1', '1', '9', 'x')) === '13' && $s->weekdays(array(1, 2, 3, 4, 5, 6, 7)) === '' && $s->weekdays('') === '' && $s->weekdays('7a5') === '57');
	check("$kit coupon(): uppercase whitelist, max 32", $s->coupon(' spring-10 ') === 'SPRING-10' && $s->coupon('a b') === '' && $s->coupon(str_repeat('A', 33)) === '' && $s->coupon('<b>') === '');
	check("$kit clockTime()", $s->clockTime('09:30') === '09:30' && $s->clockTime('24:00') === '' && $s->clockTime('9:30') === '');
	check("$kit idList(): positive ints, unique, max", $s->idList(array('3', '3', '0', '-1', '4x', 5)) === array(3, 5) && $s->idList('[1,2,"x"]') === array(1, 2) && count($s->idList(range(1, 300), 200)) === 200);
	check("$kit amount(): 49,99 → 49.9900, invalid → false", $s->amount('49,99') === '49.9900' && $s->amount('50') === '50.0000' && $s->amount('1e3') === false && $s->amount('-5') === false && $s->amount('') === false);
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
