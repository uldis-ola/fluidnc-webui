<?php
/**
 * Uninstall of the Premium add-on: removes the subscriber list and the licence key only when "Delete all messages and
 * settings when the plugin is deleted" is on (setting of the free plugin, per site), or when the free plugin's data
 * was deleted already. Messages and settings belong to the free plugin and are removed by its own uninstall.
 *
 * @package AnnouncementBarProPremium
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the add-on's table and options of the current site when its settings allow it.
 */
function abpro_premium_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'abpro_settings', false );

	// Default: keep everything, so a reinstall keeps the list. No settings at all: the free plugin's data was deleted.
	if ( false !== $settings && ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) ) {
		return;
	}

	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'abpro_subscribers' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixed table name.

	foreach ( array( 'abpro_licence', 'abpro_premium_schema' ) as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $abpro_site_id ) {
		switch_to_blog( (int) $abpro_site_id );
		abpro_premium_uninstall_site();
		restore_current_blog();
	}
} else {
	abpro_premium_uninstall_site();
}
