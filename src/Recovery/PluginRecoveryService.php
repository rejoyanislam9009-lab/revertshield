<?php
/**
 * Scoped plugin recovery execution.
 *
 * @package RevertShield
 */

namespace RevertShield\Recovery;

use RevertShield\Health\HealthChecker;
use RevertShield\Ledger\ChangeRepository;
use RevertShield\Snapshot\PluginInventory;
use RevertShield\Snapshot\PluginSourceLocator;
use RevertShield\Snapshot\SnapshotManifest;
use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Snapshot\StorageLocator;

/**
 * Restores one installed plugin from an independently verified snapshot.
 */
final class PluginRecoveryService {
	/** @var RecoveryEligibility */
	private $eligibility;

	/** @var SnapshotRepository */
	private $snapshots;

	/** @var PluginSourceLocator */
	private $source_locator;

	/** @var StorageLocator */
	private $storage_locator;

	/** @var PluginInventory */
	private $inventory;

	/** @var HealthChecker */
	private $health;

	/** @var ChangeRepository */
	private $ledger;

	/**
	 * Constructor.
	 *
	 * @param RecoveryEligibility|null  $eligibility    Optional recovery eligibility gate.
	 * @param SnapshotRepository|null   $snapshots      Optional snapshot repository.
	 * @param PluginSourceLocator|null  $source_locator Optional plugin source locator.
	 * @param StorageLocator|null       $storage_locator Optional snapshot storage locator.
	 * @param PluginInventory|null      $inventory      Optional plugin inventory builder.
	 * @param HealthChecker|null        $health         Optional health checker.
	 * @param ChangeRepository|null     $ledger         Optional change ledger.
	 */
	public function __construct(
		RecoveryEligibility $eligibility = null,
		SnapshotRepository $snapshots = null,
		PluginSourceLocator $source_locator = null,
		StorageLocator $storage_locator = null,
		PluginInventory $inventory = null,
		HealthChecker $health = null,
		ChangeRepository $ledger = null
	) {
		$this->snapshots       = $snapshots ? $snapshots : new SnapshotRepository();
		$this->eligibility     = $eligibility ? $eligibility : new RecoveryEligibility( $this->snapshots );
		$this->source_locator  = $source_locator ? $source_locator : new PluginSourceLocator();
		$this->storage_locator = $storage_locator ? $storage_locator : new StorageLocator();
		$this->inventory       = $inventory ? $inventory : new PluginInventory( $this->source_locator );
		$this->health          = $health ? $health : new HealthChecker();
		$this->ledger          = $ledger ? $ledger : new ChangeRepository();
	}

