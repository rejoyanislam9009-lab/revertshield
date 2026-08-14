<?php
/**
 * Plugin settings.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

/**
 * Registers and sanitizes RevertShield settings.
 */
final class Settings {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register Settings API fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'revertshield_settings_group',
			'revertshield_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(
					'retention_days'            => 90,
					'snapshot_retention_days'   => 7,
					'log_option_names'          => 1,
					'delete_on_uninstall'       => 0,
					'maintenance_window_enabled' => 0,
					'maintenance_window_start'  => '02:00',
					'maintenance_window_end'    => '05:00',
					'scheduled_health_enabled'  => 0,
					'scheduled_health_interval' => 24,
				),
			)
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * Snapshot retention and scheduled-health settings are preserved when the
	 * main dashboard settings form does not include those fields.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input           = is_array( $input ) ? $input : array();
		$current         = get_option( 'revertshield_settings', array() );
		$current         = is_array( $current ) ? $current : array();
		$days            = isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : 90;
		$snapshot        = isset( $input['snapshot_retention_days'] )
			? absint( $input['snapshot_retention_days'] )
			: ( isset( $current['snapshot_retention_days'] ) ? absint( $current['snapshot_retention_days'] ) : 7 );
		$start           = $this->sanitize_time( isset( $input['maintenance_window_start'] ) ? $input['maintenance_window_start'] : '02:00', '02:00' );
		$end             = $this->sanitize_time( isset( $input['maintenance_window_end'] ) ? $input['maintenance_window_end'] : '05:00', '05:00' );
		$health_enabled  = ! empty( $current['scheduled_health_enabled'] ) ? 1 : 0;
		$health_interval = isset( $current['scheduled_health_interval'] ) ? absint( $current['scheduled_health_interval'] ) : 24;
		$health_interval = in_array( $health_interval, array( 1, 6, 12, 24 ), true ) ? $health_interval : 24;

		return array(
			'retention_days'             => max( 1, min( 3650, $days ) ),
			'snapshot_retention_days'    => max( 1, min( 90, $snapshot ) ),
			'log_option_names'           => empty( $input['log_option_names'] ) ? 0 : 1,
			'delete_on_uninstall'        => empty( $input['delete_on_uninstall'] ) ? 0 : 1,
			'maintenance_window_enabled' => empty( $input['maintenance_window_enabled'] ) ? 0 : 1,
			'maintenance_window_start'   => $start,
			'maintenance_window_end'     => $end,
			'scheduled_health_enabled'   => $health_enabled,
			'scheduled_health_interval'  => $health_interval,
		);
	}

	/**
	 * Sanitize an HH:MM maintenance-window value.
	 *
	 * @param mixed  $value    Raw setting value.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	private function sanitize_time( $value, $fallback ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ? $value : $fallback;
	}
}
