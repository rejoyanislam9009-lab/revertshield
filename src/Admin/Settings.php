<?php
/**
 * Plugin settings.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

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
					'retention_days'      => 90,
					'log_option_names'    => 1,
					'delete_on_uninstall' => 0,
				),
			)
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$days  = isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : 90;

		return array(
			'retention_days'      => max( 1, min( 3650, $days ) ),
			'log_option_names'    => empty( $input['log_option_names'] ) ? 0 : 1,
			'delete_on_uninstall' => empty( $input['delete_on_uninstall'] ) ? 0 : 1,
		);
	}
}
