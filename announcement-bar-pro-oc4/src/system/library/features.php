<?php
/**
 * Announcement Bar Pro (OpenCart 4) — 2.4.0 feature helpers: message options (targeting, goals, triggers, countdown,
 * look), free-shipping overrides, cart conditions, recurring cut-off times, custom CSS, templates and form tokens.
 * Pure PHP without database access, shared by admin and catalog.
 * @version 2.4.0
 */
namespace Opencart\System\Library\Extension\AnnouncementBarPro;
\defined('VERSION') || exit; // not callable directly

// ---- shared body: everything below this line is byte-identical in the OpenCart 3 and OpenCart 4 packages (build.sh checks it) ----
class Features {
	const PLACEMENTS = array('', 'top', 'bottom', 'cart', 'product', 'minicart');
	const TRIGGERS   = array('none', 'delay', 'scroll', 'exit');
	const VISITORS   = array('all', 'new', 'returning');
	const RECUR      = array('', 'daily', 'weekly');
	const FONTS      = array('', 'system', 'humanist', 'geometric', 'serif', 'mono', 'rounded');
	const TYPES      = array('standard', 'free_shipping', 'tiers');
	const MAX_TIERS  = 5;
	const MAX_ZONES  = 50;
	const MAX_IDS    = 200;
	const MAX_TOKENS = 20;
	const MAX_CSS    = 10000;
	const MAX_EXTRA  = 160;   // characters of a tier label / form text
	const MAX_TIER_TEXT = 300;

	/** Per-language texts kept in the description's "extra" JSON column. */
	const EXTRA_TEXTS = 'email_placeholder,email_button,email_consent,email_success';

	/**
	 * System font stacks only (no web fonts, nothing is downloaded). '' = the theme's font.
	 */
	public static function fontStack($font) {
		$stacks = array(
			'system'    => 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif',
			'humanist'  => 'Seravek,"Gill Sans Nova",Ubuntu,Calibri,"DejaVu Sans",source-sans-pro,sans-serif',
			'geometric' => 'Avenir,Montserrat,Corbel,"URW Gothic",source-sans-pro,sans-serif',
			'serif'     => 'Charter,"Bitstream Charter","Sitka Text",Cambria,Georgia,serif',
			'mono'      => 'ui-monospace,"Cascadia Code","Source Code Pro",Menlo,Consolas,"DejaVu Sans Mono",monospace',
			'rounded'   => 'ui-rounded,"Hiragino Maru Gothic ProN",Quicksand,Comfortaa,Manjari,"Arial Rounded MT","Arial Rounded MT Bold",Calibri,source-sans-pro,sans-serif'
		);

		return isset($stacks[$font]) ? $stacks[$font] : '';
	}

	/** Options of a message that uses none of the 2.4.0 features (also the result for old rows and broken JSON). */
	public function defaults() {
		return array(
			'countries_mode'    => 'all',
			'countries'         => array(),
			'fs_oc'             => 0,
			'fs_zones'          => array(),
			'fs_currencies'     => array(),
			'coupon_id'         => 0,
			'tiers'             => array(),
			'cart_subtotal_min' => '',
			'cart_subtotal_max' => '',
			'cart_qty_min'      => '',
			'cart_qty_max'      => '',
			'cart_products'     => array(),
			'cart_categories'   => array(),
			'utm_source'        => '',
			'utm_medium'        => '',
			'utm_campaign'      => '',
			'referrer'          => '',
			'visitor'           => 'all',
			'trigger'           => 'none',
			'trigger_value'     => 0,
			'recur'             => '',
			'recur_time'        => '',
			'recur_day'         => 1,
			'recur_skip'        => '',
			'email'             => 0,
			'image'             => '',
			'overlay'           => 55,
			'font'              => ''
		);
	}

	/** Stored JSON (or an array) → complete, valid options. Never throws; anything invalid becomes neutral. */
	public function options($json) {
		$data = is_array($json) ? $json : json_decode((string)$json, true);

		if (!is_array($data)) {
			return $this->defaults();
		}

		list($clean) = $this->validate($data, null, array_keys(isset($data['fs_currencies']) && is_array($data['fs_currencies']) ? $data['fs_currencies'] : array()), null);

		return $clean;
	}

	/** Options as stored: JSON with ASCII only (\u escapes), so OpenCart 3's 3-byte utf8 link keeps every character. */
	public function encode(array $options) {
		$json = json_encode($options);

		return is_string($json) ? $json : '{}';
	}

	/** True when the message uses nothing that 2.4.0 added (lets old messages keep their exact output). */
	public function isNeutral(array $o) {
		return $o == $this->defaults();
	}

