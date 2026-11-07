<?php
/**
 * WooCommerce adapter: cart, visitor's place, free-shipping minimum, coupons, catalogue lookups.
 * Every method is safe without WooCommerce (it then answers "nothing").
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only access to WooCommerce data.
 */
class Commerce {
	/**
	 * Visitor's place of this request: array( country, state ).
	 *
	 * @var array|null
	 */
	private static $place = null;

	/**
	 * Cart numbers of this request.
	 *
	 * @var array|null
	 */
	private static $cart = null;

	/**
	 * True when WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * True when a cart is available in this request.
	 *
	 * @return bool
	 */
	public static function has_cart() {
		return self::active() && WC()->cart instanceof \WC_Cart;
	}

	/**
	 * Cart subtotal as WooCommerce's free-shipping check sees it (displayed subtotal, shop currency of the request).
	 *
	 * @return float
	 */
	public static function subtotal() {
		return (float) self::cart()['subtotal'];
	}

	/**
	 * Cart subtotal, item count, product and category IDs (categories only when asked for).
	 *
	 * @param bool $categories Load the categories of the products.
	 * @return array
	 */
	public static function cart( $categories = false ) {
		if ( null === self::$cart ) {
			self::$cart = array(
				'subtotal'   => 0.0,
				'qty'        => 0,
				'products'   => array(),
				'categories' => null,
			);

			if ( self::has_cart() ) {
				$cart = WC()->cart;
				$ids  = array();

				foreach ( $cart->get_cart() as $item ) {
					$pid         = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
					$ids[ $pid ] = $pid;
				}

				self::$cart['subtotal'] = (float) $cart->get_displayed_subtotal();
				self::$cart['qty']      = (int) $cart->get_cart_contents_count();
				self::$cart['products'] = array_values( array_filter( $ids ) );
			}
		}

		if ( $categories && null === self::$cart['categories'] ) {
			$cats = array();

			foreach ( self::$cart['products'] as $pid ) {
				foreach ( self::product_categories( $pid ) as $cid ) {
					$cats[ $cid ] = $cid;
				}
			}

			self::$cart['categories'] = array_values( $cats );
		}

		$state               = self::$cart;
		$state['categories'] = (array) $state['categories'];

		return $state;
	}

	/**
	 * Forgets the cart numbers (after a cart change in the same request, tests).
	 */
	public static function reset() {
		self::$cart  = null;
		self::$place = null;
	}

	/**
	 * Category IDs of a product (with their parent categories).
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public static function product_categories( $product_id ) {
		$terms = get_the_terms( (int) $product_id, 'product_cat' );
		$out   = array();

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[ (int) $term->term_id ] = (int) $term->term_id;

				foreach ( get_ancestors( (int) $term->term_id, 'product_cat', 'taxonomy' ) as $parent ) {
					$out[ (int) $parent ] = (int) $parent;
				}
			}
		}

		return array_values( $out );
	}

	/**
	 * Currency of this request (multi-currency plugins change it through WooCommerce's own filter).
	 *
	 * @return string
	 */
	public static function currency() {
		return self::active() ? (string) get_woocommerce_currency() : '';
	}

	/**
	 * Amount as the shop shows it, plain text (no HTML).
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code ('' = current).
	 * @return string
	 */
	public static function money( $amount, $currency = '' ) {
		if ( ! self::active() ) {
			return number_format_i18n( (float) $amount, 2 );
		}

		$args = '' !== $currency ? array( 'currency' => $currency ) : array();

		return trim( html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount, $args ) ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Visitor's country and state: WooCommerce customer (shipping, then billing — WooCommerce fills it from the
	 * address, its own geolocation setting or the store), CloudFlare's CF-IPCountry (setting, CloudFlare addresses only),
	 * else the store's base country (setting) or unknown. No external service is called.
	 *
	 * @param array $settings Effective settings.
	 * @return array (country, state)
	 */
	public static function place( array $settings ) {
		if ( null !== self::$place ) {
			return self::$place;
		}

		$country = '';
		$state   = '';

		if ( self::active() && WC()->customer instanceof \WC_Customer ) {
			$country = (string) WC()->customer->get_shipping_country();
			$state   = (string) WC()->customer->get_shipping_state();

			if ( '' === $country ) {
				$country = (string) WC()->customer->get_billing_country();
				$state   = (string) WC()->customer->get_billing_state();
			}
		}

		if ( '' === $country && ! empty( $settings['geo_cf'] ) ) {
			$country = ( new Features() )->cf_country( self::server(), true );
		}

		if ( '' === $country && self::active() && 'none' !== $settings['geo_fallback'] ) {
			$country = (string) WC()->countries->get_base_country();
			$state   = (string) WC()->countries->get_base_state();
		}

		self::$place = array( strtoupper( $country ), strtoupper( $state ) );

		return self::$place;
	}

	/**
	 * Request headers needed for the CloudFlare checks and the rate limit (REMOTE_ADDR and CF-* only).
	 *
	 * @return array
	 */
	public static function server() {
		$out = array();

		foreach ( array( 'REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP', 'HTTP_CF_IPCOUNTRY' ) as $key ) {
			if ( isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}

		return $out;
	}

	/**
	 * Minimum amount of WooCommerce's "Free shipping" method for the visitor's shipping zone (methods that need
	 * a minimum amount), or 0 when there is none.
	 *
	 * @param array $settings Effective settings.
	 * @return float
	 */
	public static function free_shipping_minimum( array $settings ) {
		if ( ! self::active() || ! class_exists( '\WC_Shipping_Zones' ) ) {
			return 0.0;
		}

		list( $country, $state ) = self::place( $settings );

		if ( '' === $country ) {
			return 0.0;
		}

		$postcode = WC()->customer instanceof \WC_Customer ? (string) WC()->customer->get_shipping_postcode() : '';
		$zone     = \WC_Shipping_Zones::get_zone_matching_package(
			array(
				'destination' => array(
					'country'  => $country,
					'state'    => $state,
					'postcode' => $postcode,
				),
			)
		);
		$min      = 0.0;

		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( 'free_shipping' !== $method->id ) {
				continue;
			}

			$requires = (string) $method->get_option( 'requires' );
			$amount   = (float) $method->get_option( 'min_amount' );

			if ( in_array( $requires, array( 'min_amount', 'either' ), true ) && $amount > 0 && ( 0.0 === $min || $amount < $min ) ) {
				$min = $amount;
			}
		}

		return $min;
	}

	/**
	 * Code of a coupon that a visitor could use now: published, not expired, usage limit not reached (and, for a
	 * logged-in customer, their own limit). '' otherwise — the message then hides itself.
	 *
	 * @param int $coupon_id Coupon (post) ID.
	 * @return string
	 */
	public static function usable_coupon( $coupon_id ) {
		if ( ! self::active() || 'shop_coupon' !== get_post_type( (int) $coupon_id ) || 'publish' !== get_post_status( (int) $coupon_id ) ) {
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
					$all = self::active() ? array_keys( WC()->countries->get_countries() ) : array();
					return array_values( array_intersect( (array) $values, $all ) );
				case 'state':
					$out = array();
					foreach ( (array) $values as $pair ) {
						list( $c, $s ) = array_pad( explode( ':', (string) $pair, 2 ), 2, '' );
						$states        = self::active() ? WC()->countries->get_states( $c ) : array();
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
		if ( ! self::active() ) {
			return array();
		}

		return array_keys( get_woocommerce_currencies() );
	}
}
