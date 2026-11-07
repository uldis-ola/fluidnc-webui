<?php
/**
 * Plugin Name:       Announcement Bar Pro
 * Description:       Announcement bars with rotating messages, schedules, countdowns and a WooCommerce free-shipping progress bar. Pro adds more bars, inline boxes, targeting, A/B tests, coupons and a GDPR e-mail sign-up.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            3DP SIA
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       announcement-bar-pro
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 *
 * @package AnnouncementBarPro
 */

defined( 'ABSPATH' ) || exit;

define( 'ABPRO_VERSION', '1.0.0' );
define( 'ABPRO_SCHEMA', 1 );
define( 'ABPRO_FILE', __FILE__ );
define( 'ABPRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABPRO_URL', plugin_dir_url( __FILE__ ) );

require_once ABPRO_DIR . 'includes/class-sanitizer.php';
require_once ABPRO_DIR . 'includes/class-features.php';
require_once ABPRO_DIR . 'includes/class-icons.php';
require_once ABPRO_DIR . 'includes/class-licence.php';
require_once ABPRO_DIR . 'includes/class-settings.php';
require_once ABPRO_DIR . 'includes/class-db.php';
require_once ABPRO_DIR . 'includes/class-commerce.php';
require_once ABPRO_DIR . 'includes/class-renderer.php';
require_once ABPRO_DIR . 'includes/class-frontend.php';
require_once ABPRO_DIR . 'includes/class-endpoints.php';
require_once ABPRO_DIR . 'includes/class-subscribers.php';
require_once ABPRO_DIR . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once ABPRO_DIR . 'includes/class-admin.php';
}

register_activation_hook( __FILE__, array( 'AnnouncementBarPro\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AnnouncementBarPro\\Plugin', 'deactivate' ) );

// WooCommerce: HPOS (custom order tables) and cart / checkout blocks are supported. The plugin never reads or writes orders.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

AnnouncementBarPro\Plugin::instance();
