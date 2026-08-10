<?php
/**
 * Deactivation routines.
 *
 * @package RevertShield
 */

namespace RevertShield\Core;

use RevertShield\Support\Cleanup;

final class Deactivator {
	/**
	 * Deactivate plugin without deleting user data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Cleanup::unschedule();
	}
}
