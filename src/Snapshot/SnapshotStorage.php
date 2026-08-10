<?php
/**
 * Snapshot object storage.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

/**
 * Copies verified snapshot objects into a protected, uploads-bound directory.
 */
final class SnapshotStorage {
	/** @var StorageLocator */
	private $locator;

	/**
	 * Constructor.
	 *
	 * @param StorageLocator|null $locator Optional storage locator.
	 */
	public function __construct( StorageLocator $locator = null ) {
		$this->locator = $locator ? $locator : new StorageLocator();
	}

	/**
	 * Materialize a snapshot as extensionless, content-addressed objects.
	 *
	 * Original PHP filenames are not copied into uploads. Each file is stored as
	 * `objects/<sha256>` and the relative component path remains in the manifest.
	 *
	 * @param string           $snapshot_uuid Snapshot UUID.
	 * @param SnapshotManifest $manifest      Prepared snapshot manifest.
	 * @param string           $source_root   Canonical source component root.
	 * @return array|\WP_Error Storage details or an error.
	 */
	public function materialize( $snapshot_uuid, SnapshotManifest $manifest, $source_root ) {
		$location = $this->locator->locate( $snapshot_uuid );
		if ( is_wp_error( $location ) ) {
			return $location;
		}

		$source_root = realpath( $source_root );
		if ( false === $source_root || ! is_dir( $source_root ) ) {
			return new \WP_Error(
				'revertshield_snapshot_source_unavailable',
				__( 'The snapshot source directory could not be resolved.', 'revertshield' )
			);
		}

		$source_root = untrailingslashit( wp_normalize_path( $source_root ) );
		$filesystem  = $this->filesystem();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}

		$guarded = $this->prepare_private_root( $filesystem, $location['uploads_base'] );
		if ( is_wp_error( $guarded ) ) {
			return $guarded;
		}

		$snapshot_dir = $location['absolute'];
		$objects_dir  = trailingslashit( $snapshot_dir ) . 'objects';

		if ( $filesystem->exists( $snapshot_dir ) ) {
			return new \WP_Error(
				'revertshield_snapshot_storage_exists',
				__( 'The generated snapshot storage directory already exists.', 'revertshield' )
			);
		}

		if ( ! wp_mkdir_p( $objects_dir ) ) {
			return new \WP_Error(
				'revertshield_snapshot_directory_failed',
				__( 'RevertShield could not create snapshot storage directories.', 'revertshield' )
			);
		}

		$manifest_data = $manifest->to_array();
		foreach ( $manifest_data['files'] as $file ) {
			$stored = $this->store_object( $filesystem, $source_root, $objects_dir, $file );
			if ( is_wp_error( $stored ) ) {
				$this->delete( $snapshot_uuid );
				return $stored;
			}
		}

		$manifest_json = $manifest->to_json();
		if ( false === $manifest_json ) {
			$this->delete( $snapshot_uuid );
			return new \WP_Error(
				'revertshield_manifest_encoding_failed',
				__( 'RevertShield could not encode the snapshot manifest.', 'revertshield' )
			);
		}

		$manifest_written = $this->write_manifest( $filesystem, $snapshot_dir, $snapshot_uuid, $manifest_json );
		if ( is_wp_error( $manifest_written ) ) {
			$this->delete( $snapshot_uuid );
			return $manifest_written;
		}

