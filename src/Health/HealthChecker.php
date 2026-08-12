<?php
/**
 * Local health checks.
 *
 * @package RevertShield
 */

namespace RevertShield\Health;

use RevertShield\Database\Tables;

/**
 * Runs and stores local site health checks.
 */
final class HealthChecker {
	/**
	 * Optional ecosystem probe adapters.
	 *
	 * @var HealthProbeAdapter[]
	 */
	private $adapters = array();

	/**
	 * Constructor.
	 *
	 * @param HealthProbeAdapter[]|null $adapters Optional adapters. Null uses the built-in adapters.
	 */
	public function __construct( $adapters = null ) {
		if ( null === $adapters ) {
			$adapters = array(
				new WooCommerceHealthAdapter(),
			);
		}

		if ( ! is_array( $adapters ) ) {
			return;
		}

		foreach ( $adapters as $adapter ) {
			if ( $adapter instanceof HealthProbeAdapter ) {
				$this->adapters[] = $adapter;
			}
		}
	}

	/**
	 * Run the current multi-probe site-health suite.
	 *
	 * The required core suite covers the public homepage and WordPress REST API
	 * index. Applicable ecosystem adapters may add bounded read-only HTTP probes.
	 * Only the aggregate suite row is persisted, while bounded per-probe details
	 * are returned to the caller.
	 *
	 * @return array
	 */
	public function run_site_check() {
		$homepage = $this->run_http_probe( 'homepage_http', home_url( '/' ) );
		$rest     = $this->run_http_probe( 'rest_api_http', rest_url() );
		$probes   = array(
			'homepage_http' => $homepage,
			'rest_api_http' => $rest,
		);

		foreach ( $this->adapters as $adapter ) {
			if ( ! $adapter->is_applicable() ) {
				continue;
			}

			$targets = $adapter->probe_targets();
			if ( ! is_array( $targets ) ) {
				continue;
			}

			foreach ( $targets as $name => $target ) {
				$name = sanitize_key( $name );
				if ( '' === $name || isset( $probes[ $name ] ) ) {
					continue;
				}

				if ( ! is_string( $target ) || '' === $target ) {
					$probes[ $name ] = $this->failed_probe( $name, '', __( 'Probe target is invalid.', 'revertshield' ) );
					continue;
				}

				$probes[ $name ] = $this->run_http_probe( $name, $target );
			}
		}

		$status   = 'pass';
		$failed   = array();
		$duration = 0;

		foreach ( $probes as $name => $probe ) {
			$duration += absint( $probe['duration_ms'] );
			if ( 'pass' !== $probe['status'] ) {
				$status   = 'fail';
				$failed[] = $name;
			}
		}

		$message_parts = array();
		foreach ( $probes as $name => $probe ) {
			$message_parts[] = sprintf(
				/* translators: 1: health probe label, 2: HTTP response code. */
				__( '%1$s HTTP %2$d', 'revertshield' ),
				$this->probe_label( $name ),
				absint( $probe['http_code'] )
			);
		}
		$message = implode( '; ', $message_parts ) . '.';

		if ( ! empty( $failed ) ) {
			$message .= ' ' . sprintf(
				/* translators: %s: comma-separated probe identifiers. */
				__( 'Failed probes: %s.', 'revertshield' ),
				implode( ', ', array_map( 'sanitize_key', $failed ) )
			);
		}

		$this->persist(
			'site_suite',
			home_url( '/' ),
			$status,
			absint( $homepage['http_code'] ),
			$duration,
			$message
		);

		return array(
			'check_type'    => 'site_suite',
			'status'        => $status,
			'http_code'     => absint( $homepage['http_code'] ),
			'duration_ms'   => max( 0, $duration ),
			'message'       => $message,
			'probes'        => $probes,
			'failed_probes' => $failed,
		);
	}

	/**
	 * Compatibility wrapper for pre-0.6 callers.
	 *
	 * Existing callers keep the same bounded top-level result fields while the
	 * actual decision now uses the complete site-health suite.
	 *
	 * @return array
	 */
	public function run_homepage_check() {
		return $this->run_site_check();
	}

	/**
	 * Run one bounded safe HTTP probe without persisting it.
	 *
	 * @param string $check_type Probe identifier.
	 * @param string $target     Probe URL.
	 * @return array
	 */
	private function run_http_probe( $check_type, $target ) {
		$start = microtime( true );

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
			$message = sanitize_text_field( $response->get_error_message() );
		} else {
			$code   = (int) wp_remote_retrieve_response_code( $response );
			$status = $code >= 200 && $code < 400 ? 'pass' : 'fail';
		}

		return array(
			'check_type'  => sanitize_key( $check_type ),
			'target'      => esc_url_raw( $target ),
			'status'      => $status,
			'http_code'   => $code,
			'duration_ms' => max( 0, $duration ),
			'message'     => $message,
		);
	}

	/**
	 * Build a normalized failed probe result without making an HTTP request.
	 *
	 * @param string $check_type Probe identifier.
	 * @param string $target     Probe URL.
	 * @param string $message    Failure message.
	 * @return array
	 */
	private function failed_probe( $check_type, $target, $message ) {
		return array(
			'check_type'  => sanitize_key( $check_type ),
			'target'      => esc_url_raw( $target ),
			'status'      => 'fail',
			'http_code'   => 0,
			'duration_ms' => 0,
			'message'     => sanitize_text_field( $message ),
		);
	}

	/**
	 * Return a human-readable bounded label for a probe identifier.
	 *
	 * @param string $check_type Probe identifier.
	 * @return string
	 */
	private function probe_label( $check_type ) {
		$labels = array(
			'homepage_http'               => __( 'Homepage', 'revertshield' ),
			'rest_api_http'               => __( 'REST API', 'revertshield' ),
			'woocommerce_store_api_http'  => __( 'WooCommerce Store API', 'revertshield' ),
		);

		if ( isset( $labels[ $check_type ] ) ) {
			return $labels[ $check_type ];
		}

		return sanitize_text_field( $check_type );
	}

	/**
	 * Persist one normalized health result.
	 *
	 * @param string $check_type  Check identifier.
	 * @param string $target      Target URL.
	 * @param string $status      pass or fail.
	 * @param int    $http_code   HTTP status code.
	 * @param int    $duration_ms Duration in milliseconds.
	 * @param string $message     Bounded message.
	 * @return void
	 */
	private function persist( $check_type, $target, $status, $http_code, $duration_ms, $message ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Health results are written to RevertShield's dedicated operational table.
		$wpdb->insert(
			Tables::health_runs(),
			array(
				'check_type'  => sanitize_key( $check_type ),
				'target'      => esc_url_raw( $target ),
				'status'      => 'pass' === $status ? 'pass' : 'fail',
				'http_code'   => absint( $http_code ),
				'duration_ms' => max( 0, absint( $duration_ms ) ),
				'message'     => sanitize_text_field( $message ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get most recent aggregate or legacy homepage health result.
	 *
	 * @return array|null
	 */
	public function latest() {
		global $wpdb;

		$table = Tables::health_runs();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Latest health state must be read fresh from the custom table.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE check_type IN ('site_suite','homepage_http') ORDER BY id DESC LIMIT 1", $table ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Count stored aggregate or legacy health runs.
	 *
	 * @return int
	 */
	public function count() {
		global $wpdb;

		$table = Tables::health_runs();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard metric must reflect the current custom-table row count.
		$count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE check_type IN ('site_suite','homepage_http')", $table )
		);

		return absint( $count );
	}
}
