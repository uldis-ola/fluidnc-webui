<?php
/**
 * E-mail sign-up (Pro): double opt-in, confirmation / unsubscribe pages, CSV export, erasure and the WordPress
 * personal-data exporter / eraser.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Subscriber list.
 */
class Subscribers {
	/** Query argument of the confirmation / unsubscribe pages. */
	const QUERY = 'abp-subscription';

	/**
	 * Hooks (once per request).
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'page' ), 1 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_text' ) );
	}

	/**
	 * Sign-up (POST email, consent, tok, id, honeypot "website"). JSON {ok, msg} or {error, field}. The answer never
	 * tells whether an address was already on the list.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function subscribe( \WP_REST_Request $request ) {
		$fx   = new Features();
		$fail = __( 'Sorry, the sign-up did not work. Please try again later.', 'announcement-bar-pro' );

		try {
			$s = Settings::effective();

			if ( ! Licence::is_pro() || empty( $s['status'] ) || ! Endpoints::same_origin() ) {
				$json = array( 'error' => $fail );
			} elseif ( ! $fx->token_valid( (string) $request->get_param( 'tok' ), Settings::secret(), 'abp-signup' ) ) {
				$json = array( 'error' => __( 'This page is too old — please reload it and try again.', 'announcement-bar-pro' ) );
			} elseif ( '' !== (string) $request->get_param( 'website' ) ) {
				$json = array( 'ok' => 1 ); // Honeypot filled: a bot. Pretend it worked, do nothing.
			} elseif ( Endpoints::limited( 'subscribe', (int) $s['signup_limit_ip'], HOUR_IN_SECONDS ) || Endpoints::limited( 'subscribe-all', (int) $s['signup_limit_total'], HOUR_IN_SECONDS, false ) ) {
				$json = array( 'error' => __( 'Too many attempts — please try again later.', 'announcement-bar-pro' ) );
			} else {
				$email  = $fx->email( (string) $request->get_param( 'email' ) );
				$id     = (string) $request->get_param( 'id' );
				$id     = preg_match( '/^\d{1,10}$/', $id ) ? (int) $id : 0;
				$locale = determine_locale();
				$row    = null;

				foreach ( DB::active_messages( $locale, get_locale() ) as $m ) {
					if ( (int) $m['message_id'] === $id ) {
						$row = $m;
						break;
					}
				}

				$opt = $row ? $fx->options( $row['options'] ) : array();

				if ( ! $row || empty( $opt['email'] ) ) {
					$json = array( 'error' => $fail );
				} elseif ( '' === $email ) {
					$json = array(
						'error' => __( 'Please enter a valid e-mail address.', 'announcement-bar-pro' ),
						'field' => 'email',
					);
				} elseif ( '1' !== (string) $request->get_param( 'consent' ) ) {
					$json = array(
						'error' => __( 'Please tick the box to agree.', 'announcement-bar-pro' ),
						'field' => 'consent',
					);
				} else {
					$consent = $fx->extra_of( $row['extra'] )['email_consent'];
					$json    = $this->sign_up( $email, $id, $locale, '' !== $consent ? $consent : Renderer::email_defaults()['consent'], (bool) $s['double_optin'] );
				}
			}
		} catch ( \Throwable $e ) {
			$json = array( 'error' => $fail );
		}

		$response = new \WP_REST_Response( $json, 200 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Stores the sign-up and sends the confirmation e-mail (double opt-in), or subscribes at once (opt-in off).
	 *
	 * @param string $email   Valid address.
	 * @param int    $id      Message ID.
	 * @param string $locale  Visitor's locale.
	 * @param string $consent Consent text this site showed (proof of consent; never a text sent by the browser).
	 * @param bool   $double  Double opt-in.
	 * @return array
	 */
	private function sign_up( $email, $id, $locale, $consent, $double ) {
		$existing = DB::subscriber( $email );
		$done     = array(
			'ok'  => 1,
			'msg' => $double ? '' : __( 'Thank you — you are subscribed!', 'announcement-bar-pro' ),
		);

		// Already confirmed, or a confirmation e-mail went out less than 10 minutes ago: same answer, nothing sent.
		if ( $existing && ( 1 === (int) $existing['status'] || ( 0 === (int) $existing['status'] && strtotime( $existing['date_added'] . ' UTC' ) > time() - 600 ) ) ) {
			return $done;
		}

		// Opt-in off: an unsubscribed address stays unsubscribed (only its owner can sign up again by double opt-in).
		if ( ! $double && $existing && 2 === (int) $existing['status'] ) {
			return $done;
		}

		// At most 3 confirmation e-mails per address in 24 hours.
		if ( $double && Endpoints::limited( 'subscribe-mail', 3, DAY_IN_SECONDS, strtolower( $email ) ) ) {
			return $done;
		}

		$token = bin2hex( random_bytes( 32 ) );
		$unsub = bin2hex( random_bytes( 32 ) );

		DB::save_subscriber(
			array(
				'email'       => $email,
				'locale'      => $locale,
				'message_id'  => $id,
				'status'      => $double ? 0 : 1,
				'token'       => $double ? hash( 'sha256', $token ) : '',
				'unsubscribe' => hash( 'sha256', $unsub ),
				'consent'     => function_exists( 'mb_substr' ) ? mb_substr( $consent, 0, 400, 'UTF-8' ) : substr( $consent, 0, 400 ),
			)
		);

		if ( ! $double ) {
			DB::count_subscribe( $id );

			return $done;
		}

		if ( ! $this->send_confirmation( $email, $token, $unsub ) ) {
			$row = DB::subscriber( $email );

			if ( $row ) {
				DB::unsubscribe( (int) $row['subscriber_id'] );
			}

			return array( 'error' => __( 'Sorry, the sign-up did not work. Please try again later.', 'announcement-bar-pro' ) );
		}

		return $done;
	}

