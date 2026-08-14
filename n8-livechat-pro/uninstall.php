<?php
/**
 * N8 LiveChat Pro uninstall handler.
 *
 * Chat data is preserved by default. Define N8LC_DELETE_DATA_ON_UNINSTALL as true
 * before uninstalling if you intentionally want all plugin tables/options removed.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'administrator' ) as $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) {
		foreach ( array( 'n8lc_manage_chat', 'n8lc_reply_chat', 'n8lc_manage_settings', 'n8lc_view_analytics' ) as $cap ) {
			$role->remove_cap( $cap );
		}
	}
}

if ( ! defined( 'N8LC_DELETE_DATA_ON_UNINSTALL' ) || true !== N8LC_DELETE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;
foreach ( array( 'events', 'messages', 'conversations', 'canned_replies', 'departments', 'visitors' ) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'n8lc_' . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'n8lc_settings' );
delete_option( 'n8lc_db_version' );
wp_clear_scheduled_hook( 'n8lc_daily_cleanup' );
