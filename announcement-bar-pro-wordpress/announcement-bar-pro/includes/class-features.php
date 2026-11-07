<?php
/**
 * Message options (targeting, goals, triggers, countdown, look), free-shipping overrides, cart conditions, recurring
 * cut-off times, custom CSS, form tokens and templates — port of the OpenCart 2.4.0 library
 * (system/library/announcementbarpro/features.php). Pure PHP without WordPress calls: unit-testable.
 *
 * Differences to the OpenCart file (on purpose):
 * - countries are WooCommerce ISO 3166-1 codes ("LV"), zones are WooCommerce state codes ("LV:RIX" style pairs);
 * - the background image is a media-library attachment ID, not an image-manager path;
 * - cut-off times are worked out in the site's time zone (WordPress keeps PHP on UTC);
 * - template icons are names of the built-in SVG set.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Feature helpers shared by admin, storefront and endpoints.
 */
class Features {
	const PLACEMENTS    = array( '', 'top', 'bottom', 'cart', 'product', 'minicart' );
	const TRIGGERS      = array( 'none', 'delay', 'scroll', 'exit' );
	const VISITORS      = array( 'all', 'new', 'returning' );
	const RECUR         = array( '', 'daily', 'weekly' );
	const FONTS         = array( '', 'system', 'humanist', 'geometric', 'serif', 'mono', 'rounded' );
	const TYPES         = array( 'standard', 'free_shipping', 'tiers' );
	const MAX_TIERS     = 5;
	const MAX_ZONES     = 50;
	const MAX_IDS       = 200;
	const MAX_TOKENS    = 20;
	const MAX_CSS       = 10000;
	const MAX_EXTRA     = 160;
	const MAX_TIER_TEXT = 300;

	/** Per-language texts kept in the "extra" JSON column. */
	const EXTRA_TEXTS = 'email_placeholder,email_button,email_consent,email_success';

	/**
	 * System font stacks only (no web fonts, nothing is downloaded). '' = the theme's font.
	 *
	 * @param string $font Font key.
	 * @return string
	 */
	public static function font_stack( $font ) {
		$stacks = array(
			'system'    => 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif',
			'humanist'  => 'Seravek,"Gill Sans Nova",Ubuntu,Calibri,"DejaVu Sans",source-sans-pro,sans-serif',
			'geometric' => 'Avenir,Montserrat,Corbel,"URW Gothic",source-sans-pro,sans-serif',
			'serif'     => 'Charter,"Bitstream Charter","Sitka Text",Cambria,Georgia,serif',
			'mono'      => 'ui-monospace,"Cascadia Code","Source Code Pro",Menlo,Consolas,"DejaVu Sans Mono",monospace',
			'rounded'   => 'ui-rounded,"Hiragino Maru Gothic ProN",Quicksand,Comfortaa,Manjari,"Arial Rounded MT","Arial Rounded MT Bold",Calibri,source-sans-pro,sans-serif',
		);

		return isset( $stacks[ $font ] ) ? $stacks[ $font ] : '';
	}

	/**
	 * Options of a message that uses none of the optional features.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'countries_mode'    => 'all',
			'countries'         => array(),
			'fs_wc'             => 0,
			'fs_zones'          => array(),
			'fs_currencies'     => array(),
			'coupon_id'         => 0,
			'tiers'             => array(),
			'cart_subtotal_min' => '',
			'cart_subtotal_max' => '',
			'cart_qty_min'      => '',
			'cart_qty_max'      => '',
			'cart_products'     => array(),
			'cart_categories'   => array(),
			'utm_source'        => '',
			'utm_medium'        => '',
			'utm_campaign'      => '',
			'referrer'          => '',
			'visitor'           => 'all',
			'trigger'           => 'none',
			'trigger_value'     => 0,
			'recur'             => '',
			'recur_time'        => '',
			'recur_day'         => 1,
			'recur_skip'        => '',
			'email'             => 0,
			'image'             => 0,
			'overlay'           => 55,
			'font'              => '',
		);
	}

	/**
	 * Stored JSON (or an array) → complete, valid options. Never throws; anything invalid becomes neutral.
	 *
	 * @param mixed $json Stored options.
	 * @return array
	 */
	public function options( $json ) {
		$data = is_array( $json ) ? $json : json_decode( (string) $json, true );

		if ( ! is_array( $data ) ) {
			return $this->defaults();
		}

		list( $clean ) = $this->validate( $data, null, array_keys( isset( $data['fs_currencies'] ) && is_array( $data['fs_currencies'] ) ? $data['fs_currencies'] : array() ), null );

		return $clean;
	}

