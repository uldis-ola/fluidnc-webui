<?php
/**
 * Admin: message list (with statistics), message form with live preview, templates, settings, licence and
 * subscribers. Every action: capability check (manage_options; commerce fields also need manage_woocommerce),
 * nonce, sanitising on save and escaping on output.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens and actions.
 */
class Admin {
	const SLUG = 'announcement-bar-pro';
	const CAP  = 'manage_options';

	/** Message columns that need a Pro licence. */
	const PRO_FIELDS = array( 'priority', 'placement', 'coupon', 'ab_test', 'roles', 'page_mode', 'page_rules', 'categories', 'products' );

	/** Message columns that need manage_woocommerce. */
	const COMMERCE_FIELDS = array( 'type', 'threshold', 'progress_bar', 'categories', 'products' );

	/** Options that need manage_woocommerce. */
	const COMMERCE_OPTIONS = array( 'countries_mode', 'countries', 'fs_wc', 'fs_zones', 'fs_currencies', 'coupon_id', 'tiers', 'cart_subtotal_min', 'cart_subtotal_max', 'cart_qty_min', 'cart_qty_max', 'cart_products', 'cart_categories' );

	/** Page-type rules (see Frontend::type_matches()). */
	const PAGE_TYPES = array( 'home', 'blog', 'shop', 'cart', 'checkout', 'account', 'product', 'category', 'page', 'post', 'search', '404' );

	const MAX_TEXT      = 500;
	const MAX_RAW_TEXT  = 4000;
	const MAX_LINK_TEXT = 60;

	/**
	 * Hooks.
	 */
	public static function init() {
		$admin = new self();

		add_action( 'admin_menu', array( $admin, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $admin, 'assets' ) );
		add_action( 'admin_post_abpro_save_message', array( $admin, 'save_message' ) );
		add_action( 'admin_post_abpro_message_action', array( $admin, 'message_action' ) );
		add_action( 'admin_post_abpro_save_settings', array( $admin, 'save_settings' ) );
		add_action( 'admin_post_abpro_licence', array( $admin, 'licence_action' ) );
		add_action( 'admin_post_abpro_subscribers', array( $admin, 'subscribers_action' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_menu_page( __( 'Announcement Bar Pro', 'announcement-bar-pro' ), __( 'Announcement Bar', 'announcement-bar-pro' ), self::CAP, self::SLUG, array( $this, 'page_messages' ), 'dashicons-megaphone', 58 );
		add_submenu_page( self::SLUG, __( 'Messages', 'announcement-bar-pro' ), __( 'Messages', 'announcement-bar-pro' ), self::CAP, self::SLUG, array( $this, 'page_messages' ) );
		add_submenu_page( self::SLUG, __( 'Add message', 'announcement-bar-pro' ), __( 'Add message', 'announcement-bar-pro' ), self::CAP, self::SLUG . '-edit', array( $this, 'page_edit' ) );
		add_submenu_page( self::SLUG, __( 'Templates', 'announcement-bar-pro' ), __( 'Templates', 'announcement-bar-pro' ), self::CAP, self::SLUG . '-templates', array( $this, 'page_templates' ) );
		add_submenu_page( self::SLUG, __( 'Subscribers', 'announcement-bar-pro' ), __( 'Subscribers', 'announcement-bar-pro' ), self::CAP, self::SLUG . '-subscribers', array( $this, 'page_subscribers' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'announcement-bar-pro' ), __( 'Settings', 'announcement-bar-pro' ), self::CAP, self::SLUG . '-settings', array( $this, 'page_settings' ) );
	}

	/**
	 * Current screen is one of ours.
	 *
	 * @param string $hook Screen hook.
	 * @return bool
	 */
	private function ours( $hook ) {
		return false !== strpos( (string) $hook, self::SLUG );
	}

	/**
	 * Styles and scripts of our screens.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ) {
		if ( ! $this->ours( $hook ) ) {
			return;
		}

		wp_enqueue_style( 'abp-admin', ABPRO_URL . 'assets/admin/admin.css', array(), ABPRO_VERSION );
		wp_enqueue_script( 'abp-admin', ABPRO_URL . 'assets/admin/admin.js', array(), ABPRO_VERSION, true );

		$edit = false !== strpos( $hook, self::SLUG . '-edit' );

		if ( $edit ) {
			wp_enqueue_media();

			if ( Commerce::active() && current_user_can( 'manage_woocommerce' ) && wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
				wp_enqueue_script( 'wc-enhanced-select' );
				wp_enqueue_style( 'woocommerce_admin_styles' );
			}
		}

		wp_localize_script(
			'abp-admin',
			'abpAdmin',
			array(
				'preview' => esc_url_raw( rest_url( Endpoints::NS . '/preview' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'states'  => $edit && Commerce::active() ? WC()->countries->get_states() : new \stdClass(),
				'l10n'    => array(
					'confirmDelete' => __( 'Delete this message? This cannot be undone.', 'announcement-bar-pro' ),
					'confirmErase'  => __( 'Delete every entry of this address from the list? This cannot be undone.', 'announcement-bar-pro' ),
					'confirmUseB'   => __( 'Make variant B the main text and end the A/B test?', 'announcement-bar-pro' ),
					'chooseImage'   => __( 'Choose a background image', 'announcement-bar-pro' ),
					'useImage'      => __( 'Use this image', 'announcement-bar-pro' ),
					'notShown'      => __( 'This message would not be shown with the current settings (no text yet, a Pro feature without a licence, or a coupon that cannot be used).', 'announcement-bar-pro' ),
					'previewError'  => __( 'The preview could not be loaded.', 'announcement-bar-pro' ),
					'remove'        => __( 'Remove', 'announcement-bar-pro' ),
					'wholeCountry'  => __( 'Whole country', 'announcement-bar-pro' ),
					'contrast'      => __( 'Contrast', 'announcement-bar-pro' ),
					'contrastOk'    => __( 'readable (WCAG AA)', 'announcement-bar-pro' ),
					'contrastLow'   => __( 'too low for small text — choose stronger colours (at least 4.5:1)', 'announcement-bar-pro' ),
				),
			)
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * Small helpers
	 * ------------------------------------------------------------------
	 */

	/**
	 * Stops when the user may not manage the plugin.
	 */
	private function guard() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage announcements.', 'announcement-bar-pro' ), 403 );
		}
	}