	/**
	 * Validates and cleans posted options ("opt" in the form, "options" in an export).
	 * $lookup($kind, $values): existing values only — kinds: country (ids), zone (ids → [zone_id => country_id]),
	 * coupon (ids), product (ids), category (ids), image (path → bool). Null skips existence checks (stored data).
	 * $currencies: currency codes of the shop; $type: message type (null = keep tiers as stored). Returns array(clean options, errors) — errors are language keys by field.
	 */
	public function validate($p, $lookup = null, array $currencies = array(), $type = 'standard') {
		$p   = is_array($p) ? $p : array();
		$o   = $this->defaults();
		$err = array();

		$str = function ($key, $max = 255) use ($p) {
			return isset($p[$key]) && is_scalar($p[$key]) ? trim(substr((string)$p[$key], 0, $max)) : '';
		};
		$flag = function ($key) use ($p) {
			return !empty($p[$key]) && is_scalar($p[$key]) && (string)$p[$key] !== '0' ? 1 : 0;
		};
		$exists = function ($kind, array $values) use ($lookup) {
			if (!$values || !is_callable($lookup)) {
				return true;
			}

			$found = call_user_func($lookup, $kind, $values);

			return is_array($found) && count(array_intersect($values, array_map('intval', $found))) === count($values);
		};

		// Countries (visitor's country: address, CloudFlare header or store country).
		$mode = $str('countries_mode', 16);
		$o['countries_mode'] = in_array($mode, array('all', 'include', 'exclude'), true) ? $mode : 'all';
		list($ids, $bad) = $this->ids(isset($p['countries']) ? $p['countries'] : array(), 300);
		if ($bad || !$exists('country', $ids)) {
			$err['countries'] = 'error_countries';
		}
		$o['countries'] = $ids;
		if ($o['countries_mode'] !== 'all' && !$ids) {
			$err['countries'] = 'error_countries_required';
		}
		if ($mode !== '' && $o['countries_mode'] !== $mode) {
			$err['countries'] = 'error_value';
		}

		// Free shipping: OpenCart's own amount, per-country / per-zone amounts, fixed amounts per currency.
		$o['fs_oc'] = $flag('fs_oc');

		$zones = isset($p['fs_zones']) && is_array($p['fs_zones']) ? array_values($p['fs_zones']) : array();
		$zone_ids = array();
		foreach ($zones as $row) {
			if (!is_array($row)) {
				$err['fs_zones'] = 'error_value';
				continue;
			}
			$c = isset($row['country_id']) && is_scalar($row['country_id']) ? trim((string)$row['country_id']) : '';
			$z = isset($row['zone_id']) && is_scalar($row['zone_id']) ? trim((string)$row['zone_id']) : '';
			$a = isset($row['amount']) && is_scalar($row['amount']) ? trim((string)$row['amount']) : '';

			if (($c === '' || $c === '0') && $a === '') {
				continue; // empty row of the form
			}

			$amount = $this->amount($a);

			if (!preg_match('/^\d{1,10}$/', $c) || (int)$c < 1 || ($z !== '' && !preg_match('/^\d{1,10}$/', $z)) || $amount === false || (float)$amount <= 0) {
				$err['fs_zones'] = 'error_fs_zones';
				continue;
			}

			$key = (int)$c . ':' . (int)$z;
			if (isset($zone_ids[$key])) {
				$err['fs_zones'] = 'error_fs_zones_duplicate';
				continue;
			}
			$zone_ids[$key] = true;

			$o['fs_zones'][] = array('country_id' => (int)$c, 'zone_id' => (int)$z, 'amount' => $amount);
		}
		if (count($o['fs_zones']) > self::MAX_ZONES) {
			$err['fs_zones'] = 'error_fs_zones';
			$o['fs_zones'] = array_slice($o['fs_zones'], 0, self::MAX_ZONES);
		}
		if ($o['fs_zones'] && is_callable($lookup)) {
			$countries = array_values(array_unique(array_map(function ($r) { return $r['country_id']; }, $o['fs_zones'])));
			$zone_list = array_values(array_filter(array_unique(array_map(function ($r) { return $r['zone_id']; }, $o['fs_zones']))));
			$map       = $zone_list ? call_user_func($lookup, 'zone', $zone_list) : array();

			if (!$exists('country', $countries)) {
				$err['fs_zones'] = 'error_fs_zones';
			}

			foreach ($o['fs_zones'] as $row) {
				if ($row['zone_id'] && (!is_array($map) || !isset($map[$row['zone_id']]) || (int)$map[$row['zone_id']] !== $row['country_id'])) {
					$err['fs_zones'] = 'error_fs_zones';
				}
			}
		}

		$cur = isset($p['fs_currencies']) && is_array($p['fs_currencies']) ? $p['fs_currencies'] : array();
		foreach ($cur as $code => $value) {
			$code  = strtoupper((string)$code);
			$value = is_scalar($value) ? trim((string)$value) : '';

			if ($value === '') {
				continue;
			}

			$amount = $this->amount($value);

			if (!preg_match('/^[A-Z]{3}$/', $code) || !in_array($code, $currencies, true) || $amount === false || (float)$amount <= 0) {
				$err['fs_currencies'] = 'error_fs_currencies';
				continue;
			}

			$o['fs_currencies'][$code] = $amount;
		}
		ksort($o['fs_currencies']);

		// OpenCart coupon (code, validity and usage come from the shop's own coupon).
		$coupon = $str('coupon_id', 12);
		if ($coupon !== '' && $coupon !== '0') {
			if (!preg_match('/^\d{1,10}$/', $coupon) || !$exists('coupon', array((int)$coupon))) {
				$err['coupon_id'] = 'error_coupon_id';
			} else {
				$o['coupon_id'] = (int)$coupon;
			}
		}

		// Tiered goals: 2–5 rising amounts (only for the "tiers" message type).
		$tiers = isset($p['tiers']) && is_array($p['tiers']) ? array_values($p['tiers']) : array();
		$prev  = 0.0;
		foreach (array_slice($tiers, 0, self::MAX_TIERS + 1) as $value) {
			$value = is_scalar($value) ? trim((string)$value) : '';

			if ($value === '') {
				continue;
			}

			$amount = $this->amount($value);

			if ($amount === false || (float)$amount <= $prev) {
				$err['tiers'] = 'error_tiers_order';
				continue;
			}

			$prev = (float)$amount;
			$o['tiers'][] = $amount;
		}
		if (count($tiers) > self::MAX_TIERS || count($o['tiers']) > self::MAX_TIERS) {
			$err['tiers'] = 'error_tiers_count';
			$o['tiers'] = array_slice($o['tiers'], 0, self::MAX_TIERS);
		}
		if ($type === 'tiers' && count($o['tiers']) < 2 && !isset($err['tiers'])) {
			$err['tiers'] = 'error_tiers_count';
		}
		if ($type !== 'tiers' && $type !== null) {
			$o['tiers'] = array();
		}

		// Cart conditions.
		foreach (array('cart_subtotal_min', 'cart_subtotal_max') as $key) {
			$value = $str($key, 32);

			if ($value === '') {
				continue;
			}

			$amount = $this->amount($value);

			if ($amount === false) {
				$err[$key] = 'error_amount';
			} else {
				$o[$key] = $amount;
			}
		}
		foreach (array('cart_qty_min', 'cart_qty_max') as $key) {
			$value = $str($key, 12);

			if ($value === '') {
				continue;
			}

			if (!preg_match('/^\d{1,6}$/', $value)) {
				$err[$key] = 'error_quantity';
			} else {
				$o[$key] = (string)(int)$value;
			}
		}
		if ($o['cart_subtotal_min'] !== '' && $o['cart_subtotal_max'] !== '' && (float)$o['cart_subtotal_min'] > (float)$o['cart_subtotal_max']) {
			$err['cart_subtotal_max'] = 'error_min_max';
		}
		if ($o['cart_qty_min'] !== '' && $o['cart_qty_max'] !== '' && (int)$o['cart_qty_min'] > (int)$o['cart_qty_max']) {
			$err['cart_qty_max'] = 'error_min_max';
		}
		foreach (array('cart_products' => 'product', 'cart_categories' => 'category') as $key => $kind) {
			list($ids, $bad) = $this->ids(isset($p[$key]) ? $p[$key] : array(), self::MAX_IDS);

			if ($bad || !$exists($kind, $ids)) {
				$err[$key] = 'error_' . $key;
			}

			$o[$key] = $ids;
		}

		// Campaign / referrer / new or returning visitor (decided in the visitor's browser).
		foreach (array('utm_source', 'utm_medium', 'utm_campaign') as $key) {
			$list = $this->tokens($str($key, 2000), '/^[a-z0-9._~+\-]{1,64}$/');

			if ($list === false) {
				$err[$key] = 'error_utm';
			} else {
				$o[$key] = implode(',', $list);
			}
		}
		$hosts = array();
		$ok    = true;
		foreach (preg_split('/[\s,]+/', strtolower($str('referrer', 2000))) as $host) {
			if ($host === '') {
				continue;
			}
			if (preg_match('#^[a-z][a-z0-9+.\-]*://#', $host)) {
				$parsed = parse_url($host, PHP_URL_HOST);
				$host   = is_string($parsed) ? $parsed : '';
			}
			$host = preg_replace('/^www\./', '', rtrim($host, '/'));
			if (!preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
				$ok = false;
				continue;
			}
			$hosts[$host] = $host;
		}
		if (!$ok || count($hosts) > self::MAX_TOKENS) {
			$err['referrer'] = 'error_referrer';
		}
		$o['referrer'] = implode(',', array_slice(array_values($hosts), 0, self::MAX_TOKENS));

		$visitor = $str('visitor', 16);
		$o['visitor'] = in_array($visitor, self::VISITORS, true) ? $visitor : 'all';
		if ($visitor !== '' && $o['visitor'] !== $visitor) {
			$err['visitor'] = 'error_value';
		}

		// Trigger: after a delay (s), after scrolling (%), on exit intent (desktop).
		$trigger = $str('trigger', 16);
		$o['trigger'] = in_array($trigger, self::TRIGGERS, true) ? $trigger : 'none';
		$value = $str('trigger_value', 6);
		if ($trigger !== '' && $o['trigger'] !== $trigger) {
			$err['trigger'] = 'error_value';
		} elseif ($o['trigger'] === 'delay' || $o['trigger'] === 'scroll') {
			$max = $o['trigger'] === 'delay' ? 600 : 100;

			if (!preg_match('/^\d{1,4}$/', $value) || (int)$value < 1 || (int)$value > $max) {
				$err['trigger'] = $o['trigger'] === 'delay' ? 'error_trigger_delay' : 'error_trigger_scroll';
				$o['trigger_value'] = $o['trigger'] === 'delay' ? 5 : 50;
			} else {
				$o['trigger_value'] = (int)$value;
			}
		}

		// Recurring countdown: daily / weekly cut-off in store time, with days to skip.
		$recur = $str('recur', 16);
		$o['recur'] = in_array($recur, self::RECUR, true) ? $recur : '';
		if ($recur !== '' && $o['recur'] !== $recur) {
			$err['recur'] = 'error_value';
		} elseif ($o['recur'] !== '') {
			$time = $str('recur_time', 8);

			if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
				$err['recur'] = 'error_recur_time';
				$o['recur'] = '';
			} else {
				$o['recur_time'] = $time;
			}

			$day = $str('recur_day', 2);
			$o['recur_day'] = preg_match('/^[1-7]$/', $day) ? (int)$day : 1;
			if ($o['recur'] === 'weekly' && $day !== '' && !preg_match('/^[1-7]$/', $day)) {
				$err['recur'] = 'error_value';
			}

			$skip = isset($p['recur_skip']) ? $p['recur_skip'] : '';
			$skip = is_array($skip) ? implode('', array_filter($skip, 'is_scalar')) : (is_scalar($skip) ? (string)$skip : '');
			$days = array_unique(str_split(preg_replace('/[^1-7]/', '', $skip)));
			sort($days);
			$o['recur_skip'] = $o['recur'] === 'daily' ? implode('', $days) : '';
			if (strlen($o['recur_skip']) === 7) {
				$err['recur'] = 'error_recur_skip';
				$o['recur_skip'] = '';
			}
		}

