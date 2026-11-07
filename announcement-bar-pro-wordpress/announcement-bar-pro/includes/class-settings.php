<?php
/**
 * Plugin settings (one option, "abpro_settings"): defaults, validation and the values the storefront really uses.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Settings store.
 */
class Settings {
	const OPTION = 'abpro_settings';
	const STYLES = array( 'solid', 'gradient', 'soft', 'minimal', 'glass' );

	/** Styles of the free edition. */
	const FREE_STYLES = array( 'solid', 'soft', 'minimal' );

	/** Settings that only work with a Pro licence (their stored value is kept while the licence is missing). */
	const PRO_KEYS = array( 'max_messages', 'autohide', 'pause_button', 'font', 'custom_css', 'ga4', 'consent_wait', 'double_optin', 'privacy_id', 'signup_limit_ip', 'signup_limit_total', 'geo_cf', 'geo_fallback' );

	/**
	 * Settings of this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'status'             => 1,
			'position'           => 'top',
			'sticky'             => 0,
			'mode'               => 'rotate',
			'transition'         => 'fade',
			'interval'           => 5,
			'speed'              => 60,
			'font_size'          => 14,
			'padding'            => 8,
			'dismissible'        => 1,
			'dismiss_days'       => 1,
			'arrows'             => 1,
			'allow_html'         => 0,
			'color_mode'         => 'custom',
			'style'              => 'solid',
			'default_bg_color'   => '#1f2937',
			'default_text_color' => '#ffffff',
			'languages'          => array(),
			'delete_data'        => 0,
			// Pro.
			'max_messages'       => 0,
			'autohide'           => 0,
			'pause_button'       => 1,
			'font'               => '',
			'custom_css'         => '',
			'ga4'                => 0,
			'consent_wait'       => 0,
			'double_optin'       => 1,
			'privacy_id'         => 0,
			'signup_limit_ip'    => 10,
			'signup_limit_total' => 200,
			'geo_cf'             => 0,
			'geo_fallback'       => 'store',
		);
	}

	/**
	 * Stored settings merged with the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function value( $key ) {
		$all = self::get();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Settings the storefront uses: Pro values fall back to the defaults (and Pro styles / placements to free ones)
	 * while no Pro licence is active.
	 *
	 * @return array
	 */
	public static function effective() {
		$s = self::get();

		if ( Licence::is_pro() ) {
			return $s;
		}

		foreach ( self::PRO_KEYS as $key ) {
			$s[ $key ] = self::defaults()[ $key ];
		}

		$s['pause_button'] = 0;
		$s['position']     = 'top';
		$s['mode']         = 'marquee' === $s['mode'] ? 'rotate' : $s['mode'];
		$s['style']        = in_array( $s['style'], self::FREE_STYLES, true ) ? $s['style'] : 'solid';

		return $s;
	}

	/**
	 * Site secret for form tokens and rate-limit keys (created once, 64 hex characters).
	 *
	 * @return string
	 */
	public static function secret() {
		$secret = get_option( 'abpro_secret', '' );

		if ( ! is_string( $secret ) || ! preg_match( '/^[0-9a-f]{64}$/', $secret ) ) {
			$secret = bin2hex( random_bytes( 32 ) );
			update_option( 'abpro_secret', $secret, false );
		}

		return $secret;
	}

	/**
	 * Languages the messages are written in: the site language first, then the extra ones of the settings.
	 *
	 * @return string[] Locales such as en_US, lv, ru_RU.
	 */
	public static function locales() {
		$list = array( get_locale() );

		foreach ( (array) self::value( 'languages' ) as $locale ) {
			if ( is_string( $locale ) && preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', $locale ) && ! in_array( $locale, $list, true ) ) {
				$list[] = $locale;
			}
		}

		return array_slice( $list, 0, 12 );
	}

