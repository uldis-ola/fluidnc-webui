<?php
/**
 * The "options" column of a message (JSON). This plugin uses one option of it — fs_wc, the minimum amount of
 * WooCommerce's own "Free shipping" method. Every other key belongs to an add-on (see the hooks in README.md): it is
 * kept untouched on save. Without an add-on that handles them, options that narrow the audience keep the message
 * hidden (it never reaches more visitors than its author intended) and look options are ignored.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Message options helper.
 */
class Options {
	/**
	 * Values that mean "not used" for the keys this plugin knows of. Keys that are missing count as neutral too.
	 */
	const NEUTRAL = array(
		'countries_mode' => array( 'all' ),
		'trigger'        => array( 'none' ),
		'visitor'        => array( 'all' ),
		'overlay'        => array( 55 ),
		'recur_day'      => array( 1 ),
	);

	/**
	 * Stored JSON (or an array) → array as stored (unknown keys kept).
	 *
	 * @param mixed $json Stored value.
	 * @return array
	 */
	public static function decode( $json ) {
		$data = is_array( $json ) ? $json : json_decode( (string) $json, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Array → JSON for the column.
	 *
	 * @param array $options Options.
	 * @return string
	 */
	public static function encode( array $options ) {
		$json = wp_json_encode( $options ? $options : new \stdClass() );

		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * The free-shipping switch of this plugin.
	 *
	 * @param array $options Decoded options.
	 * @return int
	 */
	public static function fs_wc( array $options ) {
		return ! empty( $options['fs_wc'] ) ? 1 : 0;
	}

	/**
	 * Options that only change the look or the amount of a message (dropped while no add-on handles them: the message
	 * then shows with this plugin's look), and qualifiers that only count together with their switch (countries with
	 * countries_mode, trigger_value with trigger). Every other option narrows the audience.
	 */
	const LOOK = array( 'countries', 'trigger_value', 'fs_wc', 'fs_zones', 'fs_currencies', 'coupon_id', 'tiers', 'email', 'image', 'overlay', 'font', 'recur', 'recur_time', 'recur_day', 'recur_skip' );

	/**
	 * True when the value of an option means "not used".
	 *
	 * @param string $key   Option.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	private static function neutral( $key, $value ) {
		if ( '' === $value || null === $value || 0 === $value || '0' === $value || array() === $value ) {
			return true;
		}

		return isset( self::NEUTRAL[ $key ] ) && in_array( $value, self::NEUTRAL[ $key ], false ); // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse -- "55" and 55 are the same value.
	}

	/**
	 * True when a message narrows its audience in a way this plugin does not check (countries, cart, campaign,
	 * visitor, trigger, a type or placement it does not know, roles, pages, products, categories). Such a message is
	 * shown only when an add-on handles it — never to more people than intended.
	 *
	 * @param array $row     Message row.
	 * @param array $options Decoded options.
	 * @return bool
	 */
	public static function narrows( array $row, array $options ) {
		foreach ( $options as $key => $value ) {
			if ( ! in_array( (string) $key, self::LOOK, true ) && ! self::neutral( (string) $key, $value ) ) {
				return true;
			}
		}

		$json_empty = static function ( $value ) {
			$list = json_decode( (string) $value, true );

			return ! is_array( $list ) || ! $list;
		};

		return ( isset( $row['placement'] ) && ! in_array( (string) $row['placement'], array( '', 'top', 'bottom' ), true ) )
			|| ( isset( $row['type'] ) && ! in_array( (string) $row['type'], array( 'standard', 'free_shipping' ), true ) )
			|| ( isset( $row['page_mode'] ) && ! in_array( (string) $row['page_mode'], array( '', 'all' ), true ) )
			|| ( isset( $row['roles'] ) && ! $json_empty( $row['roles'] ) )
			|| ( isset( $row['categories'] ) && ! $json_empty( $row['categories'] ) )
			|| ( isset( $row['products'] ) && ! $json_empty( $row['products'] ) );
	}

	/**
	 * True when an add-on handles the extra columns and options (filter "abpro_handles_extras").
	 *
	 * @return bool
	 */
	public static function handled() {
		return (bool) apply_filters( 'abpro_handles_extras', false );
	}
}
