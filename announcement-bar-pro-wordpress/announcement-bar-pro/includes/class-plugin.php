<?php
/**
 * Bootstrap: activation (multisite aware), upgrades, cron, translations and the parts of the plugin.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
class Plugin {
	const CRON = 'abpro_daily_cleanup';

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Storefront part.
	 *
	 * @var Frontend|null
	 */
	public $frontend = null;

	/**
	 * The instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( self::CRON, array( DB::class, 'cleanup' ) );
		add_action( 'wp_initialize_site', array( $this, 'new_site' ), 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( ABPRO_FILE ), array( $this, 'action_links' ) );

		$this->frontend = new Frontend();
		new Endpoints();

		if ( is_admin() ) {
			Admin::init();
		}
	}

	/**
	 * Translations, upgrades.
	 */
	public function init() {
		load_plugin_textdomain( 'announcement-bar-pro', false, dirname( plugin_basename( ABPRO_FILE ) ) . '/languages' );

		if ( (int) get_option( 'abpro_schema', 0 ) < ABPRO_SCHEMA ) {
			self::install_site();
		}
	}

	/**
	 * Activation: tables, defaults and cron on this site, or on every site of a network activation.
	 *
	 * @param bool $network_wide Network activation.
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			) as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install_site();
				restore_current_blog();
			}

			return;
		}

		self::install_site();
	}

	/**
	 * Tables, default settings, secret and the daily clean-up of the current site.
	 */
	public static function install_site() {
		DB::install();
		Settings::reset();

		if ( false === get_option( Settings::OPTION, false ) ) {
			$defaults               = Settings::defaults();
			$defaults['privacy_id'] = (int) get_option( 'wp_page_for_privacy_policy', 0 );
			add_option( Settings::OPTION, $defaults );
		}

		Settings::secret();

		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Deactivation: stops the daily clean-up (data stays until the plugin is deleted).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * New site of a network where the plugin is network-active.
	 *
	 * @param \WP_Site $site New site.
	 */
	public function new_site( $site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active_for_network( plugin_basename( ABPRO_FILE ) ) ) {
			switch_to_blog( (int) $site->blog_id );
			self::install_site();
			restore_current_blog();
		}
	}

	/**
	 * "Settings" link in the plugin list.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=announcement-bar-pro' ) ) . '">' . esc_html__( 'Messages', 'announcement-bar-pro' ) . '</a>' );

		return $links;
	}
}
