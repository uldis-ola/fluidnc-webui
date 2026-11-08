<?php
/**
 * Announcement Bar Pro — OpenCart 2.0.x–2.3.x adapter.
 * Everything that differs between OpenCart 2.0, 2.1, 2.2, 2.3 and the OpenCart 3 edition lives here, so the
 * controllers and models stay as close to the OpenCart 3 edition as possible:
 *  - PHP 5.6 polyfills for the shared feature library (intdiv, random_bytes),
 *  - routes, permissions, token, model names and setting keys per OpenCart version,
 *  - the .tpl renderer (templates generated from the OpenCart 3 Twig templates by tools/twig2tpl.py) and its helpers,
 *  - OpenCart 2.0/2.1 view hook (those versions have no view events; install.xml calls AbpOc2::view()),
 *  - e-mail (2.0.0.0 keeps the mail settings in one array; 2.0.2+ has separate keys; OpenCart 3 uses "mail engine").
 * @version 2.4.2
 */
defined('VERSION') || exit;

if (!function_exists('intdiv')) {
	/** PHP < 7.0 */
	function intdiv($dividend, $divisor) {
		if ((int)$divisor === 0) {
			throw new \Exception('Division by zero');
		}

		return (int)(($dividend - $dividend % $divisor) / $divisor);
	}
}

if (!function_exists('random_bytes')) {
	/** PHP < 7.0: cryptographically secure bytes or an exception — never a weak fallback. */
	function random_bytes($length) {
		$length = max(1, (int)$length);

		if (function_exists('openssl_random_pseudo_bytes')) {
			$strong = false;
			$bytes  = openssl_random_pseudo_bytes($length, $strong);

			if (is_string($bytes) && strlen($bytes) === $length && $strong) {
				return $bytes;
			}
		}

		if (function_exists('mcrypt_create_iv')) {
			$bytes = @mcrypt_create_iv($length, defined('MCRYPT_DEV_URANDOM') ? MCRYPT_DEV_URANDOM : 1);

			if (is_string($bytes) && strlen($bytes) === $length) {
				return $bytes;
			}
		}

		if (@is_readable('/dev/urandom') && ($fp = @fopen('/dev/urandom', 'rb'))) {
			$bytes = fread($fp, $length);
			fclose($fp);

			if (is_string($bytes) && strlen($bytes) === $length) {
				return $bytes;
			}
		}

		throw new \Exception('Announcement Bar Pro: no secure random source (enable the OpenSSL extension).');
	}
}

if (!function_exists('hash_equals')) {
	/** PHP < 5.6 (not supported by the module, kept for safety) */
	function hash_equals($known, $user) {
		if (!is_string($known) || !is_string($user) || strlen($known) !== strlen($user)) {
			return false;
		}

		$diff = 0;
		for ($i = 0, $n = strlen($known); $i < $n; $i++) {
			$diff |= ord($known[$i]) ^ ord($user[$i]);
		}

		return $diff === 0;
	}
}

class AbpOc2 {
	/** Route the module's model and language files use in every 2.x version. */
	const MROUTE = 'extension/module/announcement_bar_pro';

	/* ------------------------------------------------------------------ *
	 * Versions, routes, names
	 * ------------------------------------------------------------------ */

	/** OpenCart 2.3: extension/module/* controllers and the extension/extension page. */
	public static function is23() {
		return version_compare(VERSION, '2.3.0.0', '>=');
	}

	/** OpenCart 2.2+: view events. */
	public static function hasViewEvents() {
		return version_compare(VERSION, '2.2.0.0', '>=');
	}

	/** Admin route of the module page (permissions and links use it). */
	public static function adminRoute() {
		return self::is23() ? 'extension/module/announcement_bar_pro' : 'module/announcement_bar_pro';
	}

	/** Extensions › Modules: route + extra arguments. */
	public static function extensionsRoute() {
		return self::is23() ? array('extension/extension', '&type=module') : array('extension/module', '');
	}

