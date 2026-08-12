<?php
/**
 * Scoped recovery eligibility checks.
 *
 * @package RevertShield
 */

namespace RevertShield\Recovery;

use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Snapshot\SnapshotState;
use RevertShield\Snapshot\SnapshotVerifier;

/**
 * Validates whether a verified plugin snapshot is eligible for manual recovery.
 */
final class RecoveryEligibility {
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
	 * Validate a snapshot for an explicit recovery of one installed plugin.
	 *
	 * This gate does not restore files. It only proves that the requested
	 * snapshot is ready, targets the requested plugin, remains unexpired, and
	 * independently passes integrity verification immediately before recovery.
	 * Verification failures block recovery without mutating snapshot state.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @param string $plugin_file   Installed plugin basename.
	 * @return array|\WP_Error Verified snapshot summary or an error.
	 */
	public function validate( $snapshot_uuid, $plugin_file ) {
		$snapshot = $this->repository->find( $snapshot_uuid );
		if ( ! $snapshot ) {
			return new \WP_Error(
				'revertshield_recovery_snapshot_missing',
				__( 'The requested recovery snapshot could not be found.', 'revertshield' )
			);
		}

		if ( ! isset( $snapshot['state'] ) || SnapshotState::READY !== $snapshot['state'] ) {
			return new \WP_Error(
				'revertshield_recovery_snapshot_not_ready',
				__( 'Only a ready snapshot can be used for recovery.', 'revertshield' )
			);
		}

		if (
			! isset( $snapshot['component_type'], $snapshot['component_name'] ) ||
			'plugin' !== $snapshot['component_type'] ||
			wp_normalize_path( (string) $snapshot['component_name'] ) !== wp_normalize_path( (string) $plugin_file )
		) {
			return new \WP_Error(
				'revertshield_recovery_snapshot_target_mismatch',
				__( 'The recovery snapshot does not belong to the requested plugin.', 'revertshield' )
			);
		}

		if ( empty( $snapshot['expires_at'] ) || strtotime( $snapshot['expires_at'] . ' UTC' ) <= time() ) {
			return new \WP_Error(
				'revertshield_recovery_snapshot_expired',
				__( 'The recovery snapshot has expired and cannot be restored.', 'revertshield' )
			);
		}

		return $this->verifier->verify( $snapshot_uuid );
	}
}
