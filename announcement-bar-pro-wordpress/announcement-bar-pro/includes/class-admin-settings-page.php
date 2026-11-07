<?php
/**
 * Settings screen: bar behaviour, look, Pro options, sign-up, privacy, data and the licence.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the settings screen.
 */
class Admin_Settings_Page {
	/**
	 * Values shown.
	 *
	 * @var array
	 */
	private $v;

	/**
	 * Errors.
	 *
	 * @var array
	 */
	private $err = array();

	/**
	 * Pro licence.
	 *
	 * @var bool
	 */
	private $pro;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->v   = Settings::get();
		$this->pro = Licence::is_pro();
		$kept      = get_transient( 'abpro_settings_' . get_current_user_id() );

		if ( is_array( $kept ) ) {
			$this->v   = array_merge( $this->v, $kept['values'] );
			$this->err = $kept['errors'];
			delete_transient( 'abpro_settings_' . get_current_user_id() );
		}
	}

	/**
	 * Field row with label, control, help and error.
	 *
	 * @param string $key     Setting.
	 * @param string $label   Label.
	 * @param string $control Control HTML (escaped by the caller).
	 * @param string $help    Help text.
	 */
	private function row( $key, $label, $control, $help = '' ) {
		echo '<div class="abp-field"><label for="abp-s-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>' . $control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.

		if ( '' !== $help ) {
			echo '<p class="abp-help" id="abp-s-' . esc_attr( $key ) . '-help">' . esc_html( $help ) . '</p>';
		}

		if ( isset( $this->err[ $key ] ) ) {
			echo '<p class="abp-error" id="abp-s-' . esc_attr( $key ) . '-err">' . esc_html( $this->err[ $key ] ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * ARIA attributes of a control.
	 *
	 * @param string $key  Setting.
	 * @param bool   $help Has help.
	 * @return string
	 */
	private function aria( $key, $help ) {
		$ids = array();

		if ( $help ) {
			$ids[] = 'abp-s-' . $key . '-help';
		}

		if ( isset( $this->err[ $key ] ) ) {
			$ids[] = 'abp-s-' . $key . '-err';
		}

		return ( $ids ? ' aria-describedby="' . esc_attr( implode( ' ', $ids ) ) . '"' : '' ) . ( isset( $this->err[ $key ] ) ? ' aria-invalid="true"' : '' );
	}

	/**
	 * Number input.
	 *
	 * @param string $key   Setting.
	 * @param string $label Label.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @param string $help  Help.
	 */
	private function number( $key, $label, $min, $max, $help = '' ) {
		$this->row( $key, $label, '<input type="number" class="small-text" id="abp-s-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" min="' . (int) $min . '" max="' . (int) $max . '" value="' . esc_attr( (string) $this->v[ $key ] ) . '"' . $this->aria( $key, '' !== $help ) . '>', $help );
	}

	/**
	 * Select.
	 *
	 * @param string $key     Setting.
	 * @param string $label   Label.
	 * @param array  $choices value => label.
	 * @param string $help    Help.
	 */
	private function select( $key, $label, array $choices, $help = '' ) {
		$html = '<select id="abp-s-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"' . $this->aria( $key, '' !== $help ) . '>';

		foreach ( $choices as $value => $text ) {
			$html .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $this->v[ $key ], (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}

		$this->row( $key, $label, $html . '</select>', $help );
	}

	/**
	 * Checkbox.
	 *
	 * @param string $key   Setting.
	 * @param string $label Label.
	 * @param string $help  Help.
	 */
	private function check( $key, $label, $help = '' ) {
		echo '<div class="abp-field abp-check"><input type="checkbox" id="abp-s-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1"' . checked( ! empty( $this->v[ $key ] ), true, false ) . $this->aria( $key, '' !== $help ) . '> <label for="abp-s-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aria() escapes.

		if ( '' !== $help ) {
			echo '<p class="abp-help" id="abp-s-' . esc_attr( $key ) . '-help">' . esc_html( $help ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Opens a card.
	 *
	 * @param string $title Title.
	 * @param string $icon  Icon.
	 * @param bool   $pro   Pro card.
	 */
	private function card( $title, $icon, $pro = false ) {
		$locked = $pro && ! $this->pro;

		echo '<section class="abp-card"><h2 class="abp-card-title"><span class="abp-ico-badge" aria-hidden="true">' . Icons::svg( $icon, 'abp-card-ico' ) . '</span>' . esc_html( $title ) . ( $locked ? ' <span class="abp-pro-badge">' . esc_html__( 'Pro', 'announcement-bar-pro' ) . '</span>' : '' ) . '</h2><div class="abp-card-body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.

		if ( $locked ) {
			echo '<fieldset class="abp-group abp-locked" disabled><legend class="screen-reader-text">' . esc_html( $title ) . '</legend>';
		}
	}

	/**
	 * Closes a card.
	 *
	 * @param bool $pro Pro card.
	 */
	private function end_card( $pro = false ) {
		if ( $pro && ! $this->pro ) {
			echo '</fieldset>';
		}

		echo '</div></section>';
	}

	/**
	 * The screen.
	 */
	public function render() {
		$pro = $this->pro;

		echo '<div class="wrap abp-admin">';
		echo '<div class="abp-head"><h1 class="wp-heading-inline">' . esc_html__( 'Settings', 'announcement-bar-pro' ) . '</h1></div>';
		echo '<nav class="nav-tab-wrapper abp-tabs" aria-label="' . esc_attr__( 'Announcement Bar Pro sections', 'announcement-bar-pro' ) . '">';
		foreach ( array(
			''            => __( 'Messages', 'announcement-bar-pro' ),
			'templates'   => __( 'Templates', 'announcement-bar-pro' ),
			'subscribers' => __( 'Subscribers', 'announcement-bar-pro' ),
			'settings'    => __( 'Settings', 'announcement-bar-pro' ),
		) as $page => $label ) {
			printf( '<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>', esc_url( Admin::url( $page ) ), 'settings' === $page ? ' nav-tab-active' : '', 'settings' === $page ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';

		$code  = isset( $_GET['abpro_notice'] ) ? sanitize_key( wp_unslash( $_GET['abpro_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$texts = array(
			'saved'      => array( 'success', __( 'Saved.', 'announcement-bar-pro' ) ),
			'invalid'    => array( 'error', __( 'Please correct the marked fields.', 'announcement-bar-pro' ) ),
			'licence_ok' => array( 'success', __( 'Pro features are unlocked. Thank you!', 'announcement-bar-pro' ) ),
			'licence_no' => array( 'error', __( 'This licence key is not valid. Please check it and try again.', 'announcement-bar-pro' ) ),
			'licence_rm' => array( 'success', __( 'The licence key was removed from this site.', 'announcement-bar-pro' ) ),
		);
		if ( isset( $texts[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"%3$s><p>%2$s</p></div>', esc_attr( $texts[ $code ][0] ), esc_html( $texts[ $code ][1] ), 'error' === $texts[ $code ][0] ? ' role="alert"' : '' );
		}

		$this->licence_card();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
		wp_nonce_field( 'abpro_save_settings' );
		echo '<input type="hidden" name="action" value="abpro_save_settings"><div class="abp-grid">';

		$this->card( __( 'Bar', 'announcement-bar-pro' ), 'megaphone' );
		$this->check( 'status', __( 'Show announcements on the site', 'announcement-bar-pro' ) );
		$this->select(
			'position',
			__( 'Position of the main bar', 'announcement-bar-pro' ),
			array(
				'top'    => __( 'Top of the page', 'announcement-bar-pro' ),
				'bottom' => __( 'Bottom of the screen', 'announcement-bar-pro' ) . ( $pro ? '' : ' (Pro)' ),
			)
		);
		$this->check( 'sticky', __( 'Keep the top bar visible while scrolling', 'announcement-bar-pro' ) );
		$this->select(
			'mode',
			__( 'Several messages', 'announcement-bar-pro' ),
			array(
				'rotate'  => __( 'Rotate one at a time', 'announcement-bar-pro' ),
				'stack'   => __( 'Show all, one under another', 'announcement-bar-pro' ),
				'marquee' => __( 'Scrolling ticker', 'announcement-bar-pro' ) . ( $pro ? '' : ' (Pro)' ),
			)
		);
		$this->select(
			'transition',
			__( 'Change effect', 'announcement-bar-pro' ),
			array(
				'fade'  => __( 'Fade', 'announcement-bar-pro' ),
				'slide' => __( 'Slide', 'announcement-bar-pro' ),
			)
		);
		echo '<div class="abp-row2">';
		$this->number( 'interval', __( 'Seconds per message', 'announcement-bar-pro' ), 2, 60 );
		$this->number( 'speed', __( 'Ticker speed (px/s)', 'announcement-bar-pro' ), 20, 300 );
		echo '</div>';
		$this->check( 'arrows', __( 'Arrows to switch messages', 'announcement-bar-pro' ) );
		$this->check( 'dismissible', __( 'Visitors may close the bar', 'announcement-bar-pro' ) );
		$this->number( 'dismiss_days', __( 'Closed messages stay hidden for (days)', 'announcement-bar-pro' ), 0, 365, __( '0 = until the browser tab is closed.', 'announcement-bar-pro' ) );
		$this->end_card();

		$this->card( __( 'Look', 'announcement-bar-pro' ), 'star' );
		$styles = array(
			'solid'   => __( 'Solid', 'announcement-bar-pro' ),
			'soft'    => __( 'Soft', 'announcement-bar-pro' ),
			'minimal' => __( 'Minimal', 'announcement-bar-pro' ),
		);
		if ( $pro ) {
			$styles['gradient'] = __( 'Gradient', 'announcement-bar-pro' );
			$styles['glass']    = __( 'Glass', 'announcement-bar-pro' );
		}
		$this->select( 'style', __( 'Style', 'announcement-bar-pro' ), $styles, $pro ? '' : __( 'Gradient and glass styles are part of Pro.', 'announcement-bar-pro' ) );
		$this->select(
			'color_mode',
			__( 'Colours', 'announcement-bar-pro' ),
			array(
				'custom' => __( 'Colours of each message', 'announcement-bar-pro' ),
				'auto'   => __( 'Match the theme automatically', 'announcement-bar-pro' ),
			)
		);
		echo '<div class="abp-row2">';
		$this->row( 'default_bg_color', __( 'Default background (new messages)', 'announcement-bar-pro' ), '<input type="color" id="abp-s-default_bg_color" name="default_bg_color" value="' . esc_attr( $this->v['default_bg_color'] ) . '">' );
		$this->row( 'default_text_color', __( 'Default text colour', 'announcement-bar-pro' ), '<input type="color" id="abp-s-default_text_color" name="default_text_color" value="' . esc_attr( $this->v['default_text_color'] ) . '">' );
		echo '</div><div class="abp-row2">';
		$this->number( 'font_size', __( 'Font size (px)', 'announcement-bar-pro' ), 10, 30 );
		$this->number( 'padding', __( 'Height padding (px)', 'announcement-bar-pro' ), 0, 30 );
		echo '</div>';
		$this->check( 'allow_html', __( 'Allow simple HTML in texts (bold, italic, links)', 'announcement-bar-pro' ), __( 'Everything else is removed when saving.', 'announcement-bar-pro' ) );
		$this->row( 'languages', __( 'More languages for message texts', 'announcement-bar-pro' ), '<input type="text" class="regular-text" id="abp-s-languages" name="languages" value="' . esc_attr( is_array( $this->v['languages'] ) ? implode( ', ', $this->v['languages'] ) : (string) $this->v['languages'] ) . '" spellcheck="false"' . $this->aria( 'languages', true ) . '>', __( 'WordPress language codes such as lv, ru_RU, de_DE. Visitors see the text of their language (with a multilingual plugin), else the site language.', 'announcement-bar-pro' ) );
		$this->end_card();

		$this->card( __( 'Behaviour', 'announcement-bar-pro' ), 'bolt', true );
		echo '<div class="abp-row2">';
		$this->number( 'max_messages', __( 'Most messages at once', 'announcement-bar-pro' ), 0, 20, __( '0 = no limit; higher priority wins.', 'announcement-bar-pro' ) );
		$this->number( 'autohide', __( 'Hide the bar after (seconds)', 'announcement-bar-pro' ), 0, 600, __( '0 = never; a small tab brings it back.', 'announcement-bar-pro' ) );
		echo '</div>';
		$this->check( 'pause_button', __( 'Pause button for moving messages', 'announcement-bar-pro' ) );
		$this->select( 'font', __( 'Font of all bars', 'announcement-bar-pro' ), Admin_Form::fonts() );
		$this->row( 'custom_css', __( 'Custom CSS', 'announcement-bar-pro' ), '<textarea id="abp-s-custom_css" name="custom_css" rows="5" class="large-text code" spellcheck="false"' . $this->aria( 'custom_css', true ) . '>' . esc_textarea( (string) $this->v['custom_css'] ) . '</textarea>', __( 'Cleaned on save: no @import, scripts or outside code.', 'announcement-bar-pro' ) );
		$this->end_card( true );

		$this->card( __( 'Privacy and analytics', 'announcement-bar-pro' ), 'shield', true );
		$this->check( 'consent_wait', __( 'Wait for cookie consent', 'announcement-bar-pro' ), __( 'A/B choice, visitor memory, statistics and GA4 events start only after the consent tool allows it (WP Consent API, or window.abpConsent = true).', 'announcement-bar-pro' ) );
		$this->check( 'ga4', __( 'Send GA4 events (view, click, copy, sign-up)', 'announcement-bar-pro' ), __( 'Only if the site already loads Google Analytics (gtag or Tag Manager). The plugin itself loads nothing.', 'announcement-bar-pro' ) );
		$this->check( 'geo_cf', __( 'Use CloudFlare\'s country header', 'announcement-bar-pro' ), __( 'Only for requests that really come from CloudFlare\'s addresses.', 'announcement-bar-pro' ) );
		$this->select(
			'geo_fallback',
			__( 'Unknown country', 'announcement-bar-pro' ),
			array(
				'store' => __( 'Treat as the shop\'s country', 'announcement-bar-pro' ),
				'none'  => __( 'Treat as unknown', 'announcement-bar-pro' ),
			)
		);
		$this->end_card( true );

		$this->card( __( 'E-mail sign-up', 'announcement-bar-pro' ), 'mail', true );
		$this->check( 'double_optin', __( 'Confirm sign-ups by e-mail (double opt-in, recommended)', 'announcement-bar-pro' ) );
		$pages = wp_dropdown_pages(
			array(
				'name'              => 'privacy_id',
				'id'                => 'abp-s-privacy_id',
				'selected'          => (int) $this->v['privacy_id'],
				'show_option_none'  => esc_html__( '— WordPress privacy page —', 'announcement-bar-pro' ),
				'option_none_value' => '0',
				'echo'              => 0,
			)
		);
		$this->row( 'privacy_id', __( 'Privacy page linked in the form', 'announcement-bar-pro' ), (string) $pages );
		echo '<div class="abp-row2">';
		$this->number( 'signup_limit_ip', __( 'Sign-ups per visitor per hour', 'announcement-bar-pro' ), 1, 1000 );
		$this->number( 'signup_limit_total', __( 'Sign-ups per hour in total', 'announcement-bar-pro' ), 1, 100000 );
		echo '</div>';
		$this->end_card( true );

		$this->card( __( 'Data', 'announcement-bar-pro' ), 'box' );
		$this->check( 'delete_data', __( 'Delete all messages, statistics, subscribers and settings when the plugin is deleted', 'announcement-bar-pro' ), __( 'Off: everything stays in the database, so a reinstall keeps your work.', 'announcement-bar-pro' ) );
		$this->end_card();

		echo '</div><p class="submit"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Save settings', 'announcement-bar-pro' ) . '</button></p></form></div>';
	}

	/**
	 * Licence card (own form).
	 */
	private function licence_card() {
		$data = Licence::get();

		echo '<section class="abp-card abp-licence"><h2 class="abp-card-title"><span class="abp-ico-badge" aria-hidden="true">' . Icons::svg( 'tag', 'abp-card-ico' ) . '</span>' . esc_html__( 'Licence', 'announcement-bar-pro' ) . '</h2><div class="abp-card-body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'abpro_licence' );
		echo '<input type="hidden" name="action" value="abpro_licence">';

		if ( $this->pro ) {
			/* translators: %s: masked licence key */
			echo '<p><span class="abp-state abp-state-on">' . esc_html__( 'Pro active', 'announcement-bar-pro' ) . '</span> ' . esc_html( sprintf( __( 'Key %s', 'announcement-bar-pro' ), Licence::masked() ) ) . '</p>';
			echo '<button type="submit" name="remove" value="1" class="button">' . esc_html__( 'Remove key from this site', 'announcement-bar-pro' ) . '</button>';
		} else {
			echo '<p>' . esc_html__( 'The free edition has the top bar, rotating messages, schedules, countdowns, the free-shipping progress bar, buttons, device targeting and three styles. A Pro key unlocks everything else: more bars and boxes, targeting, coupons, A/B tests with statistics, the e-mail sign-up and templates.', 'announcement-bar-pro' ) . '</p>';
			echo '<div class="abp-field"><label for="abp-licence-key">' . esc_html__( 'Licence key', 'announcement-bar-pro' ) . '</label><input type="text" id="abp-licence-key" name="licence_key" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="ABP1-XXXX-XXXX-XXXX-XXXX" aria-describedby="abp-licence-help"></div>';
			echo '<p class="abp-help" id="abp-licence-help">' . esc_html__( 'The key is checked on this site only; no data is sent anywhere.', 'announcement-bar-pro' ) . '</p>';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Activate', 'announcement-bar-pro' ) . '</button>';
		}

		echo '</form>';

		if ( '' !== $data['key'] && ! $this->pro ) {
			echo '<p class="abp-help">' . esc_html__( 'A stored key is not valid any more.', 'announcement-bar-pro' ) . '</p>';
		}

		echo '</div></section>';
	}
}
