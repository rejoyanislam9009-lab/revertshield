<?php
/**
 * RevertShield Multisite runtime safety assertions.
 *
 * Run with: wp eval-file tests/runtime-multisite.php
 *
 * @package RevertShield
 */

use RevertShield\Admin\MultisiteNotice;
use RevertShield\Database\Migrator;
use RevertShield\Database\Tables;
use RevertShield\Health\HealthChecker;
use RevertShield\Ledger\ChangeRepository;
use RevertShield\Recovery\RecoveryEligibility;
use RevertShield\Snapshot\PluginSnapshotService;
use RevertShield\Snapshot\SnapshotCleanup;
use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Snapshot\SnapshotVerifier;
use RevertShield\Support\Cleanup;
use RevertShield\Support\SiteContext;
use RevertShield\Update\SafeUpdateGate;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress did not bootstrap.' );
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$assert_error = static function ( $result, $code, $message ) use ( $assert ) {
	$assert( is_wp_error( $result ), $message . ' Expected WP_Error.' );
	$assert( $code === $result->get_error_code(), $message . ' Unexpected error code: ' . $result->get_error_code() );
};

$capture = static function ( $callback ) {
	ob_start();
	$callback();
	return (string) ob_get_clean();
};

$assert( is_multisite(), 'Multisite runtime test did not boot a WordPress network.' );
wp_set_current_user( 1 );

global $wpdb;

$main_site_id = absint( get_main_site_id() );
$site_ids     = get_sites(
	array(
		'fields' => 'ids',
		'number' => 0,
	)
);
$site_ids     = array_map( 'absint', $site_ids );
$other_sites  = array_values( array_diff( $site_ids, array( $main_site_id ) ) );
$assert( ! empty( $other_sites ), 'Multisite regression fixture is missing a secondary site.' );
$second_site_id = $other_sites[0];

if ( get_current_blog_id() !== $main_site_id ) {
	switch_to_blog( $main_site_id );
}

$main_context = new SiteContext();
$assert( true === $main_context->is_multisite(), 'SiteContext did not detect Multisite.' );
$assert( $main_site_id === $main_context->blog_id(), 'SiteContext reported the wrong main-site ID.' );
$assert( 0 < $main_context->network_id(), 'SiteContext did not report a network ID.' );
$assert( Migrator::SCHEMA_VERSION === (string) get_option( 'revertshield_schema_version', '' ), 'Main-site RevertShield schema was not provisioned.' );
$assert( is_array( get_option( 'revertshield_settings', null ) ), 'Main-site RevertShield defaults were not provisioned.' );
$assert( false !== wp_next_scheduled( Cleanup::HOOK ), 'Main-site ledger/health cleanup was not scheduled.' );
$assert( false !== wp_next_scheduled( SnapshotCleanup::HOOK ), 'Main-site snapshot cleanup was not scheduled.' );

$main_change_count = ( new ChangeRepository() )->count();
$main_health_count = ( new HealthChecker( array() ) )->count();

$fixture_slug = 'revertshield-multisite-fixture';
$fixture_file = $fixture_slug . '/revertshield-multisite-fixture.php';
$fixture_dir  = trailingslashit( WP_PLUGIN_DIR ) . $fixture_slug;
$fixture_path = trailingslashit( $fixture_dir ) . 'revertshield-multisite-fixture.php';
$fixture_code = "<?php\n/**\n * Plugin Name: RevertShield Multisite Fixture\n * Version: 1.0.0\n */\n";

wp_mkdir_p( $fixture_dir );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Runtime test fixture setup.
file_put_contents( $fixture_path, $fixture_code );
wp_clean_plugins_cache( false );

$main_repository = new SnapshotRepository();
$main_snapshot    = ( new PluginSnapshotService() )->create( $fixture_file );
$assert( ! is_wp_error( $main_snapshot ), 'Main-site Multisite snapshot creation failed.' );
$assert( true === $main_snapshot['verified'], 'Main-site Multisite snapshot did not verify.' );
$assert(
	false !== strpos( $main_snapshot['storage_relpath'], 'revertshield/sites/' . $main_site_id . '/snapshots/' ),
	'Main-site snapshot storage was not explicitly site-scoped.'
);

$main_uuid = $main_snapshot['snapshot_uuid'];
$main_row  = $main_repository->find( $main_uuid );
$assert( is_array( $main_row ), 'Main-site snapshot metadata could not be reloaded.' );
$main_manifest = json_decode( $main_row['manifest'], true );
$assert( is_array( $main_manifest ), 'Main-site snapshot manifest could not be decoded.' );
$assert( isset( $main_manifest['metadata']['revertshield_scope'] ) && 'multisite-site' === $main_manifest['metadata']['revertshield_scope'], 'Main-site snapshot is missing Multisite scope metadata.' );
$assert( $main_site_id === absint( $main_manifest['metadata']['origin_blog_id'] ), 'Main-site snapshot stored the wrong origin site.' );
$assert( $main_context->network_id() === absint( $main_manifest['metadata']['origin_network_id'] ), 'Main-site snapshot stored the wrong origin network.' );