	/**
	 * Restore one installed plugin from one verified snapshot.
	 *
	 * The requested plugin is never replaced until a complete staged copy of the
	 * snapshot passes hash and size verification. If the final file-integrity
	 * check fails, the pre-recovery plugin files are restored immediately.
	 * A failed homepage check is recorded but does not trigger automatic rollback.
	 *
	 * @param string $plugin_file   Installed plugin basename.
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return array|\WP_Error Recovery result or an error.
	 */
	public function execute( $plugin_file, $snapshot_uuid ) {
		$component = $this->source_locator->locate( $plugin_file );
		if ( is_wp_error( $component ) ) {
			return $component;
		}

		$plugin_file   = $component['plugin_file'];
		$snapshot_uuid = strtolower( sanitize_text_field( (string) $snapshot_uuid ) );

		if ( plugin_basename( REVERTSHIELD_FILE ) === $plugin_file ) {
			return new \WP_Error(
				'revertshield_recovery_self_restore_disabled',
				__( 'Restoring RevertShield itself is not enabled in this release.', 'revertshield' )
			);
		}

		$verified = $this->eligibility->validate( $snapshot_uuid, $plugin_file );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$snapshot = $this->snapshots->find( $snapshot_uuid );
		$manifest = $this->manifest_data( $snapshot, $plugin_file );
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		$before_version = isset( $component['plugin_data']['Version'] )
			? sanitize_text_field( $component['plugin_data']['Version'] )
			: '';
		$target_version = isset( $manifest['metadata']['version'] )
			? sanitize_text_field( $manifest['metadata']['version'] )
			: '';

		$this->ledger->record(
			'recovery_started',
			'plugin',
			$plugin_file,
			array(
				'snapshot_uuid' => $snapshot_uuid,
				'from_version'  => $before_version,
				'to_version'    => $target_version,
			),
			'recovery'
		);

		$filesystem = $this->filesystem();
		if ( is_wp_error( $filesystem ) ) {
			$this->record_failure( $plugin_file, $snapshot_uuid, $filesystem->get_error_code(), $before_version, $target_version );
			return $filesystem;
		}

		$stage = $this->prepare_stage( $filesystem, $snapshot_uuid, $manifest );
		if ( is_wp_error( $stage ) ) {
			$this->record_failure( $plugin_file, $snapshot_uuid, $stage->get_error_code(), $before_version, $target_version );
			return $stage;
		}

		$replaced = $this->replace_component( $filesystem, $component, $stage );
		if ( is_wp_error( $replaced ) ) {
			$filesystem->delete( $stage['root'], true );
			$this->record_failure( $plugin_file, $snapshot_uuid, $replaced->get_error_code(), $before_version, $target_version );
			return $replaced;
		}

		wp_clean_plugins_cache( false );
		$integrity = $this->verify_restored_plugin( $plugin_file, $manifest );
		if ( is_wp_error( $integrity ) ) {
			$rolled_back = $this->restore_previous( $filesystem, $component, $stage );
			$filesystem->delete( $stage['root'], true );
			wp_clean_plugins_cache( false );

			if ( is_wp_error( $rolled_back ) ) {
				$this->record_failure( $plugin_file, $snapshot_uuid, $rolled_back->get_error_code(), $before_version, $target_version );
				return $rolled_back;
			}

			$this->record_failure( $plugin_file, $snapshot_uuid, $integrity->get_error_code(), $before_version, $target_version );
			return $integrity;
		}

		$health = $this->health->run_homepage_check();
		$event  = 'pass' === $health['status'] ? 'recovery_healthy' : 'recovery_unhealthy';

		$this->ledger->record(
			$event,
			'plugin',
			$plugin_file,
			array(
				'snapshot_uuid' => $snapshot_uuid,
				'from_version'  => $before_version,
				'to_version'    => $target_version,
				'file_count'    => absint( $integrity['file_count'] ),
				'size_bytes'    => absint( $integrity['size_bytes'] ),
				'health_status' => $health['status'],
				'http_code'     => absint( $health['http_code'] ),
				'duration_ms'   => absint( $health['duration_ms'] ),
			),
			'recovery'
		);

		$filesystem->delete( $stage['root'], true );
		wp_clean_plugins_cache( false );

		return array(
			'plugin_file'   => $plugin_file,
			'snapshot_uuid' => $snapshot_uuid,
			'from_version'  => $before_version,
			'to_version'    => $target_version,
			'file_count'    => absint( $integrity['file_count'] ),
			'size_bytes'    => absint( $integrity['size_bytes'] ),
			'health_status' => $health['status'],
			'http_code'     => absint( $health['http_code'] ),
			'duration_ms'   => absint( $health['duration_ms'] ),
		);
	}

	/**
	 * Parse and validate the persisted manifest for the requested plugin.
	 *
	 * @param array|null $snapshot    Snapshot metadata.
	 * @param string     $plugin_file Plugin basename.
	 * @return array|\WP_Error Manifest data or an error.
	 */
	private function manifest_data( $snapshot, $plugin_file ) {
		$json     = is_array( $snapshot ) && isset( $snapshot['manifest'] ) ? (string) $snapshot['manifest'] : '';
		$manifest = '' !== $json ? json_decode( $json, true ) : null;

		if (
			! is_array( $manifest ) ||
			! isset( $manifest['format_version'], $manifest['component_type'], $manifest['component_name'], $manifest['files'], $manifest['total_size'] ) ||
			SnapshotManifest::FORMAT_VERSION !== (int) $manifest['format_version'] ||
			'plugin' !== $manifest['component_type'] ||
			wp_normalize_path( (string) $manifest['component_name'] ) !== wp_normalize_path( $plugin_file ) ||
			! is_array( $manifest['files'] ) ||
			empty( $manifest['files'] )
		) {
			return new \WP_Error(
				'revertshield_recovery_manifest_invalid',
				__( 'The recovery snapshot manifest is invalid for this plugin.', 'revertshield' )
			);
		}

		$manifest['metadata'] = isset( $manifest['metadata'] ) && is_array( $manifest['metadata'] ) ? $manifest['metadata'] : array();
		return $manifest;
	}

