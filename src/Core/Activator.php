<?php
/**
 * Activation routines.
 *
 * @package RevertShield
 */

namespace RevertShield\Core;

use RevertShield\Database\Migrator;
use RevertShield\Snapshot\SnapshotCleanup;
use RevertShield\Support\Cleanup;

/**
 * Handles plugin activation.
 */
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
					'retention_days'             => 90,
					'snapshot_retention_days'    => 7,
					'log_option_names'           => 1,
					'delete_on_uninstall'        => 0,
					'maintenance_window_enabled' => 0,
					'maintenance_window_start'   => '02:00',
					'maintenance_window_end'     => '05:00',
				),
				'',
				false
			);
		}

		Cleanup::schedule();
		SnapshotCleanup::schedule();
	}
}
