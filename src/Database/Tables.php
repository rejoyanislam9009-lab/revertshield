<?php
/**
 * Database table names.
 *
 * @package RevertShield
 */

namespace RevertShield\Database;

/**
 * Resolves RevertShield custom-table names for the current site.
 */
final class Tables {
	/**
	 * Change ledger table.
	 *
	 * @return string
	 */
	public static function changes() {
		global $wpdb;
		return $wpdb->prefix . 'revertshield_changes';
	}

	/**
	 * Health run table.
	 *
	 * @return string
	 */
	public static function health_runs() {
		global $wpdb;
		return $wpdb->prefix . 'revertshield_health_runs';
	}
}
