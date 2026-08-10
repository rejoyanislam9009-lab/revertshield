<?php
/**
 * Retention cleanup.
 *
 * @package RevertShield
 */

namespace RevertShield\Support;

use RevertShield\Database\Tables;

final class Cleanup {
	const HOOK = 'revertshield_daily_cleanup';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK, array( $this, 'run' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	/**
	 * Schedule daily cleanup.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Remove scheduled event.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Delete expired rows in bounded batches.
	 *
	 * @return void
	 */
	public function run() {
		global $wpdb;

		$settings = get_option( 'revertshield_settings', array() );
		$days     = isset( $settings['retention_days'] ) ? absint( $settings['retention_days'] ) : 90;
		$days     = max( 1, min( 3650, $days ) );
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );

		foreach ( array( Tables::changes(), Tables::health_runs() ) as $table ) {
			$wpdb->query(
				$wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT 5000', $table, $cutoff )
			);
		}
	}
}