	/**
	 * Validates posted settings. Returns array( clean settings, errors by field ).
	 *
	 * @param array $p   Posted values (unslashed).
	 * @param array $old Stored settings.
	 * @param bool  $pro Pro licence active.
	 * @return array
	 */
	public static function validate( array $p, array $old, $pro ) {
		$fx  = new Features();
		$san = new Sanitizer();
		$err = array();
		$out = $old;

		$pick  = static function ( $key, array $allowed, $fallback ) use ( $p, &$err ) {
			$value = isset( $p[ $key ] ) && is_string( $p[ $key ] ) ? $p[ $key ] : $fallback;

			if ( ! in_array( $value, $allowed, true ) ) {
				$err[ $key ] = __( 'Please choose one of the offered values.', 'announcement-bar-pro' );
				return $fallback;
			}

			return $value;
		};
		$range = static function ( $key, $min, $max, $fallback ) use ( $p, &$err ) {
			$value = isset( $p[ $key ] ) && is_scalar( $p[ $key ] ) ? trim( (string) $p[ $key ] ) : '';

			if ( ! preg_match( '/^-?\d{1,7}$/', $value ) || (int) $value < $min || (int) $value > $max ) {
				/* translators: 1: smallest value, 2: largest value */
				$err[ $key ] = sprintf( __( 'Please enter a whole number from %1$d to %2$d.', 'announcement-bar-pro' ), $min, $max );
				return $fallback;
			}

			return (int) $value;
		};
		$flag  = static function ( $key ) use ( $p ) {
			return ! empty( $p[ $key ] ) ? 1 : 0;
		};

		$d = self::defaults();

		$out['status']       = $flag( 'status' );
		$out['sticky']       = $flag( 'sticky' );
		$out['dismissible']  = $flag( 'dismissible' );
		$out['arrows']       = $flag( 'arrows' );
		$out['allow_html']   = $flag( 'allow_html' );
		$out['delete_data']  = $flag( 'delete_data' );
		$out['position']     = $pick( 'position', $pro ? array( 'top', 'bottom' ) : array( 'top' ), 'top' );
		$out['mode']         = $pick( 'mode', $pro ? array( 'rotate', 'marquee', 'stack' ) : array( 'rotate', 'stack' ), 'rotate' );
		$out['transition']   = $pick( 'transition', array( 'fade', 'slide' ), 'fade' );
		$out['color_mode']   = $pick( 'color_mode', array( 'custom', 'auto' ), 'custom' );
		$out['style']        = $pick( 'style', $pro ? self::STYLES : self::FREE_STYLES, 'solid' );
		$out['interval']     = $range( 'interval', 2, 60, $d['interval'] );
		$out['speed']        = $range( 'speed', 20, 300, $d['speed'] );
		$out['font_size']    = $range( 'font_size', 10, 30, $d['font_size'] );
		$out['padding']      = $range( 'padding', 0, 30, $d['padding'] );
		$out['dismiss_days'] = $range( 'dismiss_days', 0, 365, $d['dismiss_days'] );

		foreach ( array( 'default_bg_color', 'default_text_color' ) as $key ) {
			$value = $san->color( isset( $p[ $key ] ) ? $p[ $key ] : '' );

			if ( '' === $value ) {
				$err[ $key ] = __( 'Please enter a colour such as #1f2937.', 'announcement-bar-pro' );
				$value       = $d[ $key ];
			}

			$out[ $key ] = $value;
		}

		$langs = array();
		$raw   = isset( $p['languages'] ) && is_string( $p['languages'] ) ? preg_split( '/[\s,]+/', $p['languages'] ) : array();
		foreach ( $raw as $locale ) {
			if ( '' === $locale ) {
				continue;
			}
			if ( ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', $locale ) ) {
				$err['languages'] = __( 'Use WordPress language codes such as lv, ru_RU or de_DE, separated by commas.', 'announcement-bar-pro' );
				continue;
			}
			$langs[ $locale ] = $locale;
		}
		$out['languages'] = array_values( array_slice( $langs, 0, 11 ) );

		if ( ! $pro ) {
			return array( $out, $err ); // Pro settings keep their stored values.
		}

		$out['pause_button']       = $flag( 'pause_button' );
		$out['ga4']                = $flag( 'ga4' );
		$out['consent_wait']       = $flag( 'consent_wait' );
		$out['double_optin']       = $flag( 'double_optin' );
		$out['geo_cf']             = $flag( 'geo_cf' );
		$out['max_messages']       = $range( 'max_messages', 0, 20, $d['max_messages'] );
		$out['autohide']           = $range( 'autohide', 0, 600, $d['autohide'] );
		$out['signup_limit_ip']    = $range( 'signup_limit_ip', 1, 1000, $d['signup_limit_ip'] );
		$out['signup_limit_total'] = $range( 'signup_limit_total', 1, 100000, $d['signup_limit_total'] );
		$out['font']               = $pick( 'font', Features::FONTS, '' );
		$out['geo_fallback']       = $pick( 'geo_fallback', array( 'store', 'none' ), 'store' );

		$privacy = isset( $p['privacy_id'] ) && is_scalar( $p['privacy_id'] ) ? (int) $p['privacy_id'] : 0;
		if ( $privacy > 0 && 'publish' !== get_post_status( $privacy ) ) {
			$err['privacy_id'] = __( 'Please choose a published page.', 'announcement-bar-pro' );
			$privacy           = 0;
		}
		$out['privacy_id'] = $privacy;

		$css = isset( $p['custom_css'] ) && is_string( $p['custom_css'] ) ? $p['custom_css'] : '';
		if ( strlen( $css ) > Features::MAX_CSS ) {
			/* translators: %d: number of characters */
			$err['custom_css'] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro' ), Features::MAX_CSS );
		}
		$out['custom_css'] = $fx->css( $css );

		return array( $out, $err );
	}

	/**
	 * Saves settings.
	 *
	 * @param array $settings Clean settings.
	 */
	public static function save( array $settings ) {
		update_option( self::OPTION, $settings );
		self::$cache = null;
	}

	/**
	 * Forgets the cached settings (tests, multisite switches).
	 */
	public static function reset() {
		self::$cache = null;
	}
}
