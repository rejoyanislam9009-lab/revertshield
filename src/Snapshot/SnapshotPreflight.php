<?php
/**
 * Snapshot storage preflight checks.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

/**
 * Verifies the uploads storage boundary before snapshot work begins.
 */
final class SnapshotPreflight {
	/**
	 * Run storage checks.
	 *
	 * @param int $required_bytes Estimated bytes required by the snapshot.
	 * @return array|\WP_Error Preflight information or an error.
	 */
	public function check( $required_bytes = 0 ) {
		$required_bytes = max( 0, (int) $required_bytes );
		$uploads        = wp_upload_dir( null, false );

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return new \WP_Error(
				'revertshield_uploads_unavailable',
				__( 'WordPress could not resolve a usable uploads directory.', 'revertshield' )
			);
		}

		$base_dir = wp_normalize_path( $uploads['basedir'] );
		if ( ! is_dir( $base_dir ) || ! wp_is_writable( $base_dir ) ) {
			return new \WP_Error(
				'revertshield_uploads_not_writable',
				__( 'The WordPress uploads directory is not writable.', 'revertshield' )
			);
		}

		$free_bytes = null;
		if ( function_exists( 'disk_free_space' ) ) {
			$detected = disk_free_space( $base_dir );
			if ( false !== $detected ) {
				$free_bytes = max( 0, (int) $detected );
			}
		}

		$required_with_margin = $required_bytes > 0
			? (int) ceil( ( $required_bytes * 1.10 ) + ( 5 * MB_IN_BYTES ) )
			: 0;

		if ( null !== $free_bytes && $required_with_margin > 0 && $free_bytes < $required_with_margin ) {
			return new \WP_Error(
				'revertshield_insufficient_disk_space',
				__( 'There is not enough free disk space to prepare this snapshot safely.', 'revertshield' ),
				array(
					'required_bytes' => $required_with_margin,
					'free_bytes'     => $free_bytes,
				)
			);
		}

		return array(
			'uploads_base'   => untrailingslashit( $base_dir ),
			'required_bytes' => $required_with_margin,
			'free_bytes'     => $free_bytes,
		);
	}
}
