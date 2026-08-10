<?php
/**
 * Snapshot metadata persistence.
 *
 * @package RevertShield
 */

namespace RevertShield\Snapshot;

use RevertShield\Database\Tables;

/**
 * Persists snapshot lifecycle and integrity metadata.
 */
final class SnapshotRepository {
	/**
	 * Reserve a snapshot record before any file operation begins.
	 *
	 * @param string      $snapshot_uuid   Snapshot UUID.
	 * @param string      $component_type  Component type.
	 * @param string      $component_name  Component identifier.
	 * @param string      $storage_relpath Relative uploads path.
	 * @param string|null $expires_at      Optional UTC expiration timestamp.
	 * @return int|\WP_Error Inserted row ID or an error.
	 */
	public function reserve( $snapshot_uuid, $component_type, $component_name, $storage_relpath, $expires_at = null ) {
		global $wpdb;

		if ( ! $this->is_valid_uuid( $snapshot_uuid ) ) {
			return new \WP_Error(
				'revertshield_invalid_snapshot_uuid',
				__( 'The snapshot identifier is invalid.', 'revertshield' )
			);
		}

		$storage_relpath = ltrim( wp_normalize_path( (string) $storage_relpath ), '/' );
		if ( '' === $storage_relpath || false !== strpos( $storage_relpath, '..' ) ) {
			return new \WP_Error(
				'revertshield_invalid_snapshot_path',
				__( 'The snapshot storage path is invalid.', 'revertshield' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Snapshot lifecycle metadata belongs in RevertShield's dedicated table.
		$inserted = $wpdb->insert(
			Tables::snapshots(),
			array(
				'snapshot_uuid'   => strtolower( $snapshot_uuid ),
				'component_type'  => sanitize_key( $component_type ),
				'component_name'  => sanitize_text_field( $component_name ),
				'state'           => SnapshotState::PREPARING,
				'storage_relpath' => sanitize_text_field( $storage_relpath ),
				'manifest'        => null,
				'size_bytes'      => 0,
				'created_at'      => current_time( 'mysql', true ),
				'expires_at'      => $this->sanitize_nullable_datetime( $expires_at ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error(
				'revertshield_snapshot_reservation_failed',
				__( 'RevertShield could not reserve snapshot metadata.', 'revertshield' )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Mark a snapshot ready and persist its manifest.
	 *
	 * @param string           $snapshot_uuid Snapshot UUID.
	 * @param SnapshotManifest $manifest      Snapshot manifest.
	 * @return true|\WP_Error True on success or an error.
	 */
	public function mark_ready( $snapshot_uuid, SnapshotManifest $manifest ) {
		global $wpdb;

		if ( ! $this->is_valid_uuid( $snapshot_uuid ) ) {
			return new \WP_Error(
				'revertshield_invalid_snapshot_uuid',
				__( 'The snapshot identifier is invalid.', 'revertshield' )
			);
		}

		$manifest_json = $manifest->to_json();
		if ( false === $manifest_json ) {
			return new \WP_Error(
				'revertshield_manifest_encoding_failed',
				__( 'RevertShield could not encode the snapshot manifest.', 'revertshield' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Snapshot lifecycle state must be persisted immediately and read fresh before recovery decisions.
		$updated = $wpdb->update(
			Tables::snapshots(),
			array(
				'state'      => SnapshotState::READY,
				'manifest'   => $manifest_json,
				'size_bytes' => $manifest->total_size(),
			),
			array(
				'snapshot_uuid' => strtolower( $snapshot_uuid ),
				'state'         => SnapshotState::PREPARING,
			),
			array( '%s', '%s', '%d' ),
			array( '%s', '%s' )
		);

		if ( 1 !== $updated ) {
			return new \WP_Error(
				'revertshield_snapshot_update_failed',
				__( 'RevertShield could not finalize the expected preparing snapshot.', 'revertshield' )
			);
		}

		return true;
	}

	/**
	 * Mark a reserved snapshot as failed.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return bool
	 */
	public function mark_failed( $snapshot_uuid ) {
		global $wpdb;

		if ( ! $this->is_valid_uuid( $snapshot_uuid ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Failure state must be persisted immediately and read fresh before later maintenance decisions.
		$updated = $wpdb->update(
			Tables::snapshots(),
			array( 'state' => SnapshotState::FAILED ),
			array(
				'snapshot_uuid' => strtolower( $snapshot_uuid ),
				'state'         => SnapshotState::PREPARING,
			),
			array( '%s' ),
			array( '%s', '%s' )
		);

		return 1 === $updated;
	}

	/**
	 * Read a snapshot record by UUID.
	 *
	 * @param string $snapshot_uuid Snapshot UUID.
	 * @return array|null
	 */
	public function find( $snapshot_uuid ) {
		global $wpdb;

		if ( ! $this->is_valid_uuid( $snapshot_uuid ) ) {
			return null;
		}

		$table = Tables::snapshots();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Snapshot state must be read fresh before any future recovery decision.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE snapshot_uuid = %s LIMIT 1', $table, strtolower( $snapshot_uuid ) ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
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

	/**
	 * Sanitize an optional UTC MySQL datetime string.
	 *
	 * @param string|null $value Datetime value.
	 * @return string|null
	 */
	private function sanitize_nullable_datetime( $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		$value = sanitize_text_field( (string) $value );
		$date  = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) );

		if ( ! $date || $date->format( 'Y-m-d H:i:s' ) !== $value ) {
			return null;
		}

		return $value;
	}
}
