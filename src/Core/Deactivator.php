<?php
/**
 * Deactivation routines.
 *
 * @package RevertShield
 */

namespace RevertShield\Core;

use RevertShield\Health\ScheduledHealthCheck;
use RevertShield\Snapshot\SnapshotCleanup;
use RevertShield\Support\Cleanup;

/**
 * Handles plugin deactivation.
 */
final class Deactivator {
	/**
	 * Deactivate plugin without deleting user data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Cleanup::unschedule();
		SnapshotCleanup::unschedule();
		ScheduledHealthCheck::unschedule();
	}
}
