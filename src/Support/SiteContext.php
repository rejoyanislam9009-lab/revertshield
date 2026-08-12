<?php
/**
 * Current site and network safety context.
 *
 * @package RevertShield
 */

namespace RevertShield\Support;

/**
 * Resolves site-scoped snapshot ownership and multisite mutation boundaries.
 */
final class SiteContext {
	/**
	 * Whether WordPress Multisite is enabled.
	 *
	 * @return bool
	 */
	public function is_multisite() {
		return is_multisite();
	}

	/**
	 * Current blog/site identifier.
	 *
	 * @return int
	 */
	public function blog_id() {
		return max( 1, absint( get_current_blog_id() ) );
	}

	/**
	 * Current network identifier, or zero on single-site WordPress.
	 *
	 * @return int
	 */
	public function network_id() {
		if ( ! $this->is_multisite() || ! function_exists( 'get_current_network_id' ) ) {
			return 0;
		}

		return absint( get_current_network_id() );
	}

	/**
	 * Metadata persisted inside newly created snapshot manifests.
	 *
	 * String values are used because SnapshotManifest metadata is deliberately
	 * restricted to bounded scalar text values.
	 *
	 * @return array
	 */
	public function snapshot_manifest_metadata() {
		return array(
			'revertshield_scope' => $this->is_multisite() ? 'multisite-site' : 'single-site',
			'origin_blog_id'     => (string) $this->blog_id(),
			'origin_network_id'  => (string) $this->network_id(),
		);
	}

	/**
	 * Validate that a snapshot manifest belongs to the current site context.
	 *
	 * Legacy single-site snapshots remain valid. Once Multisite is enabled,
	 * snapshots without explicit site/network ownership fail closed and a fresh
	 * snapshot is required.
	 *
	 * @param array $manifest Decoded snapshot manifest.
	 * @return true|\WP_Error
	 */
	public function validate_snapshot_manifest_scope( array $manifest ) {
		if ( ! $this->is_multisite() ) {
			return true;
		}

		$metadata = isset( $manifest['metadata'] ) && is_array( $manifest['metadata'] )
			? $manifest['metadata']
			: array();

		if (
			! isset( $metadata['revertshield_scope'], $metadata['origin_blog_id'], $metadata['origin_network_id'] ) ||
			'multisite-site' !== (string) $metadata['revertshield_scope']
		) {
			return new \WP_Error(
				'revertshield_multisite_snapshot_scope_missing',
				__( 'This snapshot predates Multisite ownership metadata. Create a fresh snapshot for the current site.', 'revertshield' )
			);
		}

		if (
			$this->blog_id() !== absint( $metadata['origin_blog_id'] ) ||
			$this->network_id() !== absint( $metadata['origin_network_id'] )
		) {
			return new \WP_Error(
				'revertshield_multisite_snapshot_scope_mismatch',
				__( 'This snapshot belongs to a different Multisite site or network context.', 'revertshield' )
			);
		}

		return true;
	}

	/**
	 * Guard operations that replace shared plugin files.
	 *
	 * WordPress Multisite sites share the installed plugin filesystem. Until
	 * RevertShield has a bounded network-wide post-change health contract, a
	 * site-scoped health result is not sufficient authorization for a shared
	 * plugin-file mutation. Multisite update/recovery therefore fails closed in
	 * this release while snapshot and observation workflows remain available.
	 *
	 * @return true|\WP_Error
	 */
	public function guard_plugin_file_mutation() {
		if ( ! $this->is_multisite() ) {
			return true;
		}

		return new \WP_Error(
			'revertshield_multisite_plugin_mutation_deferred',
			__( 'Guarded plugin updates and plugin-file recovery are deferred on Multisite until network-wide health validation is available.', 'revertshield' )
		);
	}
}