	/**
	 * Create a protected staging tree from snapshot objects and verify every file.
	 *
	 * @param \WP_Filesystem_Base $filesystem    WordPress filesystem instance.
	 * @param string              $snapshot_uuid Snapshot UUID.
	 * @param array               $manifest      Snapshot manifest.
	 * @return array|\WP_Error Staging details or an error.
	 */
	private function prepare_stage( $filesystem, $snapshot_uuid, array $manifest ) {
		$location = $this->storage_locator->locate( $snapshot_uuid );
		if ( is_wp_error( $location ) ) {
			return $location;
		}

		$upgrade_root = wp_normalize_path( WP_CONTENT_DIR . '/upgrade' );
		if ( ! wp_mkdir_p( $upgrade_root ) ) {
			return new \WP_Error(
				'revertshield_recovery_stage_root_failed',
				__( 'RevertShield could not prepare the WordPress upgrade staging directory.', 'revertshield' )
			);
		}

		$upgrade_root = realpath( $upgrade_root );
		if ( false === $upgrade_root ) {
			return new \WP_Error(
				'revertshield_recovery_stage_root_unavailable',
				__( 'The WordPress upgrade staging directory could not be resolved.', 'revertshield' )
			);
		}

		$upgrade_root = untrailingslashit( wp_normalize_path( $upgrade_root ) );
		$stage_root   = wp_normalize_path( trailingslashit( $upgrade_root ) . 'revertshield-recovery-' . $snapshot_uuid );
		$component    = trailingslashit( $stage_root ) . 'snapshot';
		$backup       = trailingslashit( $stage_root ) . 'previous';

		if ( 0 !== strpos( $stage_root, trailingslashit( $upgrade_root ) ) || $filesystem->exists( $stage_root ) ) {
			return new \WP_Error(
				'revertshield_recovery_stage_conflict',
				__( 'A safe recovery staging directory could not be reserved.', 'revertshield' )
			);
		}

		if ( ! wp_mkdir_p( $component ) ) {
			return new \WP_Error(
				'revertshield_recovery_stage_failed',
				__( 'RevertShield could not create the recovery staging directory.', 'revertshield' )
			);
		}

		$guard = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
		if ( ! $filesystem->put_contents( trailingslashit( $stage_root ) . '.htaccess', $guard ) ) {
			$filesystem->delete( $stage_root, true );
			return new \WP_Error(
				'revertshield_recovery_stage_guard_failed',
				__( 'RevertShield could not protect the recovery staging directory.', 'revertshield' )
			);
		}

		$objects_dir = trailingslashit( $location['absolute'] ) . 'objects';
		$total       = 0;

		foreach ( $manifest['files'] as $file ) {
			$copied = $this->copy_object_to_stage( $filesystem, $objects_dir, $component, $file );
			if ( is_wp_error( $copied ) ) {
				$filesystem->delete( $stage_root, true );
				return $copied;
			}

			$total += $copied;
		}

		if ( $total !== (int) $manifest['total_size'] ) {
			$filesystem->delete( $stage_root, true );
			return new \WP_Error(
				'revertshield_recovery_stage_size_mismatch',
				__( 'The staged recovery files do not match the snapshot size.', 'revertshield' )
			);
		}

		return array(
			'root'      => $stage_root,
			'component' => $component,
			'backup'    => $backup,
		);
	}

