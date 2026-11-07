<?php
/**
 * Message form (cards + sticky live preview in a sandboxed frame). Add-ons add their fields through the actions
 * "abpro_form_texts", "abpro_form_look", "abpro_form_goal", "abpro_form_schedule", "abpro_form_audience",
 * "abpro_form_cards" and "abpro_form_preview_tools"; the field helpers below are public for them.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the message form. Every value is escaped on output.
 */
class Admin_Form {
	/**
	 * Message (stored or posted values).
	 *
	 * @var array
	 */
	private $m;

	/**
	 * Options (decoded, as stored).
	 *
	 * @var array
	 */
	private $o;

	/**
	 * Errors by field.
	 *
	 * @var array
	 */
	private $err;

	/**
	 * WooCommerce active and the user may manage it.
	 *
	 * @var bool
	 */
	private $wc;

	/**
	 * Constructor.
	 *
	 * @param array $message Message.
	 * @param array $errors  Errors.
	 */
	public function __construct( array $message, array $errors ) {
		$this->m   = $message;
		$this->o   = Options::decode( isset( $message['options'] ) ? $message['options'] : '' );
		$this->err = $errors;
		$this->wc  = Commerce::active() && current_user_can( 'manage_woocommerce' );
	}

	/**
	 * The message (stored or posted values).
	 *
	 * @return array
	 */
	public function message() {
		return $this->m;
	}

	/**
	 * Options of the message (decoded, as stored).
	 *
	 * @return array
	 */
	public function options() {
		return $this->o;
	}

	/**
	 * Errors by field.
	 *
	 * @return array
	 */
	public function errors() {
		return $this->err;
	}

	/**
	 * True when WooCommerce is active and the user may manage it.
	 *
	 * @return bool
	 */
	public function commerce() {
		return $this->wc;
	}

	/**
	 * Name of a per-language text field: texts[<locale>][<field>].
	 *
	 * @param string $locale Locale.
	 * @param string $field  Field.
	 * @return string
	 */
	public static function text_name( $locale, $field ) {
		return 'texts[' . $locale . '][' . $field . ']';
	}

	/**
	 * Error key of a per-language text field: texts.<locale>.<field>.
	 *
	 * @param string $locale Locale.
	 * @param string $field  Field.
	 * @return string
	 */
	public static function text_key( $locale, $field ) {
		return 'texts.' . $locale . '.' . $field;
	}

	/*
	 * ------------------------------------------------------------------
	 * Field helpers
	 * ------------------------------------------------------------------
	 */

	/**
	 * Error text under a field.
	 *
	 * @param string $key Field key.
	 * @param string $id  Element ID of the message.
	 */
	public function error( $key, $id ) {
		if ( isset( $this->err[ $key ] ) ) {
			echo '<p class="abp-error" id="' . esc_attr( $id ) . '-err">' . esc_html( $this->err[ $key ] ) . '</p>';
		}
	}

	/**
	 * Attributes that link a field to its help and error texts.
	 *
	 * @param string $key  Field key.
	 * @param string $id   Element ID.
	 * @param bool   $help Has a help text.
	 * @return string
	 */
	public function describe( $key, $id, $help ) {
		$ids = array();

		if ( $help ) {
			$ids[] = $id . '-help';
		}

		if ( isset( $this->err[ $key ] ) ) {
			$ids[] = $id . '-err';
		}

		return ( $ids ? ' aria-describedby="' . esc_attr( implode( ' ', $ids ) ) . '"' : '' ) . ( isset( $this->err[ $key ] ) ? ' aria-invalid="true"' : '' );
	}

	/**
	 * Help text.
	 *
	 * @param string $id   Element ID.
	 * @param string $text Text.
	 */
	public function help( $id, $text ) {
		if ( '' !== $text ) {
			echo '<p class="abp-help" id="' . esc_attr( $id ) . '-help">' . esc_html( $text ) . '</p>';
		}
	}

	/**
	 * Text / number / date input.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @param array  $args  type, help, key, class, attrs.
	 */
	public function input( $name, $label, $value, array $args = array() ) {
		$id   = 'abp-' . trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );
		$key  = isset( $args['key'] ) ? $args['key'] : $name;
		$help = isset( $args['help'] ) ? $args['help'] : '';

