<?php
/**
 * Premium WooCommerce lookups: usable coupons, existence checks of the form, currency codes, and the visitor's country
 * from CloudFlare's header (setting) or "unknown" instead of the store's country (setting).
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

use AnnouncementBarPro\Commerce;
use AnnouncementBarPro\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only WooCommerce data of the premium features.
 */
class Pro_Commerce {
	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'abpro_visitor_place', array( __CLASS__, 'place' ), 10, 2 );
	}

	/**
	 * Visitor's place when WooCommerce does not know the customer's address: CloudFlare's CF-IPCountry (setting,
	 * CloudFlare addresses only), else the store's country or unknown (setting).
	 *
	 * @param array  $place  array( country, state ).
	 * @param string $source customer | store | ''.
	 * @return array
	 */
	public static function place( $place, $source ) {
		if ( 'customer' === $source ) {
			return $place;
		}

		$s = Settings::effective();

		if ( ! empty( $s['geo_cf'] ) ) {
			$country = ( new Features() )->cf_country( Commerce::server(), true );

			if ( '' !== $country ) {
				return array( $country, '' );
			}
		}

		return 'none' === $s['geo_fallback'] ? array( '', '' ) : $place;
	}

	/**
	 * Code of a coupon that a visitor could use now: published, not expired, usage limit not reached (and, for a
	 * logged-in customer, their own limit). '' otherwise — the message then hides itself.
	 *
	 * @param int $coupon_id Coupon (post) ID.
	 * @return string
	 */
	public static function usable_coupon( $coupon_id ) {
		if ( ! Commerce::active() || 'shop_coupon' !== get_post_type( (int) $coupon_id ) || 'publish' !== get_post_status( (int) $coupon_id ) ) {
			return '';
		}

		$coupon  = new \WC_Coupon( (int) $coupon_id );
		$expires = $coupon->get_date_expires();

		if ( $expires && $expires->getTimestamp() <= time() ) {
			return '';
		}

		if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
			return '';
		}

		$user = get_current_user_id();
		if ( $user && $coupon->get_usage_limit_per_user() > 0 ) {
			$store = $coupon->get_data_store();

			if ( $store && method_exists( $store, 'get_usage_by_user_id' ) && $store->get_usage_by_user_id( $coupon, $user ) >= $coupon->get_usage_limit_per_user() ) {
				return '';
			}
		}

		return strtoupper( (string) $coupon->get_code() );
	}

	/**
	 * Existence check for Features::validate().
	 *
	 * @return callable
	 */
	public static function lookup() {
		return static function ( $kind, $values ) {
			switch ( $kind ) {
				case 'country':
					$all = Commerce::active() ? array_keys( WC()->countries->get_countries() ) : array();
					return array_values( array_intersect( (array) $values, $all ) );
				case 'state':
					$out = array();
					foreach ( (array) $values as $pair ) {
						list( $c, $s ) = array_pad( explode( ':', (string) $pair, 2 ), 2, '' );
						$states        = Commerce::active() ? WC()->countries->get_states( $c ) : array();
						if ( is_array( $states ) && isset( $states[ $s ] ) ) {
							$out[] = $pair;
						}
					}
					return $out;
				case 'coupon':
					return array_values(
						array_filter(
							(array) $values,
							static function ( $id ) {
								return 'shop_coupon' === get_post_type( (int) $id );
							}
						)
					);
				case 'product':
					return array_values(
						array_filter(
							(array) $values,
							static function ( $id ) {
								return in_array( get_post_type( (int) $id ), array( 'product', 'product_variation' ), true );
							}
						)
					);
				case 'category':
					return array_values(
						array_filter(
							(array) $values,
							static function ( $id ) {
								$term = get_term( (int) $id, 'product_cat' );
								return $term instanceof \WP_Term;
							}
						)
					);
				case 'image':
					return wp_attachment_is_image( (int) $values );
			}

			return array();
		};
	}

	/**
	 * Currency codes the shop may show: its own, plus the codes of the message (multi-currency plugins).
	 *
	 * @return string[]
	 */
	public static function currency_codes() {
		if ( ! Commerce::active() ) {
			return array();
		}

		return array_keys( get_woocommerce_currencies() );
	}
}
