<?php
/**
 * Safe update snapshot precondition.
 *
 * @package RevertShield
 */

namespace RevertShield\Update;

use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Snapshot\SnapshotState;
use RevertShield\Snapshot\SnapshotVerifier;

/**
 * Validates that a snapshot is eligible to guard a future plugin update.
 */
final class SafeUpdateGate {
	/** @var SnapshotRepository */
	private $repository;

	/** @var SnapshotVerifier */
	private $verifier;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository|null $repository Optional snapshot repository.
	 * @param SnapshotVerifier|null   $verifier   Optional integrity verifier.
	 */
	public function __construct( SnapshotRepository $repository = null, SnapshotVerifier $verifier = null ) {
		$this->repository = $repository ? $repository : new SnapshotRepository();
		$this->verifier   = $verifier ? $verifier : new SnapshotVerifier( $this->repository );
	}

	/**
	 * Validate a snapshot as a precondition for a specific plugin update.
	 *
	 * This method does not execute an update or a restore. Verification errors
	 * block the update but do not mutate snapshot state because some failures,
	 * such as temporary filesystem unavailability, are not evidence of corruption.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @param string $plugin_file   Installed plugin basename.
	 * @return array|\WP_Error Verified snapshot summary or an error.
	 */
	public function validate( $snapshot_uuid, $plugin_file ) {
		$snapshot = $this->repository->find( $snapshot_uuid );
		if ( ! $snapshot ) {
			return new \WP_Error(
				'revertshield_update_snapshot_missing',
				__( 'The required update snapshot could not be found.', 'revertshield' )
			);
		}

		if ( ! isset( $snapshot['state'] ) || SnapshotState::READY !== $snapshot['state'] ) {
			return new \WP_Error(
				'revertshield_update_snapshot_not_ready',
				__( 'The update snapshot is not in a ready state.', 'revertshield' )
			);
		}

		if (
			! isset( $snapshot['component_type'], $snapshot['component_name'] ) ||
			'plugin' !== $snapshot['component_type'] ||
			wp_normalize_path( (string) $snapshot['component_name'] ) !== wp_normalize_path( (string) $plugin_file )
		) {
			return new \WP_Error(
				'revertshield_update_snapshot_target_mismatch',
				__( 'The update snapshot does not belong to the requested plugin.', 'revertshield' )
			);
		}

		if ( empty( $snapshot['expires_at'] ) || strtotime( $snapshot['expires_at'] . ' UTC' ) <= time() ) {
			return new \WP_Error(
				'revertshield_update_snapshot_expired',
				__( 'The update snapshot has expired and cannot guard a plugin update.', 'revertshield' )
			);
		}

		return $this->verifier->verify( $snapshot_uuid );
	}
}