	/**
	 * Double opt-in e-mail through wp_mail(). False when it could not be sent.
	 *
	 * @param string $email Address.
	 * @param string $token Confirmation token.
	 * @param string $unsub Unsubscribe token.
	 * @return bool
	 */
	private function send_confirmation( $email, $token, $unsub ) {
		$fx = new Features();

		if ( '' === $fx->email( $email ) ) {
			return false;
		}

		$site    = $fx->header_text( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$confirm = self::link( 'confirm', $token );
		$leave   = self::link( 'unsubscribe', $unsub );
		/* translators: %s: site name */
		$subject = $fx->header_text( sprintf( __( 'Please confirm your subscription — %s', 'announcement-bar-pro' ), $site ) );
		/* translators: %s: site name */
		$body = sprintf( __( 'please confirm that you want to receive news and offers from %s at this address. Open this link and press the button:', 'announcement-bar-pro' ), $site );

		$html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;line-height:1.5;">'
			. '<p>' . esc_html__( 'Hello,', 'announcement-bar-pro' ) . '</p><p>' . esc_html( $body ) . '</p>'
			. '<p><a href="' . esc_url( $confirm ) . '" style="display:inline-block;padding:10px 18px;background:#1f2937;color:#ffffff;text-decoration:none;border-radius:6px;">' . esc_html__( 'Confirm subscription', 'announcement-bar-pro' ) . '</a></p>'
			. '<p style="font-size:13px;color:#4b5563;">' . esc_html__( 'If you did not ask for this, simply ignore this e-mail — you will not be subscribed. The link is valid for 7 days.', 'announcement-bar-pro' ) . '</p>'
			. '<p style="font-size:12px;color:#4b5563;">' . esc_html__( 'Unsubscribe at any time:', 'announcement-bar-pro' ) . ' <a href="' . esc_url( $leave ) . '">' . esc_html( $leave ) . '</a></p></body></html>';

		// The recipient was validated (no CR / LF, quotes or backslashes); header_text() is a second line of defence.
		return (bool) wp_mail( $fx->header_text( $email ), $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Link to the confirmation / unsubscribe page.
	 *
	 * @param string $action confirm | unsubscribe.
	 * @param string $token  Token (64 hex).
	 * @return string
	 */
	public static function link( $action, $token ) {
		return add_query_arg(
			array(
				self::QUERY => $action,
				't'         => $token,
			),
			home_url( '/' )
		);
	}

	/**
	 * Confirmation / unsubscribe page. GET shows a button (mail scanners that open links must not confirm anything);
	 * POST (same origin) does it.
	 */
	public static function page() {
		// phpcs:disable WordPress.Security.NonceVerification -- the 64-hex token from the e-mail is the proof; POST is same-origin checked.
		$action = isset( $_GET[ self::QUERY ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY ] ) ) : '';

		if ( 'confirm' !== $action && 'unsubscribe' !== $action ) {
			return;
		}

		$token = '';
		if ( isset( $_POST['t'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST['t'] ) );
		} elseif ( isset( $_GET['t'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['t'] ) );
		}
		// phpcs:enable

		$title = 'confirm' === $action ? __( 'Confirm your subscription', 'announcement-bar-pro' ) : __( 'Unsubscribe', 'announcement-bar-pro' );
		$text  = '';
		$form  = false;
		$row   = null;

		if ( (int) get_option( 'abpro_schema', 0 ) >= 1 && preg_match( '/^[0-9a-f]{64}$/', $token ) ) {
			$row = DB::subscriber_by_token( 'confirm' === $action ? 'token' : 'unsubscribe', hash( 'sha256', $token ) );
		}

		$valid = $row && ( 'unsubscribe' === $action ? 2 !== (int) $row['status'] : 0 === (int) $row['status'] && strtotime( $row['date_added'] . ' UTC' ) > time() - 7 * DAY_IN_SECONDS );
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$post  = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];

		if ( ! $valid ) {
			$text = $row && 'unsubscribe' === $action ? __( 'You have been unsubscribed. You will not receive any more e-mails from us.', 'announcement-bar-pro' ) : __( 'This link is invalid or has expired.', 'announcement-bar-pro' );
		} elseif ( $post && Endpoints::same_origin() ) {
			if ( 'confirm' === $action ) {
				DB::confirm_subscriber( (int) $row['subscriber_id'] );
				DB::count_subscribe( (int) $row['message_id'] );
				$text = __( 'Thank you — your subscription is confirmed.', 'announcement-bar-pro' );
			} else {
				DB::unsubscribe( (int) $row['subscriber_id'] );
				$text = __( 'You have been unsubscribed. You will not receive any more e-mails from us.', 'announcement-bar-pro' );
			}

			do_action( 'abpro_subscriber_' . ( 'confirm' === $action ? 'confirmed' : 'unsubscribed' ), $row['email'], (int) $row['message_id'] );
		} else {
			$text = 'confirm' === $action
				/* translators: 1: site name, 2: e-mail address */
				? sprintf( __( 'Please confirm that %1$s may send news and offers to %2$s.', 'announcement-bar-pro' ), $site, $row['email'] )
				/* translators: 1: site name, 2: e-mail address */
				: sprintf( __( 'Stop e-mails from %1$s to %2$s?', 'announcement-bar-pro' ), $site, $row['email'] );
			$form = true;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: same-origin' ); // The form's POST must carry its Origin (same-origin check).
		status_header( 200 );

		$button = 'confirm' === $action ? __( 'Confirm subscription', 'announcement-bar-pro' ) : __( 'Unsubscribe', 'announcement-bar-pro' );

		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html( $title . ' — ' . $site ) . '</title>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_language_attributes() is escaped by core.
			. '<style>body{margin:0;background:#f3f4f6;color:#1f2937;font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;}main{max-width:560px;margin:10vh auto;padding:32px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);}h1{font-size:24px;margin:0 0 12px;}button,.btn{display:inline-block;padding:11px 20px;border:0;border-radius:8px;background:#1f2937;color:#fff;font:inherit;font-weight:600;text-decoration:none;cursor:pointer;}button:focus-visible,.btn:focus-visible{outline:3px solid #2563eb;outline-offset:2px;}</style></head><body><main>'
			. '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $text ) . '</p>';

		if ( $form ) {
			echo '<form method="post" action="' . esc_url( self::link( $action, '' ) ) . '"><input type="hidden" name="t" value="' . esc_attr( $token ) . '"><button type="submit">' . esc_html( $button ) . '</button></form>';
		} else {
			echo '<p><a class="btn" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Continue to the site', 'announcement-bar-pro' ) . '</a></p>';
		}

		echo '</main></body></html>';
		exit;
	}

	/*
	 * ------------------------------------------------------------------
	 * Privacy: exporter, eraser, policy text
	 * ------------------------------------------------------------------
	 */

	/**
	 * Registers the personal-data exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['announcement-bar-pro'] = array(
			'exporter_friendly_name' => __( 'Announcement Bar Pro sign-ups', 'announcement-bar-pro' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Registers the personal-data eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['announcement-bar-pro'] = array(
			'eraser_friendly_name' => __( 'Announcement Bar Pro sign-ups', 'announcement-bar-pro' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Personal-data export of one address.
	 *
	 * @param string $email Address.
	 * @return array
	 */
	public static function export( $email ) {
		$row  = DB::subscriber( (string) $email );
		$data = array();

		if ( $row ) {
			$status = array(
				0 => __( 'Waiting for confirmation', 'announcement-bar-pro' ),
				1 => __( 'Subscribed', 'announcement-bar-pro' ),
				2 => __( 'Unsubscribed', 'announcement-bar-pro' ),
			);
			$data[] = array(
				'group_id'    => 'announcement-bar-pro',
				'group_label' => __( 'Newsletter sign-up (Announcement Bar Pro)', 'announcement-bar-pro' ),
				'item_id'     => 'abp-subscriber-' . (int) $row['subscriber_id'],
				'data'        => array(
					array(
						'name'  => __( 'E-mail address', 'announcement-bar-pro' ),
						'value' => $row['email'],
					),
					array(
						'name'  => __( 'Status', 'announcement-bar-pro' ),
						'value' => isset( $status[ (int) $row['status'] ] ) ? $status[ (int) $row['status'] ] : '',
					),
					array(
						'name'  => __( 'Consent text shown', 'announcement-bar-pro' ),
						'value' => $row['consent'],
					),
					array(
						'name'  => __( 'Signed up (UTC)', 'announcement-bar-pro' ),
						'value' => $row['date_added'],
					),
					array(
						'name'  => __( 'Confirmed (UTC)', 'announcement-bar-pro' ),
						'value' => (string) $row['date_confirmed'],
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Personal-data erasure of one address.
	 *
	 * @param string $email Address.
	 * @return array
	 */
	public static function erase( $email ) {
		$count = DB::erase_subscriber( (string) $email );

		return array(
			'items_removed'  => $count > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested text for the site's privacy policy (Settings → Privacy).
	 */
	public static function privacy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$text = '<p>' . esc_html__( 'Announcement bars on this site remember in your browser (local storage) which announcements you closed, so they stay closed. Nothing is sent to us for this.', 'announcement-bar-pro' ) . '</p>'
			. '<p>' . esc_html__( 'If you sign up for our news through an announcement bar, we store your e-mail address, your language, the consent text you agreed to and the dates of your sign-up and confirmation. We send you one e-mail to confirm the sign-up; without confirmation the entry is deleted after 30 days. Every e-mail has an unsubscribe link.', 'announcement-bar-pro' ) . '</p>'
			. '<p>' . esc_html__( 'View, click and code-copy counts of announcements are stored as totals only, without any personal data. Your IP address is never stored; a keyed hash of it is kept for at most two days to limit abuse of the forms.', 'announcement-bar-pro' ) . '</p>';

		wp_add_privacy_policy_content( 'Announcement Bar Pro', wp_kses_post( $text ) );
	}
}
