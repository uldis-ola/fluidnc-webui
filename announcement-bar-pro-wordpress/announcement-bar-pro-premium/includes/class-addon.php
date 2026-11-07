<?php
/**
 * Bootstrap of the Premium add-on: translations, the subscriber table, the licence card and — with a valid licence
 * key — the premium features (registered on the hooks of the free plugin).
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

use AnnouncementBarPro\Admin;
use AnnouncementBarPro\Admin_Settings_Page;
use AnnouncementBarPro\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Add-on bootstrap.
 */
class Addon {
	/**
	 * Starts the add-on (once).
	 */
	public static function start() {
		static $started = false;

		if ( $started ) {
			return;
		}

		$started = true;

		add_action( 'init', array( __CLASS__, 'init' ), 5 );
		add_action( 'wp_initialize_site', array( __CLASS__, 'new_site' ), 21 );
		add_filter( 'plugin_action_links_' . plugin_basename( ABPRO_PREMIUM_FILE ), array( __CLASS__, 'action_links' ) );

		// The subscriber list, its screen and the privacy tools work without a licence too.
		Subscribers::init();
		add_action( 'abpro_settings_top', array( __CLASS__, 'licence_card' ) );
		add_action( 'abpro_admin_header', array( __CLASS__, 'edition' ) );
		add_action( 'admin_post_abpro_licence', array( __CLASS__, 'licence_action' ) );
		add_filter( 'abpro_admin_notices', array( __CLASS__, 'notices' ) );

		if ( Licence::is_pro() ) {
			Pro_Commerce::init();
			Pro_Settings::init();
			Pro_Render::init();
			Subscribers::init_signup();

			if ( is_admin() ) {
				Pro_Admin::init();
			} else {
				add_filter( 'abpro_message_types', array( Pro_Admin::class, 'types' ) );
				add_filter( 'abpro_style_choices', array( Pro_Admin::class, 'styles' ) );
			}

			add_action( 'rest_api_init', array( __CLASS__, 'rest_admin' ) );
		}
	}

	/**
	 * The preview route (REST) checks the message form like the admin does: the premium validation is needed there.
	 */
	public static function rest_admin() {
		if ( ! has_filter( 'abpro_message_from_post', array( Pro_Admin::class, 'from_post' ) ) ) {
			add_filter( 'abpro_message_from_post', array( Pro_Admin::class, 'from_post' ), 10, 4 );
			add_filter( 'abpro_preview_row', array( Pro_Admin::class, 'preview_row' ), 10, 2 );
		}
	}

	/**
	 * Translations and the subscriber table.
	 */
	public static function init() {
		load_plugin_textdomain( 'announcement-bar-pro-premium', false, dirname( plugin_basename( ABPRO_PREMIUM_FILE ) ) . '/languages' );
		Pro_DB::maybe_install();
	}

