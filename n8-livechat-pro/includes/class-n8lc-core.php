<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Core {
    private static $instance;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function boot() {
        $this->maybe_upgrade();
        N8LC_REST::instance()->hooks();
        N8LC_Admin::instance()->hooks();
        N8LC_Widget::instance()->hooks();
        N8LC_Automation::instance()->hooks();
        N8LC_Webhooks::instance()->hooks();

        add_action( 'n8lc_daily_cleanup', array( $this, 'cleanup' ) );

        if ( ! wp_next_scheduled( 'n8lc_daily_cleanup' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'n8lc_daily_cleanup' );
        }
        if ( ! wp_next_scheduled( 'n8lc_automation_tick' ) ) {
            wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'n8lc_five_minutes', 'n8lc_automation_tick' );
        }
    }

    private function maybe_upgrade() {
        $version = get_option( 'n8lc_db_version' );
        if ( N8LC_DB::DB_VERSION !== $version ) {
            N8LC_DB::activate();
        }
    }

    public static function deactivate() {
        foreach ( array( 'n8lc_daily_cleanup', 'n8lc_automation_tick' ) as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            while ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
                $timestamp = wp_next_scheduled( $hook );
            }
        }
    }

    public function cleanup() {
        global $wpdb;
        $settings = get_option( 'n8lc_settings', array() );
        $days     = isset( $settings['retention_days'] ) ? max( 7, min( 3650, absint( $settings['retention_days'] ) ) ) : 365;
        $cutoff   = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( DAY_IN_SECONDS * $days ) );
        $events   = N8LC_DB::table( 'events' );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        do_action( 'n8lc_after_daily_cleanup', $cutoff );
    }
}