	/**
	 * Admin URL of one of our screens.
	 *
	 * @param string $page Screen suffix ('' = messages).
	 * @param array  $args Query arguments.
	 * @return string
	 */
	public static function url( $page = '', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG . ( '' !== $page ? '-' . $page : '' ) ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Redirects to one of our screens with a notice code.
	 *
	 * @param string $page   Screen suffix.
	 * @param string $notice Notice code.
	 * @param array  $args   More arguments.
	 */
	private function back( $page, $notice, array $args = array() ) {
		wp_safe_redirect( self::url( $page, array_merge( $args, array( 'abpro_notice' => $notice ) ) ) );
		exit;
	}

	/**
	 * Posted value (unslashed) or $fallback.
	 *
	 * @param array  $p        Posted array.
	 * @param string $key      Key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	private static function val( array $p, $key, $fallback = '' ) {
		return isset( $p[ $key ] ) ? $p[ $key ] : $fallback;
	}

	/**
	 * Notice of the last action.
	 */
	private function notice() {
		$code  = isset( $_GET['abpro_notice'] ) ? sanitize_key( wp_unslash( $_GET['abpro_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$texts = array(
			'saved'      => array( 'success', __( 'Saved.', 'announcement-bar-pro' ) ),
			'deleted'    => array( 'success', __( 'Message deleted.', 'announcement-bar-pro' ) ),
			'duplicated' => array( 'success', __( 'Copy created. It is switched off until you turn it on.', 'announcement-bar-pro' ) ),
			'status'     => array( 'success', __( 'Status changed.', 'announcement-bar-pro' ) ),
			'reset'      => array( 'success', __( 'Statistics reset.', 'announcement-bar-pro' ) ),
			'use_b'      => array( 'success', __( 'Variant B is now the main text; the A/B test has ended.', 'announcement-bar-pro' ) ),
			'licence_ok' => array( 'success', __( 'Pro features are unlocked. Thank you!', 'announcement-bar-pro' ) ),
			'licence_no' => array( 'error', __( 'This licence key is not valid. Please check it and try again.', 'announcement-bar-pro' ) ),
			'licence_rm' => array( 'success', __( 'The licence key was removed from this site.', 'announcement-bar-pro' ) ),
			'erased'     => array( 'success', __( 'The address was deleted from the list.', 'announcement-bar-pro' ) ),
			'not_found'  => array( 'warning', __( 'That address is not on the list.', 'announcement-bar-pro' ) ),
			'invalid'    => array( 'error', __( 'Please correct the marked fields.', 'announcement-bar-pro' ) ),
			'failed'     => array( 'error', __( 'That did not work. Please try again.', 'announcement-bar-pro' ) ),
		);

		if ( isset( $texts[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $texts[ $code ][0] ), esc_html( $texts[ $code ][1] ) );
		}
	}

	/**
	 * "Pro" badge.
	 *
	 * @return string
	 */
	private static function pro_badge() {
		return Licence::is_pro() ? '' : ' <span class="abp-pro-badge">' . esc_html__( 'Pro', 'announcement-bar-pro' ) . '</span>';
	}

	/**
	 * Header of our screens (title, Pro state, tabs).
	 *
	 * @param string $title  Title.
	 * @param string $active Active tab.
	 * @param string $action Optional button HTML (escaped).
	 */
	private function header( $title, $active, $action = '' ) {
		$tabs = array(
			''            => __( 'Messages', 'announcement-bar-pro' ),
			'templates'   => __( 'Templates', 'announcement-bar-pro' ),
			'subscribers' => __( 'Subscribers', 'announcement-bar-pro' ),
			'settings'    => __( 'Settings', 'announcement-bar-pro' ),
		);

		echo '<div class="abp-head"><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1> ' . $action . ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $action is built from escaped parts.
		echo Licence::is_pro() ? '<span class="abp-edition abp-edition-pro">' . esc_html__( 'Pro', 'announcement-bar-pro' ) . '</span>' : '<span class="abp-edition">' . esc_html__( 'Free edition', 'announcement-bar-pro' ) . '</span>';
		echo '</div><nav class="nav-tab-wrapper abp-tabs" aria-label="' . esc_attr__( 'Announcement Bar Pro sections', 'announcement-bar-pro' ) . '">';

		foreach ( $tabs as $page => $label ) {
			printf( '<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>', esc_url( self::url( $page ) ), $page === $active ? ' nav-tab-active' : '', $page === $active ? ' aria-current="page"' : '', esc_html( $label ) );
		}

		echo '</nav>';
		$this->notice();
	}

	/*
	 * ------------------------------------------------------------------
	 * Messages list
	 * ------------------------------------------------------------------
	 */

	/**
	 * Messages screen.
	 */
	public function page_messages() {
		$this->guard();

		$rows = DB::all_messages( get_user_locale() );
		$fx   = new Features();
		$pro  = Licence::is_pro();
		$add  = '<a href="' . esc_url( self::url( 'edit' ) ) . '" class="page-title-action">' . esc_html__( 'Add message', 'announcement-bar-pro' ) . '</a>';

		echo '<div class="wrap abp-admin">';
		$this->header( __( 'Announcement Bar Pro', 'announcement-bar-pro' ), '', $add );

		if ( empty( Settings::get()['status'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The bar is switched off in the settings — no message is shown on the site.', 'announcement-bar-pro' ) . ' <a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Open settings', 'announcement-bar-pro' ) . '</a></p></div>';
		}

		if ( ! $rows ) {
			echo '<div class="abp-card abp-empty"><div class="abp-card-body"><h2>' . esc_html__( 'No messages yet', 'announcement-bar-pro' ) . '</h2><p>' . esc_html__( 'Write your first announcement — a delivery note, a sale with a countdown or a free-shipping progress bar.', 'announcement-bar-pro' ) . '</p><p><a class="button button-primary" href="' . esc_url( self::url( 'edit' ) ) . '">' . esc_html__( 'Add message', 'announcement-bar-pro' ) . '</a> <a class="button" href="' . esc_url( self::url( 'templates' ) ) . '">' . esc_html__( 'Start from a template', 'announcement-bar-pro' ) . '</a></p></div></div></div>';
			return;
		}

		echo '<div class="abp-card"><table class="widefat abp-list"><caption class="screen-reader-text">' . esc_html__( 'Messages', 'announcement-bar-pro' ) . '</caption><thead><tr>'
			. '<th scope="col">' . esc_html__( 'Message', 'announcement-bar-pro' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Status', 'announcement-bar-pro' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Statistics', 'announcement-bar-pro' ) . '</th>'
			. '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'announcement-bar-pro' ) . '</span></th>'
			. '</tr></thead><tbody>';

		foreach ( $rows as $m ) {
			$opt  = $fx->options( $m['options'] );
			$id   = (int) $m['message_id'];
			$text = wp_strip_all_tags( ( new Sanitizer() )->plain( $m['text'] ) );
			$text = '' !== $text ? $text : __( '(no text)', 'announcement-bar-pro' );

			echo '<tr><td class="abp-col-msg"><span class="abp-swatch" style="background:' . esc_attr( ( new Sanitizer() )->color( $m['bg_color'], '#1f2937' ) ) . ';color:' . esc_attr( ( new Sanitizer() )->color( $m['text_color'], '#ffffff' ) ) . '" aria-hidden="true">'
				. ( '' !== $m['icon'] ? Icons::svg( $m['icon'], 'abp-sw-ico' ) : 'A' ) . '</span><div><a class="abp-msg-title" href="' . esc_url( self::url( 'edit', array( 'id' => $id ) ) ) . '">' . esc_html( wp_html_excerpt( $text, 110, '…' ) ) . '</a><div class="abp-badges">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.

			foreach ( $this->badges( $m, $opt ) as $badge ) {
				echo '<span class="abp-badge' . ( $badge[1] ? ' abp-badge-pro' : '' ) . '">' . esc_html( $badge[0] ) . '</span>';
			}

			echo '</div></div></td><td>';
			echo (int) $m['status'] ? '<span class="abp-state abp-state-on">' . esc_html__( 'On', 'announcement-bar-pro' ) . '</span>' : '<span class="abp-state">' . esc_html__( 'Off', 'announcement-bar-pro' ) . '</span>';
			echo $this->window_text( $m ) ? '<div class="abp-small">' . esc_html( $this->window_text( $m ) ) . '</div>' : '';
			echo '</td><td>';

			if ( $pro ) {
				$this->stats( $m, 'a', (int) $m['ab_test'] ? 'A' : '' );

				if ( (int) $m['ab_test'] ) {
					$this->stats( $m, 'b', 'B' );
				}
			} else {
				echo '<span class="abp-small">' . esc_html__( 'Statistics are part of Pro.', 'announcement-bar-pro' ) . '</span>';
			}

			echo '</td><td class="abp-actions">';
			echo '<a class="button button-small" href="' . esc_url( self::url( 'edit', array( 'id' => $id ) ) ) . '">' . esc_html__( 'Edit', 'announcement-bar-pro' ) . '<span class="screen-reader-text"> ' . esc_html( wp_html_excerpt( $text, 40, '…' ) ) . '</span></a> ';
			$this->action_button( $id, 'toggle', (int) $m['status'] ? __( 'Turn off', 'announcement-bar-pro' ) : __( 'Turn on', 'announcement-bar-pro' ) );
			$this->action_button( $id, 'duplicate', __( 'Duplicate', 'announcement-bar-pro' ) );

			if ( $pro && (int) $m['ab_test'] ) {
				$this->action_button( $id, 'use_b', __( 'Use B', 'announcement-bar-pro' ), 'abp-confirm-useb' );
			}

			if ( $pro ) {
				$this->action_button( $id, 'reset', __( 'Reset statistics', 'announcement-bar-pro' ) );
			}

			$this->action_button( $id, 'delete', __( 'Delete', 'announcement-bar-pro' ), 'abp-confirm-delete button-link-delete' );
			echo '</td></tr>';
		}

		echo '</tbody></table></div>';
		echo '<p class="abp-small">' . esc_html__( 'Order: priority (highest first), then sort order. Statistics count each message once per browser tab session; nothing personal is stored.', 'announcement-bar-pro' ) . '</p></div>';
	}

	/**
	 * Small POST form with one button (actions change data, so no GET links).
	 *
	 * @param int    $id        Message ID.
	 * @param string $command   Action.
	 * @param string $label     Button text.
	 * @param string $css_class Extra classes.
	 */
	private function action_button( $id, $command, $label, $css_class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="abp-inline-form">';
		wp_nonce_field( 'abpro_message_' . $command . '_' . $id );
		echo '<input type="hidden" name="action" value="abpro_message_action"><input type="hidden" name="do" value="' . esc_attr( $command ) . '"><input type="hidden" name="id" value="' . (int) $id . '">'
			. '<button type="submit" class="button button-small ' . esc_attr( $css_class ) . '">' . esc_html( $label ) . '</button></form> ';
	}

	/**
	 * Statistics badges (with visible labels) of one variant.
	 *
	 * @param array  $m      Message row.
	 * @param string $v      a | b.
	 * @param string $prefix Variant label.
	 */
	private function stats( array $m, $v, $prefix ) {
		$sfx    = 'b' === $v ? '_b' : '';
		$views  = (int) $m[ 'views' . $sfx ];
		$clicks = (int) $m[ 'clicks' . $sfx ];
		$copies = (int) $m[ 'copies' . $sfx ];
		$ctr    = $views > 0 ? number_format_i18n( $clicks * 100 / $views, 1 ) . ' %' : '—';

		echo '<div class="abp-stats">' . ( '' !== $prefix ? '<span class="abp-stat-v">' . esc_html( $prefix ) . '</span>' : '' );
		$this->stat( __( 'Views', 'announcement-bar-pro' ), number_format_i18n( $views ) );
		$this->stat( __( 'Clicks', 'announcement-bar-pro' ), number_format_i18n( $clicks ) );
		$this->stat( __( 'CTR', 'announcement-bar-pro' ), $ctr );

		$opt = ( new Features() )->options( $m['options'] );

		if ( '' !== $m['coupon'] || $opt['coupon_id'] || $copies > 0 ) {
			$this->stat( __( 'Code copies', 'announcement-bar-pro' ), number_format_i18n( $copies ) );
		}

		if ( 'a' === $v && (int) $m['subscribes'] > 0 ) {
			$this->stat( __( 'Sign-ups', 'announcement-bar-pro' ), number_format_i18n( (int) $m['subscribes'] ) );
		}

		echo '</div>';
	}

	/**
	 * One statistics badge.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private function stat( $label, $value ) {
		echo '<span class="abp-stat"><span class="abp-stat-l">' . esc_html( $label ) . '</span> <span class="abp-stat-n">' . esc_html( $value ) . '</span></span>';
	}

	/**
	 * Date window as text.
	 *
	 * @param array $m Message row.
	 * @return string
	 */
	private function window_text( array $m ) {
		$fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$out = array();

		if ( ! empty( $m['date_start'] ) ) {
			/* translators: %s: date and time */
			$out[] = sprintf( __( 'from %s', 'announcement-bar-pro' ), wp_date( $fmt, Renderer::local_ts( $m['date_start'] ) ) );
		}

		if ( ! empty( $m['date_end'] ) ) {
			/* translators: %s: date and time */
			$out[] = sprintf( __( 'until %s', 'announcement-bar-pro' ), wp_date( $fmt, Renderer::local_ts( $m['date_end'] ) ) );
		}

		return implode( ' ', $out );
	}

	/**
	 * Feature badges of a message: array( label, pro? ).
	 *
	 * @param array $m   Message row.
	 * @param array $opt Options.
	 * @return array
	 */
	private function badges( array $m, array $opt ) {
		$out    = array();
		$places = $this->placements();

		if ( '' !== $m['placement'] ) {
			$out[] = array( $places[ $m['placement'] ], in_array( $m['placement'], array( 'bottom', 'cart', 'product', 'minicart' ), true ) );
		}

		if ( 'free_shipping' === $m['type'] ) {
			$out[] = array( __( 'Free shipping', 'announcement-bar-pro' ), false );
		} elseif ( 'tiers' === $m['type'] ) {
			$out[] = array( __( 'Reward tiers', 'announcement-bar-pro' ), true );
		}

		if ( (int) $m['countdown'] ) {
			$out[] = array( '' !== $opt['recur'] ? __( 'Recurring countdown', 'announcement-bar-pro' ) : __( 'Countdown', 'announcement-bar-pro' ), '' !== $opt['recur'] );
		}

		if ( '' !== $m['weekdays'] || '' !== $m['time_start'] ) {
			$out[] = array( __( 'Weekly schedule', 'announcement-bar-pro' ), false );
		}

		if ( 'all' !== $m['device'] ) {
			$out[] = array( 'mobile' === $m['device'] ? __( 'Phones only', 'announcement-bar-pro' ) : __( 'Computers only', 'announcement-bar-pro' ), false );
		}

		if ( '' !== $m['coupon'] || $opt['coupon_id'] ) {
			$out[] = array( __( 'Coupon', 'announcement-bar-pro' ), true );
		}

		if ( (int) $m['ab_test'] ) {
			$out[] = array( __( 'A/B test', 'announcement-bar-pro' ), true );
		}

		if ( $opt['email'] ) {
			$out[] = array( __( 'E-mail sign-up', 'announcement-bar-pro' ), true );
		}

		if ( 'all' !== $m['page_mode'] || '[]' !== $m['roles'] || '[]' !== $m['categories'] || '[]' !== $m['products'] || 'all' !== $opt['countries_mode'] || ( new Features() )->has_cart_rules( $opt ) || ( new Features() )->has_client_rules( $opt ) ) {
			$out[] = array( __( 'Targeted', 'announcement-bar-pro' ), true );
		}

		if ( 'none' !== $opt['trigger'] ) {
			$out[] = array( __( 'Trigger', 'announcement-bar-pro' ), true );
		}

		return $out;
	}

	/**
	 * Placement names.
	 *
	 * @return array
	 */
	private function placements() {
		return array(
			''         => __( 'Main bar (settings)', 'announcement-bar-pro' ),
			'top'      => __( 'Top bar', 'announcement-bar-pro' ),
			'bottom'   => __( 'Bottom bar', 'announcement-bar-pro' ),
			'cart'     => __( 'Cart page box', 'announcement-bar-pro' ),
			'product'  => __( 'Product page box (under the price)', 'announcement-bar-pro' ),
			'minicart' => __( 'Mini-cart box', 'announcement-bar-pro' ),
		);
	}

	/**
	 * List actions: delete, duplicate, on/off, reset statistics, use B.
	 */
	public function message_action() {
		$this->guard();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		check_admin_referer( 'abpro_message_' . $do . '_' . $id );

		$row = $id ? DB::message( $id ) : null;

		if ( ! $row ) {
			$this->back( '', 'failed' );
		}

		switch ( $do ) {
			case 'delete':
				DB::delete_message( $id );
				$this->back( '', 'deleted' );
				break;
			case 'duplicate':
				$this->back( '', DB::duplicate_message( $id ) ? 'duplicated' : 'failed' );
				break;
			case 'toggle':
				DB::set_status( $id, ! (int) $row['status'] );
				$this->back( '', 'status' );
				break;
			case 'reset':
				DB::reset_stats( $id );
				$this->back( '', 'reset' );
				break;
			case 'use_b':
				$this->back( '', Licence::is_pro() && DB::use_variant_b( $id ) ? 'use_b' : 'failed' );
				break;
		}

		$this->back( '', 'failed' );
	}

	/*
	 * ------------------------------------------------------------------
	 * Message form
	 * ------------------------------------------------------------------
	 */

	/**
	 * Empty message (form defaults), optionally filled from a template.
	 *
	 * @param string $template Template key.
	 * @return array
	 */
	private function blank( $template = '' ) {
		$s   = Settings::get();
		$fx  = new Features();
		$row = array(
			'message_id'   => 0,
			'status'       => 1,
			'sort_order'   => 0,
			'priority'     => 0,
			'date_start'   => '',
			'date_end'     => '',
			'bg_color'     => $s['default_bg_color'],
			'text_color'   => $s['default_text_color'],
			'link'         => '',
			'link_new_tab' => 0,
			'icon'         => '',
			'countdown'    => 0,
			'roles'        => '[]',
			'page_mode'    => 'all',
			'page_rules'   => '',
			'device'       => 'all',
			'type'         => 'standard',
			'threshold'    => '',
			'progress_bar' => 1,
			'coupon'       => '',
			'ab_test'      => 0,
			'weekdays'     => '',
			'time_start'   => '',
			'time_end'     => '',
			'categories'   => '[]',
			'products'     => '[]',
			'style'        => '',
			'button_style' => 'filled',
			'placement'    => '',
			'options'      => $fx->encode( $fx->defaults() ),
			'texts'        => array(),
		);

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
	 * Message form screen (new or edit).
	 */
	public function page_edit() {
		$this->guard();

		$id       = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$template = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$row      = $id ? DB::message( $id ) : $this->blank( $template );

		if ( ! $row ) {
			echo '<div class="wrap"><p>' . esc_html__( 'This message does not exist (any more).', 'announcement-bar-pro' ) . '</p></div>';
			return;
		}

		$errors = array();
		$kept   = get_transient( 'abpro_form_' . get_current_user_id() );

		if ( is_array( $kept ) && isset( $kept['id'] ) && (int) $kept['id'] === $id ) {
			// Values of the failed save (so nothing typed is lost) and the errors.
			$row    = array_merge( $row, $kept['row'] );
			$errors = $kept['errors'];
			delete_transient( 'abpro_form_' . get_current_user_id() );
		}

		( new Admin_Form( $row, $errors ) )->render();
	}

	/**
	 * Saves the message form (admin-post).
	 */
	public function save_message() {
		$this->guard();
		check_admin_referer( 'abpro_save_message' );

		$id       = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$existing = $id ? DB::message( $id ) : null;

		if ( $id && ! $existing ) {
			$this->back( '', 'failed' );
		}

		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is validated / sanitised in message_from_post().

		list( $data, $texts, $errors ) = $this->message_from_post( is_array( $post ) ? $post : array(), $existing, false );

		if ( $errors ) {
			$data['texts'] = $texts;
			$data['link']  = isset( $post['link'] ) && is_string( $post['link'] ) ? substr( $post['link'], 0, 300 ) : ''; // Shown again as typed (escaped on output).
			set_transient(
				'abpro_form_' . get_current_user_id(),
				array(
					'id'     => $id,
					'row'    => $data,
					'errors' => $errors,
				),
				10 * MINUTE_IN_SECONDS
			);
			$this->back( 'edit', 'invalid', $id ? array( 'id' => $id ) : array() );
		}

		$saved = DB::save_message( $id, $data, $texts );

		$this->back( 'edit', $saved ? 'saved' : 'failed', $saved ? array( 'id' => $saved ) : array() );
	}

	/**
	 * Posted message form → array( columns, texts by locale, errors ). Pro fields keep their stored values while no Pro
	 * licence is active; commerce fields keep theirs when the user may not manage WooCommerce.
	 *
	 * @param array      $p        Posted values (unslashed).
	 * @param array|null $existing Stored message.
	 * @param bool       $preview  Preview (no existence checks needed for unsaved choices, same rules otherwise).
	 * @return array
	 */
	public function message_from_post( array $p, $existing, $preview ) {
		$san      = new Sanitizer();
		$fx       = new Features();
		$s        = Settings::get();
		$pro      = Licence::is_pro();
		$commerce = Commerce::active() && current_user_can( 'manage_woocommerce' );
		$allow    = (bool) $s['allow_html'];
		$err      = array();
		$base     = $existing ? $existing : $this->blank();

		$type = (string) self::val( $p, 'type', 'standard' );
		if ( ! in_array( $type, Features::TYPES, true ) ) {
			$err['type'] = __( 'Please choose one of the offered values.', 'announcement-bar-pro' );
			$type        = 'standard';
		}
		if ( 'tiers' === $type && ! $pro ) {
			$type = 'standard';
		}
		if ( ! $commerce ) {
			$type = $base['type'];
		}

		// Options (validated by the shared feature library).
		$posted_opt = self::val( $p, 'opt', array() );
		$posted_opt = is_array( $posted_opt ) ? $posted_opt : array();
		$stored_opt = $fx->options( $base['options'] );

		if ( ! $pro ) {
			// Free: only the WooCommerce free-shipping switch comes from the form.
			$posted_opt                  = array_merge( $stored_opt, array( 'fs_wc' => isset( $posted_opt['fs_wc'] ) ? $posted_opt['fs_wc'] : 0 ) );
			$posted_opt['fs_currencies'] = $stored_opt['fs_currencies'];
		}
		if ( ! $commerce ) {
			foreach ( self::COMMERCE_OPTIONS as $key ) {
				$posted_opt[ $key ] = $stored_opt[ $key ];
			}
		}

		list( $opt, $opt_err ) = $fx->validate( $posted_opt, $preview ? null : Commerce::lookup(), array_merge( Commerce::currency_codes(), array_keys( $stored_opt['fs_currencies'] ) ), $type );

		foreach ( $opt_err as $field => $key ) {
			$err[ 'opt_' . $field ] = $this->error_text( $key );
		}

		// Texts per language.
		$posted_texts = self::val( $p, 'texts', array() );
		$posted_texts = is_array( $posted_texts ) ? $posted_texts : array();
		$index        = $fx->tier_indexes( isset( $posted_opt['tiers'] ) ? $posted_opt['tiers'] : array() );
		$ab           = $pro ? ! empty( $p['ab_test'] ) : ! empty( $base['ab_test'] );
		$texts        = array();
		$has_text     = false;
		$has_b        = false;
		$goal         = 'standard' !== $type;
		$html         = static function ( $value ) use ( $san, $allow ) {
			$value = $allow ? $san->html( $value ) : $san->plain( $value );

			return '' === trim( wp_strip_all_tags( $value ) ) ? '' : $value;
		};
		$button       = static function ( $value ) use ( $san ) {
			$value = $san->plain( $value );

			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_LINK_TEXT, 'UTF-8' ) : substr( $value, 0, self::MAX_LINK_TEXT );
		};
		$length       = static function ( $value ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		};

		foreach ( Settings::locales() as $locale ) {
			$row    = isset( $posted_texts[ $locale ] ) && is_array( $posted_texts[ $locale ] ) ? $posted_texts[ $locale ] : array();
			$old    = isset( $base['texts'][ $locale ] ) ? $base['texts'][ $locale ] : array();
			$get    = static function ( $field ) use ( $row ) {
				return isset( $row[ $field ] ) && is_string( $row[ $field ] ) ? $row[ $field ] : '';
			};
			$keep   = static function ( $field ) use ( $old ) {
				return isset( $old[ $field ] ) ? (string) $old[ $field ] : '';
			};
			$prefix = 'texts.' . $locale . '.';

			foreach ( array( 'text', 'mobile_text', 'success_text', 'text_b', 'mobile_text_b' ) as $field ) {
				$value = $get( $field );

				if ( strlen( $value ) > self::MAX_RAW_TEXT || $length( $allow ? $san->html( $value ) : $san->plain( $value ) ) > self::MAX_TEXT ) {
					/* translators: %d: number of characters */
					$err[ $prefix . $field ] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro' ), self::MAX_TEXT );
				}
			}

			foreach ( array( 'link_text', 'link_text_b' ) as $field ) {
				if ( $length( $san->plain( $get( $field ) ) ) > self::MAX_LINK_TEXT ) {
					/* translators: %d: number of characters */
					$err[ $prefix . $field ] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro' ), self::MAX_LINK_TEXT );
				}
			}

			$extra_err = array();
			$extra     = $pro ? $fx->extra( $row, 'tiers' === $type ? $index : array(), $extra_err ) : $fx->extra_of( $keep( 'extra' ) );

			foreach ( $extra_err as $field => $key ) {
				/* translators: %d: number of characters */
				$err[ $prefix . $field ] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro' ), in_array( $field, array( 'tier_text', 'email_consent' ), true ) ? Features::MAX_TIER_TEXT : Features::MAX_EXTRA );
			}

			$text = $allow ? $san->html( $get( 'text' ) ) : $san->plain( $get( 'text' ) );

			$texts[ $locale ] = array(
				'text'          => $text,
				'link_text'     => $button( $get( 'link_text' ) ),
				'mobile_text'   => $html( $get( 'mobile_text' ) ),
				'success_text'  => $goal ? $html( $get( 'success_text' ) ) : '',
				'text_b'        => $pro ? ( $ab ? $html( $get( 'text_b' ) ) : '' ) : $keep( 'text_b' ),
				'mobile_text_b' => $pro ? ( $ab ? $html( $get( 'mobile_text_b' ) ) : '' ) : $keep( 'mobile_text_b' ),
				'link_text_b'   => $pro ? ( $ab ? $button( $get( 'link_text_b' ) ) : '' ) : $keep( 'link_text_b' ),
				'extra'         => wp_json_encode( $extra ),
			);

			if ( '' !== trim( wp_strip_all_tags( $text ) ) ) {
				$has_text = true;

				if ( $goal && '' === $texts[ $locale ]['success_text'] ) {
					$err[ $prefix . 'success_text' ] = __( 'Please write the text shown once the goal is reached.', 'announcement-bar-pro' );
				}

				if ( 'tiers' === $type && in_array( '', $extra['tier_label'], true ) ) {
					$err[ $prefix . 'tier_label' ] = __( 'Every tier needs a label in each language that has a text.', 'announcement-bar-pro' );
				}
			}

			if ( '' !== $texts[ $locale ]['text_b'] ) {
				$has_b = true;
			}
		}

		$first = Settings::locales()[0];

		if ( ! $has_text ) {
			$err[ 'texts.' . $first . '.text' ] = __( 'Please write the message text in at least one language.', 'announcement-bar-pro' );
		}

		if ( $pro && $ab && ! $has_b ) {
			$err[ 'texts.' . $first . '.text_b' ] = __( 'An A/B test needs a text for variant B.', 'announcement-bar-pro' );
		}

		// Columns.
		$data = array(
			'status'       => ! empty( $p['status'] ) ? 1 : 0,
			'sort_order'   => 0,
			'bg_color'     => $san->color( self::val( $p, 'bg_color' ) ),
			'text_color'   => $san->color( self::val( $p, 'text_color' ) ),
			'link'         => '',
			'link_new_tab' => ! empty( $p['link_new_tab'] ) ? 1 : 0,
			'icon'         => $san->icon( self::val( $p, 'icon' ) ),
			'countdown'    => ! empty( $p['countdown'] ) ? 1 : 0,
			'device'       => in_array( self::val( $p, 'device' ), array( 'desktop', 'mobile' ), true ) ? self::val( $p, 'device' ) : 'all',
			'type'         => $type,
			'threshold'    => '0',
			'progress_bar' => ! empty( $p['progress_bar'] ) ? 1 : 0,
			'weekdays'     => $san->weekdays( is_array( self::val( $p, 'weekdays', array() ) ) ? self::val( $p, 'weekdays', array() ) : array() ),
			'time_start'   => '',
			'time_end'     => '',
			'style'        => in_array( self::val( $p, 'style' ), $pro ? Settings::STYLES : Settings::FREE_STYLES, true ) ? self::val( $p, 'style' ) : '',
			'button_style' => in_array( self::val( $p, 'button_style' ), array( 'filled', 'outline', 'link' ), true ) ? self::val( $p, 'button_style' ) : 'filled',
			'options'      => $fx->encode( $opt ),
			'date_start'   => null,
			'date_end'     => null,
		);

		foreach ( array( 'bg_color', 'text_color' ) as $key ) {
			if ( '' === $data[ $key ] ) {
				$err[ $key ]  = __( 'Please enter a colour such as #1f2937.', 'announcement-bar-pro' );
				$data[ $key ] = 'bg_color' === $key ? '#1f2937' : '#ffffff';
			}
		}

		$sort = trim( (string) self::val( $p, 'sort_order', '0' ) );
		if ( preg_match( '/^-?\d{1,6}$/', '' === $sort ? '0' : $sort ) ) {
			$data['sort_order'] = (int) $sort;
		} else {
			$err['sort_order'] = __( 'Please enter a whole number.', 'announcement-bar-pro' );
		}

		$link = trim( (string) self::val( $p, 'link' ) );
		if ( '' !== $link ) {
			$data['link'] = $san->url( $link );

			if ( '' === $data['link'] ) {
				$err['link'] = __( 'Please enter a full address (https://…) or a path on this site (/shop/).', 'announcement-bar-pro' );
			}
		}

		$icon = trim( (string) self::val( $p, 'icon' ) );
		if ( '' !== $icon && '' === $data['icon'] ) {
			$err['icon'] = __( 'Please choose one of the offered values.', 'announcement-bar-pro' );
		}

		foreach ( array( 'date_start', 'date_end' ) as $key ) {
			$value = trim( (string) self::val( $p, $key ) );

			if ( '' === $value ) {
				continue;
			}

			$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );

			if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $value ) {
				$err[ $key ] = __( 'Please enter a valid date and time.', 'announcement-bar-pro' );
				continue;
			}

			$data[ $key ] = $date->format( 'Y-m-d H:i:00' );
		}

		if ( $data['date_start'] && $data['date_end'] && $data['date_end'] <= $data['date_start'] ) {
			$err['date_end'] = __( 'The end must be after the start.', 'announcement-bar-pro' );
		}

		if ( $data['countdown'] && ! $data['date_end'] && '' === $opt['recur'] ) {
			$err['countdown'] = __( 'A countdown needs an end date (or, in Pro, a daily / weekly cut-off time).', 'announcement-bar-pro' );
		}

		$t1 = trim( (string) self::val( $p, 'time_start' ) );
		$t2 = trim( (string) self::val( $p, 'time_end' ) );
		if ( '' !== $t1 || '' !== $t2 ) {
			if ( '' === $san->clock_time( $t1 ) || '' === $san->clock_time( $t2 ) || $t1 === $t2 ) {
				$err['time_start'] = __( 'Please enter two different times (HH:MM), or leave both empty.', 'announcement-bar-pro' );
			} else {
				$data['time_start'] = $t1;
				$data['time_end']   = $t2;
			}
		}

		if ( 'free_shipping' === $type && ! $opt['fs_wc'] ) {
			$amount = $san->amount( self::val( $p, 'threshold' ) );

			if ( false === $amount || (float) $amount <= 0 ) {
				$err['threshold'] = __( 'Please enter the order amount for free shipping, for example 50.', 'announcement-bar-pro' );
			} else {
				$data['threshold'] = $amount;
			}
		} elseif ( 'free_shipping' === $type ) {
			$amount            = $san->amount( self::val( $p, 'threshold' ) );
			$data['threshold'] = false !== $amount ? $amount : '0';
		}

		if ( ! $commerce ) {
			$data['threshold']    = $base['threshold'];
			$data['progress_bar'] = $base['progress_bar'];
		}

		// Pro columns.
		if ( $pro ) {
			$priority = trim( (string) self::val( $p, 'priority', '0' ) );
			if ( '' !== $priority && ( ! preg_match( '/^\d{1,3}$/', $priority ) || (int) $priority > 100 ) ) {
				/* translators: 1: smallest value, 2: largest value */
				$err['priority'] = sprintf( __( 'Please enter a whole number from %1$d to %2$d.', 'announcement-bar-pro' ), 0, 100 );
			}
			$data['priority']  = max( 0, min( 100, (int) $priority ) );
			$data['placement'] = in_array( self::val( $p, 'placement' ), Features::PLACEMENTS, true ) ? self::val( $p, 'placement' ) : '';
			$data['ab_test']   = $ab ? 1 : 0;

			$coupon         = trim( (string) self::val( $p, 'coupon' ) );
			$data['coupon'] = $san->coupon( $coupon );
			if ( '' !== $coupon && '' === $data['coupon'] ) {
				$err['coupon'] = __( 'Use 1–32 letters, digits, "-" or "_".', 'announcement-bar-pro' );
			}

			$all_roles     = array_merge( array( 'guest' ), array_keys( wp_roles()->roles ) );
			$roles         = self::val( $p, 'roles', array() );
			$roles         = is_array( $roles ) ? array_values( array_intersect( array_map( 'strval', $roles ), $all_roles ) ) : array();
			$data['roles'] = wp_json_encode( $roles );

			$mode               = (string) self::val( $p, 'page_mode', 'all' );
			$data['page_mode']  = in_array( $mode, array( 'all', 'home', 'include', 'exclude' ), true ) ? $mode : 'all';
			$data['page_rules'] = '';
			if ( in_array( $data['page_mode'], array( 'include', 'exclude' ), true ) ) {
				$rules = $this->page_rules( (string) self::val( $p, 'page_rules' ) );

				if ( false === $rules ) {
					$err['page_rules'] = __( 'One rule per line: a page type from the list or a path that starts with "/" (a "*" at the end matches everything below it).', 'announcement-bar-pro' );
				} elseif ( ! $rules ) {
					$err['page_rules'] = __( 'Please add at least one page rule.', 'announcement-bar-pro' );
				} else {
					$data['page_rules'] = implode( "\n", $rules );
				}
			}

			foreach ( array(
				'categories' => 'category',
				'products'   => 'product',
			) as $key => $kind ) {
				$ids = $san->id_list( self::val( $p, $key, array() ), Features::MAX_IDS );

				if ( $ids && ! $preview && count( call_user_func( Commerce::lookup(), $kind, $ids ) ) !== count( $ids ) ) {
					$err[ $key ] = __( 'Some of the chosen items do not exist any more.', 'announcement-bar-pro' );
				}

				$data[ $key ] = wp_json_encode( $ids );
			}
		} else {
			foreach ( self::PRO_FIELDS as $key ) {
				$data[ $key ] = $base[ $key ];
			}
		}

		if ( ! $commerce ) {
			$data['categories'] = $base['categories'];
			$data['products']   = $base['products'];
		}

		return array( $data, $texts, $err );
	}

	/**
	 * Page rules from the textarea; false when a line is not a page type or a path.
	 *
	 * @param string $value Textarea.
	 * @return array|false
	 */
	private function page_rules( $value ) {
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
	public function error_text( $key ) {
		$texts = array(
			'error_value'              => __( 'Please choose one of the offered values.', 'announcement-bar-pro' ),
			'error_countries'          => __( 'Please choose countries from the list.', 'announcement-bar-pro' ),
			'error_countries_required' => __( 'Please choose at least one country.', 'announcement-bar-pro' ),
			'error_fs_zones'           => __( 'Each row needs a country from the list, an optional state of that country and an amount above 0.', 'announcement-bar-pro' ),
			'error_fs_zones_duplicate' => __( 'Each country / state may appear only once.', 'announcement-bar-pro' ),
			'error_fs_currencies'      => __( 'Amounts above 0, only for currencies of the shop.', 'announcement-bar-pro' ),
			'error_coupon_id'          => __( 'Please choose an existing coupon.', 'announcement-bar-pro' ),
			'error_tiers_order'        => __( 'Tier amounts must rise from row to row.', 'announcement-bar-pro' ),
			'error_tiers_count'        => __( 'Reward tiers need 2 to 5 amounts.', 'announcement-bar-pro' ),
			'error_amount'             => __( 'Please enter an amount such as 49.99.', 'announcement-bar-pro' ),
			'error_quantity'           => __( 'Please enter a whole number.', 'announcement-bar-pro' ),
			'error_min_max'            => __( 'The maximum must not be smaller than the minimum.', 'announcement-bar-pro' ),
			'error_cart_products'      => __( 'Some of the chosen products do not exist any more.', 'announcement-bar-pro' ),
			'error_cart_categories'    => __( 'Some of the chosen categories do not exist any more.', 'announcement-bar-pro' ),
			'error_utm'                => __( 'Use values such as newsletter, google or spring-sale, separated by commas (at most 20).', 'announcement-bar-pro' ),
			'error_referrer'           => __( 'Use site names such as facebook.com, separated by commas (at most 20).', 'announcement-bar-pro' ),
			'error_trigger_delay'      => __( 'Delay: 1 to 600 seconds.', 'announcement-bar-pro' ),
			'error_trigger_scroll'     => __( 'Scroll depth: 1 to 100 %.', 'announcement-bar-pro' ),
			'error_recur_time'         => __( 'Please enter the cut-off time as HH:MM.', 'announcement-bar-pro' ),
			'error_recur_skip'         => __( 'At least one day must stay active.', 'announcement-bar-pro' ),
			'error_image'              => __( 'Please choose an image from the media library.', 'announcement-bar-pro' ),
			'error_overlay'            => __( 'Overlay: 0 to 90 %.', 'announcement-bar-pro' ),
		);

		return isset( $texts[ $key ] ) ? $texts[ $key ] : $texts['error_value'];
	}

	/*
	 * ------------------------------------------------------------------
	 * Templates
	 * ------------------------------------------------------------------
	 */

	/**
	 * Templates gallery.
	 */
	public function page_templates() {
		$this->guard();

		$fx   = new Features();
		$lang = substr( get_user_locale(), 0, 2 );
		$pro  = Licence::is_pro();
		$wc   = Commerce::active();

		echo '<div class="wrap abp-admin">';
		$this->header( __( 'Templates', 'announcement-bar-pro' ), 'templates' );

		if ( ! $pro ) {
			echo '<div class="abp-hint">' . esc_html__( 'Templates are part of Pro. You can still write every free message yourself under Messages.', 'announcement-bar-pro' ) . '</div>';
		}

		echo '<ul class="abp-templates">';

		foreach ( $fx->templates() as $key => $t ) {
			$name = isset( $t['name'][ $lang ] ) ? $t['name'][ $lang ] : $t['name']['en'];
			$text = isset( $t['text'][ $lang ] ) ? $t['text'][ $lang ] : $t['text']['en'];
			$text = str_replace( array( '{remaining}', '{countdown}', '{goal}' ), array( Commerce::money( 12.5 ), '1d 04:12:30', isset( $t['tier_label'] ) ? $t['tier_label'][0][ isset( $t['tier_label'][0][ $lang ] ) ? $lang : 'en' ] : '' ), $text );
			$need = ! empty( $t['commerce'] ) && ! $wc;

			echo '<li class="abp-tpl"><div class="abp-tpl-bar" style="background:' . esc_attr( $t['bg'] ) . ';color:' . esc_attr( $t['fg'] ) . '">' . Icons::svg( $t['icon'] ) . ' <span>' . esc_html( $text ) . '</span></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
				. '<div class="abp-tpl-body"><h2 class="abp-tpl-name">' . esc_html( $name ) . '</h2>';

			if ( $need ) {
				echo '<p class="abp-small">' . esc_html__( 'Needs WooCommerce.', 'announcement-bar-pro' ) . '</p>';
			} elseif ( $pro ) {
				/* translators: %s: template name */
				echo '<a class="button button-primary" href="' . esc_url( self::url( 'edit', array( 'template' => $key ) ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Use template: %s', 'announcement-bar-pro' ), $name ) ) . '">' . esc_html__( 'Use template', 'announcement-bar-pro' ) . '</a>';
			} else {
				echo '<span class="abp-pro-badge">' . esc_html__( 'Pro', 'announcement-bar-pro' ) . '</span>';
			}

			echo '</div></li>';
		}

		echo '</ul></div>';
	}

	/*
	 * ------------------------------------------------------------------
	 * Settings and licence
	 * ------------------------------------------------------------------
	 */

	/**
	 * Settings screen.
	 */
	public function page_settings() {
		$this->guard();
		( new Admin_Settings_Page() )->render();
	}

	/**
	 * Saves the settings (admin-post).
	 */
	public function save_settings() {
		$this->guard();
		check_admin_referer( 'abpro_save_settings' );

		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in Settings::validate().

		list( $clean, $errors ) = Settings::validate( is_array( $post ) ? $post : array(), Settings::get(), Licence::is_pro() );

		if ( $errors ) {
			set_transient(
				'abpro_settings_' . get_current_user_id(),
				array(
					'values' => array_merge( $clean, array_intersect_key( is_array( $post ) ? $post : array(), $errors ) ),
					'errors' => $errors,
				),
				10 * MINUTE_IN_SECONDS
			);
			$this->back( 'settings', 'invalid' );
		}

		Settings::save( $clean );
		$this->back( 'settings', 'saved' );
	}

	/**
	 * Licence activate / remove (admin-post).
	 */
	public function licence_action() {
		$this->guard();
		check_admin_referer( 'abpro_licence' );

		if ( isset( $_POST['remove'] ) ) {
			Licence::deactivate();
			$this->back( 'settings', 'licence_rm' );
		}

		$key    = isset( $_POST['licence_key'] ) ? sanitize_text_field( wp_unslash( $_POST['licence_key'] ) ) : '';
		$result = Licence::activate( $key );

		$this->back( 'settings', 'valid' === $result['status'] ? 'licence_ok' : 'licence_no' );
	}

	/*
	 * ------------------------------------------------------------------
	 * Subscribers
	 * ------------------------------------------------------------------
	 */

	/**
	 * Subscribers screen.
	 */
	public function page_subscribers() {
		$this->guard();

		$counts = DB::subscriber_counts();

		echo '<div class="wrap abp-admin">';
		$this->header( __( 'Subscribers', 'announcement-bar-pro' ), 'subscribers' );

		if ( ! Licence::is_pro() ) {
			echo '<div class="abp-hint">' . esc_html__( 'The e-mail sign-up form is part of Pro. Existing entries stay here and can still be exported or erased.', 'announcement-bar-pro' ) . '</div>';
		}

		echo '<div class="abp-grid">';
		echo '<section class="abp-card"><h2 class="abp-card-title">' . esc_html__( 'List', 'announcement-bar-pro' ) . '</h2><div class="abp-card-body"><div class="abp-stats abp-stats-lg">';
		$this->stat( __( 'Subscribed', 'announcement-bar-pro' ), number_format_i18n( $counts[1] ) );
		$this->stat( __( 'Waiting for confirmation', 'announcement-bar-pro' ), number_format_i18n( $counts[0] ) );
		$this->stat( __( 'Unsubscribed', 'announcement-bar-pro' ), number_format_i18n( $counts[2] ) );
		echo '</div><p class="abp-help">' . esc_html__( 'Sign-ups are confirmed by e-mail (double opt-in) unless switched off in the settings. Unconfirmed entries are deleted after 30 days. The plugin does not send newsletters: export the confirmed addresses into your mailing tool.', 'announcement-bar-pro' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'abpro_subscribers' );
		echo '<input type="hidden" name="action" value="abpro_subscribers"><input type="hidden" name="do" value="export"><button type="submit" class="button button-primary">' . esc_html__( 'Export CSV', 'announcement-bar-pro' ) . '</button></form></div></section>';

		echo '<section class="abp-card"><h2 class="abp-card-title">' . esc_html__( 'Erase an address', 'announcement-bar-pro' ) . '</h2><div class="abp-card-body"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="abp-confirm-erase">';
		wp_nonce_field( 'abpro_subscribers' );
		echo '<input type="hidden" name="action" value="abpro_subscribers"><input type="hidden" name="do" value="erase"><p><label for="abp-erase-email">' . esc_html__( 'E-mail address', 'announcement-bar-pro' ) . '</label><br><input type="email" id="abp-erase-email" name="email" class="regular-text" required autocomplete="off"></p>'
			. '<p class="abp-help">' . esc_html__( 'Deletes the entry completely (right to erasure). Requests made through Tools → Erase Personal Data are handled automatically as well.', 'announcement-bar-pro' ) . '</p><button type="submit" class="button">' . esc_html__( 'Erase', 'announcement-bar-pro' ) . '</button></form></div></section>';
		echo '</div></div>';
	}

	/**
	 * CSV export / erase (admin-post).
	 */
	public function subscribers_action() {
		$this->guard();
		check_admin_referer( 'abpro_subscribers' );

		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		if ( 'erase' === $do ) {
			$email = ( new Features() )->email( isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '' );
			$this->back( 'subscribers', '' !== $email && DB::erase_subscriber( $email ) ? 'erased' : 'not_found' );
		}

		if ( 'export' !== $do ) {
			$this->back( 'subscribers', 'failed' );
		}

		$fx     = new Features();
		$status = array( 'pending', 'subscribed', 'unsubscribed' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );

		echo "\xEF\xBB\xBF" . 'email,status,language,message_id,consent,date_added_utc,date_confirmed_utc' . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV header.

		for ( $offset = 0; ; $offset += 500 ) {
			$rows = DB::subscribers( $offset, 500 );

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

require_once ABPRO_DIR . 'includes/class-admin-form.php';
require_once ABPRO_DIR . 'includes/class-admin-settings-page.php';