$main_verified = ( new SnapshotVerifier( $main_repository ) )->verify( $main_uuid );
$assert( ! is_wp_error( $main_verified ) && true === $main_verified['verified'], 'Origin-site snapshot verification failed on Multisite.' );

$blocked_update = ( new SafeUpdateGate( $main_repository ) )->validate( $main_uuid, $fixture_file );
$assert_error(
	$blocked_update,
	'revertshield_multisite_plugin_mutation_deferred',
	'Guarded plugin update did not fail closed on Multisite.'
);

$blocked_recovery = ( new RecoveryEligibility( $main_repository ) )->validate( $main_uuid, $fixture_file );
$assert_error(
	$blocked_recovery,
	'revertshield_multisite_plugin_mutation_deferred',
	'Plugin recovery did not fail closed on Multisite.'
);

switch_to_blog( $second_site_id );

$second_context = new SiteContext();
$assert( $second_site_id === $second_context->blog_id(), 'SiteContext did not follow switch_to_blog().' );
$assert( Migrator::SCHEMA_VERSION === (string) get_option( 'revertshield_schema_version', '' ), 'New Multisite site was not provisioned by RevertShield.' );
$assert( is_array( get_option( 'revertshield_settings', null ) ), 'New Multisite site is missing RevertShield defaults.' );
$assert( false !== wp_next_scheduled( Cleanup::HOOK ), 'Secondary-site ledger/health cleanup was not scheduled.' );
$assert( false !== wp_next_scheduled( SnapshotCleanup::HOOK ), 'Secondary-site snapshot cleanup was not scheduled.' );

$second_snapshot_table = Tables::snapshots();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Runtime test verifies per-site schema provisioning.
$existing_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $second_snapshot_table ) );
$assert( $second_snapshot_table === $existing_table, 'New Multisite site is missing the snapshot metadata table.' );

$cross_site_row = ( new SnapshotRepository() )->find( $main_uuid );
$assert( null === $cross_site_row, 'A snapshot record leaked across Multisite site tables.' );

$scope_mismatch = $second_context->validate_snapshot_manifest_scope( $main_manifest );
$assert_error(
	$scope_mismatch,
	'revertshield_multisite_snapshot_scope_mismatch',
	'Multisite snapshot ownership metadata did not reject another site context.'
);

$second_snapshot = ( new PluginSnapshotService() )->create( $fixture_file );
$assert( ! is_wp_error( $second_snapshot ), 'Secondary-site Multisite snapshot creation failed.' );
$assert(
	false !== strpos( $second_snapshot['storage_relpath'], 'revertshield/sites/' . $second_site_id . '/snapshots/' ),
	'Secondary-site snapshot storage was not explicitly site-scoped.'
);

$second_changes = new ChangeRepository();
$recorded       = $second_changes->record( 'multisite_test_event', 'site', (string) $second_site_id, array(), 'test' );
$assert( false !== $recorded, 'Secondary-site ledger event could not be recorded.' );
$assert( 1 === $second_changes->count(), 'Secondary-site ledger did not remain isolated after switch_to_blog().' );

$http_pass = static function () {
	return array(
		'headers'  => array(),
		'body'     => '{}',
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $http_pass, 10, 3 );
$second_health = ( new HealthChecker( array() ) )->run_site_check();
remove_filter( 'pre_http_request', $http_pass, 10 );
$assert( 'pass' === $second_health['status'], 'Secondary-site deterministic health suite did not pass.' );
$assert( 1 === ( new HealthChecker( array() ) )->count(), 'Secondary-site health history did not remain site-scoped.' );

if ( ! function_exists( 'set_current_screen' ) ) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
}
set_current_screen( 'tools_page_revertshield' );
$notice = $capture(
	static function () {
		( new MultisiteNotice() )->render();
	}
);
$assert( false !== strpos( $notice, 'Multisite safety mode' ), 'Multisite admin safety notice did not render.' );
$assert( false !== strpos( $notice, 'network-wide health validation' ), 'Multisite admin notice did not explain the mutation boundary.' );

restore_current_blog();

if ( get_current_blog_id() !== $main_site_id ) {
	switch_to_blog( $main_site_id );
}

$assert( $main_change_count === ( new ChangeRepository() )->count(), 'Secondary-site ledger write leaked into the main-site ledger.' );
$assert( $main_health_count === ( new HealthChecker( array() ) )->count(), 'Secondary-site health write leaked into the main-site health table.' );

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink -- Runtime test fixture cleanup.
@unlink( $fixture_path );
@rmdir( $fixture_dir );

echo "RevertShield Multisite runtime assertions passed.\n";
