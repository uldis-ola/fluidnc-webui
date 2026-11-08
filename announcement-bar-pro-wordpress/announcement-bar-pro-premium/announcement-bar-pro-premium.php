<?php
/**
 * Plugin Name:       Announcement Bar Pro — Premium
 * Description:       Premium add-on for the free Announcement Bar Pro plugin: bottom bar and boxes in the cart, under the product price and in the mini-cart, targeting, reward tiers, coupons, A/B tests with statistics, a GDPR e-mail sign-up (optional Cloudflare Turnstile), templates and more.
 * Version:           1.0.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  announcement-bar-pro
 * Author:            3DP SIA
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       announcement-bar-pro-premium
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 *
 * @package AnnouncementBarProPremium
 */

defined( 'ABSPATH' ) || exit;

define( 'ABPRO_PREMIUM_VERSION', '1.0.1' );
define( 'ABPRO_PREMIUM_FILE', __FILE__ );
define( 'ABPRO_PREMIUM_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABPRO_PREMIUM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Loads the add-on once the free plugin (hooks version 1 or newer) is loaded; otherwise explains what is missing.
 */
function abpro_premium_load() {
	if ( ! defined( 'ABPRO_API' ) || (int) ABPRO_API < 1 ) {
		add_action( 'admin_notices', 'abpro_premium_missing_notice' );
		add_action( 'network_admin_notices', 'abpro_premium_missing_notice' );
		return;
	}

	foreach ( array( 'interface-licence-provider', 'class-local-licence-provider', 'class-licence', 'class-features', 'class-turnstile', 'class-pro-db', 'class-pro-commerce', 'class-pro-settings', 'class-pro-render', 'class-subscribers', 'class-pro-admin', 'class-addon' ) as $file ) {
		require_once ABPRO_PREMIUM_DIR . 'includes/' . $file . '.php';
	}

	AnnouncementBarPro\Premium\Addon::start();
}
add_action( 'plugins_loaded', 'abpro_premium_load', 5 );

/**
 * Admin notice: the free plugin is missing, inactive or too old (the single-plugin build 1.0.0 has no hooks for add-ons).
 */
function abpro_premium_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$text = defined( 'ABPRO_VERSION' )
		? __( 'Announcement Bar Pro — Premium needs the free Announcement Bar Pro plugin with add-on support. Please update Announcement Bar Pro (Plugins → Add New) to its current version from WordPress.org.', 'announcement-bar-pro-premium' )
		: __( 'Announcement Bar Pro — Premium needs the free Announcement Bar Pro plugin. Please install and activate it (Plugins → Add New, search for "Announcement Bar Pro"); the premium features start as soon as it is active.', 'announcement-bar-pro-premium' );

	echo '<div class="notice notice-error"><p>' . esc_html( $text ) . '</p></div>';
}

/**
 * Activation: subscriber table (when the free plugin is active already).
 *
 * @param bool $network_wide Network activation.
 */
function abpro_premium_activate( $network_wide = false ) {
	if ( defined( 'ABPRO_API' ) ) {
		abpro_premium_load();
		AnnouncementBarPro\Premium\Addon::activate( $network_wide );
	}
}
register_activation_hook( __FILE__, 'abpro_premium_activate' );

// WooCommerce: HPOS (custom order tables) and cart / checkout blocks are supported. The add-on never reads or writes orders.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);
