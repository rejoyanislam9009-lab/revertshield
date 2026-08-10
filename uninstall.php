<?php
/**
 * Uninstall RevertShield.
 *
 * @package RevertShield
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'revertshield_settings', array() );

if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$changes     = $wpdb->prefix . 'revertshield_changes';
$health_runs = $wpdb->prefix . 'revertshield_health_runs';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- User explicitly opted into removing RevertShield's custom tables on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $changes ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- User explicitly opted into removing RevertShield's custom tables on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $health_runs ) );

delete_option( 'revertshield_settings' );
delete_option( 'revertshield_schema_version' );