	/** Route whose "modify" permission OpenCart checked before calling install() / uninstall(). */
	public static function installerRoute() {
		return self::is23() ? 'extension/extension/module' : 'extension/module';
	}

	/** Customer groups: sale/customer_group in 2.0, customer/customer_group since 2.1. */
	public static function customerGroupModel() {
		return version_compare(VERSION, '2.1.0.0', '>=') ? 'customer/customer_group' : 'sale/customer_group';
	}

	/** Language flag of the admin language switch. */
	public static function flag(array $language) {
		if (version_compare(VERSION, '2.2.0.0', '>=')) {
			return 'language/' . rawurlencode((string)$language['code']) . '/' . rawurlencode((string)$language['code']) . '.png';
		}

		return 'view/image/flags/' . rawurlencode(isset($language['image']) ? (string)$language['image'] : '');
	}

	/** "Free shipping" method: setting keys free_* (OpenCart 3: shipping_free_*). */
	public static function freeShipping($config) {
		return array('status' => (bool)$config->get('free_status'), 'total' => (float)$config->get('free_total'));
	}

	/** Mail protocol ('' = mail is not set up). */
	public static function mailProtocol($config) {
		$protocol = $config->get('config_mail_protocol');

		if (($protocol === null || $protocol === '') && is_array($config->get('config_mail'))) {
			$array    = $config->get('config_mail'); // 2.0.0.0
			$protocol = isset($array['protocol']) ? $array['protocol'] : '';
		}

		return is_string($protocol) ? $protocol : '';
	}

	/** A configured Mail object (2.0.0.0: one config_mail array; 2.0.2+: separate keys). */
	public static function mail($config) {
		$array = $config->get('config_mail');
		$mail  = is_array($array) ? new Mail($array) : new Mail();

		$map = array(
			'protocol'      => 'config_mail_protocol',
			'parameter'     => 'config_mail_parameter',
			'smtp_hostname' => 'config_mail_smtp_hostname',
			'smtp_username' => 'config_mail_smtp_username',
			'smtp_password' => 'config_mail_smtp_password',
			'smtp_port'     => 'config_mail_smtp_port',
			'smtp_timeout'  => 'config_mail_smtp_timeout'
		);

		foreach ($map as $property => $key) {
			$value = $config->get($key);

			if ($value !== null && $value !== '') {
				$mail->$property = $property === 'smtp_password' ? html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8') : $value;
			}
		}

		return $mail;
	}

	/** Storefront theme directory (2.2+: config_theme / theme_default_directory; 2.0/2.1: config_template). */
	public static function themeDirectory($config) {
		if (version_compare(VERSION, '2.2.0.0', '>=')) {
			$theme = (string)$config->get('config_theme');

			return $theme === 'theme_default' || $theme === '' ? (string)$config->get('theme_default_directory') : $theme;
		}

		return (string)$config->get('config_template');
	}

	/** Catalog template: the theme's own copy when it has one, else the default one. */
	public static function catalogTemplate($config, $name) {
		$theme = preg_replace('/[^a-zA-Z0-9_\-]/', '', self::themeDirectory($config));
		$file  = 'template/extension/module/' . $name . '.tpl';

		if ($theme !== '' && is_file(DIR_TEMPLATE . $theme . '/' . $file)) {
			return DIR_TEMPLATE . $theme . '/' . $file;
		}

		return DIR_TEMPLATE . 'default/' . $file;
	}

	/* ------------------------------------------------------------------ *
	 * Templates
	 * ------------------------------------------------------------------ */

	/** Renders a .tpl file with $data (and $_context = $data, as Twig's _context). */
	public static function render($file, array $data) {
		$_context = $data;
		extract($data, EXTR_SKIP);

		ob_start();

		try {
			require($file);
		} catch (\Exception $e) {
			ob_end_clean();
			throw $e;
		}

		return ob_get_clean();
	}