	/**
	 * Options as stored (JSON).
	 *
	 * @param array $options Clean options.
	 * @return string
	 */
	public function encode( array $options ) {
		$json = wp_json_encode( $options );

		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * True when the message uses none of the optional features.
	 *
	 * @param array $o Options.
	 * @return bool
	 */
	public function is_neutral( array $o ) {
		return $o == $this->defaults(); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- array content comparison (order-insensitive keys).
	}

	/**
	 * Validates and cleans posted options. $lookup( $kind, $values ) returns the existing values only — kinds:
	 * country (ISO codes), state ("CC:STATE" pairs), coupon, product, category (IDs), image (ID → bool).
	 * Null skips the existence checks (stored data). $currencies: the shop's currency codes; $type: message type
	 * (null keeps tiers as stored). Returns array( clean options, errors by field ).
	 *
	 * @param mixed         $p          Posted options.
	 * @param callable|null $lookup     Existence check.
	 * @param array         $currencies Currency codes.
	 * @param string|null   $type       Message type.
	 * @return array
	 */
	public function validate( $p, $lookup = null, array $currencies = array(), $type = 'standard' ) {
		$p   = is_array( $p ) ? $p : array();
		$o   = $this->defaults();
		$err = array();

		$str    = static function ( $key, $max = 255 ) use ( $p ) {
			return isset( $p[ $key ] ) && is_scalar( $p[ $key ] ) ? trim( substr( (string) $p[ $key ], 0, $max ) ) : '';
		};
		$flag   = static function ( $key ) use ( $p ) {
			return ! empty( $p[ $key ] ) && is_scalar( $p[ $key ] ) && '0' !== (string) $p[ $key ] ? 1 : 0;
		};
		$exists = static function ( $kind, array $values ) use ( $lookup ) {
			if ( ! $values || ! is_callable( $lookup ) ) {
				return true;
			}

			$found = call_user_func( $lookup, $kind, $values );

			return is_array( $found ) && count( array_intersect( $values, $found ) ) === count( $values );
		};

		// Countries (visitor's country: WooCommerce customer address, CloudFlare header or store country).
		$mode                = $str( 'countries_mode', 16 );
		$o['countries_mode'] = in_array( $mode, array( 'all', 'include', 'exclude' ), true ) ? $mode : 'all';
		list( $codes, $bad ) = $this->country_codes( isset( $p['countries'] ) ? $p['countries'] : array(), 300 );
		if ( $bad || ! $exists( 'country', $codes ) ) {
			$err['countries'] = 'error_countries';
		}
		$o['countries'] = $codes;
		if ( 'all' !== $o['countries_mode'] && ! $codes ) {
			$err['countries'] = 'error_countries_required';
		}
		if ( '' !== $mode && $o['countries_mode'] !== $mode ) {
			$err['countries'] = 'error_value';
		}

		// Free shipping: WooCommerce's own minimum, per-country / per-state amounts, fixed amounts per currency.
		$o['fs_wc'] = $flag( 'fs_wc' );

		$zones = isset( $p['fs_zones'] ) && is_array( $p['fs_zones'] ) ? array_values( $p['fs_zones'] ) : array();
		$seen  = array();
		foreach ( $zones as $row ) {
			if ( ! is_array( $row ) ) {
				$err['fs_zones'] = 'error_value';
				continue;
			}
			$c = isset( $row['country'] ) && is_scalar( $row['country'] ) ? strtoupper( trim( (string) $row['country'] ) ) : '';
			$s = isset( $row['state'] ) && is_scalar( $row['state'] ) ? strtoupper( trim( (string) $row['state'] ) ) : '';
			$a = isset( $row['amount'] ) && is_scalar( $row['amount'] ) ? trim( (string) $row['amount'] ) : '';

			if ( '' === $c && '' === $a ) {
				continue; // Empty row of the form.
			}

			$amount = $this->amount( $a );

			if ( ! preg_match( '/^[A-Z]{2}$/', $c ) || ( '' !== $s && ! preg_match( '/^[A-Z0-9\-]{1,10}$/', $s ) ) || false === $amount || (float) $amount <= 0 ) {
				$err['fs_zones'] = 'error_fs_zones';
				continue;
			}

			$key = $c . ':' . $s;
			if ( isset( $seen[ $key ] ) ) {
				$err['fs_zones'] = 'error_fs_zones_duplicate';
				continue;
			}
			$seen[ $key ] = true;

			$o['fs_zones'][] = array(
				'country' => $c,
				'state'   => $s,
				'amount'  => $amount,
			);
		}
		if ( count( $o['fs_zones'] ) > self::MAX_ZONES ) {
			$err['fs_zones'] = 'error_fs_zones';
			$o['fs_zones']   = array_slice( $o['fs_zones'], 0, self::MAX_ZONES );
		}
		if ( $o['fs_zones'] && is_callable( $lookup ) ) {
			$countries = array_values( array_unique( array_column( $o['fs_zones'], 'country' ) ) );
			$states    = array();
			foreach ( $o['fs_zones'] as $row ) {
				if ( '' !== $row['state'] ) {
					$states[] = $row['country'] . ':' . $row['state'];
				}
			}

			if ( ! $exists( 'country', $countries ) || ! $exists( 'state', array_values( array_unique( $states ) ) ) ) {
				$err['fs_zones'] = 'error_fs_zones';
			}
		}

		$cur = isset( $p['fs_currencies'] ) && is_array( $p['fs_currencies'] ) ? $p['fs_currencies'] : array();
		foreach ( $cur as $code => $value ) {
			$code  = strtoupper( (string) $code );
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			if ( '' === $value ) {
				continue;
			}

			$amount = $this->amount( $value );

			if ( ! preg_match( '/^[A-Z]{3}$/', $code ) || ! in_array( $code, $currencies, true ) || false === $amount || (float) $amount <= 0 ) {
				$err['fs_currencies'] = 'error_fs_currencies';
				continue;
			}

			$o['fs_currencies'][ $code ] = $amount;
		}
		ksort( $o['fs_currencies'] );

		// WooCommerce coupon (code, expiry and usage limits come from the coupon itself).
		$coupon = $str( 'coupon_id', 12 );
		if ( '' !== $coupon && '0' !== $coupon ) {
			if ( ! preg_match( '/^\d{1,10}$/', $coupon ) || ! $exists( 'coupon', array( (int) $coupon ) ) ) {
				$err['coupon_id'] = 'error_coupon_id';
			} else {
				$o['coupon_id'] = (int) $coupon;
			}
		}

		// Tiered goals: 2–5 rising amounts (only for the "tiers" message type).
		$tiers = isset( $p['tiers'] ) && is_array( $p['tiers'] ) ? array_values( $p['tiers'] ) : array();
		$prev  = 0.0;
		foreach ( array_slice( $tiers, 0, self::MAX_TIERS + 1 ) as $value ) {
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			if ( '' === $value ) {
				continue;
			}

			$amount = $this->amount( $value );

			if ( false === $amount || (float) $amount <= $prev ) {
				$err['tiers'] = 'error_tiers_order';
				continue;
			}

			$prev         = (float) $amount;
			$o['tiers'][] = $amount;
		}
		if ( count( $tiers ) > self::MAX_TIERS || count( $o['tiers'] ) > self::MAX_TIERS ) {
			$err['tiers'] = 'error_tiers_count';
			$o['tiers']   = array_slice( $o['tiers'], 0, self::MAX_TIERS );
		}
		if ( 'tiers' === $type && count( $o['tiers'] ) < 2 && ! isset( $err['tiers'] ) ) {
			$err['tiers'] = 'error_tiers_count';
		}
		if ( 'tiers' !== $type && null !== $type ) {
			$o['tiers'] = array();
		}

		// Cart conditions.
		foreach ( array( 'cart_subtotal_min', 'cart_subtotal_max' ) as $key ) {
			$value = $str( $key, 32 );

			if ( '' === $value ) {
				continue;
			}

			$amount = $this->amount( $value );

			if ( false === $amount ) {
				$err[ $key ] = 'error_amount';
			} else {
				$o[ $key ] = $amount;
			}
		}
		foreach ( array( 'cart_qty_min', 'cart_qty_max' ) as $key ) {
			$value = $str( $key, 12 );

			if ( '' === $value ) {
				continue;
			}

			if ( ! preg_match( '/^\d{1,6}$/', $value ) ) {
				$err[ $key ] = 'error_quantity';
			} else {
				$o[ $key ] = (string) (int) $value;
			}
		}
		if ( '' !== $o['cart_subtotal_min'] && '' !== $o['cart_subtotal_max'] && (float) $o['cart_subtotal_min'] > (float) $o['cart_subtotal_max'] ) {
			$err['cart_subtotal_max'] = 'error_min_max';
		}
		if ( '' !== $o['cart_qty_min'] && '' !== $o['cart_qty_max'] && (int) $o['cart_qty_min'] > (int) $o['cart_qty_max'] ) {
			$err['cart_qty_max'] = 'error_min_max';
		}
		foreach ( array(
			'cart_products'   => 'product',
			'cart_categories' => 'category',
		) as $key => $kind ) {
			list( $ids, $bad ) = $this->ids( isset( $p[ $key ] ) ? $p[ $key ] : array(), self::MAX_IDS );

			if ( $bad || ! $exists( $kind, $ids ) ) {
				$err[ $key ] = 'error_' . $key;
			}

			$o[ $key ] = $ids;
		}

		// Campaign / referrer / new or returning visitor (decided in the visitor's browser).
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $key ) {
			$list = $this->tokens( $str( $key, 2000 ), '/^[a-z0-9._~+\-]{1,64}$/' );

			if ( false === $list ) {
				$err[ $key ] = 'error_utm';
			} else {
				$o[ $key ] = implode( ',', $list );
			}
		}
		$hosts = array();
		$ok    = true;
		foreach ( preg_split( '/[\s,]+/', strtolower( $str( 'referrer', 2000 ) ) ) as $host ) {
			if ( '' === $host ) {
				continue;
			}
			if ( preg_match( '#^[a-z][a-z0-9+.\-]*://#', $host ) ) {
				$parsed = wp_parse_url( $host, PHP_URL_HOST );
				$host   = is_string( $parsed ) ? $parsed : '';
			}
			$host = (string) preg_replace( '/^www\./', '', rtrim( $host, '/' ) );
			if ( ! preg_match( '/^(?=.{1,253}$)([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host ) ) {
				$ok = false;
				continue;
			}
			$hosts[ $host ] = $host;
		}
		if ( ! $ok || count( $hosts ) > self::MAX_TOKENS ) {
			$err['referrer'] = 'error_referrer';
		}
		$o['referrer'] = implode( ',', array_slice( array_values( $hosts ), 0, self::MAX_TOKENS ) );

		$visitor      = $str( 'visitor', 16 );
		$o['visitor'] = in_array( $visitor, self::VISITORS, true ) ? $visitor : 'all';
		if ( '' !== $visitor && $o['visitor'] !== $visitor ) {
			$err['visitor'] = 'error_value';
		}

		// Trigger: after a delay (s), after scrolling (%), on exit intent (desktop).
		$trigger      = $str( 'trigger', 16 );
		$o['trigger'] = in_array( $trigger, self::TRIGGERS, true ) ? $trigger : 'none';
		$value        = $str( 'trigger_value', 6 );
		if ( '' !== $trigger && $o['trigger'] !== $trigger ) {
			$err['trigger'] = 'error_value';
		} elseif ( 'delay' === $o['trigger'] || 'scroll' === $o['trigger'] ) {
			$max = 'delay' === $o['trigger'] ? 600 : 100;

			if ( ! preg_match( '/^\d{1,4}$/', $value ) || (int) $value < 1 || (int) $value > $max ) {
				$err['trigger']     = 'delay' === $o['trigger'] ? 'error_trigger_delay' : 'error_trigger_scroll';
				$o['trigger_value'] = 'delay' === $o['trigger'] ? 5 : 50;
			} else {
				$o['trigger_value'] = (int) $value;
			}
		}

		// Recurring countdown: daily / weekly cut-off in site time, with days to skip.
		$recur      = $str( 'recur', 16 );
		$o['recur'] = in_array( $recur, self::RECUR, true ) ? $recur : '';
		if ( '' !== $recur && $o['recur'] !== $recur ) {
			$err['recur'] = 'error_value';
		} elseif ( '' !== $o['recur'] ) {
			$time = $str( 'recur_time', 8 );

			if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
				$err['recur'] = 'error_recur_time';
				$o['recur']   = '';
			} else {
				$o['recur_time'] = $time;
			}

			$day            = $str( 'recur_day', 2 );
			$o['recur_day'] = preg_match( '/^[1-7]$/', $day ) ? (int) $day : 1;
			if ( 'weekly' === $o['recur'] && '' !== $day && ! preg_match( '/^[1-7]$/', $day ) ) {
				$err['recur'] = 'error_value';
			}

			$skip = isset( $p['recur_skip'] ) ? $p['recur_skip'] : '';
			$skip = is_array( $skip ) ? implode( '', array_filter( $skip, 'is_scalar' ) ) : ( is_scalar( $skip ) ? (string) $skip : '' );
			$days = array_unique( str_split( (string) preg_replace( '/[^1-7]/', '', $skip ) ) );
			sort( $days );
			$o['recur_skip'] = 'daily' === $o['recur'] ? implode( '', $days ) : '';
			if ( 7 === strlen( $o['recur_skip'] ) ) {
				$err['recur']    = 'error_recur_skip';
				$o['recur_skip'] = '';
			}
		}

		// E-mail capture (newsletter, double opt-in).
		$o['email'] = $flag( 'email' );

		// Look: background image from the media library, overlay, font stack.
		$image = $str( 'image', 12 );
		if ( '' !== $image && '0' !== $image ) {
			if ( ! preg_match( '/^\d{1,10}$/', $image ) || ( is_callable( $lookup ) && ! call_user_func( $lookup, 'image', (int) $image ) ) ) {
				$err['image'] = 'error_image';
			} else {
				$o['image'] = (int) $image;
			}
		}
		$overlay = $str( 'overlay', 3 );
		if ( '' !== $overlay ) {
			if ( ! preg_match( '/^\d{1,2}$/', $overlay ) || (int) $overlay > 90 ) {
				$err['overlay'] = 'error_overlay';
			} else {
				$o['overlay'] = (int) $overlay;
			}
		}
		$font      = $str( 'font', 16 );
		$o['font'] = in_array( $font, self::FONTS, true ) ? $font : '';
		if ( '' !== $font && $o['font'] !== $font ) {
			$err['font'] = 'error_value';
		}

		return array( $o, $err );
	}

