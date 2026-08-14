<?php
/**
 * Scheduled local health checks.
 *
 * @package RevertShield
 */

namespace RevertShield\Health;

/**
 * Runs the existing local site-health suite through WordPress Cron.
 */
final class ScheduledHealthCheck {
	const HOOK = 'revertshield_scheduled_health_check';

	/** @var HealthChecker */
	private $health;

	/**
	 * Constructor.
	 *
	 * @param HealthChecker|null $health Optional health checker.
	 */
	public function __construct( HealthChecker $health = null ) {
		$this->health = $health ? $health : new HealthChecker();
	}

	/**
	 * Register cron schedules and callbacks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'update_option_revertshield_settings', array( $this, 'settings_updated' ), 10, 2 );
		self::sync_schedule();
	}

	/**
	 * Add the bounded six-hour interval used by RevertShield.
	 *
	 * @param array $schedules Existing WordPress schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$schedules = is_array( $schedules ) ? $schedules : array();
		$schedules['revertshield_six_hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 hours (RevertShield)', 'revertshield' ),
		);
		return $schedules;
	}

	/**
	 * Reconcile the current site schedule with saved settings.
	 *
	 * @return void
	 */
	public static function sync_schedule() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );

		$settings = get_option( 'revertshield_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$enabled  = ! empty( $settings['scheduled_health_enabled'] );
		$hours    = isset( $settings['scheduled_health_interval'] ) ? absint( $settings['scheduled_health_interval'] ) : 24;
		$hours    = in_array( $hours, array( 1, 6, 12, 24 ), true ) ? $hours : 24;

		if ( ! $enabled ) {
			self::unschedule();
			return;
		}

		$schedule = self::schedule_name( $hours );
		$event    = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( self::HOOK ) : false;

		if ( $event && isset( $event->schedule ) && $schedule === $event->schedule ) {
			return;
		}

		self::unschedule();
		wp_schedule_event( time() + ( $hours * HOUR_IN_SECONDS ), $schedule, self::HOOK );
	}

	/**
	 * Remove the current site's scheduled health event.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Return normalized schedule status for admin and CLI surfaces.
	 *
	 * @return array
	 */
	public static function status() {
		$settings = get_option( 'revertshield_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$hours    = isset( $settings['scheduled_health_interval'] ) ? absint( $settings['scheduled_health_interval'] ) : 24;
		$hours    = in_array( $hours, array( 1, 6, 12, 24 ), true ) ? $hours : 24;
		$next     = wp_next_scheduled( self::HOOK );

		return array(
			'enabled'  => ! empty( $settings['scheduled_health_enabled'] ),
			'interval' => $hours,
			'next_run' => $next ? (int) $next : 0,
		);
	}

	/**
	 * Run the existing health suite. Results are persisted by HealthChecker.
	 *
	 * @return void
	 */
	public function run() {
		$this->health->run_site_check();
	}

	/**
	 * Reconcile cron after settings are changed.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function settings_updated( $old_value, $new_value ) {
		unset( $old_value, $new_value );
		self::sync_schedule();
	}

	/**
	 * Map a bounded interval to a WordPress recurrence name.
	 *
	 * @param int $hours Interval in hours.
	 * @return string
	 */
	private static function schedule_name( $hours ) {
		if ( 1 === $hours ) {
			return 'hourly';
		}
		if ( 6 === $hours ) {
			return 'revertshield_six_hours';
		}
		if ( 12 === $hours ) {
			return 'twicedaily';
		}
		return 'daily';
	}
}
