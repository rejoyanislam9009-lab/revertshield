<?php
/**
 * Snapshot storage path resolution.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

use RevertShield\Support\SiteContext;

/**
 * Resolves snapshot paths under the WordPress uploads directory.
 */
final class StorageLocator {
	/** @var SiteContext */
	private $site_context;

	/**
	 * Constructor.
	 *
	 * @param SiteContext|null $site_context Optional site context.
	 */
	public function __construct( SiteContext $site_context = null ) {
		$this->site_context = $site_context ? $site_context : new SiteContext();
	}

	/**
	 * Resolve a snapshot storage location.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return array|\WP_Error Location details or an error.
	 */
	public function locate( $snapshot_uuid ) {
		if ( ! $this->is_valid_uuid( $snapshot_uuid ) ) {
			return new \WP_Error(
				'revertshield_invalid_snapshot_uuid',
				__( 'The snapshot identifier is invalid.', 'revertshield' )
			);
		}

		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return new \WP_Error(
				'revertshield_uploads_unavailable',
				__( 'WordPress could not resolve a usable uploads directory.', 'revertshield' )
			);
		}

		$base_dir = untrailingslashit( wp_normalize_path( $uploads['basedir'] ) );
		$relative = 'revertshield/snapshots/' . strtolower( $snapshot_uuid );

		if ( $this->site_context->is_multisite() ) {
			$relative = 'revertshield/sites/' . $this->site_context->blog_id() . '/snapshots/' . strtolower( $snapshot_uuid );
		}

		$absolute = wp_normalize_path( trailingslashit( $base_dir ) . $relative );

		if ( 0 !== strpos( $absolute, trailingslashit( $base_dir ) ) ) {
			return new \WP_Error(
				'revertshield_snapshot_path_escape',
				__( 'The snapshot path escaped the uploads storage boundary.', 'revertshield' )
			);
		}

		return array(
			'uploads_base' => $base_dir,
			'relative'     => $relative,
			'absolute'     => $absolute,
		);
	}

	/**
	 * Validate a version 4 UUID-shaped identifier.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return bool
	 */
	private function is_valid_uuid( $snapshot_uuid ) {
		return 1 === preg_match(
			'/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i',
			(string) $snapshot_uuid
		);
	}
}