	/**
	 * Per-language extra texts (tier labels and texts, e-mail form texts) → clean array; $errors gets error keys.
	 *
	 * @param mixed $row        Posted texts of one language.
	 * @param array $tier_index Indexes of the tier rows that have an amount.
	 * @param array $errors     Errors (by reference).
	 * @return array
	 */
	public function extra( $row, array $tier_index, &$errors ) {
		$row = is_array( $row ) ? $row : array();
		$out = array(
			'tier_label' => array(),
			'tier_text'  => array(),
		);

		foreach ( array(
			'tier_label' => self::MAX_EXTRA,
			'tier_text'  => self::MAX_TIER_TEXT,
		) as $key => $max ) {
			$list = isset( $row[ $key ] ) && is_array( $row[ $key ] ) ? array_values( $row[ $key ] ) : array();

			foreach ( $tier_index as $i ) {
				$value = isset( $list[ $i ] ) && is_scalar( $list[ $i ] ) ? $this->plain_text( (string) $list[ $i ] ) : '';

				if ( $this->len( $value ) > $max ) {
					$errors[ $key ] = 'error_extra_length';
					$value          = $this->cut( $value, $max );
				}

				$out[ $key ][] = $value;
			}
		}

		foreach ( explode( ',', self::EXTRA_TEXTS ) as $key ) {
			$value = isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? $this->plain_text( (string) $row[ $key ] ) : '';

			if ( $this->len( $value ) > ( 'email_consent' === $key ? self::MAX_TIER_TEXT : self::MAX_EXTRA ) ) {
				$errors[ $key ] = 'error_extra_length';
				$value          = $this->cut( $value, self::MAX_TIER_TEXT );
			}

			$out[ $key ] = $value;
		}

		return $out;
	}

