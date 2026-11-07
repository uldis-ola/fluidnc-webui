<?php
/**
 * E-mail sign-up: double opt-in, optional Cloudflare Turnstile check, confirmation / unsubscribe pages, the
 * subscriber screen (CSV export, erasure) and the WordPress personal-data exporter / eraser.
 *
 * An unsubscribed address stays unsubscribed: a new sign-up of it by anybody only sends a confirmation e-mail; only
 * the owner of the address, by confirming it, subscribes it again.
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

use AnnouncementBarPro\Admin;
use AnnouncementBarPro\DB;
use AnnouncementBarPro\Endpoints;
use AnnouncementBarPro\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Subscriber list.
 */
class Subscribers {
	/** Query argument of the confirmation / unsubscribe pages. */
	const QUERY = 'abp-subscription';

	/** Confirmation links are valid for 7 days. */
	const TOKEN_DAYS = 7;

	/**
	 * Hooks that work without a licence too: the list stays available (export, erasure, privacy tools, unsubscribe
	 * links in e-mails already sent).
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'page' ), 1 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_text' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 21 );
		add_filter( 'abpro_admin_tabs', array( __CLASS__, 'tab' ), 20 );
		add_filter( 'abpro_admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_post_abpro_subscribers', array( __CLASS__, 'subscribers_action' ) );
		add_action( \AnnouncementBarPro\Plugin::CRON, array( Pro_DB::class, 'cleanup' ) );
	}

	/**
	 * The public sign-up endpoint (licensed sites only).
	 */
	public static function init_signup() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Sign-up route (same URL as the single-plugin build, so cached pages keep working).
	 */
	public static function routes() {
		register_rest_route(
			Endpoints::NS,
			'/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( new self(), 'subscribe' ),
				'permission_callback' => '__return_true', // Public form: form token, honeypot, same origin, rate limits and (optional) Turnstile inside.
			)
		);
	}

	/**
	 * A new confirmation token: 8 hex digits of its creation time + 56 random hex digits. Only its SHA-256 is stored,
	 * so the time cannot be changed without making the token unknown.
	 *
	 * @param int|null $time Unix time.
	 * @return string
	 */
	public static function new_token( $time = null ) {
		return sprintf( '%08x', null === $time ? time() : (int) $time ) . bin2hex( random_bytes( 28 ) );
	}

	/**
	 * Creation time of a token of new_token() (0 when it is not one).
	 *
	 * @param string $token Token.
	 * @return int
	 */
	public static function token_time( $token ) {
		return is_string( $token ) && preg_match( '/^[0-9a-f]{64}$/', $token ) ? (int) hexdec( substr( $token, 0, 8 ) ) : 0;
	}

	/**
	 * True when a token of new_token() is at most 7 days old (and not from the future beyond 5 minutes).
	 *
	 * @param string   $token Token.
	 * @param int|null $now   Unix time.
	 * @return bool
	 */
	public static function token_fresh( $token, $now = null ) {
		$now  = null === $now ? time() : (int) $now;
		$time = self::token_time( $token );

		return $time > 0 && $time <= $now + 300 && $time >= $now - self::TOKEN_DAYS * DAY_IN_SECONDS;
	}

	/**
	 * Unsubscribe token sent with the confirmation of a re-subscription (derived from its confirmation token, so
	 * nothing has to be stored before the owner confirms).
	 *
	 * @param string $token Confirmation token.
	 * @return string
	 */
	public static function derived_unsubscribe( $token ) {
		return hash_hmac( 'sha256', 'unsub|' . (string) $token, Settings::secret() );
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
		$fail = __( 'Sorry, the sign-up did not work. Please try again later.', 'announcement-bar-pro-premium' );

		try {
			$s = Settings::effective();

			if ( empty( $s['status'] ) || ! Endpoints::same_origin() ) {
				$json = array( 'error' => $fail );
			} elseif ( ! $fx->token_valid( (string) $request->get_param( 'tok' ), Settings::secret(), 'abp-signup' ) ) {
				$json = array( 'error' => __( 'This page is too old — please reload it and try again.', 'announcement-bar-pro-premium' ) );
			} elseif ( '' !== (string) $request->get_param( 'website' ) ) {
				$json = array( 'ok' => 1 ); // Honeypot filled: a bot. Pretend it worked, do nothing.
			} elseif ( Endpoints::limited( 'subscribe', (int) $s['signup_limit_ip'], HOUR_IN_SECONDS ) || Endpoints::limited( 'subscribe-all', (int) $s['signup_limit_total'], HOUR_IN_SECONDS, false ) ) {
				$json = array( 'error' => __( 'Too many attempts — please try again later.', 'announcement-bar-pro-premium' ) );
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
						'error' => __( 'Please enter a valid e-mail address.', 'announcement-bar-pro-premium' ),
						'field' => 'email',
					);
				} elseif ( '1' !== (string) $request->get_param( 'consent' ) ) {
					$json = array(
						'error' => __( 'Please tick the box to agree.', 'announcement-bar-pro-premium' ),
						'field' => 'consent',
					);
				} else {
					$check = Turnstile::enabled( $s ) ? Turnstile::verify( $s['turnstile_secret'], $request->get_param( 'cf-turnstile-response' ), Endpoints::client_ip(), array( __CLASS__, 'http_post' ) ) : Turnstile::OK;

					if ( Turnstile::MISSING === $check ) {
						$json = array(
							'error' => __( 'Please wait a moment until the anti-bot check is done, then send again.', 'announcement-bar-pro-premium' ),
							'field' => 'form',
						);
					} elseif ( Turnstile::OK !== $check ) {
						$json = array(
							'error' => __( 'The anti-bot check failed. Please reload the page and try again.', 'announcement-bar-pro-premium' ),
							'field' => 'form',
						);
					} else {
						$consent = $fx->extra_of( $row['extra'] )['email_consent'];
						$json    = $this->sign_up( $email, $id, $locale, '' !== $consent ? $consent : Pro_Render::email_defaults()['consent'], (bool) $s['double_optin'] );
					}
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
	 * Transport of Turnstile::verify(): HTTPS POST through the WordPress HTTP API (certificates checked). The body,
	 * or false on any error.
	 *
	 * @param string $url    Address.
	 * @param array  $fields Form fields.
	 * @return string|false
	 */
	public static function http_post( $url, array $fields ) {
		$answer = wp_remote_post(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'body'        => $fields,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $answer ) || 200 !== (int) wp_remote_retrieve_response_code( $answer ) ) {
			return false;
		}

		return (string) wp_remote_retrieve_body( $answer );
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
		$existing = Pro_DB::subscriber( $email );
		$done     = array(
			'ok'  => 1,
			'msg' => $double ? '' : __( 'Thank you — you are subscribed!', 'announcement-bar-pro-premium' ),
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

		$token = self::new_token();

		// Unsubscribed: the record of the opt-out stays as it is. Only a new confirmation token is stored; the owner of
		// the address subscribes again by confirming it (page()).
		if ( $existing && 2 === (int) $existing['status'] ) {
			Pro_DB::set_resubscribe_token( (int) $existing['subscriber_id'], hash( 'sha256', $token ) );

			if ( ! $this->send_confirmation( $email, $token, self::derived_unsubscribe( $token ) ) ) {
				Pro_DB::unsubscribe( (int) $existing['subscriber_id'] ); // Clears the token; the row stays unsubscribed.

				return array( 'error' => __( 'Sorry, the sign-up did not work. Please try again later.', 'announcement-bar-pro-premium' ) );
			}

			return $done;
		}

		$unsub = bin2hex( random_bytes( 32 ) );

		Pro_DB::save_subscriber(
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
			Pro_DB::count_subscribe( $id );

			return $done;
		}

		if ( ! $this->send_confirmation( $email, $token, $unsub ) ) {
			$row = Pro_DB::subscriber( $email );

			if ( $row && 0 === (int) $row['status'] ) {
				Pro_DB::erase_subscriber( $email ); // Never confirmed and no e-mail went out: no record is kept.
			}

			return array( 'error' => __( 'Sorry, the sign-up did not work. Please try again later.', 'announcement-bar-pro-premium' ) );
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
		$subject = $fx->header_text( sprintf( __( 'Please confirm your subscription — %s', 'announcement-bar-pro-premium' ), $site ) );
		/* translators: %s: site name */
		$body = sprintf( __( 'please confirm that you want to receive news and offers from %s at this address. Open this link and press the button:', 'announcement-bar-pro-premium' ), $site );

		$html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;line-height:1.5;">'
			. '<p>' . esc_html__( 'Hello,', 'announcement-bar-pro-premium' ) . '</p><p>' . esc_html( $body ) . '</p>'
			. '<p><a href="' . esc_url( $confirm ) . '" style="display:inline-block;padding:10px 18px;background:#1f2937;color:#ffffff;text-decoration:none;border-radius:6px;">' . esc_html__( 'Confirm subscription', 'announcement-bar-pro-premium' ) . '</a></p>'
			. '<p style="font-size:13px;color:#4b5563;">' . esc_html__( 'If you did not ask for this, simply ignore this e-mail — you will not be subscribed. The link is valid for 7 days.', 'announcement-bar-pro-premium' ) . '</p>'
			. '<p style="font-size:12px;color:#4b5563;">' . esc_html__( 'Unsubscribe at any time:', 'announcement-bar-pro-premium' ) . ' <a href="' . esc_url( $leave ) . '">' . esc_html( $leave ) . '</a></p></body></html>';

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

		$title = 'confirm' === $action ? __( 'Confirm your subscription', 'announcement-bar-pro-premium' ) : __( 'Unsubscribe', 'announcement-bar-pro-premium' );
		$text  = '';
		$form  = false;
		$row   = null;

		if ( (int) get_option( 'abpro_premium_schema', 0 ) >= 1 && preg_match( '/^[0-9a-f]{64}$/', $token ) ) {
			$row = Pro_DB::subscriber_by_token( 'confirm' === $action ? 'token' : 'unsubscribe', hash( 'sha256', $token ) );
		}

		if ( 'unsubscribe' === $action ) {
			$valid = $row && 2 !== (int) $row['status'];
		} else {
			// A pending sign-up: 7 days from the sign-up; an unsubscribed address: 7 days from the new token.
			$valid = $row && ( ( 0 === (int) $row['status'] && strtotime( $row['date_added'] . ' UTC' ) > time() - self::TOKEN_DAYS * DAY_IN_SECONDS ) || ( 2 === (int) $row['status'] && self::token_fresh( $token ) ) );
		}
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];

		if ( ! $valid ) {
			$text = $row && 'unsubscribe' === $action ? __( 'You have been unsubscribed. You will not receive any more e-mails from us.', 'announcement-bar-pro-premium' ) : __( 'This link is invalid or has expired.', 'announcement-bar-pro-premium' );
		} elseif ( $post && Endpoints::same_origin() ) {
			if ( 'confirm' === $action && 2 === (int) $row['status'] ) {
				Pro_DB::resubscribe( (int) $row['subscriber_id'], hash( 'sha256', self::derived_unsubscribe( $token ) ) );
				Pro_DB::count_subscribe( (int) $row['message_id'] );
				$text = __( 'Thank you — your subscription is confirmed.', 'announcement-bar-pro-premium' );
			} elseif ( 'confirm' === $action ) {
				Pro_DB::confirm_subscriber( (int) $row['subscriber_id'] );
				Pro_DB::count_subscribe( (int) $row['message_id'] );
				$text = __( 'Thank you — your subscription is confirmed.', 'announcement-bar-pro-premium' );
			} else {
				Pro_DB::unsubscribe( (int) $row['subscriber_id'] );
				$text = __( 'You have been unsubscribed. You will not receive any more e-mails from us.', 'announcement-bar-pro-premium' );
			}

			do_action( 'abpro_subscriber_' . ( 'confirm' === $action ? 'confirmed' : 'unsubscribed' ), $row['email'], (int) $row['message_id'] );
		} else {
			$text = 'confirm' === $action
				/* translators: 1: site name, 2: e-mail address */
				? sprintf( __( 'Please confirm that %1$s may send news and offers to %2$s.', 'announcement-bar-pro-premium' ), $site, $row['email'] )
				/* translators: 1: site name, 2: e-mail address */
				: sprintf( __( 'Stop e-mails from %1$s to %2$s?', 'announcement-bar-pro-premium' ), $site, $row['email'] );
			$form = true;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: same-origin' ); // The form's POST must carry its Origin (same-origin check).
		status_header( 200 );

		$button = 'confirm' === $action ? __( 'Confirm subscription', 'announcement-bar-pro-premium' ) : __( 'Unsubscribe', 'announcement-bar-pro-premium' );

		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html( $title . ' — ' . $site ) . '</title>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_language_attributes() is escaped by core.
			. '<style>body{margin:0;background:#f3f4f6;color:#1f2937;font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;}main{max-width:560px;margin:10vh auto;padding:32px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);}h1{font-size:24px;margin:0 0 12px;}button,.btn{display:inline-block;padding:11px 20px;border:0;border-radius:8px;background:#1f2937;color:#fff;font:inherit;font-weight:600;text-decoration:none;cursor:pointer;}button:focus-visible,.btn:focus-visible{outline:3px solid #2563eb;outline-offset:2px;}</style></head><body><main>'
			. '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $text ) . '</p>';

		if ( $form ) {
			echo '<form method="post" action="' . esc_url( self::link( $action, '' ) ) . '"><input type="hidden" name="t" value="' . esc_attr( $token ) . '"><button type="submit">' . esc_html( $button ) . '</button></form>';
		} else {
			echo '<p><a class="btn" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Continue to the site', 'announcement-bar-pro-premium' ) . '</a></p>';
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
			'exporter_friendly_name' => __( 'Announcement Bar Pro sign-ups', 'announcement-bar-pro-premium' ),
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
			'eraser_friendly_name' => __( 'Announcement Bar Pro sign-ups', 'announcement-bar-pro-premium' ),
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
		$row  = Pro_DB::subscriber( (string) $email );
		$data = array();

		if ( $row ) {
			$status = array(
				0 => __( 'Waiting for confirmation', 'announcement-bar-pro-premium' ),
				1 => __( 'Subscribed', 'announcement-bar-pro-premium' ),
				2 => __( 'Unsubscribed', 'announcement-bar-pro-premium' ),
			);
			$data[] = array(
				'group_id'    => 'announcement-bar-pro',
				'group_label' => __( 'Newsletter sign-up (Announcement Bar Pro)', 'announcement-bar-pro-premium' ),
				'item_id'     => 'abp-subscriber-' . (int) $row['subscriber_id'],
				'data'        => array(
					array(
						'name'  => __( 'E-mail address', 'announcement-bar-pro-premium' ),
						'value' => $row['email'],
					),
					array(
						'name'  => __( 'Status', 'announcement-bar-pro-premium' ),
						'value' => isset( $status[ (int) $row['status'] ] ) ? $status[ (int) $row['status'] ] : '',
					),
					array(
						'name'  => __( 'Consent text shown', 'announcement-bar-pro-premium' ),
						'value' => $row['consent'],
					),
					array(
						'name'  => __( 'Signed up (UTC)', 'announcement-bar-pro-premium' ),
						'value' => $row['date_added'],
					),
					array(
						'name'  => __( 'Confirmed (UTC)', 'announcement-bar-pro-premium' ),
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
		$count = Pro_DB::erase_subscriber( (string) $email );

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

		$text = '<p>' . esc_html__( 'Announcement bars on this site remember in your browser (local storage) which announcements you closed, so they stay closed. Nothing is sent to us for this.', 'announcement-bar-pro-premium' ) . '</p>'
			. '<p>' . esc_html__( 'If you sign up for our news through an announcement bar, we store your e-mail address, your language, the consent text you agreed to and the dates of your sign-up and confirmation. We send you one e-mail to confirm the sign-up; without confirmation the entry is deleted after 30 days. Every e-mail has an unsubscribe link.', 'announcement-bar-pro-premium' ) . '</p>'
			. '<p>' . esc_html__( 'View, click and code-copy counts of announcements are stored as totals only, without any personal data. Your IP address is never stored; a keyed hash of it is kept for at most two days to limit abuse of the forms.', 'announcement-bar-pro-premium' ) . '</p>'
			. '<p>' . esc_html__( 'If the anti-bot check Cloudflare Turnstile is switched on, pages with a sign-up form load a script from Cloudflare, Inc., which processes your IP address and technical data of your browser to tell people from bots, and our server sends the result token and your IP address to Cloudflare to check it. If the site waits for cookie consent, the script is loaded only after your consent. See Cloudflare\'s privacy policy.', 'announcement-bar-pro-premium' ) . '</p>';

		wp_add_privacy_policy_content( 'Announcement Bar Pro — Premium', wp_kses_post( $text ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * Subscriber screen
	 * ------------------------------------------------------------------
	 */

	/**
	 * Subscribers screen (after "Templates").
	 */
	public static function menu() {
		add_submenu_page( Admin::SLUG, __( 'Subscribers', 'announcement-bar-pro-premium' ), __( 'Subscribers', 'announcement-bar-pro-premium' ), Admin::CAP, Admin::SLUG . '-subscribers', array( __CLASS__, 'page_subscribers' ), 3 );
	}

	/**
	 * Subscribers tab (before "Settings").
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function tab( $tabs ) {
		$out = array();

		foreach ( (array) $tabs as $key => $label ) {
			if ( 'settings' === $key ) {
				$out['subscribers'] = __( 'Subscribers', 'announcement-bar-pro-premium' );
			}
			$out[ $key ] = $label;
		}

		return $out;
	}

	/**
	 * Notices of the subscriber screen.
	 *
	 * @param array $texts Notices.
	 * @return array
	 */
	public static function notices( $texts ) {
		return array_merge(
			(array) $texts,
			array(
				'erased'    => array( 'success', __( 'The address was deleted from the list.', 'announcement-bar-pro-premium' ) ),
				'not_found' => array( 'warning', __( 'That address is not on the list.', 'announcement-bar-pro-premium' ) ),
			)
		);
	}

	/**
	 * Subscribers screen.
	 */
	public static function page_subscribers() {
		Admin::guard();

		$counts = Pro_DB::subscriber_counts();

		echo '<div class="wrap abp-admin">';
		Admin::header( __( 'Subscribers', 'announcement-bar-pro-premium' ), 'subscribers' );

		if ( ! Licence::is_pro() ) {
			echo '<div class="abp-hint">' . esc_html__( 'The sign-up form needs an active licence key. Existing entries stay here and can still be exported or erased.', 'announcement-bar-pro-premium' ) . '</div>';
		}

		echo '<div class="abp-grid">';
		echo '<section class="abp-card"><h2 class="abp-card-title">' . esc_html__( 'List', 'announcement-bar-pro-premium' ) . '</h2><div class="abp-card-body"><div class="abp-stats abp-stats-lg">';
		Admin::stat( __( 'Subscribed', 'announcement-bar-pro-premium' ), number_format_i18n( $counts[1] ) );
		Admin::stat( __( 'Waiting for confirmation', 'announcement-bar-pro-premium' ), number_format_i18n( $counts[0] ) );
		Admin::stat( __( 'Unsubscribed', 'announcement-bar-pro-premium' ), number_format_i18n( $counts[2] ) );
		echo '</div><p class="abp-help">' . esc_html__( 'Sign-ups are confirmed by e-mail (double opt-in) unless switched off in the settings. Unconfirmed entries are deleted after 30 days. The plugin does not send newsletters: export the confirmed addresses into your mailing tool.', 'announcement-bar-pro-premium' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'abpro_subscribers' );
		echo '<input type="hidden" name="action" value="abpro_subscribers"><input type="hidden" name="do" value="export"><button type="submit" class="button button-primary">' . esc_html__( 'Export CSV', 'announcement-bar-pro-premium' ) . '</button></form></div></section>';

		echo '<section class="abp-card"><h2 class="abp-card-title">' . esc_html__( 'Erase an address', 'announcement-bar-pro-premium' ) . '</h2><div class="abp-card-body"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="abp-confirm-erase">';
		wp_nonce_field( 'abpro_subscribers' );
		echo '<input type="hidden" name="action" value="abpro_subscribers"><input type="hidden" name="do" value="erase"><p><label for="abp-erase-email">' . esc_html__( 'E-mail address', 'announcement-bar-pro-premium' ) . '</label><br><input type="email" id="abp-erase-email" name="email" class="regular-text" required autocomplete="off"></p>'
			. '<p class="abp-help">' . esc_html__( 'Deletes the entry completely (right to erasure). Requests made through Tools → Erase Personal Data are handled automatically as well.', 'announcement-bar-pro-premium' ) . '</p><button type="submit" class="button">' . esc_html__( 'Erase', 'announcement-bar-pro-premium' ) . '</button></form></div></section>';
		echo '</div></div>';
	}

	/**
	 * CSV export / erase (admin-post).
	 */
	public static function subscribers_action() {
		Admin::guard();
		check_admin_referer( 'abpro_subscribers' );

		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		if ( 'erase' === $do ) {
			$email = ( new Features() )->email( isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '' );
			Admin::back( 'subscribers', '' !== $email && Pro_DB::erase_subscriber( $email ) ? 'erased' : 'not_found' );
		}

		if ( 'export' !== $do ) {
			Admin::back( 'subscribers', 'failed' );
		}

		$fx     = new Features();
		$status = array( 'pending', 'subscribed', 'unsubscribed' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );

		echo "\xEF\xBB\xBF" . 'email,status,language,message_id,consent,date_added_utc,date_confirmed_utc' . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV header.

		for ( $offset = 0; ; $offset += 500 ) {
			$rows = Pro_DB::subscribers( $offset, 500 );

			foreach ( $rows as $r ) {
				$cells = array( $r['email'], isset( $status[ (int) $r['status'] ] ) ? $status[ (int) $r['status'] ] : '', $r['locale'], (int) $r['message_id'], $r['consent'], $r['date_added'], (string) $r['date_confirmed'] );
				echo implode( ',', array_map( array( $fx, 'csv_cell' ), $cells ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV cells, formula-safe.
			}

			if ( count( $rows ) < 500 ) {
				break;
			}
		}

		exit;
	}
}
