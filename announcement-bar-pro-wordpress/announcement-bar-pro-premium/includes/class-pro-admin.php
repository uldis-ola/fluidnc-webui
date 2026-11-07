<?php
/**
 * Premium admin: the premium fields of the message form, their validation, statistics and actions in the message
 * list, feature badges and the templates gallery — all through the hooks of the free plugin.
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

use AnnouncementBarPro\Admin;
use AnnouncementBarPro\Admin_Form;
use AnnouncementBarPro\Commerce;
use AnnouncementBarPro\DB;
use AnnouncementBarPro\Icons;
use AnnouncementBarPro\Sanitizer;
use AnnouncementBarPro\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Premium admin screens and fields.
 */
class Pro_Admin {
	/** Options that need manage_woocommerce. */
	const COMMERCE_OPTIONS = array( 'countries_mode', 'countries', 'fs_wc', 'fs_zones', 'fs_currencies', 'coupon_id', 'tiers', 'cart_subtotal_min', 'cart_subtotal_max', 'cart_qty_min', 'cart_qty_max', 'cart_products', 'cart_categories' );

	/** Page-type rules (see Pro_Render::type_matches()). */
	const PAGE_TYPES = array( 'home', 'blog', 'shop', 'cart', 'checkout', 'account', 'product', 'category', 'page', 'post', 'search', '404' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_filter( 'abpro_admin_tabs', array( __CLASS__, 'tabs' ) );
		add_action( 'abpro_admin_assets', array( __CLASS__, 'assets' ), 10, 2 );
		add_filter( 'abpro_admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'abpro_list_columns', array( __CLASS__, 'columns' ) );
		add_action( 'abpro_list_cell', array( __CLASS__, 'cell' ), 10, 2 );
		add_action( 'abpro_list_actions', array( __CLASS__, 'list_actions' ) );
		add_action( 'abpro_list_empty_actions', array( __CLASS__, 'empty_actions' ) );
		add_action( 'abpro_message_action', array( __CLASS__, 'message_action' ), 10, 3 );
		add_filter( 'abpro_message_badges', array( __CLASS__, 'badges' ), 10, 2 );
		add_filter( 'abpro_blank_message', array( __CLASS__, 'blank' ), 10, 2 );
		add_filter( 'abpro_message_types', array( __CLASS__, 'types' ) );
		add_filter( 'abpro_style_choices', array( __CLASS__, 'styles' ) );
		add_action( 'abpro_form_texts', array( __CLASS__, 'form_texts' ), 10, 3 );
		add_action( 'abpro_form_look', array( __CLASS__, 'form_look' ) );
		add_action( 'abpro_form_goal', array( __CLASS__, 'form_goal' ) );
		add_action( 'abpro_form_schedule', array( __CLASS__, 'form_schedule' ), 10, 2 );
		add_action( 'abpro_form_audience', array( __CLASS__, 'form_audience' ) );
		add_action( 'abpro_form_cards', array( __CLASS__, 'extras_card' ) );
		add_action( 'abpro_form_preview_tools', array( __CLASS__, 'preview_tools' ) );
		add_filter( 'abpro_message_from_post', array( __CLASS__, 'from_post' ), 10, 4 );
		add_filter( 'abpro_preview_row', array( __CLASS__, 'preview_row' ), 10, 2 );
	}

	/**
	 * Options of the message in the form (complete and valid).
	 *
	 * @param Admin_Form $form Form.
	 * @return array
	 */
	private static function opt( $form ) {
		return ( new Features() )->options( $form->options() );
	}

	/*
	 * ------------------------------------------------------------------
	 * Menu, tabs, assets, notices
	 * ------------------------------------------------------------------
	 */

	/**
	 * Templates screen (after "Add message").
	 */
	public static function menu() {
		add_submenu_page( Admin::SLUG, __( 'Templates', 'announcement-bar-pro-premium' ), __( 'Templates', 'announcement-bar-pro-premium' ), Admin::CAP, Admin::SLUG . '-templates', array( __CLASS__, 'page_templates' ), 2 );
	}

	/**
	 * Templates tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function tabs( $tabs ) {
		$out = array();

		foreach ( (array) $tabs as $key => $label ) {
			if ( 'settings' === $key ) {
				$out['templates'] = __( 'Templates', 'announcement-bar-pro-premium' );
			}
			$out[ $key ] = $label;
		}

		return $out;
	}

	/**
	 * Media library, WooCommerce selects and the premium form script on the message form.
	 *
	 * @param string $hook Screen hook.
	 * @param bool   $edit Message form.
	 */
	public static function assets( $hook, $edit ) {
		if ( ! $edit ) {
			wp_enqueue_script( 'abp-admin-premium', ABPRO_PREMIUM_URL . 'assets/admin-premium.js', array( 'abp-admin' ), ABPRO_PREMIUM_VERSION, true );
			wp_localize_script( 'abp-admin-premium', 'abpPremium', self::l10n( false ) );
			return;
		}

		wp_enqueue_media();

		if ( Commerce::active() && current_user_can( 'manage_woocommerce' ) && wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		wp_enqueue_script( 'abp-admin-premium', ABPRO_PREMIUM_URL . 'assets/admin-premium.js', array( 'abp-admin' ), ABPRO_PREMIUM_VERSION, true );
		wp_localize_script( 'abp-admin-premium', 'abpPremium', self::l10n( true ) );
	}

	/**
	 * Texts of the premium admin script.
	 *
	 * @param bool $edit Message form.
	 * @return array
	 */
	private static function l10n( $edit ) {
		return array(
			'states' => $edit && Commerce::active() ? WC()->countries->get_states() : new \stdClass(),
			'l10n'   => array(
				'confirmErase' => __( 'Delete every entry of this address from the list? This cannot be undone.', 'announcement-bar-pro-premium' ),
				'confirmUseB'  => __( 'Make variant B the main text and end the A/B test?', 'announcement-bar-pro-premium' ),
				'chooseImage'  => __( 'Choose a background image', 'announcement-bar-pro-premium' ),
				'useImage'     => __( 'Use this image', 'announcement-bar-pro-premium' ),
			),
		);
	}

	/**
	 * Notices of the premium actions.
	 *
	 * @param array $texts Notices.
	 * @return array
	 */
	public static function notices( $texts ) {
		return array_merge(
			(array) $texts,
			array(
				'reset' => array( 'success', __( 'Statistics reset.', 'announcement-bar-pro-premium' ) ),
				'use_b' => array( 'success', __( 'Variant B is now the main text; the A/B test has ended.', 'announcement-bar-pro-premium' ) ),
			)
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * Message list: statistics, actions, badges
	 * ------------------------------------------------------------------
	 */

	/**
	 * Statistics column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$columns          = (array) $columns;
		$columns['stats'] = __( 'Statistics', 'announcement-bar-pro-premium' );

		return $columns;
	}

	/**
	 * Statistics cell.
	 *
	 * @param string $column Column.
	 * @param array  $m      Message row.
	 */
	public static function cell( $column, $m ) {
		if ( 'stats' !== $column ) {
			return;
		}

		self::stats( $m, 'a', (int) $m['ab_test'] ? 'A' : '' );

		if ( (int) $m['ab_test'] ) {
			self::stats( $m, 'b', 'B' );
		}
	}

	/**
	 * Statistics badges (with visible labels) of one variant.
	 *
	 * @param array  $m      Message row.
	 * @param string $v      a | b.
	 * @param string $prefix Variant label.
	 */
	private static function stats( array $m, $v, $prefix ) {
		$sfx    = 'b' === $v ? '_b' : '';
		$views  = (int) $m[ 'views' . $sfx ];
		$clicks = (int) $m[ 'clicks' . $sfx ];
		$copies = (int) $m[ 'copies' . $sfx ];
		$ctr    = $views > 0 ? number_format_i18n( $clicks * 100 / $views, 1 ) . ' %' : '—';

		echo '<div class="abp-stats">' . ( '' !== $prefix ? '<span class="abp-stat-v">' . esc_html( $prefix ) . '</span>' : '' );
		Admin::stat( __( 'Views', 'announcement-bar-pro-premium' ), number_format_i18n( $views ) );
		Admin::stat( __( 'Clicks', 'announcement-bar-pro-premium' ), number_format_i18n( $clicks ) );
		Admin::stat( __( 'CTR', 'announcement-bar-pro-premium' ), $ctr );

		$opt = ( new Features() )->options( $m['options'] );

		if ( '' !== $m['coupon'] || $opt['coupon_id'] || $copies > 0 ) {
			Admin::stat( __( 'Code copies', 'announcement-bar-pro-premium' ), number_format_i18n( $copies ) );
		}

		if ( 'a' === $v && (int) $m['subscribes'] > 0 ) {
			Admin::stat( __( 'Sign-ups', 'announcement-bar-pro-premium' ), number_format_i18n( (int) $m['subscribes'] ) );
		}

		echo '</div>';
	}

	/**
	 * "Use B" and "Reset statistics" buttons.
	 *
	 * @param array $m Message row.
	 */
	public static function list_actions( $m ) {
		$id = (int) $m['message_id'];

		if ( (int) $m['ab_test'] ) {
			Admin::action_button( $id, 'use_b', __( 'Use B', 'announcement-bar-pro-premium' ), 'abp-confirm-useb' );
		}

		Admin::action_button( $id, 'reset', __( 'Reset statistics', 'announcement-bar-pro-premium' ) );
	}

	/**
	 * "Start from a template" on the empty list.
	 */
	public static function empty_actions() {
		echo ' <a class="button" href="' . esc_url( Admin::url( 'templates' ) ) . '">' . esc_html__( 'Start from a template', 'announcement-bar-pro-premium' ) . '</a>';
	}

	/**
	 * Reset statistics / use variant B (nonce and rights checked by the free plugin).
	 *
	 * @param string $command Action.
	 * @param int    $id      Message ID.
	 * @param array  $row     Message row.
	 */
	public static function message_action( $command, $id, $row ) {
		unset( $row );

		if ( 'reset' === $command ) {
			Pro_DB::reset_stats( $id );
			Admin::back( '', 'reset' );
		}

		if ( 'use_b' === $command ) {
			Admin::back( '', Pro_DB::use_variant_b( $id ) ? 'use_b' : 'failed' );
		}
	}

	/**
	 * Premium badges of a message.
	 *
	 * @param array $badges Badges of the free plugin.
	 * @param array $m      Message row.
	 * @return array
	 */
	public static function badges( $badges, $m ) {
		return array_merge( (array) $badges, self::badge_list( $m, ( new Features() )->options( $m['options'] ) ) );
	}

	/**
	 * Feature badges of a message: array( label, pro? ).
	 *
	 * @param array $m   Message row.
	 * @param array $opt Options.
	 * @return array
	 */
	private static function badge_list( array $m, array $opt ) {
		$out    = array();
		$places = self::placements();

		if ( '' !== $m['placement'] ) {
			$out[] = array( $places[ $m['placement'] ], in_array( $m['placement'], array( 'bottom', 'cart', 'product', 'minicart' ), true ) );
		}

		if ( 'tiers' === $m['type'] ) {
			$out[] = array( __( 'Reward tiers', 'announcement-bar-pro-premium' ), true );
		}

		if ( (int) $m['countdown'] && '' !== $opt['recur'] ) {
			$out[] = array( __( 'Recurring countdown', 'announcement-bar-pro-premium' ), true );
		}

		if ( '' !== $m['coupon'] || $opt['coupon_id'] ) {
			$out[] = array( __( 'Coupon', 'announcement-bar-pro-premium' ), true );
		}

		if ( (int) $m['ab_test'] ) {
			$out[] = array( __( 'A/B test', 'announcement-bar-pro-premium' ), true );
		}

		if ( $opt['email'] ) {
			$out[] = array( __( 'E-mail sign-up', 'announcement-bar-pro-premium' ), true );
		}

		if ( 'all' !== $m['page_mode'] || '[]' !== $m['roles'] || '[]' !== $m['categories'] || '[]' !== $m['products'] || 'all' !== $opt['countries_mode'] || ( new Features() )->has_cart_rules( $opt ) || ( new Features() )->has_client_rules( $opt ) ) {
			$out[] = array( __( 'Targeted', 'announcement-bar-pro-premium' ), true );
		}

		if ( 'none' !== $opt['trigger'] ) {
			$out[] = array( __( 'Trigger', 'announcement-bar-pro-premium' ), true );
		}

		return $out;
	}

	/**
	 * Placement names.
	 *
	 * @return array
	 */
	public static function placements() {
		return array(
			''         => __( 'Main bar (settings)', 'announcement-bar-pro-premium' ),
			'top'      => __( 'Top bar', 'announcement-bar-pro-premium' ),
			'bottom'   => __( 'Bottom bar', 'announcement-bar-pro-premium' ),
			'cart'     => __( 'Cart page box', 'announcement-bar-pro-premium' ),
			'product'  => __( 'Product page box (under the price)', 'announcement-bar-pro-premium' ),
			'minicart' => __( 'Mini-cart box', 'announcement-bar-pro-premium' ),
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * Message form
	 * ------------------------------------------------------------------
	 */

	/**
	 * New message filled from a template.
	 *
	 * @param array  $row      Empty message.
	 * @param string $template Template key.
	 * @return array
	 */
	public static function blank( $row, $template ) {
		$fx   = new Features();
		$tpls = $fx->templates();

		if ( '' === $template || ! isset( $tpls[ $template ] ) ) {
			return $row;
		}

		$t   = $tpls[ $template ];
		$opt = $fx->defaults();

		$row['icon']       = $t['icon'];
		$row['bg_color']   = $t['bg'];
		$row['text_color'] = $t['fg'];
		$row['type']       = isset( $t['type'] ) ? $t['type'] : 'standard';
		$row['threshold']  = isset( $t['threshold'] ) ? $t['threshold'] : '';
		$row['countdown']  = ! empty( $t['countdown'] ) ? 1 : 0;
		$row['style']      = isset( $t['style'] ) ? $t['style'] : '';
		$row['coupon']     = isset( $t['coupon'] ) ? $t['coupon'] : '';
		$row['placement']  = isset( $t['placement'] ) ? $t['placement'] : '';
		$row['weekdays']   = isset( $t['weekdays'] ) ? implode( '', $t['weekdays'] ) : '';

		if ( ! empty( $t['end_days'] ) ) {
			$row['date_end'] = wp_date( 'Y-m-d 23:59:00', time() + (int) $t['end_days'] * DAY_IN_SECONDS );
		}

		foreach ( array( 'recur', 'recur_time', 'recur_skip', 'trigger', 'email' ) as $key ) {
			if ( isset( $t[ $key ] ) ) {
				$opt[ $key ] = $t[ $key ];
			}
		}

		if ( isset( $t['tiers'] ) ) {
			$opt['tiers'] = $t['tiers'];
		}

		$row['options'] = $fx->encode( $opt );

		foreach ( Settings::locales() as $locale ) {
			$lang = substr( $locale, 0, 2 );
			$pick = static function ( $texts ) use ( $lang ) {
				return isset( $texts[ $lang ] ) ? $texts[ $lang ] : $texts['en'];
			};
			$x    = array();

			if ( isset( $t['tier_label'] ) ) {
				$x['tier_label'] = array_map( $pick, $t['tier_label'] );
			}

			$row['texts'][ $locale ] = array(
				'text'         => $pick( $t['text'] ),
				'success_text' => isset( $t['success_text'] ) ? $pick( $t['success_text'] ) : '',
				'link_text'    => isset( $t['link_text'] ) ? $pick( $t['link_text'] ) : '',
				'extra'        => wp_json_encode( $x ),
			);
		}

		return $row;
	}

	/**
	 * Reward tiers type.
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public static function types( $types ) {
		if ( Commerce::active() ) {
			$types          = (array) $types;
			$types['tiers'] = __( 'Reward tiers (several goals)', 'announcement-bar-pro-premium' );
		}

		return $types;
	}

	/**
	 * Gradient and glass styles of a message.
	 *
	 * @param array $styles value => array( name, description ).
	 * @return array
	 */
	public static function styles( $styles ) {
		$styles             = (array) $styles;
		$styles['gradient'] = array( __( 'Gradient', 'announcement-bar-pro-premium' ), __( 'Colour blend', 'announcement-bar-pro-premium' ) );
		$styles['glass']    = array( __( 'Glass', 'announcement-bar-pro-premium' ), __( 'See-through, blurred', 'announcement-bar-pro-premium' ) );

		return $styles;
	}

	/**
	 * Per-language premium texts: tier labels, variant B, sign-up form texts.
	 *
	 * @param Admin_Form $form   Form.
	 * @param string     $locale Locale.
	 * @param array      $t      Stored texts of that language.
	 */
	public static function form_texts( $form, $locale, $t ) {
		$t     = is_array( $t ) ? $t : array();
		$v     = static function ( $field ) use ( $t ) {
			return isset( $t[ $field ] ) ? (string) $t[ $field ] : '';
		};
		$extra = ( new Features() )->extra_of( $v( 'extra' ) );
		$n     = static function ( $field ) use ( $locale ) {
			return Admin_Form::text_name( $locale, $field );
		};
		$k     = static function ( $field ) use ( $locale ) {
			return Admin_Form::text_key( $locale, $field );
		};

			echo '<div data-show="tiers">';
			$form->group( __( 'Reward tiers — labels and own texts', 'announcement-bar-pro-premium' ), true );
			echo '<p class="abp-help">' . esc_html__( 'Label of each tier (shown under the bar and for {goal}); optional own text while that tier is next.', 'announcement-bar-pro-premium' ) . '</p><div class="abp-tier-texts">';
		for ( $j = 0; $j < Features::MAX_TIERS; $j++ ) {
			/* translators: %d: tier number */
			$label = sprintf( __( 'Tier %d label', 'announcement-bar-pro-premium' ), $j + 1 );
			/* translators: %d: tier number */
			$text = sprintf( __( 'Tier %d own text', 'announcement-bar-pro-premium' ), $j + 1 );
			echo '<div class="abp-row2" data-tier="' . (int) $j . '">';
			$form->input( $n( 'tier_label' ) . '[' . $j . ']', $label, isset( $extra['tier_label'][ $j ] ) ? $extra['tier_label'][ $j ] : '', array( 'key' => $k( 'tier_label' ) ) );
			$form->input( $n( 'tier_text' ) . '[' . $j . ']', $text, isset( $extra['tier_text'][ $j ] ) ? $extra['tier_text'][ $j ] : '', array( 'key' => $k( 'tier_text' ) ) );
			echo '</div>';
		}
			echo '</div></fieldset></div>';

			echo '<div data-show="ab">';
			$form->group( __( 'Variant B (A/B test)', 'announcement-bar-pro-premium' ) );
			$form->textarea( $n( 'text_b' ), __( 'Text B', 'announcement-bar-pro-premium' ), $v( 'text_b' ), array( 'key' => $k( 'text_b' ) ) );
			$form->textarea(
				$n( 'mobile_text_b' ),
				__( 'Shorter text B for phones (optional)', 'announcement-bar-pro-premium' ),
				$v( 'mobile_text_b' ),
				array(
					'key'  => $k( 'mobile_text_b' ),
					'rows' => 1,
				)
			);
			$form->input( $n( 'link_text_b' ), __( 'Button text B (optional)', 'announcement-bar-pro-premium' ), $v( 'link_text_b' ), array( 'key' => $k( 'link_text_b' ) ) );
			echo '</fieldset></div>';

			echo '<div data-show="email">';
			$form->group( __( 'E-mail sign-up texts (empty = default text)', 'announcement-bar-pro-premium' ) );
			$defaults = Pro_Render::email_defaults();
			echo '<div class="abp-row2">';
			$form->input( $n( 'email_placeholder' ), __( 'Field placeholder', 'announcement-bar-pro-premium' ), $extra['email_placeholder'], array( 'attrs' => ' placeholder="' . esc_attr( $defaults['placeholder'] ) . '"' ) );
			$form->input( $n( 'email_button' ), __( 'Button', 'announcement-bar-pro-premium' ), $extra['email_button'], array( 'attrs' => ' placeholder="' . esc_attr( $defaults['button'] ) . '"' ) );
			echo '</div>';
			$form->input(
				$n( 'email_consent' ),
				__( 'Consent text (stored with each sign-up as proof)', 'announcement-bar-pro-premium' ),
				$extra['email_consent'],
				array(
					'attrs' => ' placeholder="' . esc_attr( $defaults['consent'] ) . '"',
					'class' => 'large-text',
				)
			);
			$form->input(
				$n( 'email_success' ),
				__( 'Text after sending', 'announcement-bar-pro-premium' ),
				$extra['email_success'],
				array(
					'attrs' => ' placeholder="' . esc_attr( $defaults['success'] ) . '"',
					'class' => 'large-text',
				)
			);
			echo '</fieldset></div>';
	}

	/**
	 * Background image and font.
	 *
	 * @param Admin_Form $form Form.
	 */
	public static function form_look( $form ) {
		$o = self::opt( $form );

		$form->group( __( 'Background image and font', 'announcement-bar-pro-premium' ) );
		$image = (int) $o['image'];
		$thumb = $image ? wp_get_attachment_image_url( $image, 'thumbnail' ) : '';
		echo '<div class="abp-field"><span class="abp-label" id="abp-image-label">' . esc_html__( 'Background image', 'announcement-bar-pro-premium' ) . '</span><div class="abp-media" aria-labelledby="abp-image-label"><input type="hidden" name="opt[image]" id="abp-image" value="' . (int) $image . '"><img id="abp-image-thumb" alt="" src="' . esc_url( (string) $thumb ) . '"' . ( $thumb ? '' : ' hidden' ) . '>'
			. '<button type="button" class="button" id="abp-image-pick">' . esc_html__( 'Choose image', 'announcement-bar-pro-premium' ) . '</button> <button type="button" class="button-link" id="abp-image-clear"' . ( $image ? '' : ' hidden' ) . '>' . esc_html__( 'Remove image', 'announcement-bar-pro-premium' ) . '</button></div>';
		$form->error( 'opt_image', 'abp-image' );
		echo '</div><div class="abp-row2">';
		$form->input(
			'opt[overlay]',
			__( 'Colour over the image (%)', 'announcement-bar-pro-premium' ),
			$o['overlay'],
			array(
				'type'  => 'number',
				'class' => 'small-text',
				'key'   => 'opt_overlay',
				'attrs' => ' min="0" max="90" step="5"',
				'help'  => __( 'Keeps the text readable: 55 % or more for most photos.', 'announcement-bar-pro-premium' ),
			)
		);
		$form->select( 'opt[font]', __( 'Font', 'announcement-bar-pro-premium' ), $o['font'], self::fonts(), array( 'key' => 'opt_font' ) );
		echo '</div></fieldset>';
	}

	/**
	 * Font choices.
	 *
	 * @return array
	 */
	public static function fonts() {
		return array(
			''          => __( 'Theme font', 'announcement-bar-pro-premium' ),
			'system'    => __( 'System UI', 'announcement-bar-pro-premium' ),
			'humanist'  => __( 'Humanist sans', 'announcement-bar-pro-premium' ),
			'geometric' => __( 'Geometric sans', 'announcement-bar-pro-premium' ),
			'serif'     => __( 'Serif', 'announcement-bar-pro-premium' ),
			'mono'      => __( 'Monospace', 'announcement-bar-pro-premium' ),
			'rounded'   => __( 'Rounded', 'announcement-bar-pro-premium' ),
		);
	}

	/**
	 * Tier amounts and free-shipping amounts by country, state or currency.
	 *
	 * @param Admin_Form $form Form.
	 */
	public static function form_goal( $form ) {
		if ( ! Commerce::active() ) {
			return;
		}

		$o = self::opt( $form );

		echo '<div data-show="tiers">';
		$form->group( __( 'Tier amounts', 'announcement-bar-pro-premium' ), true );
		echo '<p class="abp-help">' . esc_html__( '2 to 5 rising amounts; labels are set per language under Message.', 'announcement-bar-pro-premium' ) . '</p><div class="abp-tiers">';
		for ( $j = 0; $j < Features::MAX_TIERS; $j++ ) {
			$value = isset( $o['tiers'][ $j ] ) ? rtrim( rtrim( $o['tiers'][ $j ], '0' ), '.' ) : '';
			/* translators: %d: tier number */
			$form->input( 'opt[tiers][' . $j . ']', sprintf( __( 'Tier %d', 'announcement-bar-pro-premium' ), $j + 1 ), $value, array( 'class' => 'small-text', 'key' => 'opt_tiers', 'attrs' => ' inputmode="decimal" data-tier-amount="' . (int) $j . '"' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		}
		echo '</div></fieldset></div>';

		echo '<div data-show="fs">';
		$form->group( __( 'Amounts by country, state or currency', 'announcement-bar-pro-premium' ), true );
		echo '<p class="abp-help">' . esc_html__( 'Optional. A state amount wins over a country amount, which wins over a currency amount; otherwise the amount above is used.', 'announcement-bar-pro-premium' ) . '</p>';
		$countries = WC()->countries->get_countries();
		$rows      = $o['fs_zones'];
		$rows[]    = array(
			'country' => '',
			'state'   => '',
			'amount'  => '',
		);
		echo '<table class="abp-zones"><thead><tr><th scope="col">' . esc_html__( 'Country', 'announcement-bar-pro-premium' ) . '</th><th scope="col">' . esc_html__( 'State code (optional)', 'announcement-bar-pro-premium' ) . '</th><th scope="col">' . esc_html__( 'Amount', 'announcement-bar-pro-premium' ) . '</th></tr></thead><tbody id="abp-zone-rows">';
		foreach ( $rows as $j => $r ) {
			echo '<tr><td><select name="opt[fs_zones][' . (int) $j . '][country]" aria-label="' . esc_attr__( 'Country', 'announcement-bar-pro-premium' ) . '"><option value="">—</option>';
			foreach ( $countries as $code => $name ) {
				echo '<option value="' . esc_attr( $code ) . '"' . selected( $r['country'], $code, false ) . '>' . esc_html( $name ) . '</option>';
			}
			echo '</select></td><td><input type="text" class="small-text" name="opt[fs_zones][' . (int) $j . '][state]" value="' . esc_attr( $r['state'] ) . '" aria-label="' . esc_attr__( 'State code (optional)', 'announcement-bar-pro-premium' ) . '"></td><td><input type="text" class="small-text" inputmode="decimal" name="opt[fs_zones][' . (int) $j . '][amount]" value="' . esc_attr( '' !== $r['amount'] ? rtrim( rtrim( $r['amount'], '0' ), '.' ) : '' ) . '" aria-label="' . esc_attr__( 'Amount', 'announcement-bar-pro-premium' ) . '"></td></tr>';
		}
		echo '</tbody></table><button type="button" class="button" id="abp-zone-add">' . esc_html__( 'Add row', 'announcement-bar-pro-premium' ) . '</button>';
		$form->error( 'opt_fs_zones', 'abp-zones' );

		$codes = array_unique( array_merge( array( get_woocommerce_currency() ), array_keys( $o['fs_currencies'] ) ) );
		echo '<div class="abp-row2 abp-currencies">';
		foreach ( $codes as $code ) {
			/* translators: %s: currency code */
			$form->input( 'opt[fs_currencies][' . $code . ']', sprintf( __( 'Fixed amount in %s', 'announcement-bar-pro-premium' ), $code ), isset( $o['fs_currencies'][ $code ] ) ? rtrim( rtrim( $o['fs_currencies'][ $code ], '0' ), '.' ) : '', array( 'class' => 'small-text', 'key' => 'opt_fs_currencies' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		}
		echo '</div><p class="abp-help">' . esc_html__( 'Currency amounts are for multi-currency shops: the amount is used as it is, without conversion.', 'announcement-bar-pro-premium' ) . '</p></fieldset></div>';
	}

	/**
	 * Recurring countdown.
	 *
	 * @param Admin_Form $form Form.
	 * @param array      $days Day names (1 = Monday).
	 */
	public static function form_schedule( $form, $days ) {
		$o = self::opt( $form );

		$form->group( __( 'Recurring countdown (daily / weekly cut-off)', 'announcement-bar-pro-premium' ) );
		echo '<div class="abp-row2">';
		$form->select(
			'opt[recur]',
			__( 'Repeat', 'announcement-bar-pro-premium' ),
			$o['recur'],
			array(
				''       => __( 'No — count down to the end date', 'announcement-bar-pro-premium' ),
				'daily'  => __( 'Every day', 'announcement-bar-pro-premium' ),
				'weekly' => __( 'Once a week', 'announcement-bar-pro-premium' ),
			),
			array( 'key' => 'opt_recur' )
		);
		$form->input(
			'opt[recur_time]',
			__( 'Cut-off time', 'announcement-bar-pro-premium' ),
			$o['recur_time'],
			array(
				'type'  => 'time',
				'class' => '',
				'key'   => 'opt_recur',
			)
		);
		echo '</div><div data-show="recur-weekly">';
		$form->select( 'opt[recur_day]', __( 'Day', 'announcement-bar-pro-premium' ), (string) $o['recur_day'], $days );
		echo '</div><fieldset class="abp-field" data-show="recur-daily"><legend>' . esc_html__( 'Skip these days', 'announcement-bar-pro-premium' ) . '</legend><div class="abp-days">';
		foreach ( $days as $d => $label ) {
			echo '<label class="abp-day"><input type="checkbox" name="opt[recur_skip][]" value="' . (int) $d . '"' . checked( false !== strpos( (string) $o['recur_skip'], (string) $d ), true, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</div></fieldset></fieldset>';
	}

	/**
	 * Placement, priority, visitors, pages, campaign, trigger, products, countries and cart.
	 *
	 * @param Admin_Form $form Form.
	 */
	public static function form_audience( $form ) {
		$m  = $form->message();
		$o  = self::opt( $form );
		$wc = Commerce::active();

		$places = array(
			''       => __( 'Main bar (position from the settings)', 'announcement-bar-pro-premium' ),
			'top'    => __( 'Top bar', 'announcement-bar-pro-premium' ),
			'bottom' => __( 'Bottom bar', 'announcement-bar-pro-premium' ),
		);
		if ( $wc ) {
			$places['cart']     = __( 'Box on the cart page', 'announcement-bar-pro-premium' );
			$places['product']  = __( 'Box under the product price', 'announcement-bar-pro-premium' );
			$places['minicart'] = __( 'Box in the mini-cart', 'announcement-bar-pro-premium' );
		}

		$form->group( __( 'Placement and priority', 'announcement-bar-pro-premium' ) );
		echo '<div class="abp-row2">';
		$form->select( 'placement', __( 'Placement', 'announcement-bar-pro-premium' ), $m['placement'], $places, array( 'help' => __( 'Boxes appear in the cart (classic page and cart block), under the price of the product page and in the classic mini-cart.', 'announcement-bar-pro-premium' ) ) );
		$form->input(
			'priority',
			__( 'Priority (0–100)', 'announcement-bar-pro-premium' ),
			$m['priority'],
			array(
				'type'  => 'number',
				'class' => 'small-text',
				'attrs' => ' min="0" max="100"',
				'help'  => __( 'Higher priority comes first; with "most messages at once" set, lower ones are left out.', 'announcement-bar-pro-premium' ),
			)
		);
		echo '</div></fieldset>';

		$form->group( __( 'Visitors and pages', 'announcement-bar-pro-premium' ) );
		$roles  = json_decode( (string) $m['roles'], true );
		$roles  = is_array( $roles ) ? $roles : array();
		$choice = array_merge( array( 'guest' => __( 'Not logged in', 'announcement-bar-pro-premium' ) ), wp_roles()->get_names() );
		echo '<fieldset class="abp-field"><legend>' . esc_html__( 'Show to (none ticked = everyone)', 'announcement-bar-pro-premium' ) . '</legend><div class="abp-checks">';
		foreach ( $choice as $key => $label ) {
			echo '<label><input type="checkbox" name="roles[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $roles, true ), true, false ) . '> ' . esc_html( translate_user_role( $label ) ) . '</label>';
		}
		echo '</div></fieldset>';
		$form->select(
			'page_mode',
			__( 'Pages', 'announcement-bar-pro-premium' ),
			$m['page_mode'],
			array(
				'all'     => __( 'Every page', 'announcement-bar-pro-premium' ),
				'home'    => __( 'Front page only', 'announcement-bar-pro-premium' ),
				'include' => __( 'Only these pages', 'announcement-bar-pro-premium' ),
				'exclude' => __( 'All pages except these', 'announcement-bar-pro-premium' ),
			)
		);
		echo '<div data-show="pages">';
		$form->textarea(
			'page_rules',
			__( 'Page rules (one per line)', 'announcement-bar-pro-premium' ),
			$m['page_rules'],
			array(
				'rows' => 3,
				/* translators: %s: list of page types */
				'help' => sprintf( __( 'Page types: %s — or paths such as /sale/ (exact) and /blog/* (everything below).', 'announcement-bar-pro-premium' ), implode( ', ', self::PAGE_TYPES ) ),
			)
		);
		echo '</div>';
		$form->input(
			'opt[referrer]',
			__( 'Visitors coming from (sites)', 'announcement-bar-pro-premium' ),
			$o['referrer'],
			array(
				'key'  => 'opt_referrer',
				'help' => __( 'For example facebook.com, instagram.com — separated by commas.', 'announcement-bar-pro-premium' ),
			)
		);
		echo '<div class="abp-row3">';
		$form->input( 'opt[utm_source]', __( 'utm_source', 'announcement-bar-pro-premium' ), $o['utm_source'], array( 'key' => 'opt_utm_source', 'class' => '' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		$form->input( 'opt[utm_medium]', __( 'utm_medium', 'announcement-bar-pro-premium' ), $o['utm_medium'], array( 'key' => 'opt_utm_medium', 'class' => '' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		$form->input( 'opt[utm_campaign]', __( 'utm_campaign', 'announcement-bar-pro-premium' ), $o['utm_campaign'], array( 'key' => 'opt_utm_campaign', 'class' => '' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		echo '</div><p class="abp-help">' . esc_html__( 'Campaign values of the landing page, kept for the visit (decided in the browser).', 'announcement-bar-pro-premium' ) . '</p>';
		$form->select(
			'opt[visitor]',
			__( 'New or returning visitors', 'announcement-bar-pro-premium' ),
			$o['visitor'],
			array(
				'all'       => __( 'Everyone', 'announcement-bar-pro-premium' ),
				'new'       => __( 'First visit only', 'announcement-bar-pro-premium' ),
				'returning' => __( 'Returning visitors only', 'announcement-bar-pro-premium' ),
			),
			array( 'key' => 'opt_visitor' )
		);
		echo '<div class="abp-row2">';
		$form->select(
			'opt[trigger]',
			__( 'Show', 'announcement-bar-pro-premium' ),
			$o['trigger'],
			array(
				'none'   => __( 'At once', 'announcement-bar-pro-premium' ),
				'delay'  => __( 'After a delay (seconds)', 'announcement-bar-pro-premium' ),
				'scroll' => __( 'After scrolling (%)', 'announcement-bar-pro-premium' ),
				'exit'   => __( 'When the visitor is about to leave (computers)', 'announcement-bar-pro-premium' ),
			),
			array( 'key' => 'opt_trigger' )
		);
		echo '<div data-show="trigger-value">';
		$form->input(
			'opt[trigger_value]',
			__( 'Seconds / scroll %', 'announcement-bar-pro-premium' ),
			$o['trigger_value'] ? $o['trigger_value'] : '',
			array(
				'type'  => 'number',
				'class' => 'small-text',
				'key'   => 'opt_trigger',
			)
		);
		echo '</div></div></fieldset>';

		if ( $wc ) {
			self::commerce_audience( $form );
		}
	}

	/**
	 * WooCommerce parts of the audience: products / categories, countries, cart conditions.
	 *
	 * @param Admin_Form $form Form.
	 */
	private static function commerce_audience( Admin_Form $form ) {
		$m = $form->message();
		$o = self::opt( $form );

		$form->group( __( 'Products, countries and cart', 'announcement-bar-pro-premium' ), true );

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

		echo '<p class="abp-help">' . esc_html__( 'With products or categories chosen, the message shows only on those product pages and category pages.', 'announcement-bar-pro-premium' ) . '</p><div class="abp-row2">';
		self::products_field( $form, 'products[]', 'abp-products', __( 'Product pages', 'announcement-bar-pro-premium' ), $products, 'products' );
		self::categories_field( $form, 'categories[]', 'abp-categories', __( 'Category pages', 'announcement-bar-pro-premium' ), $cats, $terms, 'categories' );
		echo '</div>';

		$countries = WC()->countries->get_countries();
		echo '<div class="abp-row2">';
		$form->select(
			'opt[countries_mode]',
			__( 'Countries', 'announcement-bar-pro-premium' ),
			$o['countries_mode'],
			array(
				'all'     => __( 'Every country', 'announcement-bar-pro-premium' ),
				'include' => __( 'Only these countries', 'announcement-bar-pro-premium' ),
				'exclude' => __( 'All except these countries', 'announcement-bar-pro-premium' ),
			),
			array( 'key' => 'opt_countries' )
		);
		echo '<div class="abp-field" data-show="countries"><label for="abp-countries">' . esc_html__( 'Choose countries', 'announcement-bar-pro-premium' ) . '</label><select id="abp-countries" name="opt[countries][]" multiple class="wc-enhanced-select abp-multi" data-placeholder="' . esc_attr__( 'Search a country…', 'announcement-bar-pro-premium' ) . '">';
		foreach ( $countries as $code => $name ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( in_array( $code, $o['countries'], true ), true, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select><p class="abp-help">' . esc_html__( 'The visitor\'s country comes from the WooCommerce customer address (or WooCommerce\'s own geolocation setting). No outside service is asked.', 'announcement-bar-pro-premium' ) . '</p></div></div>';

		echo '<h3 class="abp-sub">' . esc_html__( 'Cart conditions (every set condition must hold)', 'announcement-bar-pro-premium' ) . '</h3><div class="abp-row4">';
		foreach ( array(
			'cart_subtotal_min' => __( 'Subtotal from', 'announcement-bar-pro-premium' ),
			'cart_subtotal_max' => __( 'Subtotal up to', 'announcement-bar-pro-premium' ),
			'cart_qty_min'      => __( 'Items from', 'announcement-bar-pro-premium' ),
			'cart_qty_max'      => __( 'Items up to', 'announcement-bar-pro-premium' ),
		) as $key => $label ) {
			$value = $o[ $key ];
			if ( '' !== $value && false !== strpos( $value, '.' ) ) {
				$value = rtrim( rtrim( $value, '0' ), '.' );
			}
			$form->input( 'opt[' . $key . ']', $label, $value, array( 'class' => 'small-text', 'key' => 'opt_' . $key, 'attrs' => ' inputmode="decimal"' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		}
		echo '</div><div class="abp-row2">';
		self::products_field( $form, 'opt[cart_products][]', 'abp-cart-products', __( 'Cart contains one of these products', 'announcement-bar-pro-premium' ), $o['cart_products'], 'opt_cart_products' );
		self::categories_field( $form, 'opt[cart_categories][]', 'abp-cart-categories', __( 'Cart contains a product of these categories', 'announcement-bar-pro-premium' ), $o['cart_categories'], $terms, 'opt_cart_categories' );
		echo '</div></fieldset>';
	}

	/**
	 * WooCommerce product search field.
	 *
	 * @param Admin_Form $form Form.
	 * @param string     $name  Field name.
	 * @param string     $id    Element ID.
	 * @param string     $label Label.
	 * @param array      $ids   Chosen IDs.
	 * @param string     $key   Error key.
	 */
	private static function products_field( Admin_Form $form, $name, $id, $label, array $ids, $key ) {
		echo '<div class="abp-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" multiple class="wc-product-search abp-multi" data-placeholder="' . esc_attr__( 'Search a product…', 'announcement-bar-pro-premium' ) . '" data-action="woocommerce_json_search_products_and_variations">';
		foreach ( $ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( $product ) {
				echo '<option value="' . (int) $pid . '" selected>' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
			}
		}
		echo '</select>';
		$form->error( $key, $id );
		echo '</div>';
	}

	/**
	 * Product category field.
	 *
	 * @param Admin_Form $form Form.
	 * @param string     $name  Field name.
	 * @param string     $id    Element ID.
	 * @param string     $label Label.
	 * @param array      $ids   Chosen IDs.
	 * @param array      $terms Categories.
	 * @param string     $key   Error key.
	 */
	private static function categories_field( Admin_Form $form, $name, $id, $label, array $ids, array $terms, $key ) {
		echo '<div class="abp-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" multiple class="wc-enhanced-select abp-multi" data-placeholder="' . esc_attr__( 'Search a category…', 'announcement-bar-pro-premium' ) . '">';
		foreach ( $terms as $term ) {
			echo '<option value="' . (int) $term->term_id . '"' . selected( in_array( (int) $term->term_id, $ids, true ), true, false ) . '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select>';
		$form->error( $key, $id );
		echo '</div>';
	}

	/**
	 * Coupon, e-mail sign-up, A/B test.
	 *
	 * @param Admin_Form $form Form.
	 */
	public static function extras_card( $form ) {
		$m  = $form->message();
		$o  = self::opt( $form );
		$wc = Commerce::active();

		$form->card( __( 'Coupon, sign-up and A/B test', 'announcement-bar-pro-premium' ), 'ticket' );

		$form->group( __( 'Coupon', 'announcement-bar-pro-premium' ) );
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
			$choice  = array( '0' => __( '— none —', 'announcement-bar-pro-premium' ) );
			foreach ( $coupons as $cid ) {
				$choice[ (string) $cid ] = strtoupper( get_the_title( $cid ) );
			}
			if ( $o['coupon_id'] && ! isset( $choice[ (string) $o['coupon_id'] ] ) ) {
				$choice[ (string) $o['coupon_id'] ] = get_the_title( $o['coupon_id'] );
			}
			$form->select(
				'opt[coupon_id]',
				__( 'WooCommerce coupon', 'announcement-bar-pro-premium' ),
				(string) $o['coupon_id'],
				$choice,
				array(
					'key'   => 'opt_coupon_id',
					'help'  => __( 'Shows the code with a copy button. The message hides itself when the coupon expires or is used up — the same rules WooCommerce uses.', 'announcement-bar-pro-premium' ),
					'attrs' => $form->commerce() ? '' : ' disabled',
				)
			);
		}
		$form->input(
			'coupon',
			__( 'Or a fixed code', 'announcement-bar-pro-premium' ),
			$m['coupon'],
			array(
				'class' => '',
				'help'  => __( 'Shown as it is (not checked against the shop).', 'announcement-bar-pro-premium' ),
				'attrs' => ' maxlength="32" spellcheck="false"',
			)
		);
		echo '</div></fieldset>';

		$form->group( __( 'E-mail sign-up (GDPR, double opt-in)', 'announcement-bar-pro-premium' ) );
		$form->checkbox( 'opt[email]', __( 'Show an e-mail sign-up form in this message', 'announcement-bar-pro-premium' ), (int) $o['email'], __( 'With a consent box, a link to the privacy page and a confirmation e-mail. Texts per language are under Message.', 'announcement-bar-pro-premium' ) );
		echo '</fieldset>';

		$form->group( __( 'A/B test', 'announcement-bar-pro-premium' ) );
		$form->checkbox( 'ab_test', __( 'Test a second text (variant B)', 'announcement-bar-pro-premium' ), (int) $m['ab_test'], __( 'Each browser keeps one variant. Views, clicks and CTR per variant appear in the message list.', 'announcement-bar-pro-premium' ) );
		echo '</fieldset>';

		$form->end_card();
	}

	/**
	 * A / B switch of the preview.
	 *
	 * @param Admin_Form $form Form.
	 */
	public static function preview_tools( $form ) {
		unset( $form );
		echo '<div class="abp-seg" role="group" aria-label="' . esc_attr__( 'Variant', 'announcement-bar-pro-premium' ) . '" data-show="ab"><button type="button" class="active" data-pv-variant="a" aria-pressed="true">A</button><button type="button" data-pv-variant="b" aria-pressed="false">B</button></div>';
	}

	/**
	 * Preview of variant B.
	 *
	 * @param array            $row     Row of the preview.
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public static function preview_row( $row, $request ) {
		if ( is_array( $row ) && 'b' === $request->get_param( 'pv_variant' ) && ! empty( $row['ab_test'] ) && '' !== trim( (string) $row['text_b'] ) ) {
			$row['text']        = $row['text_b'];
			$row['mobile_text'] = $row['mobile_text_b'];
			$row['link_text']   = '' !== trim( (string) $row['link_text_b'] ) ? $row['link_text_b'] : $row['link_text'];
			$row['ab_test']     = 0;
		}

		return $row;
	}

	/**
	 * Validates the premium fields of the posted message form (columns, options, texts).
	 *
	 * @param array $result   array( columns, texts, errors ) of the free plugin.
	 * @param array $p        Posted values (unslashed).
	 * @param array $base     Stored message (or the empty one).
	 * @param bool  $preview  Preview (no existence checks).
	 * @return array
	 */
	public static function from_post( $result, $p, $base, $preview ) {
		list( $data, $texts, $err ) = $result;

		$p        = (array) $p;
		$san      = new Sanitizer();
		$fx       = new Features();
		$allow    = (bool) Settings::get()['allow_html'];
		$commerce = Commerce::active() && current_user_can( 'manage_woocommerce' );
		$type     = $data['type'];

		// Options (validated by the shared feature library).
		$posted_opt = Admin::val( $p, 'opt', array() );
		$posted_opt = is_array( $posted_opt ) ? $posted_opt : array();
		$stored_opt = $fx->options( $base['options'] );

		if ( ! $commerce ) {
			foreach ( self::COMMERCE_OPTIONS as $key ) {
				$posted_opt[ $key ] = $stored_opt[ $key ];
			}
		}

		list( $opt, $opt_err ) = $fx->validate( $posted_opt, $preview ? null : Pro_Commerce::lookup(), array_merge( Pro_Commerce::currency_codes(), array_keys( $stored_opt['fs_currencies'] ) ), $type );

		foreach ( $opt_err as $field => $key ) {
			$err[ 'opt_' . $field ] = self::error_text( $key );
		}

		$data['options'] = $fx->encode( $opt );

		// Countdown to a recurring cut-off needs no end date.
		if ( isset( $err['countdown'] ) && '' !== $opt['recur'] ) {
			unset( $err['countdown'] );
		} elseif ( isset( $err['countdown'] ) ) {
			$err['countdown'] = __( 'A countdown needs an end date or a daily / weekly cut-off time.', 'announcement-bar-pro-premium' );
		}

		// Texts per language.
		$index  = $fx->tier_indexes( isset( $posted_opt['tiers'] ) ? $posted_opt['tiers'] : array() );
		$ab     = ! empty( $p['ab_test'] );
		$has_b  = false;
		$posted = Admin::val( $p, 'texts', array() );
		$posted = is_array( $posted ) ? $posted : array();
		$html   = static function ( $value ) use ( $san, $allow ) {
			$value = $allow ? $san->html( $value ) : $san->plain( $value );

			return '' === trim( wp_strip_all_tags( $value ) ) ? '' : $value;
		};
		$button = static function ( $value ) use ( $san ) {
			$value = $san->plain( $value );

			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, Admin::MAX_LINK_TEXT, 'UTF-8' ) : substr( $value, 0, Admin::MAX_LINK_TEXT );
		};
		$length = static function ( $value ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		};

		foreach ( $texts as $locale => $t ) {
			$row    = isset( $posted[ $locale ] ) && is_array( $posted[ $locale ] ) ? $posted[ $locale ] : array();
			$get    = static function ( $field ) use ( $row ) {
				return isset( $row[ $field ] ) && is_string( $row[ $field ] ) ? $row[ $field ] : '';
			};
			$prefix = 'texts.' . $locale . '.';

			foreach ( array( 'text_b', 'mobile_text_b' ) as $field ) {
				$value = $get( $field );

				if ( strlen( $value ) > Admin::MAX_RAW_TEXT || $length( $allow ? $san->html( $value ) : $san->plain( $value ) ) > Admin::MAX_TEXT ) {
					/* translators: %d: number of characters */
					$err[ $prefix . $field ] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro-premium' ), Admin::MAX_TEXT );
				}
			}

			if ( $length( $san->plain( $get( 'link_text_b' ) ) ) > Admin::MAX_LINK_TEXT ) {
				/* translators: %d: number of characters */
				$err[ $prefix . 'link_text_b' ] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro-premium' ), Admin::MAX_LINK_TEXT );
			}

			$extra_err = array();
			$extra     = $fx->extra( $row, 'tiers' === $type ? $index : array(), $extra_err );

			foreach ( $extra_err as $field => $key ) {
				unset( $key );
				/* translators: %d: number of characters */
				$err[ $prefix . $field ] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro-premium' ), in_array( $field, array( 'tier_text', 'email_consent' ), true ) ? Features::MAX_TIER_TEXT : Features::MAX_EXTRA );
			}

			$texts[ $locale ]['text_b']        = $ab ? $html( $get( 'text_b' ) ) : '';
			$texts[ $locale ]['mobile_text_b'] = $ab ? $html( $get( 'mobile_text_b' ) ) : '';
			$texts[ $locale ]['link_text_b']   = $ab ? $button( $get( 'link_text_b' ) ) : '';
			$texts[ $locale ]['extra']         = wp_json_encode( $extra );

			if ( '' !== trim( wp_strip_all_tags( $t['text'] ) ) && 'tiers' === $type && in_array( '', $extra['tier_label'], true ) ) {
				$err[ $prefix . 'tier_label' ] = __( 'Every tier needs a label in each language that has a text.', 'announcement-bar-pro-premium' );
			}

			if ( '' !== $texts[ $locale ]['text_b'] ) {
				$has_b = true;
			}
		}

		if ( $ab && ! $has_b ) {
			$err[ 'texts.' . Settings::locales()[0] . '.text_b' ] = __( 'An A/B test needs a text for variant B.', 'announcement-bar-pro-premium' );
		}

		// Premium columns.
		$priority = trim( (string) Admin::val( $p, 'priority', '0' ) );
		if ( '' !== $priority && ( ! preg_match( '/^\d{1,3}$/', $priority ) || (int) $priority > 100 ) ) {
			/* translators: 1: smallest value, 2: largest value */
			$err['priority'] = sprintf( __( 'Please enter a whole number from %1$d to %2$d.', 'announcement-bar-pro-premium' ), 0, 100 );
		}
		$data['priority']  = max( 0, min( 100, (int) $priority ) );
		$data['placement'] = in_array( Admin::val( $p, 'placement' ), Features::PLACEMENTS, true ) ? Admin::val( $p, 'placement' ) : '';
		$data['ab_test']   = $ab ? 1 : 0;

		$coupon         = trim( (string) Admin::val( $p, 'coupon' ) );
		$data['coupon'] = $san->coupon( $coupon );
		if ( '' !== $coupon && '' === $data['coupon'] ) {
			$err['coupon'] = __( 'Use 1–32 letters, digits, "-" or "_".', 'announcement-bar-pro-premium' );
		}

		$all_roles     = array_merge( array( 'guest' ), array_keys( wp_roles()->roles ) );
		$roles         = Admin::val( $p, 'roles', array() );
		$roles         = is_array( $roles ) ? array_values( array_intersect( array_map( 'strval', $roles ), $all_roles ) ) : array();
		$data['roles'] = wp_json_encode( $roles );

		$mode               = (string) Admin::val( $p, 'page_mode', 'all' );
		$data['page_mode']  = in_array( $mode, array( 'all', 'home', 'include', 'exclude' ), true ) ? $mode : 'all';
		$data['page_rules'] = '';
		if ( in_array( $data['page_mode'], array( 'include', 'exclude' ), true ) ) {
			$rules = self::page_rules( (string) Admin::val( $p, 'page_rules' ) );

			if ( false === $rules ) {
				$err['page_rules'] = __( 'One rule per line: a page type from the list or a path that starts with "/" (a "*" at the end matches everything below it).', 'announcement-bar-pro-premium' );
			} elseif ( ! $rules ) {
				$err['page_rules'] = __( 'Please add at least one page rule.', 'announcement-bar-pro-premium' );
			} else {
				$data['page_rules'] = implode( "\n", $rules );
			}
		}

		if ( $commerce ) {
			foreach ( array(
				'categories' => 'category',
				'products'   => 'product',
			) as $key => $kind ) {
				$ids = $san->id_list( Admin::val( $p, $key, array() ), Features::MAX_IDS );

				if ( $ids && ! $preview && count( call_user_func( Pro_Commerce::lookup(), $kind, $ids ) ) !== count( $ids ) ) {
					$err[ $key ] = __( 'Some of the chosen items do not exist any more.', 'announcement-bar-pro-premium' );
				}

				$data[ $key ] = wp_json_encode( $ids );
			}
		}

		return array( $data, $texts, $err );
	}

	/**
	 * Page rules from the textarea; false when a line is not a page type or a path.
	 *
	 * @param string $value Textarea.
	 * @return array|false
	 */
	private static function page_rules( $value ) {
		$out = array();

		foreach ( preg_split( '/\r?\n/', $value ) as $line ) {
			$line = strtolower( trim( $line ) );

			if ( '' === $line ) {
				continue;
			}

			if ( ! in_array( $line, self::PAGE_TYPES, true ) && ! preg_match( '#^/[a-z0-9/_\-.~%]*\*?$#', $line ) ) {
				return false;
			}

			$out[ $line ] = $line;
		}

		return count( $out ) > 50 ? false : array_values( $out );
	}

	/**
	 * Error text of a feature-library error key.
	 *
	 * @param string $key Error key.
	 * @return string
	 */
	public static function error_text( $key ) {
		$texts = array(
			'error_value'              => __( 'Please choose one of the offered values.', 'announcement-bar-pro-premium' ),
			'error_countries'          => __( 'Please choose countries from the list.', 'announcement-bar-pro-premium' ),
			'error_countries_required' => __( 'Please choose at least one country.', 'announcement-bar-pro-premium' ),
			'error_fs_zones'           => __( 'Each row needs a country from the list, an optional state of that country and an amount above 0.', 'announcement-bar-pro-premium' ),
			'error_fs_zones_duplicate' => __( 'Each country / state may appear only once.', 'announcement-bar-pro-premium' ),
			'error_fs_currencies'      => __( 'Amounts above 0, only for currencies of the shop.', 'announcement-bar-pro-premium' ),
			'error_coupon_id'          => __( 'Please choose an existing coupon.', 'announcement-bar-pro-premium' ),
			'error_tiers_order'        => __( 'Tier amounts must rise from row to row.', 'announcement-bar-pro-premium' ),
			'error_tiers_count'        => __( 'Reward tiers need 2 to 5 amounts.', 'announcement-bar-pro-premium' ),
			'error_amount'             => __( 'Please enter an amount such as 49.99.', 'announcement-bar-pro-premium' ),
			'error_quantity'           => __( 'Please enter a whole number.', 'announcement-bar-pro-premium' ),
			'error_min_max'            => __( 'The maximum must not be smaller than the minimum.', 'announcement-bar-pro-premium' ),
			'error_cart_products'      => __( 'Some of the chosen products do not exist any more.', 'announcement-bar-pro-premium' ),
			'error_cart_categories'    => __( 'Some of the chosen categories do not exist any more.', 'announcement-bar-pro-premium' ),
			'error_utm'                => __( 'Use values such as newsletter, google or spring-sale, separated by commas (at most 20).', 'announcement-bar-pro-premium' ),
			'error_referrer'           => __( 'Use site names such as facebook.com, separated by commas (at most 20).', 'announcement-bar-pro-premium' ),
			'error_trigger_delay'      => __( 'Delay: 1 to 600 seconds.', 'announcement-bar-pro-premium' ),
			'error_trigger_scroll'     => __( 'Scroll depth: 1 to 100 %.', 'announcement-bar-pro-premium' ),
			'error_recur_time'         => __( 'Please enter the cut-off time as HH:MM.', 'announcement-bar-pro-premium' ),
			'error_recur_skip'         => __( 'At least one day must stay active.', 'announcement-bar-pro-premium' ),
			'error_image'              => __( 'Please choose an image from the media library.', 'announcement-bar-pro-premium' ),
			'error_overlay'            => __( 'Overlay: 0 to 90 %.', 'announcement-bar-pro-premium' ),
		);

		return isset( $texts[ $key ] ) ? $texts[ $key ] : $texts['error_value'];
	}

	/**
	 * Templates gallery.
	 */
	public static function page_templates() {
		Admin::guard();

		$fx   = new Features();
		$lang = substr( get_user_locale(), 0, 2 );
		$wc   = Commerce::active();

		echo '<div class="wrap abp-admin">';
		Admin::header( __( 'Templates', 'announcement-bar-pro-premium' ), 'templates' );

		echo '<ul class="abp-templates">';

		foreach ( $fx->templates() as $key => $t ) {
			$name = isset( $t['name'][ $lang ] ) ? $t['name'][ $lang ] : $t['name']['en'];
			$text = isset( $t['text'][ $lang ] ) ? $t['text'][ $lang ] : $t['text']['en'];
			$text = str_replace( array( '{remaining}', '{countdown}', '{goal}' ), array( Commerce::money( 12.5 ), '1d 04:12:30', isset( $t['tier_label'] ) ? $t['tier_label'][0][ isset( $t['tier_label'][0][ $lang ] ) ? $lang : 'en' ] : '' ), $text );
			$need = ! empty( $t['commerce'] ) && ! $wc;

			echo '<li class="abp-tpl"><div class="abp-tpl-bar" style="background:' . esc_attr( $t['bg'] ) . ';color:' . esc_attr( $t['fg'] ) . '">' . Icons::svg( $t['icon'] ) . ' <span>' . esc_html( $text ) . '</span></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
				. '<div class="abp-tpl-body"><h2 class="abp-tpl-name">' . esc_html( $name ) . '</h2>';

			if ( $need ) {
				echo '<p class="abp-small">' . esc_html__( 'Needs WooCommerce.', 'announcement-bar-pro-premium' ) . '</p>';
			} else {
				/* translators: %s: template name */
				echo '<a class="button button-primary" href="' . esc_url( Admin::url( 'edit', array( 'template' => $key ) ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Use template: %s', 'announcement-bar-pro-premium' ), $name ) ) . '">' . esc_html__( 'Use template', 'announcement-bar-pro-premium' ) . '</a>';
			}

			echo '</div></li>';
		}

		echo '</ul></div>';
	}
}