	/**
	 * Stored extra JSON → array with every key present.
	 *
	 * @param mixed $json Stored value.
	 * @return array
	 */
	public function extra_of( $json ) {
		$data = is_array( $json ) ? $json : json_decode( (string) $json, true );
		$data = is_array( $data ) ? $data : array();
		$out  = array(
			'tier_label' => array(),
			'tier_text'  => array(),
		);

		foreach ( array( 'tier_label', 'tier_text' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				foreach ( array_slice( array_values( $data[ $key ] ), 0, self::MAX_TIERS ) as $value ) {
					$out[ $key ][] = is_scalar( $value ) ? $this->plain_text( (string) $value ) : '';
				}
			}
		}

		foreach ( explode( ',', self::EXTRA_TEXTS ) as $key ) {
			$out[ $key ] = isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? $this->plain_text( (string) $data[ $key ] ) : '';
		}

		return $out;
	}

	/**
	 * Indexes of the tier rows that have an amount (labels / texts of empty rows are dropped with them).
	 *
	 * @param mixed $posted Posted tier amounts.
	 * @return int[]
	 */
	public function tier_indexes( $posted ) {
		$out = array();

		foreach ( array_slice( is_array( $posted ) ? array_values( $posted ) : array(), 0, self::MAX_TIERS ) as $i => $value ) {
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$out[] = $i;
			}
		}