	/**
	 * Copy one verified object into the staging tree.
	 *
	 * @param \WP_Filesystem_Base $filesystem  WordPress filesystem instance.
	 * @param string              $objects_dir Snapshot objects directory.
	 * @param string              $stage_root  Staged component root.
	 * @param mixed               $file        Manifest file entry.
	 * @return int|\WP_Error Copied size or an error.
	 */
	private function copy_object_to_stage( $filesystem, $objects_dir, $stage_root, $file ) {
		if ( ! is_array( $file ) ) {
			return new \WP_Error( 'revertshield_recovery_file_invalid', __( 'A recovery manifest file entry is invalid.', 'revertshield' ) );
		}

		$relative = isset( $file['path'] ) ? ltrim( wp_normalize_path( (string) $file['path'] ), '/' ) : '';
		$sha256   = isset( $file['sha256'] ) ? strtolower( (string) $file['sha256'] ) : '';
		$size     = isset( $file['size'] ) ? max( 0, (int) $file['size'] ) : -1;

		if ( '' === $relative || 0 !== validate_file( $relative ) || ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) || $size < 0 ) {
			return new \WP_Error( 'revertshield_recovery_file_invalid', __( 'A recovery manifest file entry is invalid.', 'revertshield' ) );
		}

		$source      = trailingslashit( $objects_dir ) . $sha256;
		$destination = wp_normalize_path( trailingslashit( $stage_root ) . $relative );

		if ( 0 !== strpos( $destination, trailingslashit( wp_normalize_path( $stage_root ) ) ) ) {
			return new \WP_Error( 'revertshield_recovery_path_escape', __( 'A recovery file escaped the staging directory.', 'revertshield' ) );
		}

		$parent = dirname( $destination );
		if ( ! wp_mkdir_p( $parent ) ) {
			return new \WP_Error( 'revertshield_recovery_directory_failed', __( 'RevertShield could not create a recovery subdirectory.', 'revertshield' ) );
		}

		if ( ! $filesystem->exists( $source ) || ! $filesystem->is_file( $source ) || ! $filesystem->copy( $source, $destination, false ) ) {
			return new \WP_Error( 'revertshield_recovery_copy_failed', __( 'RevertShield could not stage a verified snapshot object.', 'revertshield' ) );
		}

		$actual_hash = hash_file( 'sha256', $destination );
		$actual_size = filesize( $destination );
		if ( false === $actual_hash || false === $actual_size || ! hash_equals( $sha256, strtolower( $actual_hash ) ) || $size !== (int) $actual_size ) {
			return new \WP_Error( 'revertshield_recovery_stage_integrity_failed', __( 'A staged recovery file failed its integrity check.', 'revertshield' ) );
		}

