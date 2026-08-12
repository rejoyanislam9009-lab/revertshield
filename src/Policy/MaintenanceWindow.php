<?php
/**
 * Guarded maintenance-window policy.
 *
 * @package RevertShield
 */

namespace RevertShield\Policy;

/**
 * Determines whether guarded update execution is currently allowed.
 */
final class MaintenanceWindow {
	/** @var callable|null */
	private $now_provider;

	/**
	 * Constructor.
	 *
	 * @param callable|null $now_provider Optional timestamp provider for deterministic tests.
	 */
	public function __construct( $now_provider = null ) {
		$this->now_provider = is_callable( $now_provider ) ? $now_provider : null;
	}

	/**
	 * Return normalized policy status.
	 *
	 * @return array
	 */
	public function status() {
		$settings = get_option( 'revertshield_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$enabled  = ! empty( $settings['maintenance_window_enabled'] );
		$start    = $this->normalize_time( isset( $settings['maintenance_window_start'] ) ? $settings['maintenance_window_start'] : '02:00', '02:00' );
		$end      = $this->normalize_time( isset( $settings['maintenance_window_end'] ) ? $settings['maintenance_window_end'] : '05:00', '05:00' );
		$allowed  = true;

		if ( $enabled ) {
			$now     = $this->local_minutes();
			$start_m = $this->minutes( $start );
			$end_m   = $this->minutes( $end );

			if ( $start_m === $end_m ) {
				$allowed = true;
			} elseif ( $start_m < $end_m ) {
				$allowed = $now >= $start_m && $now < $end_m;
			} else {
				$allowed = $now >= $start_m || $now < $end_m;
			}
		}

		return array(
			'enabled' => $enabled,
			'allowed' => $allowed,
			'start'   => $start,
			'end'     => $end,
		);
	}

	/**
	 * Whether guarded update execution is allowed now.
	 *
	 * @return bool
	 */
	public function allows_now() {
		$status = $this->status();
		return ! empty( $status['allowed'] );
	}

	/**
	 * Resolve current WordPress-local minute of day.
	 *
	 * @return int
	 */
	private function local_minutes() {
		if ( $this->now_provider ) {
			$provided = call_user_func( $this->now_provider );

			if ( $provided instanceof \DateTimeInterface ) {
				return ( (int) $provided->format( 'G' ) * 60 ) + (int) $provided->format( 'i' );
			}

			$timestamp = (int) $provided;
			return ( (int) wp_date( 'G', $timestamp, wp_timezone() ) * 60 ) + (int) wp_date( 'i', $timestamp, wp_timezone() );
		}

		$now = current_datetime();
		return ( (int) $now->format( 'G' ) * 60 ) + (int) $now->format( 'i' );
	}

	/**
	 * Convert HH:MM to minute of day.
	 *
	 * @param string $value Normalized time.
	 * @return int
	 */
	private function minutes( $value ) {
		$parts = explode( ':', $value );
		return ( (int) $parts[0] * 60 ) + (int) $parts[1];
	}

	/**
	 * Normalize a time setting.
	 *
	 * @param mixed  $value    Raw setting.
	 * @param string $fallback Fallback time.
	 * @return string
	 */
	private function normalize_time( $value, $fallback ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ? $value : $fallback;
	}
}