		return $out;
	}

	/*
	 * ------------------------------------------------------------------
	 * Storefront decisions
	 * ------------------------------------------------------------------
	 */

	/**
	 * Country targeting. '' = unknown country: "only in" lists do not match, "except in" lists do.
	 *
	 * @param array  $o       Options.
	 * @param string $country ISO code.
	 * @return bool
	 */
	public function country_matches( array $o, $country ) {
		if ( 'all' === $o['countries_mode'] || ! $o['countries'] ) {
			return true;
		}

		$in = '' !== (string) $country && in_array( strtoupper( (string) $country ), $o['countries'], true );

		return 'include' === $o['countries_mode'] ? $in : ! $in;
	}

	/**
	 * Free-shipping amount for this visitor: a state amount, else a country amount (both in the shop currency), else a
	 * fixed amount in the visitor's currency, else $base. Returns array( amount, currency code or '' = shop currency ).
	 *
	 * @param array  $o        Options.
	 * @param float  $base     Amount of the message (or WooCommerce's minimum).
	 * @param string $country  ISO code.
	 * @param string $state    State code.
	 * @param string $currency Visitor's currency.
	 * @return array
	 */
	public function threshold( array $o, $base, $country, $state, $currency ) {
		$whole   = null;
		$country = strtoupper( (string) $country );
		$state   = strtoupper( (string) $state );

		foreach ( $o['fs_zones'] as $row ) {
			if ( '' !== $country && $row['country'] === $country ) {
				if ( '' !== $row['state'] && $row['state'] === $state ) {
					return array( (float) $row['amount'], '' );
				}

				if ( '' === $row['state'] && null === $whole ) {
					$whole = (float) $row['amount'];
				}
			}
		}

		if ( null !== $whole ) {
			return array( $whole, '' );
		}

		if ( is_string( $currency ) && isset( $o['fs_currencies'][ $currency ] ) ) {
			return array( (float) $o['fs_currencies'][ $currency ], $currency );
		}

		return array( (float) $base, '' );
	}

	/**
	 * Tiered goals: how many tiers the subtotal reached, the next tier (index or -1), progress 0–100 and markers (%).
	 *
	 * @param array $tiers    Amounts.
	 * @param float $subtotal Cart subtotal.
	 * @return array
	 */
	public function tier_state( array $tiers, $subtotal ) {
		$tiers = array_values( array_map( 'floatval', $tiers ) );
		$max   = $tiers ? max( $tiers ) : 0;
		$done  = 0;

		foreach ( $tiers as $amount ) {
			if ( $subtotal >= $amount ) {
				++$done;
			}
		}

		$marks = array();
		foreach ( $tiers as $amount ) {
			$marks[] = $max > 0 ? round( $amount * 100 / $max, 2 ) : 0;
		}

		return array(
			'done'  => $done,
			'next'  => $done < count( $tiers ) ? $done : -1,
			'pct'   => $max > 0 ? (int) min( 100, floor( $subtotal * 100 / $max ) ) : 0,
			'marks' => $marks,
		);
	}

	/**
	 * True when the message uses cart conditions.
	 *
	 * @param array $o Options.
	 * @return bool
	 */
	public function has_cart_rules( array $o ) {
		return '' !== $o['cart_subtotal_min'] || '' !== $o['cart_subtotal_max'] || '' !== $o['cart_qty_min'] || '' !== $o['cart_qty_max'] || $o['cart_products'] || $o['cart_categories'];
	}

	/**
	 * Cart conditions: every condition that is set must hold.
	 *
	 * @param array $o            Options.
	 * @param float $subtotal     Cart subtotal.
	 * @param int   $quantity     Number of items.
	 * @param array $product_ids  Products in the cart.
	 * @param array $category_ids Categories of those products.
	 * @return bool
	 */
	public function cart_matches( array $o, $subtotal, $quantity, array $product_ids, array $category_ids ) {
		if ( '' !== $o['cart_subtotal_min'] && $subtotal < (float) $o['cart_subtotal_min'] ) {
			return false;
		}

		if ( '' !== $o['cart_subtotal_max'] && $subtotal > (float) $o['cart_subtotal_max'] ) {
			return false;
		}

		if ( '' !== $o['cart_qty_min'] && $quantity < (int) $o['cart_qty_min'] ) {
			return false;
		}

		if ( '' !== $o['cart_qty_max'] && $quantity > (int) $o['cart_qty_max'] ) {
			return false;
		}

		if ( $o['cart_products'] || $o['cart_categories'] ) {
			$hit = ( $o['cart_products'] && array_intersect( $o['cart_products'], array_map( 'intval', $product_ids ) ) )
				|| ( $o['cart_categories'] && array_intersect( $o['cart_categories'], array_map( 'intval', $category_ids ) ) );

			if ( ! $hit ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * True when the visitor's browser decides (campaign, referrer, new / returning visitor).
	 *
	 * @param array $o Options.
	 * @return bool
	 */
	public function has_client_rules( array $o ) {
		return '' !== $o['utm_source'] || '' !== $o['utm_medium'] || '' !== $o['utm_campaign'] || '' !== $o['referrer'] || 'all' !== $o['visitor'];
	}

	/**
	 * Campaign rules for the browser (data-utm): {s, m, c, r} as lists.
	 *
	 * @param array $o Options.
	 * @return array
	 */
	public function client_rules( array $o ) {
		$list = static function ( $v ) {
			return '' === $v ? array() : explode( ',', $v );
		};

		return array(
			's' => $list( $o['utm_source'] ),
			'm' => $list( $o['utm_medium'] ),
			'c' => $list( $o['utm_campaign'] ),
			'r' => $list( $o['referrer'] ),
		);
	}

	/**
	 * Value of data-trig: "delay:5", "scroll:50", "exit" or ''.
	 *
	 * @param array $o Options.
	 * @return string
	 */
	public function trigger_attr( array $o ) {
		if ( 'delay' === $o['trigger'] || 'scroll' === $o['trigger'] ) {
			return $o['trigger'] . ':' . (int) $o['trigger_value'];
		}

		return 'exit' === $o['trigger'] ? 'exit' : '';
	}

	/**
	 * Value of data-recur: "d|14:00|67" (daily, skip Sat + Sun) or "w|14:00|5" (weekly on Friday).
	 *
	 * @param array $o Options.
	 * @return string
	 */
	public function recur_attr( array $o ) {
		if ( 'daily' === $o['recur'] ) {
			return 'd|' . $o['recur_time'] . '|' . $o['recur_skip'];
		}

		return 'weekly' === $o['recur'] ? 'w|' . $o['recur_time'] . '|' . (int) $o['recur_day'] : '';
	}

	/**
	 * Next cut-off (Unix time) of a recurring countdown after $now, in $tz (the site's time zone). 0 if none.
	 *
	 * @param array              $o   Options.
	 * @param int|null           $now Unix time.
	 * @param \DateTimeZone|null $tz  Time zone.
	 * @return int
	 */
	public function next_cutoff( array $o, $now = null, $tz = null ) {
		$now = null === $now ? time() : (int) $now;
		$tz  = $tz instanceof \DateTimeZone ? $tz : new \DateTimeZone( 'UTC' );

		if ( '' === $o['recur'] || ! preg_match( '/^(\d{2}):(\d{2})$/', $o['recur_time'], $m ) ) {
			return 0;
		}

		$today = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( $tz );

		for ( $i = 0; $i < 8; $i++ ) {
			$at = $today->modify( '+' . $i . ' day' )->setTime( (int) $m[1], (int) $m[2], 0 );

			if ( $at->getTimestamp() <= $now ) {
				continue;
			}

			$dow = (int) $at->format( 'N' );

			if ( 'weekly' === $o['recur'] ? (int) $o['recur_day'] === $dow : false === strpos( $o['recur_skip'], (string) $dow ) ) {
				return $at->getTimestamp();
			}
		}

		return 0;
	}

	/*
	 * ------------------------------------------------------------------
	 * Custom CSS, form tokens, CSV, e-mail
	 * ------------------------------------------------------------------
	 */

	/**
	 * Custom CSS, safe inside <style>: no "<", no CSS escapes, no @import / @charset, no javascript: / expression() /
	 * behavior / -moz-binding, url() only for http(s), site paths and data:image.
	 *
	 * @param mixed $value CSS.
	 * @return string
	 */
	public function css( $value ) {
		$css = substr( str_replace( "\0", '', (string) $value ), 0, self::MAX_CSS );
		$css = (string) preg_replace( '#<!--|-->|</?[a-z!][^>]*>?#i', '', $css );
		$css = str_replace( array( '<', '\\' ), '', $css );

		do {
			$before = $css;
			$css    = (string) preg_replace( '/@(import|charset|namespace)\b[^;{}]*;?/i', '', $css );
			$css    = (string) preg_replace( '/(javascript|vbscript|livescript)\s*:/i', '', $css );
			$css    = (string) preg_replace( '/(expression|behaviou?r|-moz-binding)\s*[:(]?/i', '', $css );
			$css    = (string) preg_replace( '/((-webkit-)?image-set|(?<![a-z-])image|(?<![a-z-])src)\s*\(/i', 'none(', $css ); // String forms that could load outside files.
			$css    = (string) preg_replace_callback(
				'/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/is',
				static function ( $m ) {
					$u = trim( $m[2] );

					return preg_match( '#^(https?://|/(?!/)|data:image/(png|gif|jpe?g|webp|svg\+xml)[;,])#i', $u ) && ! preg_match( '/[\'"()\s]/', $u ) ? 'url("' . $u . '")' : 'none';
				},
				$css
			);
		} while ( $css !== $before );

		return trim( $css );
	}

	/**
	 * Form token "time.hmac" bound to $context (no server-side state needed to check it).
	 *
	 * @param string   $secret  Site secret.
	 * @param string   $context Binding value.
	 * @param int|null $time    Unix time.
	 * @return string
	 */
	public function token( $secret, $context, $time = null ) {
		$time = null === $time ? time() : (int) $time;

		return $time . '.' . substr( hash_hmac( 'sha256', $time . '|' . (string) $context, (string) $secret ), 0, 32 );
	}

	/**
	 * Valid token: right context, at least $min_age seconds old (bots submit at once), at most 2 days old.
	 *
	 * @param mixed    $token   Token.
	 * @param string   $secret  Site secret.
	 * @param string   $context Binding value.
	 * @param int|null $now     Unix time.
	 * @param int      $min_age Seconds.
	 * @return bool
	 */
	public function token_valid( $token, $secret, $context, $now = null, $min_age = 2 ) {
		$now = null === $now ? time() : (int) $now;

		if ( ! is_string( $token ) || ! preg_match( '/^(\d{9,11})\.[0-9a-f]{32}$/', $token, $m ) || '' === (string) $secret ) {
			return false;
		}

		$age = $now - (int) $m[1];

		return $age >= $min_age && $age <= 172800 && hash_equals( $this->token( $secret, $context, (int) $m[1] ), $token );
	}

	/**
	 * CSV cell safe for spreadsheets (no formula injection).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public function csv_cell( $value ) {
		$value = str_replace( array( "\r", "\n" ), ' ', (string) $value );

		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t" ), true ) ) {
			$value = "'" . $value;
		}

		return '"' . str_replace( '"', '""', $value ) . '"';
	}

	/**
	 * Valid e-mail address (at most 96 characters). FILTER_VALIDATE_EMAIL accepts quoted local parts such as
	 * "a\<LF>X-Injected:1"@a.co, which would reach the mail headers: any control character, quote or backslash is
	 * refused first.
	 *
	 * @param mixed $value Address.
	 * @return string '' when invalid.
	 */
	public function email( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value || preg_match( '/[\x00-\x1F\x7F"\\\\]/', $value ) ) {
			return '';
		}

		return strlen( $value ) <= 96 && filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : '';
	}

	/**
	 * Text for a mail header: control characters (CR / LF …) become spaces.
	 *
	 * @param mixed $value Text.
	 * @return string
	 */
	public function header_text( $value ) {
		return trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string) $value ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * CloudFlare: its headers are trusted only on requests that really come from CloudFlare
	 * ------------------------------------------------------------------
	 */

	/** CloudFlare's published ranges (https://www.cloudflare.com/ips-v4 and /ips-v6), as of 2026-11-07. */
	const CF_IPV4 = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
	);
	const CF_IPV6 = array( '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32' );

	/**
	 * True when $ip (IPv4 or IPv6) is inside $cidr of the same family.
	 *
	 * @param string $ip   Address.
	 * @param string $cidr Range.
	 * @return bool
	 */
	public function ip_in_cidr( $ip, $cidr ) {
		$parts = explode( '/', (string) $cidr, 2 );
		$addr  = @inet_pton( $this->plain_ip( $ip ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input gives false.
		$net   = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input gives false.

		if ( false === $addr || false === $net || strlen( $addr ) !== strlen( $net ) || ! isset( $parts[1] ) || ! preg_match( '/^\d{1,3}$/', $parts[1] ) ) {
			return false;
		}

		$bits = (int) $parts[1];
		if ( $bits > strlen( $addr ) * 8 ) {
			return false;
		}

		$bytes = intdiv( $bits, 8 );
		if ( $bytes && substr( $addr, 0, $bytes ) !== substr( $net, 0, $bytes ) ) {
			return false;
		}

		$rest = $bits % 8;
		if ( $rest ) {
			$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;

			return ( ord( $addr[ $bytes ] ) & $mask ) === ( ord( $net[ $bytes ] ) & $mask );
		}

		return true;
	}

	/**
	 * True when $ip belongs to CloudFlare.
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	public function is_cloudflare( $ip ) {
		$ip = $this->plain_ip( $ip );

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		foreach ( false === strpos( $ip, ':' ) ? self::CF_IPV4 : self::CF_IPV6 as $cidr ) {
			if ( $this->ip_in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Visitor IP: REMOTE_ADDR, or CF-Connecting-IP when $use_cf is on AND the request comes from CloudFlare.
	 *
	 * @param array $server $_SERVER.
	 * @param bool  $use_cf Setting.
	 * @return string
	 */
	public function client_ip( array $server, $use_cf ) {
		$remote = isset( $server['REMOTE_ADDR'] ) && is_string( $server['REMOTE_ADDR'] ) ? $server['REMOTE_ADDR'] : '';

		if ( $use_cf && isset( $server['HTTP_CF_CONNECTING_IP'] ) && is_string( $server['HTTP_CF_CONNECTING_IP'] ) && filter_var( $server['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP ) && $this->is_cloudflare( $remote ) ) {
			return $server['HTTP_CF_CONNECTING_IP'];
		}

		return $remote;
	}

	/**
	 * Visitor country from CF-IPCountry (upper case), only on requests from CloudFlare; else ''.
	 *
	 * @param array $server $_SERVER.
	 * @param bool  $use_cf Setting.
	 * @return string
	 */
	public function cf_country( array $server, $use_cf ) {
		if ( ! $use_cf || ! isset( $server['HTTP_CF_IPCOUNTRY'], $server['REMOTE_ADDR'] ) || ! is_string( $server['HTTP_CF_IPCOUNTRY'] ) || ! is_string( $server['REMOTE_ADDR'] ) || ! $this->is_cloudflare( $server['REMOTE_ADDR'] ) ) {
			return '';
		}

		$iso = strtoupper( $server['HTTP_CF_IPCOUNTRY'] );

		return preg_match( '/^[A-Z]{2}$/', $iso ) && 'XX' !== $iso && 'T1' !== $iso ? $iso : '';
	}

	/**
	 * "::ffff:1.2.3.4" (IPv4 on an IPv6 socket) → "1.2.3.4".
	 *
	 * @param string $ip Address.
	 * @return string
	 */
	private function plain_ip( $ip ) {
		$ip = trim( (string) $ip );

		return 0 === stripos( $ip, '::ffff:' ) && false !== strpos( $ip, '.' ) ? substr( $ip, 7 ) : $ip;
	}

	/*
	 * ------------------------------------------------------------------
	 * Ready-made templates (texts by language code; other languages get English)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Templates of the gallery.
	 *
	 * @return array
	 */
	public function templates() {
		$t = static function ( $en, $lv, $ru ) {
			return array(
				'en' => $en,
				'lv' => $lv,
				'ru' => $ru,
			);
		};

		return array(
			'free_shipping' => array(
				'icon'         => 'truck',
				'bg'           => '#0f766e',
				'fg'           => '#ffffff',
				'type'         => 'free_shipping',
				'threshold'    => '50',
				'commerce'     => 1,
				'name'         => $t( 'Free shipping progress', 'Bezmaksas piegādes progress', 'Прогресс до бесплатной доставки' ),
				'text'         => $t( 'Add {remaining} more to your cart for free shipping', 'Pievieno grozam vēl {remaining} un saņem bezmaksas piegādi', 'Добавьте в корзину ещё {remaining} — и доставка будет бесплатной' ),
				'success_text' => $t( 'You have unlocked free shipping!', 'Tev ir bezmaksas piegāde!', 'Вам доступна бесплатная доставка!' ),
			),
			'tiers'         => array(
				'icon'         => 'gift',
				'bg'           => '#4338ca',
				'fg'           => '#ffffff',
				'type'         => 'tiers',
				'tiers'        => array( '50', '100', '150' ),
				'commerce'     => 1,
				'name'         => $t( 'Reward tiers', 'Bonusu pakāpes', 'Уровни бонусов' ),
				'text'         => $t( 'Add {remaining} more to unlock: {goal}', 'Pievieno vēl {remaining} un saņem: {goal}', 'Добавьте ещё {remaining} и получите: {goal}' ),
				'success_text' => $t( 'Every reward unlocked — thank you!', 'Visi bonusi saņemti — paldies!', 'Все бонусы получены — спасибо!' ),
				'tier_label'   => array(
					$t( 'Free shipping', 'Bezmaksas piegāde', 'Бесплатная доставка' ),
					$t( 'Free gift', 'Dāvana', 'Подарок' ),
					$t( '10% off', '10% atlaide', 'Скидка 10%' ),
				),
			),
			'flash_sale'    => array(
				'icon'      => 'bolt',
				'bg'        => '#b91c1c',
				'fg'        => '#ffffff',
				'countdown' => 1,
				'end_days'  => 2,
				'style'     => 'gradient',
				'name'      => $t( 'Flash sale with countdown', 'Zibakcija ar laika atskaiti', 'Распродажа с таймером' ),
				'text'      => $t( 'Flash sale: up to 30% off — ends in {countdown}', 'Zibakcija: līdz −30% — beidzas pēc {countdown}', 'Распродажа: скидки до 30% — до конца {countdown}' ),
				'link_text' => $t( 'Shop now', 'Iepirkties', 'В магазин' ),
			),
			'same_day'      => array(
				'icon'       => 'clock',
				'bg'         => '#1e3a8a',
				'fg'         => '#ffffff',
				'countdown'  => 1,
				'recur'      => 'daily',
				'recur_time' => '14:00',
				'recur_skip' => '67',
				'name'       => $t( 'Same-day dispatch cut-off', 'Nosūtīšana tajā pašā dienā', 'Отправка в тот же день' ),
				'text'       => $t( 'Order within {countdown} for same-day dispatch', 'Pasūti {countdown} laikā — nosūtīsim vēl šodien', 'Закажите в течение {countdown} — отправим сегодня' ),
			),
			'coupon'        => array(
				'icon'   => 'ticket',
				'bg'     => '#7c2d12',
				'fg'     => '#ffffff',
				'coupon' => 'WELCOME10',
				'style'  => 'soft',
				'name'   => $t( 'Welcome coupon', 'Sveiciena kupons', 'Приветственный купон' ),
				'text'   => $t( 'Welcome! 10% off your first order with this code:', 'Laipni lūgti! −10% pirmajam pasūtījumam ar šo kodu:', 'Добро пожаловать! −10% на первый заказ с этим кодом:' ),
			),
			'newsletter'    => array(
				'icon'  => 'mail',
				'bg'    => '#111827',
				'fg'    => '#ffffff',
				'email' => 1,
				'name'  => $t( 'Newsletter sign-up', 'Jaunumu pieteikšanās', 'Подписка на новости' ),
				'text'  => $t( 'Get our news and offers first', 'Saņem jaunumus un piedāvājumus pirmais', 'Узнавайте о новинках и акциях первыми' ),
			),
			'new_arrivals'  => array(
				'icon'      => 'star',
				'bg'        => '#065f46',
				'fg'        => '#ffffff',
				'style'     => 'gradient',
				'name'      => $t( 'New arrivals', 'Jaunumi', 'Новинки' ),
				'text'      => $t( 'New arrivals are here — discover the latest products', 'Jaunumi ir klāt — apskati jaunākās preces', 'Новинки уже здесь — посмотрите последние товары' ),
				'link_text' => $t( 'See what\'s new', 'Skatīt jaunumus', 'Смотреть новинки' ),
			),
			'back_in_stock' => array(
				'icon'      => 'box',
				'bg'        => '#0e7490',
				'fg'        => '#ffffff',
				'name'      => $t( 'Back in stock', 'Atkal pieejams', 'Снова в наличии' ),
				'text'      => $t( 'Back in stock: popular products are available again', 'Atkal pieejams: populārās preces atkal ir pārdošanā', 'Снова в наличии: популярные товары снова в продаже' ),
				'link_text' => $t( 'Shop now', 'Iepirkties', 'В магазин' ),
			),
			'holiday'       => array(
				'icon'  => 'snowflake',
				'bg'    => '#14532d',
				'fg'    => '#ffffff',
				'style' => 'gradient',
				'name'  => $t( 'Holiday greeting', 'Svētku apsveikums', 'Праздничное поздравление' ),
				'text'  => $t( 'Happy holidays! Thank you for shopping with us this year', 'Priecīgus svētkus! Paldies, ka šogad iepirkies pie mums', 'С праздниками! Спасибо, что делали покупки у нас в этом году' ),
			),
			'weekend'       => array(
				'icon'      => 'percent',
				'bg'        => '#9d174d',
				'fg'        => '#ffffff',
				'weekdays'  => array( 6, 7 ),
				'name'      => $t( 'Weekend offer (Sat–Sun)', 'Nedēļas nogales piedāvājums (S–Sv)', 'Акция выходного дня (Сб–Вс)' ),
				'text'      => $t( 'Weekend offer: extra 15% off selected products', 'Nedēļas nogales piedāvājums: papildu −15% izvēlētām precēm', 'Акция выходного дня: дополнительно −15% на избранные товары' ),
				'link_text' => $t( 'See offers', 'Skatīt piedāvājumus', 'Смотреть акции' ),
			),
			'exit_offer'    => array(
				'icon'      => 'exit',
				'bg'        => '#312e81',
				'fg'        => '#ffffff',
				'coupon'    => 'STAY10',
				'trigger'   => 'exit',
				'placement' => 'bottom',
				'name'      => $t( 'Exit-intent offer', 'Piedāvājums pirms aiziešanas', 'Предложение перед уходом' ),
				'text'      => $t( 'Before you go: 10% off your order with this code', 'Pirms aizej: −10% pasūtījumam ar šo kodu', 'Перед уходом: −10% на заказ с этим кодом' ),
			),
			'b2b'           => array(
				'icon'      => 'briefcase',
				'bg'        => '#334155',
				'fg'        => '#ffffff',
				'style'     => 'minimal',
				'name'      => $t( 'Business customers (B2B)', 'Uzņēmumiem (B2B)', 'Для бизнеса (B2B)' ),
				'text'      => $t( 'Buying for a business? Ask about trade prices and invoice payment', 'Pērc uzņēmumam? Jautā par vairumcenām un apmaksu ar rēķinu', 'Покупаете для компании? Спросите об оптовых ценах и оплате по счёту' ),
				'link_text' => $t( 'Contact us', 'Sazināties', 'Связаться' ),
			),
			'privacy'       => array(
				'icon'  => 'shield',
				'bg'    => '#1f2937',
				'fg'    => '#ffffff',
				'style' => 'minimal',
				'name'  => $t( 'Privacy-friendly shop', 'Privātumu cienošs veikals', 'Магазин, уважающий приватность' ),
				'text'  => $t( 'No tracking cookies here — shop with peace of mind', 'Šeit nav izsekošanas sīkdatņu — iepērcies mierīgi', 'Здесь нет отслеживающих cookie — покупайте спокойно' ),
			),
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * Small helpers
	 * ------------------------------------------------------------------
	 */

	/**
	 * Money amount ("50", "49.99", "49,99") → '49.9900'; false when invalid.
	 *
	 * @param mixed $value Amount.
	 * @return string|false
	 */
	public function amount( $value ) {
		$value = str_replace( array( ' ', "\xc2\xa0", ',' ), array( '', '', '.' ), trim( (string) $value ) );

		return preg_match( '/^\d{1,9}(\.\d{1,4})?$/', $value ) ? number_format( (float) $value, 4, '.', '' ) : false;
	}

	/**
	 * Positive integer IDs (unique, at most $max) and whether anything else was posted.
	 *
	 * @param mixed $value List.
	 * @param int   $max   Maximum.
	 * @return array (ids, bad?)
	 */
	public function ids( $value, $max = self::MAX_IDS ) {
		$list = is_array( $value ) ? $value : ( is_scalar( $value ) && '' !== (string) $value ? explode( ',', (string) $value ) : array() );
		$out  = array();
		$bad  = false;

		foreach ( $list as $id ) {
			if ( is_scalar( $id ) && preg_match( '/^\s*\d{1,10}\s*$/', (string) $id ) && (int) $id > 0 ) {
				$out[ (int) $id ] = (int) $id;
			} else {
				$bad = true;
			}
		}

		if ( count( $out ) > $max ) {
			$bad = true;
		}

		return array( array_slice( array_values( $out ), 0, $max ), $bad );
	}

	/**
	 * ISO 3166-1 alpha-2 codes (upper case, unique, at most $max) and whether anything else was posted.
	 *
	 * @param mixed $value List.
	 * @param int   $max   Maximum.
	 * @return array (codes, bad?)
	 */
	public function country_codes( $value, $max = 300 ) {
		$list = is_array( $value ) ? $value : ( is_scalar( $value ) && '' !== (string) $value ? explode( ',', (string) $value ) : array() );
		$out  = array();
		$bad  = false;

		foreach ( $list as $code ) {
			$code = is_scalar( $code ) ? strtoupper( trim( (string) $code ) ) : '';

			if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
				$out[ $code ] = $code;
			} else {
				$bad = true;
			}
		}

		if ( count( $out ) > $max ) {
			$bad = true;
		}

		return array( array_slice( array_values( $out ), 0, $max ), $bad );
	}

	/**
	 * Comma / space separated list, lower-cased; false when an entry does not match $pattern or the list is too long.
	 *
	 * @param string $value   List.
	 * @param string $pattern Regex per entry.
	 * @return array|false
	 */
	private function tokens( $value, $pattern ) {
		$out = array();

		foreach ( preg_split( '/[\s,]+/', strtolower( (string) $value ) ) as $token ) {
			if ( '' === $token ) {
				continue;
			}

			if ( ! preg_match( $pattern, $token ) ) {
				return false;
			}

			$out[ $token ] = $token;
		}

		return count( $out ) > self::MAX_TOKENS ? false : array_values( $out );
	}

	/**
	 * Plain text: entities decoded, tags and control characters removed, whitespace collapsed.
	 *
	 * @param mixed $value Text.
	 * @return string
	 */
	public function plain_text( $value ) {
		$value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );

		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $value, 'UTF-8' ) ) {
			$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
		}

		$value = (string) preg_replace( '#</?[a-z][a-z0-9-]*(\s[^<>]*)?/?>#i', '', $value );
		$value = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );

		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * Length in characters.
	 *
	 * @param string $value Text.
	 * @return int
	 */
	private function len( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * First $max characters.
	 *
	 * @param string $value Text.
	 * @param int    $max   Characters.
	 * @return string
	 */
	private function cut( $value, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max, 'UTF-8' ) : substr( $value, 0, $max );
	}
}