		// E-mail capture (newsletter, double opt-in).
		$o['email'] = $flag('email');

		// Look: background image from the image manager, overlay, font stack.
		$image = $str('image', 255);
		if ($image !== '') {
			if (!$this->imagePath($image) || (is_callable($lookup) && !call_user_func($lookup, 'image', $image))) {
				$err['image'] = 'error_image';
			} else {
				$o['image'] = $image;
			}
		}
		$overlay = $str('overlay', 3);
		if ($overlay !== '') {
			if (!preg_match('/^\d{1,2}$/', $overlay) || (int)$overlay > 90) {
				$err['overlay'] = 'error_overlay';
			} else {
				$o['overlay'] = (int)$overlay;
			}
		}
		$font = $str('font', 16);
		$o['font'] = in_array($font, self::FONTS, true) ? $font : '';
		if ($font !== '' && $o['font'] !== $font) {
			$err['font'] = 'error_value';
		}

		return array($o, $err);
	}

	/** Image manager path below image/ (catalog/…), safe for a CSS url(): no "..", quotes or brackets. */
	public function imagePath($path) {
		return is_string($path) && strlen($path) <= 255 && strpos($path, '..') === false && (bool)preg_match('#^catalog/[A-Za-z0-9 _\-./]+\.(jpe?g|png|gif|webp|avif)$#i', $path);
	}

	/**
	 * Per-language extra texts (tier labels and texts, e-mail form texts) → clean array; $errors gets language keys.
	 * Texts are plain (no HTML), at most MAX_EXTRA characters (tier texts MAX_TIER_TEXT, they may hold tokens).
	 */
	public function extra($row, array $tier_index, &$errors) {
		$row = is_array($row) ? $row : array();
		$out = array('tier_label' => array(), 'tier_text' => array());

		foreach (array('tier_label' => self::MAX_EXTRA, 'tier_text' => self::MAX_TIER_TEXT) as $key => $max) {
			$list = isset($row[$key]) && is_array($row[$key]) ? array_values($row[$key]) : array();

			foreach ($tier_index as $i) {
				$value = isset($list[$i]) && is_scalar($list[$i]) ? $this->plainText((string)$list[$i]) : '';

				if ($this->len($value) > $max) {
					$errors[$key] = 'error_extra_length';
					$value = $this->cut($value, $max);
				}

				$out[$key][] = $value;
			}
		}

		foreach (explode(',', self::EXTRA_TEXTS) as $key) {
			$value = isset($row[$key]) && is_scalar($row[$key]) ? $this->plainText((string)$row[$key]) : '';

			if ($this->len($value) > ($key === 'email_consent' ? self::MAX_TIER_TEXT : self::MAX_EXTRA)) {
				$errors[$key] = 'error_extra_length';
				$value = $this->cut($value, self::MAX_TIER_TEXT);
			}

			$out[$key] = $value;
		}

		return $out;
	}

	/** Stored extra JSON → array with every key present. */
	public function extraOf($json) {
		$data = is_array($json) ? $json : json_decode((string)$json, true);
		$data = is_array($data) ? $data : array();
		$out  = array('tier_label' => array(), 'tier_text' => array());

		foreach (array('tier_label', 'tier_text') as $key) {
			if (isset($data[$key]) && is_array($data[$key])) {
				foreach (array_slice(array_values($data[$key]), 0, self::MAX_TIERS) as $value) {
					$out[$key][] = is_scalar($value) ? $this->plainText((string)$value) : '';
				}
			}
		}

		foreach (explode(',', self::EXTRA_TEXTS) as $key) {
			$out[$key] = isset($data[$key]) && is_scalar($data[$key]) ? $this->plainText((string)$data[$key]) : '';
		}

		return $out;
	}

	/** Indexes of the tier rows that have an amount (labels / texts of empty rows are dropped with them). */
	public function tierIndexes($posted) {
		$out = array();

		foreach (array_slice(is_array($posted) ? array_values($posted) : array(), 0, self::MAX_TIERS) as $i => $value) {
			if (is_scalar($value) && trim((string)$value) !== '') {
				$out[] = $i;
			}
		}

		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Storefront decisions
	 * ------------------------------------------------------------------ */

	/** Country targeting. $country_id 0 = unknown: "only in" lists do not match, "except in" lists do. */
	public function countryMatches(array $o, $country_id) {
		if ($o['countries_mode'] === 'all' || !$o['countries']) {
			return true;
		}

		$in = $country_id > 0 && in_array((int)$country_id, $o['countries'], true);

		return $o['countries_mode'] === 'include' ? $in : !$in;
	}

	/**
	 * Free-shipping amount for this visitor: a zone amount, else a country amount (both in the default currency),
	 * else a fixed amount in the visitor's currency, else $base. Returns array(amount, currency code or '' = default).
	 */
	public function threshold(array $o, $base, $country_id, $zone_id, $currency) {
		$country = null;

		foreach ($o['fs_zones'] as $row) {
			if ($country_id > 0 && $row['country_id'] === (int)$country_id) {
				if ($row['zone_id'] && $row['zone_id'] === (int)$zone_id) {
					return array((float)$row['amount'], '');
				}

				if (!$row['zone_id'] && $country === null) {
					$country = (float)$row['amount'];
				}
			}
		}

		if ($country !== null) {
			return array($country, '');
		}

		if (is_string($currency) && isset($o['fs_currencies'][$currency])) {
			return array((float)$o['fs_currencies'][$currency], $currency);
		}

		return array((float)$base, '');
	}

	/**
	 * Tiered goals: how many tiers the subtotal reached, the next tier (index or -1), total progress (0–100) and
	 * each tier's position on the bar (%). Amounts and subtotal in the same currency.
	 */
	public function tierState(array $tiers, $subtotal) {
		$tiers = array_values(array_map('floatval', $tiers));
		$max   = $tiers ? max($tiers) : 0;
		$done  = 0;

		foreach ($tiers as $amount) {
			if ($subtotal >= $amount) {
				$done++;
			}
		}

		$marks = array();
		foreach ($tiers as $amount) {
			$marks[] = $max > 0 ? round($amount * 100 / $max, 2) : 0;
		}

		return array(
			'done'  => $done,
			'next'  => $done < count($tiers) ? $done : -1,
			'pct'   => $max > 0 ? (int)min(100, floor($subtotal * 100 / $max)) : 0,
			'marks' => $marks
		);
	}

	/** True when the message uses cart conditions at all. */
	public function hasCartRules(array $o) {
		return $o['cart_subtotal_min'] !== '' || $o['cart_subtotal_max'] !== '' || $o['cart_qty_min'] !== '' || $o['cart_qty_max'] !== '' || $o['cart_products'] || $o['cart_categories'];
	}

	/**
	 * Cart conditions: subtotal (default currency, without tax) min/max, number of items min/max, cart contains one of
	 * the products / a product of one of the categories. Every set condition must hold.
	 */
	public function cartMatches(array $o, $subtotal, $quantity, array $product_ids, array $category_ids) {
		if ($o['cart_subtotal_min'] !== '' && $subtotal < (float)$o['cart_subtotal_min']) {
			return false;
		}

		if ($o['cart_subtotal_max'] !== '' && $subtotal > (float)$o['cart_subtotal_max']) {
			return false;
		}

		if ($o['cart_qty_min'] !== '' && $quantity < (int)$o['cart_qty_min']) {
			return false;
		}

		if ($o['cart_qty_max'] !== '' && $quantity > (int)$o['cart_qty_max']) {
			return false;
		}

		if ($o['cart_products'] || $o['cart_categories']) {
			$hit = ($o['cart_products'] && array_intersect($o['cart_products'], array_map('intval', $product_ids)))
				|| ($o['cart_categories'] && array_intersect($o['cart_categories'], array_map('intval', $category_ids)));

			if (!$hit) {
				return false;
			}
		}

		return true;
	}

	/** True when the visitor's browser decides (campaign, referrer, new / returning visitor). */
	public function hasClientRules(array $o) {
		return $o['utm_source'] !== '' || $o['utm_medium'] !== '' || $o['utm_campaign'] !== '' || $o['referrer'] !== '' || $o['visitor'] !== 'all';
	}

	/** Campaign rules for the browser (data-utm): {s, m, c, r} as lists. */
	public function clientRules(array $o) {
		$list = function ($v) {
			return $v === '' ? array() : explode(',', $v);
		};

		return array('s' => $list($o['utm_source']), 'm' => $list($o['utm_medium']), 'c' => $list($o['utm_campaign']), 'r' => $list($o['referrer']));
	}

	/** data-trig value: "delay:5", "scroll:50", "exit", or '' without a trigger. */
	public function triggerAttr(array $o) {
		if ($o['trigger'] === 'delay' || $o['trigger'] === 'scroll') {
			return $o['trigger'] . ':' . (int)$o['trigger_value'];
		}

		return $o['trigger'] === 'exit' ? 'exit' : '';
	}

	/** data-recur value for the browser: "d|14:00|67" (daily, skip Sat+Sun) or "w|14:00|5" (weekly on Friday). */
	public function recurAttr(array $o) {
		if ($o['recur'] === 'daily') {
			return 'd|' . $o['recur_time'] . '|' . $o['recur_skip'];
		}

		return $o['recur'] === 'weekly' ? 'w|' . $o['recur_time'] . '|' . (int)$o['recur_day'] : '';
	}

	/**
	 * Next cut-off (Unix time) of a recurring countdown after $now, in the PHP default time zone (OpenCart sets the
	 * store's). Daily: the next HH:MM that is not on a skipped day; weekly: the next chosen weekday at HH:MM. 0 if none.
	 */
	public function nextCutoff(array $o, $now = null) {
		$now = $now === null ? time() : (int)$now;

		if ($o['recur'] === '' || !preg_match('/^(\d{2}):(\d{2})$/', $o['recur_time'], $m)) {
			return 0;
		}

		for ($i = 0; $i < 8; $i++) {
			$day = strtotime('+' . $i . ' day', $now);
			$at  = mktime((int)$m[1], (int)$m[2], 0, (int)date('n', $day), (int)date('j', $day), (int)date('Y', $day));
			$dow = (int)date('N', $at);

			if ($at <= $now) {
				continue;
			}

			if ($o['recur'] === 'weekly' ? $dow === (int)$o['recur_day'] : strpos($o['recur_skip'], (string)$dow) === false) {
				return $at;
			}
		}

		return 0;
	}

	/* ------------------------------------------------------------------ *
	 * Custom CSS, form tokens, CSV
	 * ------------------------------------------------------------------ */

	/**
	 * Custom CSS from the settings, safe inside <style>: no "<" (so no </style>), no CSS escapes (they could hide
	 * the words below), no @import / @charset, no javascript:/vbscript:/expression()/behavior/-moz-binding, and
	 * url() only for http(s), site paths and data:image.
	 */
	public function css($value) {
		$css = substr(str_replace("\0", '', (string)$value), 0, self::MAX_CSS);
		$css = (string)preg_replace('#<!--|-->|</?[a-z!][^>]*>?#i', '', $css); // tags never belong in CSS (</style>, <script>)
		$css = str_replace(array('<', '\\'), '', $css);

		do {
			$before = $css;
			$css = (string)preg_replace('/@(import|charset|namespace)\b[^;{}]*;?/i', '', $css);
			$css = (string)preg_replace('/(javascript|vbscript|livescript)\s*:/i', '', $css);
			$css = (string)preg_replace('/(expression|behaviou?r|-moz-binding)\s*[:(]?/i', '', $css);
			$css = (string)preg_replace_callback('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/is', function ($m) {
				$u = trim($m[2]);

				return preg_match('#^(https?://|/(?!/)|data:image/(png|gif|jpe?g|webp|svg\+xml)[;,])#i', $u) && !preg_match('/[\'"()\s]/', $u) ? 'url("' . $u . '")' : 'none';
			}, $css);
		} while ($css !== $before);

		return trim($css);
	}

	/** Form token "time.hmac" bound to the visitor's session (no session write needed to check it). */
	public function token($secret, $session_id, $time = null) {
		$time = $time === null ? time() : (int)$time;

		return $time . '.' . substr(hash_hmac('sha256', $time . '|' . (string)$session_id, (string)$secret), 0, 32);
	}

	/** Valid token: right session, at least $min_age seconds old (bots submit at once), at most 2 days old. */
	public function tokenValid($token, $secret, $session_id, $now = null, $min_age = 2) {
		$now = $now === null ? time() : (int)$now;

		if (!is_string($token) || !preg_match('/^(\d{9,11})\.[0-9a-f]{32}$/', $token, $m) || (string)$secret === '') {
			return false;
		}

		$age = $now - (int)$m[1];

		return $age >= $min_age && $age <= 172800 && hash_equals($this->token($secret, $session_id, (int)$m[1]), $token);
	}

	/** CSV cell safe for spreadsheets (no formula injection). */
	public function csvCell($value) {
		$value = str_replace(array("\r", "\n"), ' ', (string)$value);

		if ($value !== '' && in_array($value[0], array('=', '+', '-', '@', "\t"), true)) {
			$value = "'" . $value;
		}

		return '"' . str_replace('"', '""', $value) . '"';
	}

	/** Valid e-mail address for the newsletter (OpenCart's own limit is 96 characters). */
	public function email($value) {
		$value = trim((string)$value);

		return strlen($value) <= 96 && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
	}

	/* ------------------------------------------------------------------ *
	 * Ready-made templates (texts by language code; other languages get English)
	 * ------------------------------------------------------------------ */
	public function templates() {
		$t = function ($en, $lv, $ru) {
			return array('en' => $en, 'lv' => $lv, 'ru' => $ru);
		};

		return array(
			'free_shipping' => array(
				'icon' => 'fa-truck', 'bg' => '#0f766e', 'fg' => '#ffffff', 'type' => 'free_shipping', 'threshold' => '50',
				'name' => $t('Free shipping progress', 'Bezmaksas piegādes progress', 'Прогресс до бесплатной доставки'),
				'text' => $t('Add {remaining} more to your cart for free shipping', 'Pievieno grozam vēl {remaining} un saņem bezmaksas piegādi', 'Добавьте в корзину ещё {remaining} — и доставка будет бесплатной'),
				'success_text' => $t('You have unlocked free shipping!', 'Tev ir bezmaksas piegāde!', 'Вам доступна бесплатная доставка!')
			),
			'tiers' => array(
				'icon' => 'fa-gift', 'bg' => '#4338ca', 'fg' => '#ffffff', 'type' => 'tiers', 'tiers' => array('50', '100', '150'),
				'name' => $t('Reward tiers', 'Bonusu pakāpes', 'Уровни бонусов'),
				'text' => $t('Add {remaining} more to unlock: {goal}', 'Pievieno vēl {remaining} un saņem: {goal}', 'Добавьте ещё {remaining} и получите: {goal}'),
				'success_text' => $t('Every reward unlocked — thank you!', 'Visi bonusi saņemti — paldies!', 'Все бонусы получены — спасибо!'),
				'tier_label' => array(
					$t('Free shipping', 'Bezmaksas piegāde', 'Бесплатная доставка'),
					$t('Free gift', 'Dāvana', 'Подарок'),
					$t('10% off', '10% atlaide', 'Скидка 10%')
				)
			),
			'flash_sale' => array(
				'icon' => 'fa-bolt', 'bg' => '#b91c1c', 'fg' => '#ffffff', 'countdown' => 1, 'end_days' => 2, 'style' => 'gradient',
				'name' => $t('Flash sale with countdown', 'Zibakcija ar laika atskaiti', 'Распродажа с таймером'),
				'text' => $t('Flash sale: up to 30% off — ends in {countdown}', 'Zibakcija: līdz −30% — beidzas pēc {countdown}', 'Распродажа: скидки до 30% — до конца {countdown}'),
				'link_text' => $t('Shop now', 'Iepirkties', 'В магазин')
			),
			'same_day' => array(
				'icon' => 'fa-clock-o', 'bg' => '#1e3a8a', 'fg' => '#ffffff', 'countdown' => 1, 'recur' => 'daily', 'recur_time' => '14:00', 'recur_skip' => '67',
				'name' => $t('Same-day dispatch cut-off', 'Nosūtīšana tajā pašā dienā', 'Отправка в тот же день'),
				'text' => $t('Order within {countdown} for same-day dispatch', 'Pasūti {countdown} laikā — nosūtīsim vēl šodien', 'Закажите в течение {countdown} — отправим сегодня')
			),
			'coupon' => array(
				'icon' => 'fa-ticket', 'bg' => '#7c2d12', 'fg' => '#ffffff', 'coupon' => 'WELCOME10', 'style' => 'soft',
				'name' => $t('Welcome coupon', 'Sveiciena kupons', 'Приветственный купон'),
				'text' => $t('Welcome! 10% off your first order with this code:', 'Laipni lūgti! −10% pirmajam pasūtījumam ar šo kodu:', 'Добро пожаловать! −10% на первый заказ с этим кодом:')
			),
			'newsletter' => array(
				'icon' => 'fa-envelope', 'bg' => '#111827', 'fg' => '#ffffff', 'email' => 1,
				'name' => $t('Newsletter sign-up', 'Jaunumu pieteikšanās', 'Подписка на новости'),
				'text' => $t('Get our news and offers first', 'Saņem jaunumus un piedāvājumus pirmais', 'Узнавайте о новинках и акциях первыми')
			),
			'new_arrivals' => array(
				'icon' => 'fa-star', 'bg' => '#065f46', 'fg' => '#ffffff', 'style' => 'gradient',
				'name' => $t('New arrivals', 'Jaunumi', 'Новинки'),
				'text' => $t('New arrivals are here — discover the latest products', 'Jaunumi ir klāt — apskati jaunākās preces', 'Новинки уже здесь — посмотрите последние товары'),
				'link_text' => $t('See what\'s new', 'Skatīt jaunumus', 'Смотреть новинки')
			),
			'back_in_stock' => array(
				'icon' => 'fa-cubes', 'bg' => '#0e7490', 'fg' => '#ffffff',
				'name' => $t('Back in stock', 'Atkal pieejams', 'Снова в наличии'),
				'text' => $t('Back in stock: popular products are available again', 'Atkal pieejams: populārās preces atkal ir pārdošanā', 'Снова в наличии: популярные товары снова в продаже'),
				'link_text' => $t('Shop now', 'Iepirkties', 'В магазин')
			),
			'holiday' => array(
				'icon' => 'fa-snowflake-o', 'bg' => '#14532d', 'fg' => '#ffffff', 'style' => 'gradient',
				'name' => $t('Holiday greeting', 'Svētku apsveikums', 'Праздничное поздравление'),
				'text' => $t('Happy holidays! Thank you for shopping with us this year', 'Priecīgus svētkus! Paldies, ka šogad iepirkies pie mums', 'С праздниками! Спасибо, что делали покупки у нас в этом году')
			),
			'weekend' => array(
				'icon' => 'fa-percent', 'bg' => '#9d174d', 'fg' => '#ffffff', 'weekdays' => array(6, 7),
				'name' => $t('Weekend offer (Sat–Sun)', 'Nedēļas nogales piedāvājums (S–Sv)', 'Акция выходного дня (Сб–Вс)'),
				'text' => $t('Weekend offer: extra 15% off selected products', 'Nedēļas nogales piedāvājums: papildu −15% izvēlētām precēm', 'Акция выходного дня: дополнительно −15% на избранные товары'),
				'link_text' => $t('See offers', 'Skatīt piedāvājumus', 'Смотреть акции')
			),
			'exit_offer' => array(
				'icon' => 'fa-hand-paper-o', 'bg' => '#312e81', 'fg' => '#ffffff', 'coupon' => 'STAY10', 'trigger' => 'exit', 'placement' => 'bottom',
				'name' => $t('Exit-intent offer', 'Piedāvājums pirms aiziešanas', 'Предложение перед уходом'),
				'text' => $t('Before you go: 10% off your order with this code', 'Pirms aizej: −10% pasūtījumam ar šo kodu', 'Перед уходом: −10% на заказ с этим кодом')
			),
			'b2b' => array(
				'icon' => 'fa-briefcase', 'bg' => '#334155', 'fg' => '#ffffff', 'style' => 'minimal',
				'name' => $t('Business customers (B2B)', 'Uzņēmumiem (B2B)', 'Для бизнеса (B2B)'),
				'text' => $t('Buying for a business? Ask about trade prices and invoice payment', 'Pērc uzņēmumam? Jautā par vairumcenām un apmaksu ar rēķinu', 'Покупаете для компании? Спросите об оптовых ценах и оплате по счёту'),
				'link_text' => $t('Contact us', 'Sazināties', 'Связаться')
			),
			'privacy' => array(
				'icon' => 'fa-shield', 'bg' => '#1f2937', 'fg' => '#ffffff', 'style' => 'minimal',
				'name' => $t('Privacy-friendly shop', 'Privātumu cienošs veikals', 'Магазин, уважающий приватность'),
				'text' => $t('No tracking cookies here — shop with peace of mind', 'Šeit nav izsekošanas sīkdatņu — iepērcies mierīgi', 'Здесь нет отслеживающих cookie — покупайте спокойно')
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Small helpers
	 * ------------------------------------------------------------------ */

	/** Money amount ("50", "49.99", "49,99") → '49.9900'; false when invalid. */
	public function amount($value) {
		$value = str_replace(array(' ', "\xc2\xa0", ','), array('', '', '.'), trim((string)$value));

		return preg_match('/^\d{1,9}(\.\d{1,4})?$/', $value) ? number_format((float)$value, 4, '.', '') : false;
	}

	/** Positive integer IDs (unique, at most $max) and whether anything else was posted. */
	public function ids($value, $max = self::MAX_IDS) {
		$list = is_array($value) ? $value : (is_scalar($value) && (string)$value !== '' ? explode(',', (string)$value) : array());
		$out  = array();
		$bad  = false;

		foreach ($list as $id) {
			if (is_scalar($id) && preg_match('/^\s*\d{1,10}\s*$/', (string)$id) && (int)$id > 0) {
				$out[(int)$id] = (int)$id;
			} else {
				$bad = true;
			}
		}

		if (count($out) > $max) {
			$bad = true;
		}

		return array(array_slice(array_values($out), 0, $max), $bad);
	}

	/** Comma / space separated list, lower-cased; false when an entry does not match $pattern or the list is too long. */
	private function tokens($value, $pattern) {
		$out = array();

		foreach (preg_split('/[\s,]+/', strtolower((string)$value)) as $token) {
			if ($token === '') {
				continue;
			}

			if (!preg_match($pattern, $token)) {
				return false;
			}

			$out[$token] = $token;
		}

		return count($out) > self::MAX_TOKENS ? false : array_values($out);
	}

	/** Plain text: entities decoded, tags and control characters removed, whitespace collapsed. */
	public function plainText($value) {
		$value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');

		if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
			$value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
		}

		$value = (string)preg_replace('#</?[a-z][a-z0-9-]*(\s[^<>]*)?/?>#i', '', $value);
		$value = (string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);

		return trim((string)preg_replace('/\s+/u', ' ', $value));
	}

	private function len($value) {
		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}

	private function cut($value, $max) {
		return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
	}
}
