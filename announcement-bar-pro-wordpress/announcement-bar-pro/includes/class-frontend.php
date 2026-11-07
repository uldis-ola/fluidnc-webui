<?php
/**
 * Storefront: decides which messages this page gets and prints the bars (wp_body_open, footer as fallback). Boxes of
 * other placements are printed by an add-on through inline_html().
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
	}

	/**
	 * True on requests that may show messages (not admin, feeds, REST, embeds, robots).
	 *
	 * @return bool
	 */
	public function storefront() {
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
			'need'   => array( 'cart' => false ),
		);

		try {
			$s = Settings::effective();

			if ( empty( $s['status'] ) || (int) get_option( 'abpro_schema', 0 ) < 1 ) {
				return $this->prepared;
			}

			$locale = determine_locale();
			$rows   = array();

			foreach ( DB::active_messages( $locale, get_locale() ) as $row ) {
				if ( self::targets( $row ) ) {
					$rows[] = $row;
				}
			}

			if ( ! $rows ) {
				return $this->prepared;
			}

			$renderer                   = new Renderer( $s );
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
	 * Visitor role, page and product / category rules of a message. This plugin has none of these rules: a message that
	 * uses them shows only when an add-on checks them (filter "abpro_message_targets"), never to more people than
	 * intended.
	 *
	 * @param array $row Message row.
	 * @return bool
	 */
	public static function targets( array $row ) {
		$plain = static function ( $value ) {
			$list = json_decode( (string) $value, true );

			return ! is_array( $list ) || ! $list;
		};

		if ( $plain( $row['roles'] ) && in_array( (string) $row['page_mode'], array( '', 'all' ), true ) && $plain( $row['categories'] ) && $plain( $row['products'] ) ) {
			return true;
		}

		return (bool) apply_filters( 'abpro_message_targets', false, $row );
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
		do_action( 'abpro_enqueue', $p );
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
			// Boxes of other placements are printed where they belong (inline_html()).
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
	 * Boxes of other placements (registered by an add-on)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Box HTML of a placement ('' when there is none or it was printed already).
	 *
	 * @param string $placement Placement (see Renderer::placements()).
	 * @param bool   $once      Print only once per request.
	 * @return string
	 */
	public function inline_html( $placement, $once = true ) {
		if ( ( $once && ! empty( $this->inline_done[ $placement ] ) ) || ! Renderer::is_inline( $placement ) || ! $this->storefront() ) {
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
}
