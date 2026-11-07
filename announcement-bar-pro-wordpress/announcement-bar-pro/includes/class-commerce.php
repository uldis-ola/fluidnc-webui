<?php
/**
 * WooCommerce adapter: cart, visitor's place, free-shipping minimum.
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
	 * address, its own geolocation setting or the store), else the store's base country. The filter
	 * "abpro_visitor_place" may change it (an add-on: CloudFlare's country header). No external service is called.
	 *
	 * @return array (country, state)
	 */
	public static function place() {
		if ( null !== self::$place ) {
			return self::$place;
		}

		$country = '';
		$state   = '';
		$source  = '';

		if ( self::active() && WC()->customer instanceof \WC_Customer ) {
			$country = (string) WC()->customer->get_shipping_country();
			$state   = (string) WC()->customer->get_shipping_state();

			if ( '' === $country ) {
				$country = (string) WC()->customer->get_billing_country();
				$state   = (string) WC()->customer->get_billing_state();
			}

			$source = '' !== $country ? 'customer' : '';
		}

		if ( '' === $country && self::active() ) {
			$country = (string) WC()->countries->get_base_country();
			$state   = (string) WC()->countries->get_base_state();
			$source  = 'store';
		}

		$place       = array( strtoupper( $country ), strtoupper( $state ) );
		$filtered    = apply_filters( 'abpro_visitor_place', $place, $source );
		self::$place = is_array( $filtered ) && 2 === count( $filtered ) ? array_values( array_map( 'strval', $filtered ) ) : $place;

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
	 * @return float
	 */
	public static function free_shipping_minimum() {
		if ( ! self::active() || ! class_exists( '\WC_Shipping_Zones' ) ) {
			return 0.0;
		}

		list( $country, $state ) = self::place();

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
}
