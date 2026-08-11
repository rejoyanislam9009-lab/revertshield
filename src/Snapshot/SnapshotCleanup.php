<?php
/**
 * Snapshot expiration cleanup.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

/**
 * Removes expired snapshot storage in bounded daily batches.
 */
final class SnapshotCleanup {
	const HOOK = 'revertshield_daily_snapshot_cleanup';

	/** @var SnapshotRepository */
	private $repository;

	/** @var SnapshotStorage */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository|null $repository Optional snapshot repository.
	 * @param SnapshotStorage|null    $storage    Optional storage service.
	 */
	public function __construct( SnapshotRepository $repository = null, SnapshotStorage $storage = null ) {
		$this->repository = $repository ? $repository : new SnapshotRepository();
		$this->storage    = $storage ? $storage : new SnapshotStorage();
	}

	/**
	 * Register cleanup hook and ensure scheduling.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK, array( $this, 'run' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	/**
	 * Schedule daily cleanup.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'daily', self::HOOK );
		}
	}

	/**
	 * Remove the scheduled cleanup hook.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Expire a bounded set of snapshots.
	 *
	 * Storage is removed before metadata is moved to `expired`. If storage
	 * deletion fails, metadata remains recoverable and cleanup retries later.
	 *
	 * @return void
	 */
	public function run() {
		$candidates = $this->repository->expired_candidates( 25 );

		foreach ( $candidates as $candidate ) {
			if ( empty( $candidate['snapshot_uuid'] ) ) {
				continue;
			}

			$snapshot_uuid = sanitize_text_field( $candidate['snapshot_uuid'] );

			if ( $this->storage->delete( $snapshot_uuid ) ) {
				$this->repository->mark_expired( $snapshot_uuid );
			}
		}
	}
}
