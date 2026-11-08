<?php
/**
 * Cloudflare Turnstile: optional anti-bot check of the e-mail sign-up form. Off unless the shop enters its own site key
 * and secret key. The answer of Cloudflare's siteverify is parsed here without WordPress calls (unit-testable with a
 * mocked transport); the plugin passes wp_remote_post() as the transport.
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

defined( 'ABSPATH' ) || exit;

/**
 * Turnstile keys and server-side verification.
 */
class Turnstile {
	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
	const ACTION     = 'abp-signup';

	/** Results of verify(). */
	const OK      = 'ok';
	const MISSING = 'missing'; // No token in the form: the widget had not finished.
	const FAILED  = 'failed';  // Refused by Cloudflare, or the check could not be done (fail closed).

	/**
	 * Valid site key ('' when not).
	 *
	 * @param mixed $value Key.
	 * @return string
	 */
	public static function site_key( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return preg_match( '/^[0-9A-Za-z_\-]{10,100}$/', $value ) ? $value : '';
	}

	/**
	 * Valid secret key ('' when not).
	 *
	 * @param mixed $value Key.
	 * @return string
	 */
	public static function secret_key( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return preg_match( '/^[0-9A-Za-z_.\-]{10,200}$/', $value ) ? $value : '';
	}

	/**
	 * True when both keys are set (the check is on).
	 *
	 * @param array $settings Settings.
	 * @return bool
	 */
	public static function enabled( array $settings ) {
		return '' !== self::site_key( isset( $settings['turnstile_site_key'] ) ? $settings['turnstile_site_key'] : '' )
			&& '' !== self::secret_key( isset( $settings['turnstile_secret'] ) ? $settings['turnstile_secret'] : '' );
	}

	/**
	 * Checks a token with Cloudflare. $transport( $url, array $fields ) returns the response body (string) or false;
	 * it may throw. Anything but a clear success fails closed.
	 *
	 * @param string   $secret    Secret key.
	 * @param mixed    $token     Posted "cf-turnstile-response".
	 * @param string   $ip        Visitor IP (remoteip).
	 * @param callable $transport HTTP POST.
	 * @param string   $host      The site's host from self::host() ('' = no hostname check).
	 * @return string self::OK | self::MISSING | self::FAILED
	 */
	public static function verify( $secret, $token, $ip, $transport, $host = '' ) {
		$token = is_string( $token ) ? trim( $token ) : '';

		if ( '' === $token ) {
			return self::MISSING;
		}

		if ( strlen( $token ) > 2048 || preg_match( '/[\x00-\x1F\x7F\s]/', $token ) || '' === self::secret_key( $secret ) || ! is_callable( $transport ) ) {
			return self::FAILED;
		}

		$fields = array(
			'secret'   => (string) $secret,
			'response' => $token,
		);

		if ( is_string( $ip ) && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$fields['remoteip'] = $ip;
		}

		try {
			$body = call_user_func( $transport, self::VERIFY_URL, $fields );
		} catch ( \Throwable $e ) {
			return self::FAILED;
		}

		return self::parse( $body, $host );
	}

	/**
	 * Siteverify answer → OK or FAILED. Success must be the JSON value true; an action, when Cloudflare reports one,
	 * must be ours; a hostname, when Cloudflare reports one and $host is set, must be the site's (1.0.1: a token solved
	 * on another site with the same widget keys is refused).
	 *
	 * @param mixed  $body Response body.
	 * @param string $host The site's host from self::host() ('' = no hostname check).
	 * @return string
	 */
	public static function parse( $body, $host = '' ) {
		$data = is_string( $body ) && '' !== $body ? json_decode( $body, true ) : null;

		if ( ! is_array( $data ) || ! isset( $data['success'] ) || true !== $data['success'] ) {
			return self::FAILED;
		}

		if ( isset( $data['action'] ) && is_string( $data['action'] ) && '' !== $data['action'] && self::ACTION !== $data['action'] ) {
			return self::FAILED;
		}

		$host = is_string( $host ) ? self::host_name( $host ) : '';

		if ( '' !== $host && isset( $data['hostname'] ) && '' !== $data['hostname'] && ( ! is_string( $data['hostname'] ) || self::host_name( $data['hostname'] ) !== $host ) ) {
			return self::FAILED;
		}

		return self::OK;
	}

	/**
	 * The host compared with siteverify's "hostname": from the site's own URL (home_url(), never the request's Host
	 * header). '' (no hostname check) when it has none, or for a local development host (localhost, *.localhost,
	 * 127.x.x.x, ::1): a public site is never served under those names, and Cloudflare's test keys answer
	 * "hostname": "example.com" there.
	 *
	 * @param mixed $url Site URL (or a bare host).
	 * @return string
	 */
	public static function host( $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';

		if ( '' === $url ) {
			return '';
		}

		$host = wp_parse_url( false === strpos( $url, '//' ) ? 'http://' . $url : $url, PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		$host = self::host_name( $host );

		if ( 'localhost' === $host || '.localhost' === substr( $host, -10 ) || '::1' === $host || preg_match( '/^127(\.\d{1,3}){3}$/', $host ) ) {
			return '';
		}

		return $host;
	}

	/**
	 * Host for comparing: lower case, no [ ] (IPv6), no trailing dot, no leading "www." (www and the bare domain are
	 * one site).
	 *
	 * @param string $host Host.
	 * @return string
	 */
	private static function host_name( $host ) {
		$host = rtrim( strtolower( trim( trim( $host ), '[]' ) ), '.' );

		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}
}