		return $size;
	}

	/**
	 * Replace the installed plugin while preserving the previous files for rollback.
	 *
	 * @param \WP_Filesystem_Base $filesystem WordPress filesystem instance.
	 * @param array               $component  Installed plugin component.
	 * @param array               $stage      Staging details.
	 * @return true|\WP_Error True or an error.
	 */
	private function replace_component( $filesystem, array $component, array $stage ) {
		if ( ! empty( $component['single_file'] ) ) {
			$target = wp_normalize_path( WP_PLUGIN_DIR . '/' . $component['plugin_file'] );
			$source = trailingslashit( $stage['component'] ) . basename( $component['plugin_file'] );
			$backup = $stage['backup'];
		} else {
			$target = $component['component_root'];
			$source = $stage['component'];
			$backup = $stage['backup'];
		}

		if ( ! $filesystem->exists( $target ) || ! $filesystem->exists( $source ) ) {
			return new \WP_Error(
				'revertshield_recovery_target_unavailable',
				__( 'The plugin target or staged recovery files are unavailable.', 'revertshield' )
			);
		}

		if ( ! $filesystem->move( $target, $backup, false ) ) {
			return new \WP_Error(
				'revertshield_recovery_backup_failed',
				__( 'RevertShield could not preserve the current plugin files before recovery.', 'revertshield' )
			);
		}

		if ( $filesystem->move( $source, $target, false ) ) {
			return true;
		}

		if ( ! $filesystem->move( $backup, $target, false ) ) {
			return new \WP_Error(
				'revertshield_recovery_replace_and_rollback_failed',
				__( 'Recovery replacement failed and the previous plugin files could not be restored automatically.', 'revertshield' )
			);
		}

		return new \WP_Error(
			'revertshield_recovery_replace_failed',
			__( 'Recovery replacement failed; the previous plugin files were restored.', 'revertshield' )
		);
	}

	/**
	 * Verify that the installed plugin exactly matches the snapshot inventory.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param array  $manifest    Snapshot manifest.
	 * @return array|\WP_Error Verification summary or an error.
	 */
	private function verify_restored_plugin( $plugin_file, array $manifest ) {
		$restored = $this->inventory->build( $plugin_file );
		if ( is_wp_error( $restored ) ) {
			return $restored;
		}

		$data           = $restored->to_array();
		$expected_files = $this->normalize_manifest_files( $manifest['files'] );
		$actual_files   = $this->normalize_manifest_files( $data['files'] );

		if ( $expected_files !== $actual_files || (int) $manifest['total_size'] !== (int) $data['total_size'] ) {
			return new \WP_Error(
				'revertshield_recovery_post_restore_integrity_failed',
				__( 'The restored plugin files do not exactly match the verified snapshot.', 'revertshield' )
			);
		}

		return array(
			'file_count' => count( $actual_files ),
			'size_bytes' => (int) $data['total_size'],
		);
	}

	/**
	 * Normalize manifest file rows for strict comparison.
	 *
	 * @param array $files File rows.
	 * @return array
	 */
	private function normalize_manifest_files( array $files ) {
		$normalized = array();

		foreach ( $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}

			$normalized[] = array(
				'path'   => isset( $file['path'] ) ? ltrim( wp_normalize_path( (string) $file['path'] ), '/' ) : '',
				'sha256' => isset( $file['sha256'] ) ? strtolower( (string) $file['sha256'] ) : '',
				'size'   => isset( $file['size'] ) ? max( 0, (int) $file['size'] ) : 0,
			);
		}

		usort(
			$normalized,
			static function ( $left, $right ) {
				return strcmp( $left['path'], $right['path'] );
			}
		);

		return $normalized;
	}

	/**
	 * Restore the pre-recovery plugin files after a failed integrity check.
	 *
	 * @param \WP_Filesystem_Base $filesystem WordPress filesystem instance.
	 * @param array               $component  Original plugin component.
	 * @param array               $stage      Staging details.
	 * @return true|\WP_Error True or an error.
	 */
	private function restore_previous( $filesystem, array $component, array $stage ) {
		$target = ! empty( $component['single_file'] )
			? wp_normalize_path( WP_PLUGIN_DIR . '/' . $component['plugin_file'] )
			: $component['component_root'];

		if ( $filesystem->exists( $target ) && ! $filesystem->delete( $target, empty( $component['single_file'] ) ) ) {
			return new \WP_Error(
				'revertshield_recovery_failed_restore_cleanup',
				__( 'The failed recovery files could not be removed safely.', 'revertshield' )
			);
		}

		if ( ! $filesystem->exists( $stage['backup'] ) || ! $filesystem->move( $stage['backup'], $target, false ) ) {
			return new \WP_Error(
				'revertshield_recovery_rollback_failed',
				__( 'The previous plugin files could not be restored after recovery verification failed.', 'revertshield' )
			);
		}

		return true;
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
				'revertshield_recovery_direct_filesystem_required',
				__( 'Plugin recovery currently requires direct local filesystem access.', 'revertshield' )
			);
		}

		return $wp_filesystem;
	}

	/**
	 * Record a normalized recovery failure without persisting raw messages.
	 *
	 * @param string $plugin_file    Plugin basename.
	 * @param string $snapshot_uuid  Snapshot UUID.
	 * @param string $error_code     Error code.
	 * @param string $before_version Previous plugin version.
	 * @param string $target_version Snapshot plugin version.
	 * @return void
	 */
	private function record_failure( $plugin_file, $snapshot_uuid, $error_code, $before_version, $target_version ) {
		$this->ledger->record(
			'recovery_failed',
			'plugin',
			$plugin_file,
			array(
				'snapshot_uuid'  => $snapshot_uuid,
				'error_code'     => sanitize_key( $error_code ),
				'from_version'   => sanitize_text_field( $before_version ),
				'target_version' => sanitize_text_field( $target_version ),
			),
			'recovery'
		);
	}
}
