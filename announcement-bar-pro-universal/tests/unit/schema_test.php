<?php
/**
 * Announcement Bar Pro — schema 8 (2.4.0) migration test WITHOUT a database.
 *
 * Loads the real admin models of both kits (OpenCart 3 and OpenCart 4) on top of minimal OpenCart stubs and a
 * fake DB driver that keeps a table → columns map, answers SHOW TABLES / SHOW COLUMNS … LIKE and applies
 * CREATE TABLE / ALTER TABLE … ADD COLUMN like MySQL does (a duplicate ADD COLUMN throws, as MySQL error 1060).
 * What it proves: fresh install, upgrade 5→8, 6→8 and 7→8, interrupted upgrade, repeat runs (idempotent), LIKE escaping,
 * column and value whitelists, text escaping, duplicate, statistics reset, "use B" SQL, the shared sanitizer helpers
 * (weekly schedule incl. midnight, coupon, ID lists, amounts) and the 2.4.0 feature library of both kits (options
 * validation, free-shipping overrides, tiers, cart conditions, cut-off times, custom CSS, form tokens, CSV, templates).
 * What it cannot prove: that a real MySQL/MariaDB accepts the SQL (syntax, types, charset).
 *
 * Run: php tests/unit/schema_test.php   (exit code 0 = all passed)
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('VERSION', '3.0.5.1'); // the OC4 model only uses VERSION in registerEvents(), which the test keeps "not installed"
define('DB_PREFIX', 'oc_');

$root = dirname(__DIR__, 3);
define('DIR_SYSTEM', $root . '/announcement-bar-pro/upload/system/');
define('DIR_IMAGE', __DIR__ . '/');
// OpenCart 4's library is loaded here (its kit has no DIR_EXTENSION layout); the models then find the class.
require $root . '/announcement-bar-pro-oc4/src/system/library/features.php';

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
				'weekdays' => '12345', 'time_start' => '09:00', 'time_end' => '17:00', 'categories' => '[20,27]', 'products' => '[]', 'style' => 'soft', 'button_style' => 'filled',
				'placement' => 'cart', 'priority' => 40, 'subscribes' => 3, 'options' => '{"countries_mode":"include","countries":[117],"trigger":"delay","trigger_value":5,"email":1}'
			)) : array());
		}

		if (preg_match("/^SELECT \* FROM `oc_abp_message_description` WHERE `message_id` = '7'$/", $sql)) {
			return $this->res(array(array('message_id' => 7, 'language_id' => 1, 'text' => "It's on", 'link_text' => 'Shop', 'mobile_text' => 'On', 'success_text' => 'Free!', 'text_b' => 'B on', 'mobile_text_b' => '', 'link_text_b' => 'Go', 'extra' => '{"email_button":"Join","tier_label":[]}')));
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
// 2.3.0 (schema 7) tables and the 2.4.0 additions
$s7_message  = array_merge($s6_message, $new_message);
$s7_desc     = array_merge($s6_desc, $new_desc);
$v24_message = array('placement', 'priority', 'options', 'subscribes');

foreach (array('OC3' => 'ModelExtensionModuleAnnouncementBarPro', 'OC4' => 'Opencart\Admin\Model\Extension\AnnouncementBarPro\Module\AnnouncementBarPro') as $kit => $class) {
	check("$kit SCHEMA_VERSION is 8", constant($class . '::SCHEMA_VERSION') === 8);

	// 1. Fresh install: CREATE TABLE already has every column, nothing is altered.
	list($model, $db, $setting) = make($class);
	$model->ensureSchema();
	check("$kit fresh: abp_message has the 2.2.0 + 2.3.0 + 2.4.0 columns", !array_diff(array_merge(array('device', 'views', 'clicks'), $new_message, $v24_message), $db->tables['abp_message']), implode(',', $db->tables['abp_message']));
	check("$kit fresh: description has mobile_text + 2.3.0 texts + extra", !array_diff(array_merge(array('mobile_text', 'extra'), $new_desc), $db->tables['abp_message_description']));
	check("$kit fresh: subscriber + rate tables", isset($db->tables['abp_subscriber'], $db->tables['abp_rate']) && !array_diff(array('email', 'store_id', 'status', 'token', 'unsubscribe', 'consent', 'date_confirmed'), $db->tables['abp_subscriber']), implode(',', array_keys($db->tables)));
	check("$kit fresh: no ADD COLUMN", !alters($db), implode(' | ', alters($db)));
	check("$kit fresh: 29 SHOW COLUMNS checks", count(preg_grep('/^SHOW COLUMNS /', $db->log)) === 29, (string)count(preg_grep('/^SHOW COLUMNS /', $db->log)));
	check("$kit fresh: schema setting 8, new settings defaults", (int)$setting->settings['module_announcement_bar_pro_schema'] === 8 && $setting->settings['module_announcement_bar_pro_style'] === 'solid' && $setting->settings['module_announcement_bar_pro_transition'] === 'fade' && (int)$setting->settings['module_announcement_bar_pro_arrows'] === 0
		&& (int)$setting->settings['module_announcement_bar_pro_double_optin'] === 1 && (int)$setting->settings['module_announcement_bar_pro_ga4'] === 1 && $setting->settings['module_announcement_bar_pro_geo_fallback'] === 'store' && (int)$setting->settings['module_announcement_bar_pro_max_messages'] === 0);
	check("$kit fresh: form secret (64 hex) written, schema written last", preg_match('/^[0-9a-f]{64}$/', $setting->settings['module_announcement_bar_pro_secret']) === 1 && array_key_last($setting->settings) === 'module_announcement_bar_pro_schema');
	$create = implode("\n", preg_grep('/^CREATE TABLE/', $db->log));
	check("$kit fresh: 2.4.0 column types", strpos($create, "`placement` VARCHAR(16) NOT NULL DEFAULT ''") !== false && strpos($create, "`priority` INT(11) NOT NULL DEFAULT '0'") !== false && strpos($create, '`options` TEXT NOT NULL,') !== false && strpos($create, '`extra` TEXT NOT NULL,') !== false && strpos($create, 'UNIQUE KEY `email_store` (`email`, `store_id`)') !== false);
	check("$kit fresh: column types", strpos($create, "`device` VARCHAR(8) NOT NULL DEFAULT 'all'") !== false && strpos($create, "`views` INT(10) UNSIGNED NOT NULL DEFAULT '0'") !== false && strpos($create, "`threshold` DECIMAL(15,4) NOT NULL DEFAULT '0.0000'") !== false && strpos($create, "`copies_b` INT(10) UNSIGNED NOT NULL DEFAULT '0'") !== false && strpos($create, '`categories` TEXT NOT NULL,') !== false && strpos($create, '`link_text_b` TEXT NOT NULL,') !== false);
	check("$kit fresh: no DEFAULT on any TEXT column (MySQL refuses it)", !preg_match('/` TEXT NOT NULL DEFAULT/', $create));

	// 2. Upgrade from schema 5 (2.1.x): existing tables without the 2.2.0 / 2.3.0 columns.
	list($model, $db, $setting) = make($class);
	$db->tables = array('abp_message' => $old_message, 'abp_message_description' => $old_desc);
	$setting->settings = array('module_announcement_bar_pro_schema' => 5, 'module_announcement_bar_pro_status' => 1, 'module_announcement_bar_pro_color_mode' => 'custom');
	$model->ensureSchema();
	$a = alters($db);
	check("$kit upgrade 5→8: exactly 29 ADD COLUMN", count($a) === 29, count($a) . ': ' . implode(' | ', $a));
	check("$kit upgrade 5→8: 2.2.0 ALTER statements", in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `device` VARCHAR(8) NOT NULL DEFAULT 'all'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `views` INT(10) UNSIGNED NOT NULL DEFAULT '0'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message_description` ADD COLUMN `mobile_text` TEXT NOT NULL", $a, true), implode(' | ', $a));
	check("$kit upgrade 5→8: settings kept, schema 8", (int)$setting->settings['module_announcement_bar_pro_schema'] === 8 && $setting->settings['module_announcement_bar_pro_color_mode'] === 'custom' && (int)$setting->settings['module_announcement_bar_pro_status'] === 1);
	check("$kit upgrade: SHOW COLUMNS escapes LIKE wildcards", (bool)preg_grep("/^SHOW COLUMNS FROM `oc_abp_message_description` LIKE 'mobile\\\\\\\\_text'$/", $db->log), implode(' | ', preg_grep('/^SHOW COLUMNS/', $db->log)));

	// 3. Upgrade from schema 6 (2.2.0, like the live shop): only the 2.3.0 columns are added.
	list($model, $db, $setting) = make($class);
	$db->tables = array('abp_message' => $s6_message, 'abp_message_description' => $s6_desc);
	$setting->settings = array('module_announcement_bar_pro_schema' => 6, 'module_announcement_bar_pro_status' => 1, 'module_announcement_bar_pro_color_mode' => 'custom', 'module_announcement_bar_pro_mode' => 'stack', 'module_announcement_bar_pro_dismiss_days' => 7);
	$model->ensureSchema();
	$a = alters($db);
	check("$kit upgrade 6→8: exactly 25 ADD COLUMN", count($a) === 25, count($a) . ': ' . implode(' | ', $a));
	check("$kit upgrade 6→8: ALTER statements", in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `type` VARCHAR(16) NOT NULL DEFAULT 'standard'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `threshold` DECIMAL(15,4) NOT NULL DEFAULT '0.0000'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `coupon` VARCHAR(32) NOT NULL DEFAULT ''", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `weekdays` VARCHAR(7) NOT NULL DEFAULT ''", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `categories` TEXT NOT NULL", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `button_style` VARCHAR(16) NOT NULL DEFAULT 'outline'", $a, true)
		&& in_array("ALTER TABLE `oc_abp_message_description` ADD COLUMN `text_b` TEXT NOT NULL", $a, true), implode(' | ', $a));
	check("$kit upgrade 6→8: no 2.2.0 column added twice", !preg_grep('/ADD COLUMN `(device|views|clicks|mobile_text)`/', $a));
	check("$kit upgrade 6→8: settings kept, new defaults added, schema 8", (int)$setting->settings['module_announcement_bar_pro_schema'] === 8 && $setting->settings['module_announcement_bar_pro_mode'] === 'stack' && (int)$setting->settings['module_announcement_bar_pro_dismiss_days'] === 7 && $setting->settings['module_announcement_bar_pro_style'] === 'solid' && $setting->settings['module_announcement_bar_pro_color_mode'] === 'custom');
	check("$kit upgrade 6→8: existing messages keep the 2.2.0 button look (button_style default 'outline')", (bool)preg_grep("/ADD COLUMN `button_style` VARCHAR\(16\) NOT NULL DEFAULT 'outline'/", $a));

	// 3b. Upgrade from schema 7 (2.3.0, like the live shop): only the 2.4.0 columns + the two new tables.
	list($model, $db, $setting) = make($class);
	$db->tables = array('abp_message' => $s7_message, 'abp_message_description' => $s7_desc);
	$setting->settings = array('module_announcement_bar_pro_schema' => 7, 'module_announcement_bar_pro_status' => 1, 'module_announcement_bar_pro_color_mode' => 'custom', 'module_announcement_bar_pro_mode' => 'stack', 'module_announcement_bar_pro_style' => 'gradient');
	$model->ensureSchema();
	$a = alters($db);
	check("$kit upgrade 7→8: exactly 5 ADD COLUMN (placement, priority, options, subscribes, extra)", count($a) === 5 && in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `options` TEXT NOT NULL", $a, true) && in_array("ALTER TABLE `oc_abp_message_description` ADD COLUMN `extra` TEXT NOT NULL", $a, true) && in_array("ALTER TABLE `oc_abp_message` ADD COLUMN `priority` INT(11) NOT NULL DEFAULT '0'", $a, true), count($a) . ': ' . implode(' | ', $a));
	check("$kit upgrade 7→8: subscriber + rate tables created", isset($db->tables['abp_subscriber'], $db->tables['abp_rate']));
	check("$kit upgrade 7→8: 2.3.0 settings kept (stack, gradient), 2.4.0 defaults, secret, schema 8", (int)$setting->settings['module_announcement_bar_pro_schema'] === 8 && $setting->settings['module_announcement_bar_pro_mode'] === 'stack' && $setting->settings['module_announcement_bar_pro_style'] === 'gradient' && (int)$setting->settings['module_announcement_bar_pro_pause_button'] === 1 && strlen($setting->settings['module_announcement_bar_pro_secret']) === 64);
	$secret = $setting->settings['module_announcement_bar_pro_secret'];
	$setting->settings['module_announcement_bar_pro_schema'] = 7; // run again (e.g. a second admin tab during the upgrade)
	$before = count($db->log);
	$threw = '';
	try { $model->ensureSchema(); } catch (Exception $e) { $threw = $e->getMessage(); }
	check("$kit 7→8 re-run: no ADD COLUMN, no error, secret unchanged", $threw === '' && !preg_grep('/ ADD COLUMN /', array_slice($db->log, $before)) && $setting->settings['module_announcement_bar_pro_secret'] === $secret, $threw);

	// 4. Second run: schema is current → no DDL at all.
	$before = count($db->log);
	$model->ensureSchema();
	$ddl = preg_grep('/^(CREATE|ALTER|SHOW COLUMNS)/', array_slice($db->log, $before));
	check("$kit repeat: no DDL once schema 8", !$ddl, implode(' | ', $ddl));

	// 5. Repeat even when the schema key is lost (e.g. settings deleted on uninstall, tables kept): no duplicate column.
	$setting->settings = array();
	$before = count($db->log);
	$threw = '';
	try { $model->ensureSchema(); } catch (Exception $e) { $threw = $e->getMessage(); }
	check("$kit re-run with kept tables: no ADD COLUMN, no error", $threw === '' && !preg_grep('/ ADD COLUMN /', array_slice($db->log, $before)), $threw);

	// 6. Re-run 6→8 after an interrupted migration (half of the columns already there): only the rest is added.
	list($model, $db, $setting) = make($class);
	$db->tables = array('abp_message' => array_merge($s6_message, array_slice($new_message, 0, 8)), 'abp_message_description' => array_merge($s6_desc, array('success_text')));
	$setting->settings = array('module_announcement_bar_pro_schema' => 6);
	$model->ensureSchema();
	$a = alters($db);
	check("$kit interrupted 6→8: only the 16 missing columns added", count($a) === 16 && !preg_grep('/`(type|threshold|copies|success_text)`/', $a), count($a) . ': ' . implode(' | ', $a));

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
	// SQL value of one column from a fake INSERT ("`col` = '…'"), un-escaped (the fake escape() is addslashes()).
	$col = function ($sql, $name) { return preg_match('/`' . $name . "` = '((?:[^'\\\\]|\\\\.)*)'/", $sql, $m) ? stripslashes($m[1]) : null; };
	$o3 = json_decode((string)$col($db->rows[3], 'options'), true);
	$x3 = json_decode((string)$col(end($db->desc), 'extra'), true);
	check("$kit addMessage without 2.4.0 keys: placement '', priority 0, neutral options JSON, empty extra", $col($db->rows[3], 'placement') === '' && $col($db->rows[3], 'priority') === '0' && is_array($o3) && $o3['countries_mode'] === 'all' && $o3['trigger'] === 'none' && is_array($x3) && $x3['tier_label'] === array() && $x3['email_button'] === '', $db->rows[3] . ' / ' . end($db->desc));
	$evil = $data;
	$evil['placement'] = "top' OR 1"; $evil['priority'] = '999'; $evil['type'] = 'tiers';
	$evil['options'] = array('countries_mode' => "include'", 'countries' => array('5', "6' OR 1"), 'trigger' => 'delay', 'trigger_value' => '7', 'utm_source' => "x'y", 'image' => '../../config.php', 'font' => 'comic', 'email' => '1');
	$evil['message_description'][1]['extra'] = array('email_button' => "Jo'in <b>now</b>", 'tier_label' => array("A'"));
	$model->addMessage($evil);
	$r  = $db->rows[4];
	$o4 = json_decode((string)$col($r, 'options'), true);
	check("$kit addMessage: placement / priority whitelisted, tiers type kept", $col($r, 'placement') === '' && $col($r, 'priority') === '100' && $col($r, 'type') === 'tiers', $r);
	check("$kit addMessage: options JSON re-validated (bad values dropped), every quote escaped", is_array($o4) && $o4['countries_mode'] === 'all' && $o4['countries'] === array(5) && $o4['trigger'] === 'delay' && $o4['trigger_value'] === 7 && $o4['utm_source'] === '' && $o4['image'] === '' && $o4['font'] === '' && $o4['email'] === 1 && preg_match("/`options` = '([^'\\\\]|\\\\.)*', `date_added`/", $r) === 1, $r);
	$x4 = json_decode((string)$col(end($db->desc), 'extra'), true);
	check("$kit addMessage: extra texts plain text, ASCII-only JSON", is_array($x4) && $x4['email_button'] === "Jo'in now" && preg_match('/[^\x00-\x7F]/', end($db->desc)) === 0, end($db->desc));

	// 9. Duplicate: copy switched off, everything else copied, statistics start at 0.
	list($model, $db) = make($class);
	$new = $model->duplicateMessage(7);
	check("$kit duplicate returns new id", $new === 1);
	check("$kit duplicate: status 0, fields copied incl. 2.3.0", isset($db->rows[1]) && strpos($db->rows[1], "`status` = '0'") !== false && strpos($db->rows[1], "`device` = 'mobile'") !== false && strpos($db->rows[1], "`date_end` = '2026-12-24 23:59:59'") !== false && strpos($db->rows[1], "`sort_order` = '3'") !== false && strpos($db->rows[1], "`coupon` = 'XMAS'") !== false && strpos($db->rows[1], "`categories` = '[20,27]'") !== false && strpos($db->rows[1], "`ab_test` = '1'") !== false && !preg_match('/`(views|clicks|copies)(_b)?`/', $db->rows[1]), isset($db->rows[1]) ? $db->rows[1] : '');
	check("$kit duplicate: 2.4.0 placement, priority, options copied; subscribes not", strpos($db->rows[1], "`placement` = 'cart'") !== false && strpos($db->rows[1], "`priority` = '40'") !== false && strpos(stripslashes($db->rows[1]), '"countries_mode":"include","countries":[117]') !== false && strpos(stripslashes($db->rows[1]), '"trigger":"delay","trigger_value":5') !== false && strpos($db->rows[1], '`subscribes`') === false, $db->rows[1]);
	check("$kit duplicate: extra texts copied", isset($db->desc[0]) && strpos(stripslashes($db->desc[0]), '"email_button":"Join"') !== false, isset($db->desc[0]) ? $db->desc[0] : '');
	check("$kit duplicate: description copied incl. phone, success and B texts", isset($db->desc[0]) && strpos($db->desc[0], "`text` = 'It\\'s on'") !== false && strpos($db->desc[0], "`mobile_text` = 'On'") !== false && strpos($db->desc[0], "`text_b` = 'B on'") !== false && strpos($db->desc[0], "`success_text` = 'Free!'") !== false);
	check("$kit duplicate of a missing message → 0, nothing written", $model->duplicateMessage(8) === 0 && count($db->rows) === 1);

	// 10. Reset statistics: all six counters, (int) id.
	list($model, $db) = make($class);
	$model->resetStatistics($kit === 'OC3' ? '5 OR 1=1' : 5); // OC4 has an int type hint
	check("$kit resetStatistics: all counters incl. sign-ups, cast to int", end($db->log) === "UPDATE `oc_abp_message` SET `views` = '0', `clicks` = '0', `copies` = '0', `views_b` = '0', `clicks_b` = '0', `copies_b` = '0', `subscribes` = '0' WHERE `message_id` = '5'", end($db->log));

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
	preg_match('/function getUsableCoupon\(.*?\n\t}/s', $src, $m);
	check("$kit catalog getUsableCoupon(): dates checked in SQL like OpenCart (start < NOW(), end > NOW() — hidden on its end date)", isset($m[0]) && strpos($m[0], "AND `status` = '1' AND (`date_start` = '0000-00-00' OR `date_start` < NOW()) AND (`date_end` = '0000-00-00' OR `date_end` > NOW())") !== false && strpos($m[0], "date('Y-m-d')") === false);
}

// 12b. Catalog controller (2.4.0 review fixes): source checks in both kits.
foreach (array('OC3' => '/announcement-bar-pro/upload/catalog/controller/extension/module/announcement_bar_pro.php', 'OC4' => '/announcement-bar-pro-oc4/src/catalog/controller/module/announcement_bar_pro.php') as $kit => $file) {
	$src = file_get_contents($root . $file);
	preg_match('/function signUp\(.*?\n\t}/s', $src, $m);
	$su = isset($m[0]) ? $m[0] : '';
	check("$kit signUp(): opt-in off never touches customer accounts nor re-subscribes an unsubscribed address; 3 mails / address / 24 h", $su !== '' && strpos($su, 'setCustomerNewsletter') === false && strpos($su, "!\$double && \$existing && (int)\$existing['status'] === 2") !== false && strpos($su, "limited('subscribe-mail', 3, 86400, strtolower(\$email))") !== false);
	check("$kit subscribe(): per-IP / per-shop limits from the settings (defaults 10 / 200)", strpos($src, "_signup_limit_ip'), 1, 1000, 10), 3600)") !== false && strpos($src, "_signup_limit_total'), 1, 100000, 200), 3600, false)") !== false);
	preg_match('/public function cart\(.*?\n\t}/s', $src, $m);
	$ca = isset($m[0]) ? $m[0] : '';
	check("$kit cart(): ids validated before the rate limit (no DB write without ids), 1200 / 10 min", $ca !== '' && strpos($ca, 'if ($ids && ') !== false && strpos($ca, "limited('cart', 1200, 600)") !== false && strpos($ca, '$ids') < strpos($ca, 'limited('));
	check("$kit CloudFlare headers only through the library (REMOTE_ADDR in CloudFlare's ranges)", strpos($src, 'HTTP_CF_') === false && strpos($src, '->cfCountry(') !== false && strpos($src, '->clientIp(') !== false);
	check("$kit confirmation mail: header values without CR / LF", strpos($src, '$mail->setTo($this->fx->headerText($email))') !== false && strpos($src, 'setSubject($this->fx->headerText(') !== false);
	check("$kit consent wait passed to the template", strpos($src, "'consent'      => \$schema >= 8 && (bool)\$this->config->get(self::CODE . '_consent_wait')") !== false);
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

// 14. 2.4.0 feature library (identical class body in both kits; both are run).
require_once $root . '/announcement-bar-pro/upload/system/library/announcementbarpro/features.php';
date_default_timezone_set('Europe/Riga');
foreach (array('OC3' => '\Announcementbarpro\Features', 'OC4' => '\Opencart\System\Library\Extension\AnnouncementBarPro\Features') as $kit => $cls) {
	$f = new $cls();
	$lookup = function ($kind, $values) {
		if ($kind === 'zone') { return array(10 => 117, 20 => 222); }
		if ($kind === 'image') { return $values === 'catalog/a.jpg'; }
		$known = array('country' => array(117, 222), 'coupon' => array(5), 'product' => array(40), 'category' => array(20));
		return array_values(array_intersect((array)$values, $known[$kind]));
	};
	list($o, $e) = $f->validate(array(), $lookup, array('EUR', 'USD'));
	check("$kit validate(empty) = defaults, no errors", $o == $f->defaults() && !$e);
	list($o, $e) = $f->validate(array('countries_mode' => 'include', 'countries' => array('117'), 'fs_zones' => array(array('country_id' => '117', 'zone_id' => '10', 'amount' => '40'), array('country_id' => '0', 'zone_id' => '0', 'amount' => '')),
		'fs_currencies' => array('EUR' => '45,5', 'USD' => ''), 'coupon_id' => '5', 'tiers' => array('50', '100', '', '', ''), 'cart_qty_min' => '2', 'cart_products' => array('40'),
		'utm_source' => 'NewsLetter, Google', 'referrer' => 'https://www.Facebook.com/x', 'visitor' => 'new', 'trigger' => 'scroll', 'trigger_value' => '40',
		'recur' => 'daily', 'recur_time' => '14:00', 'recur_skip' => array('7', '6'), 'email' => '1', 'image' => 'catalog/a.jpg', 'overlay' => '70', 'font' => 'serif'), $lookup, array('EUR', 'USD'), 'tiers');
	check("$kit validate(valid): no errors, values normalised", !$e && $o['countries'] === array(117) && $o['fs_zones'] === array(array('country_id' => 117, 'zone_id' => 10, 'amount' => '40.0000')) && $o['fs_currencies'] === array('EUR' => '45.5000')
		&& $o['tiers'] === array('50.0000', '100.0000') && $o['utm_source'] === 'newsletter,google' && $o['referrer'] === 'facebook.com' && $o['trigger_value'] === 40 && $o['recur_skip'] === '67' && $o['coupon_id'] === 5 && $o['font'] === 'serif', json_encode($e) . json_encode($o));
	list($o, $e) = $f->validate(array('countries_mode' => 'include', 'countries' => array('999'), 'fs_zones' => array(array('country_id' => '222', 'zone_id' => '10', 'amount' => '5')), 'fs_currencies' => array('GBP' => '5'),
		'coupon_id' => '6', 'tiers' => array('100', '50'), 'cart_subtotal_min' => '20', 'cart_subtotal_max' => '10', 'cart_products' => array('41'), 'utm_source' => 'a b<c', 'referrer' => 'not a host',
		'trigger' => 'delay', 'trigger_value' => '601', 'recur' => 'daily', 'recur_time' => '25:00', 'image' => '../x.jpg', 'overlay' => '95', 'font' => 'comic', 'visitor' => 'robot'), $lookup, array('EUR'), 'tiers');
	$want = array('countries', 'fs_zones', 'fs_currencies', 'coupon_id', 'tiers', 'cart_subtotal_max', 'cart_products', 'utm_source', 'referrer', 'trigger', 'recur', 'image', 'overlay', 'font', 'visitor');
	check("$kit validate(invalid): every bad field reported", !array_diff($want, array_keys($e)), implode(',', array_diff($want, array_keys($e))));
	check("$kit validate(invalid): unsafe values never kept", $o['image'] === '' && $o['font'] === '' && $o['visitor'] === 'all' && $o['coupon_id'] === 0 && $o['fs_currencies'] === array());
	list(, $e) = $f->validate(array('tiers' => array('50')), null, array(), 'tiers');
	check("$kit tiers: fewer than 2 rejected; other types drop tiers", isset($e['tiers']) && $f->validate(array('tiers' => array('50', '60')), null, array(), 'standard')[0]['tiers'] === array());
	check("$kit options(): broken JSON → defaults; stored tiers kept", $f->options('{x') == $f->defaults() && $f->options('{"tiers":["5.0000","9.0000"]}')['tiers'] === array('5.0000', '9.0000'));
	$o = $f->validate(array('countries_mode' => 'exclude', 'countries' => array('117'), 'fs_zones' => array(array('country_id' => '117', 'zone_id' => '0', 'amount' => '60'), array('country_id' => '117', 'zone_id' => '10', 'amount' => '30')), 'fs_currencies' => array('EUR' => '80')), null, array('EUR'))[0];
	check("$kit country: exclude list; unknown country (0) passes exclude, fails include", !$f->countryMatches($o, 117) && $f->countryMatches($o, 222) && $f->countryMatches($o, 0) && !$f->countryMatches(array('countries_mode' => 'include', 'countries' => array(117)), 0));
	check("$kit threshold: zone > country > currency > default", $f->threshold($o, 100, 117, 10, 'EUR') === array(30.0, '') && $f->threshold($o, 100, 117, 99, 'EUR') === array(60.0, '') && $f->threshold($o, 100, 222, 0, 'EUR') === array(80.0, 'EUR') && $f->threshold($o, 100, 222, 0, 'USD') === array(100.0, ''));
	$t = $f->tierState(array('50', '100', '150'), 120);
	check("$kit tierState: 2 reached, next = 3rd, 80 %, markers", $t['done'] === 2 && $t['next'] === 2 && $t['pct'] === 80 && $t['marks'] === array(33.33, 66.67, 100.0), json_encode($t));
	check("$kit tierState: all reached → next -1", $f->tierState(array('50', '100'), 100)['next'] === -1);
	$c = array_merge($f->defaults(), array('cart_subtotal_min' => '10.0000', 'cart_qty_max' => '3', 'cart_categories' => array(20)));
	check("$kit cartMatches: subtotal, quantity and category rules", $f->hasCartRules($c) && $f->cartMatches($c, 15, 2, array(1), array(20)) && !$f->cartMatches($c, 5, 2, array(1), array(20)) && !$f->cartMatches($c, 15, 4, array(1), array(20)) && !$f->cartMatches($c, 15, 2, array(1), array(21)) && !$f->hasCartRules($f->defaults()));
	$r = array_merge($f->defaults(), array('recur' => 'daily', 'recur_time' => '14:00', 'recur_skip' => '67'));
	$fri15 = strtotime('2026-11-13 15:00:00'); $mon10 = strtotime('2026-11-09 10:00:00');
	check("$kit nextCutoff daily: same day before, next working day after (skips Sat+Sun)", $f->nextCutoff($r, $mon10) === strtotime('2026-11-09 14:00:00') && $f->nextCutoff($r, $fri15) === strtotime('2026-11-16 14:00:00'), date('c', $f->nextCutoff($r, $fri15)));
	$w = array_merge($f->defaults(), array('recur' => 'weekly', 'recur_time' => '09:30', 'recur_day' => 5));
	check("$kit nextCutoff weekly (Friday 09:30) + attributes", $f->nextCutoff($w, $mon10) === strtotime('2026-11-13 09:30:00') && $f->recurAttr($w) === 'w|09:30|5' && $f->recurAttr($r) === 'd|14:00|67');
	check("$kit nextCutoff across the DST change (store time)", date('H:i', $f->nextCutoff($r, strtotime('2026-10-23 15:00:00'))) === '14:00');
	$css = $f->css('a{color:red}</style><script>alert(1)</script>@import url(x.css);b{background:url(javascript:alert(1))}c{width:expression(alert(1))}d{background:url("/img/x.png")}\\3c/style\\3e{}e{-moz-binding:url(x)}');
	check("$kit css(): no tags, @import, javascript:, expression, escapes, bindings; safe url kept", strpos($css, '<') === false && stripos($css, 'script>') === false && stripos($css, '@import') === false && stripos($css, 'javascript') === false && stripos($css, 'expression') === false && strpos($css, '\\') === false && stripos($css, 'binding') === false && strpos($css, 'url("/img/x.png")') !== false && strpos($css, 'a{color:red}') === 0, $css);
	$T = 1794000000; $tok = $f->token('secret', 'sess1', $T);
	check("$kit token: valid for its session after 2 s; not too early, not for another session or key, not after 2 days, not forged", $f->tokenValid($tok, 'secret', 'sess1', $T + 5) && !$f->tokenValid($tok, 'secret', 'sess1', $T + 1) && !$f->tokenValid($tok, 'secret', 'sess2', $T + 5) && !$f->tokenValid($tok, 'other', 'sess1', $T + 5) && !$f->tokenValid($tok, 'secret', 'sess1', $T + 172801) && !$f->tokenValid($T . '.' . str_repeat('0', 32), 'secret', 'sess1', $T + 5) && !$f->tokenValid($tok, '', 'sess1', $T + 5));
	check("$kit csvCell: formula injection neutralised, quotes doubled", $f->csvCell('=HYPERLINK("x")') === '"\'=HYPERLINK(""x"")"' && $f->csvCell('+1') === '"\'+1"' && $f->csvCell('a@b.lv') === '"a@b.lv"');
	check("$kit email(): valid, too long, invalid", $f->email(' a@b.lv ') === 'a@b.lv' && $f->email(str_repeat('a', 90) . '@b.lv') === '' && $f->email('a@b') === '' && $f->email('<a@b.lv>') === '');
	// Header / SMTP injection through a quoted local part that FILTER_VALIDATE_EMAIL accepts (2.4.0 review).
	$inj = array("\"a\\\nX-Injected:1\"@a.co", "\"a\\\rX:1\"@a.co", "a@a.co\r\nBcc: x@evil.co", "a@a.co\nRCPT TO:<x@evil.co>", "\"x y\"@a.co", "\"a\\\\b\"@a.co", "a\x00@a.co", "a\x7f@a.co", "a@a.co\t1");
	$all = true;
	foreach ($inj as $x) { if ($f->email($x) !== '') { $all = false; echo '   accepted: ' . json_encode($x) . "\n"; } }
	check("$kit email(): injection strings refused (quoted local part with escaped CR / LF, raw CR / LF, quotes, backslash, NUL, DEL)", $all && filter_var("\"a\\\nX-Injected:1\"@a.co", FILTER_VALIDATE_EMAIL) !== false);
	check("$kit email(): ordinary addresses still valid", $f->email('first.last+tag@sub.example.co.uk') === 'first.last+tag@sub.example.co.uk' && $f->email("o'brien@example.ie") === "o'brien@example.ie");
	check("$kit headerText(): CR / LF / control characters → space", $f->headerText("Shop\r\nBcc: x@evil.co") === 'Shop Bcc: x@evil.co' && $f->headerText("a\x00b\tc") === 'a b c' && $f->headerText('Veikals Ā') === 'Veikals Ā');
	check("$kit ipInCidr(): IPv4 / IPv6, prefix edges, other family never matches", $f->ipInCidr('104.16.0.1', '104.16.0.0/13') && $f->ipInCidr('104.23.255.255', '104.16.0.0/13') && !$f->ipInCidr('104.24.0.0', '104.16.0.0/13') && $f->ipInCidr('2a06:98c7:ffff::1', '2a06:98c0::/29') && !$f->ipInCidr('2a06:98c8::1', '2a06:98c0::/29') && !$f->ipInCidr('104.16.0.1', '2400:cb00::/32') && !$f->ipInCidr('x', '104.16.0.0/13') && !$f->ipInCidr('104.16.0.1', '104.16.0.0/33') && $f->ipInCidr('1.2.3.4', '0.0.0.0/0'));
	check("$kit isCloudflare(): published ranges (v4, v6, v4-mapped) only", $f->isCloudflare('173.245.48.10') && $f->isCloudflare('131.0.75.255') && $f->isCloudflare('2606:4700:10::6816:1') && $f->isCloudflare('::ffff:162.158.1.1') && !$f->isCloudflare('8.8.8.8') && !$f->isCloudflare('127.0.0.1') && !$f->isCloudflare('2001:db8::1') && !$f->isCloudflare('') && count(constant(get_class($f) . '::CF_IPV4')) === 15 && count(constant(get_class($f) . '::CF_IPV6')) === 7);
	$cf   = array('REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_CONNECTING_IP' => '203.0.113.7', 'HTTP_CF_IPCOUNTRY' => 'lv');
	$fake = array('REMOTE_ADDR' => '198.51.100.9', 'HTTP_CF_CONNECTING_IP' => '203.0.113.7', 'HTTP_CF_IPCOUNTRY' => 'LV');
	check("$kit clientIp(): CF-Connecting-IP only from a CloudFlare REMOTE_ADDR with the setting on", $f->clientIp($cf, true) === '203.0.113.7' && $f->clientIp($cf, false) === '162.158.10.20' && $f->clientIp($fake, true) === '198.51.100.9' && $f->clientIp(array('REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_CONNECTING_IP' => 'junk'), true) === '162.158.10.20' && $f->clientIp(array(), true) === '');
	check("$kit cfCountry(): CF-IPCountry only from CloudFlare with the setting on; XX / T1 / junk ignored", $f->cfCountry($cf, true) === 'LV' && $f->cfCountry($cf, false) === '' && $f->cfCountry($fake, true) === '' && $f->cfCountry(array('REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_IPCOUNTRY' => 'XX'), true) === '' && $f->cfCountry(array('REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_IPCOUNTRY' => 'T1'), true) === '' && $f->cfCountry(array('REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_IPCOUNTRY' => 'LVA'), true) === '');
	check("$kit imagePath(): only catalog images, no traversal / quotes", $f->imagePath('catalog/demo/a b.jpg') && !$f->imagePath('catalog/../config.php') && !$f->imagePath('catalog/x".jpg') && !$f->imagePath('image/catalog/x.jpg') && !$f->imagePath('catalog/x.php'));
	$tpl = $f->templates(); $complete = true;
	foreach ($tpl as $k => $x) { foreach (array('en', 'lv', 'ru') as $l) { if (empty($x['name'][$l]) || empty($x['text'][$l])) { $complete = false; } } }
	check("$kit templates: 10–15, each with name + text in EN / LV / RU", count($tpl) >= 10 && count($tpl) <= 15 && $complete, (string)count($tpl));
	$ex = $f->extraOf('{"tier_label":["<b>A</b>","B"],"email_button":"Jo&amp;in"}'); $errs = array();
	$cl = $f->extra(array('tier_label' => array('X', '', 'Z'), 'tier_text' => array(str_repeat('y', 400)), 'email_consent' => 'ok'), array(0, 2), $errs);
	check("$kit extraOf / extra: plain text, tier rows by index, length limit reported", $ex['tier_label'] === array('A', 'B') && $ex['email_button'] === 'Jo&in' && $cl['tier_label'] === array('X', 'Z') && isset($errs['tier_text']) && $cl['email_consent'] === 'ok');
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
