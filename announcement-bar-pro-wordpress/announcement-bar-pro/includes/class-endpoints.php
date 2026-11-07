<?php
/**
 * Public endpoints: statistics (REST, POST), e-mail sign-up (REST, POST), cart refresh (WooCommerce AJAX, GET,
 * read-only) — and the admin-only preview / search routes. None of them writes a session; every public one is
 * same-origin only and rate limited.
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
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track' ),
				'permission_callback' => '__return_true', // Public: anonymous counters, checked inside (same origin, rate limit).
			)
		);
		register_rest_route(
			self::NS,
			'/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( new Subscribers(), 'subscribe' ),
				'permission_callback' => '__return_true', // Public form: form token, honeypot, same origin and rate limits inside.
			)
		);
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
		$fx  = new Features();
		$who = is_string( $per ) ? 'k:' . $per : ( $per ? $fx->client_ip( Commerce::server(), (bool) Settings::effective()['geo_cf'] ) : '*' );

		// IPv6: one visitor usually holds a whole /64, so the limit counts the /64 prefix.
		$bin = false !== strpos( $who, ':' ) && 0 !== strpos( $who, 'k:' ) ? @inet_pton( $who ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input gives false.
		if ( is_string( $bin ) && 16 === strlen( $bin ) ) {
			$who = bin2hex( substr( $bin, 0, 8 ) ) . '::/64';
		}

		return DB::hit( hash_hmac( 'sha1', $action . '|' . $who, Settings::secret() ), (int) $window ) > (int) $max;
	}

	/**
	 * Statistics (POST id, type, v). Counters only; nothing personal is read or stored. Always 204.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function track( \WP_REST_Request $request ) {
		try {
			$id      = (string) $request->get_param( 'id' );
			$type    = (string) $request->get_param( 'type' );
			$variant = 'b' === $request->get_param( 'v' ) ? 'b' : 'a';

			if ( Licence::is_pro() && ! empty( Settings::get()['status'] ) && preg_match( '/^\d{1,10}$/', $id ) && in_array( $type, array( 'view', 'click', 'copy' ), true ) && self::same_origin() && ! self::limited( 'track', 300, 600 ) ) {
				DB::track( (int) $id, $type, $variant );
			}
		} catch ( \Throwable $e ) {
			// Statistics must never produce an error.
			unset( $e );
		}

		$response = new \WP_REST_Response( null, 204 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
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
				$renderer = new Renderer( $s, Licence::is_pro() );
				$out      = array();
				$user     = wp_get_current_user();
				$mine     = $user->exists() ? (array) $user->roles : array( 'guest' );

				foreach ( DB::active_messages( determine_locale(), get_locale() ) as $row ) {
					$roles = json_decode( (string) $row['roles'], true );

					if ( ! in_array( (int) $row['message_id'], $ids, true ) || ( is_array( $roles ) && $roles && ! array_intersect( $roles, $mine ) ) ) {
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
		$abv    = 'b' === $request->get_param( 'pv_variant' ) ? 'b' : 'a';
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

		if ( 'b' === $abv && ! empty( $row['ab_test'] ) && '' !== trim( $row['text_b'] ) ) {
			$row['text']        = $row['text_b'];
			$row['mobile_text'] = $row['mobile_text_b'];
			$row['link_text']   = '' !== trim( $row['link_text_b'] ) ? $row['link_text_b'] : $row['link_text'];
			$row['ab_test']     = 0;
		}

		$s        = Settings::effective();
		$pro      = Licence::is_pro();
		$renderer = new Renderer( $s, $pro, true );
		$renderer->set_sample_subtotal( $cart );

		$item  = $renderer->item( $row );
		$html  = '';
		$place = 'top';

		if ( $item ) {
			$place  = in_array( $item['placement'], array( 'top', 'bottom', 'cart', 'product', 'minicart' ), true ) ? $item['placement'] : ( 'bottom' === $s['position'] ? 'bottom' : 'top' );
			$inline = in_array( $place, array( 'cart', 'product', 'minicart' ), true );
			$box    = $renderer->container( $place, array( $item ), $inline );

			$box['id'] = $inline ? '' : 'abp-bar';
			$need      = array(
				'cart'  => false,
				'email' => (bool) $item['email'],
			);
			$html      = $renderer->html( array( $box ), $renderer->common( $need ) );
		}

		return new \WP_REST_Response(
			array(
				'html'   => self::preview_document( $html, $place, $device, '' !== $s['custom_css'] ? ( new Features() )->css( $s['custom_css'] ) : '' ),
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
		$inline = in_array( $place, array( 'cart', 'product', 'minicart' ), true );
		$labels = array(
			'cart'     => __( 'Cart', 'announcement-bar-pro' ),
			'product'  => __( 'Product page — under the price', 'announcement-bar-pro' ),
			'minicart' => __( 'Mini-cart', 'announcement-bar-pro' ),
		);
		$page   = '<main class="pv-page"><div class="pv-line w60"></div><div class="pv-line w80"></div><div class="pv-line w40"></div></main>';

		if ( $inline ) {
			$page = '<main class="pv-page"><p class="pv-label">' . esc_html( $labels[ $place ] ) . '</p>' . $bar . '<div class="pv-line w80"></div><div class="pv-line w60"></div></main>';
			$bar  = '';
		}

		// The document is shown in an iframe with sandbox="allow-scripts" (opaque origin, no admin access).
		return '<!DOCTYPE html><html lang="' . esc_attr( str_replace( '_', '-', get_locale() ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<link rel="stylesheet" href="' . esc_url( $base . 'css/announcement-bar-pro.css?ver=' . $v ) . '">' // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone preview document.
			. '<link rel="stylesheet" href="' . esc_url( $base . 'css/announcement-bar-pro-wp.css?ver=' . $v ) . '">' // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone preview document.
			. '<style>body{margin:0;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1f2937;background:#f3f4f6;}.pv-page{padding:18px 20px;}.pv-line{height:10px;border-radius:5px;background:#d1d5db;margin:12px 0;}.w40{width:40%}.w60{width:60%}.w80{width:80%}.pv-label{margin:0 0 4px;font-size:12px;font-weight:600;color:#4b5563;text-transform:uppercase;letter-spacing:.05em;}' . ( 'mobile' === $device ? '' : '' ) . '</style>'
			. ( '' !== $css ? '<style id="abp-custom-css">' . $css . '</style>' : '' )
			. '</head><body>' . $bar
			. '<script src="' . esc_url( $base . 'js/announcement-bar-pro-pre.js?ver=' . $v ) . '"></script>' // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone preview document.
			. $page
			. '<script src="' . esc_url( $base . 'js/announcement-bar-pro.js?ver=' . $v ) . '"></script>' // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone preview document.
			. '</body></html>';
	}
}
