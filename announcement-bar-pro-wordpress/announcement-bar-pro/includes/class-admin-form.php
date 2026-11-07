<?php
/**
 * Message form (cards + sticky live preview in a sandboxed frame).
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
	 * Options.
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
	 * Pro licence.
	 *
	 * @var bool
	 */
	private $pro;

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
		$this->o   = ( new Features() )->options( isset( $message['options'] ) ? $message['options'] : '' );
		$this->err = $errors;
		$this->pro = Licence::is_pro();
		$this->wc  = Commerce::active() && current_user_can( 'manage_woocommerce' );
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
	private function error( $key, $id ) {
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
	private function describe( $key, $id, $help ) {
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
	private function help( $id, $text ) {
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
	private function input( $name, $label, $value, array $args = array() ) {
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
	private function select( $name, $label, $value, array $choices, array $args = array() ) {
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
	private function checkbox( $name, $label, $on, $help = '' ) {
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
	private function textarea( $name, $label, $value, array $args = array() ) {
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
	private function card( $title, $icon, $note = '' ) {
		echo '<section class="abp-card"><h2 class="abp-card-title"><span class="abp-ico-badge" aria-hidden="true">' . Icons::svg( $icon, 'abp-card-ico' ) . '</span>' . esc_html( $title ) . ( '' !== $note ? '<small>' . esc_html( $note ) . '</small>' : '' ) . '</h2><div class="abp-card-body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
	}

	/**
	 * Closes a card.
	 */
	private function end_card() {
		echo '</div></section>';
	}

	/**
	 * Opens a fieldset that is disabled (and marked) without the Pro licence or WooCommerce rights.
	 *
	 * @param string $legend   Legend.
	 * @param bool   $commerce Needs WooCommerce.
	 * @param string $attrs    Extra attributes (fixed strings).
	 */
	private function group( $legend, $commerce = false, $attrs = '' ) {
		$locked = ! $this->pro || ( $commerce && ! $this->wc );

		echo '<fieldset class="abp-group' . ( $locked ? ' abp-locked' : '' ) . '"' . ( $locked ? ' disabled' : '' ) . $attrs . '><legend>' . esc_html( $legend ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed strings.

		if ( ! $this->pro ) {
			echo ' <span class="abp-pro-badge">' . esc_html__( 'Pro', 'announcement-bar-pro' ) . '</span>';
		}

		echo '</legend>';

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
		$this->extras_card();

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
			$t     = isset( $this->m['texts'][ $locale ] ) ? $this->m['texts'][ $locale ] : array();
			$v     = static function ( $field ) use ( $t ) {
				return isset( $t[ $field ] ) ? (string) $t[ $field ] : '';
			};
			$extra = ( new Features() )->extra_of( $v( 'extra' ) );
			$n     = static function ( $field ) use ( $locale ) {
				return 'texts[' . $locale . '][' . $field . ']';
			};
			$k     = static function ( $field ) use ( $locale ) {
				return 'texts.' . $locale . '.' . $field;
			};

			echo '<div class="abp-lang-pane" id="abp-pane-' . esc_attr( $locale ) . '"' . ( count( $locales ) > 1 ? ' role="tabpanel" aria-labelledby="abp-tab-' . esc_attr( $locale ) . '"' : '' ) . ( 0 === $i ? '' : ' hidden' ) . ' data-locale="' . esc_attr( $locale ) . '">';

			$this->textarea(
				$n( 'text' ),
				__( 'Text', 'announcement-bar-pro' ),
				$v( 'text' ),
				array(
					'key'  => $k( 'text' ),
					'help' => __( 'Placeholders: {countdown} (timer), {remaining} (amount still missing for free shipping / the next tier), {goal} (name of the next tier).', 'announcement-bar-pro' ),
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

			echo '<div data-show="tiers">';
			$this->group( __( 'Reward tiers — labels and own texts', 'announcement-bar-pro' ), true );
			echo '<p class="abp-help">' . esc_html__( 'Label of each tier (shown under the bar and for {goal}); optional own text while that tier is next.', 'announcement-bar-pro' ) . '</p><div class="abp-tier-texts">';
			for ( $j = 0; $j < Features::MAX_TIERS; $j++ ) {
				/* translators: %d: tier number */
				$label = sprintf( __( 'Tier %d label', 'announcement-bar-pro' ), $j + 1 );
				/* translators: %d: tier number */
				$text = sprintf( __( 'Tier %d own text', 'announcement-bar-pro' ), $j + 1 );
				echo '<div class="abp-row2" data-tier="' . (int) $j . '">';
				$this->input( $n( 'tier_label' ) . '[' . $j . ']', $label, isset( $extra['tier_label'][ $j ] ) ? $extra['tier_label'][ $j ] : '', array( 'key' => $k( 'tier_label' ) ) );
				$this->input( $n( 'tier_text' ) . '[' . $j . ']', $text, isset( $extra['tier_text'][ $j ] ) ? $extra['tier_text'][ $j ] : '', array( 'key' => $k( 'tier_text' ) ) );
				echo '</div>';
			}
			echo '</div></fieldset></div>';

			echo '<div data-show="ab">';
			$this->group( __( 'Variant B (A/B test)', 'announcement-bar-pro' ) );
			$this->textarea( $n( 'text_b' ), __( 'Text B', 'announcement-bar-pro' ), $v( 'text_b' ), array( 'key' => $k( 'text_b' ) ) );
			$this->textarea(
				$n( 'mobile_text_b' ),
				__( 'Shorter text B for phones (optional)', 'announcement-bar-pro' ),
				$v( 'mobile_text_b' ),
				array(
					'key'  => $k( 'mobile_text_b' ),
					'rows' => 1,
				)
			);
			$this->input( $n( 'link_text_b' ), __( 'Button text B (optional)', 'announcement-bar-pro' ), $v( 'link_text_b' ), array( 'key' => $k( 'link_text_b' ) ) );
			echo '</fieldset></div>';

			echo '<div data-show="email">';
			$this->group( __( 'E-mail sign-up texts (empty = default text)', 'announcement-bar-pro' ) );
			$defaults = Renderer::email_defaults();
			echo '<div class="abp-row2">';
			$this->input( $n( 'email_placeholder' ), __( 'Field placeholder', 'announcement-bar-pro' ), $extra['email_placeholder'], array( 'attrs' => ' placeholder="' . esc_attr( $defaults['placeholder'] ) . '"' ) );
			$this->input( $n( 'email_button' ), __( 'Button', 'announcement-bar-pro' ), $extra['email_button'], array( 'attrs' => ' placeholder="' . esc_attr( $defaults['button'] ) . '"' ) );
			echo '</div>';
			$this->input(
				$n( 'email_consent' ),
				__( 'Consent text (stored with each sign-up as proof)', 'announcement-bar-pro' ),
				$extra['email_consent'],
				array(
					'attrs' => ' placeholder="' . esc_attr( $defaults['consent'] ) . '"',
					'class' => 'large-text',
				)
			);
			$this->input(
				$n( 'email_success' ),
				__( 'Text after sending', 'announcement-bar-pro' ),
				$extra['email_success'],
				array(
					'attrs' => ' placeholder="' . esc_attr( $defaults['success'] ) . '"',
					'class' => 'large-text',
				)
			);
			echo '</fieldset></div>';

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
			''         => array( __( 'As in the settings', 'announcement-bar-pro' ), __( 'Use the main style', 'announcement-bar-pro' ) ),
			'solid'    => array( __( 'Solid', 'announcement-bar-pro' ), __( 'One clear colour', 'announcement-bar-pro' ) ),
			'soft'     => array( __( 'Soft', 'announcement-bar-pro' ), __( 'Light tint, dark text', 'announcement-bar-pro' ) ),
			'minimal'  => array( __( 'Minimal', 'announcement-bar-pro' ), __( 'Light with an accent line', 'announcement-bar-pro' ) ),
			'gradient' => array( __( 'Gradient', 'announcement-bar-pro' ), __( 'Colour blend', 'announcement-bar-pro' ) ),
			'glass'    => array( __( 'Glass', 'announcement-bar-pro' ), __( 'See-through, blurred', 'announcement-bar-pro' ) ),
		);
		echo '<fieldset class="abp-field"><legend>' . esc_html__( 'Style', 'announcement-bar-pro' ) . '</legend><div class="abp-styles">';
		foreach ( $styles as $key => $s ) {
			$locked = ! $this->pro && in_array( $key, array( 'gradient', 'glass' ), true );
			echo '<span class="abp-style-opt"><input type="radio" name="style" id="abp-style-' . esc_attr( '' === $key ? 'default' : $key ) . '" value="' . esc_attr( $key ) . '"' . checked( $key, (string) $m['style'], false ) . disabled( $locked, true, false ) . '><label for="abp-style-' . esc_attr( '' === $key ? 'default' : $key ) . '"><span class="abp-sw abp-sw-' . esc_attr( '' === $key ? 'default' : $key ) . '" aria-hidden="true"><i></i><b></b></span><span class="abp-name">' . esc_html( $s[0] ) . ( $locked ? ' <span class="abp-pro-badge">' . esc_html__( 'Pro', 'announcement-bar-pro' ) . '</span>' : '' ) . '</span><span class="abp-desc">' . esc_html( $s[1] ) . '</span></label></span>';
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

		$this->group( __( 'Background image and font', 'announcement-bar-pro' ) );
		$image = (int) $this->o['image'];
		$thumb = $image ? wp_get_attachment_image_url( $image, 'thumbnail' ) : '';
		echo '<div class="abp-field"><span class="abp-label" id="abp-image-label">' . esc_html__( 'Background image', 'announcement-bar-pro' ) . '</span><div class="abp-media" aria-labelledby="abp-image-label"><input type="hidden" name="opt[image]" id="abp-image" value="' . (int) $image . '"><img id="abp-image-thumb" alt="" src="' . esc_url( (string) $thumb ) . '"' . ( $thumb ? '' : ' hidden' ) . '>'
			. '<button type="button" class="button" id="abp-image-pick">' . esc_html__( 'Choose image', 'announcement-bar-pro' ) . '</button> <button type="button" class="button-link" id="abp-image-clear"' . ( $image ? '' : ' hidden' ) . '>' . esc_html__( 'Remove image', 'announcement-bar-pro' ) . '</button></div>';
		$this->error( 'opt_image', 'abp-image' );
		echo '</div><div class="abp-row2">';
		$this->input(
			'opt[overlay]',
			__( 'Colour over the image (%)', 'announcement-bar-pro' ),
			$this->o['overlay'],
			array(
				'type'  => 'number',
				'class' => 'small-text',
				'key'   => 'opt_overlay',
				'attrs' => ' min="0" max="90" step="5"',
				'help'  => __( 'Keeps the text readable: 55 % or more for most photos.', 'announcement-bar-pro' ),
			)
		);
		$this->select( 'opt[font]', __( 'Font', 'announcement-bar-pro' ), $this->o['font'], self::fonts(), array( 'key' => 'opt_font' ) );
		echo '</div></fieldset>';

		$this->end_card();
	}

	/**
	 * Font choices.
	 *
	 * @return array
	 */
	public static function fonts() {
		return array(
			''          => __( 'Theme font', 'announcement-bar-pro' ),
			'system'    => __( 'System UI', 'announcement-bar-pro' ),
			'humanist'  => __( 'Humanist sans', 'announcement-bar-pro' ),
			'geometric' => __( 'Geometric sans', 'announcement-bar-pro' ),
			'serif'     => __( 'Serif', 'announcement-bar-pro' ),
			'mono'      => __( 'Monospace', 'announcement-bar-pro' ),
			'rounded'   => __( 'Rounded', 'announcement-bar-pro' ),
		);
	}

	/**
	 * Message type, free-shipping amount, tiers, country / currency amounts.
	 */
	private function goal_card() {
		$m  = $this->m;
		$o  = $this->o;
		$wc = Commerce::active();

		$this->card( __( 'Type and goal', 'announcement-bar-pro' ), 'truck', $wc ? '' : __( 'Free shipping and goals need WooCommerce', 'announcement-bar-pro' ) );

		$types = array( 'standard' => __( 'Announcement', 'announcement-bar-pro' ) );
		if ( $wc ) {
			$types['free_shipping'] = __( 'Free-shipping progress', 'announcement-bar-pro' );
			$types['tiers']         = __( 'Reward tiers (several goals)', 'announcement-bar-pro' ) . ( $this->pro ? '' : ' — Pro' );
		}

		$this->select(
			'type',
			__( 'Type', 'announcement-bar-pro' ),
			$m['type'],
			$types,
			array(
				'attrs' => $this->wc ? '' : ' disabled',
				'help'  => __( 'Free-shipping progress and reward tiers show how much is still missing in the cart and update when the cart changes.', 'announcement-bar-pro' ),
			)
		);

		if ( ! $wc ) {
			$this->end_card();
			return;
		}

		echo '<div data-show="fs">';
		$this->checkbox( 'opt[fs_wc]', __( 'Use the minimum amount of WooCommerce\'s "Free shipping" method', 'announcement-bar-pro' ), (int) $o['fs_wc'], __( 'Taken from the shipping zone of the visitor. The message hides itself when that zone has no free shipping with a minimum amount.', 'announcement-bar-pro' ) );
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

		echo '<div data-show="tiers">';
		$this->group( __( 'Tier amounts', 'announcement-bar-pro' ), true );
		echo '<p class="abp-help">' . esc_html__( '2 to 5 rising amounts; labels are set per language under Message.', 'announcement-bar-pro' ) . '</p><div class="abp-tiers">';
		for ( $j = 0; $j < Features::MAX_TIERS; $j++ ) {
			$value = isset( $o['tiers'][ $j ] ) ? rtrim( rtrim( $o['tiers'][ $j ], '0' ), '.' ) : '';
			/* translators: %d: tier number */
			$this->input( 'opt[tiers][' . $j . ']', sprintf( __( 'Tier %d', 'announcement-bar-pro' ), $j + 1 ), $value, array( 'class' => 'small-text', 'key' => 'opt_tiers', 'attrs' => ' inputmode="decimal" data-tier-amount="' . (int) $j . '"' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		}
		echo '</div></fieldset></div>';

		echo '<div data-show="fs">';
		$this->group( __( 'Amounts by country, state or currency', 'announcement-bar-pro' ), true );
		echo '<p class="abp-help">' . esc_html__( 'Optional. A state amount wins over a country amount, which wins over a currency amount; otherwise the amount above is used.', 'announcement-bar-pro' ) . '</p>';
		$countries = WC()->countries->get_countries();
		$rows      = $o['fs_zones'];
		$rows[]    = array(
			'country' => '',
			'state'   => '',
			'amount'  => '',
		);
		echo '<table class="abp-zones"><thead><tr><th scope="col">' . esc_html__( 'Country', 'announcement-bar-pro' ) . '</th><th scope="col">' . esc_html__( 'State code (optional)', 'announcement-bar-pro' ) . '</th><th scope="col">' . esc_html__( 'Amount', 'announcement-bar-pro' ) . '</th></tr></thead><tbody id="abp-zone-rows">';
		foreach ( $rows as $j => $r ) {
			echo '<tr><td><select name="opt[fs_zones][' . (int) $j . '][country]" aria-label="' . esc_attr__( 'Country', 'announcement-bar-pro' ) . '"><option value="">—</option>';
			foreach ( $countries as $code => $name ) {
				echo '<option value="' . esc_attr( $code ) . '"' . selected( $r['country'], $code, false ) . '>' . esc_html( $name ) . '</option>';
			}
			echo '</select></td><td><input type="text" class="small-text" name="opt[fs_zones][' . (int) $j . '][state]" value="' . esc_attr( $r['state'] ) . '" aria-label="' . esc_attr__( 'State code (optional)', 'announcement-bar-pro' ) . '"></td><td><input type="text" class="small-text" inputmode="decimal" name="opt[fs_zones][' . (int) $j . '][amount]" value="' . esc_attr( '' !== $r['amount'] ? rtrim( rtrim( $r['amount'], '0' ), '.' ) : '' ) . '" aria-label="' . esc_attr__( 'Amount', 'announcement-bar-pro' ) . '"></td></tr>';
		}
		echo '</tbody></table><button type="button" class="button" id="abp-zone-add">' . esc_html__( 'Add row', 'announcement-bar-pro' ) . '</button>';
		$this->error( 'opt_fs_zones', 'abp-zones' );

		$codes = array_unique( array_merge( array( get_woocommerce_currency() ), array_keys( $o['fs_currencies'] ) ) );
		echo '<div class="abp-row2 abp-currencies">';
		foreach ( $codes as $code ) {
			/* translators: %s: currency code */
			$this->input( 'opt[fs_currencies][' . $code . ']', sprintf( __( 'Fixed amount in %s', 'announcement-bar-pro' ), $code ), isset( $o['fs_currencies'][ $code ] ) ? rtrim( rtrim( $o['fs_currencies'][ $code ], '0' ), '.' ) : '', array( 'class' => 'small-text', 'key' => 'opt_fs_currencies' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		}
		echo '</div><p class="abp-help">' . esc_html__( 'Currency amounts are for multi-currency shops: the amount is used as it is, without conversion.', 'announcement-bar-pro' ) . '</p></fieldset></div>';

		$this->end_card();
	}

	/**
	 * Status, dates, countdown, weekly schedule, recurring cut-off.
	 */
	private function schedule_card() {
		$m = $this->m;
		$o = $this->o;

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

		$this->group( __( 'Recurring countdown (daily / weekly cut-off)', 'announcement-bar-pro' ) );
		echo '<div class="abp-row2">';
		$this->select(
			'opt[recur]',
			__( 'Repeat', 'announcement-bar-pro' ),
			$o['recur'],
			array(
				''       => __( 'No — count down to the end date', 'announcement-bar-pro' ),
				'daily'  => __( 'Every day', 'announcement-bar-pro' ),
				'weekly' => __( 'Once a week', 'announcement-bar-pro' ),
			),
			array( 'key' => 'opt_recur' )
		);
		$this->input(
			'opt[recur_time]',
			__( 'Cut-off time', 'announcement-bar-pro' ),
			$o['recur_time'],
			array(
				'type'  => 'time',
				'class' => '',
				'key'   => 'opt_recur',
			)
		);
		echo '</div><div data-show="recur-weekly">';
		$this->select( 'opt[recur_day]', __( 'Day', 'announcement-bar-pro' ), (string) $o['recur_day'], $days );
		echo '</div><fieldset class="abp-field" data-show="recur-daily"><legend>' . esc_html__( 'Skip these days', 'announcement-bar-pro' ) . '</legend><div class="abp-days">';
		foreach ( $days as $d => $label ) {
			echo '<label class="abp-day"><input type="checkbox" name="opt[recur_skip][]" value="' . (int) $d . '"' . checked( false !== strpos( (string) $o['recur_skip'], (string) $d ), true, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</div></fieldset></fieldset>';

		$this->end_card();
	}

	/**
	 * Device, placement, priority, roles, pages, products, countries, cart, campaign, trigger.
	 */
	private function audience_card() {
		$m  = $this->m;
		$o  = $this->o;
		$wc = Commerce::active();

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

		$places = array(
			''       => __( 'Main bar (position from the settings)', 'announcement-bar-pro' ),
			'top'    => __( 'Top bar', 'announcement-bar-pro' ),
			'bottom' => __( 'Bottom bar', 'announcement-bar-pro' ),
		);
		if ( $wc ) {
			$places['cart']     = __( 'Box on the cart page', 'announcement-bar-pro' );
			$places['product']  = __( 'Box under the product price', 'announcement-bar-pro' );
			$places['minicart'] = __( 'Box in the mini-cart', 'announcement-bar-pro' );
		}

		$this->group( __( 'Placement and priority', 'announcement-bar-pro' ) );
		echo '<div class="abp-row2">';
		$this->select( 'placement', __( 'Placement', 'announcement-bar-pro' ), $m['placement'], $places, array( 'help' => __( 'Boxes appear in the cart (classic page and cart block), under the price of the product page and in the classic mini-cart.', 'announcement-bar-pro' ) ) );
		$this->input(
			'priority',
			__( 'Priority (0–100)', 'announcement-bar-pro' ),
			$m['priority'],
			array(
				'type'  => 'number',
				'class' => 'small-text',
				'attrs' => ' min="0" max="100"',
				'help'  => __( 'Higher priority comes first; with "most messages at once" set, lower ones are left out.', 'announcement-bar-pro' ),
			)
		);
		echo '</div></fieldset>';

		$this->group( __( 'Visitors and pages', 'announcement-bar-pro' ) );
		$roles  = json_decode( (string) $m['roles'], true );
		$roles  = is_array( $roles ) ? $roles : array();
		$choice = array_merge( array( 'guest' => __( 'Not logged in', 'announcement-bar-pro' ) ), wp_roles()->get_names() );
		echo '<fieldset class="abp-field"><legend>' . esc_html__( 'Show to (none ticked = everyone)', 'announcement-bar-pro' ) . '</legend><div class="abp-checks">';
		foreach ( $choice as $key => $label ) {
			echo '<label><input type="checkbox" name="roles[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $roles, true ), true, false ) . '> ' . esc_html( translate_user_role( $label ) ) . '</label>';
		}
		echo '</div></fieldset>';
		$this->select(
			'page_mode',
			__( 'Pages', 'announcement-bar-pro' ),
			$m['page_mode'],
			array(
				'all'     => __( 'Every page', 'announcement-bar-pro' ),
				'home'    => __( 'Front page only', 'announcement-bar-pro' ),
				'include' => __( 'Only these pages', 'announcement-bar-pro' ),
				'exclude' => __( 'All pages except these', 'announcement-bar-pro' ),
			)
		);
		echo '<div data-show="pages">';
		$this->textarea(
			'page_rules',
			__( 'Page rules (one per line)', 'announcement-bar-pro' ),
			$m['page_rules'],
			array(
				'rows' => 3,
				/* translators: %s: list of page types */
				'help' => sprintf( __( 'Page types: %s — or paths such as /sale/ (exact) and /blog/* (everything below).', 'announcement-bar-pro' ), implode( ', ', Admin::PAGE_TYPES ) ),
			)
		);
		echo '</div>';
		$this->input(
			'opt[referrer]',
			__( 'Visitors coming from (sites)', 'announcement-bar-pro' ),
			$o['referrer'],
			array(
				'key'  => 'opt_referrer',
				'help' => __( 'For example facebook.com, instagram.com — separated by commas.', 'announcement-bar-pro' ),
			)
		);
		echo '<div class="abp-row3">';
		$this->input( 'opt[utm_source]', __( 'utm_source', 'announcement-bar-pro' ), $o['utm_source'], array( 'key' => 'opt_utm_source', 'class' => '' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		$this->input( 'opt[utm_medium]', __( 'utm_medium', 'announcement-bar-pro' ), $o['utm_medium'], array( 'key' => 'opt_utm_medium', 'class' => '' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		$this->input( 'opt[utm_campaign]', __( 'utm_campaign', 'announcement-bar-pro' ), $o['utm_campaign'], array( 'key' => 'opt_utm_campaign', 'class' => '' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		echo '</div><p class="abp-help">' . esc_html__( 'Campaign values of the landing page, kept for the visit (decided in the browser).', 'announcement-bar-pro' ) . '</p>';
		$this->select(
			'opt[visitor]',
			__( 'New or returning visitors', 'announcement-bar-pro' ),
			$o['visitor'],
			array(
				'all'       => __( 'Everyone', 'announcement-bar-pro' ),
				'new'       => __( 'First visit only', 'announcement-bar-pro' ),
				'returning' => __( 'Returning visitors only', 'announcement-bar-pro' ),
			),
			array( 'key' => 'opt_visitor' )
		);
		echo '<div class="abp-row2">';
		$this->select(
			'opt[trigger]',
			__( 'Show', 'announcement-bar-pro' ),
			$o['trigger'],
			array(
				'none'   => __( 'At once', 'announcement-bar-pro' ),
				'delay'  => __( 'After a delay (seconds)', 'announcement-bar-pro' ),
				'scroll' => __( 'After scrolling (%)', 'announcement-bar-pro' ),
				'exit'   => __( 'When the visitor is about to leave (computers)', 'announcement-bar-pro' ),
			),
			array( 'key' => 'opt_trigger' )
		);
		echo '<div data-show="trigger-value">';
		$this->input(
			'opt[trigger_value]',
			__( 'Seconds / scroll %', 'announcement-bar-pro' ),
			$o['trigger_value'] ? $o['trigger_value'] : '',
			array(
				'type'  => 'number',
				'class' => 'small-text',
				'key'   => 'opt_trigger',
			)
		);
		echo '</div></div></fieldset>';

		if ( $wc ) {
			$this->commerce_audience();
		}

		$this->end_card();
	}

	/**
	 * WooCommerce parts of the audience: products / categories, countries, cart conditions.
	 */
	private function commerce_audience() {
		$m = $this->m;
		$o = $this->o;

		$this->group( __( 'Products, countries and cart', 'announcement-bar-pro' ), true );

		$products = ( new Sanitizer() )->id_list( $m['products'] );
		$cats     = ( new Sanitizer() )->id_list( $m['categories'] );
		$terms    = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 500,
			)
		);
		$terms    = is_array( $terms ) ? $terms : array();

		echo '<p class="abp-help">' . esc_html__( 'With products or categories chosen, the message shows only on those product pages and category pages.', 'announcement-bar-pro' ) . '</p><div class="abp-row2">';
		$this->products_field( 'products[]', 'abp-products', __( 'Product pages', 'announcement-bar-pro' ), $products, 'products' );
		$this->categories_field( 'categories[]', 'abp-categories', __( 'Category pages', 'announcement-bar-pro' ), $cats, $terms, 'categories' );
		echo '</div>';

		$countries = WC()->countries->get_countries();
		echo '<div class="abp-row2">';
		$this->select(
			'opt[countries_mode]',
			__( 'Countries', 'announcement-bar-pro' ),
			$o['countries_mode'],
			array(
				'all'     => __( 'Every country', 'announcement-bar-pro' ),
				'include' => __( 'Only these countries', 'announcement-bar-pro' ),
				'exclude' => __( 'All except these countries', 'announcement-bar-pro' ),
			),
			array( 'key' => 'opt_countries' )
		);
		echo '<div class="abp-field" data-show="countries"><label for="abp-countries">' . esc_html__( 'Choose countries', 'announcement-bar-pro' ) . '</label><select id="abp-countries" name="opt[countries][]" multiple class="wc-enhanced-select abp-multi" data-placeholder="' . esc_attr__( 'Search a country…', 'announcement-bar-pro' ) . '">';
		foreach ( $countries as $code => $name ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( in_array( $code, $o['countries'], true ), true, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select><p class="abp-help">' . esc_html__( 'The visitor\'s country comes from the WooCommerce customer address (or WooCommerce\'s own geolocation setting). No outside service is asked.', 'announcement-bar-pro' ) . '</p></div></div>';

		echo '<h3 class="abp-sub">' . esc_html__( 'Cart conditions (every set condition must hold)', 'announcement-bar-pro' ) . '</h3><div class="abp-row4">';
		foreach ( array(
			'cart_subtotal_min' => __( 'Subtotal from', 'announcement-bar-pro' ),
			'cart_subtotal_max' => __( 'Subtotal up to', 'announcement-bar-pro' ),
			'cart_qty_min'      => __( 'Items from', 'announcement-bar-pro' ),
			'cart_qty_max'      => __( 'Items up to', 'announcement-bar-pro' ),
		) as $key => $label ) {
			$value = $o[ $key ];
			if ( '' !== $value && false !== strpos( $value, '.' ) ) {
				$value = rtrim( rtrim( $value, '0' ), '.' );
			}
			$this->input( 'opt[' . $key . ']', $label, $value, array( 'class' => 'small-text', 'key' => 'opt_' . $key, 'attrs' => ' inputmode="decimal"' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		}
		echo '</div><div class="abp-row2">';
		$this->products_field( 'opt[cart_products][]', 'abp-cart-products', __( 'Cart contains one of these products', 'announcement-bar-pro' ), $o['cart_products'], 'opt_cart_products' );
		$this->categories_field( 'opt[cart_categories][]', 'abp-cart-categories', __( 'Cart contains a product of these categories', 'announcement-bar-pro' ), $o['cart_categories'], $terms, 'opt_cart_categories' );
		echo '</div></fieldset>';
	}

	/**
	 * WooCommerce product search field.
	 *
	 * @param string $name  Field name.
	 * @param string $id    Element ID.
	 * @param string $label Label.
	 * @param array  $ids   Chosen IDs.
	 * @param string $key   Error key.
	 */
	private function products_field( $name, $id, $label, array $ids, $key ) {
		echo '<div class="abp-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" multiple class="wc-product-search abp-multi" data-placeholder="' . esc_attr__( 'Search a product…', 'announcement-bar-pro' ) . '" data-action="woocommerce_json_search_products_and_variations">';
		foreach ( $ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( $product ) {
				echo '<option value="' . (int) $pid . '" selected>' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
			}
		}
		echo '</select>';
		$this->error( $key, $id );
		echo '</div>';
	}

	/**
	 * Product category field.
	 *
	 * @param string $name  Field name.
	 * @param string $id    Element ID.
	 * @param string $label Label.
	 * @param array  $ids   Chosen IDs.
	 * @param array  $terms Categories.
	 * @param string $key   Error key.
	 */
	private function categories_field( $name, $id, $label, array $ids, array $terms, $key ) {
		echo '<div class="abp-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" multiple class="wc-enhanced-select abp-multi" data-placeholder="' . esc_attr__( 'Search a category…', 'announcement-bar-pro' ) . '">';
		foreach ( $terms as $term ) {
			echo '<option value="' . (int) $term->term_id . '"' . selected( in_array( (int) $term->term_id, $ids, true ), true, false ) . '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select>';
		$this->error( $key, $id );
		echo '</div>';
	}

	/**
	 * Coupon, e-mail sign-up, A/B test.
	 */
	private function extras_card() {
		$m  = $this->m;
		$o  = $this->o;
		$wc = Commerce::active();

		$this->card( __( 'Coupon, sign-up and A/B test', 'announcement-bar-pro' ), 'ticket' );

		$this->group( __( 'Coupon', 'announcement-bar-pro' ) );
		echo '<div class="abp-row2">';
		if ( $wc ) {
			$coupons = get_posts(
				array(
					'post_type'      => 'shop_coupon',
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
				)
			);
			$choice  = array( '0' => __( '— none —', 'announcement-bar-pro' ) );
			foreach ( $coupons as $cid ) {
				$choice[ (string) $cid ] = strtoupper( get_the_title( $cid ) );
			}
			if ( $o['coupon_id'] && ! isset( $choice[ (string) $o['coupon_id'] ] ) ) {
				$choice[ (string) $o['coupon_id'] ] = get_the_title( $o['coupon_id'] );
			}
			$this->select(
				'opt[coupon_id]',
				__( 'WooCommerce coupon', 'announcement-bar-pro' ),
				(string) $o['coupon_id'],
				$choice,
				array(
					'key'   => 'opt_coupon_id',
					'help'  => __( 'Shows the code with a copy button. The message hides itself when the coupon expires or is used up — the same rules WooCommerce uses.', 'announcement-bar-pro' ),
					'attrs' => $this->wc ? '' : ' disabled',
				)
			);
		}
		$this->input(
			'coupon',
			__( 'Or a fixed code', 'announcement-bar-pro' ),
			$m['coupon'],
			array(
				'class' => '',
				'help'  => __( 'Shown as it is (not checked against the shop).', 'announcement-bar-pro' ),
				'attrs' => ' maxlength="32" spellcheck="false"',
			)
		);
		echo '</div></fieldset>';

		$this->group( __( 'E-mail sign-up (GDPR, double opt-in)', 'announcement-bar-pro' ) );
		$this->checkbox( 'opt[email]', __( 'Show an e-mail sign-up form in this message', 'announcement-bar-pro' ), (int) $o['email'], __( 'With a consent box, a link to the privacy page and a confirmation e-mail. Texts per language are under Message.', 'announcement-bar-pro' ) );
		echo '</fieldset>';

		$this->group( __( 'A/B test', 'announcement-bar-pro' ) );
		$this->checkbox( 'ab_test', __( 'Test a second text (variant B)', 'announcement-bar-pro' ), (int) $m['ab_test'], __( 'Each browser keeps one variant. Views, clicks and CTR per variant appear in the message list.', 'announcement-bar-pro' ) );
		echo '</fieldset>';

		$this->end_card();
	}

	/**
	 * Sticky live preview.
	 */
	private function preview_card() {
		$locales = Settings::locales();

		echo '<section class="abp-card abp-preview-card" aria-labelledby="abp-pv-title"><h2 class="abp-card-title" id="abp-pv-title"><span class="abp-ico-badge" aria-hidden="true">' . Icons::svg( 'info', 'abp-card-ico' ) . '</span>' . esc_html__( 'Live preview', 'announcement-bar-pro' ) . '</h2><div class="abp-card-body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
		echo '<div class="abp-pv-tools"><div class="abp-seg" role="group" aria-label="' . esc_attr__( 'Screen', 'announcement-bar-pro' ) . '"><button type="button" class="active" data-pv-device="desktop" aria-pressed="true">' . esc_html__( 'Computer', 'announcement-bar-pro' ) . '</button><button type="button" data-pv-device="mobile" aria-pressed="false">' . esc_html__( 'Phone', 'announcement-bar-pro' ) . '</button></div>';
		echo '<div class="abp-seg" role="group" aria-label="' . esc_attr__( 'Variant', 'announcement-bar-pro' ) . '" data-show="ab"><button type="button" class="active" data-pv-variant="a" aria-pressed="true">A</button><button type="button" data-pv-variant="b" aria-pressed="false">B</button></div>';

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
		echo '<p class="abp-help">' . esc_html__( 'The preview uses the real bar script and styles. Triggers, campaign rules and statistics are not applied here.', 'announcement-bar-pro' ) . '</p>';
		echo '</div></section>';
	}
}
