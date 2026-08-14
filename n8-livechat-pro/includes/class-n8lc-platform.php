<?php

defined( 'ABSPATH' ) || exit;

/**
 * Professional operations layer for N8 LiveChat Pro.
 *
 * This module is deliberately additive. It owns its own schema/options and
 * extends the stable v0.3 chat core through hooks and REST endpoints.
 */
final class N8LC_Platform {
    const SCHEMA_VERSION = '1.0.0';
    const OPTION         = 'n8lc_platform_settings';
    const VERSION_OPTION = 'n8lc_platform_schema_version';

    private static $instance;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function hooks() {
        $this->maybe_install();

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 40 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ), 30 );
        add_filter( 'rest_pre_dispatch', array( $this, 'enforce_blocks' ), 20, 3 );

        add_action( 'n8lc_conversation_created', array( $this, 'apply_routing_rules' ), 30, 2 );
        add_action( 'n8lc_conversation_created', array( $this, 'integration_conversation_created' ), 50, 2 );
        add_action( 'n8lc_message_created', array( $this, 'integration_message_created' ), 50, 3 );
        add_action( 'n8lc_conversation_updated', array( $this, 'integration_conversation_updated' ), 50, 1 );
        add_action( 'n8lc_csat_submitted', array( $this, 'integration_csat_submitted' ), 50, 2 );
        add_action( 'n8lc_after_daily_cleanup', array( $this, 'privacy_retention_cleanup' ), 30 );
    }

    public static function defaults() {
        return array(
            'workspace_name'             => 'Support Workspace',
            'inbox_density'              => 'comfortable',
            'default_inbox_status'       => 'open',
            'default_sort'               => 'recent',
            'enable_customer_profiles'   => 1,
            'enable_saved_views'         => 1,
            'enable_segments'            => 1,
            'enable_custom_fields'       => 1,
            'enable_routing_rules'       => 1,
            'enable_knowledge_base'      => 1,
            'enable_integrations'        => 1,
            'enable_privacy_tools'       => 1,
            'enable_health_checks'       => 1,
            'widget_show_knowledge'       => 1,
            'privacy_auto_anonymize'      => 0,
            'privacy_auto_delete_messages' => 0,
            'max_active_chats_default'   => 8,
            'widget_auto_open'           => 0,
            'widget_auto_open_delay'     => 8,
            'widget_hide_mobile'         => 0,
            'widget_hide_desktop'        => 0,
            'widget_offset_x'            => 20,
            'widget_offset_y'            => 20,
            'widget_z_index'             => 999999,
            'widget_font_scale'          => 100,
            'widget_reduce_motion'       => 0,
            'widget_rtl'                 => 0,
            'widget_page_exclusions'     => '',
            'chat_auto_close_idle'        => 1,
            'chat_idle_timeout_minutes'   => 15,
            'chat_show_session_timer'     => 1,
            'prechat_consent_enabled'    => 0,
            'prechat_consent_required'   => 0,
            'prechat_consent_text'       => 'I agree that my details may be used to respond to this support request.',
            'privacy_retention_messages' => 365,
            'privacy_anonymize_after'    => 730,
        );
    }

    public static function settings() {
        $current = get_option( self::OPTION, array() );
        $current = is_array( $current ) ? $current : array();
        return wp_parse_args( $current, self::defaults() );
    }

    public static function table( $name ) {
        global $wpdb;
        $allowed = array( 'agent_profiles', 'routing_rules', 'saved_views', 'segments', 'custom_fields', 'integrations', 'blocks' );
        if ( ! in_array( $name, $allowed, true ) ) {
            return '';
        }
        return $wpdb->prefix . 'n8lc_' . $name;
    }

    private function maybe_install() {
        if ( self::SCHEMA_VERSION !== get_option( self::VERSION_OPTION ) ) {
            $this->install_schema();
            $this->install_capabilities();
            update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
        }

        $current = get_option( self::OPTION, null );
        if ( null === $current || ! is_array( $current ) ) {
            add_option( self::OPTION, self::defaults(), '', false );
        } else {
            $merged = wp_parse_args( $current, self::defaults() );
            if ( $merged !== $current ) {
                update_option( self::OPTION, $merged, false );
            }
        }
    }

    private function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $agent_profiles = self::table( 'agent_profiles' );
        $routing_rules  = self::table( 'routing_rules' );
        $saved_views    = self::table( 'saved_views' );
        $segments       = self::table( 'segments' );
        $custom_fields  = self::table( 'custom_fields' );
        $integrations   = self::table( 'integrations' );
        $blocks         = self::table( 'blocks' );

        $sql = array();
        $sql[] = "CREATE TABLE {$agent_profiles} (
            user_id bigint(20) unsigned NOT NULL,
            title varchar(120) NOT NULL DEFAULT '',
            avatar_url text NULL,
            availability varchar(20) NOT NULL DEFAULT 'auto',
            max_active_chats int(10) unsigned NOT NULL DEFAULT 8,
            languages text NULL,
            skills text NULL,
            department_ids text NULL,
            email_notifications tinyint(1) NOT NULL DEFAULT 1,
            browser_notifications tinyint(1) NOT NULL DEFAULT 1,
            sound_notifications tinyint(1) NOT NULL DEFAULT 1,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (user_id),
            KEY availability (availability)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$routing_rules} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            priority int(11) NOT NULL DEFAULT 100,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            match_json longtext NULL,
            action_json longtext NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY is_active (is_active),
            KEY priority (priority)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$saved_views} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            owner_id bigint(20) unsigned NOT NULL,
            name varchar(190) NOT NULL,
            filters longtext NULL,
            is_shared tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY owner_id (owner_id),
            KEY is_shared (is_shared)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$segments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            description text NULL,
            rules longtext NULL,
            color varchar(20) NOT NULL DEFAULT '#64748b',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY is_active (is_active)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$custom_fields} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            field_key varchar(120) NOT NULL,
            label varchar(190) NOT NULL,
            scope varchar(24) NOT NULL DEFAULT 'prechat',
            field_type varchar(24) NOT NULL DEFAULT 'text',
            options_json longtext NULL,
            placeholder varchar(190) NOT NULL DEFAULT '',
            is_required tinyint(1) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 100,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY field_key (field_key),
            KEY scope (scope),
            KEY is_active (is_active)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$integrations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            integration_type varchar(40) NOT NULL DEFAULT 'webhook',
            endpoint_url text NOT NULL,
            secret varchar(190) NOT NULL DEFAULT '',
            events longtext NULL,
            is_active tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY integration_type (integration_type),
            KEY is_active (is_active)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$blocks} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            visitor_id bigint(20) unsigned NULL,
            email_hash char(64) NOT NULL DEFAULT '',
            ip_hash char(64) NOT NULL DEFAULT '',
            reason varchar(255) NOT NULL DEFAULT '',
            expires_at datetime NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY visitor_id (visitor_id),
            KEY email_hash (email_hash),
            KEY ip_hash (ip_hash),
            KEY expires_at (expires_at)
        ) {$charset};";

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }
    }

    private function install_capabilities() {
        $caps = array(
            'n8lc_manage_team',
            'n8lc_manage_routing',
            'n8lc_manage_fields',
            'n8lc_manage_integrations',
            'n8lc_manage_privacy',
            'n8lc_manage_knowledge',
            'n8lc_view_health',
        );

        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            foreach ( $caps as $cap ) {
                $administrator->add_cap( $cap );
            }
        }

        $manager_caps = array(
            'read'                     => true,
            'n8lc_manage_chat'         => true,
            'n8lc_reply_chat'          => true,
            'n8lc_manage_tags'         => true,
            'n8lc_view_analytics'      => true,
            'n8lc_export_chat'         => true,
            'n8lc_manage_settings'     => true,
            'n8lc_manage_team'         => true,
            'n8lc_manage_routing'      => true,
            'n8lc_manage_fields'       => true,
            'n8lc_manage_integrations' => true,
            'n8lc_manage_privacy'      => true,
            'n8lc_manage_knowledge'    => true,
            'n8lc_view_health'         => true,
        );
        $manager = add_role( 'n8_livechat_manager', __( 'N8 LiveChat Manager', 'n8-livechat-pro' ), $manager_caps );
        if ( ! $manager ) {
            $manager = get_role( 'n8_livechat_manager' );
            if ( $manager ) {
                foreach ( $manager_caps as $cap => $grant ) {
                    $manager->add_cap( $cap, $grant );
                }
            }
        }

    }

    public function admin_menu() {
        add_submenu_page(
            'n8-livechat',
            __( 'Platform', 'n8-livechat-pro' ),
            __( 'Platform', 'n8-livechat-pro' ),
            'n8lc_manage_chat',
            'n8-livechat-platform',
            array( $this, 'render_admin' )
        );
    }

    public function admin_assets( $hook ) {
        if ( false === strpos( (string) $hook, 'n8-livechat-platform' ) ) {
            return;
        }
        wp_enqueue_style( 'n8lc-platform-v04', N8LC_URL . 'assets/css/platform-v04.css', array(), N8LC_VERSION );
        wp_enqueue_script( 'n8lc-platform-v04', N8LC_URL . 'assets/js/platform-v04.js', array(), N8LC_VERSION, true );
        wp_localize_script(
            'n8lc-platform-v04',
            'N8LCPlatform',
            array(
                'restRoot' => esc_url_raw( rest_url( N8LC_REST::NS . '/admin/platform/' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'version'  => N8LC_VERSION,
                'userId'   => get_current_user_id(),
            )
        );
    }

    public function render_admin() {
        if ( ! current_user_can( 'n8lc_manage_chat' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'n8-livechat-pro' ) );
        }
        echo '<div class="wrap n8lc-platform-wrap">';
        echo '<div class="n8lc-platform-hero"><div><span class="n8lc-kicker">' . esc_html__( 'Professional workspace', 'n8-livechat-pro' ) . '</span><h1>' . esc_html__( 'N8 LiveChat Platform', 'n8-livechat-pro' ) . '</h1><p>' . esc_html__( 'Team operations, routing, customer data, integrations, privacy and system controls.', 'n8-livechat-pro' ) . '</p></div><span class="n8lc-platform-version">v' . esc_html( N8LC_VERSION ) . '</span></div>';
        echo '<div id="n8lc-platform-app"><div class="n8lc-platform-loading">' . esc_html__( 'Loading workspace…', 'n8-livechat-pro' ) . '</div></div>';
        echo '</div>';
    }

    public function frontend_assets() {
        $base = get_option( 'n8lc_settings', array() );
        if ( empty( $base['enabled'] ) || ! wp_script_is( 'n8lc-widget', 'enqueued' ) ) {
            return;
        }

        wp_enqueue_style( 'n8lc-widget-v04', N8LC_URL . 'assets/css/widget-v04.css', array( 'n8lc-widget' ), N8LC_VERSION );
        wp_enqueue_script( 'n8lc-widget-v04', N8LC_URL . 'assets/js/widget-v04.js', array( 'n8lc-widget' ), N8LC_VERSION, true );

        $fields = $this->active_custom_fields( 'prechat' );
        $settings = self::settings();
        wp_localize_script(
            'n8lc-widget-v04',
            'N8LCPro',
            array(
                'customFields'    => $fields,
                'autoOpen'        => ! empty( $settings['widget_auto_open'] ),
                'autoOpenDelay'   => absint( $settings['widget_auto_open_delay'] ),
                'hideMobile'      => ! empty( $settings['widget_hide_mobile'] ),
                'hideDesktop'     => ! empty( $settings['widget_hide_desktop'] ),
                'offsetX'         => absint( $settings['widget_offset_x'] ),
                'offsetY'         => absint( $settings['widget_offset_y'] ),
                'zIndex'          => absint( $settings['widget_z_index'] ),
                'fontScale'       => absint( $settings['widget_font_scale'] ),
                'reduceMotion'    => ! empty( $settings['widget_reduce_motion'] ),
                'rtl'             => ! empty( $settings['widget_rtl'] ),
                'pageExclusions'  => $this->split_lines( $settings['widget_page_exclusions'] ),
                'consentEnabled'  => ! empty( $settings['prechat_consent_enabled'] ),
                'consentRequired' => ! empty( $settings['prechat_consent_required'] ),
                'consentText'     => sanitize_text_field( $settings['prechat_consent_text'] ),
                'showKnowledge'    => ! empty( $settings['enable_knowledge_base'] ) && ! empty( $settings['widget_show_knowledge'] ),
                'knowledgeUrl'     => esc_url_raw( rest_url( N8LC_REST::NS . '/knowledge?limit=3' ) ),
            )
        );
    }

    public function register_routes() {
        $ns = N8LC_REST::NS;

        register_rest_route( $ns, '/admin/platform/summary', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_summary' ),
            'permission_callback' => array( $this, 'can_manage_chat' ),
        ) );
        register_rest_route( $ns, '/admin/platform/settings', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_settings' ),
                'permission_callback' => array( $this, 'can_manage_settings' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_save_settings' ),
                'permission_callback' => array( $this, 'can_manage_settings' ),
            ),
        ) );
        register_rest_route( $ns, '/admin/platform/agents', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_agents' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_save_agent' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
        ) );

        register_rest_route( $ns, '/admin/platform/customers', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_customers' ),
            'permission_callback' => array( $this, 'can_manage_chat' ),
        ) );
        register_rest_route( $ns, '/admin/platform/customers/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_customer' ),
                'permission_callback' => array( $this, 'can_manage_chat' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_save_customer' ),
                'permission_callback' => array( $this, 'can_manage_chat' ),
            ),
        ) );

        foreach ( array( 'routing-rules', 'saved-views', 'segments', 'custom-fields', 'integrations', 'blocks' ) as $resource ) {
            register_rest_route( $ns, '/admin/platform/' . $resource, array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => function () use ( $resource ) { return $this->rest_collection( $resource ); },
                    'permission_callback' => function () use ( $resource ) { return $this->can_resource( $resource ); },
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => function ( WP_REST_Request $request ) use ( $resource ) { return $this->rest_create( $resource, $request ); },
                    'permission_callback' => function () use ( $resource ) { return $this->can_resource( $resource ); },
                ),
            ) );
            register_rest_route( $ns, '/admin/platform/' . $resource . '/(?P<id>\d+)', array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => function ( WP_REST_Request $request ) use ( $resource ) { return $this->rest_update( $resource, $request ); },
                    'permission_callback' => function () use ( $resource ) { return $this->can_resource( $resource ); },
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => function ( WP_REST_Request $request ) use ( $resource ) { return $this->rest_delete( $resource, $request ); },
                    'permission_callback' => function () use ( $resource ) { return $this->can_resource( $resource ); },
                ),
            ) );
        }

        register_rest_route( $ns, '/admin/platform/bulk', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'rest_bulk_action' ),
            'permission_callback' => array( $this, 'can_manage_chat' ),
        ) );
        register_rest_route( $ns, '/admin/platform/diagnostics', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_diagnostics' ),
            'permission_callback' => array( $this, 'can_view_health' ),
        ) );
        register_rest_route( $ns, '/form-schema', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_form_schema' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function can_manage_chat() { return current_user_can( 'n8lc_manage_chat' ) || current_user_can( 'manage_options' ); }
    public function can_manage_settings() { return current_user_can( 'n8lc_manage_settings' ) || current_user_can( 'manage_options' ); }
    public function can_manage_team() { return current_user_can( 'n8lc_manage_team' ) || current_user_can( 'manage_options' ); }
    public function can_view_health() { return current_user_can( 'n8lc_view_health' ) || current_user_can( 'manage_options' ); }

    private function can_resource( $resource ) {
        $map = array(
            'routing-rules' => 'n8lc_manage_routing',
            'saved-views'   => 'n8lc_manage_chat',
            'segments'      => 'n8lc_manage_chat',
            'custom-fields' => 'n8lc_manage_fields',
            'integrations'  => 'n8lc_manage_integrations',
            'blocks'        => 'n8lc_manage_chat',
        );
        $cap = isset( $map[ $resource ] ) ? $map[ $resource ] : 'n8lc_manage_settings';
        return current_user_can( $cap ) || current_user_can( 'manage_options' );
    }

    public function rest_summary() {
        global $wpdb;
        $counts = array();
        foreach ( array( 'routing_rules', 'saved_views', 'segments', 'custom_fields', 'integrations', 'blocks' ) as $name ) {
            $table = self::table( $name );
            $counts[ $name ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        $agents = count( get_users( array( 'capability' => 'n8lc_reply_chat', 'fields' => 'ID' ) ) );
        $kb     = post_type_exists( 'n8lc_article' ) ? (int) wp_count_posts( 'n8lc_article' )->publish : 0;
        return rest_ensure_response( array(
            'workspace' => self::settings(),
            'counts'    => $counts,
            'agents'    => $agents,
            'knowledge_articles' => $kb,
            'wordpress' => get_bloginfo( 'version' ),
            'php'       => PHP_VERSION,
            'plugin'    => N8LC_VERSION,
        ) );
    }

    public function rest_get_settings() {
        return rest_ensure_response( self::settings() );
    }

    public function rest_save_settings( WP_REST_Request $request ) {
        $input = $request->get_json_params();
        $input = is_array( $input ) ? $input : array();
        $next  = array_merge( self::settings(), $this->sanitize_settings( $input ) );
        update_option( self::OPTION, $next, false );
        N8LC_DB::log_event( 'platform_settings_updated', array( 'agent_id' => get_current_user_id(), 'payload' => array( 'keys' => array_keys( $input ) ) ) );
        return rest_ensure_response( $next );
    }

    private function sanitize_settings( array $input ) {
        $clean = array();
        foreach ( array( 'enable_customer_profiles', 'enable_saved_views', 'enable_segments', 'enable_custom_fields', 'enable_routing_rules', 'enable_knowledge_base', 'enable_integrations', 'enable_privacy_tools', 'enable_health_checks', 'widget_auto_open', 'widget_hide_mobile', 'widget_hide_desktop', 'widget_reduce_motion', 'widget_rtl', 'widget_show_knowledge', 'prechat_consent_enabled', 'prechat_consent_required', 'privacy_auto_anonymize', 'privacy_auto_delete_messages', 'chat_auto_close_idle', 'chat_show_session_timer' ) as $key ) {
            if ( array_key_exists( $key, $input ) ) {
                $clean[ $key ] = rest_sanitize_boolean( $input[ $key ] ) ? 1 : 0;
            }
        }
        foreach ( array( 'workspace_name', 'prechat_consent_text' ) as $key ) {
            if ( array_key_exists( $key, $input ) ) {
                $clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
            }
        }
        if ( array_key_exists( 'widget_page_exclusions', $input ) ) {
            $clean['widget_page_exclusions'] = sanitize_textarea_field( (string) $input['widget_page_exclusions'] );
        }
        if ( isset( $input['inbox_density'] ) ) {
            $value = sanitize_key( $input['inbox_density'] );
            $clean['inbox_density'] = in_array( $value, array( 'compact', 'comfortable', 'spacious' ), true ) ? $value : 'comfortable';
        }
        if ( isset( $input['default_inbox_status'] ) ) {
            $value = sanitize_key( $input['default_inbox_status'] );
            $clean['default_inbox_status'] = in_array( $value, array( 'all', 'open', 'pending', 'closed' ), true ) ? $value : 'open';
        }
        if ( isset( $input['default_sort'] ) ) {
            $value = sanitize_key( $input['default_sort'] );
            $clean['default_sort'] = in_array( $value, array( 'recent', 'oldest', 'priority', 'sla' ), true ) ? $value : 'recent';
        }
        $ranges = array(
            'max_active_chats_default'   => array( 1, 100 ),
            'widget_auto_open_delay'     => array( 0, 120 ),
            'widget_offset_x'            => array( 0, 160 ),
            'widget_offset_y'            => array( 0, 160 ),
            'widget_z_index'             => array( 1000, 2147483000 ),
            'widget_font_scale'          => array( 80, 140 ),
            'privacy_retention_messages' => array( 7, 3650 ),
            'privacy_anonymize_after'    => array( 30, 3650 ),
            'chat_idle_timeout_minutes'  => array( 5, 1440 ),
        );
        foreach ( $ranges as $key => $range ) {
            if ( array_key_exists( $key, $input ) ) {
                $clean[ $key ] = max( $range[0], min( $range[1], absint( $input[ $key ] ) ) );
            }
        }
        if ( ! empty( $clean['prechat_consent_required'] ) ) {
            $clean['prechat_consent_enabled'] = 1;
        }
        return $clean;
    }

    public function rest_customers( WP_REST_Request $request ) {
        global $wpdb;
        $visitors = N8LC_DB::table( 'visitors' );
        $conversations = N8LC_DB::table( 'conversations' );
        $search = sanitize_text_field( (string) $request->get_param( 'search' ) );
        $page = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page = max( 10, min( 100, absint( $request->get_param( 'per_page' ) ?: 50 ) ) );
        $offset = ( $page - 1 ) * $per_page;
        $where = '1=1';
        $args = array();
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND (v.name LIKE %s OR v.email LIKE %s OR v.phone LIKE %s)';
            $args = array( $like, $like, $like );
        }
        $args[] = $per_page;
        $args[] = $offset;
        $sql = "SELECT v.*,
                (SELECT COUNT(*) FROM {$conversations} c WHERE c.visitor_id=v.id) conversation_count,
                (SELECT COUNT(*) FROM {$conversations} c WHERE c.visitor_id=v.id AND c.status='open') open_count
                FROM {$visitors} v WHERE {$where}
                ORDER BY v.last_seen DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( array(
            'items'    => array_map( array( $this, 'decode_customer' ), $rows ),
            'page'     => $page,
            'per_page' => $per_page,
        ) );
    }

    public function rest_customer( WP_REST_Request $request ) {
        global $wpdb;
        $id = absint( $request['id'] );
        $visitor = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . N8LC_DB::table( 'visitors' ) . ' WHERE id=%d', $id ), ARRAY_A );
        if ( ! $visitor ) {
            return new WP_Error( 'n8lc_customer_not_found', __( 'Customer was not found.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
        }
        $conversation_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE visitor_id=%d', $id ) );
        $visitor['conversation_count'] = $conversation_count;
        return rest_ensure_response( $this->decode_customer( $visitor ) );
    }

    public function rest_save_customer( WP_REST_Request $request ) {
        global $wpdb;
        $id = absint( $request['id'] );
        $table = N8LC_DB::table( 'visitors' );
        $visitor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! $visitor ) {
            return new WP_Error( 'n8lc_customer_not_found', __( 'Customer was not found.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
        }
        $input = $request->get_json_params();
        $input = is_array( $input ) ? $input : array();
        $data = array();
        if ( array_key_exists( 'name', $input ) ) $data['name'] = sanitize_text_field( (string) $input['name'] );
        if ( array_key_exists( 'email', $input ) ) {
            $email = sanitize_email( (string) $input['email'] );
            if ( $input['email'] && ! is_email( $email ) ) {
                return new WP_Error( 'n8lc_invalid_customer_email', __( 'Enter a valid customer email address.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
            }
            $data['email'] = $email;
        }
        if ( array_key_exists( 'phone', $input ) ) $data['phone'] = sanitize_text_field( (string) $input['phone'] );

        $metadata = ! empty( $visitor['metadata'] ) ? json_decode( $visitor['metadata'], true ) : array();
        $metadata = is_array( $metadata ) ? $metadata : array();
        $profile = isset( $metadata['profile'] ) && is_array( $metadata['profile'] ) ? $metadata['profile'] : array();
        if ( array_key_exists( 'company', $input ) ) $profile['company'] = sanitize_text_field( (string) $input['company'] );
        if ( array_key_exists( 'notes', $input ) ) $profile['notes'] = sanitize_textarea_field( (string) $input['notes'] );
        if ( array_key_exists( 'status', $input ) ) $profile['status'] = $this->enum( $input['status'], array( 'lead', 'customer', 'vip', 'at_risk' ), 'customer' );
        $profile['updated_by'] = get_current_user_id();
        $profile['updated_at'] = current_time( 'mysql' );
        $metadata['profile'] = $profile;
        $data['metadata'] = wp_json_encode( $metadata );
        if ( ! $data ) {
            return rest_ensure_response( $this->decode_customer( $visitor ) );
        }
        $wpdb->update( $table, $data, array( 'id' => $id ) );
        N8LC_DB::log_event( 'customer_profile_updated', array( 'visitor_id' => $id, 'agent_id' => get_current_user_id() ) );
        return $this->rest_customer( $request );
    }

    private function decode_customer( $row ) {
        if ( ! is_array( $row ) ) return $row;
        unset( $row['token_hash'], $row['ip_hash'], $row['user_agent'] );
        $metadata = ! empty( $row['metadata'] ) ? json_decode( $row['metadata'], true ) : array();
        $metadata = is_array( $metadata ) ? $metadata : array();
        $row['profile'] = isset( $metadata['profile'] ) && is_array( $metadata['profile'] ) ? $metadata['profile'] : array();
        $row['custom_fields'] = isset( $metadata['custom_fields'] ) && is_array( $metadata['custom_fields'] ) ? $metadata['custom_fields'] : array();
        $row['consent'] = ! empty( $metadata['consent'] );
        $row['context'] = array(
            'timezone' => isset( $metadata['timezone'] ) ? sanitize_text_field( $metadata['timezone'] ) : '',
            'language' => isset( $metadata['language'] ) ? sanitize_text_field( $metadata['language'] ) : '',
            'screen'   => isset( $metadata['screen'] ) ? sanitize_text_field( $metadata['screen'] ) : '',
        );
        unset( $row['metadata'] );
        foreach ( array( 'id', 'conversation_count', 'open_count' ) as $key ) {
            if ( isset( $row[ $key ] ) ) $row[ $key ] = (int) $row[ $key ];
        }
        return $row;
    }

    public function rest_agents() {
        global $wpdb;
        $table = self::table( 'agent_profiles' );
        $users = get_users( array( 'fields' => 'all' ) );
        $items = array();
        $can_onboard = current_user_can( 'manage_options' );
        foreach ( $users as $user ) {
            if ( ! ( $user instanceof WP_User ) ) {
                continue;
            }
            $is_agent   = in_array( 'n8_livechat_agent', (array) $user->roles, true );
            $is_manager = in_array( 'n8_livechat_manager', (array) $user->roles, true );
            $is_admin   = user_can( $user, 'manage_options' );
            if ( ! $can_onboard && ! user_can( $user, 'n8lc_reply_chat' ) && ! $is_manager && ! $is_admin ) {
                continue;
            }
            $profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id=%d", $user->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $items[] = array(
                'user_id'      => (int) $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'roles'        => array_values( $user->roles ),
                'access_level' => $is_admin ? 'administrator' : ( $is_manager ? 'manager' : ( $is_agent || user_can( $user, 'n8lc_reply_chat' ) ? 'agent' : 'none' ) ),
                'can_onboard'  => $can_onboard,
                'profile'      => $profile ? $this->decode_agent_profile( $profile ) : $this->default_agent_profile( $user->ID ),
            );
        }
        return rest_ensure_response( array( 'items' => $items ) );
    }

    public function rest_save_agent( WP_REST_Request $request ) {
        global $wpdb;
        $input   = $request->get_json_params();
        $input   = is_array( $input ) ? $input : array();
        $user_id = absint( isset( $input['user_id'] ) ? $input['user_id'] : 0 );
        $user    = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new WP_Error( 'n8lc_invalid_agent', __( 'Agent was not found.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
        }
        if ( array_key_exists( 'access_level', $input ) && current_user_can( 'manage_options' ) && ! user_can( $user, 'manage_options' ) ) {
            $access = $this->enum( $input['access_level'], array( 'none', 'agent', 'manager' ), 'agent' );
            $user->remove_role( 'n8_livechat_agent' );
            $user->remove_role( 'n8_livechat_manager' );
            if ( 'agent' === $access ) {
                $user->add_role( 'n8_livechat_agent' );
            } elseif ( 'manager' === $access ) {
                $user->add_role( 'n8_livechat_manager' );
            }
            N8LC_DB::log_event( 'team_access_updated', array( 'agent_id' => get_current_user_id(), 'payload' => array( 'user_id' => $user_id, 'access_level' => $access ) ) );
        }

        $settings = self::settings();
        $data = array(
            'user_id'               => $user_id,
            'title'                 => sanitize_text_field( isset( $input['title'] ) ? $input['title'] : '' ),
            'avatar_url'            => esc_url_raw( isset( $input['avatar_url'] ) ? $input['avatar_url'] : '' ),
            'availability'          => $this->enum( isset( $input['availability'] ) ? $input['availability'] : 'auto', array( 'auto', 'online', 'away', 'offline' ), 'auto' ),
            'max_active_chats'      => max( 1, min( 100, absint( isset( $input['max_active_chats'] ) ? $input['max_active_chats'] : $settings['max_active_chats_default'] ) ) ),
            'languages'             => wp_json_encode( $this->sanitize_string_list( isset( $input['languages'] ) ? $input['languages'] : array() ) ),
            'skills'                => wp_json_encode( $this->sanitize_string_list( isset( $input['skills'] ) ? $input['skills'] : array() ) ),
            'department_ids'        => wp_json_encode( array_values( array_filter( array_map( 'absint', (array) ( isset( $input['department_ids'] ) ? $input['department_ids'] : array() ) ) ) ) ),
            'email_notifications'   => ! empty( $input['email_notifications'] ) ? 1 : 0,
            'browser_notifications' => ! empty( $input['browser_notifications'] ) ? 1 : 0,
            'sound_notifications'   => ! empty( $input['sound_notifications'] ) ? 1 : 0,
            'updated_at'            => current_time( 'mysql' ),
        );
        $formats = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' );
        $wpdb->replace( self::table( 'agent_profiles' ), $data, $formats );
        N8LC_DB::log_event( 'agent_profile_updated', array( 'agent_id' => get_current_user_id(), 'payload' => array( 'user_id' => $user_id ) ) );
        return rest_ensure_response( $this->decode_agent_profile( $data ) );
    }

    private function default_agent_profile( $user_id ) {
        $settings = self::settings();
        return array(
            'user_id'               => (int) $user_id,
            'title'                 => '',
            'avatar_url'            => '',
            'availability'          => 'auto',
            'max_active_chats'      => absint( $settings['max_active_chats_default'] ),
            'languages'             => array(),
            'skills'                => array(),
            'department_ids'        => array(),
            'email_notifications'   => 1,
            'browser_notifications' => 1,
            'sound_notifications'   => 1,
        );
    }

    private function decode_agent_profile( array $row ) {
        foreach ( array( 'languages', 'skills', 'department_ids' ) as $key ) {
            $decoded = isset( $row[ $key ] ) ? json_decode( $row[ $key ], true ) : array();
            $row[ $key ] = is_array( $decoded ) ? $decoded : array();
        }
        foreach ( array( 'user_id', 'max_active_chats', 'email_notifications', 'browser_notifications', 'sound_notifications' ) as $key ) {
            if ( isset( $row[ $key ] ) ) {
                $row[ $key ] = (int) $row[ $key ];
            }
        }
        return $row;
    }

    public function rest_collection( $resource ) {
        global $wpdb;
        $meta = $this->resource_meta( $resource );
        if ( ! $meta ) {
            return new WP_Error( 'n8lc_invalid_resource', __( 'Unknown resource.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
        }
        $table = self::table( $meta['table'] );
        $where = '';
        if ( 'saved_views' === $meta['table'] && ! current_user_can( 'manage_options' ) ) {
            $where = $wpdb->prepare( ' WHERE owner_id=%d OR is_shared=1', get_current_user_id() );
        }
        $rows = $wpdb->get_results( "SELECT * FROM {$table}{$where} ORDER BY {$meta['order']} LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( array( 'items' => array_map( array( $this, 'decode_resource_row' ), $rows ) ) );
    }

    public function rest_create( $resource, WP_REST_Request $request ) {
        global $wpdb;
        $meta = $this->resource_meta( $resource );
        if ( ! $meta ) {
            return new WP_Error( 'n8lc_invalid_resource', __( 'Unknown resource.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
        }
        $input = $request->get_json_params();
        $data  = $this->sanitize_resource( $resource, is_array( $input ) ? $input : array(), false );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        $now = current_time( 'mysql' );
        $data['created_at'] = $now;
        if ( 'blocks' !== $resource ) {
            $data['updated_at'] = $now;
        }
        if ( in_array( $resource, array( 'routing-rules', 'integrations' ), true ) ) {
            $data['created_by'] = get_current_user_id();
        }
        if ( 'saved-views' === $resource ) {
            $data['owner_id'] = get_current_user_id();
        }
        if ( 'blocks' === $resource ) {
            $data['created_by'] = get_current_user_id();
            if ( empty( $data['visitor_id'] ) ) {
                return new WP_Error( 'n8lc_block_visitor_required', __( 'Select a valid visitor to block.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
            }
            $visitor = $wpdb->get_row( $wpdb->prepare( 'SELECT email,ip_hash FROM ' . N8LC_DB::table( 'visitors' ) . ' WHERE id=%d', $data['visitor_id'] ), ARRAY_A );
            if ( ! $visitor ) {
                return new WP_Error( 'n8lc_block_visitor_missing', __( 'The visitor to block was not found.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
            }
            $data['email_hash'] = self::email_hash( $visitor['email'] );
            $data['ip_hash']    = sanitize_text_field( (string) $visitor['ip_hash'] );
        }
        $ok = $wpdb->insert( self::table( $meta['table'] ), $data );
        if ( false === $ok ) {
            return new WP_Error( 'n8lc_db_error', __( 'Unable to save item.', 'n8-livechat-pro' ), array( 'status' => 500 ) );
        }
        $id = (int) $wpdb->insert_id;
        N8LC_DB::log_event( 'platform_' . sanitize_key( $resource ) . '_created', array( 'agent_id' => get_current_user_id(), 'payload' => array( 'id' => $id ) ) );
        return $this->rest_get_resource_row( $meta['table'], $id );
    }

    public function rest_update( $resource, WP_REST_Request $request ) {
        global $wpdb;
        $meta = $this->resource_meta( $resource );
        $id   = absint( $request['id'] );
        if ( ! $meta || ! $id ) {
            return new WP_Error( 'n8lc_invalid_resource', __( 'Invalid resource.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        if ( 'saved-views' === $resource && ! $this->can_edit_saved_view( $id ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'You cannot edit this saved view.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        $input = $request->get_json_params();
        $data  = $this->sanitize_resource( $resource, is_array( $input ) ? $input : array(), true );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        if ( 'blocks' !== $resource ) {
            $data['updated_at'] = current_time( 'mysql' );
        }
        if ( empty( $data ) ) {
            return $this->rest_get_resource_row( $meta['table'], $id );
        }
        $wpdb->update( self::table( $meta['table'] ), $data, array( 'id' => $id ) );
        N8LC_DB::log_event( 'platform_' . sanitize_key( $resource ) . '_updated', array( 'agent_id' => get_current_user_id(), 'payload' => array( 'id' => $id ) ) );
        return $this->rest_get_resource_row( $meta['table'], $id );
    }

    public function rest_delete( $resource, WP_REST_Request $request ) {
        global $wpdb;
        $meta = $this->resource_meta( $resource );
        $id   = absint( $request['id'] );
        if ( ! $meta || ! $id ) {
            return new WP_Error( 'n8lc_invalid_resource', __( 'Invalid resource.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        if ( 'saved-views' === $resource && ! $this->can_edit_saved_view( $id ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'You cannot delete this saved view.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        $deleted = $wpdb->delete( self::table( $meta['table'] ), array( 'id' => $id ), array( '%d' ) );
        return rest_ensure_response( array( 'deleted' => (bool) $deleted, 'id' => $id ) );
    }

    private function rest_get_resource_row( $table_name, $id ) {
        global $wpdb;
        $table = self::table( $table_name );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! $row ) {
            return new WP_Error( 'n8lc_not_found', __( 'Item not found.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
        }
        return rest_ensure_response( $this->decode_resource_row( $row ) );
    }

    private function resource_meta( $resource ) {
        $map = array(
            'routing-rules' => array( 'table' => 'routing_rules', 'order' => 'priority ASC, id ASC' ),
            'saved-views'   => array( 'table' => 'saved_views', 'order' => 'updated_at DESC' ),
            'segments'      => array( 'table' => 'segments', 'order' => 'name ASC' ),
            'custom-fields' => array( 'table' => 'custom_fields', 'order' => 'sort_order ASC, id ASC' ),
            'integrations'  => array( 'table' => 'integrations', 'order' => 'name ASC' ),
            'blocks'        => array( 'table' => 'blocks', 'order' => 'created_at DESC' ),
        );
        return isset( $map[ $resource ] ) ? $map[ $resource ] : null;
    }

    private function sanitize_resource( $resource, array $input, $partial ) {
        $out = array();
        if ( 'routing-rules' === $resource ) {
            if ( ! $partial || array_key_exists( 'name', $input ) ) $out['name'] = sanitize_text_field( isset( $input['name'] ) ? $input['name'] : '' );
            if ( ! $partial || array_key_exists( 'priority', $input ) ) $out['priority'] = max( 1, min( 9999, absint( isset( $input['priority'] ) ? $input['priority'] : 100 ) ) );
            if ( ! $partial || array_key_exists( 'is_active', $input ) ) $out['is_active'] = ! empty( $input['is_active'] ) ? 1 : 0;
            if ( ! $partial || array_key_exists( 'match', $input ) ) $out['match_json'] = wp_json_encode( $this->sanitize_rule_match( isset( $input['match'] ) ? $input['match'] : array() ) );
            if ( ! $partial || array_key_exists( 'action', $input ) ) $out['action_json'] = wp_json_encode( $this->sanitize_rule_action( isset( $input['action'] ) ? $input['action'] : array() ) );
        } elseif ( 'saved-views' === $resource ) {
            if ( ! $partial || array_key_exists( 'name', $input ) ) $out['name'] = sanitize_text_field( isset( $input['name'] ) ? $input['name'] : '' );
            if ( ! $partial || array_key_exists( 'filters', $input ) ) $out['filters'] = wp_json_encode( N8LC_Security::sanitize_custom_data( isset( $input['filters'] ) ? $input['filters'] : array() ) );
            if ( ! $partial || array_key_exists( 'is_shared', $input ) ) $out['is_shared'] = ! empty( $input['is_shared'] ) ? 1 : 0;
        } elseif ( 'segments' === $resource ) {
            if ( ! $partial || array_key_exists( 'name', $input ) ) $out['name'] = sanitize_text_field( isset( $input['name'] ) ? $input['name'] : '' );
            if ( ! $partial || array_key_exists( 'description', $input ) ) $out['description'] = sanitize_textarea_field( isset( $input['description'] ) ? $input['description'] : '' );
            if ( ! $partial || array_key_exists( 'rules', $input ) ) $out['rules'] = wp_json_encode( N8LC_Security::sanitize_custom_data( isset( $input['rules'] ) ? $input['rules'] : array() ) );
            if ( ! $partial || array_key_exists( 'color', $input ) ) $out['color'] = sanitize_hex_color( isset( $input['color'] ) ? $input['color'] : '#64748b' ) ?: '#64748b';
            if ( ! $partial || array_key_exists( 'is_active', $input ) ) $out['is_active'] = ! empty( $input['is_active'] ) ? 1 : 0;
        } elseif ( 'custom-fields' === $resource ) {
            if ( ! $partial || array_key_exists( 'field_key', $input ) ) {
                $key = sanitize_key( isset( $input['field_key'] ) ? $input['field_key'] : '' );
                if ( '' === $key ) return new WP_Error( 'n8lc_field_key', __( 'Field key is required.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
                $out['field_key'] = substr( $key, 0, 120 );
            }
            if ( ! $partial || array_key_exists( 'label', $input ) ) $out['label'] = sanitize_text_field( isset( $input['label'] ) ? $input['label'] : '' );
            if ( ! $partial || array_key_exists( 'scope', $input ) ) $out['scope'] = $this->enum( isset( $input['scope'] ) ? $input['scope'] : 'prechat', array( 'prechat', 'visitor', 'conversation' ), 'prechat' );
            if ( ! $partial || array_key_exists( 'field_type', $input ) ) $out['field_type'] = $this->enum( isset( $input['field_type'] ) ? $input['field_type'] : 'text', array( 'text', 'email', 'tel', 'number', 'textarea', 'select', 'checkbox' ), 'text' );
            if ( ! $partial || array_key_exists( 'options', $input ) ) $out['options_json'] = wp_json_encode( $this->sanitize_string_list( isset( $input['options'] ) ? $input['options'] : array() ) );
            if ( ! $partial || array_key_exists( 'placeholder', $input ) ) $out['placeholder'] = sanitize_text_field( isset( $input['placeholder'] ) ? $input['placeholder'] : '' );
            if ( ! $partial || array_key_exists( 'is_required', $input ) ) $out['is_required'] = ! empty( $input['is_required'] ) ? 1 : 0;
            if ( ! $partial || array_key_exists( 'is_active', $input ) ) $out['is_active'] = ! empty( $input['is_active'] ) ? 1 : 0;
            if ( ! $partial || array_key_exists( 'sort_order', $input ) ) $out['sort_order'] = max( 1, min( 9999, absint( isset( $input['sort_order'] ) ? $input['sort_order'] : 100 ) ) );
        } elseif ( 'integrations' === $resource ) {
            if ( ! $partial || array_key_exists( 'name', $input ) ) $out['name'] = sanitize_text_field( isset( $input['name'] ) ? $input['name'] : '' );
            if ( ! $partial || array_key_exists( 'integration_type', $input ) ) $out['integration_type'] = $this->enum( isset( $input['integration_type'] ) ? $input['integration_type'] : 'webhook', array( 'webhook', 'n8n', 'crm', 'custom' ), 'webhook' );
            if ( ! $partial || array_key_exists( 'endpoint_url', $input ) ) {
                $url = esc_url_raw( isset( $input['endpoint_url'] ) ? $input['endpoint_url'] : '' );
                if ( $url && 0 !== strpos( $url, 'https://' ) ) return new WP_Error( 'n8lc_https_required', __( 'Integration endpoints must use HTTPS.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
                if ( ! $partial && ! $url ) return new WP_Error( 'n8lc_integration_endpoint_required', __( 'An HTTPS integration endpoint is required.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
                $out['endpoint_url'] = $url;
            }
            if ( ! $partial || array_key_exists( 'secret', $input ) ) {
                $secret = sanitize_text_field( isset( $input['secret'] ) ? $input['secret'] : '' );
                $out['secret'] = $secret ?: wp_generate_password( 40, false, false );
            }
            if ( ! $partial || array_key_exists( 'events', $input ) ) $out['events'] = wp_json_encode( array_values( array_intersect( $this->sanitize_string_list( isset( $input['events'] ) ? $input['events'] : array() ), array( 'conversation.created', 'message.created', 'conversation.updated', 'csat.submitted' ) ) ) );
            if ( ! $partial || array_key_exists( 'is_active', $input ) ) $out['is_active'] = ! empty( $input['is_active'] ) ? 1 : 0;
        } elseif ( 'blocks' === $resource ) {
            if ( ! $partial || array_key_exists( 'visitor_id', $input ) ) $out['visitor_id'] = absint( isset( $input['visitor_id'] ) ? $input['visitor_id'] : 0 );
            if ( ! $partial || array_key_exists( 'reason', $input ) ) $out['reason'] = sanitize_text_field( isset( $input['reason'] ) ? $input['reason'] : '' );
            if ( ! $partial || array_key_exists( 'expires_at', $input ) ) $out['expires_at'] = $this->sanitize_datetime( isset( $input['expires_at'] ) ? $input['expires_at'] : '' );
        }
        return $out;
    }

    private function decode_resource_row( $row ) {
        if ( ! is_array( $row ) ) return $row;
        foreach ( array( 'match_json' => 'match', 'action_json' => 'action', 'filters' => 'filters', 'rules' => 'rules', 'options_json' => 'options', 'events' => 'events' ) as $source => $target ) {
            if ( array_key_exists( $source, $row ) ) {
                $decoded = json_decode( (string) $row[ $source ], true );
                $row[ $target ] = is_array( $decoded ) ? $decoded : array();
                unset( $row[ $source ] );
            }
        }
        foreach ( array( 'id', 'priority', 'is_active', 'owner_id', 'is_shared', 'sort_order', 'is_required', 'visitor_id', 'created_by' ) as $key ) {
            if ( isset( $row[ $key ] ) ) $row[ $key ] = (int) $row[ $key ];
        }
        if ( isset( $row['secret'] ) ) {
            $row['secret_masked'] = $row['secret'] ? substr( $row['secret'], 0, 4 ) . '••••••••' : '';
            unset( $row['secret'] );
        }
        return $row;
    }

    private function can_edit_saved_view( $id ) {
        if ( current_user_can( 'manage_options' ) ) return true;
        global $wpdb;
        $table = self::table( 'saved_views' );
        return (int) get_current_user_id() === (int) $wpdb->get_var( $wpdb->prepare( "SELECT owner_id FROM {$table} WHERE id=%d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public function rest_bulk_action( WP_REST_Request $request ) {
        global $wpdb;
        $input = $request->get_json_params();
        $input = is_array( $input ) ? $input : array();
        $ids   = array_values( array_unique( array_filter( array_map( 'absint', (array) ( isset( $input['conversation_ids'] ) ? $input['conversation_ids'] : array() ) ) ) ) );
        $ids   = array_slice( $ids, 0, 100 );
        if ( empty( $ids ) ) {
            return new WP_Error( 'n8lc_bulk_empty', __( 'Select at least one conversation.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        $action = sanitize_key( isset( $input['action'] ) ? $input['action'] : '' );
        $value  = isset( $input['value'] ) ? $input['value'] : '';
        $table  = N8LC_DB::table( 'conversations' );
        $changed = 0;
        foreach ( $ids as $id ) {
            $data = array();
            if ( 'status' === $action ) {
                $status = $this->enum( $value, array( 'open', 'pending', 'closed' ), '' );
                if ( $status ) $data['status'] = $status;
            } elseif ( 'priority' === $action ) {
                $priority = $this->enum( $value, array( 'low', 'normal', 'high', 'urgent' ), '' );
                if ( $priority ) $data['priority'] = $priority;
            } elseif ( 'agent' === $action ) {
                $data['agent_id'] = absint( $value ) ?: null;
            } elseif ( 'department' === $action ) {
                $data['department_id'] = absint( $value ) ?: null;
            }
            if ( ! $data ) continue;
            $data['updated_at'] = current_time( 'mysql' );
            $result = $wpdb->update( $table, $data, array( 'id' => $id ) );
            if ( false !== $result ) {
                $changed++;
                N8LC_DB::log_event( 'conversation_bulk_updated', array( 'conversation_id' => $id, 'agent_id' => get_current_user_id(), 'payload' => array( 'action' => $action, 'value' => $value ) ) );
                do_action( 'n8lc_conversation_updated', $id );
            }
        }
        return rest_ensure_response( array( 'changed' => $changed, 'selected' => count( $ids ) ) );
    }

    public function privacy_retention_cleanup() {
        $settings = self::settings();
        if ( empty( $settings['enable_privacy_tools'] ) ) {
            return;
        }

        global $wpdb;
        if ( ! empty( $settings['privacy_auto_anonymize'] ) && class_exists( 'N8LC_Privacy' ) ) {
            $cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( DAY_IN_SECONDS * absint( $settings['privacy_anonymize_after'] ) ) );
            $visitors = N8LC_DB::table( 'visitors' );
            $conversations = N8LC_DB::table( 'conversations' );
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT v.id FROM {$visitors} v
                     WHERE v.last_seen < %s AND v.email <> ''
                     AND NOT EXISTS (
                         SELECT 1 FROM {$conversations} c
                         WHERE c.visitor_id = v.id AND c.status <> 'closed'
                     )
                     ORDER BY v.id ASC LIMIT 100",
                    $cutoff
                )
            ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            foreach ( $ids as $visitor_id ) {
                N8LC_Privacy::instance()->anonymize_visitor( absint( $visitor_id ) );
            }
            if ( $ids ) {
                N8LC_DB::log_event( 'privacy_auto_anonymized', array( 'payload' => array( 'count' => count( $ids ), 'cutoff' => $cutoff ) ) );
            }
        }

        if ( ! empty( $settings['privacy_auto_delete_messages'] ) ) {
            $cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( DAY_IN_SECONDS * absint( $settings['privacy_retention_messages'] ) ) );
            $messages = N8LC_DB::table( 'messages' );
            $conversations = N8LC_DB::table( 'conversations' );
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT m.id FROM {$messages} m
                     INNER JOIN {$conversations} c ON c.id=m.conversation_id
                     WHERE c.status='closed' AND m.created_at < %s
                     ORDER BY m.id ASC LIMIT 1000",
                    $cutoff
                )
            ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
            if ( $ids ) {
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$messages} WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                N8LC_DB::log_event( 'privacy_messages_retained_cleanup', array( 'payload' => array( 'count' => count( $ids ), 'cutoff' => $cutoff ) ) );
            }
        }
    }

    public function rest_diagnostics() {
        global $wpdb;
        $tables = array();
        foreach ( array( 'agent_profiles', 'routing_rules', 'saved_views', 'segments', 'custom_fields', 'integrations', 'blocks' ) as $name ) {
            $table = self::table( $name );
            $tables[ $name ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        }
        return rest_ensure_response( array(
            'schema_version' => get_option( self::VERSION_OPTION ),
            'tables'         => $tables,
            'daily_cleanup'  => wp_next_scheduled( 'n8lc_daily_cleanup' ),
            'automation'     => wp_next_scheduled( 'n8lc_automation_tick' ),
            'rest_url'       => rest_url( N8LC_REST::NS . '/' ),
            'uploads_url'    => esc_url_raw( wp_upload_dir()['baseurl'] ),
            'wp_debug'       => defined( 'WP_DEBUG' ) && WP_DEBUG,
        ) );
    }

    public function rest_form_schema() {
        $settings = self::settings();
        return rest_ensure_response( array(
            'fields'           => ! empty( $settings['enable_custom_fields'] ) ? $this->active_custom_fields( 'prechat' ) : array(),
            'consent_enabled'  => ! empty( $settings['prechat_consent_enabled'] ),
            'consent_required' => ! empty( $settings['prechat_consent_required'] ),
            'consent_text'     => sanitize_text_field( $settings['prechat_consent_text'] ),
        ) );
    }

    public function active_custom_fields( $scope ) {
        global $wpdb;
        $table = self::table( 'custom_fields' );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,field_key,label,scope,field_type,options_json,placeholder,is_required,sort_order FROM {$table} WHERE is_active=1 AND scope=%s ORDER BY sort_order ASC,id ASC", $scope ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $items = array();
        foreach ( $rows as $row ) {
            $items[] = array(
                'id'          => (int) $row['id'],
                'key'         => $row['field_key'],
                'label'       => $row['label'],
                'type'        => $row['field_type'],
                'options'     => (array) json_decode( (string) $row['options_json'], true ),
                'placeholder' => $row['placeholder'],
                'required'    => (bool) $row['is_required'],
            );
        }
        return $items;
    }

    public function apply_routing_rules( $conversation_id, $visitor_id ) {
        $settings = self::settings();
        if ( empty( $settings['enable_routing_rules'] ) ) return;
        global $wpdb;
        $rules_table = self::table( 'routing_rules' );
        $rules = $wpdb->get_results( "SELECT * FROM {$rules_table} WHERE is_active=1 ORDER BY priority ASC,id ASC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! $rules ) return;
        $conversation = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id=%d', $conversation_id ), ARRAY_A );
        $visitor      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . N8LC_DB::table( 'visitors' ) . ' WHERE id=%d', $visitor_id ), ARRAY_A );
        if ( ! $conversation || ! $visitor ) return;
        foreach ( $rules as $rule ) {
            $match  = (array) json_decode( (string) $rule['match_json'], true );
            $action = (array) json_decode( (string) $rule['action_json'], true );
            if ( ! $this->rule_matches( $match, $conversation, $visitor ) ) continue;
            $update = array();
            if ( ! empty( $action['priority'] ) ) $update['priority'] = $this->enum( $action['priority'], array( 'low', 'normal', 'high', 'urgent' ), $conversation['priority'] );
            if ( ! empty( $action['status'] ) ) $update['status'] = $this->enum( $action['status'], array( 'open', 'pending' ), $conversation['status'] );
            if ( isset( $action['agent_id'] ) && absint( $action['agent_id'] ) ) $update['agent_id'] = absint( $action['agent_id'] );
            if ( isset( $action['department_id'] ) && absint( $action['department_id'] ) ) $update['department_id'] = absint( $action['department_id'] );
            if ( $update ) {
                $update['updated_at'] = current_time( 'mysql' );
                $wpdb->update( N8LC_DB::table( 'conversations' ), $update, array( 'id' => $conversation_id ) );
            }
            if ( ! empty( $action['tag_id'] ) ) {
                $wpdb->replace( N8LC_DB::table( 'conversation_tags' ), array( 'conversation_id' => $conversation_id, 'tag_id' => absint( $action['tag_id'] ), 'created_at' => current_time( 'mysql' ) ), array( '%d', '%d', '%s' ) );
            }
            N8LC_DB::log_event( 'routing_rule_matched', array( 'conversation_id' => $conversation_id, 'visitor_id' => $visitor_id, 'payload' => array( 'rule_id' => (int) $rule['id'], 'rule_name' => $rule['name'] ) ) );
            if ( empty( $action['continue'] ) ) break;
        }
    }

    private function rule_matches( array $match, array $conversation, array $visitor ) {
        if ( ! empty( $match['source'] ) && sanitize_key( $match['source'] ) !== $conversation['source'] ) return false;
        if ( ! empty( $match['department_id'] ) && absint( $match['department_id'] ) !== (int) $conversation['department_id'] ) return false;
        if ( ! empty( $match['email_domain'] ) ) {
            $parts = explode( '@', strtolower( (string) $visitor['email'] ) );
            $domain = count( $parts ) > 1 ? end( $parts ) : '';
            if ( strtolower( sanitize_text_field( $match['email_domain'] ) ) !== $domain ) return false;
        }
        if ( ! empty( $match['url_contains'] ) && false === stripos( (string) $visitor['last_url'], sanitize_text_field( $match['url_contains'] ) ) ) return false;
        if ( ! empty( $match['referrer_contains'] ) && false === stripos( (string) $visitor['referrer'], sanitize_text_field( $match['referrer_contains'] ) ) ) return false;
        if ( ! empty( $match['name_contains'] ) && false === stripos( (string) $visitor['name'], sanitize_text_field( $match['name_contains'] ) ) ) return false;
        if ( isset( $match['business_hours'] ) ) {
            $open = N8LC_Availability::is_open();
            if ( (bool) $match['business_hours'] !== (bool) $open ) return false;
        }
        return true;
    }

    private function sanitize_rule_match( $input ) {
        $input = is_array( $input ) ? $input : array();
        $out = array();
        foreach ( array( 'source', 'email_domain', 'url_contains', 'referrer_contains', 'name_contains' ) as $key ) {
            if ( isset( $input[ $key ] ) && '' !== $input[ $key ] ) $out[ $key ] = sanitize_text_field( $input[ $key ] );
        }
        if ( ! empty( $input['department_id'] ) ) $out['department_id'] = absint( $input['department_id'] );
        if ( array_key_exists( 'business_hours', $input ) ) $out['business_hours'] = (bool) rest_sanitize_boolean( $input['business_hours'] );
        return $out;
    }

    private function sanitize_rule_action( $input ) {
        $input = is_array( $input ) ? $input : array();
        $out = array();
        if ( ! empty( $input['priority'] ) ) $out['priority'] = $this->enum( $input['priority'], array( 'low', 'normal', 'high', 'urgent' ), 'normal' );
        if ( ! empty( $input['status'] ) ) $out['status'] = $this->enum( $input['status'], array( 'open', 'pending' ), 'open' );
        foreach ( array( 'agent_id', 'department_id', 'tag_id' ) as $key ) {
            if ( ! empty( $input[ $key ] ) ) $out[ $key ] = absint( $input[ $key ] );
        }
        if ( array_key_exists( 'continue', $input ) ) $out['continue'] = (bool) rest_sanitize_boolean( $input['continue'] );
        return $out;
    }

    public function integration_conversation_created( $conversation_id, $visitor_id ) {
        $this->dispatch_integrations( 'conversation.created', array( 'conversation_id' => (int) $conversation_id, 'visitor_id' => (int) $visitor_id ) );
    }
    public function integration_message_created( $message_id, $conversation_id, $sender_type ) {
        $this->dispatch_integrations( 'message.created', array( 'message_id' => (int) $message_id, 'conversation_id' => (int) $conversation_id, 'sender_type' => sanitize_key( $sender_type ) ) );
    }
    public function integration_conversation_updated( $conversation_id ) {
        $this->dispatch_integrations( 'conversation.updated', array( 'conversation_id' => (int) $conversation_id ) );
    }
    public function integration_csat_submitted( $conversation_id, $rating ) {
        $this->dispatch_integrations( 'csat.submitted', array( 'conversation_id' => (int) $conversation_id, 'rating' => (int) $rating ) );
    }

    private function dispatch_integrations( $event, array $data ) {
        $settings = self::settings();
        if ( empty( $settings['enable_integrations'] ) ) return;
        global $wpdb;
        $table = self::table( 'integrations' );
        $rows = $wpdb->get_results( "SELECT id,name,endpoint_url,secret,events FROM {$table} WHERE is_active=1 AND endpoint_url<>'' ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ( $rows as $row ) {
            $events = (array) json_decode( (string) $row['events'], true );
            if ( $events && ! in_array( $event, $events, true ) ) continue;
            $payload = array(
                'event'      => $event,
                'site_url'   => home_url( '/' ),
                'occurred_at'=> gmdate( 'c' ),
                'data'       => $data,
            );
            $body = wp_json_encode( $payload );
            $signature = hash_hmac( 'sha256', $body, (string) $row['secret'] );
            wp_safe_remote_post( $row['endpoint_url'], array(
                'timeout'   => 4,
                'blocking'  => false,
                'headers'   => array( 'Content-Type' => 'application/json', 'X-N8LC-Signature' => $signature, 'X-N8LC-Event' => $event ),
                'body'      => $body,
                'user-agent'=> 'N8-LiveChat-Pro/' . N8LC_VERSION . '; ' . home_url( '/' ),
            ) );
        }
    }

    public function enforce_blocks( $result, $server, $request ) {
        if ( is_wp_error( $result ) ) return $result;
        $route = $request->get_route();
        if ( 0 !== strpos( $route, '/' . N8LC_REST::NS . '/' ) || false !== strpos( $route, '/admin/' ) ) return $result;

        global $wpdb;
        $table = self::table( 'blocks' );
        $now   = current_time( 'mysql' );

        if ( '/' . N8LC_REST::NS . '/session' === $route ) {
            $email_hash = self::email_hash( $request->get_param( 'email' ) );
            $ip_hash    = N8LC_Security::client_ip_hash();
            $blocked = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE (expires_at IS NULL OR expires_at>%s) AND ((email_hash<>'' AND email_hash=%s) OR (ip_hash<>'' AND ip_hash=%s))",
                    $now,
                    $email_hash,
                    $ip_hash
                )
            ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( $blocked ) return new WP_Error( 'n8lc_blocked', __( 'Chat is not available for this visitor.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
            return $result;
        }

        $conversation_id = absint( $request->get_param( 'conversation_id' ) );
        if ( ! $conversation_id ) return $result;
        $visitor_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT visitor_id FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id=%d', $conversation_id ) );
        if ( ! $visitor_id ) return $result;
        $blocked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE visitor_id=%d AND (expires_at IS NULL OR expires_at>%s)", $visitor_id, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $blocked ) return new WP_Error( 'n8lc_blocked', __( 'This chat has been disabled.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        return $result;
    }

    private static function email_hash( $email ) {
        $email = strtolower( sanitize_email( (string) $email ) );
        return $email ? hash_hmac( 'sha256', $email, wp_salt( 'auth' ) ) : '';
    }

    private function sanitize_string_list( $value ) {
        if ( is_string( $value ) ) $value = preg_split( '/[,\n]/', $value );
        $out = array();
        foreach ( (array) $value as $item ) {
            $item = sanitize_text_field( (string) $item );
            if ( '' !== $item ) $out[] = substr( $item, 0, 120 );
        }
        return array_values( array_unique( array_slice( $out, 0, 100 ) ) );
    }

    private function enum( $value, array $allowed, $default ) {
        $value = sanitize_key( (string) $value );
        return in_array( $value, $allowed, true ) ? $value : $default;
    }

    private function sanitize_datetime( $value ) {
        $value = sanitize_text_field( (string) $value );
        if ( '' === $value ) return null;
        $timestamp = strtotime( $value );
        return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
    }

    private function split_lines( $value ) {
        $out = array();
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
            $line = trim( sanitize_text_field( $line ) );
            if ( '' !== $line ) $out[] = $line;
        }
        return array_values( array_unique( array_slice( $out, 0, 100 ) ) );
    }
}