		echo '<div class="abp-field' . ( isset( $args['wrap'] ) ? ' ' . esc_attr( $args['wrap'] ) : '' ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="' . esc_attr( isset( $args['type'] ) ? $args['type'] : 'text' ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="' . esc_attr( isset( $args['class'] ) ? $args['class'] : 'regular-text' ) . '"' . ( isset( $args['attrs'] ) ? $args['attrs'] : '' ) . $this->describe( $key, $id, '' !== $help ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attrs are fixed strings from this class.
		$this->help( $id, $help );
		$this->error( $key, $id );
		echo '</div>';
	}

	/**
	 * Select.
	 *
	 * @param string $name    Field name.
	 * @param string $label   Label.
	 * @param string $value   Value.
	 * @param array  $choices value => label.
	 * @param array  $args    help, key, attrs.
	 */
	public function select( $name, $label, $value, array $choices, array $args = array() ) {
		$id   = 'abp-' . trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );
		$key  = isset( $args['key'] ) ? $args['key'] : $name;
		$help = isset( $args['help'] ) ? $args['help'] : '';

		echo '<div class="abp-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( isset( $args['attrs'] ) ? $args['attrs'] : '' ) . $this->describe( $key, $id, '' !== $help ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed strings.

		foreach ( $choices as $k => $l ) {
			echo '<option value="' . esc_attr( (string) $k ) . '"' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}

