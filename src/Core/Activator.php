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
 * Handles plugin activation and per-site provisioning.
 */
final class Activator {
	/**
	 * Activate plugin for the current site context.
	 *
	 * @return void
	 */
	public static function activate() {
		Migrator::migrate();
		self::ensure_settings();
		self::ensure_schedules();
	}

	/**
	 * Ensure an already active site has current schema, defaults, and schedules.
	 *
	 * This supports Multisite network activation without iterating an unbounded
	 * network during activation. Each existing site is repaired on first load.
	 * Scheduling is idempotent because both cleanup services check for an
	 * existing event before adding one.
	 *
	 * @return void
	 */
	public static function ensure_current_site() {
		Migrator::maybe_upgrade();
		self::ensure_settings();
		self::ensure_schedules();
	}

	/**
	 * Ensure the current site has the default local settings record.
	 *
	 * @return void
	 */
	private static function ensure_settings() {
		if ( false !== get_option( 'revertshield_settings', false ) ) {
			return;
		}

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

	/**
	 * Ensure current-site housekeeping schedules exist.
	 *
	 * @return void
	 */
	private static function ensure_schedules() {
		Cleanup::schedule();
		SnapshotCleanup::schedule();
	}
}
