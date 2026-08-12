<?php
/**
 * RevertShield runtime smoke assertions executed inside a real WordPress install.
 *
 * Run with: wp eval-file tests/runtime-smoke.php
 *
 * @package RevertShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress did not bootstrap.' );
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$assert( defined( 'REVERTSHIELD_VERSION' ), 'RevertShield version constant is missing.' );
$assert( class_exists( 'RevertShield\\Core\\Plugin' ), 'Main plugin coordinator did not autoload.' );
$assert( class_exists( 'RevertShield\\Snapshot\\PluginSnapshotService' ), 'Snapshot service did not autoload.' );
$assert( class_exists( 'RevertShield\\Update\\GuardedPluginUpdateService' ), 'Guarded update service did not autoload.' );
$assert( class_exists( 'RevertShield\\Recovery\\PluginRecoveryService' ), 'Recovery service did not autoload.' );
$assert( class_exists( 'RevertShield\\Admin\\RecoveryAdminPage' ), 'Recovery admin page did not autoload.' );

$assert( '2' === (string) get_option( 'revertshield_schema_version', '' ), 'Database schema migration did not complete.' );

$settings = get_option( 'revertshield_settings', array() );
$assert( is_array( $settings ), 'RevertShield settings are missing.' );
$assert( isset( $settings['retention_days'] ) && 90 === (int) $settings['retention_days'], 'Default ledger retention is incorrect.' );
$assert( isset( $settings['snapshot_retention_days'] ) && 7 === (int) $settings['snapshot_retention_days'], 'Default snapshot retention is incorrect.' );
$assert( isset( $settings['log_option_names'] ) && 1 === (int) $settings['log_option_names'], 'Default option-name logging setting is incorrect.' );
$assert( isset( $settings['delete_on_uninstall'] ) && 0 === (int) $settings['delete_on_uninstall'], 'Delete-on-uninstall must default to disabled.' );

global $wpdb;

$tables = array(
	$wpdb->prefix . 'revertshield_changes',
	$wpdb->prefix . 'revertshield_health_runs',
	$wpdb->prefix . 'revertshield_snapshots',
);

foreach ( $tables as $table ) {
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	$assert( $table === $found, 'Expected RevertShield table was not created: ' . $table );
}

$assert( false !== wp_next_scheduled( 'revertshield_daily_cleanup' ), 'Ledger cleanup event was not scheduled.' );
$assert( false !== wp_next_scheduled( 'revertshield_daily_snapshot_cleanup' ), 'Snapshot cleanup event was not scheduled.' );

$assert( false !== has_action( 'admin_post_revertshield_create_snapshot' ), 'Snapshot admin action is not registered.' );
$assert( false !== has_action( 'admin_post_revertshield_guarded_plugin_update' ), 'Guarded update admin action is not registered.' );
$assert( false !== has_action( 'admin_post_revertshield_plugin_recovery' ), 'Recovery admin action is not registered.' );

WP_CLI::success( 'RevertShield runtime smoke checks passed.' );
