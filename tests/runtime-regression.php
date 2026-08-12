<?php
/**
 * RevertShield runtime regression assertions executed inside a real WordPress install.
 *
 * Run with: wp eval-file tests/runtime-regression.php
 *
 * @package RevertShield
 */

use RevertShield\Admin\AdminNavigation;
use RevertShield\Admin\AdminPage;
use RevertShield\Admin\GuardedUpdateAdminPage;
use RevertShield\Admin\RecoveryAdminPage;
use RevertShield\Admin\SnapshotAdminPage;
use RevertShield\Database\Tables;
use RevertShield\Health\HealthChecker;
use RevertShield\Ledger\ChangeRepository;
use RevertShield\Recovery\PluginRecoveryService;
use RevertShield\Recovery\RecoveryEligibility;
use RevertShield\Snapshot\PluginSnapshotService;
use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Snapshot\SnapshotVerifier;
use RevertShield\Update\GuardedPluginUpdateService;
use RevertShield\Update\SafeUpdateGate;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress did not bootstrap.' );
}

global $wpdb;

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

wp_set_current_user( 1 );

$fixture_slug = 'revertshield-fixture';
$fixture_file = $fixture_slug . '/revertshield-fixture.php';
$fixture_dir  = trailingslashit( WP_PLUGIN_DIR ) . $fixture_slug;
$nested_dir   = trailingslashit( $fixture_dir ) . 'includes';
$main_path    = trailingslashit( $fixture_dir ) . 'revertshield-fixture.php';
$data_path    = trailingslashit( $nested_dir ) . 'data.txt';

$version_one = "<?php\n/**\n * Plugin Name: RevertShield Fixture\n * Version: 1.0.0\n */\n";
$version_two = "<?php\n/**\n * Plugin Name: RevertShield Fixture\n * Version: 2.0.0\n */\n";
$data_one    = "fixture-snapshot-content\n";
$data_two    = "changed-after-snapshot\n";

wp_mkdir_p( $nested_dir );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Runtime test fixture setup.
file_put_contents( $main_path, $version_one );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Runtime test fixture setup.
file_put_contents( $data_path, $data_one );
wp_clean_plugins_cache( false );

$plugins = get_plugins();
$assert( isset( $plugins[ $fixture_file ] ), 'Runtime fixture plugin was not detected by WordPress.' );
$assert( '1.0.0' === $plugins[ $fixture_file ]['Version'], 'Runtime fixture plugin version is incorrect.' );

$snapshots        = new SnapshotRepository();
$snapshot_service = new PluginSnapshotService();
$verifier         = new SnapshotVerifier( $snapshots );
$update_gate      = new SafeUpdateGate( $snapshots, $verifier );
$recovery_gate    = new RecoveryEligibility( $snapshots, $verifier );
$health           = new HealthChecker();
$ledger           = new ChangeRepository();
$recovery         = new PluginRecoveryService( $recovery_gate, $snapshots, null, null, null, $health, $ledger );
$guarded_update   = new GuardedPluginUpdateService( $update_gate, null, $health, $ledger );

$snapshot = $snapshot_service->create( $fixture_file );
$assert( ! is_wp_error( $snapshot ), 'Verified fixture snapshot creation failed.' );
$assert( true === $snapshot['verified'], 'Created snapshot was not independently verified.' );
$assert( 'ready' === $snapshot['state'], 'Created snapshot did not reach ready state.' );
$assert( $fixture_file === $snapshot['component_name'], 'Snapshot target does not match fixture plugin.' );
$assert( 2 === (int) $snapshot['object_count'], 'Snapshot did not contain the expected fixture files.' );

$snapshot_uuid = $snapshot['snapshot_uuid'];
$snapshot_row  = $snapshots->find( $snapshot_uuid );
$assert( is_array( $snapshot_row ), 'Ready snapshot metadata could not be reloaded.' );
$assert( 'ready' === $snapshot_row['state'], 'Persisted snapshot state is not ready.' );

$verified = $verifier->verify( $snapshot_uuid );
$assert( ! is_wp_error( $verified ) && true === $verified['verified'], 'Independent snapshot verification failed.' );

$update_allowed = $update_gate->validate( $snapshot_uuid, $fixture_file );
$assert( ! is_wp_error( $update_allowed ), 'Matching ready snapshot did not pass the guarded-update gate.' );

$update_mismatch = $update_gate->validate( $snapshot_uuid, 'different-plugin/different.php' );
$assert_error(
	$update_mismatch,
	'revertshield_update_snapshot_target_mismatch',
	'Guarded-update gate did not reject a target mismatch.'
);

$recovery_allowed = $recovery_gate->validate( $snapshot_uuid, $fixture_file );
$assert( ! is_wp_error( $recovery_allowed ), 'Matching ready snapshot did not pass the recovery gate.' );

$recovery_mismatch = $recovery_gate->validate( $snapshot_uuid, 'different-plugin/different.php' );
$assert_error(
	$recovery_mismatch,
	'revertshield_recovery_snapshot_target_mismatch',
	'Recovery gate did not reject a target mismatch.'
);

