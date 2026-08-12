<?php
/**
 * Uninstall RevertShield.
 *
 * @package RevertShield
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// RevertShield settings are site-scoped. A single site's delete-on-uninstall
// choice is not sufficient authorization to erase data belonging to an entire
// Multisite network, and deleting only the current site's data would create a
// misleading partial cleanup. Until a bounded network-wide deletion policy is
// available, Multisite uninstall therefore retains all RevertShield site data.
if ( is_multisite() ) {
	return;
}

$revertshield_settings = get_option( 'revertshield_settings', array() );

if ( empty( $revertshield_settings['delete_on_uninstall'] ) ) {
	return;
}

$revertshield_uploads = wp_upload_dir( null, false );
if ( empty( $revertshield_uploads['error'] ) && ! empty( $revertshield_uploads['basedir'] ) ) {
	$revertshield_basedir = untrailingslashit( wp_normalize_path( $revertshield_uploads['basedir'] ) );
	$revertshield_storage = wp_normalize_path( trailingslashit( $revertshield_basedir ) . 'revertshield' );

	if ( 0 === strpos( $revertshield_storage, trailingslashit( $revertshield_basedir ) ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( WP_Filesystem() ) {
			global $wp_filesystem;

			if ( $wp_filesystem && isset( $wp_filesystem->method ) && 'direct' === $wp_filesystem->method && $wp_filesystem->exists( $revertshield_storage ) ) {
				$wp_filesystem->delete( $revertshield_storage, true );
			}
		}
	}
}

global $wpdb;

$revertshield_changes     = $wpdb->prefix . 'revertshield_changes';
$revertshield_health_runs = $wpdb->prefix . 'revertshield_health_runs';
$revertshield_snapshots   = $wpdb->prefix . 'revertshield_snapshots';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- User explicitly opted into removing RevertShield's custom tables on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $revertshield_changes ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- User explicitly opted into removing RevertShield's custom tables on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $revertshield_health_runs ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- User explicitly opted into removing RevertShield's custom tables on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $revertshield_snapshots ) );

delete_option( 'revertshield_settings' );
delete_option( 'revertshield_schema_version' );
