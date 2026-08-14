<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Health {
    private static $instance;

    public static function instance() {
        if ( ! self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public function hooks() {
        add_filter( 'site_status_tests', array( $this, 'site_health_tests' ) );
        add_filter( 'debug_information', array( $this, 'debug_information' ) );
    }

    public function site_health_tests( $tests ) {
        $tests['direct']['n8lc_schema'] = array(
            'label' => __( 'N8 LiveChat database schema', 'n8-livechat-pro' ),
            'test'  => array( $this, 'test_schema' ),
        );
        $tests['direct']['n8lc_cron'] = array(
            'label' => __( 'N8 LiveChat scheduled tasks', 'n8-livechat-pro' ),
            'test'  => array( $this, 'test_cron' ),
        );
        return $tests;
    }

    public function test_schema() {
        global $wpdb;
        $missing = array();
        foreach ( array( 'visitors', 'conversations', 'messages', 'departments', 'events' ) as $name ) {
            $table = N8LC_DB::table( $name );
            if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) $missing[] = $table;
        }
        foreach ( array( 'agent_profiles', 'routing_rules', 'saved_views', 'segments', 'custom_fields', 'integrations', 'blocks' ) as $name ) {
            $table = N8LC_Platform::table( $name );
            if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) $missing[] = $table;
        }
        if ( $missing ) {
            return array(
                'label'       => __( 'LiveChat database tables need attention', 'n8-livechat-pro' ),
                'status'      => 'critical',
                'badge'       => array( 'label' => 'N8 LiveChat', 'color' => 'red' ),
                'description' => '<p>' . esc_html( implode( ', ', $missing ) ) . '</p>',
                'actions'     => '',
                'test'        => 'n8lc_schema',
            );
        }
        return array(
            'label'       => __( 'LiveChat database tables are available', 'n8-livechat-pro' ),
            'status'      => 'good',
            'badge'       => array( 'label' => 'N8 LiveChat', 'color' => 'blue' ),
            'description' => '<p>' . esc_html__( 'Core and professional workspace tables are installed.', 'n8-livechat-pro' ) . '</p>',
            'actions'     => '',
            'test'        => 'n8lc_schema',
        );
    }

    public function test_cron() {
        $daily = wp_next_scheduled( 'n8lc_daily_cleanup' );
        $auto  = wp_next_scheduled( 'n8lc_automation_tick' );
        $good  = $daily && $auto;
        return array(
            'label'       => $good ? __( 'LiveChat scheduled tasks are registered', 'n8-livechat-pro' ) : __( 'LiveChat scheduled tasks need attention', 'n8-livechat-pro' ),
            'status'      => $good ? 'good' : 'recommended',
            'badge'       => array( 'label' => 'N8 LiveChat', 'color' => 'blue' ),
            'description' => '<p>' . esc_html( $good ? __( 'Cleanup and automation schedules are present.', 'n8-livechat-pro' ) : __( 'At least one expected WP-Cron event is missing. Re-activating the plugin can rebuild schedules.', 'n8-livechat-pro' ) ) . '</p>',
            'actions'     => '',
            'test'        => 'n8lc_cron',
        );
    }

    public function debug_information( $info ) {
        $settings = N8LC_Platform::settings();
        $info['n8-livechat-pro'] = array(
            'label'  => __( 'N8 LiveChat Pro', 'n8-livechat-pro' ),
            'fields' => array(
                'version' => array( 'label' => __( 'Plugin version', 'n8-livechat-pro' ), 'value' => N8LC_VERSION ),
                'schema'  => array( 'label' => __( 'Platform schema', 'n8-livechat-pro' ), 'value' => (string) get_option( N8LC_Platform::VERSION_OPTION ) ),
                'rest'    => array( 'label' => __( 'REST namespace', 'n8-livechat-pro' ), 'value' => N8LC_REST::NS ),
                'routing' => array( 'label' => __( 'Routing rules', 'n8-livechat-pro' ), 'value' => empty( $settings['enable_routing_rules'] ) ? 'disabled' : 'enabled' ),
                'privacy' => array( 'label' => __( 'Privacy tools', 'n8-livechat-pro' ), 'value' => empty( $settings['enable_privacy_tools'] ) ? 'disabled' : 'enabled' ),
            ),
        );
        return $info;
    }
}
