<?php
/**
 * Admin: message list, message form with live preview and settings. Every action: capability check (manage_options;
 * commerce fields also need manage_woocommerce), nonce, sanitising on save and escaping on output. Add-ons extend the
 * screens through the actions and filters listed in README.md ("Hooks for add-ons").
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

	/** Message columns this plugin has no field for: kept as stored unless an add-on validates them. */
	const EXTRA_FIELDS = array( 'priority', 'placement', 'coupon', 'ab_test', 'roles', 'page_mode', 'page_rules', 'categories', 'products' );

	/** Message columns that need manage_woocommerce. */
	const COMMERCE_FIELDS = array( 'type', 'threshold', 'progress_bar' );

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
	}

	/**
	 * Menu (add-ons add their screens with add_submenu_page( Admin::SLUG, … )).
	 */
	public function menu() {
		add_menu_page( __( 'Announcement Bar Pro', 'announcement-bar-pro' ), __( 'Announcement Bar', 'announcement-bar-pro' ), self::CAP, self::SLUG, array( $this, 'page_messages' ), 'dashicons-megaphone', 58 );
		add_submenu_page( self::SLUG, __( 'Messages', 'announcement-bar-pro' ), __( 'Messages', 'announcement-bar-pro' ), self::CAP, self::SLUG, array( $this, 'page_messages' ) );
		add_submenu_page( self::SLUG, __( 'Add message', 'announcement-bar-pro' ), __( 'Add message', 'announcement-bar-pro' ), self::CAP, self::SLUG . '-edit', array( $this, 'page_edit' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'announcement-bar-pro' ), __( 'Settings', 'announcement-bar-pro' ), self::CAP, self::SLUG . '-settings', array( $this, 'page_settings' ) );
	}

	/**
	 * Current screen is one of ours.
	 *
	 * @param string $hook Screen hook.
	 * @return bool
	 */
	public static function ours( $hook ) {
		return false !== strpos( (string) $hook, self::SLUG );
	}

	/**
	 * Styles and scripts of our screens.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ) {
		if ( ! self::ours( $hook ) ) {
			return;
		}

		wp_enqueue_style( 'abp-admin', ABPRO_URL . 'assets/admin/admin.css', array(), ABPRO_VERSION );
		wp_enqueue_script( 'abp-admin', ABPRO_URL . 'assets/admin/admin.js', array(), ABPRO_VERSION, true );

		wp_localize_script(
			'abp-admin',
			'abpAdmin',
			array(
				'preview' => esc_url_raw( rest_url( Endpoints::NS . '/preview' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'l10n'    => array(
					'confirmDelete' => __( 'Delete this message? This cannot be undone.', 'announcement-bar-pro' ),
					'notShown'      => __( 'This message would not be shown with the current settings (no text yet, or rules that this site cannot apply).', 'announcement-bar-pro' ),
					'previewError'  => __( 'The preview could not be loaded.', 'announcement-bar-pro' ),
					'contrast'      => __( 'Contrast', 'announcement-bar-pro' ),
					'contrastOk'    => __( 'readable (WCAG AA)', 'announcement-bar-pro' ),
					'contrastLow'   => __( 'too low for small text — choose stronger colours (at least 4.5:1)', 'announcement-bar-pro' ),
				),
			)
		);

		$edit = false !== strpos( $hook, self::SLUG . '-edit' );
		do_action( 'abpro_admin_assets', $hook, $edit );
	}

	/*
	 * ------------------------------------------------------------------
	 * Small helpers
	 * ------------------------------------------------------------------
	 */

	/**
	 * Stops when the user may not manage the plugin.
	 */
	public static function guard() {
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
	public static function back( $page, $notice, array $args = array() ) {
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
	public static function val( array $p, $key, $fallback = '' ) {
		return isset( $p[ $key ] ) ? $p[ $key ] : $fallback;
	}

	/**
	 * Notice of the last action (filter "abpro_admin_notices" adds the codes of an add-on).
	 */
	public static function notice() {
		$code  = isset( $_GET['abpro_notice'] ) ? sanitize_key( wp_unslash( $_GET['abpro_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$texts = apply_filters(
			'abpro_admin_notices',
			array(
				'saved'      => array( 'success', __( 'Saved.', 'announcement-bar-pro' ) ),
				'deleted'    => array( 'success', __( 'Message deleted.', 'announcement-bar-pro' ) ),
				'duplicated' => array( 'success', __( 'Copy created. It is switched off until you turn it on.', 'announcement-bar-pro' ) ),
				'status'     => array( 'success', __( 'Status changed.', 'announcement-bar-pro' ) ),
				'invalid'    => array( 'error', __( 'Please correct the marked fields.', 'announcement-bar-pro' ) ),
				'failed'     => array( 'error', __( 'That did not work. Please try again.', 'announcement-bar-pro' ) ),
			)
		);

		if ( is_array( $texts ) && isset( $texts[ $code ] ) && is_array( $texts[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"%3$s><p>%2$s</p></div>', esc_attr( $texts[ $code ][0] ), esc_html( $texts[ $code ][1] ), 'error' === $texts[ $code ][0] ? ' role="alert"' : '' );
		}
	}

	/**
	 * Tabs of our screens: suffix => label (filter "abpro_admin_tabs").
	 *
	 * @return array
	 */
	public static function tabs() {
		$tabs = apply_filters(
			'abpro_admin_tabs',
			array(
				''         => __( 'Messages', 'announcement-bar-pro' ),
				'settings' => __( 'Settings', 'announcement-bar-pro' ),
			)
		);

		return is_array( $tabs ) ? $tabs : array();
	}

	/**
	 * Header of our screens (title, tabs, notice).
	 *
	 * @param string $title  Title.
	 * @param string $active Active tab.
	 * @param string $action Optional button HTML (escaped).
	 */
	public static function header( $title, $active, $action = '' ) {
		echo '<div class="abp-head"><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1> ' . $action . ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $action is built from escaped parts.
		do_action( 'abpro_admin_header', $active );
		echo '</div><nav class="nav-tab-wrapper abp-tabs" aria-label="' . esc_attr__( 'Announcement Bar sections', 'announcement-bar-pro' ) . '">';

		foreach ( self::tabs() as $page => $label ) {
			printf( '<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>', esc_url( self::url( (string) $page ) ), (string) $page === $active ? ' nav-tab-active' : '', (string) $page === $active ? ' aria-current="page"' : '', esc_html( $label ) );
		}

		echo '</nav>';
		self::notice();
	}

	/**
	 * One statistics-style badge (label + value).
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	public static function stat( $label, $value ) {
		echo '<span class="abp-stat"><span class="abp-stat-l">' . esc_html( $label ) . '</span> <span class="abp-stat-n">' . esc_html( $value ) . '</span></span>';
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
		self::guard();

		$rows    = DB::all_messages( get_user_locale() );
		$add     = '<a href="' . esc_url( self::url( 'edit' ) ) . '" class="page-title-action">' . esc_html__( 'Add message', 'announcement-bar-pro' ) . '</a>';
		$columns = apply_filters( 'abpro_list_columns', array() );
		$columns = is_array( $columns ) ? $columns : array();

		echo '<div class="wrap abp-admin">';
		self::header( __( 'Announcement Bar Pro', 'announcement-bar-pro' ), '', $add );

		if ( empty( Settings::get()['status'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The bar is switched off in the settings — no message is shown on the site.', 'announcement-bar-pro' ) . ' <a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Open settings', 'announcement-bar-pro' ) . '</a></p></div>';
		}

		if ( ! $rows ) {
			echo '<div class="abp-card abp-empty"><div class="abp-card-body"><h2>' . esc_html__( 'No messages yet', 'announcement-bar-pro' ) . '</h2><p>' . esc_html__( 'Write your first announcement — a delivery note, a sale with a countdown or a free-shipping progress bar.', 'announcement-bar-pro' ) . '</p><p><a class="button button-primary" href="' . esc_url( self::url( 'edit' ) ) . '">' . esc_html__( 'Add message', 'announcement-bar-pro' ) . '</a>';
			do_action( 'abpro_list_empty_actions' );
			echo '</p></div></div></div>';
			return;
		}

		echo '<div class="abp-card"><table class="widefat abp-list"><caption class="screen-reader-text">' . esc_html__( 'Messages', 'announcement-bar-pro' ) . '</caption><thead><tr>'
			. '<th scope="col">' . esc_html__( 'Message', 'announcement-bar-pro' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Status', 'announcement-bar-pro' ) . '</th>';
		foreach ( $columns as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'announcement-bar-pro' ) . '</span></th></tr></thead><tbody>';

		$san     = new Sanitizer();
		$handled = Options::handled();

		foreach ( $rows as $m ) {
			$id   = (int) $m['message_id'];
			$text = wp_strip_all_tags( $san->plain( $m['text'] ) );
			$text = '' !== $text ? $text : __( '(no text)', 'announcement-bar-pro' );

			echo '<tr><td class="abp-col-msg"><span class="abp-swatch" style="background:' . esc_attr( $san->color( $m['bg_color'], '#1f2937' ) ) . ';color:' . esc_attr( $san->color( $m['text_color'], '#ffffff' ) ) . '" aria-hidden="true">'
				. ( '' !== $m['icon'] ? Icons::svg( $m['icon'], 'abp-sw-ico' ) : 'A' ) . '</span><div><a class="abp-msg-title" href="' . esc_url( self::url( 'edit', array( 'id' => $id ) ) ) . '">' . esc_html( wp_html_excerpt( $text, 110, '…' ) ) . '</a><div class="abp-badges">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.

			$badges = apply_filters( 'abpro_message_badges', $this->badges( $m ), $m );

			if ( ! $handled && Options::narrows( $m, Options::decode( $m['options'] ) ) ) {
				$badges[] = array( __( 'Not shown: uses rules this site cannot apply', 'announcement-bar-pro' ), true );
			}

			foreach ( (array) $badges as $badge ) {
				echo '<span class="abp-badge' . ( ! empty( $badge[1] ) ? ' abp-badge-pro' : '' ) . '">' . esc_html( $badge[0] ) . '</span>';
			}

			echo '</div></div></td><td>';
			echo (int) $m['status'] ? '<span class="abp-state abp-state-on">' . esc_html__( 'On', 'announcement-bar-pro' ) . '</span>' : '<span class="abp-state">' . esc_html__( 'Off', 'announcement-bar-pro' ) . '</span>';
			echo $this->window_text( $m ) ? '<div class="abp-small">' . esc_html( $this->window_text( $m ) ) . '</div>' : '';
			echo '</td>';

			foreach ( array_keys( $columns ) as $column ) {
				echo '<td>';
				do_action( 'abpro_list_cell', $column, $m );
				echo '</td>';
			}

			echo '<td class="abp-actions">';
			echo '<a class="button button-small" href="' . esc_url( self::url( 'edit', array( 'id' => $id ) ) ) . '">' . esc_html__( 'Edit', 'announcement-bar-pro' ) . '<span class="screen-reader-text"> ' . esc_html( wp_html_excerpt( $text, 40, '…' ) ) . '</span></a> ';
			self::action_button( $id, 'toggle', (int) $m['status'] ? __( 'Turn off', 'announcement-bar-pro' ) : __( 'Turn on', 'announcement-bar-pro' ) );
			self::action_button( $id, 'duplicate', __( 'Duplicate', 'announcement-bar-pro' ) );
			do_action( 'abpro_list_actions', $m );
			self::action_button( $id, 'delete', __( 'Delete', 'announcement-bar-pro' ), 'abp-confirm-delete button-link-delete' );
			echo '</td></tr>';
		}

		echo '</tbody></table></div>';
		echo '<p class="abp-small">' . esc_html__( 'Order: priority (highest first), then sort order.', 'announcement-bar-pro' ) . '</p></div>';
	}

	/**
	 * Small POST form with one button (actions change data, so no GET links). Add-ons handle their own commands in
	 * the action "abpro_message_action".
	 *
	 * @param int    $id        Message ID.
	 * @param string $command   Action.
	 * @param string $label     Button text.
	 * @param string $css_class Extra classes.
	 */
	public static function action_button( $id, $command, $label, $css_class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="abp-inline-form">';
		wp_nonce_field( 'abpro_message_' . $command . '_' . $id );
		echo '<input type="hidden" name="action" value="abpro_message_action"><input type="hidden" name="do" value="' . esc_attr( $command ) . '"><input type="hidden" name="id" value="' . (int) $id . '">'
			. '<button type="submit" class="button button-small ' . esc_attr( $css_class ) . '">' . esc_html( $label ) . '</button></form> ';
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
	 * Feature badges of a message: array( label, highlighted? ).
	 *
	 * @param array $m Message row.
	 * @return array
	 */
	private function badges( array $m ) {
		$out = array();

		if ( 'free_shipping' === $m['type'] ) {
			$out[] = array( __( 'Free shipping', 'announcement-bar-pro' ), false );
		}

		if ( (int) $m['countdown'] ) {
			$out[] = array( __( 'Countdown', 'announcement-bar-pro' ), false );
		}

		if ( '' !== $m['weekdays'] || '' !== $m['time_start'] ) {
			$out[] = array( __( 'Weekly schedule', 'announcement-bar-pro' ), false );
		}

		if ( 'all' !== $m['device'] ) {
			$out[] = array( 'mobile' === $m['device'] ? __( 'Phones only', 'announcement-bar-pro' ) : __( 'Computers only', 'announcement-bar-pro' ), false );
		}

		return $out;
	}

	/**
	 * List actions: delete, duplicate, on / off (more in the action "abpro_message_action").
	 */
	public function message_action() {
		self::guard();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		check_admin_referer( 'abpro_message_' . $do . '_' . $id );

		$row = $id ? DB::message( $id ) : null;

		if ( ! $row ) {
			self::back( '', 'failed' );
		}

		switch ( $do ) {
			case 'delete':
				DB::delete_message( $id );
				self::back( '', 'deleted' );
				break;
			case 'duplicate':
				self::back( '', DB::duplicate_message( $id ) ? 'duplicated' : 'failed' );
				break;
			case 'toggle':
				DB::set_status( $id, ! (int) $row['status'] );
				self::back( '', 'status' );
				break;
		}

		do_action( 'abpro_message_action', $do, $id, $row ); // An add-on redirects with its own notice.
		self::back( '', 'failed' );
	}

	/*
	 * ------------------------------------------------------------------
	 * Message form
	 * ------------------------------------------------------------------
	 */

	/**
	 * Empty message (form defaults); filter "abpro_blank_message" may fill it (an add-on: templates).
	 *
	 * @param string $template Template key from the address (passed to the filter).
	 * @return array
	 */
	public function blank( $template = '' ) {
		$s   = Settings::get();
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
			'options'      => '{}',
			'texts'        => array(),
		);

		$filled = '' !== $template ? apply_filters( 'abpro_blank_message', $row, $template ) : $row;

		return is_array( $filled ) ? array_merge( $row, $filled ) : $row;
	}

	/**
	 * Message form screen (new or edit).
	 */
	public function page_edit() {
		self::guard();

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
		self::guard();
		check_admin_referer( 'abpro_save_message' );

		$id       = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$existing = $id ? DB::message( $id ) : null;

		if ( $id && ! $existing ) {
			self::back( '', 'failed' );
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
			self::back( 'edit', 'invalid', $id ? array( 'id' => $id ) : array() );
		}

		$saved = DB::save_message( $id, $data, $texts );

		self::back( 'edit', $saved ? 'saved' : 'failed', $saved ? array( 'id' => $saved ) : array() );
	}

	/**
	 * Message types: value => label (filter "abpro_message_types"). Free shipping needs WooCommerce.
	 *
	 * @return array
	 */
	public static function types() {
		$types = array( 'standard' => __( 'Announcement', 'announcement-bar-pro' ) );

		if ( Commerce::active() ) {
			$types['free_shipping'] = __( 'Free-shipping progress', 'announcement-bar-pro' );
		}

		$more = apply_filters( 'abpro_message_types', $types );

		return is_array( $more ) && $more ? $more : $types;
	}

	/**
	 * Posted message form → array( columns, texts by locale, errors ). Columns, options and texts this plugin has no
	 * field for keep their stored values; an add-on validates its own (filter "abpro_message_from_post"). Commerce fields
	 * keep theirs when the user may not manage WooCommerce.
	 *
	 * @param array      $p        Posted values (unslashed).
	 * @param array|null $existing Stored message.
	 * @param bool       $preview  Preview (no existence checks needed for unsaved choices, same rules otherwise).
	 * @return array
	 */
	public function message_from_post( array $p, $existing, $preview ) {
		$san      = new Sanitizer();
		$s        = Settings::get();
		$commerce = Commerce::active() && current_user_can( 'manage_woocommerce' );
		$allow    = (bool) $s['allow_html'];
		$err      = array();
		$base     = $existing ? $existing : $this->blank();

		$type = (string) self::val( $p, 'type', 'standard' );
		if ( ! isset( self::types()[ $type ] ) ) {
			$err['type'] = __( 'Please choose one of the offered values.', 'announcement-bar-pro' );
			$type        = 'standard';
		}
		if ( ! $commerce ) {
			$type = $base['type'];
		}

		// Options: this plugin sets fs_wc only; every other option keeps its stored value.
		$opt        = Options::decode( $base['options'] );
		$posted_opt = self::val( $p, 'opt', array() );
		$posted_opt = is_array( $posted_opt ) ? $posted_opt : array();

		if ( $commerce ) {
			$opt['fs_wc'] = ! empty( $posted_opt['fs_wc'] ) && '0' !== (string) $posted_opt['fs_wc'] ? 1 : 0;
		}

		// Texts per language.
		$posted_texts = self::val( $p, 'texts', array() );
		$posted_texts = is_array( $posted_texts ) ? $posted_texts : array();
		$texts        = array();
		$has_text     = false;
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

			foreach ( array( 'text', 'mobile_text', 'success_text' ) as $field ) {
				$value = $get( $field );

				if ( strlen( $value ) > self::MAX_RAW_TEXT || $length( $allow ? $san->html( $value ) : $san->plain( $value ) ) > self::MAX_TEXT ) {
					/* translators: %d: number of characters */
					$err[ $prefix . $field ] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro' ), self::MAX_TEXT );
				}
			}

			if ( $length( $san->plain( $get( 'link_text' ) ) ) > self::MAX_LINK_TEXT ) {
				/* translators: %d: number of characters */
				$err[ $prefix . 'link_text' ] = sprintf( __( 'At most %d characters.', 'announcement-bar-pro' ), self::MAX_LINK_TEXT );
			}

			$text = $allow ? $san->html( $get( 'text' ) ) : $san->plain( $get( 'text' ) );

			$texts[ $locale ] = array(
				'text'          => $text,
				'link_text'     => $button( $get( 'link_text' ) ),
				'mobile_text'   => $html( $get( 'mobile_text' ) ),
				'success_text'  => $goal ? $html( $get( 'success_text' ) ) : '',
				// Texts of an add-on (second variant, extra texts) keep their stored values.
				'text_b'        => $keep( 'text_b' ),
				'mobile_text_b' => $keep( 'mobile_text_b' ),
				'link_text_b'   => $keep( 'link_text_b' ),
				'extra'         => '' !== $keep( 'extra' ) ? $keep( 'extra' ) : '{}',
			);

			if ( '' !== trim( wp_strip_all_tags( $text ) ) ) {
				$has_text = true;

				if ( $goal && '' === $texts[ $locale ]['success_text'] ) {
					$err[ $prefix . 'success_text' ] = __( 'Please write the text shown once the goal is reached.', 'announcement-bar-pro' );
				}
			}
		}

		if ( ! $has_text ) {
			$err[ 'texts.' . Settings::locales()[0] . '.text' ] = __( 'Please write the message text in at least one language.', 'announcement-bar-pro' );
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
			'style'        => is_string( self::val( $p, 'style' ) ) && isset( Settings::choices( 'style' )[ self::val( $p, 'style' ) ] ) ? self::val( $p, 'style' ) : '',
			'button_style' => in_array( self::val( $p, 'button_style' ), array( 'filled', 'outline', 'link' ), true ) ? self::val( $p, 'button_style' ) : 'filled',
			'options'      => Options::encode( $opt ),
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

		if ( $data['countdown'] && ! $data['date_end'] ) {
			$err['countdown'] = __( 'A countdown needs an end date.', 'announcement-bar-pro' );
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

		if ( 'free_shipping' === $type ) {
			$amount = $san->amount( self::val( $p, 'threshold' ) );

			if ( empty( $opt['fs_wc'] ) && ( false === $amount || (float) $amount <= 0 ) ) {
				$err['threshold'] = __( 'Please enter the order amount for free shipping, for example 50.', 'announcement-bar-pro' );
			} else {
				$data['threshold'] = false !== $amount ? $amount : '0';
			}
		}

		if ( ! $commerce ) {
			$data['threshold']    = $base['threshold'];
			$data['progress_bar'] = $base['progress_bar'];
		}

		// Columns without a field here keep their stored values (an add-on may validate them below).
		foreach ( self::EXTRA_FIELDS as $key ) {
			$data[ $key ] = $base[ $key ];
		}

		$result = apply_filters( 'abpro_message_from_post', array( $data, $texts, $err ), $p, $base, $preview );

		return is_array( $result ) && 3 === count( $result ) ? array_values( $result ) : array( $data, $texts, $err );
	}

	/*
	 * ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------
	 */

	/**
	 * Settings screen.
	 */
	public function page_settings() {
		self::guard();
		( new Admin_Settings_Page() )->render();
	}

	/**
	 * Saves the settings (admin-post).
	 */
	public function save_settings() {
		self::guard();
		check_admin_referer( 'abpro_save_settings' );

		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in Settings::validate().

		list( $clean, $errors ) = Settings::validate( is_array( $post ) ? $post : array(), Settings::get() );

		if ( $errors ) {
			set_transient(
				'abpro_settings_' . get_current_user_id(),
				array(
					'values' => array_merge( $clean, array_intersect_key( is_array( $post ) ? $post : array(), $errors ) ),
					'errors' => $errors,
				),
				10 * MINUTE_IN_SECONDS
			);
			self::back( 'settings', 'invalid' );
		}

		Settings::save( $clean );
		self::back( 'settings', 'saved' );
	}
}

require_once ABPRO_DIR . 'includes/class-admin-form.php';
require_once ABPRO_DIR . 'includes/class-admin-settings-page.php';
