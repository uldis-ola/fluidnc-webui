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

	/**
	 * Settings of this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values (filter "abpro_settings_defaults" adds the settings of an add-on).
	 *
	 * @return array
	 */
	public static function defaults() {
		$own = array(
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
		);
		$all = apply_filters( 'abpro_settings_defaults', $own );

		return is_array( $all ) ? array_merge( $own, $all ) : $own;
	}

	/**
	 * Choices of a select setting: value => label (filter "abpro_setting_choices" adds values of an add-on).
	 *
	 * @param string $key position | mode | style.
	 * @return array
	 */
	public static function choices( $key ) {
		$own  = array(
			'position' => array( 'top' => __( 'Top of the page', 'announcement-bar-pro' ) ),
			'mode'     => array(
				'rotate' => __( 'Rotate one at a time', 'announcement-bar-pro' ),
				'stack'  => __( 'Show all, one under another', 'announcement-bar-pro' ),
			),
			'style'    => array(
				'solid'   => __( 'Solid', 'announcement-bar-pro' ),
				'soft'    => __( 'Soft', 'announcement-bar-pro' ),
				'minimal' => __( 'Minimal', 'announcement-bar-pro' ),
			),
		);
		$list = isset( $own[ $key ] ) ? $own[ $key ] : array();
		$more = apply_filters( 'abpro_setting_choices', $list, $key );

		return is_array( $more ) && $more ? $more : $list;
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
	 * Settings the storefront uses: values this plugin does not offer (kept from an add-on) fall back to the ones it
	 * does, unless an add-on takes them over (filter "abpro_effective_settings").
	 *
	 * @return array
	 */
	public static function effective() {
		$stored = self::get();
		$s      = $stored;

		foreach ( array( 'position', 'mode', 'style' ) as $key ) {
			if ( ! isset( self::choices( $key )[ $s[ $key ] ] ) ) {
				$s[ $key ] = 'mode' === $key && 'marquee' === $s[ $key ] ? 'rotate' : array_keys( self::choices( $key ) )[0];
			}
		}

		$s = apply_filters( 'abpro_effective_settings', $s, $stored );

		return is_array( $s ) ? $s : $stored;
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
	 * Settings of an add-on are validated by it (filter "abpro_validate_settings"); without one they keep their stored
	 * values.
	 *
	 * @param array $p   Posted values (unslashed).
	 * @param array $old Stored settings.
	 * @return array
	 */
	public static function validate( array $p, array $old ) {
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
		$out['position']     = $pick( 'position', array_map( 'strval', array_keys( self::choices( 'position' ) ) ), 'top' );
		$out['mode']         = $pick( 'mode', array_map( 'strval', array_keys( self::choices( 'mode' ) ) ), 'rotate' );
		$out['transition']   = $pick( 'transition', array( 'fade', 'slide' ), 'fade' );
		$out['color_mode']   = $pick( 'color_mode', array( 'custom', 'auto' ), 'custom' );
		$out['style']        = $pick( 'style', array_map( 'strval', array_keys( self::choices( 'style' ) ) ), 'solid' );
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

		$result = apply_filters( 'abpro_validate_settings', array( $out, $err ), $p, $old );

		return is_array( $result ) && 2 === count( $result ) ? array_values( $result ) : array( $out, $err );
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
