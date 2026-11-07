<?php
/**
 * Uninstall: removes the plugin's data only when "Delete all messages and settings when the plugin is deleted" is on
 * (per site). Add-ons remove their own data (tables, options) with their own uninstall.
 *
 * @package AnnouncementBarPro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes tables and options of the current site when its settings allow it.
 */
function abpro_uninstall_site() {
	global $wpdb;

	wp_clear_scheduled_hook( 'abpro_daily_cleanup' );

	$settings = get_option( 'abpro_settings', array() );

	if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
		return; // Default: keep everything, so a reinstall keeps the messages.
	}

	foreach ( array( 'abpro_messages', 'abpro_message_texts', 'abpro_rate' ) as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixed table names.
	}

	foreach ( array( 'abpro_settings', 'abpro_secret', 'abpro_schema' ) as $option ) {
		delete_option( $option );
	}

	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_abpro_' ) . '%', $wpdb->esc_like( '_transient_timeout_abpro_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time clean-up of our transients.
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $abpro_site_id ) {
		switch_to_blog( (int) $abpro_site_id );
		abpro_uninstall_site();
		restore_current_blog();
	}
} else {
	abpro_uninstall_site();
}
