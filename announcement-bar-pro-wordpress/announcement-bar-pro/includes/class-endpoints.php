<?php
/**
 * Endpoints: cart refresh (WooCommerce AJAX, GET, read-only, same-origin, rate limited, no session write) and the
 * admin-only preview route. same_origin() and limited() are shared with add-ons for their own public endpoints.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes and the WooCommerce AJAX cart endpoint.
 */
class Endpoints {
	const NS = 'announcement-bar-pro/v1';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'wc_ajax_abpro_cart', array( $this, 'cart' ) );
	}

	/**
	 * REST routes.
	 */
	public function routes() {
		register_rest_route(
			self::NS,
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Only requests from the site's own pages count (the browser's Sec-Fetch-Site, else Origin / Referer host).
	 *
	 * @param bool $allow_unknown Read-only requests may come without any origin header.
	 * @return bool
	 */
	public static function same_origin( $allow_unknown = false ) {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';

		if ( isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) {
			return 'same-origin' === sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_SITE'] ) );
		}

		foreach ( array( 'HTTP_ORIGIN', 'HTTP_REFERER' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$url  = esc_url_raw( wp_unslash( $_SERVER[ $key ] ) );
				$from = wp_parse_url( $url, PHP_URL_HOST );
				$port = wp_parse_url( $url, PHP_URL_PORT );

				return '' !== $host && is_string( $from ) && strtolower( $from . ( $port ? ':' . $port : '' ) ) === $host;
			}
		}

		return $allow_unknown;
	}

	/**
	 * Rate limit: true when this visitor (IP — never stored, only a keyed hash) made more than $max requests in the
	 * current $window seconds. $per = false counts every visitor together; a string counts that key (kept as a hash).
	 *
	 * @param string      $action Endpoint.
	 * @param int         $max    Requests.
	 * @param int         $window Seconds.
	 * @param bool|string $per    Per IP, everyone, or a key.
	 * @return bool
	 */
	public static function limited( $action, $max, $window, $per = true ) {
		$who = is_string( $per ) ? 'k:' . $per : ( $per ? self::client_ip() : '*' );

		// IPv6: one visitor usually holds a whole /64, so the limit counts the /64 prefix.
		$bin = false !== strpos( $who, ':' ) && 0 !== strpos( $who, 'k:' ) ? @inet_pton( $who ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input gives false.
		if ( is_string( $bin ) && 16 === strlen( $bin ) ) {
			$who = bin2hex( substr( $bin, 0, 8 ) ) . '::/64';
		}

		return DB::hit( hash_hmac( 'sha1', $action . '|' . $who, Settings::secret() ), (int) $window ) > (int) $max;
	}

	/**
	 * Visitor IP for rate limits: REMOTE_ADDR (filter "abpro_client_ip": an add-on may use a trusted proxy header).
	 * Never stored — only a keyed hash of it.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$server = Commerce::server();
		$remote = isset( $server['REMOTE_ADDR'] ) ? (string) $server['REMOTE_ADDR'] : '';
		$ip     = apply_filters( 'abpro_client_ip', $remote, $server );

		return is_string( $ip ) && filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : $remote;
	}

	/**
	 * Cart refresh (GET ids=1,2) after WooCommerce cart changes: new text of each variant, progress, reached tiers and
	 * whether the cart conditions hold. Read-only JSON; never outputs errors.
	 */
	public function cart() {
		$json = array( 'm' => new \stdClass() );

		try {
			$san = new Sanitizer();
			$ids = $san->id_list( isset( $_GET['ids'] ) && is_string( $_GET['ids'] ) ? sanitize_text_field( wp_unslash( $_GET['ids'] ) ) : '', 20 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, public, cacheless data of the visitor's own cart.
			$get = isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'];

			// Cheap checks first: without valid ids there is nothing to do (no rate-limit write either).
			if ( $ids && $get && ! empty( Settings::get()['status'] ) && self::same_origin( true ) && ! self::limited( 'cart', 1200, 600 ) ) {
				$s        = Settings::effective();
				$renderer = new Renderer( $s );
				$out      = array();

				foreach ( DB::active_messages( determine_locale(), get_locale() ) as $row ) {
					// Page rules cannot be checked here (no page); visitor rules of an add-on can ("abpro_cart_row").
					if ( ! in_array( (int) $row['message_id'], $ids, true ) || ! apply_filters( 'abpro_cart_row', true, $row ) ) {
						continue;
					}

					$item = $renderer->item( $row );

					if ( ! $item || ( ! $item['fs'] && ! $item['cc'] ) ) {
						continue;
					}

					$entry = array(
						'p'    => $item['pct'],
						'done' => $item['done'],
						'k'    => $item['k'],
						'off'  => $item['coff'],
					);

					foreach ( $item['variants'] as $v ) {
						$entry[ $v['name'] ] = wp_kses( $v['txt'], Renderer::kses_text() );
					}

					$out[ (string) (int) $row['message_id'] ] = $entry;
				}

				$json['m'] = $out ? $out : new \stdClass();
			}
		} catch ( \Throwable $e ) {
			unset( $e ); // Never output errors.
		}

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		wp_send_json( $json );
	}

	/**
	 * Admin preview: the posted message form → the bar as the shop would print it, inside a full HTML document for the
	 * sandboxed preview frame (scripts may run there, but the frame has no access to the admin).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function preview( \WP_REST_Request $request ) {
		require_once ABPRO_DIR . 'includes/class-admin.php';

		$post   = $request->get_body_params();
		$admin  = new Admin();
		$locale = (string) $request->get_param( 'pv_locale' );
		$device = 'mobile' === $request->get_param( 'pv_device' ) ? 'mobile' : 'desktop';
		$cart   = (float) $request->get_param( 'pv_cart' );

		list( $data, $texts ) = $admin->message_from_post( is_array( $post ) ? $post : array(), null, true );

		$locales = Settings::locales();
		$locale  = in_array( $locale, $locales, true ) ? $locale : $locales[0];
		$row     = $data;
		$t       = isset( $texts[ $locale ] ) ? $texts[ $locale ] : reset( $texts );

		foreach ( DB::TEXT_FIELDS as $field ) {
			$row[ $field ] = is_array( $t ) && isset( $t[ $field ] ) ? $t[ $field ] : '';
		}

		$row['message_id'] = 1;
		$row['locale']     = $locale;
		$row['status']     = 1;

		$row = apply_filters( 'abpro_preview_row', $row, $request );

		$s        = Settings::effective();
		$renderer = new Renderer( $s, true );
		$renderer->set_sample_subtotal( $cart );

		$item  = is_array( $row ) && $row ? $renderer->item( $row ) : null;
		$html  = '';
		$place = 'top';

		if ( $item ) {
			$places = Renderer::placements();
			$place  = '' !== $item['placement'] && in_array( $item['placement'], $places, true ) ? $item['placement'] : ( 'bottom' === $s['position'] ? 'bottom' : 'top' );
			$inline = Renderer::is_inline( $place );
			$box    = $renderer->container( $place, array( $item ), $inline );

			$box['id'] = $inline ? '' : 'abp-bar';
			$need      = apply_filters( 'abpro_need', array( 'cart' => false ), $item );
			$html      = $renderer->html( array( $box ), $renderer->common( is_array( $need ) ? $need : array() ) );
		}

		return new \WP_REST_Response(
			array(
				'html'   => self::preview_document( $html, $place, $device, (string) apply_filters( 'abpro_preview_css', '', $s ) ),
				'shown'  => (bool) $item,
				'place'  => $place,
				'device' => $device,
			)
		);
	}

	/**
	 * Full HTML document of the preview frame: the shop's real styles and scripts around the bar.
	 *
	 * @param string $bar    Bar HTML.
	 * @param string $place  Placement.
	 * @param string $device desktop | mobile.
	 * @param string $css    Custom CSS (clean).
	 * @return string
	 */
	private static function preview_document( $bar, $place, $device, $css ) {
		$v      = rawurlencode( ABPRO_VERSION );
		$base   = ABPRO_URL . 'assets/';
		$inline = Renderer::is_inline( $place );
		$page   = '<main class="pv-page"><div class="pv-line w60"></div><div class="pv-line w80"></div><div class="pv-line w40"></div></main>';

		if ( $inline ) {
			$page = '<main class="pv-page"><p class="pv-label">' . esc_html( (string) apply_filters( 'abpro_placement_label', $place ) ) . '</p>' . $bar . '<div class="pv-line w80"></div><div class="pv-line w60"></div></main>';
			$bar  = '';
		}

		// The document is shown in an iframe with sandbox="allow-scripts" (opaque origin, no admin access).
		return '<!DOCTYPE html><html lang="' . esc_attr( str_replace( '_', '-', get_locale() ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<link rel="stylesheet" href="' . esc_url( $base . 'css/announcement-bar-pro.css?ver=' . $v ) . '">' // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone preview document.
			. '<link rel="stylesheet" href="' . esc_url( $base . 'css/announcement-bar-pro-wp.css?ver=' . $v ) . '">' // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone preview document.
			. '<style>body{margin:0;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1f2937;background:#f3f4f6;}.pv-page{padding:18px 20px;}.pv-line{height:10px;border-radius:5px;background:#d1d5db;margin:12px 0;}.w40{width:40%}.w60{width:60%}.w80{width:80%}.pv-label{margin:0 0 4px;font-size:12px;font-weight:600;color:#4b5563;text-transform:uppercase;letter-spacing:.05em;}</style>'
			. ( '' !== $css ? '<style id="abp-custom-css">' . $css . '</style>' : '' )
			. '</head><body>' . $bar
			. '<script src="' . esc_url( $base . 'js/announcement-bar-pro-pre.js?ver=' . $v ) . '"></script>' // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone preview document.
			. $page
			. '<script src="' . esc_url( $base . 'js/announcement-bar-pro.js?ver=' . $v ) . '"></script>' // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone preview document.
			. '</body></html>';
	}
}
