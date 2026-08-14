<?php
/**
 * Snapshot pin/protect registry.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

use RevertShield\Database\Tables;

/**
 * Protects selected snapshots from retention cleanup without changing manifests.
 */
final class SnapshotPinStore {
	const OPTION = 'revertshield_pinned_snapshots';
	const LIMIT  = 250;

	/** @var SnapshotRepository */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository|null $repository Optional snapshot repository.
	 */
	public function __construct( SnapshotRepository $repository = null ) {
		$this->repository = $repository ? $repository : new SnapshotRepository();
	}

	/**
	 * Protect one stored snapshot from expiration cleanup.
	 *
	 * The original expiration timestamp is retained in a site-scoped option and
	 * restored if the administrator later unpins the snapshot. Snapshot manifests
	 * and stored objects are never rewritten by this operation.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return true|\WP_Error
	 */
	public function pin( $snapshot_uuid ) {
		$snapshot_uuid = strtolower( sanitize_text_field( (string) $snapshot_uuid ) );
		$snapshot      = $this->repository->find( $snapshot_uuid );

		if ( ! $snapshot ) {
			return new \WP_Error( 'revertshield_snapshot_not_found', __( 'The requested snapshot could not be found.', 'revertshield' ) );
		}

		$state = isset( $snapshot['state'] ) ? sanitize_key( $snapshot['state'] ) : '';
		if ( in_array( $state, array( SnapshotState::PREPARING, SnapshotState::EXPIRED ), true ) ) {
			return new \WP_Error( 'revertshield_snapshot_not_pinnable', __( 'Preparing or expired snapshots cannot be pinned.', 'revertshield' ) );
		}

		$pins = $this->load();
		if ( isset( $pins[ $snapshot_uuid ] ) ) {
			return true;
		}

		if ( count( $pins ) >= self::LIMIT ) {
			return new \WP_Error( 'revertshield_snapshot_pin_limit', __( 'The protected snapshot limit has been reached.', 'revertshield' ) );
		}

		global $wpdb;

		$table = Tables::snapshots();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pinning is a deliberate lifecycle metadata update in RevertShield's dedicated table.
		$updated = $wpdb->update(
			$table,
			array( 'expires_at' => null ),
			array( 'snapshot_uuid' => $snapshot_uuid ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'revertshield_snapshot_pin_failed', __( 'RevertShield could not protect the snapshot.', 'revertshield' ) );
		}

		$pins[ $snapshot_uuid ] = array(
			'expires_at' => isset( $snapshot['expires_at'] ) && '' !== $snapshot['expires_at'] ? sanitize_text_field( $snapshot['expires_at'] ) : null,
			'pinned_at'  => current_time( 'mysql', true ),
		);
		$this->persist( $pins );

		return true;
	}

	/**
	 * Remove protection and restore the snapshot's original expiration time.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return true|\WP_Error
	 */
	public function unpin( $snapshot_uuid ) {
		$snapshot_uuid = strtolower( sanitize_text_field( (string) $snapshot_uuid ) );
		$pins          = $this->load();

		if ( ! isset( $pins[ $snapshot_uuid ] ) ) {
			return true;
		}

		$snapshot = $this->repository->find( $snapshot_uuid );
		if ( ! $snapshot ) {
			unset( $pins[ $snapshot_uuid ] );
			$this->persist( $pins );
			return true;
		}

		$expires_at = isset( $pins[ $snapshot_uuid ]['expires_at'] ) && null !== $pins[ $snapshot_uuid ]['expires_at']
			? sanitize_text_field( $pins[ $snapshot_uuid ]['expires_at'] )
			: null;

		global $wpdb;

		$table = Tables::snapshots();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Unpinning restores deliberate lifecycle metadata in RevertShield's dedicated table.
		$updated = $wpdb->update(
			$table,
			array( 'expires_at' => $expires_at ),
			array( 'snapshot_uuid' => $snapshot_uuid ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'revertshield_snapshot_unpin_failed', __( 'RevertShield could not remove snapshot protection.', 'revertshield' ) );
		}

		unset( $pins[ $snapshot_uuid ] );
		$this->persist( $pins );

		return true;
	}

	/**
	 * Whether a snapshot is currently protected.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return bool
	 */
	public function is_pinned( $snapshot_uuid ) {
		$pins = $this->load();
		return isset( $pins[ strtolower( sanitize_text_field( (string) $snapshot_uuid ) ) ] );
	}

	/**
	 * Count protected snapshots.
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->load() );
	}

	/**
	 * Read and normalize the site-scoped pin registry.
	 *
	 * @return array
	 */
	private function load() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$pins   = array();

		foreach ( $stored as $snapshot_uuid => $metadata ) {
			$snapshot_uuid = strtolower( sanitize_text_field( (string) $snapshot_uuid ) );
			if ( ! $this->is_valid_uuid( $snapshot_uuid ) || ! is_array( $metadata ) ) {
				continue;
			}

			$pins[ $snapshot_uuid ] = array(
				'expires_at' => isset( $metadata['expires_at'] ) && null !== $metadata['expires_at'] ? sanitize_text_field( $metadata['expires_at'] ) : null,
				'pinned_at'  => isset( $metadata['pinned_at'] ) ? sanitize_text_field( $metadata['pinned_at'] ) : '',
			);
		}

		return $pins;
	}

	/**
	 * Persist the normalized registry without autoloading it on every request.
	 *
	 * @param array $pins Pin registry.
	 * @return void
	 */
	private function persist( array $pins ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $pins, '', false );
			return;
		}

		update_option( self::OPTION, $pins, false );
	}

	/**
	 * Validate a version 4 UUID-shaped identifier.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return bool
	 */
	private function is_valid_uuid( $snapshot_uuid ) {
		return 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $snapshot_uuid );
	}
}
