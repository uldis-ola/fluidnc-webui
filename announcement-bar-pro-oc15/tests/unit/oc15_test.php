<?php
/**
 * Announcement Bar Pro — checks of the OpenCart 1.5 edition only (included by schema15_test.php, which defines check()).
 * PHP 5.6 polyfills, template helpers, image path mapping, the vQmod file, generated templates, 1.5 API use.
 * Run on its own (`php tests/unit/oc15_test.php`) it runs the whole suite through schema15_test.php.
 */
if (!function_exists('check')) {
	require __DIR__ . '/schema15_test.php'; // defines check() / make(), loads compat.php, includes this file, exits
	exit(0);
}
$k15 = $root . '/announcement-bar-pro-oc15/upload/';
require_once $k15 . 'system/library/announcementbarpro/compat.php';

// 1. compat.php: random_bytes() / intdiv() exist and behave (on PHP 5.6 the polyfills, on 7.x the built-ins).
$rb = random_bytes(32);
check('OC15 compat: random_bytes(32) gives 32 bytes, two calls differ', strlen($rb) === 32 && $rb !== random_bytes(32));
check('OC15 compat: intdiv() like PHP 7', intdiv(7, 2) === 3 && intdiv(-7, 2) === -3 && intdiv(224, 8) === 28);
$threw = false;
try { random_bytes(0); } catch (\Exception $e) { $threw = true; } catch (\Throwable $e) { $threw = true; }
check('OC15 compat: random_bytes(0) throws', $threw);

// 2. Template helpers (Twig semantics in PHP templates).
require $k15 . 'system/library/announcementbarpro/tpl.php';
$arr = array('a' => array('b' => 'x<y'));
check('OC15 tpl: abp15_v() — value, missing variable / key is null without a notice', abp15_v($arr['a']['b']) === 'x<y' && abp15_v($arr['zz']['q']) === null && abp15_v($undefined_var) === null);
check('OC15 tpl: abp15_e() escapes like Twig e', abp15_e('<a href="x">\'&') === '&lt;a href=&quot;x&quot;&gt;&#039;&amp;' && abp15_e(null) === '' && abp15_e(true) === '1' && abp15_e(array(1)) === '');
check('OC15 tpl: abp15_url() / abp15_js()', abp15_url('a b/é') === 'a%20b%2F%C3%A9' && abp15_js("it's\n") === 'it\x27s\x0A' && abp15_js('Ā') === '\u0100' && abp15_js('a.b,c_1') === 'a.b,c_1');
check('OC15 tpl: abp15_len() / abp15_in()', abp15_len(array(1, 2)) === 2 && abp15_len('āb') === 2 && abp15_len(null) === 0 && abp15_in(2, array('1', '2')) && !abp15_in(3, array(1)) && abp15_in('ab', 'xaby') && !abp15_in('', 'x'));

// 3. Generated templates: valid PHP, no Twig left, no raw OpenCart 3 names.
foreach (array('admin/view/template/module/announcement_bar_pro.tpl', 'admin/view/template/module/announcement_bar_pro_form.tpl', 'catalog/view/theme/default/template/module/announcement_bar_pro.tpl', 'catalog/view/theme/default/template/module/announcement_bar_pro_page.tpl') as $tpl) {
	$src = file_get_contents($k15 . $tpl);
	check("OC15 template $tpl: no Twig tags left, no OpenCart 3 names", !preg_match('/\{\{|\{%|\{#/', $src) && strpos($src, 'module_announcement_bar_pro_') === false && strpos($src, 'user_token') === false && strpos($src, 'column_left }}') === false);
}
$form = file_get_contents($k15 . 'admin/view/template/module/announcement_bar_pro_form.tpl');
check('OC15 form: 1.5 image manager, flags, timepicker, scoped styles', strpos($form, 'data-abp15-image="input-image"') !== false && strpos($form, 'view/image/flags/') !== false && strpos($form, 'jquery-ui-timepicker-addon.js') !== false && strpos($form, 'abp-admin abp15') !== false && strpos($form, 'data-toggle="image"') === false);

