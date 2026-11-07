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
	 * @return string self::OK | self::MISSING | self::FAILED
	 */
	public static function verify( $secret, $token, $ip, $transport ) {
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

		return self::parse( $body );
	}

	/**
	 * Siteverify answer → OK or FAILED. Success must be the JSON value true; an action, when Cloudflare reports one,
	 * must be ours.
	 *
	 * @param mixed $body Response body.
	 * @return string
	 */
	public static function parse( $body ) {
		$data = is_string( $body ) && '' !== $body ? json_decode( $body, true ) : null;

		if ( ! is_array( $data ) || ! isset( $data['success'] ) || true !== $data['success'] ) {
			return self::FAILED;
		}

		if ( isset( $data['action'] ) && is_string( $data['action'] ) && '' !== $data['action'] && self::ACTION !== $data['action'] ) {
			return self::FAILED;
		}

		return self::OK;
	}
}
