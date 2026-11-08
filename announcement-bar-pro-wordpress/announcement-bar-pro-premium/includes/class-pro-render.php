<?php
/**
 * Premium storefront: targeting, goals, codes, variants, triggers, looks and the boxes on the cart page, under the
 * product price and in the mini-cart — all through the filters of the free plugin's renderer — plus the statistics
 * endpoint.
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

use AnnouncementBarPro\Commerce;
use AnnouncementBarPro\Endpoints;
use AnnouncementBarPro\Plugin;
use AnnouncementBarPro\Renderer;
use AnnouncementBarPro\Sanitizer;
use AnnouncementBarPro\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Premium storefront features.
 */
class Pro_Render {
	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'abpro_handles_extras', '__return_true' );
		add_filter( 'abpro_placements', array( __CLASS__, 'placements' ) );
		add_filter( 'abpro_message_targets', array( __CLASS__, 'targets' ), 10, 2 );
		add_filter( 'abpro_cart_row', array( __CLASS__, 'cart_row' ), 10, 2 );
		add_filter( 'abpro_item_row', array( __CLASS__, 'item_row' ), 10, 2 );
		add_filter( 'abpro_goal', array( __CLASS__, 'goal' ), 10, 3 );
		add_filter( 'abpro_variant_names', array( __CLASS__, 'variant_names' ), 10, 2 );
		add_filter( 'abpro_countdown', array( __CLASS__, 'countdown' ), 10, 2 );
		add_filter( 'abpro_item', array( __CLASS__, 'item' ), 10, 3 );
		add_filter( 'abpro_need', array( __CLASS__, 'need' ), 10, 2 );
		add_filter( 'abpro_container', array( __CLASS__, 'container' ), 10, 2 );
		add_filter( 'abpro_common', array( __CLASS__, 'common' ), 10, 3 );
		add_filter( 'abpro_container_class', array( __CLASS__, 'container_class' ), 10, 3 );
		add_filter( 'abpro_container_attrs', array( __CLASS__, 'container_attrs' ), 10, 3 );
		add_filter( 'abpro_container_controls', array( __CLASS__, 'controls' ), 10, 3 );
		add_filter( 'abpro_item_attrs', array( __CLASS__, 'item_attrs' ), 10, 2 );
		add_filter( 'abpro_item_html', array( __CLASS__, 'item_html' ), 10, 4 );
		add_filter( 'abpro_preview_css', array( __CLASS__, 'preview_css' ), 10, 2 );
		add_filter( 'abpro_placement_label', array( __CLASS__, 'placement_label' ) );
		add_action( 'abpro_enqueue', array( __CLASS__, 'enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );

		// Boxes: classic WooCommerce templates, then blocks (cart block, product price block of block themes).
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'print_cart_box' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'print_product_box' ), 11 );
		add_action( 'woocommerce_before_mini_cart', array( __CLASS__, 'print_minicart_box' ), 5 );
		add_filter( 'render_block', array( __CLASS__, 'render_block' ), 10, 3 );
		add_action( 'wp_footer', array( __CLASS__, 'print_minicart_block_box' ), 5 );
	}

	/**
	 * Options of a row (complete, valid), cached per row.
	 *
	 * @param array $row Message row.
	 * @return array
	 */
	private static function opt( array $row ) {
		static $cache = array();

		$json = isset( $row['options'] ) ? (string) ( is_array( $row['options'] ) ? wp_json_encode( $row['options'] ) : $row['options'] ) : '';
		$key  = md5( $json );

		if ( ! isset( $cache[ $key ] ) ) {
			$cache[ $key ] = ( new Features() )->options( $json );
		}

		return $cache[ $key ];
	}

	/**
	 * Box placements.
	 *
	 * @param array $places Placements.
	 * @return array
	 */
	public static function placements( $places ) {
		return array_values( array_unique( array_merge( (array) $places, array( 'cart', 'product', 'minicart' ) ) ) );
	}

	/**
	 * Name of a box placement (preview).
	 *
	 * @param string $place Placement.
	 * @return string
	 */
	public static function placement_label( $place ) {
		$labels = array(
			'cart'     => __( 'Cart', 'announcement-bar-pro-premium' ),
			'product'  => __( 'Product page — under the price', 'announcement-bar-pro-premium' ),
			'minicart' => __( 'Mini-cart', 'announcement-bar-pro-premium' ),
		);

		return isset( $labels[ $place ] ) ? $labels[ $place ] : (string) $place;
	}

	/*
	 * ------------------------------------------------------------------
	 * Who sees a message
	 * ------------------------------------------------------------------
	 */

	/**
	 * Visitor role, page and product / category rules.
	 *
	 * @param bool  $ok  Answer so far.
	 * @param array $row Message row.
	 * @return bool
	 */
	public static function targets( $ok, $row ) {
		unset( $ok );

		$san        = new Sanitizer();
		$categories = $san->id_list( $row['categories'] );
		$products   = $san->id_list( $row['products'] );

		if ( ! self::roles_match( $row ) ) {
			return false;
		}

		if ( ! self::page_matches( (string) $row['page_mode'], (string) $row['page_rules'] ) ) {
			return false;
		}

		return ! ( $categories || $products ) || self::product_matches( $categories, $products );
	}

	/**
	 * Cart endpoint: visitor roles (pages cannot be checked there).
	 *
	 * @param bool  $ok  Answer so far.
	 * @param array $row Message row.
	 * @return bool
	 */
	public static function cart_row( $ok, $row ) {
		return $ok && self::roles_match( $row );
	}

	/**
	 * Role rule ("guest" = not logged in).
	 *
	 * @param array $row Message row.
	 * @return bool
	 */
	private static function roles_match( array $row ) {
		$roles = json_decode( (string) $row['roles'], true );
		$roles = is_array( $roles ) ? array_filter( $roles, 'is_string' ) : array();

		if ( ! $roles ) {
			return true;
		}

		$user = wp_get_current_user();
		$mine = $user->exists() ? (array) $user->roles : array( 'guest' );

		return (bool) array_intersect( $roles, $mine );
	}

	/**
	 * Page rules: home page only, or a list of page types / paths to include or exclude.
	 *
	 * @param string $mode  all | home | include | exclude.
	 * @param string $rules One rule per line.
	 * @return bool
	 */
	public static function page_matches( $mode, $rules ) {
		if ( 'home' === $mode ) {
			return is_front_page();
		}

		if ( 'include' !== $mode && 'exclude' !== $mode ) {
			return true;
		}

		$match = false;
		$path  = self::request_path();

		foreach ( preg_split( '/\r?\n/', $rules ) as $rule ) {
			$rule = strtolower( trim( $rule ) );

			if ( '' === $rule ) {
				continue;
			}

			if ( '/' === $rule[0] ? self::path_matches( $rule, $path ) : self::type_matches( $rule ) ) {
				$match = true;
				break;
			}
		}

		return 'include' === $mode ? $match : ! $match;
	}

	/**
	 * Path of this request relative to the site's home path, lower case, with a trailing slash ("/shop/").
	 *
	 * @return string
	 */
	public static function request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( '' !== $home && '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
			$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
		}

		$path = '/' . trim( strtolower( rawurldecode( $path ) ), '/' );

		return '/' === $path ? '/' : $path . '/';
	}

	/**
	 * Path rule: "/sale/" exact, "/sale/*" the section and everything below it.
	 *
	 * @param string $rule Rule.
	 * @param string $path Request path.
	 * @return bool
	 */
	public static function path_matches( $rule, $path ) {
		if ( '/*' === $rule ) {
			return true;
		}

		if ( '*' === substr( $rule, -1 ) ) {
			$base = rtrim( substr( $rule, 0, -1 ), '/' ) . '/';

			return 0 === strpos( $path, $base );
		}

		return rtrim( $rule, '/' ) . '/' === $path || ( '/' === $rule && '/' === $path );
	}

	/**
	 * Page-type rule (home, blog, shop, cart, checkout, account, product, category, page, post, search, 404).
	 *
	 * @param string $rule Rule.
	 * @return bool
	 */
	public static function type_matches( $rule ) {
		$wc = Commerce::active();

		switch ( $rule ) {
			case 'home':
				return is_front_page();
			case 'blog':
				return is_home() || is_category() || is_tag() || ( is_single() && 'post' === get_post_type() );
			case 'shop':
				return $wc && is_shop();
			case 'cart':
				return $wc && is_cart();
			case 'checkout':
				return $wc && is_checkout();
			case 'account':
				return $wc && is_account_page();
			case 'product':
				return $wc && is_product();
			case 'category':
				return $wc && ( is_product_category() || is_product_tag() );
			case 'page':
				return is_page();
			case 'post':
				return is_single() && 'post' === get_post_type();
			case 'search':
				return is_search();
			case '404':
				return is_404();
		}

		return false;
	}

	/**
	 * Product rules: product category pages of those categories, product pages of those products or of a product in
	 * one of those categories.
	 *
	 * @param int[] $categories Category IDs.
	 * @param int[] $products   Product IDs.
	 * @return bool
	 */
	private static function product_matches( array $categories, array $products ) {
		if ( ! Commerce::active() ) {
			return false;
		}

		if ( $categories && is_product_category() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$ids = array_merge( array( (int) $term->term_id ), array_map( 'intval', get_ancestors( (int) $term->term_id, 'product_cat', 'taxonomy' ) ) );

				return (bool) array_intersect( $categories, $ids );
			}
		}

		if ( is_product() ) {
			$id = (int) get_queried_object_id();

			if ( in_array( $id, $products, true ) ) {
				return true;
			}

			if ( $categories ) {
				return (bool) array_intersect( $categories, Commerce::product_categories( $id ) );
			}
		}

		return false;
	}

	/*
	 * ------------------------------------------------------------------
	 * Items
	 * ------------------------------------------------------------------
	 */

	/**
	 * Server-side rules of a message: visitor's country and the WooCommerce coupon (code and validity).
	 *
	 * @param array|null $row      Message row.
	 * @param Renderer   $renderer Renderer.
	 * @return array|null
	 */
	public static function item_row( $row, $renderer ) {
		if ( ! is_array( $row ) ) {
			return $row;
		}

		$fx  = new Features();
		$opt = self::opt( $row );

		if ( 'all' !== $opt['countries_mode'] && ! $renderer->is_preview() && ! $fx->country_matches( $opt, Commerce::place()[0] ) ) {
			return null;
		}

		if ( $opt['coupon_id'] ) {
			$code = Pro_Commerce::usable_coupon( $opt['coupon_id'] );

			if ( '' === $code ) {
				return null; // Not published, expired or used up: the message hides itself, like WooCommerce would refuse it.
			}

			$row['coupon'] = $code;
		}

		return $row;
	}

	/**
	 * Free shipping by country / state / currency, and reward tiers.
	 *
	 * @param array|null $goal     Goal of the free plugin.
	 * @param array      $row      Message row.
	 * @param Renderer   $renderer Renderer.
	 * @return array|null
	 */
	public static function goal( $goal, $row, $renderer ) {
		$type = isset( $row['type'] ) ? $row['type'] : 'standard';
		$opt  = self::opt( $row );
		$fx   = new Features();

		if ( 'free_shipping' === $type ) {
			if ( ! $goal || ( ! $opt['fs_zones'] && ! $opt['fs_currencies'] ) ) {
				return $goal;
			}

			$place                 = $opt['fs_zones'] && ! $renderer->is_preview() ? Commerce::place() : array( '', '' );
			list( $amount, $code ) = $fx->threshold( $opt, (float) $row['threshold'], $place[0], $place[1], Commerce::currency() );

			if ( $amount <= 0 ) {
				return null;
			}

			$have              = $renderer->subtotal();
			$goal['done']      = $have >= $amount;
			$goal['pct']       = (int) min( 100, floor( $have * 100 / $amount ) );
			$goal['remaining'] = Renderer::money_html( max( 0, $amount - $have ), $code );

			return $goal;
		}

		if ( 'tiers' !== $type || count( $opt['tiers'] ) < 2 || ( ! $renderer->is_preview() && ! Commerce::active() ) ) {
			return $goal;
		}

		$extra    = $fx->extra_of( isset( $row['extra'] ) ? $row['extra'] : '' );
		$subtotal = $renderer->subtotal();
		$state    = $fx->tier_state( $opt['tiers'], $subtotal );
		$next     = $state['next'];
		$steps    = array();

		foreach ( $state['marks'] as $i => $mark ) {
			$steps[] = array(
				'at'    => $mark,
				'label' => isset( $extra['tier_label'][ $i ] ) ? $extra['tier_label'][ $i ] : '',
				'done'  => $i < $state['done'],
			);
		}

		return array(
			'threshold' => implode( ',', $opt['tiers'] ),
			'done'      => $next < 0,
			'pct'       => $state['pct'],
			'remaining' => $next >= 0 ? Renderer::money_html( max( 0, (float) $opt['tiers'][ $next ] - $subtotal ) ) : '',
			'goal'      => $next >= 0 && isset( $extra['tier_label'][ $next ] ) ? '<span class="abp-goal">' . esc_html( $extra['tier_label'][ $next ] ) . '</span>' : '',
			'text'      => $next >= 0 && isset( $extra['tier_text'][ $next ] ) ? $extra['tier_text'][ $next ] : '',
			'steps'     => $steps,
			'k'         => $state['done'],
		);
	}

	/**
	 * Variant B while the A/B test is on and B has a text.
	 *
	 * @param array $names Variants.
	 * @param array $row   Message row.
	 * @return array
	 */
	public static function variant_names( $names, $row ) {
		if ( ! empty( $row['ab_test'] ) && isset( $row['text_b'] ) && '' !== trim( wp_strip_all_tags( ( new Sanitizer() )->decode( $row['text_b'] ) ) ) ) {
			$names   = (array) $names;
			$names[] = 'b';
		}

		return $names;
	}

	/**
	 * Recurring daily / weekly cut-off countdown.
	 *
	 * @param string $html Countdown of the free plugin (to the end date).
	 * @param array  $row  Message row.
	 * @return string
	 */
	public static function countdown( $html, $row ) {
		$opt = self::opt( $row );

		if ( '' === $opt['recur'] ) {
			return $html;
		}

		$fx  = new Features();
		$end = $fx->next_cutoff( $opt, time(), wp_timezone() );

		return $end ? '<span class="abp-countdown" data-end="' . ( $end * 1000 ) . '" data-recur="' . esc_attr( $fx->recur_attr( $opt ) ) . '"></span>' : '';
	}

	/**
	 * Code, tiers, sign-up form, image, font, campaign, visitor, trigger and cart conditions of an item.
	 *
	 * @param array    $item     Item of the free plugin.
	 * @param array    $row      Message row.
	 * @param Renderer $renderer Renderer.
	 * @return array
	 */
	public static function item( $item, $row, $renderer ) {
		$fx      = new Features();
		$opt     = self::opt( $row );
		$extra   = $fx->extra_of( isset( $row['extra'] ) ? $row['extra'] : '' );
		$preview = $renderer->is_preview();
		$coupon  = ( new Sanitizer() )->coupon( isset( $row['coupon'] ) ? $row['coupon'] : '' );

		$item['coupon'] = $coupon;
		/* translators: %s: coupon code */
		$item['copy_aria'] = sprintf( __( 'Copy code %s', 'announcement-bar-pro-premium' ), $coupon );
		$item['email']     = $opt['email'] ? self::email_texts( $extra ) : null;
		$item['font']      = $opt['font'];
		$item['utm']       = ( '' !== $opt['utm_source'] || '' !== $opt['utm_medium'] || '' !== $opt['utm_campaign'] || '' !== $opt['referrer'] ) && ! $preview ? wp_json_encode( $fx->client_rules( $opt ) ) : '';
		$item['vis']       = 'all' !== $opt['visitor'] && ! $preview ? $opt['visitor'] : '';
		$item['trig']      = $preview ? '' : $fx->trigger_attr( $opt );
		$item['cc']        = $fx->has_cart_rules( $opt ) && ! $preview;

		if ( $item['cc'] ) {
			$cart         = Commerce::cart( (bool) $opt['cart_categories'] );
			$item['coff'] = ! $fx->cart_matches( $opt, $cart['subtotal'], $cart['qty'], $cart['products'], $cart['categories'] );
		}

		$item['image'] = $opt['image'] ? self::image_style( (int) $opt['image'], $item['bg_color'], $opt['overlay'] ) : '';
		if ( '' !== $item['image'] ) {
			$item['style_attr'] .= $item['image'];
		}

		$item['seen']['c'] = '' !== $coupon ? '|c|' . $coupon : '';
		$item['seen']['t'] = $item['steps'] ? '|t|' . implode( '|', $extra['tier_label'] ) : '';
		$item['seen']['e'] = $item['email'] ? '|e' : '';
		$item['seen']['i'] = $opt['image'] ? '|i|' . $opt['image'] : '';

		return $item;
	}

	/**
	 * The page needs the sign-up endpoint.
	 *
	 * @param array $need Needs.
	 * @param array $item Item.
	 * @return array
	 */
	public static function need( $need, $item ) {
		$need          = (array) $need;
		$need['email'] = ! empty( $need['email'] ) || ! empty( $item['email'] );

		return $need;
	}

	/**
	 * Pause button, auto-hide; no form in a scrolling ticker.
	 *
	 * @param array    $box      Container.
	 * @param Renderer $renderer Renderer.
	 * @return array
	 */
	public static function container( $box, $renderer ) {
		$s      = $renderer->settings();
		$inline = ! empty( $box['inline'] );
		$mode   = $box['mode'];

		if ( 'marquee' === $mode ) {
			foreach ( $box['items'] as $i => $item ) {
				$box['items'][ $i ]['email'] = null; // A form cannot scroll by; the text is still shown.
			}
		}

		$box['pause']    = ! $inline && ! empty( $s['pause_button'] ) && ( ( 'rotate' === $mode && count( $box['items'] ) > 1 ) || 'marquee' === $mode );
		$box['autohide'] = $inline ? 0 : (int) $s['autohide'];

		return $box;
	}

	/**
	 * Values every container shares: font, GA4, consent, limits, endpoints and the texts of the premium parts.
	 *
	 * @param array    $c        Common values.
	 * @param array    $need     Needs.
	 * @param Renderer $renderer Renderer.
	 * @return array
	 */
	public static function common( $c, $need, $renderer ) {
		$s     = $renderer->settings();
		$live  = ! $renderer->is_preview();
		$email = ! empty( $need['email'] );
		$ts    = $live && $email && Turnstile::enabled( $s );

		return array_merge(
			(array) $c,
			array(
				'font'         => (string) $s['font'],
				'ga4'          => $live && ! empty( $s['ga4'] ),
				'consent'      => $live && ! empty( $s['consent_wait'] ),
				'max'          => (int) $s['max_messages'],
				'track'        => $live ? rest_url( 'announcement-bar-pro/v1/track' ) : '',
				'subscribe'    => $email ? ( $live ? rest_url( 'announcement-bar-pro/v1/subscribe' ) : '#' ) : '',
				'token'        => $live && $email ? ( new Features() )->token( Settings::secret(), 'abp-signup' ) : '',
				'privacy'      => $email ? self::privacy_url( (int) $s['privacy_id'] ) : '',
				'ts_key'       => $ts ? Turnstile::site_key( $s['turnstile_site_key'] ) : '',
				'show'         => __( 'Show announcements', 'announcement-bar-pro-premium' ),
				'pause_label'  => __( 'Pause messages', 'announcement-bar-pro-premium' ),
				'play_label'   => __( 'Play messages', 'announcement-bar-pro-premium' ),
				'copy'         => __( 'Copy', 'announcement-bar-pro-premium' ),
				'copied'       => __( 'Copied!', 'announcement-bar-pro-premium' ),
				'privacy_text' => __( 'Privacy policy', 'announcement-bar-pro-premium' ),
				'sending'      => __( 'Sending…', 'announcement-bar-pro-premium' ),
				'err_email'    => __( 'Please enter a valid e-mail address.', 'announcement-bar-pro-premium' ),
				'err_consent'  => __( 'Please tick the box to agree.', 'announcement-bar-pro-premium' ),
				'err_failed'   => __( 'Sorry, the sign-up did not work. Please try again later.', 'announcement-bar-pro-premium' ),
				'ts_wait'      => __( 'This form uses Cloudflare\'s anti-bot check. Please accept cookies to use it.', 'announcement-bar-pro-premium' ),
				'err_ts'       => __( 'The anti-bot check is not ready yet — please wait a moment or reload the page.', 'announcement-bar-pro-premium' ),
			)
		);
	}

	/**
	 * Font and pause classes.
	 *
	 * @param string $classes Classes.
	 * @param array  $box     Container.
	 * @param array  $c       Common values.
	 * @return string
	 */
	public static function container_class( $classes, $box, $c ) {
		return $classes . ( ! empty( $box['pause'] ) ? ' abp-has-pause' : '' ) . ( ! empty( $c['font'] ) ? ' abp-font-' . $c['font'] : '' );
	}

	/**
	 * Statistics endpoint, limits, auto-hide, pause labels, GA4, consent.
	 *
	 * @param array $attrs Attributes.
	 * @param array $box   Container.
	 * @param array $c     Common values.
	 * @return array
	 */
	public static function container_attrs( $attrs, $box, $c ) {
		$auto  = ! empty( $box['autohide'] ) ? (int) $box['autohide'] : 0;
		$pause = ! empty( $box['pause'] );
		$more  = array(
			'data-track'    => isset( $c['track'] ) ? $c['track'] : '',
			'data-max'      => ! empty( $c['max'] ) ? $c['max'] : '',
			'data-autohide' => $auto ? $auto : '',
			'data-l-show'   => $auto ? $c['show'] : '',
			'data-l-pause'  => $pause ? $c['pause_label'] : '',
			'data-l-play'   => $pause ? $c['play_label'] : '',
			'data-ga'       => ! empty( $c['ga4'] ) ? 1 : '',
			'data-consent'  => ! empty( $c['consent'] ) ? 1 : '',
		);
		$style = isset( $attrs['style'] ) ? $attrs['style'] : null;
		unset( $attrs['style'] );

		return array_merge( (array) $attrs, $more, array( 'style' => $style ) );
	}

	/**
	 * Pause button.
	 *
	 * @param string $html Controls.
	 * @param array  $box  Container.
	 * @param array  $c    Common values.
	 * @return string
	 */
	public static function controls( $html, $box, $c ) {
		if ( empty( $box['pause'] ) ) {
			return $html;
		}

		return $html . '<button type="button" class="abp-pause" aria-pressed="false" aria-label="' . esc_attr( $c['pause_label'] ) . '" title="' . esc_attr( $c['pause_label'] ) . '"><svg class="abp-i-pause" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M5.5 3.5v9m5-9v9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><svg class="abp-i-play" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M5 3.2v9.6L12.6 8z" fill="currentColor"/></svg></button>';
	}

	/**
	 * Item attributes of the premium features.
	 *
	 * @param array $attrs Attributes.
	 * @param array $item  Item.
	 * @return array
	 */
	public static function item_attrs( $attrs, $item ) {
		$utm   = isset( $item['utm'] ) ? $item['utm'] : '';
		$vis   = isset( $item['vis'] ) ? $item['vis'] : '';
		$trig  = isset( $item['trig'] ) ? $item['trig'] : '';
		$style = isset( $attrs['style'] ) ? $attrs['style'] : null;
		unset( $attrs['style'] );

		return array_merge(
			(array) $attrs,
			array(
				'data-font' => isset( $item['font'] ) ? $item['font'] : '',
				'data-img'  => ! empty( $item['image'] ) ? 1 : '',
				'data-utm'  => $utm,
				'data-vis'  => $vis,
				'data-uoff' => $utm || $vis ? 1 : '',
				'data-trig' => $trig,
				'data-toff' => $trig ? 1 : '',
				'data-cc'   => ! empty( $item['cc'] ) ? 1 : '',
				'data-coff' => ! empty( $item['cc'] ) && ! empty( $item['coff'] ) ? 1 : '',
				'style'     => $style,
			)
		);
	}

	/**
	 * Code with a copy button and the e-mail sign-up form (with Cloudflare Turnstile when it is on).
	 *
	 * @param string $html Extra HTML so far.
	 * @param array  $item Item.
	 * @param array  $box  Container.
	 * @param array  $c    Common values.
	 * @return string
	 */
	public static function item_html( $html, $item, $box, $c ) {
		if ( ! empty( $item['coupon'] ) ) {
			$html .= '<span class="abp-coupon"><span class="abp-code">' . esc_html( $item['coupon'] ) . '</span><button type="button" class="abp-copy" data-code="' . esc_attr( $item['coupon'] ) . '" data-label="' . esc_attr( $c['copy'] ) . '" data-copied="' . esc_attr( $c['copied'] ) . '" aria-label="' . esc_attr( $item['copy_aria'] ) . '">'
				. '<svg class="abp-cp" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="5.2" y="5.2" width="8.6" height="8.6" rx="1.8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10.6 3.1V3A1.5 1.5 0 0 0 9.1 1.5H3A1.5 1.5 0 0 0 1.5 3v6.1a1.5 1.5 0 0 0 1.5 1.5h.1" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'
				. '<svg class="abp-ok" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3 8.6l3.3 3.1L13 4.6" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>'
				. '<span class="abp-copy-label">' . esc_html( $c['copy'] ) . '</span></button><span class="abp-sr" role="status" aria-live="polite"></span></span>';
		}

		if ( ! empty( $item['email'] ) && ! empty( $c['subscribe'] ) ) {
			$fid   = 'abp-fm-' . $box['placement'] . '-' . $item['id'];
			$ts    = ! empty( $c['ts_key'] ) ? ' data-ts="' . esc_attr( $c['ts_key'] ) . '" data-ts-wait="' . esc_attr( $c['ts_wait'] ) . '" data-err-ts="' . esc_attr( $c['err_ts'] ) . '"' : '';
			$html .= '<form class="abp-form" action="' . esc_url( $c['subscribe'] ) . '" method="post" novalidate data-tok="' . esc_attr( $c['token'] ) . '" data-success="' . esc_attr( $item['email']['success'] ) . '" data-sending="' . esc_attr( $c['sending'] ) . '" data-err-email="' . esc_attr( $c['err_email'] ) . '" data-err-consent="' . esc_attr( $c['err_consent'] ) . '" data-err-failed="' . esc_attr( $c['err_failed'] ) . '"' . $ts . '>'
				. '<span class="abp-hp" aria-hidden="true"><label>Website <input type="text" name="website" value="" tabindex="-1" autocomplete="off" /></label></span>'
				. '<span class="abp-fields"><input type="email" name="email" class="abp-email" required="required" maxlength="96" autocomplete="email" inputmode="email" placeholder="' . esc_attr( $item['email']['placeholder'] ) . '" aria-label="' . esc_attr( $item['email']['placeholder'] ) . '" aria-describedby="' . esc_attr( $fid ) . '" /><button type="submit" class="abp-submit">' . esc_html( $item['email']['button'] ) . '</button></span>'
				. '<label class="abp-consent"><input type="checkbox" name="consent" value="1" required="required" aria-describedby="' . esc_attr( $fid ) . '" /> <span>' . esc_html( $item['email']['consent'] )
				. ( '' !== $c['privacy'] ? ' <a href="' . esc_url( $c['privacy'] ) . '" target="_blank" rel="noopener">' . esc_html( $c['privacy_text'] ) . '</a>' : '' ) . '</span></label>'
				. ( '' !== $ts ? '<span class="abp-ts" aria-live="polite"></span>' : '' )
				. '<span class="abp-form-msg" id="' . esc_attr( $fid ) . '" role="status" aria-live="polite"></span></form>';
		}

		return $html;
	}

	/**
	 * E-mail form texts of the visitor's language; empty fields use the plugin's texts.
	 *
	 * @param array $extra Extra texts.
	 * @return array
	 */
	private static function email_texts( array $extra ) {
		$defaults = self::email_defaults();
		$out      = array();

		foreach ( array( 'placeholder', 'button', 'consent', 'success' ) as $key ) {
			$value       = isset( $extra[ 'email_' . $key ] ) ? $extra[ 'email_' . $key ] : '';
			$out[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		return $out;
	}

	/**
	 * Default texts of the e-mail form.
	 *
	 * @return array
	 */
	public static function email_defaults() {
		return array(
			'placeholder' => __( 'Your e-mail address', 'announcement-bar-pro-premium' ),
			'button'      => __( 'Subscribe', 'announcement-bar-pro-premium' ),
			'consent'     => __( 'I agree to receive news and offers by e-mail. I can unsubscribe at any time.', 'announcement-bar-pro-premium' ),
			'success'     => __( 'Almost done — please confirm with the link we sent to your inbox.', 'announcement-bar-pro-premium' ),
		);
	}

	/**
	 * Background image with the message colour laid over it (overlay %), for a style attribute.
	 *
	 * @param int    $id      Attachment ID.
	 * @param string $hex     Message colour.
	 * @param int    $overlay Overlay %.
	 * @return string
	 */
	private static function image_style( $id, $hex, $overlay ) {
		$url = wp_get_attachment_image_url( $id, 'full' );

		if ( ! is_string( $url ) || '' === $url || preg_match( '/[\'"()\s\\\\]/', $url ) || ! preg_match( '/^#[0-9a-f]{6}$/i', (string) $hex ) ) {
			return '';
		}

		$rgb  = array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
		$tint = 'rgba(' . implode( ',', $rgb ) . ',' . number_format( max( 0, min( 90, (int) $overlay ) ) / 100, 2, '.', '' ) . ')';

		return 'background-image:linear-gradient(' . $tint . ',' . $tint . '),url("' . esc_url_raw( $url ) . '");background-size:cover;background-position:center;';
	}

	/**
	 * Privacy page URL: the page of the settings, else WordPress's privacy policy page.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	public static function privacy_url( $page_id ) {
		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			$url = get_permalink( $page_id );

			return is_string( $url ) ? $url : '';
		}

		return get_privacy_policy_url();
	}

	/*
	 * ------------------------------------------------------------------
	 * Custom CSS, boxes, statistics
	 * ------------------------------------------------------------------
	 */

	/**
	 * Custom CSS on pages that show messages.
	 */
	public static function enqueue() {
		$css = (string) Settings::effective()['custom_css'];

		if ( '' !== $css ) {
			wp_add_inline_style( 'announcement-bar-pro-wp', ( new Features() )->css( $css ) );
		}
	}

	/**
	 * Custom CSS of the preview frame.
	 *
	 * @param string $css      CSS so far.
	 * @param array  $settings Effective settings.
	 * @return string
	 */
	public static function preview_css( $css, $settings ) {
		return '' !== (string) $settings['custom_css'] ? ( new Features() )->css( $settings['custom_css'] ) : $css;
	}

	/**
	 * Box of a placement.
	 *
	 * @param string $place Placement.
	 * @param bool   $once  Print once per request.
	 * @return string
	 */
	private static function box( $place, $once = true ) {
		$frontend = Plugin::instance()->frontend;

		return $frontend ? $frontend->inline_html( $place, $once ) : '';
	}

	/**
	 * Classic cart page: above the cart table.
	 */
	public static function print_cart_box() {
		echo self::box( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	/**
	 * Classic product page: right under the price.
	 */
	public static function print_product_box() {
		echo self::box( 'product' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	/**
	 * Classic mini-cart (also inside the refreshed cart fragments).
	 */
	public static function print_minicart_box() {
		echo self::box( 'minicart', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	/**
	 * Mini-Cart block on this page (its drawer is drawn in the browser).
	 *
	 * @var bool
	 */
	private static $minicart_block = false;

	/**
	 * Mini-Cart block: the box for its drawer, once per page in a <template> (assets/minicart-block.js copies it into
	 * the drawer each time the drawer is drawn). In the footer, so the shared scripts of the head are already there.
	 */
	public static function print_minicart_block_box() {
		if ( ! self::$minicart_block ) {
			return;
		}

		self::$minicart_block = false;
		$box                  = self::box( 'minicart', false );

		if ( '' === $box ) {
			return;
		}

		wp_enqueue_script( 'announcement-bar-pro-premium-minicart', ABPRO_PREMIUM_URL . 'assets/minicart-block.js', array( 'announcement-bar-pro' ), ABPRO_PREMIUM_VERSION, true );
		echo '<template id="abp-minicart-block-tpl">' . $box . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	/**
	 * Blocks: cart block (box above it), the product price block of the main product (box under it) and the Mini-Cart
	 * block (noted here; its box is printed in the footer).
	 *
	 * @param string         $content  Block HTML.
	 * @param array          $block    Block.
	 * @param \WP_Block|null $instance Block instance (context: postId).
	 * @return string
	 */
	public static function render_block( $content, $block, $instance = null ) {
		if ( ! is_array( $block ) || empty( $block['blockName'] ) || is_admin() ) {
			return $content;
		}

		if ( 'woocommerce/cart' === $block['blockName'] ) {
			return self::box( 'cart' ) . $content;
		}

		if ( 'woocommerce/mini-cart' === $block['blockName'] ) {
			self::$minicart_block = true;
			return $content;
		}

		if ( 'woocommerce/product-price' === $block['blockName'] && function_exists( 'is_product' ) && is_product() ) {
			$post_id = $instance instanceof \WP_Block && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();

			if ( (int) get_queried_object_id() !== $post_id ) {
				return $content;
			}

			return $content . self::box( 'product' );
		}

		return $content;
	}

	/**
	 * Statistics route.
	 */
	public static function routes() {
		register_rest_route(
			Endpoints::NS,
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'track' ),
				'permission_callback' => '__return_true', // Public: anonymous counters, checked inside (same origin, rate limit).
			)
		);
	}

	/**
	 * Statistics (POST id, type, v). Counters only; nothing personal is read or stored. Always 204.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function track( \WP_REST_Request $request ) {
		try {
			$id      = (string) $request->get_param( 'id' );
			$type    = (string) $request->get_param( 'type' );
			$variant = 'b' === $request->get_param( 'v' ) ? 'b' : 'a';

			if ( ! empty( Settings::get()['status'] ) && preg_match( '/^\d{1,10}$/', $id ) && in_array( $type, array( 'view', 'click', 'copy' ), true ) && Endpoints::same_origin() && ! Endpoints::limited( 'track', 300, 600 ) ) {
				Pro_DB::track( (int) $id, $type, $variant );
			}
		} catch ( \Throwable $e ) {
			// Statistics must never produce an error.
			unset( $e );
		}

		$response = new \WP_REST_Response( null, 204 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
