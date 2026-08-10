<?php
/**
 * Local health checks.
 *
 * @package RevertShield
 */

namespace RevertShield\Health;

use RevertShield\Database\Tables;

final class HealthChecker {
	/**
	 * Check the public home page through the WordPress HTTP API.
	 *
	 * @return array
	 */
	public function run_homepage_check() {
		global $wpdb;

		$target = home_url( '/' );
		$start  = microtime( true );

		$response = wp_safe_remote_get(
			$target,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'user-agent'  => 'RevertShield/' . REVERTSHIELD_VERSION . '; ' . home_url( '/' ),
			)
		);

		$duration = (int) round( ( microtime( true ) - $start ) * 1000 );
		$status   = 'fail';
		$code     = 0;
		$message  = '';

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
		} else {
			$code   = (int) wp_remote_retrieve_response_code( $response );
			$status = $code >= 200 && $code < 400 ? 'pass' : 'fail';
		}

		$wpdb->insert(
			Tables::health_runs(),
			array(
				'check_type'  => 'homepage_http',
				'target'      => esc_url_raw( $target ),
				'status'      => $status,
				'http_code'   => $code,
				'duration_ms' => max( 0, $duration ),
				'message'     => sanitize_text_field( $message ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return array(
			'status'      => $status,
			'http_code'   => $code,
			'duration_ms' => $duration,
			'message'     => $message,
		);
	}

	/**
	 * Get most recent health result.
	 *
	 * @return array|null
	 */
	public function latest() {
		global $wpdb;

		$table = Tables::health_runs();
		$row   = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 1', $table ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Count stored health runs.
	 *
	 * @return int
	 */
	public function count() {
		global $wpdb;

		$table = Tables::health_runs();
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		return absint( $count );
	}
}
