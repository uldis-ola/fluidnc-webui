<?php
/**
 * Announcement Bar Pro — template helpers of the OpenCart 1.5 edition (the .tpl files are converted from the Twig
 * templates of the newer editions by tools/twig2tpl.py and keep Twig's rules: a missing value is null without a PHP
 * notice, escaping as Twig's e / e('url') / e('js') filters).
 * @version 2.4.1
 */
defined('VERSION') || exit;

if (!function_exists('abp15_v')) {
	/**
	 * Value of a variable or array element, null when missing. Taken by reference, so a missing variable or key never
	 * raises a notice (OpenCart 1.5 prints notices on the page when "Display errors" is on).
	 */
	function abp15_v(&$value) {
		return $value;
	}

	/** HTML escape (Twig "e"). */
	function abp15_e($value) {
		if (is_bool($value)) {
			$value = $value ? '1' : '';
		}

		return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES, 'UTF-8');
	}

	/** URL component escape (Twig "e('url')"). */
	function abp15_url($value) {
		return rawurlencode(is_scalar($value) ? (string)$value : '');
	}

	/** JavaScript string escape (Twig "e('js')"): every character except a-z, 0-9, ",", "." and "_" as \uXXXX / \xHH. */
	function abp15_js($value) {
		$value = is_scalar($value) ? (string)$value : '';

		return preg_replace_callback('/[^a-zA-Z0-9,._]/Su', function ($m) {
			$char = $m[0];

			if (strlen($char) === 1) {
				return sprintf('\\x%02X', ord($char));
			}

			$utf16 = function_exists('mb_convert_encoding') ? mb_convert_encoding($char, 'UTF-16BE', 'UTF-8') : '';

			if ($utf16 === '') {
				return '';
			}

			$out = '';

			foreach (str_split(bin2hex($utf16), 4) as $unit) {
				$out .= '\\u' . strtoupper($unit);
			}

			return $out;
		}, $value);
	}

	/** Twig "length": items of an array, characters of a string. */
	function abp15_len($value) {
		if (is_array($value) || $value instanceof Countable) {
			return count($value);
		}

		$value = is_scalar($value) ? (string)$value : '';

		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}

	/** Twig "in": element of an array (loose, like Twig), or part of a string. */
	function abp15_in($needle, $haystack) {
		if (is_array($haystack)) {
			return in_array($needle, $haystack);
		}

		return is_scalar($haystack) && is_scalar($needle) && (string)$needle !== '' && strpos((string)$haystack, (string)$needle) !== false;
	}
}