$preparing_uuid = wp_generate_uuid4();
$reserved       = $snapshots->reserve(
	$preparing_uuid,
	'plugin',
	$fixture_file,
	'revertshield-test/' . $preparing_uuid,
	gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS )
);
$assert( ! is_wp_error( $reserved ), 'Preparing-state regression fixture could not be reserved.' );

$not_ready_update = $update_gate->validate( $preparing_uuid, $fixture_file );
$assert_error(
	$not_ready_update,
	'revertshield_update_snapshot_not_ready',
	'Guarded-update gate did not reject a preparing snapshot.'
);

$not_ready_recovery = $recovery_gate->validate( $preparing_uuid, $fixture_file );
$assert_error(
	$not_ready_recovery,
	'revertshield_recovery_snapshot_not_ready',
	'Recovery gate did not reject a preparing snapshot.'
);
$snapshots->mark_failed( $preparing_uuid );

$invalid_path = $snapshots->reserve(
	wp_generate_uuid4(),
	'plugin',
	$fixture_file,
	'../escape',
	gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS )
);
$assert_error(
	$invalid_path,
	'revertshield_invalid_snapshot_path',
	'Snapshot repository accepted a traversal storage path.'
);

set_site_transient(
	'update_plugins',
	(object) array(
		'response' => array(),
	),
	HOUR_IN_SECONDS
);
$guarded_unavailable = $guarded_update->execute( $fixture_file, $snapshot_uuid );
$assert_error(
	$guarded_unavailable,
	'revertshield_guarded_update_unavailable',
	'Guarded update did not fail closed when WordPress reported no update.'
);

$self_update = $guarded_update->execute( plugin_basename( REVERTSHIELD_FILE ), wp_generate_uuid4() );
$assert_error(
	$self_update,
	'revertshield_guarded_self_update_disabled',
	'Guarded RevertShield self-update was not blocked.'
);

$self_recovery = $recovery->execute( plugin_basename( REVERTSHIELD_FILE ), wp_generate_uuid4() );
$assert_error(
	$self_recovery,
	'revertshield_recovery_self_restore_disabled',
	'RevertShield self-recovery was not blocked.'
);

$recovery_admin = new RecoveryAdminPage( $snapshots, $recovery );
$lock_method    = new ReflectionMethod( $recovery_admin, 'acquire_lock' );
$lock_method->setAccessible( true );
delete_option( 'revertshield_recovery_lock' );
add_option( 'revertshield_recovery_lock', time(), '', false );
$lock_result = $lock_method->invoke( $recovery_admin );
$assert_error(
	$lock_result,
	'revertshield_recovery_already_running',
	'Recovery concurrency lock did not block a second recovery.'
);
delete_option( 'revertshield_recovery_lock' );

if ( ! function_exists( 'set_current_screen' ) ) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
}
set_current_screen( 'tools_page_revertshield' );

$navigation = $capture(
	static function () {
		( new AdminNavigation() )->render();
	}
);
$assert( false !== strpos( $navigation, 'Dashboard' ), 'Admin navigation is missing Dashboard.' );
$assert( false !== strpos( $navigation, 'Snapshots' ), 'Admin navigation is missing Snapshots.' );
$assert( false !== strpos( $navigation, 'Updates' ), 'Admin navigation is missing Updates.' );
$assert( false !== strpos( $navigation, 'Recovery' ), 'Admin navigation is missing Recovery.' );
$assert( false !== strpos( $navigation, 'nav-tab-active' ), 'Admin navigation did not mark the current screen active.' );

$dashboard_html = $capture(
	static function () use ( $ledger, $health ) {
		( new AdminPage( $ledger, $health ) )->render();
	}
);
$assert( false !== strpos( $dashboard_html, 'RevertShield' ), 'Dashboard render smoke failed.' );

$snapshot_html = $capture(
	static function () use ( $snapshots, $snapshot_service, $ledger ) {
		( new SnapshotAdminPage( $snapshots, $snapshot_service, $ledger ) )->render();
	}
);
$assert( false !== strpos( $snapshot_html, 'Verified Plugin Snapshots' ), 'Snapshot screen render smoke failed.' );

$updates_html = $capture(
	static function () use ( $snapshots, $guarded_update ) {
		( new GuardedUpdateAdminPage( $snapshots, $guarded_update ) )->render();
	}
);
$assert( false !== strpos( $updates_html, 'Guarded Plugin Updates' ), 'Guarded Updates screen render smoke failed.' );

$recovery_html = $capture(
	static function () use ( $recovery_admin ) {
		$recovery_admin->render();
	}
);
$assert( false !== strpos( $recovery_html, 'Plugin Recovery' ), 'Recovery screen render smoke failed.' );
$assert( false !== strpos( $recovery_html, 'confirm_recovery' ), 'Recovery screen is missing explicit confirmation control.' );

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Runtime mutation before recovery test.
file_put_contents( $main_path, $version_two );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Runtime mutation before recovery test.
file_put_contents( $data_path, $data_two );
wp_clean_plugins_cache( false );