	/** Twig "e" (html) */
	public static function e($value) {
		return htmlspecialchars(is_bool($value) ? ($value ? '1' : '') : (string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/** Twig "e('js')": everything except [a-zA-Z0-9,._] as \xHH / \uHHHH. */
	public static function escapeJs($value) {
		return preg_replace_callback('/[^a-zA-Z0-9,\._]/u', function ($m) {
			$char = $m[0];

			if (strlen($char) === 1) {
				return sprintf('\\x%02X', ord($char));
			}

			// UTF-8 → code point → UTF-16 units (no mbstring needed)
			$n  = strlen($char);
			$cp = ord($char[0]) & (0xFF >> ($n + 1));
			for ($i = 1; $i < $n; $i++) {
				$cp = ($cp << 6) | (ord($char[$i]) & 0x3F);
			}

			if ($cp < 0x10000) {
				return sprintf('\u%04X', $cp);
			}

			$cp -= 0x10000;
			$out = sprintf('\u%04X\u%04X', 0xD800 | ($cp >> 10), 0xDC00 | ($cp & 0x3FF));

			return $out;
		}, (string)$value);
	}

	/** Twig "in" */
	public static function in($needle, $haystack) {
		if (is_array($haystack)) {
			return in_array($needle, $haystack);
		}

		return is_scalar($haystack) && (string)$needle !== '' && strpos((string)$haystack, (string)$needle) !== false;
	}

	/** Twig "length" */
	public static function length($value) {
		if (is_array($value) || $value instanceof \Countable) {
			return count($value);
		}

		return function_exists('mb_strlen') ? mb_strlen((string)$value, 'UTF-8') : (int)preg_match_all('/./us', (string)$value);
	}

	/** Twig "is empty" for non-variables */
	public static function isEmpty($value) {
		return $value === null || $value === false || $value === '' || $value === array();
	}

	/* ------------------------------------------------------------------ *
	 * OpenCart 2.0 / 2.1 view hook (called from system/engine/loader.php, see install.xml)
	 * ------------------------------------------------------------------ */

	/**
	 * Same handlers, in the same order, as the OpenCart 3 / 2.2+ view events:
	 * header → bars, footer → fallback marker, every view → fallback resolution, cart / product / mini-cart → inline boxes.
	 * Never breaks the page: any failure returns the original output.
	 */
	public static function view($registry, $template, $output) {
		if (defined('DIR_CATALOG') || self::hasViewEvents() || !is_string($output) || !is_string($template)) {
			return $output; // admin, or OpenCart 2.2+ (events do this)
		}

		$file = DIR_APPLICATION . 'controller/extension/module/announcement_bar_pro.php';

		if (!is_file($file)) {
			return $output;
		}

		$route    = preg_replace('/\.tpl$/', '', str_replace('\\', '/', $template));
		$handlers = array();

		if (preg_match('#(^|/)common/header$#', $route)) {
			$handlers[] = 'injectBar';
		}

		if (preg_match('#(^|/)common/footer$#', $route)) {
			$handlers[] = 'injectBarFooter';
		}

		$handlers[] = 'resolveFallback';

		if (preg_match('#(^|/)checkout/cart$#', $route)) {
			$handlers[] = 'injectCart';
		} elseif (preg_match('#(^|/)product/product$#', $route)) {
			$handlers[] = 'injectProduct';
		} elseif (preg_match('#(^|/)common/cart$#', $route)) {
			$handlers[] = 'injectMiniCart';
		}

		$original = $output;

		try {
			require_once($file);

			foreach ($handlers as $method) {
				$controller = new ControllerExtensionModuleAnnouncementBarPro($registry);
				$args       = array();
				$controller->$method($route, $args, $output);
			}
		} catch (\Exception $e) {
			return $original;
		} catch (\Throwable $e) { // PHP 7+ errors
			return $original;
		}

		return is_string($output) ? $output : $original;
	}
}