		echo '</select>';
		$this->help( $id, $help );
		$this->error( $key, $id );
		echo '</div>';
	}

	/**
	 * Checkbox.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param bool   $on    Checked.
	 * @param string $help  Help text.
	 */
	public function checkbox( $name, $label, $on, $help = '' ) {
		$id = 'abp-' . trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );

		echo '<div class="abp-field abp-check"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $on, true, false ) . $this->describe( $name, $id, '' !== $help ) . '> <label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- describe() escapes.
		$this->help( $id, $help );
		$this->error( $name, $id );
		echo '</div>';
	}

	/**
	 * Textarea.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param array  $args  help, key, rows.
	 */
	public function textarea( $name, $label, $value, array $args = array() ) {
		$id   = 'abp-' . trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );
		$key  = isset( $args['key'] ) ? $args['key'] : $name;
		$help = isset( $args['help'] ) ? $args['help'] : '';

		echo '<div class="abp-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . (int) ( isset( $args['rows'] ) ? $args['rows'] : 2 ) . '" class="large-text"' . $this->describe( $key, $id, '' !== $help ) . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- describe() escapes.
		$this->help( $id, $help );
		$this->error( $key, $id );
		echo '</div>';
	}

	/**
	 * Opens a card.
	 *
	 * @param string $title Title.
	 * @param string $icon  Icon name.
	 * @param string $note  Small note on the right.
	 */
	public function card( $title, $icon, $note = '' ) {
		echo '<section class="abp-card"><h2 class="abp-card-title"><span class="abp-ico-badge" aria-hidden="true">' . Icons::svg( $icon, 'abp-card-ico' ) . '</span>' . esc_html( $title ) . ( '' !== $note ? '<small>' . esc_html( $note ) . '</small>' : '' ) . '</h2><div class="abp-card-body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
	}

	/**
	 * Closes a card.
	 */
	public function end_card() {
		echo '</div></section>';
	}

	/**
	 * Opens a fieldset (close it with </fieldset>); a commerce fieldset is disabled without WooCommerce rights.
	 *
	 * @param string $legend   Legend.
	 * @param bool   $commerce Needs WooCommerce.
	 * @param string $attrs    Extra attributes (fixed strings).
	 */
	public function group( $legend, $commerce = false, $attrs = '' ) {
		$locked = $commerce && ! $this->wc; // Commerce fields need the manage_woocommerce capability.

		echo '<fieldset class="abp-group' . ( $locked ? ' abp-no-rights' : '' ) . '"' . ( $locked ? ' disabled' : '' ) . $attrs . '><legend>' . esc_html( $legend ) . '</legend>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed strings.

		if ( $commerce && ! Commerce::active() ) {
			echo '<p class="abp-help">' . esc_html__( 'Needs WooCommerce.', 'announcement-bar-pro' ) . '</p>';
		} elseif ( $commerce && ! $this->wc ) {
			echo '<p class="abp-help">' . esc_html__( 'Only users who may manage WooCommerce can change these settings.', 'announcement-bar-pro' ) . '</p>';
		}
	}

	/*
	 * ------------------------------------------------------------------
	 * Form
	 * ------------------------------------------------------------------
	 */

	/**
	 * The whole screen.
	 */
	public function render() {
		$m     = $this->m;
		$id    = (int) $m['message_id'];
		$title = $id ? __( 'Edit message', 'announcement-bar-pro' ) : __( 'Add message', 'announcement-bar-pro' );

		echo '<div class="wrap abp-admin abp-edit">';
		echo '<div class="abp-head"><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1> <a class="page-title-action" href="' . esc_url( Admin::url() ) . '">' . esc_html__( 'Back to messages', 'announcement-bar-pro' ) . '</a></div>';

		$code = isset( $_GET['abpro_notice'] ) ? sanitize_key( wp_unslash( $_GET['abpro_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( 'saved' === $code ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'announcement-bar-pro' ) . '</p></div>';
		} elseif ( $this->err ) {
			echo '<div class="notice notice-error" role="alert"><p>' . esc_html__( 'Please correct the marked fields.', 'announcement-bar-pro' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="abp-form" novalidate>';
		wp_nonce_field( 'abpro_save_message' );
		echo '<input type="hidden" name="action" value="abpro_save_message"><input type="hidden" name="message_id" value="' . (int) $id . '">';
		echo '<div class="abp-layout"><div class="abp-main">';

		$this->content_card();
		$this->look_card();
		$this->goal_card();
		$this->schedule_card();
		$this->audience_card();
		do_action( 'abpro_form_cards', $this );

		echo '<p class="submit abp-submit-row"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Save message', 'announcement-bar-pro' ) . '</button></p>';
		echo '</div><aside class="abp-side">';
		$this->preview_card();
		echo '</aside></div></form></div>';
	}

	/**
	 * Texts per language.
	 */
	private function content_card() {
		$locales = Settings::locales();
		$allow   = (bool) Settings::get()['allow_html'];

		$this->card( __( 'Message', 'announcement-bar-pro' ), 'megaphone', $allow ? __( 'Simple HTML allowed: b, i, u, s, br, a', 'announcement-bar-pro' ) : __( 'Plain text', 'announcement-bar-pro' ) );

		if ( count( $locales ) > 1 ) {
			echo '<div class="abp-langs" role="tablist" aria-label="' . esc_attr__( 'Language', 'announcement-bar-pro' ) . '">';
			foreach ( $locales as $i => $locale ) {
				$err = false;
				foreach ( array_keys( $this->err ) as $k ) {
					$err = $err || 0 === strpos( $k, 'texts.' . $locale . '.' );
				}
				echo '<button type="button" role="tab" id="abp-tab-' . esc_attr( $locale ) . '" aria-controls="abp-pane-' . esc_attr( $locale ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '" tabindex="' . ( 0 === $i ? '0' : '-1' ) . '" data-locale="' . esc_attr( $locale ) . '" class="' . ( 0 === $i ? 'active' : '' ) . ( $err ? ' abp-has-error' : '' ) . '">' . esc_html( $locale ) . '</button>';
			}
			echo '</div>';
		}

		foreach ( $locales as $i => $locale ) {
			$t = isset( $this->m['texts'][ $locale ] ) ? $this->m['texts'][ $locale ] : array();
			$v = static function ( $field ) use ( $t ) {
				return isset( $t[ $field ] ) ? (string) $t[ $field ] : '';
			};
			$n = static function ( $field ) use ( $locale ) {
				return 'texts[' . $locale . '][' . $field . ']';
			};
			$k = static function ( $field ) use ( $locale ) {
				return 'texts.' . $locale . '.' . $field;
			};

			echo '<div class="abp-lang-pane" id="abp-pane-' . esc_attr( $locale ) . '"' . ( count( $locales ) > 1 ? ' role="tabpanel" aria-labelledby="abp-tab-' . esc_attr( $locale ) . '"' : '' ) . ( 0 === $i ? '' : ' hidden' ) . ' data-locale="' . esc_attr( $locale ) . '">';

			$this->textarea(
				$n( 'text' ),
				__( 'Text', 'announcement-bar-pro' ),
				$v( 'text' ),
				array(
					'key'  => $k( 'text' ),
					'help' => __( 'Placeholders: {countdown} (timer), {remaining} (amount still missing for free shipping).', 'announcement-bar-pro' ),
				)
			);
			$this->textarea(
				$n( 'mobile_text' ),
				__( 'Shorter text for phones (optional)', 'announcement-bar-pro' ),
				$v( 'mobile_text' ),
				array(
					'key'  => $k( 'mobile_text' ),
					'rows' => 1,
				)
			);
			$this->input( $n( 'link_text' ), __( 'Button text (optional)', 'announcement-bar-pro' ), $v( 'link_text' ), array( 'key' => $k( 'link_text' ) ) );

			echo '<div data-show="goal">';
			$this->textarea(
				$n( 'success_text' ),
				__( 'Text once the goal is reached', 'announcement-bar-pro' ),
				$v( 'success_text' ),
				array(
					'key'  => $k( 'success_text' ),
					'rows' => 1,
				)
			);
			echo '</div>';

			do_action( 'abpro_form_texts', $this, $locale, $t );

			echo '</div>';
		}

		$this->end_card();
	}

	/**
	 * Icon, colours, style, button, link, image, font.
	 */
	private function look_card() {
		$m = $this->m;

		$this->card( __( 'Look and link', 'announcement-bar-pro' ), 'star' );

		echo '<fieldset class="abp-field"><legend>' . esc_html__( 'Icon', 'announcement-bar-pro' ) . '</legend><div class="abp-icons">';
		echo '<span class="abp-icon-opt"><input type="radio" name="icon" id="abp-icon-none" value=""' . checked( '', (string) $m['icon'], false ) . '><label for="abp-icon-none">' . esc_html__( 'None', 'announcement-bar-pro' ) . '</label></span>';
		foreach ( Icons::NAMES as $name ) {
			echo '<span class="abp-icon-opt"><input type="radio" name="icon" id="abp-icon-' . esc_attr( $name ) . '" value="' . esc_attr( $name ) . '"' . checked( $name, (string) $m['icon'], false ) . '><label for="abp-icon-' . esc_attr( $name ) . '" title="' . esc_attr( $name ) . '">' . Icons::svg( $name ) . '<span class="screen-reader-text">' . esc_html( $name ) . '</span></label></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
		}
		echo '</div>';
		$this->error( 'icon', 'abp-icon' );
		echo '</fieldset>';

		echo '<div class="abp-row2">';
		$this->input(
			'bg_color',
			__( 'Background colour', 'announcement-bar-pro' ),
			$m['bg_color'],
			array(
				'type'  => 'color',
				'class' => 'abp-color',
			)
		);
		$this->input(
			'text_color',
			__( 'Text colour', 'announcement-bar-pro' ),
			$m['text_color'],
			array(
				'type'  => 'color',
				'class' => 'abp-color',
			)
		);
		echo '</div><p class="abp-contrast" id="abp-contrast" aria-live="polite"></p>';

		$styles = array(
			''        => array( __( 'As in the settings', 'announcement-bar-pro' ), __( 'Use the main style', 'announcement-bar-pro' ) ),
			'solid'   => array( __( 'Solid', 'announcement-bar-pro' ), __( 'One clear colour', 'announcement-bar-pro' ) ),
			'soft'    => array( __( 'Soft', 'announcement-bar-pro' ), __( 'Light tint, dark text', 'announcement-bar-pro' ) ),
			'minimal' => array( __( 'Minimal', 'announcement-bar-pro' ), __( 'Light with an accent line', 'announcement-bar-pro' ) ),
		);
		$styles = apply_filters( 'abpro_style_choices', $styles );
		echo '<fieldset class="abp-field"><legend>' . esc_html__( 'Style', 'announcement-bar-pro' ) . '</legend><div class="abp-styles">';
		foreach ( (array) $styles as $key => $s ) {
			echo '<span class="abp-style-opt"><input type="radio" name="style" id="abp-style-' . esc_attr( '' === $key ? 'default' : $key ) . '" value="' . esc_attr( $key ) . '"' . checked( $key, (string) $m['style'], false ) . '><label for="abp-style-' . esc_attr( '' === $key ? 'default' : $key ) . '"><span class="abp-sw abp-sw-' . esc_attr( '' === $key ? 'default' : $key ) . '" aria-hidden="true"><i></i><b></b></span><span class="abp-name">' . esc_html( $s[0] ) . '</span><span class="abp-desc">' . esc_html( $s[1] ) . '</span></label></span>';
		}
		echo '</div></fieldset>';

		echo '<div class="abp-row2">';
		$this->input(
			'link',
			__( 'Link (optional)', 'announcement-bar-pro' ),
			$m['link'],
			array(
				'help'  => __( 'https://… or a path on this site such as /shop/', 'announcement-bar-pro' ),
				'attrs' => ' inputmode="url" spellcheck="false"',
			)
		);
		$this->select(
			'button_style',
			__( 'Button style', 'announcement-bar-pro' ),
			$m['button_style'],
			array(
				'filled'  => __( 'Filled', 'announcement-bar-pro' ),
				'outline' => __( 'Outline', 'announcement-bar-pro' ),
				'link'    => __( 'Text link', 'announcement-bar-pro' ),
			),
			array( 'help' => __( 'Shown when the message has a button text.', 'announcement-bar-pro' ) )
		);
		echo '</div>';
		$this->checkbox( 'link_new_tab', __( 'Open the link in a new tab', 'announcement-bar-pro' ), (int) $m['link_new_tab'] );

		do_action( 'abpro_form_look', $this );

		$this->end_card();
	}

	/**
	 * Message type, free-shipping amount, tiers, country / currency amounts.
	 */
	private function goal_card() {
		$m  = $this->m;
		$o  = $this->o;
		$wc = Commerce::active();

		$this->card( __( 'Type and goal', 'announcement-bar-pro' ), 'truck', $wc ? '' : __( 'Free shipping and goals need WooCommerce', 'announcement-bar-pro' ) );

		$types = Admin::types();

		$this->select(
			'type',
			__( 'Type', 'announcement-bar-pro' ),
			$m['type'],
			$types,
			array(
				'attrs' => $this->wc ? '' : ' disabled',
				'help'  => __( 'Free-shipping progress shows how much is still missing in the cart and updates when the cart changes.', 'announcement-bar-pro' ),
			)
		);

		if ( ! $wc ) {
			$this->end_card();
			return;
		}

		echo '<div data-show="fs">';
		$this->checkbox( 'opt[fs_wc]', __( 'Use the minimum amount of WooCommerce\'s "Free shipping" method', 'announcement-bar-pro' ), Options::fs_wc( $o ), __( 'Taken from the shipping zone of the visitor. The message hides itself when that zone has no free shipping with a minimum amount.', 'announcement-bar-pro' ) );
		echo '<div data-show="fs-own">';
		$this->input(
			'threshold',
			__( 'Free shipping from (order amount)', 'announcement-bar-pro' ),
			(float) $m['threshold'] > 0 ? rtrim( rtrim( number_format( (float) $m['threshold'], 4, '.', '' ), '0' ), '.' ) : '',
			array(
				'class' => 'small-text',
				'attrs' => ' inputmode="decimal"',
			)
		);
		echo '</div></div>';

		echo '<div data-show="goal">';
		$this->checkbox( 'progress_bar', __( 'Show a progress bar', 'announcement-bar-pro' ), (int) $m['progress_bar'] );
		echo '</div>';

		do_action( 'abpro_form_goal', $this );

		$this->end_card();
	}

	/**
	 * Status, dates, countdown, weekly schedule.
	 */
	private function schedule_card() {
		$m = $this->m;

		$this->card( __( 'Schedule', 'announcement-bar-pro' ), 'clock' );

		$this->checkbox( 'status', __( 'Message is on', 'announcement-bar-pro' ), (int) $m['status'] );

		$local = static function ( $value ) {
			return is_string( $value ) && preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})/', $value, $x ) ? $x[1] . 'T' . $x[2] : '';
		};

		/* translators: %s: time zone name such as Europe/Riga */
		$tz = sprintf( __( 'Site time (%s).', 'announcement-bar-pro' ), wp_timezone_string() );
		echo '<div class="abp-row2">';
		$this->input(
			'date_start',
			__( 'Start (optional)', 'announcement-bar-pro' ),
			$local( $m['date_start'] ),
			array(
				'type'  => 'datetime-local',
				'class' => '',
				'help'  => $tz,
			)
		);
		$this->input(
			'date_end',
			__( 'End (optional)', 'announcement-bar-pro' ),
			$local( $m['date_end'] ),
			array(
				'type'  => 'datetime-local',
				'class' => '',
			)
		);
		echo '</div>';
		$this->checkbox( 'countdown', __( 'Show a countdown to the end', 'announcement-bar-pro' ), (int) $m['countdown'], __( 'Put {countdown} in the text where the timer should be; without it the timer follows the text.', 'announcement-bar-pro' ) );

		$days = array(
			1 => __( 'Mon', 'announcement-bar-pro' ),
			2 => __( 'Tue', 'announcement-bar-pro' ),
			3 => __( 'Wed', 'announcement-bar-pro' ),
			4 => __( 'Thu', 'announcement-bar-pro' ),
			5 => __( 'Fri', 'announcement-bar-pro' ),
			6 => __( 'Sat', 'announcement-bar-pro' ),
			7 => __( 'Sun', 'announcement-bar-pro' ),
		);
		echo '<fieldset class="abp-field"><legend>' . esc_html__( 'Days of the week (none = every day)', 'announcement-bar-pro' ) . '</legend><div class="abp-days">';
		foreach ( $days as $d => $label ) {
			echo '<label class="abp-day"><input type="checkbox" name="weekdays[]" value="' . (int) $d . '"' . checked( false !== strpos( (string) $m['weekdays'], (string) $d ), true, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</div></fieldset><div class="abp-row2">';
		$this->input(
			'time_start',
			__( 'Daily from (optional)', 'announcement-bar-pro' ),
			$m['time_start'],
			array(
				'type'  => 'time',
				'class' => '',
				'help'  => __( 'A window such as 22:00–02:00 belongs to the day it starts on.', 'announcement-bar-pro' ),
			)
		);
		$this->input(
			'time_end',
			__( 'Daily until', 'announcement-bar-pro' ),
			$m['time_end'],
			array(
				'type'  => 'time',
				'class' => '',
				'key'   => 'time_start_end',
			)
		);
		echo '</div>';

		do_action( 'abpro_form_schedule', $this, $days );

		$this->end_card();
	}

	/**
	 * Device and sort order.
	 */
	private function audience_card() {
		$m = $this->m;

		$this->card( __( 'Where and for whom', 'announcement-bar-pro' ), 'globe' );

		echo '<div class="abp-row2">';
		$this->select(
			'device',
			__( 'Devices', 'announcement-bar-pro' ),
			$m['device'],
			array(
				'all'     => __( 'All devices', 'announcement-bar-pro' ),
				'desktop' => __( 'Computers and tablets only', 'announcement-bar-pro' ),
				'mobile'  => __( 'Phones only (below 768 px)', 'announcement-bar-pro' ),
			)
		);
		$this->input(
			'sort_order',
			__( 'Sort order', 'announcement-bar-pro' ),
			$m['sort_order'],
			array(
				'type'  => 'number',
				'class' => 'small-text',
				'help'  => __( 'Smaller numbers come first.', 'announcement-bar-pro' ),
			)
		);
		echo '</div>';

		do_action( 'abpro_form_audience', $this );

		$this->end_card();
	}

	/**
	 * Sticky live preview.
	 */
	private function preview_card() {
		$locales = Settings::locales();

		echo '<section class="abp-card abp-preview-card" aria-labelledby="abp-pv-title"><h2 class="abp-card-title" id="abp-pv-title"><span class="abp-ico-badge" aria-hidden="true">' . Icons::svg( 'info', 'abp-card-ico' ) . '</span>' . esc_html__( 'Live preview', 'announcement-bar-pro' ) . '</h2><div class="abp-card-body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
		echo '<div class="abp-pv-tools"><div class="abp-seg" role="group" aria-label="' . esc_attr__( 'Screen', 'announcement-bar-pro' ) . '"><button type="button" class="active" data-pv-device="desktop" aria-pressed="true">' . esc_html__( 'Computer', 'announcement-bar-pro' ) . '</button><button type="button" data-pv-device="mobile" aria-pressed="false">' . esc_html__( 'Phone', 'announcement-bar-pro' ) . '</button></div>';
		do_action( 'abpro_form_preview_tools', $this );

		if ( count( $locales ) > 1 ) {
			echo '<label class="abp-pv-field">' . esc_html__( 'Language', 'announcement-bar-pro' ) . ' <select id="abp-pv-locale">';
			foreach ( $locales as $locale ) {
				echo '<option value="' . esc_attr( $locale ) . '">' . esc_html( $locale ) . '</option>';
			}
			echo '</select></label>';
		}

		echo '<label class="abp-pv-field" data-show="goal">' . esc_html__( 'Cart in the preview', 'announcement-bar-pro' ) . ' <input type="number" id="abp-pv-cart" value="20" min="0" step="1" class="small-text"></label></div>';
		echo '<div id="abp-preview-wrap"><iframe id="abp-preview-frame" title="' . esc_attr__( 'Preview of the message', 'announcement-bar-pro' ) . '" sandbox="allow-scripts" referrerpolicy="no-referrer"></iframe></div>';
		echo '<p class="abp-pv-note" id="abp-pv-note" aria-live="polite"></p>';
		echo '<p class="abp-help">' . esc_html__( 'The preview uses the real bar script and styles.', 'announcement-bar-pro' ) . '</p>';
		echo '</div></section>';
	}
}