	/**
	 * Activation: the subscriber table on this site, or on every site of a network activation.
	 *
	 * @param bool $network_wide Network activation.
	 */
	public static function activate( $network_wide = false ) {
		if ( ! defined( 'ABPRO_API' ) ) {
			return; // The free plugin is missing: the table is made once it is active (init).
		}

		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			) as $site_id ) {
				switch_to_blog( (int) $site_id );
				Pro_DB::maybe_install();
				restore_current_blog();
			}

			return;
		}

		Pro_DB::maybe_install();
	}

	/**
	 * New site of a network where the add-on is network-active.
	 *
	 * @param \WP_Site $site New site.
	 */
	public static function new_site( $site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active_for_network( plugin_basename( ABPRO_PREMIUM_FILE ) ) ) {
			switch_to_blog( (int) $site->blog_id );
			Pro_DB::maybe_install();
			restore_current_blog();
		}
	}

	/**
	 * "Licence" link in the plugin list.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( Admin::url( 'settings' ) ) . '">' . esc_html__( 'Licence', 'announcement-bar-pro-premium' ) . '</a>' );

		return $links;
	}

	/**
	 * Edition label in the header of the admin screens.
	 */
	public static function edition() {
		echo Licence::is_pro() ? '<span class="abp-edition abp-edition-pro">' . esc_html__( 'Premium', 'announcement-bar-pro-premium' ) . '</span>' : '<span class="abp-edition">' . esc_html__( 'Premium: no licence key', 'announcement-bar-pro-premium' ) . '</span>';
	}

	/**
	 * Licence notices.
	 *
	 * @param array $texts Notices.
	 * @return array
	 */
	public static function notices( $texts ) {
		return array_merge(
			(array) $texts,
			array(
				'licence_ok' => array( 'success', __( 'Premium features are unlocked. Thank you!', 'announcement-bar-pro-premium' ) ),
				'licence_no' => array( 'error', __( 'This licence key is not valid. Please check it and try again.', 'announcement-bar-pro-premium' ) ),
				'licence_rm' => array( 'success', __( 'The licence key was removed from this site.', 'announcement-bar-pro-premium' ) ),
			)
		);
	}

	/**
	 * Licence card on the settings screen (own form).
	 *
	 * @param Admin_Settings_Page $page Settings page.
	 */
	public static function licence_card( $page ) {
		unset( $page );

		$data = Licence::get();
		$pro  = Licence::is_pro();

		echo '<section class="abp-card abp-licence"><h2 class="abp-card-title"><span class="abp-ico-badge" aria-hidden="true">' . Icons::svg( 'tag', 'abp-card-ico' ) . '</span>' . esc_html__( 'Premium licence', 'announcement-bar-pro-premium' ) . '</h2><div class="abp-card-body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() escapes.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'abpro_licence' );
		echo '<input type="hidden" name="action" value="abpro_licence">';

		if ( $pro ) {
			/* translators: %s: masked licence key */
			echo '<p><span class="abp-state abp-state-on">' . esc_html__( 'Premium active', 'announcement-bar-pro-premium' ) . '</span> ' . esc_html( sprintf( __( 'Key %s', 'announcement-bar-pro-premium' ), Licence::masked() ) ) . '</p>';
			echo '<button type="submit" name="remove" value="1" class="button">' . esc_html__( 'Remove key from this site', 'announcement-bar-pro-premium' ) . '</button>';
		} else {
			echo '<p>' . esc_html__( 'Enter the licence key from your order to switch on the premium features: more bars and boxes, targeting, coupons, A/B tests with statistics, the e-mail sign-up and templates.', 'announcement-bar-pro-premium' ) . '</p>';
			echo '<div class="abp-field"><label for="abp-licence-key">' . esc_html__( 'Licence key', 'announcement-bar-pro-premium' ) . '</label><input type="text" id="abp-licence-key" name="licence_key" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="ABP1-XXXX-XXXX-XXXX-XXXX" aria-describedby="abp-licence-help"></div>';
			echo '<p class="abp-help" id="abp-licence-help">' . esc_html__( 'The key is checked on this site only; no data is sent anywhere.', 'announcement-bar-pro-premium' ) . '</p>';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Activate', 'announcement-bar-pro-premium' ) . '</button>';
		}

		echo '</form>';

		if ( '' !== $data['key'] && ! $pro ) {
			echo '<p class="abp-help">' . esc_html__( 'A stored key is not valid any more.', 'announcement-bar-pro-premium' ) . '</p>';
		}

		echo '</div></section>';
	}

	/**
	 * Licence activate / remove (admin-post).
	 */
	public static function licence_action() {
		Admin::guard();
		check_admin_referer( 'abpro_licence' );

		if ( isset( $_POST['remove'] ) ) {
			Licence::deactivate();
			Admin::back( 'settings', 'licence_rm' );
		}

		$key    = isset( $_POST['licence_key'] ) ? sanitize_text_field( wp_unslash( $_POST['licence_key'] ) ) : '';
		$result = Licence::activate( $key );

		Admin::back( 'settings', 'valid' === $result['status'] ? 'licence_ok' : 'licence_no' );
	}
}