		return array(
			'relative'        => $location['relative'],
			'object_count'    => count( $manifest_data['files'] ),
			'manifest_sha256' => hash( 'sha256', $manifest_json ),
		);
	}

	/**
	 * Remove one generated snapshot directory.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return bool
	 */
	public function delete( $snapshot_uuid ) {
		$location = $this->locator->locate( $snapshot_uuid );
		if ( is_wp_error( $location ) ) {
			return false;
		}

		$filesystem = $this->filesystem();
		if ( is_wp_error( $filesystem ) ) {
			return false;
		}

		if ( ! $filesystem->exists( $location['absolute'] ) ) {
			return true;
		}

		return (bool) $filesystem->delete( $location['absolute'], true );
	}

	/**
	 * Copy and verify one content-addressed object.
	 *
	 * @param \WP_Filesystem_Base $filesystem  WordPress filesystem instance.
	 * @param string              $source_root Canonical source root.
	 * @param string              $objects_dir Snapshot objects directory.
	 * @param array               $file        Manifest file entry.
	 * @return true|\WP_Error True or an error.
	 */
	private function store_object( $filesystem, $source_root, $objects_dir, array $file ) {
		$relative = isset( $file['path'] ) ? ltrim( wp_normalize_path( (string) $file['path'] ), '/' ) : '';
		$sha256   = isset( $file['sha256'] ) ? strtolower( (string) $file['sha256'] ) : '';

		if ( '' === $relative || 0 !== validate_file( $relative ) || ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
			return new \WP_Error(
				'revertshield_snapshot_manifest_path_invalid',
				__( 'A snapshot manifest file entry is invalid.', 'revertshield' )
			);
		}

		$source = realpath( trailingslashit( $source_root ) . $relative );
		if ( false === $source || ! is_file( $source ) ) {
			return new \WP_Error(
				'revertshield_snapshot_source_file_missing',
				__( 'A source file changed or disappeared during snapshot creation.', 'revertshield' )
			);
		}

		$source = wp_normalize_path( $source );
		if ( ! $this->is_within_root( $source, $source_root ) ) {
			return new \WP_Error(
				'revertshield_snapshot_source_escape',
				__( 'A source file escaped the expected component directory.', 'revertshield' )
			);
		}

		$source_hash = hash_file( 'sha256', $source );
		if ( false === $source_hash || ! hash_equals( $sha256, strtolower( $source_hash ) ) ) {
			return new \WP_Error(
				'revertshield_snapshot_source_changed',
				__( 'A source file changed after snapshot inventory was prepared.', 'revertshield' )
			);
		}

		$destination = trailingslashit( $objects_dir ) . $sha256;
		if ( $filesystem->exists( $destination ) ) {
			$existing_hash = hash_file( 'sha256', $destination );
			if ( false !== $existing_hash && hash_equals( $sha256, strtolower( $existing_hash ) ) ) {
				return true;
			}

			return new \WP_Error(
				'revertshield_snapshot_object_collision',
				__( 'An existing snapshot object failed its integrity check.', 'revertshield' )
			);
		}

		if ( ! $filesystem->copy( $source, $destination, false ) ) {
			return new \WP_Error(
				'revertshield_snapshot_copy_failed',
				__( 'RevertShield could not copy a snapshot object.', 'revertshield' )
			);
		}

		$destination_hash = hash_file( 'sha256', $destination );
		if ( false === $destination_hash || ! hash_equals( $sha256, strtolower( $destination_hash ) ) ) {
			$filesystem->delete( $destination, false );
			return new \WP_Error(
				'revertshield_snapshot_integrity_failed',
				__( 'A copied snapshot object failed its integrity check.', 'revertshield' )
			);
		}

		return true;
	}

	/**
	 * Write manifest through a same-directory temporary file and atomic move.
	 *
	 * @param \WP_Filesystem_Base $filesystem    WordPress filesystem instance.
	 * @param string              $snapshot_dir  Snapshot directory.
	 * @param string              $snapshot_uuid Snapshot UUID.
	 * @param string              $manifest_json Manifest JSON.
	 * @return true|\WP_Error True or an error.
	 */
	private function write_manifest( $filesystem, $snapshot_dir, $snapshot_uuid, $manifest_json ) {
		$temporary = trailingslashit( $snapshot_dir ) . '.manifest-' . strtolower( $snapshot_uuid ) . '.tmp';
		$final     = trailingslashit( $snapshot_dir ) . 'manifest.json';

		if ( ! $filesystem->put_contents( $temporary, $manifest_json ) ) {
			return new \WP_Error(
				'revertshield_snapshot_manifest_write_failed',
				__( 'RevertShield could not write the snapshot manifest.', 'revertshield' )
			);
		}

		if ( ! $filesystem->move( $temporary, $final, true ) ) {
			$filesystem->delete( $temporary, false );
			return new \WP_Error(
				'revertshield_snapshot_manifest_commit_failed',
				__( 'RevertShield could not commit the snapshot manifest atomically.', 'revertshield' )
			);
		}

		$written = $filesystem->get_contents( $final );
		if ( false === $written || ! hash_equals( hash( 'sha256', $manifest_json ), hash( 'sha256', $written ) ) ) {
			return new \WP_Error(
				'revertshield_snapshot_manifest_integrity_failed',
				__( 'The stored snapshot manifest failed its integrity check.', 'revertshield' )
			);
		}

		return true;
	}

	/**
	 * Prepare web-server guard files at the RevertShield uploads root.
	 *
	 * Objects are extensionless as the primary execution-safety boundary. These
	 * guard files additionally deny HTTP access on common Apache/IIS setups.
	 *
	 * @param \WP_Filesystem_Base $filesystem   WordPress filesystem instance.
	 * @param string              $uploads_base Canonical uploads base.
	 * @return true|\WP_Error True or an error.
	 */
	private function prepare_private_root( $filesystem, $uploads_base ) {
		$root = trailingslashit( $uploads_base ) . 'revertshield';
		if ( ! wp_mkdir_p( $root ) ) {
			return new \WP_Error(
				'revertshield_snapshot_root_failed',
				__( 'RevertShield could not create its snapshot storage root.', 'revertshield' )
			);
		}

		$guards = array(
			'.htaccess'  => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.html' => "<!doctype html>\n<meta charset=\"utf-8\">\n",
		);

		foreach ( $guards as $filename => $contents ) {
			$path = trailingslashit( $root ) . $filename;
			if ( ! $filesystem->exists( $path ) && ! $filesystem->put_contents( $path, $contents ) ) {
				return new \WP_Error(
					'revertshield_snapshot_guard_failed',
					__( 'RevertShield could not protect its snapshot storage root.', 'revertshield' )
				);
			}
		}

		return true;
	}

	/**
	 * Initialize a local direct WordPress filesystem implementation.
	 *
	 * The snapshot writer currently fails closed on FTP/SSH filesystem methods
	 * because source and destination integrity checks require canonical local paths.
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
				__( 'Snapshot creation currently requires direct local filesystem access.', 'revertshield' )
			);
		}

		return $wp_filesystem;
	}

	/**
	 * Check that a canonical path remains inside a canonical root.
	 *
	 * @param string $path Canonical candidate path.
	 * @param string $root Canonical root path.
	 * @return bool
	 */
	private function is_within_root( $path, $root ) {
		$path = wp_normalize_path( $path );
		$root = untrailingslashit( wp_normalize_path( $root ) );

		return 0 === strpos( $path, trailingslashit( $root ) );
	}
}
