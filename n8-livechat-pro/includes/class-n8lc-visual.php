<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Visual {
    private static $instance;
    const OPTION = 'n8lc_visual_settings';

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function hooks() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        $this->ensure_defaults();
    }

    public static function defaults() {
        return array(
            'theme_preset'          => 'indigo',
            'launcher_icon'         => 'message',
            'launcher_shape'        => 'circle',
            'launcher_size'         => 64,
            'launcher_label'        => '',
            'launcher_animation'    => 'pulse',
            'show_greeting'         => 1,
            'greeting_text'         => 'Hi there! Need a hand?',
            'greeting_delay'        => 1800,
            'greeting_auto_hide'    => 12,
            'agent_name'            => 'Support Team',
            'agent_avatar_url'      => '',
            'header_subtitle'       => 'Typically replies in a few minutes',
            'panel_width'           => 400,
            'panel_height'          => 660,
            'panel_radius'          => 24,
            'sound_enabled'         => 1,
            'show_branding'         => 1,
        );
    }

    public static function get() {
        $current = get_option( self::OPTION, array() );
        $current = is_array( $current ) ? $current : array();
        return wp_parse_args( $current, self::defaults() );
    }

    public function ensure_defaults() {
        $current = get_option( self::OPTION, null );
        if ( null === $current || ! is_array( $current ) ) {
            add_option( self::OPTION, self::defaults(), '', false );
            return;
        }
        $merged = wp_parse_args( $current, self::defaults() );
        if ( $merged !== $current ) {
            update_option( self::OPTION, $merged, false );
        }
    }

    public function register_routes() {
        register_rest_route(
            N8LC_REST::NS,
            '/admin/visual-settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_settings' ),
                    'permission_callback' => array( 'N8LC_Security', 'settings_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'save_settings' ),
                    'permission_callback' => array( 'N8LC_Security', 'settings_permission' ),
                ),
            )
        );
    }

    public function get_settings() {
        return rest_ensure_response( self::get() );
    }

    public function save_settings( WP_REST_Request $request ) {
        $input = $request->get_json_params();
        $input = is_array( $input ) ? $input : array();
        $next  = array_merge( self::get(), $this->sanitize( $input ) );
        update_option( self::OPTION, $next, false );

        N8LC_DB::log_event(
            'visual_settings_updated',
            array(
                'agent_id' => get_current_user_id(),
                'payload'  => array( 'keys' => array_keys( $input ) ),
            )
        );

        return rest_ensure_response( $next );
    }

    private function sanitize( array $input ) {
        $clean = array();

        foreach ( array( 'show_greeting', 'sound_enabled', 'show_branding' ) as $key ) {
            if ( array_key_exists( $key, $input ) ) {
                $clean[ $key ] = rest_sanitize_boolean( $input[ $key ] ) ? 1 : 0;
            }
        }

        foreach ( array( 'launcher_label', 'agent_name', 'header_subtitle' ) as $key ) {
            if ( array_key_exists( $key, $input ) ) {
                $clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
            }
        }

        if ( array_key_exists( 'greeting_text', $input ) ) {
            $clean['greeting_text'] = N8LC_Security::sanitize_message( $input['greeting_text'] );
        }

        if ( array_key_exists( 'agent_avatar_url', $input ) ) {
            $clean['agent_avatar_url'] = esc_url_raw( (string) $input['agent_avatar_url'] );
        }

        if ( isset( $input['theme_preset'] ) ) {
            $value = sanitize_key( (string) $input['theme_preset'] );
            $clean['theme_preset'] = in_array( $value, array( 'indigo', 'ocean', 'emerald', 'violet', 'rose', 'sunset', 'midnight', 'custom' ), true ) ? $value : 'indigo';
        }

        if ( isset( $input['launcher_icon'] ) ) {
            $value = sanitize_key( (string) $input['launcher_icon'] );
            $clean['launcher_icon'] = in_array( $value, array( 'message', 'chat', 'headset', 'support', 'sparkle', 'bot', 'phone', 'mail' ), true ) ? $value : 'message';
        }

        if ( isset( $input['launcher_shape'] ) ) {
            $value = sanitize_key( (string) $input['launcher_shape'] );
            $clean['launcher_shape'] = in_array( $value, array( 'circle', 'rounded', 'pill' ), true ) ? $value : 'circle';
        }

        if ( isset( $input['launcher_animation'] ) ) {
            $value = sanitize_key( (string) $input['launcher_animation'] );
            $clean['launcher_animation'] = in_array( $value, array( 'none', 'pulse', 'float', 'glow' ), true ) ? $value : 'pulse';
        }

        if ( isset( $input['launcher_size'] ) ) {
            $clean['launcher_size'] = max( 48, min( 84, absint( $input['launcher_size'] ) ) );
        }
        if ( isset( $input['panel_width'] ) ) {
            $clean['panel_width'] = max( 320, min( 520, absint( $input['panel_width'] ) ) );
        }
        if ( isset( $input['panel_height'] ) ) {
            $clean['panel_height'] = max( 460, min( 820, absint( $input['panel_height'] ) ) );
        }
        if ( isset( $input['panel_radius'] ) ) {
            $clean['panel_radius'] = max( 12, min( 36, absint( $input['panel_radius'] ) ) );
        }
        if ( isset( $input['greeting_delay'] ) ) {
            $clean['greeting_delay'] = max( 0, min( 20000, absint( $input['greeting_delay'] ) ) );
        }
        if ( isset( $input['greeting_auto_hide'] ) ) {
            $clean['greeting_auto_hide'] = max( 3, min( 60, absint( $input['greeting_auto_hide'] ) ) );
        }

        return $clean;
    }
}
