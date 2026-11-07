<?php
/**
 * Storefront: decides which messages this page gets and prints the bars (wp_body_open, footer as fallback) and the
 * inline boxes (WooCommerce cart page, under the product price, mini-cart; classic templates and blocks).
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Storefront output.
 */
class Frontend {
	/**
	 * Groups and common values of this request (null = not worked out yet).
	 *
	 * @var array|null
	 */
	private $prepared = null;

	/**
	 * Bars printed already.
	 *
	 * @var bool
	 */
	private $bars_done = false;

	/**
	 * Inline boxes printed already, by placement.
	 *
	 * @var array
	 */
	private $inline_done = array();

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'wp_body_open', array( $this, 'print_bars' ), 5 );
		add_action( 'wp_footer', array( $this, 'print_bars_fallback' ), 5 );

		// Inline boxes: classic WooCommerce templates, then blocks.
		add_action( 'woocommerce_before_cart', array( $this, 'print_cart_box' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'print_product_box' ), 11 );
		add_action( 'woocommerce_before_mini_cart', array( $this, 'print_minicart_box' ), 5 );
		// Blocks: cart block, product price block of block themes.
		add_filter( 'render_block', array( $this, 'render_block' ), 10, 3 );
	}

	/**
	 * True on requests that may show messages (not admin, feeds, REST, embeds, robots).
	 *
	 * @return bool
	 */
	private function storefront() {
		return ! is_admin() && ! is_feed() && ! is_embed() && ! is_robots() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! is_customize_preview();
	}

	/**
	 * Messages of this request grouped by placement, worked out once.
	 *
	 * @return array
	 */
	public function prepare() {
		if ( null !== $this->prepared ) {
			return $this->prepared;
		}

		$this->prepared = array(
			'groups' => array(),
			'need'   => array(
				'cart'  => false,
				'email' => false,
			),
		);

		try {
			$s = Settings::effective();

			if ( empty( $s['status'] ) || (int) get_option( 'abpro_schema', 0 ) < 1 ) {
				return $this->prepared;
			}

			$pro    = Licence::is_pro();
			$locale = determine_locale();
			$rows   = array();

			foreach ( DB::active_messages( $locale, get_locale() ) as $row ) {
				if ( $this->targets( $row, $pro ) ) {
					$rows[] = $row;
				}
			}

			if ( ! $rows ) {
				return $this->prepared;
			}

			$renderer                   = new Renderer( $s, $pro );
			list( $groups, $need )      = $renderer->groups( $rows );
			$this->prepared['groups']   = $groups;
			$this->prepared['need']     = $need;
			$this->prepared['renderer'] = $renderer;
		} catch ( \Throwable $e ) {
			// Never break the storefront because of this plugin.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Announcement Bar Pro: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug mode only.
			}
		}

		return $this->prepared;
	}

	/**
	 * Visitor role, page and product / category rules of a message (Pro). Without a Pro licence a message that uses
	 * them never shows.
	 *
	 * @param array $row Message row.
	 * @param bool  $pro Pro licence.
	 * @return bool
	 */
	private function targets( array $row, $pro ) {
		$san        = new Sanitizer();
		$roles      = json_decode( (string) $row['roles'], true );
		$roles      = is_array( $roles ) ? array_filter( $roles, 'is_string' ) : array();
		$categories = $san->id_list( $row['categories'] );
		$products   = $san->id_list( $row['products'] );
		$mode       = (string) $row['page_mode'];

		if ( ! $roles && 'all' === $mode && ! $categories && ! $products ) {
			return true;
		}

		if ( ! $pro ) {
			return false;
		}

		if ( $roles ) {
			$user = wp_get_current_user();
			$mine = $user->exists() ? (array) $user->roles : array( 'guest' );

			if ( ! array_intersect( $roles, $mine ) ) {
				return false;
			}
		}

		if ( ! self::page_matches( $mode, (string) $row['page_rules'] ) ) {
			return false;
		}

		if ( ( $categories || $products ) && ! $this->product_matches( $categories, $products ) ) {
			return false;
		}

		return true;
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
	private function product_matches( array $categories, array $products ) {
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
	 * Assets
	 * ------------------------------------------------------------------
	 */

	/**
	 * Styles and scripts, only on pages that show something.
	 */
	public function enqueue() {
		if ( ! $this->storefront() ) {
			return;
		}

		$p = $this->prepare();

		if ( ! $p['groups'] ) {
			return;
		}

		self::register_assets();
		wp_enqueue_style( 'announcement-bar-pro-wp' );

		$css = Settings::effective()['custom_css'];
		if ( '' !== $css ) {
			wp_add_inline_style( 'announcement-bar-pro-wp', ( new Features() )->css( $css ) );
		}

		wp_enqueue_script( 'announcement-bar-pro' );
		wp_enqueue_script( 'announcement-bar-pro-wp' );
	}

	/**
	 * Registers the shared storefront files (also used by the admin preview).
	 */
	public static function register_assets() {
		$v = ABPRO_VERSION;

		wp_register_style( 'announcement-bar-pro', ABPRO_URL . 'assets/css/announcement-bar-pro.css', array(), $v );
		wp_register_style( 'announcement-bar-pro-wp', ABPRO_URL . 'assets/css/announcement-bar-pro-wp.css', array( 'announcement-bar-pro' ), $v );
		wp_register_script( 'announcement-bar-pro-pre', ABPRO_URL . 'assets/js/announcement-bar-pro-pre.js', array(), $v, false );
		wp_register_script(
			'announcement-bar-pro',
			ABPRO_URL . 'assets/js/announcement-bar-pro.js',
			array(),
			$v,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_register_script(
			'announcement-bar-pro-wp',
			ABPRO_URL . 'assets/js/announcement-bar-pro-wp.js',
			array( 'announcement-bar-pro' ),
			$v,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * Bars
	 * ------------------------------------------------------------------
	 */

	/**
	 * Bars right after <body> (themes that call wp_body_open()).
	 */
	public function print_bars() {
		$this->bars( false );
	}

	/**
	 * Fallback for themes without wp_body_open(): bars in the footer; the script moves a top bar up.
	 */
	public function print_bars_fallback() {
		$this->bars( true );
	}

	/**
	 * Prints the top / bottom bars once.
	 *
	 * @param bool $fallback Printed from the footer.
	 */
	private function bars( $fallback ) {
		if ( $this->bars_done || ! $this->storefront() ) {
			return;
		}

		$this->bars_done = true;
		$p               = $this->prepare();
		$containers      = array();

		if ( empty( $p['renderer'] ) ) {
			return;
		}

		foreach ( array( 'top', 'bottom' ) as $place ) {
			if ( ! empty( $p['groups'][ $place ] ) ) {
				$containers[] = $p['renderer']->container( $place, $p['groups'][ $place ], false );
			}
		}

		if ( ! $containers ) {
			return;
		}

		$containers[0]['id'] = 'abp-bar';
		if ( 2 === count( $containers ) ) {
			$containers[1]['id'] = 'abp-bar-bottom';
		}

		echo $p['renderer']->html( $containers, $p['renderer']->common( $p['need'], $fallback ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in Renderer::html().
		self::print_pre();
	}

	/**
	 * The small script that must run right after the bars (before the first paint), printed once.
	 */
	private static function print_pre() {
		if ( ! wp_script_is( 'announcement-bar-pro-pre', 'registered' ) ) {
			self::register_assets();
		}

		wp_print_scripts( array( 'announcement-bar-pro-pre' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * Inline boxes
	 * ------------------------------------------------------------------
	 */

	/**
	 * Inline box HTML of a placement ('' when there is none or it was printed already).
	 *
	 * @param string $placement cart | product | minicart.
	 * @param bool   $once      Print only once per request.
	 * @return string
	 */
	public function inline_html( $placement, $once = true ) {
		if ( ( $once && ! empty( $this->inline_done[ $placement ] ) ) || ! Licence::is_pro() ) {
			return '';
		}

		$p = $this->prepare();

		if ( empty( $p['groups'][ $placement ] ) || empty( $p['renderer'] ) ) {
			return '';
		}

		$this->inline_done[ $placement ] = true;
		$r                               = $p['renderer'];
		$html                            = $r->html( array( $r->container( $placement, $p['groups'][ $placement ], true ) ), $r->common( $p['need'] ) );

		// Boxes added later (mini-cart refresh) are started by the main script; the pre script runs once per page.
		if ( ! wp_doing_ajax() && ! self::is_wc_ajax() ) {
			ob_start();
			self::print_pre();
			$html .= ob_get_clean();
		}

		return $html;
	}

	/**
	 * True inside a WooCommerce AJAX request (?wc-ajax=…).
	 *
	 * @return bool
	 */
	private static function is_wc_ajax() {
		return isset( $_GET['wc-ajax'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks which kind of request this is.
	}

	/**
	 * Classic cart page: above the cart table.
	 */
	public function print_cart_box() {
		echo $this->inline_html( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	/**
	 * Classic product page: right under the price.
	 */
	public function print_product_box() {
		echo $this->inline_html( 'product' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	/**
	 * Classic mini-cart (also inside the refreshed cart fragments).
	 */
	public function print_minicart_box() {
		echo $this->inline_html( 'minicart', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	/**
	 * Blocks: cart block (box above it) and the product price block of the main product (box under it).
	 *
	 * @param string         $content  Block HTML.
	 * @param array          $block    Block.
	 * @param \WP_Block|null $instance Block instance (context: postId).
	 * @return string
	 */
	public function render_block( $content, $block, $instance = null ) {
		if ( ! is_array( $block ) || empty( $block['blockName'] ) || ! $this->storefront() || ! Licence::is_pro() ) {
			return $content;
		}

		if ( 'woocommerce/cart' === $block['blockName'] ) {
			return $this->inline_html( 'cart' ) . $content;
		}

		if ( 'woocommerce/product-price' === $block['blockName'] && function_exists( 'is_product' ) && is_product() ) {
			$post_id = $instance instanceof \WP_Block && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();

			if ( (int) get_queried_object_id() !== $post_id ) {
				return $content;
			}

			return $content . $this->inline_html( 'product' );
		}

		return $content;
	}
}
