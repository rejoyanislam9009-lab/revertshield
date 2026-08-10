<?php
/**
 * Activation routines.
 *
 * @package RevertShield
 */

namespace RevertShield\Core;

use RevertShield\Database\Migrator;
use RevertShield\Support\Cleanup;

final class Activator {
	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		Migrator::migrate();

		if ( false === get_option( 'revertshield_settings', false ) ) {
			add_option(
				'revertshield_settings',
				array(
					'retention_days'      => 90,
					'log_option_names'    => 1,
					'delete_on_uninstall' => 0,
				),
				'',
				false
			);
		}

		Cleanup::schedule();
	}
}
