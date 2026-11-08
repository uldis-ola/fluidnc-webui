<?php
/**
 * Announcement Bar Pro — OpenCart 2.x adapter test (no database). Runs itself once per OpenCart version
 * (2.0.0.0, 2.0.3.1, 2.1.0.2, 2.2.0.0, 2.3.0.2) in a child process, because VERSION is a constant.
 * Checks: routes / permissions / models per version, event registration (2.2+ only, theme wildcard), the 2.0/2.1
 * loader hook dispatch, PHP 5.6 polyfills, Twig helpers of the .tpl templates, language stubs, install.xml.
 * Run: php tests/oc2_test.php   (exit code 0 = all passed)
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Europe/Riga');
$root = dirname(__DIR__);

if (!isset($argv[1])) {
	$fail = 0;
	foreach (array('2.0.0.0', '2.0.3.1', '2.1.0.2', '2.2.0.0', '2.3.0.2') as $v) {
		$out = array();
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $v, $out, $code);
		echo implode("\n", $out) . "\n";
		$fail += $code ? 1 : 0;
	}
	echo $fail ? "\nFAILED for $fail version(s)\n" : "\nall versions passed\n";
	exit($fail ? 1 : 0);
}

define('VERSION', $argv[1]);
define('DB_PREFIX', 'oc_');
define('DIR_SYSTEM', $root . '/upload/system/');
define('DIR_APPLICATION', $root . '/upload/catalog/');
define('DIR_TEMPLATE', $root . '/upload/catalog/view/theme/');
require DIR_SYSTEM . 'library/announcementbarpro/oc2.php';

$pass = 0;
$fail = 0;
function check($name, $cond, $extra = '') {
	global $pass, $fail;
	if ($cond) { $pass++; echo 'PASS [' . VERSION . "] $name\n"; } else { $fail++; echo 'FAIL [' . VERSION . "] $name" . ($extra !== '' ? " — $extra" : '') . "\n"; }
}
$v23 = version_compare(VERSION, '2.3', '>=');
$v22 = version_compare(VERSION, '2.2', '>=');

// Routes, permissions, models
check('admin route', AbpOc2::adminRoute() === ($v23 ? 'extension/module/announcement_bar_pro' : 'module/announcement_bar_pro'));
check('extensions page', AbpOc2::extensionsRoute() === ($v23 ? array('extension/extension', '&type=module') : array('extension/module', '')));
check('installer permission route', AbpOc2::installerRoute() === ($v23 ? 'extension/extension/module' : 'extension/module'));
check('customer group model', AbpOc2::customerGroupModel() === (version_compare(VERSION, '2.1', '>=') ? 'customer/customer_group' : 'sale/customer_group'));
check('language flag path', AbpOc2::flag(array('code' => $v22 ? 'en-gb' : 'en', 'image' => 'gb.png')) === ($v22 ? 'language/en-gb/en-gb.png' : 'view/image/flags/gb.png'));
check('view events only on 2.2+', AbpOc2::hasViewEvents() === $v22);

// Config-dependent helpers
class Cfg { public $d; function __construct($d) { $this->d = $d; } function get($k) { return isset($this->d[$k]) ? $this->d[$k] : null; } }
check('free shipping keys free_*', AbpOc2::freeShipping(new Cfg(array('free_status' => '1', 'free_total' => '120'))) === array('status' => true, 'total' => 120.0));
check('mail protocol: 2.0.2+ key, 2.0.0.0 array, none', AbpOc2::mailProtocol(new Cfg(array('config_mail_protocol' => 'smtp'))) === 'smtp' && AbpOc2::mailProtocol(new Cfg(array('config_mail' => array('protocol' => 'mail')))) === 'mail' && AbpOc2::mailProtocol(new Cfg(array())) === '');
check('theme directory', AbpOc2::themeDirectory(new Cfg(array('config_theme' => 'theme_default', 'theme_default_directory' => 'mytheme', 'config_template' => 'old'))) === ($v22 ? 'mytheme' : 'old'));
check('catalog template falls back to default', AbpOc2::catalogTemplate(new Cfg(array('config_template' => 'nope', 'config_theme' => 'nope')), 'announcement_bar_pro') === DIR_TEMPLATE . 'default/template/extension/module/announcement_bar_pro.tpl');

// Polyfills (PHP 5.6) — and the real functions on PHP 7
check('random_bytes: 32 bytes, differs each call', strlen(random_bytes(32)) === 32 && random_bytes(16) !== random_bytes(16));
check('intdiv', intdiv(7, 2) === 3 && intdiv(-7, 2) === -3 && intdiv(255, 8) === 31);
check('hash_equals', hash_equals('abc', 'abc') && !hash_equals('abc', 'abd'));

// Twig helpers
check('e(): html escaping like Twig', AbpOc2::e('<a href="x">\'&') === '&lt;a href=&quot;x&quot;&gt;&#039;&amp;' && AbpOc2::e(null) === '' && AbpOc2::e(true) === '1' && AbpOc2::e(false) === '');
check("escapeJs(): like Twig e('js')", AbpOc2::escapeJs("en-gb") === 'en\\x2Dgb' && AbpOc2::escapeJs("a'b") === 'a\\x27b' && AbpOc2::escapeJs('ā') === '\\u0101' && AbpOc2::escapeJs('a.b_c,1') === 'a.b_c,1');
check('in(): arrays and strings', AbpOc2::in(2, array(1, 2)) && !AbpOc2::in(3, array(1, 2)) && AbpOc2::in('b', 'abc') && !AbpOc2::in('', 'abc'));
check('length()', AbpOc2::length(array(1, 2, 3)) === 3 && AbpOc2::length('āb') === 2);

// Templates render without notices on empty data (Twig: undefined = null)
$errors = array();
set_error_handler(function ($no, $str, $file, $line) use (&$errors) { $errors[] = "$str ($file:$line)"; return true; });
foreach (array('admin/view/template/extension/module/announcement_bar_pro.tpl', 'admin/view/template/extension/module/announcement_bar_pro_form.tpl',
	'catalog/view/theme/default/template/extension/module/announcement_bar_pro.tpl', 'catalog/view/theme/default/template/extension/module/announcement_bar_pro_page.tpl') as $t) {
	$html = AbpOc2::render($root . '/upload/' . $t, array());
	check("template renders with no data, no PHP notice: $t", is_string($html) && !$errors, implode(' | ', array_slice($errors, 0, 3)));
	$errors = array();
}
$html = AbpOc2::render($root . '/upload/catalog/view/theme/default/template/extension/module/announcement_bar_pro.tpl', array(
	'containers' => array(array('id' => 'abp-bar', 'inline' => false, 'placement' => 'top', 'mode' => 'rotate', 'sticky' => true, 'dismissible' => true, 'arrows' => false, 'pause' => false, 'autohide' => 0,
		'items' => array(array('id' => 5, 'key' => 'k', 'style' => 'solid', 'bg_color' => '#222222', 'text_color' => '#ffffff', 'device' => 'all', 'image' => '', 'variants' => array(array('name' => 'a', 'wrap_link' => false, 'txt' => 'Hello <b>x</b>', 'link_text' => '')), 'link' => '', 'icon' => '', 'coupon' => 'ABC"1', 'copy_aria' => 'Copy')))),
	'transition' => 'fade', 'label' => 'Notice', 'base' => 'catalog/view/javascript/announcement_bar_pro/', 'version' => '2.4.2', 'pre' => true, 'assets' => true, 'copy' => 'Copy'));
restore_error_handler();
check('bar template: markup, escaping, assets once', strpos($html, 'id="abp-bar"') !== false && strpos($html, 'abp-item abp-active') !== false && strpos($html, 'Hello <b>x</b>') !== false && strpos($html, 'data-code="ABC&quot;1"') !== false
	&& substr_count($html, 'announcement_bar_pro.js?v=2.4.2') === 1 && !$errors, implode(' | ', $errors));

// Admin model: events registered on 2.2+ only, with the theme wildcard; removed by code
class FakeEvent { public $added = array(), $deleted = array(); function addEvent($c, $t, $a) { $this->added[] = array($c, $t, $a); } function deleteEvent($c) { $this->deleted[] = $c; } }
class FakeExt { function getInstalled($type) { return array('announcement_bar_pro'); } }
class FakeLoader { public $reg; function model($r) { $k = 'model_' . str_replace('/', '_', $r); if (!isset($this->reg->data[$k])) { $this->reg->data[$k] = $r === 'extension/event' ? new FakeEvent() : new FakeExt(); } } }
class FakeReg { public $data = array(); }
class Model { protected $registry; function __construct($r) { $this->registry = $r; } function __get($k) { return isset($this->registry->data[$k]) ? $this->registry->data[$k] : null; } }
require $root . '/upload/admin/model/extension/module/announcement_bar_pro.php';
$reg = new FakeReg(); $reg->data['load'] = new FakeLoader(); $reg->data['load']->reg = $reg;
$m = new ModelExtensionModuleAnnouncementBarPro($reg);
$m->registerEvents();
$ev = isset($reg->data['model_extension_event']) ? $reg->data['model_extension_event'] : null;
if ($v22) {
	$triggers = $ev ? array_map(function ($e) { return $e[1]; }, $ev->added) : array();
	check('2.2+: 6 events after deleting the old ones, every trigger catalog/view/*/…', $ev && count($ev->added) === 6 && $ev->deleted === array('announcement_bar_pro') && count(preg_grep('#^catalog/view/\*/#', $triggers)) === 6 && in_array('catalog/view/*/common/header/after', $triggers, true), implode(',', $triggers));
	check('2.2+: event actions are extension/module/announcement_bar_pro/*', count(preg_grep('#^extension/module/announcement_bar_pro/(injectBar|injectBarFooter|resolveFallback|injectCart|injectProduct|injectMiniCart)$#', array_map(function ($e) { return $e[2]; }, $ev->added))) === 6);
	// Trigger strings as OpenCart 2.2/2.3 match them (Event::trigger: prefix regex, "*" = ".*") against the themed route.
	$match = function ($trigger, $event) { $trigger = substr($trigger, strpos($trigger, '/') + 1); return (bool)preg_match('/^' . str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($trigger, '/')) . '/', $event); };
	check('2.2+: header trigger matches view/default/template/common/header/after, not the footer', $match('catalog/view/*/common/header/after', 'view/default/template/common/header/after') && !$match('catalog/view/*/common/header/after', 'view/default/template/common/footer/after'));
	check('2.2+: cart trigger matches the themed checkout/cart view only', $match('catalog/view/*/checkout/cart/after', 'view/mytheme/template/checkout/cart/after') && !$match('catalog/view/*/checkout/cart/after', 'view/default/template/common/cart/after'));
} else {
	check('2.0/2.1: no event registered (loader hook instead)', $ev === null);
}

