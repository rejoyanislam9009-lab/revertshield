<?php
/**
 * Runtime regression assertions for 0.9 operations and observability.
 *
 * @package RevertShield
 */

use RevertShield\Health\ScheduledHealthCheck;
use RevertShield\Snapshot\SnapshotPinStore;
use RevertShield\Snapshot\SnapshotRepository;

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$settings = get_option( 'revertshield_settings', array() );
$settings = is_array( $settings ) ? $settings : array();
$assert( array_key_exists( 'scheduled_health_enabled', $settings ), 'Scheduled health enabled default is missing.' );
$assert( array_key_exists( 'scheduled_health_interval', $settings ), 'Scheduled health interval default is missing.' );

$repository = new SnapshotRepository();
$pins       = new SnapshotPinStore( $repository );
$uuid       = wp_generate_uuid4();
$expired_at = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
$reserved   = $repository->reserve( $uuid, 'plugin', 'revertshield-operations-fixture/plugin.php', 'revertshield/snapshots/' . $uuid, $expired_at );
$assert( ! is_wp_error( $reserved ), 'Could not reserve operations fixture snapshot.' );
$assert( $repository->mark_failed( $uuid ), 'Could not move operations fixture snapshot to failed state.' );

$result = $pins->pin( $uuid );
$assert( true === $result, 'Could not pin operations fixture snapshot.' );
$assert( $pins->is_pinned( $uuid ), 'Pinned snapshot was not present in the site-scoped pin registry.' );
$pinned_row = $repository->find( $uuid );
$assert( is_array( $pinned_row ) && null === $pinned_row['expires_at'], 'Pinned snapshot expiration was not suspended.' );

$candidates = $repository->expired_candidates( 100 );
$candidate_ids = array_map(
	static function ( $row ) {
		return isset( $row['snapshot_uuid'] ) ? $row['snapshot_uuid'] : '';
	},
	$candidates
);
$assert( ! in_array( $uuid, $candidate_ids, true ), 'Pinned snapshot remained eligible for retention cleanup.' );

$result = $pins->unpin( $uuid );
$assert( true === $result, 'Could not unpin operations fixture snapshot.' );
$assert( ! $pins->is_pinned( $uuid ), 'Unpinned snapshot remained in the pin registry.' );
$unpinned_row = $repository->find( $uuid );
$assert( is_array( $unpinned_row ) && $expired_at === $unpinned_row['expires_at'], 'Unpin did not restore the original expiration timestamp.' );

$candidates = $repository->expired_candidates( 100 );
$candidate_ids = array_map(
	static function ( $row ) {
		return isset( $row['snapshot_uuid'] ) ? $row['snapshot_uuid'] : '';
	},
	$candidates
);
$assert( in_array( $uuid, $candidate_ids, true ), 'Unpinned expired snapshot did not return to cleanup eligibility.' );
$repository->mark_expired( $uuid );

$original_settings = $settings;
$settings['scheduled_health_enabled']  = 1;
$settings['scheduled_health_interval'] = 6;
update_option( 'revertshield_settings', $settings, false );
ScheduledHealthCheck::sync_schedule();
$event = wp_get_scheduled_event( ScheduledHealthCheck::HOOK );
$assert( $event && 'revertshield_six_hours' === $event->schedule, 'Six-hour scheduled health event was not registered.' );

$settings['scheduled_health_enabled'] = 0;
update_option( 'revertshield_settings', $settings, false );
ScheduledHealthCheck::sync_schedule();
$assert( false === wp_next_scheduled( ScheduledHealthCheck::HOOK ), 'Scheduled health event remained after disabling the feature.' );

update_option( 'revertshield_settings', $original_settings, false );
ScheduledHealthCheck::sync_schedule();

echo "RevertShield operations assertions passed.\n";
