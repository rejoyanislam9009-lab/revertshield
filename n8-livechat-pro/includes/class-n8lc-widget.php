<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Widget {
    private static $instance;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'wp_footer', array( $this, 'render' ), 99 );
    }

    public function enqueue() {
        $settings = get_option( 'n8lc_settings', array() );
        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        wp_enqueue_style( 'n8lc-widget', N8LC_URL . 'assets/css/widget.css', array(), N8LC_VERSION );
        wp_enqueue_script( 'n8lc-widget', N8LC_URL . 'assets/js/widget.js', array(), N8LC_VERSION, true );

        $departments = array();
        global $wpdb;
        $table = N8LC_DB::table( 'departments' );
        $rows  = $wpdb->get_results( "SELECT id,name FROM {$table} WHERE is_active=1 ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ( $rows as $row ) {
            $departments[] = array( 'id' => (int) $row['id'], 'name' => $row['name'] );
        }

        wp_localize_script(
            'n8lc-widget',
            'N8LCWidget',
            array(
                'restRoot'       => esc_url_raw( rest_url( N8LC_REST::NS . '/' ) ),
                'title'          => isset( $settings['widget_title'] ) ? $settings['widget_title'] : __( 'Chat with us', 'n8-livechat-pro' ),
                'position'       => isset( $settings['position'] ) && 'left' === $settings['position'] ? 'left' : 'right',
                'accentColor'    => isset( $settings['accent_color'] ) ? $settings['accent_color'] : '#111827',
                'requireEmail'   => ! empty( $settings['require_email'] ),
                'pollInterval'   => isset( $settings['poll_interval'] ) ? absint( $settings['poll_interval'] ) : 3000,
                'departments'    => $departments,
                'uploadsEnabled' => ! empty( $settings['uploads_enabled'] ),
                'maxUploadMb'    => isset( $settings['max_upload_mb'] ) ? absint( $settings['max_upload_mb'] ) : 5,
                'csatEnabled'    => ! empty( $settings['csat_enabled'] ),
                'availability'   => N8LC_Availability::is_open( $settings ) ? 'online' : 'away',
                'offlineMessage' => isset( $settings['offline_message'] ) ? $settings['offline_message'] : '',
                'i18n'           => array(
                    'name'        => __( 'Your name', 'n8-livechat-pro' ),
                    'email'       => __( 'Email', 'n8-livechat-pro' ),
                    'phone'       => __( 'Phone (optional)', 'n8-livechat-pro' ),
                    'department'  => __( 'Department', 'n8-livechat-pro' ),
                    'start'       => __( 'Start chat', 'n8-livechat-pro' ),
                    'placeholder' => __( 'Type a message…', 'n8-livechat-pro' ),
                    'send'        => __( 'Send', 'n8-livechat-pro' ),
                    'close'       => __( 'Close chat window', 'n8-livechat-pro' ),
                    'end'         => __( 'End chat', 'n8-livechat-pro' ),
                    'attach'      => __( 'Attach file', 'n8-livechat-pro' ),
                    'typing'      => __( 'Agent is typing…', 'n8-livechat-pro' ),
                    'error'       => __( 'Could not connect. Please try again.', 'n8-livechat-pro' ),
                    'online'      => __( 'Online', 'n8-livechat-pro' ),
                    'away'        => __( 'Away', 'n8-livechat-pro' ),
                    'rateUs'      => __( 'How was your support experience?', 'n8-livechat-pro' ),
                ),
            )
        );
    }

    public function render() {
        $settings = get_option( 'n8lc_settings', array() );
        if ( empty( $settings['enabled'] ) ) {
            return;
        }
        echo '<div id="n8lc-widget-root" class="n8lc-widget-root" aria-live="polite"></div>';
    }
}