// 2.0/2.1 loader hook: dispatch by template, never in admin / on 2.2+, original output on failure
if (!$v22) {
	$src = file_get_contents(DIR_SYSTEM . 'library/announcementbarpro/oc2.php');
	check('2.0/2.1: hook maps header / footer / cart / product / mini-cart and runs the fallback on every view', strpos($src, "'injectBar'") !== false && strpos($src, "'resolveFallback'") !== false && strpos($src, "'injectMiniCart'") !== false);
	check('2.0/2.1: hook leaves non-string output alone', AbpOc2::view(null, 'default/template/common/home.tpl', null) === null);
}
$xml = simplexml_load_file($root . '/install.xml');
check('install.xml: code, version 2.4.2, loader operation with error="skip" and the version guard', (string)$xml->code === 'announcement_bar_pro' && (string)$xml->version === '2.4.2'
	&& (string)$xml->file['path'] === 'system/engine/loader.php' && (string)$xml->file->operation['error'] === 'skip' && strpos((string)$xml->file->operation->add, "version_compare(VERSION, '2.2.0.0', '<')") !== false && strpos((string)$xml->file->operation->add, "!defined('DIR_CATALOG')") !== false);

// Language stubs: every folder points to the shared texts; every text file defines the same keys
foreach (array('english' => 'en', 'en-gb' => 'en', 'latvian' => 'lv', 'lv-lv' => 'lv', 'russian' => 'ru', 'ru-ru' => 'ru') as $dir => $code) {
	foreach (array('admin/language/' . $dir . '/extension/module', 'admin/language/' . $dir . '/module', 'catalog/language/' . $dir . '/extension/module') as $path) {
		$stub = @file_get_contents($root . '/upload/' . $path . '/announcement_bar_pro.php');
		check("language stub $path → $code", $stub !== false && strpos($stub, "language/$code/" . (strpos($path, 'admin') === 0 ? 'admin' : 'catalog') . '.php') !== false);
	}
}
foreach (array('admin', 'catalog') as $side) {
	$keys = array();
	foreach (array('en', 'lv', 'ru') as $code) { $_ = array(); require DIR_SYSTEM . "library/announcementbarpro/language/$code/$side.php"; $keys[$code] = array_keys($_); sort($keys[$code]); }
	check("$side language files en / lv / ru have the same keys", $keys['en'] === $keys['lv'] && $keys['en'] === $keys['ru'], implode(',', array_diff($keys['en'], $keys['lv'])) . ' / ' . implode(',', array_diff($keys['en'], $keys['ru'])));
}

echo "[" . VERSION . "] $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
