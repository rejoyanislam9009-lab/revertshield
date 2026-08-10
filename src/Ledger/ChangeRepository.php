<?php
/**
 * Change ledger persistence.
 *
 * @package RevertShield
 */

namespace RevertShield\Ledger;

use RevertShield\Database\Tables;

final class ChangeRepository {
	/**
	 * Store a ledger event.
	 *
	 * @param string $event_type  Event identifier.
	 * @param string $object_type Object category.
	 * @param string $object_name Object name or slug.
	 * @param array  $context     Non-sensitive context.
	 * @param string $source      Event source.
	 * @return int|false Inserted row ID or false.
	 */
	public function record( $event_type, $object_type = '', $object_name = '', array $context = array(), $source = 'wordpress' ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			Tables::changes(),
			array(
				'event_type'  => sanitize_key( $event_type ),
				'object_type' => sanitize_key( $object_type ),
				'object_name' => sanitize_text_field( $object_name ),
				'actor_id'    => get_current_user_id(),
				'source'      => sanitize_key( $source ),
				'context'     => empty( $context ) ? null : wp_json_encode( $this->sanitize_context( $context ) ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Get recent ledger entries.
	 *
	 * @param int $limit Number of entries.
	 * @return array
	 */
	public function recent( $limit = 50 ) {
		global $wpdb;

		$limit = max( 1, min( 200, absint( $limit ) ) );
		$table = Tables::changes();

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count all ledger entries.
	 *
	 * @return int
	 */
	public function count() {
		global $wpdb;

		$table = Tables::changes();
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		return absint( $count );
	}

	/**
	 * Sanitize context recursively and remove obvious secrets.
	 *
	 * @param array $context Context values.
	 * @return array
	 */
	private function sanitize_context( array $context ) {
		$clean = array();

		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( preg_match( '/password|passwd|secret|token|api[_-]?key|authorization/i', $key ) ) {
				$clean[ $key ] = '[redacted]';
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = $this->sanitize_context( $value );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}
}