// 4. Render the storefront template with a minimal bar: real HTML, escaped values, assets once.
$file = $k15 . 'catalog/view/theme/default/template/module/announcement_bar_pro.tpl';
$render = function (array $data) use ($file) { extract($data); ob_start(); require $file; return ob_get_clean(); };
$item = array('id' => 3, 'key' => 'abcdef012345', 'variants' => array(array('name' => 'a', 'txt' => 'Hello <b>world</b>', 'link_text' => 'Go"', 'wrap_link' => false)), 'ab' => false, 'device' => 'all', 'bg_color' => '#222222', 'text_color' => '#ffffff',
	'style' => 'solid', 'btn_style' => 'filled', 'icon' => 'fa fa-truck', 'link' => 'https://example.com/?a=1&b=2', 'new_tab' => true, 'coupon' => 'SALE10', 'copy_aria' => 'Copy SALE10', 'fs' => false, 'done' => false, 'pct' => 0, 'progress' => false,
	'steps' => array(), 'k' => 0, 'wd' => '', 't1' => '', 't2' => '', 'off' => false, 'end' => 0, 'font' => '', 'image' => '', 'utm' => '', 'vis' => '', 'trig' => '', 'cc' => false, 'coff' => false, 'email' => null);
$c = array('id' => 'abp-bar', 'inline' => false, 'placement' => 'top', 'mode' => 'rotate', 'items' => array($item), 'sticky' => false, 'arrows' => false, 'dismissible' => true, 'pause' => false, 'autohide' => 0);
$html = $render(array('containers' => array($c), 'assets' => true, 'pre' => true, 'fa' => 'catalog/view/javascript/announcement_bar_pro/fa/css/font-awesome.min.css?v=4.7.0', 'base' => 'catalog/view/javascript/announcement_bar_pro/', 'version' => '2.4.1', 'custom_css' => '', 'fallback' => false,
	'transition' => 'fade', 'label' => 'Announcements', 'close' => 'Close', 'dismiss_days' => 1, 'interval' => 5, 'speed' => 60, 'now' => 1, 'tz' => 0, 'color_mode' => 'custom', 'days' => 'd', 'track' => 'index.php?route=module/announcement_bar_pro/track', 'cart' => '', 'max' => 0,
	'ga4' => true, 'consent' => false, 'font' => '', 'copy' => 'Copy', 'copied' => 'Copied', 'prev' => 'Previous', 'next' => 'Next', 'show' => 'Show', 'pause_label' => 'Pause', 'play_label' => 'Play'));
check('OC15 render: bar with id, item, raw safe text, escaped button text and link, coupon, icon', strpos($html, 'id="abp-bar"') !== false && strpos($html, 'data-id="3"') !== false && strpos($html, 'Hello <b>world</b>') !== false && strpos($html, 'Go&quot;') !== false && strpos($html, 'href="https://example.com/?a=1&amp;b=2"') !== false && strpos($html, 'SALE10') !== false && strpos($html, 'fa fa-truck') !== false, $html);
check('OC15 render: assets (shared CSS + JS, OpenCart 1.5 adapter CSS + JS, Font Awesome) once', substr_count($html, 'announcement_bar_pro.css?v=2.4.1') === 1 && substr_count($html, 'announcement_bar_pro.js?v=2.4.1') === 1 && substr_count($html, 'announcement_bar_pro_oc15.js') === 1 && substr_count($html, 'font-awesome.min.css') === 1 && strpos($html, '<script>') === false);
$html2 = $render(array('containers' => array($c), 'assets' => false, 'pre' => false, 'fa' => '', 'base' => 'x/', 'version' => '2.4.1', 'custom_css' => '', 'fallback' => false));
check('OC15 render: no assets → no CSS / JS tags, no PHP notices for the missing values', strpos($html2, '<link') === false && strpos($html2, '<script') === false && strpos($html2, 'Notice') === false);

