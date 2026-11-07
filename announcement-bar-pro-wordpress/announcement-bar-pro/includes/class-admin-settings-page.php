<?php
/**
 * Settings screen: bar, look and data. Add-ons add their cards through the actions "abpro_settings_top" (before the
 * form) and "abpro_settings_cards" (inside it); the field helpers below are public for them.
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
	 * Constructor.
	 */
	public function __construct() {
		$this->v = Settings::get();
		$kept    = get_transient( 'abpro_settings_' . get_current_user_id() );

		if ( is_array( $kept ) ) {
			$this->v   = array_merge( $this->v, (array) $kept['values'] );
			$this->err = (array) $kept['errors'];
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
	public function row( $key, $label, $control, $help = '' ) {
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
	public function aria( $key, $help ) {
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
	public function number( $key, $label, $min, $max, $help = '' ) {
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
	public function select( $key, $label, array $choices, $help = '' ) {
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
	public function check( $key, $label, $help = '' ) {
		echo '<div class="abp-field abp-check"><input type="checkbox" id="abp-s-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1"' . checked( ! empty( $this->v[ $key ] ), true, false ) . $this->aria( $key, '' !== $help ) . '> <label for="abp-s-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aria() escapes.

		if ( '' !== $help ) {
			echo '<p class="abp-help" id="abp-s-' . esc_attr( $key ) . '-help">' . esc_html( $help ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Value shown for a setting (stored, or as typed before a failed save).
	 *
	 * @param string $key Setting.
	 * @return mixed
	 */
	public function value( $key ) {
		return isset( $this->v[ $key ] ) ? $this->v[ $key ] : '';
	}

	/**
	 * Error of a setting ('' = none).
	 *
	 * @param string $key Setting.
	 * @return string
	 */
	public function error( $key ) {
		return isset( $this->err[ $key ] ) ? (string) $this->err[ $key ] : '';
	}

	/**
	 * Opens a card.
	 *
	 * @param string $title Title.
	 * @param string $icon  Icon.
	 */
	public function card( $title, $icon ) {
		echo '<section class="abp-card"><h2 class="abp-card-title"><span class="abp-ico-badge" aria-hidden="true">' . Icons::svg( $icon, 'abp-card-ico' ) . '</span>' . esc_html( $title ) . '</h2><div class="abp-card-body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
	}

	/**
	 * Closes a card.
	 */
	public function end_card() {
		echo '</div></section>';
	}

	/**
	 * The screen.
	 */
	public function render() {
		echo '<div class="wrap abp-admin">';
		Admin::header( __( 'Settings', 'announcement-bar-pro' ), 'settings' );

		do_action( 'abpro_settings_top', $this );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
		wp_nonce_field( 'abpro_save_settings' );
		echo '<input type="hidden" name="action" value="abpro_save_settings"><div class="abp-grid">';

		$this->card( __( 'Bar', 'announcement-bar-pro' ), 'megaphone' );
		$this->check( 'status', __( 'Show announcements on the site', 'announcement-bar-pro' ) );
		if ( count( Settings::choices( 'position' ) ) > 1 ) {
			$this->select( 'position', __( 'Position of the main bar', 'announcement-bar-pro' ), Settings::choices( 'position' ) );
		}
		$this->check( 'sticky', __( 'Keep the top bar visible while scrolling', 'announcement-bar-pro' ) );
		$this->select( 'mode', __( 'Several messages', 'announcement-bar-pro' ), Settings::choices( 'mode' ) );
		$this->select(
			'transition',
			__( 'Change effect', 'announcement-bar-pro' ),
			array(
				'fade'  => __( 'Fade', 'announcement-bar-pro' ),
				'slide' => __( 'Slide', 'announcement-bar-pro' ),
			)
		);
		$this->number( 'interval', __( 'Seconds per message', 'announcement-bar-pro' ), 2, 60 );
		do_action( 'abpro_settings_bar', $this );
		$this->check( 'arrows', __( 'Arrows to switch messages', 'announcement-bar-pro' ) );
		$this->check( 'dismissible', __( 'Visitors may close the bar', 'announcement-bar-pro' ) );
		$this->number( 'dismiss_days', __( 'Closed messages stay hidden for (days)', 'announcement-bar-pro' ), 0, 365, __( '0 = until the browser tab is closed.', 'announcement-bar-pro' ) );
		$this->end_card();

		$this->card( __( 'Look', 'announcement-bar-pro' ), 'star' );
		$this->select( 'style', __( 'Style', 'announcement-bar-pro' ), Settings::choices( 'style' ) );
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

		do_action( 'abpro_settings_cards', $this );

		$this->card( __( 'Data', 'announcement-bar-pro' ), 'box' );
		$this->check( 'delete_data', __( 'Delete all messages and settings when the plugin is deleted', 'announcement-bar-pro' ), __( 'Off: everything stays in the database, so a reinstall keeps your work. Add-ons delete their own data when they are deleted.', 'announcement-bar-pro' ) );
		$this->end_card();

		echo '</div><p class="submit"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Save settings', 'announcement-bar-pro' ) . '</button></p></form></div>';
	}
}
