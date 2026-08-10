<?php
/**
 * Plugin snapshot orchestration.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

/**
 * Coordinates plugin inventory, storage preflight, materialization, and metadata.
 */
final class PluginSnapshotService {
	/** @var PluginSourceLocator */
	private $source_locator;

	/** @var PluginInventory */
	private $inventory;

	/** @var SnapshotPreflight */
	private $preflight;

	/** @var StorageLocator */
	private $storage_locator;

	/** @var SnapshotStorage */
	private $storage;

	/** @var SnapshotRepository */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param PluginSourceLocator|null $source_locator  Optional source locator.
	 * @param PluginInventory|null     $inventory       Optional inventory service.
	 * @param SnapshotPreflight|null   $preflight       Optional preflight service.
	 * @param StorageLocator|null      $storage_locator Optional storage locator.
	 * @param SnapshotStorage|null     $storage         Optional storage service.
	 * @param SnapshotRepository|null  $repository      Optional metadata repository.
	 */
	public function __construct(
		PluginSourceLocator $source_locator = null,
		PluginInventory $inventory = null,
		SnapshotPreflight $preflight = null,
		StorageLocator $storage_locator = null,
		SnapshotStorage $storage = null,
		SnapshotRepository $repository = null
	) {
		$this->source_locator  = $source_locator ? $source_locator : new PluginSourceLocator();
		$this->inventory       = $inventory ? $inventory : new PluginInventory( $this->source_locator );
		$this->preflight       = $preflight ? $preflight : new SnapshotPreflight();
		$this->storage_locator = $storage_locator ? $storage_locator : new StorageLocator();
		$this->storage         = $storage ? $storage : new SnapshotStorage( $this->storage_locator );
		$this->repository      = $repository ? $repository : new SnapshotRepository();
	}

	/**
	 * Create and verify a scoped plugin snapshot.
	 *
	 * This internal service does not perform plugin updates or restore operations.
	 *
	 * @param string $plugin_file Installed plugin basename.
	 * @return array|\WP_Error Final snapshot information or an error.
	 */
	public function create( $plugin_file ) {
		$component = $this->source_locator->locate( $plugin_file );
		if ( is_wp_error( $component ) ) {
			return $component;
		}

		$manifest = $this->inventory->build( $component['plugin_file'] );
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		$preflight = $this->preflight->check( $manifest->total_size() );
		if ( is_wp_error( $preflight ) ) {
			return $preflight;
		}

		$snapshot_uuid = wp_generate_uuid4();
		$location      = $this->storage_locator->locate( $snapshot_uuid );
		if ( is_wp_error( $location ) ) {
			return $location;
		}

		$expires_at = $this->expiration_time();
		$reserved   = $this->repository->reserve(
			$snapshot_uuid,
			'plugin',
			$component['plugin_file'],
			$location['relative'],
			$expires_at
		);

		if ( is_wp_error( $reserved ) ) {
			return $reserved;
		}

		$materialized = $this->storage->materialize(
			$snapshot_uuid,
			$manifest,
			$component['component_root']
		);

		if ( is_wp_error( $materialized ) ) {
			$this->repository->mark_failed( $snapshot_uuid );
			$this->storage->delete( $snapshot_uuid );
			return $materialized;
		}

		$ready = $this->repository->mark_ready( $snapshot_uuid, $manifest );
		if ( is_wp_error( $ready ) ) {
			$this->repository->mark_failed( $snapshot_uuid );
			$this->storage->delete( $snapshot_uuid );
			return $ready;
		}

		return array(
			'snapshot_uuid'   => $snapshot_uuid,
			'component_type'  => 'plugin',
			'component_name'  => $component['plugin_file'],
			'state'           => SnapshotState::READY,
			'storage_relpath' => $location['relative'],
			'size_bytes'      => $manifest->total_size(),
			'expires_at'      => $expires_at,
			'object_count'    => $materialized['object_count'],
			'manifest_sha256' => $materialized['manifest_sha256'],
		);
	}

	/**
	 * Calculate a bounded UTC expiration timestamp.
	 *
	 * @return string
	 */
	private function expiration_time() {
		/**
		 * Filter the default snapshot retention window.
		 *
		 * @param int    $days           Snapshot retention days.
		 * @param string $component_type Component type.
		 */
		$days = (int) apply_filters( 'revertshield_snapshot_retention_days', 7, 'plugin' );
		$days = max( 1, min( 90, $days ) );

		return gmdate( 'Y-m-d H:i:s', time() + ( DAY_IN_SECONDS * $days ) );
	}
}
