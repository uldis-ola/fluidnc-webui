<?php
/**
 * Announcement Bar Pro — PHP 5.6 compatibility for the OpenCart 1.5 edition.
 * The shared helpers (features.php, sanitizer.php) are identical in every edition and call two PHP 7.0 functions:
 * random_bytes() and intdiv(). On PHP 5.6 they are provided here (only when missing, so a newer PHP or another
 * polyfill such as paragonie/random_compat always wins). Nothing else is defined.
 * @version 2.4.1
 */
defined('VERSION') || exit;

if (!function_exists('random_bytes')) {
	/** Cryptographically secure random bytes (OpenSSL, else mcrypt / urandom); throws when no secure source exists. */
	function random_bytes($length) {
		$length = (int)$length;

		if ($length < 1) {
			throw new Exception('random_bytes(): length must be greater than 0');
		}

		if (function_exists('openssl_random_pseudo_bytes')) {
			$strong = false;
			$bytes  = openssl_random_pseudo_bytes($length, $strong);

			if (is_string($bytes) && strlen($bytes) === $length && $strong) {
				return $bytes;
			}
		}

		if (function_exists('mcrypt_create_iv')) {
			$bytes = @mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);

			if (is_string($bytes) && strlen($bytes) === $length) {
				return $bytes;
			}
		}

		if (@is_readable('/dev/urandom')) {
			$fp = @fopen('/dev/urandom', 'rb');

			if ($fp) {
				$bytes = fread($fp, $length);
				fclose($fp);

				if (is_string($bytes) && strlen($bytes) === $length) {
					return $bytes;
				}
			}
		}

		throw new Exception('random_bytes(): no secure random source (enable the OpenSSL extension)');
	}
}

if (!function_exists('intdiv')) {
	/** Integer division (PHP 7.0). */
	function intdiv($dividend, $divisor) {
		$dividend = (int)$dividend;
		$divisor  = (int)$divisor;

		if ($divisor === 0) {
			throw new Exception('Division by zero');
		}

		return (int)(($dividend - $dividend % $divisor) / $divisor);
	}
}
