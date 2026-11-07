<?php
/**
 * Premium settings: their defaults, choices, validation and cards on the settings screen of the free plugin
 * (bottom bar, ticker, more styles, behaviour, privacy and analytics, e-mail sign-up with Cloudflare Turnstile).
 * Same option ("abpro_settings") and keys as the single-plugin build 1.0.0, so stored values keep working.
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

use AnnouncementBarPro\Admin_Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Premium settings.
 */
class Pro_Settings {
	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'abpro_settings_defaults', array( __CLASS__, 'defaults' ) );
		add_filter( 'abpro_setting_choices', array( __CLASS__, 'choices' ), 10, 2 );
		add_filter( 'abpro_effective_settings', array( __CLASS__, 'effective' ), 10, 2 );
		add_filter( 'abpro_validate_settings', array( __CLASS__, 'validate' ), 10, 3 );
		add_action( 'abpro_settings_bar', array( __CLASS__, 'bar_fields' ) );
		add_action( 'abpro_settings_cards', array( __CLASS__, 'cards' ) );
	}

	/**
	 * Default values of the premium settings.
	 *
	 * @param array $defaults Defaults of the free plugin.
	 * @return array
	 */
	public static function defaults( $defaults ) {
		return array_merge(
			(array) $defaults,
			array(
				'max_messages'       => 0,
				'autohide'           => 0,
				'pause_button'       => 1,
				'font'               => '',
				'custom_css'         => '',
				'ga4'                => 0,
				'consent_wait'       => 0,
				'double_optin'       => 1,
				'privacy_id'         => 0,
				'signup_limit_ip'    => 10,
				'signup_limit_total' => 200,
				'geo_cf'             => 0,
				'geo_fallback'       => 'store',
				'turnstile_site_key' => '',
				'turnstile_secret'   => '',
			)
		);
	}

	/**
	 * More choices: bottom bar, scrolling ticker, gradient and glass styles.
	 *
	 * @param array  $choices Choices.
	 * @param string $key     Setting.
	 * @return array
	 */
	public static function choices( $choices, $key ) {
		$choices = (array) $choices;

		if ( 'position' === $key ) {
			$choices['bottom'] = __( 'Bottom of the screen', 'announcement-bar-pro-premium' );
		} elseif ( 'mode' === $key ) {
			$choices['marquee'] = __( 'Scrolling ticker', 'announcement-bar-pro-premium' );
		} elseif ( 'style' === $key ) {
			$choices['gradient'] = __( 'Gradient', 'announcement-bar-pro-premium' );
			$choices['glass']    = __( 'Glass', 'announcement-bar-pro-premium' );
		}

		return $choices;
	}

	/**
	 * The storefront uses every stored value.
	 *
	 * @param array $settings Settings normalised by the free plugin.
	 * @param array $stored   Stored settings.
	 * @return array
	 */
	public static function effective( $settings, $stored ) {
		return is_array( $stored ) ? $stored : $settings;
	}

	/**
	 * Validates the premium settings.
	 *
	 * @param array $result array( clean settings, errors ).
	 * @param array $p      Posted values (unslashed).
	 * @param array $old    Stored settings.
	 * @return array
	 */
	public static function validate( $result, $p, $old ) {
		list( $out, $err ) = $result;

		$p   = (array) $p;
		$fx  = new Features();
		$d   = self::defaults( array() );
		$err = (array) $err;

		$pick  = static function ( $key, array $allowed, $fallback ) use ( $p, &$err ) {
			$value = isset( $p[ $key ] ) && is_string( $p[ $key ] ) ? $p[ $key ] : $fallback;

			if ( ! in_array( $value, $allowed, true ) ) {
				$err[ $key ] = __( 'Please choose one of the offered values.', 'announcement-bar-pro-premium' );
				return $fallback;
			}

			return $value;
		};
		$range = static function ( $key, $min, $max, $fallback ) use ( $p, &$err ) {
			$value = isset( $p[ $key ] ) && is_scalar( $p[ $key ] ) ? trim( (string) $p[ $key ] ) : '';

			if ( ! preg_match( '/^-?\d{1,7}$/', $value ) || (int) $value < $min || (int) $value > $max ) {
				/* translators: 1: smallest value, 2: largest value */
				$err[ $key ] = sprintf( __( 'Please enter a whole number from %1$d to %2$d.', 'announcement-bar-pro-premium' ), $min, $max );
				return $fallback;
			}

			return (int) $value;
		};
		$flag  = static function ( $key ) use ( $p ) {
			return ! empty( $p[ $key ] ) ? 1 : 0;
		};

		$out['speed']              = $range( 'speed', 20, 300, 60 );
		$out['pause_button']       = $flag( 'pause_button' );
		$out['ga4']                = $flag( 'ga4' );
		$out['consent_wait']       = $flag( 'consent_wait' );
		$out['double_optin']       = $flag( 'double_optin' );
		$out['geo_cf']             = $flag( 'geo_cf' );
		$out['max_messages']       = $range( 'max_messages', 0, 20, $d['max_messages'] );
		$out['autohide']           = $range( 'autohide', 0, 600, $d['autohide'] );
		$out['signup_limit_ip']    = $range( 'signup_limit_ip', 1, 1000, $d['signup_limit_ip'] );
		$out['signup_limit_total'] = $range( 'signup_limit_total', 1, 100000, $d['signup_limit_total'] );
		$out['font']               = $pick( 'font', Features::FONTS, '' );
		$out['geo_fallback']       = $pick( 'geo_fallback', array( 'store', 'none' ), 'store' );

		$privacy = isset( $p['privacy_id'] ) && is_scalar( $p['privacy_id'] ) ? (int) $p['privacy_id'] : 0;
		if ( $privacy > 0 && 'publish' !== get_post_status( $privacy ) ) {
			$err['privacy_id'] = __( 'Please choose a published page.', 'announcement-bar-pro-premium' );
			$privacy           = 0;
		}
		$out['privacy_id'] = $privacy;

		$css = isset( $p['custom_css'] ) && is_string( $p['custom_css'] ) ? $p['custom_css'] : '';
		if ( strlen( $css ) > Features::MAX_CSS ) {
			/* translators: %d: number of characters */
			$err['custom_css'] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro-premium' ), Features::MAX_CSS );
		}
		$out['custom_css'] = $fx->css( $css );

		// Cloudflare Turnstile: an empty secret field keeps the stored secret (it is never printed back).
		$site   = isset( $p['turnstile_site_key'] ) && is_string( $p['turnstile_site_key'] ) ? trim( $p['turnstile_site_key'] ) : '';
		$typed  = isset( $p['turnstile_secret'] ) && is_string( $p['turnstile_secret'] ) ? trim( $p['turnstile_secret'] ) : '';
		$stored = isset( $old['turnstile_secret'] ) && is_string( $old['turnstile_secret'] ) ? $old['turnstile_secret'] : '';
		$secret = $stored;

		if ( '' !== $typed ) {
			$secret = Turnstile::secret_key( $typed );

			if ( '' === $secret ) {
				$err['turnstile'] = __( 'This secret key does not look like a Turnstile key. Please copy it again from Cloudflare.', 'announcement-bar-pro-premium' );
				$secret           = $stored;
			}
		} elseif ( ! empty( $p['turnstile_forget'] ) ) {
			$secret = '';
		}

		if ( '' !== $site && '' === Turnstile::site_key( $site ) ) {
			$err['turnstile_site_key'] = __( 'This site key does not look like a Turnstile key. Please copy it again from Cloudflare.', 'announcement-bar-pro-premium' );
			$site                      = isset( $old['turnstile_site_key'] ) ? (string) $old['turnstile_site_key'] : '';
		} elseif ( '' !== $site && '' === $secret && ! isset( $err['turnstile'] ) ) {
			$err['turnstile'] = __( 'Please enter the secret key too — the check is on only with both keys.', 'announcement-bar-pro-premium' );
		}

		$out['turnstile_site_key'] = $site;
		$out['turnstile_secret']   = $secret;

		return array( $out, $err );
	}

	/**
	 * Ticker speed (Bar card).
	 *
	 * @param Admin_Settings_Page $page Page.
	 */
	public static function bar_fields( $page ) {
		$page->number( 'speed', __( 'Ticker speed (px/s)', 'announcement-bar-pro-premium' ), 20, 300 );
	}

	/**
	 * Behaviour, privacy and sign-up cards.
	 *
	 * @param Admin_Settings_Page $page Page.
	 */
	public static function cards( $page ) {
		$page->card( __( 'Behaviour', 'announcement-bar-pro-premium' ), 'bolt' );
		echo '<div class="abp-row2">';
		$page->number( 'max_messages', __( 'Most messages at once', 'announcement-bar-pro-premium' ), 0, 20, __( '0 = no limit; higher priority wins.', 'announcement-bar-pro-premium' ) );
		$page->number( 'autohide', __( 'Hide the bar after (seconds)', 'announcement-bar-pro-premium' ), 0, 600, __( '0 = never; a small tab brings it back.', 'announcement-bar-pro-premium' ) );
		echo '</div>';
		$page->check( 'pause_button', __( 'Pause button for moving messages', 'announcement-bar-pro-premium' ) );
		$page->select( 'font', __( 'Font of all bars', 'announcement-bar-pro-premium' ), Pro_Admin::fonts() );
		$page->row( 'custom_css', __( 'Custom CSS', 'announcement-bar-pro-premium' ), '<textarea id="abp-s-custom_css" name="custom_css" rows="5" class="large-text code" spellcheck="false"' . $page->aria( 'custom_css', true ) . '>' . esc_textarea( (string) $page->value( 'custom_css' ) ) . '</textarea>', __( 'Cleaned on save: no @import, scripts or outside code.', 'announcement-bar-pro-premium' ) );
		$page->end_card();

		$page->card( __( 'Privacy and analytics', 'announcement-bar-pro-premium' ), 'shield' );
		$page->check( 'consent_wait', __( 'Wait for cookie consent', 'announcement-bar-pro-premium' ), __( 'A/B choice, visitor memory, statistics, GA4 events and the Cloudflare Turnstile check start only after the consent tool allows it (WP Consent API, or window.abpConsent = true).', 'announcement-bar-pro-premium' ) );
		$page->check( 'ga4', __( 'Send GA4 events (view, click, copy, sign-up)', 'announcement-bar-pro-premium' ), __( 'Only if the site already loads Google Analytics (gtag or Tag Manager). The plugin itself loads nothing.', 'announcement-bar-pro-premium' ) );
		$page->check( 'geo_cf', __( 'Use CloudFlare\'s country header', 'announcement-bar-pro-premium' ), __( 'Only for requests that really come from CloudFlare\'s addresses.', 'announcement-bar-pro-premium' ) );
		$page->select(
			'geo_fallback',
			__( 'Unknown country', 'announcement-bar-pro-premium' ),
			array(
				'store' => __( 'Treat as the shop\'s country', 'announcement-bar-pro-premium' ),
				'none'  => __( 'Treat as unknown', 'announcement-bar-pro-premium' ),
			)
		);
		$page->end_card();

		$page->card( __( 'E-mail sign-up', 'announcement-bar-pro-premium' ), 'mail' );
		$page->check( 'double_optin', __( 'Confirm sign-ups by e-mail (double opt-in, recommended)', 'announcement-bar-pro-premium' ) );
		$pages = wp_dropdown_pages(
			array(
				'name'              => 'privacy_id',
				'id'                => 'abp-s-privacy_id',
				'selected'          => (int) $page->value( 'privacy_id' ),
				'show_option_none'  => esc_html__( '— WordPress privacy page —', 'announcement-bar-pro-premium' ),
				'option_none_value' => '0',
				'echo'              => 0,
			)
		);
		$page->row( 'privacy_id', __( 'Privacy page linked in the form', 'announcement-bar-pro-premium' ), (string) $pages );
		echo '<div class="abp-row2">';
		$page->number( 'signup_limit_ip', __( 'Sign-ups per visitor per hour', 'announcement-bar-pro-premium' ), 1, 1000 );
		$page->number( 'signup_limit_total', __( 'Sign-ups per hour in total', 'announcement-bar-pro-premium' ), 1, 100000 );
		echo '</div>';

		$saved = '' !== Turnstile::secret_key( (string) $page->value( 'turnstile_secret' ) );
		echo '<fieldset class="abp-group abp-turnstile"><legend>' . esc_html__( 'Cloudflare Turnstile (optional anti-bot check)', 'announcement-bar-pro-premium' ) . '</legend>';
		echo '<p class="abp-help" id="abp-s-turnstile-about">' . esc_html__( 'Off unless both keys are filled in. Create a free widget in your Cloudflare account (Turnstile), add this site\'s domain and copy its site key and secret key here. When it is on, Cloudflare\'s script is loaded only on pages with a sign-up form, and Cloudflare processes the visitor\'s IP address and browser data for the check. With "Wait for cookie consent" the script loads only after consent. Mention Cloudflare Turnstile in your privacy policy.', 'announcement-bar-pro-premium' ) . '</p>';
		$page->row( 'turnstile_site_key', __( 'Site key', 'announcement-bar-pro-premium' ), '<input type="text" class="regular-text code" id="abp-s-turnstile_site_key" name="turnstile_site_key" value="' . esc_attr( (string) $page->value( 'turnstile_site_key' ) ) . '" autocomplete="off" spellcheck="false"' . $page->aria( 'turnstile_site_key', false ) . '>' );
		echo '<div class="abp-field"><label for="abp-s-turnstile_secret">' . esc_html__( 'Secret key', 'announcement-bar-pro-premium' ) . '</label>'
			. '<input type="password" class="regular-text code" id="abp-s-turnstile_secret" name="turnstile_secret" value="" autocomplete="new-password" spellcheck="false" aria-describedby="abp-s-turnstile-help' . ( '' !== $page->error( 'turnstile' ) ? ' abp-s-turnstile-err' : '' ) . '"' . ( '' !== $page->error( 'turnstile' ) ? ' aria-invalid="true"' : '' ) . ( $saved ? ' placeholder="••••••••••••"' : '' ) . '>'
			. '<p class="abp-help" id="abp-s-turnstile-help">' . esc_html( $saved ? __( 'A secret key is saved — leave the field empty to keep it. It is never shown again.', 'announcement-bar-pro-premium' ) : __( 'Kept on the server only; never shown on any page.', 'announcement-bar-pro-premium' ) ) . '</p>';
		if ( '' !== $page->error( 'turnstile' ) ) {
			echo '<p class="abp-error" id="abp-s-turnstile-err">' . esc_html( $page->error( 'turnstile' ) ) . '</p>';
		}
		echo '</div>';
		if ( $saved ) {
			echo '<div class="abp-field abp-check"><input type="checkbox" id="abp-s-turnstile_forget" name="turnstile_forget" value="1"> <label for="abp-s-turnstile_forget">' . esc_html__( 'Remove the saved secret key (switches the check off)', 'announcement-bar-pro-premium' ) . '</label></div>';
		}
		echo '<p class="abp-small">' . esc_html(
			Turnstile::enabled(
				array(
					'turnstile_site_key' => $page->value( 'turnstile_site_key' ),
					'turnstile_secret'   => $page->value( 'turnstile_secret' ),
				)
			) ? __(
				'Status: on',
				'announcement-bar-pro-premium'
			) : __(
				'Status: off',
				'announcement-bar-pro-premium'
			)
		) . '</p>';
		echo '</fieldset>';
		$page->end_card();
	}
}