// 5. Image path mapping (OpenCart 1.5 image/data/ ↔ the shared "catalog/" rule).
$dir = sys_get_temp_dir() . '/abp15img' . mt_rand();
@mkdir($dir . '/data/demo', 0777, true);
file_put_contents($dir . '/data/demo/a.jpg', 'x');
if (!defined('DIR_IMAGE')) { define('DIR_IMAGE', $dir . '/'); }
list($m15) = make('ModelModuleAnnouncementBarPro');
$real = DIR_IMAGE === $dir . '/';
check('OC15 images: data/x → catalog/x (stored), back to data/x; only existing files; no traversal', $m15->imageToStored('data/demo/a.jpg') === 'catalog/demo/a.jpg' && $m15->imageToStored('catalog/b.png') === 'catalog/b.png' && $m15->imageFile('catalog/demo/a.jpg', true) === 'data/demo/a.jpg'
	&& (!$real || ($m15->imageFile('catalog/demo/a.jpg') === 'data/demo/a.jpg' && $m15->imageFile('catalog/demo/none.jpg') === '' && $m15->imageFile('catalog/../data/demo/a.jpg') === '')));
@unlink($dir . '/data/demo/a.jpg'); @rmdir($dir . '/data/demo'); @rmdir($dir . '/data'); @rmdir($dir);

// 6. vQmod file: well-formed, every hook guarded, each search line exists in OpenCart 1.5.6.4 / 1.5.5.1 when present.
$xml = simplexml_load_file($k15 . 'vqmod/xml/announcement_bar_pro.xml');
check('OC15 vQmod: well-formed, 6 files, every operation error="skip"', $xml !== false && count($xml->file) === 6 && count($xml->xpath('//operation[@error="skip"]')) === 6);
$guards = true;
foreach ($xml->xpath('//operation/add') as $add) {
	if (strpos((string)$add, 'ControllerModuleAnnouncementBarPro::') !== false && strpos((string)$add, 'is_file(') === false && strpos((string)$add, 'method_exists(') === false) {
		$guards = false;
	}
}
check('OC15 vQmod: every hook checks that the module file / class exists', $guards);
foreach (array('1.5.6.4', '1.5.5.1') as $v) {
	$core = 'C:/Users/Admin/abp-testenv/src/oc-' . $v . '/opencart-' . $v . '/upload/';
	if (!is_dir($core)) {
		continue;
	}
	$found = true;
	foreach ($xml->file as $f) {
		$code = file_get_contents($core . (string)$f['name']);
		if (strpos($code, trim((string)$f->operation->search)) === false) {
			$found = false;
			echo '   missing in ' . $v . ': ' . $f['name'] . "\n";
		}
	}
	check("OC15 vQmod: every search line exists in OpenCart $v", $found);
}

// 7. OpenCart 1.5 API only (no OpenCart 2+ calls) in the PHP files of the kit.
$bad = array();
foreach (array('admin/controller/module/announcement_bar_pro.php', 'admin/model/module/announcement_bar_pro.php', 'catalog/controller/module/announcement_bar_pro.php', 'catalog/model/module/announcement_bar_pro.php') as $php) {
	$src = file_get_contents($k15 . $php);
	foreach (array('load->view(', 'load->controller(', 'user_token', 'setting/event', 'marketplace/', 'getGroupId()', "session->getId()", 'config_mail_engine', 'shipping_free_', '->editSettingValue(') as $needle) {
		if (strpos($src, $needle) !== false) {
			$bad[] = $php . ': ' . $needle;
		}
	}
}
check('OC15 source: no OpenCart 2 / 3 API calls left', !$bad, implode(', ', $bad));
$langs = true;
foreach (array('english', 'latvian', 'russian') as $l) {
	foreach (array('admin', 'catalog') as $side) {
		$_ = array();
		include $k15 . $side . '/language/' . $l . '/module/announcement_bar_pro.php';
		$keys[$side][$l] = array_keys($_);
	}
}
foreach (array('admin', 'catalog') as $side) {
	$langs = $langs && !array_diff($keys[$side]['english'], $keys[$side]['latvian']) && !array_diff($keys[$side]['english'], $keys[$side]['russian']);
}
check('OC15 languages: Latvian and Russian have every English key (admin + catalog)', $langs);