$plugins = get_plugins();
$assert( '2.0.0' === $plugins[ $fixture_file ]['Version'], 'Fixture mutation was not visible before recovery.' );

$http_mock = static function ( $preempt, $parsed_args, $url ) {
	unset( $parsed_args );
	if ( home_url( '/' ) !== $url ) {
		return $preempt;
	}

	return array(
		'headers'  => array(),
		'body'     => 'ok',
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $http_mock, 10, 3 );

$recovery_result = $recovery->execute( $fixture_file, $snapshot_uuid );
remove_filter( 'pre_http_request', $http_mock, 10 );

$assert( ! is_wp_error( $recovery_result ), 'Manual scoped recovery execution failed.' );
$assert( 'pass' === $recovery_result['health_status'], 'Post-recovery health check did not pass under the deterministic HTTP fixture.' );
$assert( '1.0.0' === $recovery_result['to_version'], 'Recovery did not target the snapshotted plugin version.' );

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Runtime recovery verification.
$restored_main = file_get_contents( $main_path );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Runtime recovery verification.
$restored_data = file_get_contents( $data_path );
$assert( $version_one === $restored_main, 'Recovery did not restore the original plugin main file exactly.' );
$assert( $data_one === $restored_data, 'Recovery did not restore the original nested file exactly.' );

wp_clean_plugins_cache( false );
$plugins = get_plugins();
$assert( '1.0.0' === $plugins[ $fixture_file ]['Version'], 'Recovered plugin version is not the snapshotted version.' );

$latest_health = $health->latest();
$assert( is_array( $latest_health ), 'Post-recovery health result was not persisted.' );
$assert( 'pass' === $latest_health['status'], 'Persisted post-recovery health status is not pass.' );
$assert( 200 === (int) $latest_health['http_code'], 'Persisted post-recovery HTTP status is not 200.' );

$events      = $ledger->recent( 30 );
$event_types = wp_list_pluck( $events, 'event_type' );
$assert( in_array( 'recovery_started', $event_types, true ), 'Recovery-start ledger event was not persisted.' );
$assert( in_array( 'recovery_healthy', $event_types, true ), 'Recovery-healthy ledger event was not persisted.' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Runtime regression mutates isolated test metadata to prove expiration gating.
$wpdb->update(
	Tables::snapshots(),
	array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ),
	array( 'snapshot_uuid' => $snapshot_uuid ),
	array( '%s' ),
	array( '%s' )
);

$expired_update = $update_gate->validate( $snapshot_uuid, $fixture_file );
$assert_error(
	$expired_update,
	'revertshield_update_snapshot_expired',
	'Guarded-update gate did not reject an expired snapshot.'
);

$expired_recovery = $recovery_gate->validate( $snapshot_uuid, $fixture_file );
$assert_error(
	$expired_recovery,
	'revertshield_recovery_snapshot_expired',
	'Recovery gate did not reject an expired snapshot.'
);

$tamper_snapshot = $snapshot_service->create( $fixture_file );
$assert( ! is_wp_error( $tamper_snapshot ), 'Tamper-detection snapshot creation failed.' );
$tamper_row = $snapshots->find( $tamper_snapshot['snapshot_uuid'] );
$assert( is_array( $tamper_row ), 'Tamper-detection snapshot metadata is missing.' );
$tamper_manifest = json_decode( $tamper_row['manifest'], true );
$assert( is_array( $tamper_manifest ) && ! empty( $tamper_manifest['files'] ), 'Tamper-detection manifest is invalid.' );

$uploads     = wp_upload_dir( null, false );
$first_hash  = $tamper_manifest['files'][0]['sha256'];
$object_path = trailingslashit( $uploads['basedir'] ) . trailingslashit( $tamper_row['storage_relpath'] ) . 'objects/' . $first_hash;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Intentional integrity-corruption regression fixture.
file_put_contents( $object_path, "tampered-object\n" );

$tampered_verify = $verifier->verify( $tamper_snapshot['snapshot_uuid'] );
$assert( is_wp_error( $tampered_verify ), 'Snapshot verifier accepted a tampered stored object.' );
$assert(
	in_array(
		$tampered_verify->get_error_code(),
		array( 'revertshield_snapshot_object_invalid', 'revertshield_snapshot_size_mismatch' ),
		true
	),
	'Tampered snapshot returned an unexpected verification error.'
);

$tampered_update = $update_gate->validate( $tamper_snapshot['snapshot_uuid'], $fixture_file );
$assert( is_wp_error( $tampered_update ), 'Guarded-update gate accepted a tampered snapshot.' );

$tampered_recovery = $recovery_gate->validate( $tamper_snapshot['snapshot_uuid'], $fixture_file );
$assert( is_wp_error( $tampered_recovery ), 'Recovery gate accepted a tampered snapshot.' );

WP_CLI::success( 'RevertShield runtime regression checks passed.' );
