<?php
/**
 * Snapshot integrity verification.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

/**
 * Independently verifies snapshot metadata, manifest, and stored objects.
 */
final class SnapshotVerifier {
	/** @var SnapshotRepository */
	private $repository;

	/** @var StorageLocator */
	private $locator;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository|null $repository Optional metadata repository.
	 * @param StorageLocator|null     $locator    Optional storage locator.
	 */
	public function __construct( SnapshotRepository $repository = null, StorageLocator $locator = null ) {
		$this->repository = $repository ? $repository : new SnapshotRepository();
		$this->locator    = $locator ? $locator : new StorageLocator();
	}

	/**
	 * Verify a ready snapshot against both persisted manifests and all objects.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return array|\WP_Error Verification summary or an error.
	 */
	public function verify( $snapshot_uuid ) {
		$snapshot = $this->repository->find( $snapshot_uuid );
		if ( ! $snapshot ) {
			return new \WP_Error(
				'revertshield_snapshot_not_found',
				__( 'The requested snapshot metadata could not be found.', 'revertshield' )
			);
		}

		if ( SnapshotState::READY !== $snapshot['state'] ) {
			return new \WP_Error(
				'revertshield_snapshot_not_ready',
				__( 'Only ready snapshots can pass recovery integrity verification.', 'revertshield' )
			);
		}

		$location = $this->locator->locate( $snapshot_uuid );
		if ( is_wp_error( $location ) ) {
			return $location;
		}

		if ( wp_normalize_path( $snapshot['storage_relpath'] ) !== wp_normalize_path( $location['relative'] ) ) {
			return new \WP_Error(
				'revertshield_snapshot_storage_mismatch',
				__( 'The snapshot storage location does not match its generated identifier.', 'revertshield' )
			);
		}

		$filesystem = $this->filesystem();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}

		$manifest_path = trailingslashit( $location['absolute'] ) . 'manifest.json';
		$stored_json   = $filesystem->get_contents( $manifest_path );
		$db_json       = isset( $snapshot['manifest'] ) ? (string) $snapshot['manifest'] : '';

		if ( false === $stored_json || '' === $db_json ) {
			return new \WP_Error(
				'revertshield_snapshot_manifest_missing',
				__( 'The snapshot manifest is missing from storage or metadata.', 'revertshield' )
			);
		}

		if ( ! hash_equals( hash( 'sha256', $db_json ), hash( 'sha256', $stored_json ) ) ) {
			return new \WP_Error(
				'revertshield_snapshot_manifest_mismatch',
				__( 'The stored snapshot manifest does not match the database manifest.', 'revertshield' )
			);
		}

		$manifest = json_decode( $stored_json, true );
		if ( ! is_array( $manifest ) || SnapshotManifest::FORMAT_VERSION !== (int) $manifest['format_version'] || empty( $manifest['files'] ) || ! is_array( $manifest['files'] ) ) {
			return new \WP_Error(
				'revertshield_snapshot_manifest_invalid',
				__( 'The snapshot manifest format is invalid.', 'revertshield' )
			);
		}

		$objects_dir = trailingslashit( $location['absolute'] ) . 'objects';
		$total_size  = 0;

		foreach ( $manifest['files'] as $file ) {
			$verified = $this->verify_object( $filesystem, $objects_dir, $file );
			if ( is_wp_error( $verified ) ) {
				return $verified;
			}

			$total_size += $verified;
		}

		if ( $total_size !== (int) $snapshot['size_bytes'] || $total_size !== (int) $manifest['total_size'] ) {
			return new \WP_Error(
				'revertshield_snapshot_size_mismatch',
				__( 'The verified snapshot size does not match its manifest metadata.', 'revertshield' )
			);
		}

		return array(
			'snapshot_uuid'   => strtolower( $snapshot_uuid ),
			'component_type'  => $snapshot['component_type'],
			'component_name'  => $snapshot['component_name'],
			'object_count'    => count( $manifest['files'] ),
			'size_bytes'      => $total_size,
			'manifest_sha256' => hash( 'sha256', $stored_json ),
			'verified'        => true,
		);
	}

	/**
	 * Verify one extensionless content-addressed object.
	 *
	 * @param \WP_Filesystem_Base $filesystem  WordPress filesystem instance.
	 * @param string              $objects_dir Objects directory.
	 * @param mixed               $file        Manifest entry.
	 * @return int|\WP_Error Represented bytes or an error.
	 */
	private function verify_object( $filesystem, $objects_dir, $file ) {
		if ( ! is_array( $file ) ) {
			return new \WP_Error(
				'revertshield_snapshot_file_entry_invalid',
				__( 'A snapshot file entry is invalid.', 'revertshield' )
			);
		}

		$relative = isset( $file['path'] ) ? ltrim( wp_normalize_path( (string) $file['path'] ), '/' ) : '';
		$sha256   = isset( $file['sha256'] ) ? strtolower( (string) $file['sha256'] ) : '';
		$size     = isset( $file['size'] ) ? max( 0, (int) $file['size'] ) : -1;

		if ( '' === $relative || 0 !== validate_file( $relative ) || ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) || $size < 0 ) {
			return new \WP_Error(
				'revertshield_snapshot_file_entry_invalid',
				__( 'A snapshot file entry is invalid.', 'revertshield' )
			);
		}

		$object = trailingslashit( $objects_dir ) . $sha256;
		if ( ! $filesystem->exists( $object ) || ! $filesystem->is_file( $object ) ) {
			return new \WP_Error(
				'revertshield_snapshot_object_missing',
				__( 'A snapshot object is missing.', 'revertshield' )
			);
		}

		$actual_hash = hash_file( 'sha256', $object );
		$actual_size = filesize( $object );

		if ( false === $actual_hash || false === $actual_size || ! hash_equals( $sha256, strtolower( $actual_hash ) ) || $size !== (int) $actual_size ) {
			return new \WP_Error(
				'revertshield_snapshot_object_invalid',
				__( 'A snapshot object failed its hash or size verification.', 'revertshield' )
			);
		}

		return $size;
	}

	/**
	 * Initialize direct local WordPress filesystem access.
	 *
	 * @return \WP_Filesystem_Base|\WP_Error Filesystem instance or an error.
	 */
	private function filesystem() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem() ) {
			return new \WP_Error(
				'revertshield_filesystem_unavailable',
				__( 'WordPress could not initialize filesystem access.', 'revertshield' )
			);
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem || ! isset( $wp_filesystem->method ) || 'direct' !== $wp_filesystem->method ) {
			return new \WP_Error(
				'revertshield_direct_filesystem_required',
				__( 'Snapshot verification currently requires direct local filesystem access.', 'revertshield' )
			);
		}

		return $wp_filesystem;
	}
}
