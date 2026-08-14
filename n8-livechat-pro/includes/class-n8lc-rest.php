<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_REST {
    private static $instance;
    const NS = 'n8lc/v1';

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function hooks() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( self::NS, '/session', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_session' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NS, '/messages', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'visitor_messages' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'visitor_send' ),
                'permission_callback' => '__return_true',
            ),
        ) );

        register_rest_route( self::NS, '/heartbeat', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'heartbeat' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NS, '/typing', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'visitor_typing' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NS, '/state', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'visitor_state' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NS, '/upload', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'visitor_upload' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NS, '/close', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'visitor_close' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NS, '/rating', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'visitor_rating' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NS, '/admin/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'admin_stats' ),
            'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/conversations', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'admin_conversations' ),
            'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)/messages', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'admin_conversation_messages' ),
            'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)/state', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'admin_conversation_state' ),
            'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)/reply', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'admin_reply' ),
            'permission_callback' => array( 'N8LC_Security', 'reply_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)/typing', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'admin_typing' ),
            'permission_callback' => array( 'N8LC_Security', 'reply_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)/upload', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'admin_upload' ),
            'permission_callback' => array( 'N8LC_Security', 'reply_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)/transcript', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'admin_transcript' ),
            'permission_callback' => array( 'N8LC_Security', 'reply_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)/tags', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'conversation_tags' ),
                'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'save_conversation_tags' ),
                'permission_callback' => array( 'N8LC_Security', 'tag_permission' ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'admin_update_conversation' ),
            'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/visitors', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'admin_visitors' ),
            'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/canned-replies', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'canned_replies' ),
                'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_canned_reply' ),
                'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/canned-replies/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_canned_reply' ),
            'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/departments', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'departments' ),
                'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_department' ),
                'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/departments/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_department' ),
            'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/tags', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'tags' ),
                'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_tag' ),
                'permission_callback' => array( 'N8LC_Security', 'tag_permission' ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/tags/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_tag' ),
            'permission_callback' => array( 'N8LC_Security', 'tag_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/analytics', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'analytics' ),
            'permission_callback' => array( 'N8LC_Security', 'analytics_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/settings', array(
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
        ) );

        register_rest_route( self::NS, '/admin/audit', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'audit' ),
            'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/export', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'export_csv' ),
            'permission_callback' => array( 'N8LC_Security', 'export_permission' ),
        ) );
    }

    public function create_session( WP_REST_Request $request ) {
        global $wpdb;
        $settings = get_option( 'n8lc_settings', array() );
        if ( empty( $settings['enabled'] ) ) {
            return new WP_Error( 'n8lc_disabled', __( 'Live chat is currently disabled.', 'n8-livechat-pro' ), array( 'status' => 503 ) );
        }
        if ( ! N8LC_Security::rate_limit( 'session', 10, MINUTE_IN_SECONDS ) ) {
            return new WP_Error( 'n8lc_rate_limited', __( 'Too many chat attempts. Please try again shortly.', 'n8-livechat-pro' ), array( 'status' => 429 ) );
        }

        $name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
        $email = sanitize_email( (string) $request->get_param( 'email' ) );
        $phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );
        $custom_data = N8LC_Security::sanitize_custom_data( $request->get_param( 'custom_data' ) );
        $platform_settings = class_exists( 'N8LC_Platform' ) ? N8LC_Platform::settings() : array();
        if ( ! empty( $platform_settings['prechat_consent_enabled'] ) && ! empty( $platform_settings['prechat_consent_required'] ) && ! rest_sanitize_boolean( $request->get_param( 'consent' ) ) ) {
            return new WP_Error( 'n8lc_consent_required', __( 'Consent is required to start this chat.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        if ( class_exists( 'N8LC_Platform' ) && ! empty( $platform_settings['enable_custom_fields'] ) ) {
            foreach ( N8LC_Platform::instance()->active_custom_fields( 'prechat' ) as $field ) {
                if ( empty( $field['required'] ) ) {
                    continue;
                }
                $value = isset( $custom_data[ $field['key'] ] ) ? trim( (string) $custom_data[ $field['key'] ] ) : '';
                $missing = '' === $value;
                if ( 'checkbox' === $field['type'] ) {
                    $missing = ! in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
                }
                if ( $missing ) {
                    return new WP_Error( 'n8lc_required_field', sprintf( __( '%s is required.', 'n8-livechat-pro' ), $field['label'] ), array( 'status' => 400 ) );
                }
                if ( '' !== $value ) {
                    if ( 'email' === $field['type'] && ! is_email( $value ) ) {
                        return new WP_Error( 'n8lc_invalid_custom_field', sprintf( __( '%s must be a valid email address.', 'n8-livechat-pro' ), $field['label'] ), array( 'status' => 400 ) );
                    }
                    if ( 'number' === $field['type'] && ! is_numeric( $value ) ) {
                        return new WP_Error( 'n8lc_invalid_custom_field', sprintf( __( '%s must be a number.', 'n8-livechat-pro' ), $field['label'] ), array( 'status' => 400 ) );
                    }
                    if ( 'select' === $field['type'] && ! in_array( $value, (array) $field['options'], true ) ) {
                        return new WP_Error( 'n8lc_invalid_custom_field', sprintf( __( '%s contains an invalid option.', 'n8-livechat-pro' ), $field['label'] ), array( 'status' => 400 ) );
                    }
                }
            }
        }
        if ( ! empty( $settings['require_email'] ) && ! is_email( $email ) ) {
            return new WP_Error( 'n8lc_email_required', __( 'A valid email address is required.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }

        $token                  = N8LC_Security::generate_token();
        $visitor_public_id      = wp_generate_uuid4();
        $conversation_public_id = wp_generate_uuid4();
        $now                    = current_time( 'mysql' );
        $metadata               = array(
            'timezone'      => sanitize_text_field( (string) $request->get_param( 'timezone' ) ),
            'language'      => sanitize_text_field( (string) $request->get_param( 'language' ) ),
            'screen'        => sanitize_text_field( (string) $request->get_param( 'screen' ) ),
            'custom_fields' => $custom_data,
            'consent'       => rest_sanitize_boolean( $request->get_param( 'consent' ) ) ? 1 : 0,
        );
        $user_agent = ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        $ok = $wpdb->insert(
            N8LC_DB::table( 'visitors' ),
            array(
                'public_id'  => $visitor_public_id,
                'token_hash' => N8LC_Security::hash_token( $token ),
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'ip_hash'    => N8LC_Security::client_ip_hash(),
                'user_agent' => $user_agent,
                'last_url'   => esc_url_raw( (string) $request->get_param( 'url' ) ),
                'referrer'   => esc_url_raw( (string) $request->get_param( 'referrer' ) ),
                'metadata'   => wp_json_encode( $metadata ),
                'first_seen' => $now,
                'last_seen'  => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'n8lc_db_error', __( 'Unable to start chat.', 'n8-livechat-pro' ), array( 'status' => 500 ) );
        }
        $visitor_id = (int) $wpdb->insert_id;

        $department_id = absint( $request->get_param( 'department_id' ) );
        if ( $department_id ) {
            $department_id = (int) $wpdb->get_var(
                $wpdb->prepare( 'SELECT id FROM ' . N8LC_DB::table( 'departments' ) . ' WHERE id=%d AND is_active=1', $department_id )
            );
        }
        if ( ! $department_id ) {
            $department_id = (int) $wpdb->get_var( 'SELECT id FROM ' . N8LC_DB::table( 'departments' ) . ' WHERE is_active = 1 ORDER BY id ASC LIMIT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $initial_status = N8LC_Availability::is_open( $settings ) ? 'open' : 'pending';
        $wpdb->insert(
            N8LC_DB::table( 'conversations' ),
            array(
                'public_id'     => $conversation_public_id,
                'visitor_id'    => $visitor_id,
                'department_id' => $department_id ?: null,
                'status'        => $initial_status,
                'priority'      => 'normal',
                'subject'       => sanitize_text_field( (string) $request->get_param( 'subject' ) ),
                'source'        => 'widget',
                'custom_data'   => ! empty( $custom_data ) ? wp_json_encode( $custom_data ) : null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        $conversation_id = (int) $wpdb->insert_id;
        if ( ! $conversation_id ) {
            $wpdb->delete( N8LC_DB::table( 'visitors' ), array( 'id' => $visitor_id ), array( '%d' ) );
            return new WP_Error( 'n8lc_db_error', __( 'Unable to start chat.', 'n8-livechat-pro' ), array( 'status' => 500 ) );
        }

        $welcome = isset( $settings['welcome_message'] ) ? N8LC_Security::sanitize_message( $settings['welcome_message'] ) : '';
        if ( $welcome ) {
            $wpdb->insert(
                N8LC_DB::table( 'messages' ),
                array(
                    'conversation_id' => $conversation_id,
                    'sender_type'     => 'system',
                    'body'            => $welcome,
                    'message_type'    => 'text',
                    'is_private'      => 0,
                    'created_at'      => $now,
                ),
                array( '%d', '%s', '%s', '%s', '%d', '%s' )
            );
        }

        N8LC_DB::log_event( 'conversation_created', array( 'conversation_id' => $conversation_id, 'visitor_id' => $visitor_id ) );
        do_action( 'n8lc_conversation_created', $conversation_id, $visitor_id );

        return rest_ensure_response( array(
            'conversation_id'        => $conversation_id,
            'conversation_public_id' => $conversation_public_id,
            'visitor_id'             => $visitor_id,
            'visitor_public_id'      => $visitor_public_id,
            'token'                  => $token,
            'poll_interval'          => isset( $settings['poll_interval'] ) ? absint( $settings['poll_interval'] ) : 3000,
            'availability'           => N8LC_Availability::is_open( $settings ) ? 'online' : 'away',
        ) );
    }

    public function visitor_messages( WP_REST_Request $request ) {
        global $wpdb;
        $conversation_id = absint( $request->get_param( 'conversation_id' ) );
        $token           = N8LC_Security::request_token( $request );
        if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        $after_id = absint( $request->get_param( 'after_id' ) );
        $table    = N8LC_DB::table( 'messages' );
        $rows     = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id,sender_type,body,message_type,attachment_url,attachment_name,attachment_mime,attachment_size,seen_at,created_at FROM {$table} WHERE conversation_id=%d AND is_private=0 AND id>%d ORDER BY id ASC LIMIT 200",
                $conversation_id,
                $after_id
            ),
            ARRAY_A
        );
        $wpdb->update( N8LC_DB::table( 'conversations' ), array( 'unread_visitor' => 0 ), array( 'id' => $conversation_id ), array( '%d' ), array( '%d' ) );
        return rest_ensure_response( array( 'messages' => $rows ) );
    }

    public function visitor_send( WP_REST_Request $request ) {
        global $wpdb;
        if ( ! N8LC_Security::rate_limit( 'message', 45, MINUTE_IN_SECONDS ) ) {
            return new WP_Error( 'n8lc_rate_limited', __( 'You are sending messages too quickly.', 'n8-livechat-pro' ), array( 'status' => 429 ) );
        }
        $conversation_id = absint( $request->get_param( 'conversation_id' ) );
        $token           = N8LC_Security::request_token( $request );
        if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        $body = N8LC_Security::sanitize_message( $request->get_param( 'message' ) );
        if ( '' === $body ) {
            return new WP_Error( 'n8lc_empty_message', __( 'Message cannot be empty.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }

        $now = current_time( 'mysql' );
        $wpdb->insert(
            N8LC_DB::table( 'messages' ),
            array(
                'conversation_id' => $conversation_id,
                'sender_type'     => 'visitor',
                'body'            => $body,
                'message_type'    => 'text',
                'is_private'      => 0,
                'created_at'      => $now,
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%s' )
        );
        $message_id = (int) $wpdb->insert_id;
        $reopen     = N8LC_Availability::is_open() ? 'open' : 'pending';
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . N8LC_DB::table( 'conversations' ) . ' SET unread_agent=unread_agent+1,status=IF(status=%s,%s,status),last_message_at=%s,updated_at=%s WHERE id=%d',
                'closed',
                $reopen,
                $now,
                $now,
                $conversation_id
            )
        );
        $visitor_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT visitor_id FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id=%d', $conversation_id ) );
        $wpdb->update(
            N8LC_DB::table( 'visitors' ),
            array( 'last_seen' => $now, 'last_url' => esc_url_raw( (string) $request->get_param( 'url' ) ) ),
            array( 'id' => $visitor_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        delete_transient( 'n8lc_vtyping_' . $conversation_id );
        N8LC_DB::log_event( 'visitor_message', array( 'conversation_id' => $conversation_id, 'visitor_id' => $visitor_id ) );
        do_action( 'n8lc_message_created', $message_id, $conversation_id, 'visitor' );
        return rest_ensure_response( array( 'message_id' => $message_id, 'created_at' => $now ) );
    }

    public function heartbeat( WP_REST_Request $request ) {
        global $wpdb;
        $conversation_id = absint( $request->get_param( 'conversation_id' ) );
        $token           = N8LC_Security::request_token( $request );
        if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        $visitor_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT visitor_id FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id=%d', $conversation_id ) );
        $wpdb->update(
            N8LC_DB::table( 'visitors' ),
            array( 'last_seen' => current_time( 'mysql' ), 'last_url' => esc_url_raw( (string) $request->get_param( 'url' ) ) ),
            array( 'id' => $visitor_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        return rest_ensure_response( array( 'ok' => true ) );
    }

    public function visitor_typing( WP_REST_Request $request ) {
        $conversation_id = absint( $request->get_param( 'conversation_id' ) );
        $token           = N8LC_Security::request_token( $request );
        if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        if ( ! N8LC_Security::rate_limit( 'typing', 120, MINUTE_IN_SECONDS ) ) {
            return rest_ensure_response( array( 'ok' => true ) );
        }
        $typing = rest_sanitize_boolean( $request->get_param( 'typing' ) );
        $key    = 'n8lc_vtyping_' . $conversation_id;
        if ( $typing ) {
            set_transient( $key, array( 'at' => time() ), 10 );
        } else {
            delete_transient( $key );
        }
        return rest_ensure_response( array( 'ok' => true ) );
    }

    public function visitor_state( WP_REST_Request $request ) {
        global $wpdb;
        $conversation_id = absint( $request->get_param( 'conversation_id' ) );
        $token           = N8LC_Security::request_token( $request );
        if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT status,priority,agent_id,csat_rating FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id=%d', $conversation_id ),
            ARRAY_A
        );
        $typing = get_transient( 'n8lc_atyping_' . $conversation_id );
        return rest_ensure_response( array(
            'status'       => $row ? $row['status'] : 'open',
            'priority'     => $row ? $row['priority'] : 'normal',
            'csat_rating'  => $row && null !== $row['csat_rating'] ? (int) $row['csat_rating'] : null,
            'agent_typing' => is_array( $typing ) ? $typing : null,
            'availability' => N8LC_Availability::is_open() ? 'online' : 'away',
        ) );
    }

    public function visitor_upload( WP_REST_Request $request ) {
        $settings = get_option( 'n8lc_settings', array() );
        if ( empty( $settings['uploads_enabled'] ) ) {
            return new WP_Error( 'n8lc_uploads_disabled', __( 'File uploads are disabled.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        if ( ! N8LC_Security::rate_limit( 'upload', 12, HOUR_IN_SECONDS ) ) {
            return new WP_Error( 'n8lc_rate_limited', __( 'Too many uploads. Please try again later.', 'n8-livechat-pro' ), array( 'status' => 429 ) );
        }
        $conversation_id = absint( $request->get_param( 'conversation_id' ) );
        $token           = N8LC_Security::request_token( $request );
        if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        return $this->handle_upload_message( $request, $conversation_id, 'visitor', 0 );
    }

    public function visitor_close( WP_REST_Request $request ) {
        global $wpdb;
        $conversation_id = absint( $request->get_param( 'conversation_id' ) );
        $token           = N8LC_Security::request_token( $request );
        if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        $now = current_time( 'mysql' );
        $wpdb->update(
            N8LC_DB::table( 'conversations' ),
            array( 'status' => 'closed', 'closed_at' => $now, 'updated_at' => $now ),
            array( 'id' => $conversation_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
        N8LC_DB::log_event( 'visitor_closed', array( 'conversation_id' => $conversation_id ) );
        do_action( 'n8lc_conversation_updated', $conversation_id, array( 'status' => 'closed' ) );
        return rest_ensure_response( array( 'ok' => true ) );
    }

    public function visitor_rating( WP_REST_Request $request ) {
        global $wpdb;
        $settings        = get_option( 'n8lc_settings', array() );
        $conversation_id = absint( $request->get_param( 'conversation_id' ) );
        $token           = N8LC_Security::request_token( $request );
        if ( empty( $settings['csat_enabled'] ) ) {
            return new WP_Error( 'n8lc_csat_disabled', __( 'Ratings are disabled.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
            return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
        }
        $rating  = absint( $request->get_param( 'rating' ) );
        $comment = N8LC_Security::sanitize_message( $request->get_param( 'comment' ) );
        if ( $rating < 1 || $rating > 5 ) {
            return new WP_Error( 'n8lc_invalid_rating', __( 'Rating must be between 1 and 5.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        if ( function_exists( 'mb_substr' ) ) {
            $comment = mb_substr( $comment, 0, 1000 );
        } else {
            $comment = substr( $comment, 0, 1000 );
        }
        $wpdb->update(
            N8LC_DB::table( 'conversations' ),
            array( 'csat_rating' => $rating, 'csat_comment' => $comment, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $conversation_id ),
            array( '%d', '%s', '%s' ),
            array( '%d' )
        );
        N8LC_DB::log_event( 'csat_submitted', array( 'conversation_id' => $conversation_id, 'payload' => array( 'rating' => $rating ) ) );
        do_action( 'n8lc_csat_submitted', $conversation_id, $rating );
        return rest_ensure_response( array( 'ok' => true, 'rating' => $rating ) );
    }

    public function admin_stats() {
        global $wpdb;
        $c             = N8LC_DB::table( 'conversations' );
        $v             = N8LC_DB::table( 'visitors' );
        $m             = N8LC_DB::table( 'messages' );
        $today         = current_time( 'Y-m-d 00:00:00' );
        $online_cutoff = wp_date( 'Y-m-d H:i:s', time() - 300, wp_timezone() );
        return rest_ensure_response( array(
            'open'                => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} WHERE status='open'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'pending'             => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} WHERE status='pending'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'unread'              => (int) $wpdb->get_var( "SELECT COALESCE(SUM(unread_agent),0) FROM {$c}" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'sla_breached'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} WHERE sla_breached=1 AND status IN ('open','pending')" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'online_visitors'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$v} WHERE last_seen >= %s", $online_cutoff ) ),
            'conversations_today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$c} WHERE created_at >= %s", $today ) ),
            'messages_today'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$m} WHERE created_at >= %s", $today ) ),
            'availability'        => N8LC_Availability::is_open() ? 'online' : 'away',
        ) );
    }

    public function admin_conversations( WP_REST_Request $request ) {
        global $wpdb;
        $c        = N8LC_DB::table( 'conversations' );
        $v        = N8LC_DB::table( 'visitors' );
        $d        = N8LC_DB::table( 'departments' );
        $status   = sanitize_key( (string) $request->get_param( 'status' ) );
        $priority = sanitize_key( (string) $request->get_param( 'priority' ) );
        $search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
        $page     = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page = min( 100, max( 10, absint( $request->get_param( 'per_page' ) ) ?: 30 ) );
        $where    = '1=1';
        $args     = array();

        if ( in_array( $status, array( 'open', 'pending', 'closed' ), true ) ) {
            $where  .= ' AND c.status=%s';
            $args[] = $status;
        }
        if ( in_array( $priority, array( 'low', 'normal', 'high', 'urgent' ), true ) ) {
            $where  .= ' AND c.priority=%s';
            $args[] = $priority;
        }
        if ( $search ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND (v.name LIKE %s OR v.email LIKE %s OR c.subject LIKE %s)';
            array_push( $args, $like, $like, $like );
        }
        if ( rest_sanitize_boolean( $request->get_param( 'sla_breached' ) ) ) {
            $where .= ' AND c.sla_breached=1';
        }

        $offset = ( $page - 1 ) * $per_page;
        $args[] = $per_page;
        $args[] = $offset;
        $sql = "SELECT c.*,v.name visitor_name,v.email visitor_email,v.phone visitor_phone,v.last_seen visitor_last_seen,d.name department_name,u.display_name agent_name
                FROM {$c} c INNER JOIN {$v} v ON v.id=c.visitor_id
                LEFT JOIN {$d} d ON d.id=c.department_id
                LEFT JOIN {$wpdb->users} u ON u.ID=c.agent_id
                WHERE {$where}
                ORDER BY COALESCE(c.last_message_at,c.created_at) DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ( $rows as &$row ) {
            $row['tags']        = $this->tags_for_conversation( $row['id'] );
            $row['custom_data'] = ! empty( $row['custom_data'] ) ? json_decode( $row['custom_data'], true ) : array();
        }
        unset( $row );
        return rest_ensure_response( array( 'conversations' => $rows, 'page' => $page ) );
    }

    public function admin_conversation_messages( WP_REST_Request $request ) {
        global $wpdb;
        $id    = absint( $request['id'] );
        $table = N8LC_DB::table( 'messages' );
        $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE conversation_id=%d ORDER BY id ASC LIMIT 500", $id ), ARRAY_A );
        $wpdb->update( N8LC_DB::table( 'conversations' ), array( 'unread_agent' => 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
        return rest_ensure_response( array( 'messages' => $rows ) );
    }

    public function admin_conversation_state( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return rest_ensure_response( array(
            'visitor_typing' => (bool) get_transient( 'n8lc_vtyping_' . $id ),
            'tags'           => $this->tags_for_conversation( $id ),
        ) );
    }

    public function admin_reply( WP_REST_Request $request ) {
        global $wpdb;
        $id         = absint( $request['id'] );
        $body       = N8LC_Security::sanitize_message( $request->get_param( 'message' ) );
        $is_private = rest_sanitize_boolean( $request->get_param( 'is_private' ) );
        if ( '' === $body ) {
            return new WP_Error( 'n8lc_empty_message', __( 'Message cannot be empty.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        if ( ! $this->conversation_exists( $id ) ) {
            return new WP_Error( 'n8lc_not_found', __( 'Conversation not found.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
        }
        $now      = current_time( 'mysql' );
        $agent_id = get_current_user_id();
        $wpdb->insert(
            N8LC_DB::table( 'messages' ),
            array(
                'conversation_id' => $id,
                'sender_type'     => $is_private ? 'note' : 'agent',
                'sender_id'       => $agent_id,
                'body'            => $body,
                'message_type'    => $is_private ? 'note' : 'text',
                'is_private'      => $is_private ? 1 : 0,
                'created_at'      => $now,
            ),
            array( '%d', '%s', '%d', '%s', '%s', '%d', '%s' )
        );
        $message_id = (int) $wpdb->insert_id;
        if ( $is_private ) {
            $wpdb->query( $wpdb->prepare( 'UPDATE ' . N8LC_DB::table( 'conversations' ) . ' SET agent_id=COALESCE(agent_id,%d),updated_at=%s WHERE id=%d', $agent_id, $now, $id ) );
        } else {
            $wpdb->query( $wpdb->prepare( 'UPDATE ' . N8LC_DB::table( 'conversations' ) . ' SET agent_id=COALESCE(agent_id,%d),unread_visitor=unread_visitor+1,last_message_at=%s,updated_at=%s WHERE id=%d', $agent_id, $now, $now, $id ) );
        }
        delete_transient( 'n8lc_atyping_' . $id );
        N8LC_DB::log_event( $is_private ? 'private_note' : 'agent_message', array( 'conversation_id' => $id, 'agent_id' => $agent_id ) );
        do_action( 'n8lc_message_created', $message_id, $id, $is_private ? 'note' : 'agent' );
        return rest_ensure_response( array( 'message_id' => $message_id, 'created_at' => $now ) );
    }

    public function admin_typing( WP_REST_Request $request ) {
        $id     = absint( $request['id'] );
        $typing = rest_sanitize_boolean( $request->get_param( 'typing' ) );
        $key    = 'n8lc_atyping_' . $id;
        if ( $typing ) {
            $user = wp_get_current_user();
            set_transient( $key, array( 'agent_id' => get_current_user_id(), 'name' => $user->display_name, 'at' => time() ), 10 );
        } else {
            delete_transient( $key );
        }
        return rest_ensure_response( array( 'ok' => true ) );
    }

    public function admin_upload( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        if ( ! $this->conversation_exists( $id ) ) {
            return new WP_Error( 'n8lc_not_found', __( 'Conversation not found.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
        }
        return $this->handle_upload_message( $request, $id, 'agent', get_current_user_id() );
    }

    public function admin_transcript( WP_REST_Request $request ) {
        $id     = absint( $request['id'] );
        $result = N8LC_Automation::instance()->email_transcript( $id );
        if ( is_wp_error( $result ) ) {
            $result->add_data( array( 'status' => 400 ) );
            return $result;
        }
        return rest_ensure_response( array( 'ok' => true ) );
    }

    public function admin_update_conversation( WP_REST_Request $request ) {
        global $wpdb;
        $id      = absint( $request['id'] );
        $data    = array();
        $formats = array();
        $status  = sanitize_key( (string) $request->get_param( 'status' ) );
        $priority = sanitize_key( (string) $request->get_param( 'priority' ) );

        if ( in_array( $status, array( 'open', 'pending', 'closed' ), true ) ) {
            $data['status'] = $status;
            $formats[]      = '%s';
            if ( 'closed' === $status ) {
                $data['closed_at'] = current_time( 'mysql' );
                $formats[]         = '%s';
            } else {
                $data['closed_at'] = null;
                $formats[]         = '%s';
            }
        }
        if ( in_array( $priority, array( 'low', 'normal', 'high', 'urgent' ), true ) ) {
            $data['priority'] = $priority;
            $formats[]        = '%s';
        }
        if ( null !== $request->get_param( 'agent_id' ) ) {
            $data['agent_id'] = absint( $request->get_param( 'agent_id' ) ) ?: null;
            $formats[]        = '%d';
        }
        if ( null !== $request->get_param( 'department_id' ) ) {
            $data['department_id'] = absint( $request->get_param( 'department_id' ) ) ?: null;
            $formats[]             = '%d';
        }
        if ( null !== $request->get_param( 'subject' ) ) {
            $data['subject'] = sanitize_text_field( (string) $request->get_param( 'subject' ) );
            $formats[]       = '%s';
        }
        if ( null !== $request->get_param( 'custom_data' ) ) {
            $data['custom_data'] = wp_json_encode( N8LC_Security::sanitize_custom_data( $request->get_param( 'custom_data' ) ) );
            $formats[]           = '%s';
        }
        if ( empty( $data ) ) {
            return new WP_Error( 'n8lc_no_changes', __( 'No valid changes supplied.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        $data['updated_at'] = current_time( 'mysql' );
        $formats[]          = '%s';
        $wpdb->update( N8LC_DB::table( 'conversations' ), $data, array( 'id' => $id ), $formats, array( '%d' ) );
        N8LC_DB::log_event( 'conversation_updated', array( 'conversation_id' => $id, 'agent_id' => get_current_user_id(), 'payload' => $data ) );
        do_action( 'n8lc_conversation_updated', $id, $data );
        return rest_ensure_response( array( 'ok' => true ) );
    }

    public function admin_visitors( WP_REST_Request $request ) {
        global $wpdb;
        $v      = N8LC_DB::table( 'visitors' );
        $c      = N8LC_DB::table( 'conversations' );
        $search = sanitize_text_field( (string) $request->get_param( 'search' ) );
        $where  = '1=1';
        $args   = array();
        if ( $search ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= ' AND (v.name LIKE %s OR v.email LIKE %s OR v.phone LIKE %s)';
            $args   = array( $like, $like, $like );
        }
        $sql = "SELECT v.id,v.public_id,v.name,v.email,v.phone,v.last_url,v.referrer,v.first_seen,v.last_seen,
                (SELECT COUNT(*) FROM {$c} cx WHERE cx.visitor_id=v.id) conversations
                FROM {$v} v WHERE {$where} ORDER BY v.last_seen DESC LIMIT 200";
        $rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( array( 'visitors' => $rows ) );
    }

    public function canned_replies() {
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT * FROM ' . N8LC_DB::table( 'canned_replies' ) . ' ORDER BY title ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return rest_ensure_response( array( 'canned_replies' => $rows ) );
    }

    public function save_canned_reply( WP_REST_Request $request ) {
        global $wpdb;
        $title = sanitize_text_field( (string) $request->get_param( 'title' ) );
        $body  = N8LC_Security::sanitize_message( $request->get_param( 'body' ) );
        if ( ! $title || ! $body ) {
            return new WP_Error( 'n8lc_invalid_reply', __( 'Title and message are required.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        $now = current_time( 'mysql' );
        $wpdb->insert(
            N8LC_DB::table( 'canned_replies' ),
            array(
                'title'      => $title,
                'shortcut'   => sanitize_key( (string) $request->get_param( 'shortcut' ) ),
                'body'       => $body,
                'author_id'  => get_current_user_id(),
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );
        return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id ) );
    }

    public function delete_canned_reply( WP_REST_Request $request ) {
        global $wpdb;
        $wpdb->delete( N8LC_DB::table( 'canned_replies' ), array( 'id' => absint( $request['id'] ) ), array( '%d' ) );
        return rest_ensure_response( array( 'ok' => true ) );
    }

    public function departments() {
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT * FROM ' . N8LC_DB::table( 'departments' ) . ' ORDER BY is_active DESC,name ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return rest_ensure_response( array( 'departments' => $rows ) );
    }

    public function save_department( WP_REST_Request $request ) {
        global $wpdb;
        $name = sanitize_text_field( (string) $request->get_param( 'name' ) );
        if ( ! $name ) {
            return new WP_Error( 'n8lc_invalid_department', __( 'Department name is required.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        $now  = current_time( 'mysql' );
        $slug = sanitize_title( (string) ( $request->get_param( 'slug' ) ?: $name ) );
        $ok   = $wpdb->insert(
            N8LC_DB::table( 'departments' ),
            array(
                'name'        => $name,
                'slug'        => $slug,
                'description' => sanitize_textarea_field( (string) $request->get_param( 'description' ) ),
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'n8lc_department_exists', __( 'A department with that slug may already exist.', 'n8-livechat-pro' ), array( 'status' => 409 ) );
        }
        return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id ) );
    }

    public function delete_department( WP_REST_Request $request ) {
        global $wpdb;
        $id = absint( $request['id'] );
        $wpdb->update( N8LC_DB::table( 'conversations' ), array( 'department_id' => null ), array( 'department_id' => $id ), array( '%d' ), array( '%d' ) );
        $wpdb->delete( N8LC_DB::table( 'departments' ), array( 'id' => $id ), array( '%d' ) );
        return rest_ensure_response( array( 'ok' => true ) );
    }

    public function tags() {
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT * FROM ' . N8LC_DB::table( 'tags' ) . ' ORDER BY name ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return rest_ensure_response( array( 'tags' => $rows ) );
    }

    public function save_tag( WP_REST_Request $request ) {
        global $wpdb;
        $name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
        $slug  = sanitize_title( (string) ( $request->get_param( 'slug' ) ?: $name ) );
        $color = sanitize_hex_color( (string) $request->get_param( 'color' ) );
        if ( ! $name || ! $slug ) {
            return new WP_Error( 'n8lc_invalid_tag', __( 'Tag name is required.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        $now = current_time( 'mysql' );
        $ok  = $wpdb->insert(
            N8LC_DB::table( 'tags' ),
            array( 'name' => $name, 'slug' => $slug, 'color' => $color ?: '#64748b', 'created_at' => $now, 'updated_at' => $now ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'n8lc_tag_exists', __( 'A tag with that slug may already exist.', 'n8-livechat-pro' ), array( 'status' => 409 ) );
        }
        return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id ) );
    }

    public function delete_tag( WP_REST_Request $request ) {
        global $wpdb;
        $id = absint( $request['id'] );
        $wpdb->delete( N8LC_DB::table( 'conversation_tags' ), array( 'tag_id' => $id ), array( '%d' ) );
        $wpdb->delete( N8LC_DB::table( 'tags' ), array( 'id' => $id ), array( '%d' ) );
        return rest_ensure_response( array( 'ok' => true ) );
    }

    public function conversation_tags( WP_REST_Request $request ) {
        return rest_ensure_response( array( 'tags' => $this->tags_for_conversation( absint( $request['id'] ) ) ) );
    }

    public function save_conversation_tags( WP_REST_Request $request ) {
        global $wpdb;
        $conversation_id = absint( $request['id'] );
        $tag_ids         = $request->get_param( 'tag_ids' );
        $tag_ids         = is_array( $tag_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $tag_ids ) ) ) ) : array();
        $wpdb->delete( N8LC_DB::table( 'conversation_tags' ), array( 'conversation_id' => $conversation_id ), array( '%d' ) );
        $now = current_time( 'mysql' );
        foreach ( array_slice( $tag_ids, 0, 30 ) as $tag_id ) {
            $valid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . N8LC_DB::table( 'tags' ) . ' WHERE id=%d', $tag_id ) );
            if ( $valid ) {
                $wpdb->insert(
                    N8LC_DB::table( 'conversation_tags' ),
                    array( 'conversation_id' => $conversation_id, 'tag_id' => $tag_id, 'created_at' => $now ),
                    array( '%d', '%d', '%s' )
                );
            }
        }
        N8LC_DB::log_event( 'tags_updated', array( 'conversation_id' => $conversation_id, 'agent_id' => get_current_user_id(), 'payload' => array( 'tag_ids' => $tag_ids ) ) );
        return rest_ensure_response( array( 'tags' => $this->tags_for_conversation( $conversation_id ) ) );
    }

    public function analytics( WP_REST_Request $request ) {
        global $wpdb;
        $days  = min( 90, max( 7, absint( $request->get_param( 'days' ) ) ?: 30 ) );
        $start = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - ( DAY_IN_SECONDS * ( $days - 1 ) ) );
        $c     = N8LC_DB::table( 'conversations' );
        $m     = N8LC_DB::table( 'messages' );
        $daily_conversations = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) day,COUNT(*) total FROM {$c} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC", $start ), ARRAY_A );
        $daily_messages      = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) day,COUNT(*) total FROM {$m} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC", $start ), ARRAY_A );
        $by_status           = $wpdb->get_results( "SELECT status,COUNT(*) total FROM {$c} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $by_department       = $wpdb->get_results( 'SELECT COALESCE(d.name,\'Unassigned\') department,COUNT(c.id) total FROM ' . $c . ' c LEFT JOIN ' . N8LC_DB::table( 'departments' ) . ' d ON d.id=c.department_id GROUP BY c.department_id,d.name ORDER BY total DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $avg_first_response  = $wpdb->get_var( $wpdb->prepare( "SELECT AVG(TIMESTAMPDIFF(MINUTE,created_at,first_response_at)) FROM {$c} WHERE created_at >= %s AND first_response_at IS NOT NULL", $start ) );
        $avg_csat            = $wpdb->get_var( $wpdb->prepare( "SELECT AVG(csat_rating) FROM {$c} WHERE created_at >= %s AND csat_rating IS NOT NULL", $start ) );
        $sla_breaches        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$c} WHERE created_at >= %s AND sla_breached=1", $start ) );

        return rest_ensure_response( array(
            'days'                => $days,
            'daily_conversations' => $daily_conversations,
            'daily_messages'      => $daily_messages,
            'by_status'           => $by_status,
            'by_department'       => $by_department,
            'avg_first_response'  => null !== $avg_first_response ? round( (float) $avg_first_response, 1 ) : null,
            'avg_csat'            => null !== $avg_csat ? round( (float) $avg_csat, 2 ) : null,
            'sla_breaches'        => $sla_breaches,
        ) );
    }

    public function get_settings() {
        $settings = get_option( 'n8lc_settings', array() );
        return rest_ensure_response( $settings );
    }

    public function save_settings( WP_REST_Request $request ) {
        $current = get_option( 'n8lc_settings', array() );
        $input   = $request->get_json_params();
        $input   = is_array( $input ) ? $input : array();
        $clean   = $this->sanitize_settings( $input, $current );
        $next    = array_merge( $current, $clean );
        update_option( 'n8lc_settings', $next );
        N8LC_DB::log_event( 'settings_updated', array( 'agent_id' => get_current_user_id(), 'payload' => array( 'keys' => array_keys( $clean ) ) ) );
        return rest_ensure_response( $next );
    }

    public function audit( WP_REST_Request $request ) {
        global $wpdb;
        $event_type = sanitize_key( (string) $request->get_param( 'event_type' ) );
        $limit      = min( 500, max( 20, absint( $request->get_param( 'limit' ) ) ?: 100 ) );
        $table      = N8LC_DB::table( 'events' );
        if ( $event_type ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT e.*,u.display_name agent_name FROM {$table} e LEFT JOIN {$wpdb->users} u ON u.ID=e.agent_id WHERE event_type=%s ORDER BY e.id DESC LIMIT %d", $event_type, $limit ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT e.*,u.display_name agent_name FROM {$table} e LEFT JOIN {$wpdb->users} u ON u.ID=e.agent_id ORDER BY e.id DESC LIMIT %d", $limit ), ARRAY_A );
        }
        return rest_ensure_response( array( 'events' => $rows ) );
    }

    public function export_csv( WP_REST_Request $request ) {
        global $wpdb;
        $limit = min( 5000, max( 100, absint( $request->get_param( 'limit' ) ) ?: 2000 ) );
        $c     = N8LC_DB::table( 'conversations' );
        $v     = N8LC_DB::table( 'visitors' );
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.id,c.status,c.priority,c.subject,c.source,c.agent_id,c.department_id,c.first_response_at,c.first_response_due_at,c.resolution_due_at,c.sla_breached,c.csat_rating,c.created_at,c.closed_at,v.name visitor_name,v.email visitor_email,v.phone visitor_phone FROM {$c} c INNER JOIN {$v} v ON v.id=c.visitor_id ORDER BY c.id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        $headers = array( 'id','status','priority','subject','source','agent_id','department_id','first_response_at','first_response_due_at','resolution_due_at','sla_breached','csat_rating','created_at','closed_at','visitor_name','visitor_email','visitor_phone' );
        $lines   = array( $this->csv_line( $headers ) );
        foreach ( $rows as $row ) {
            $values = array();
            foreach ( $headers as $header ) {
                $values[] = isset( $row[ $header ] ) ? $row[ $header ] : '';
            }
            $lines[] = $this->csv_line( $values );
        }
        return rest_ensure_response( array(
            'filename' => 'n8-livechat-export-' . gmdate( 'Y-m-d' ) . '.csv',
            'csv'      => implode( "\r\n", $lines ),
        ) );
    }

    private function handle_upload_message( WP_REST_Request $request, $conversation_id, $sender_type, $sender_id ) {
        global $wpdb;
        $files = $request->get_file_params();
        if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
            return new WP_Error( 'n8lc_no_file', __( 'No file was uploaded.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }
        $file     = $files['file'];
        $settings = get_option( 'n8lc_settings', array() );
        $max_mb   = max( 1, min( 25, absint( isset( $settings['max_upload_mb'] ) ? $settings['max_upload_mb'] : 5 ) ) );
        if ( ! empty( $file['size'] ) && (int) $file['size'] > ( $max_mb * MB_IN_BYTES ) ) {
            return new WP_Error( 'n8lc_file_too_large', sprintf( __( 'File exceeds the %d MB upload limit.', 'n8-livechat-pro' ), $max_mb ), array( 'status' => 400 ) );
        }
        if ( ! empty( $file['error'] ) ) {
            return new WP_Error( 'n8lc_upload_error', __( 'The upload failed before it could be processed.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }

        $validation = apply_filters( 'n8lc_validate_upload', true, $file, absint( $conversation_id ), sanitize_key( $sender_type ) );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }
        if ( false === $validation ) {
            return new WP_Error( 'n8lc_upload_rejected', __( 'This file was rejected by the site upload policy.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded = wp_handle_upload(
            $file,
            array(
                'test_form' => false,
                'mimes'     => N8LC_Security::allowed_upload_mimes(),
            )
        );
        if ( isset( $uploaded['error'] ) ) {
            return new WP_Error( 'n8lc_upload_error', sanitize_text_field( $uploaded['error'] ), array( 'status' => 400 ) );
        }

        $mime         = isset( $uploaded['type'] ) ? sanitize_text_field( $uploaded['type'] ) : '';
        $url          = isset( $uploaded['url'] ) ? esc_url_raw( $uploaded['url'] ) : '';
        $name         = sanitize_file_name( isset( $file['name'] ) ? $file['name'] : basename( $url ) );
        $message_type = 0 === strpos( $mime, 'image/' ) ? 'image' : 'file';
        $now          = current_time( 'mysql' );
        $size         = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
        $is_private   = 0;

        $wpdb->insert(
            N8LC_DB::table( 'messages' ),
            array(
                'conversation_id' => absint( $conversation_id ),
                'sender_type'     => sanitize_key( $sender_type ),
                'sender_id'       => $sender_id ? absint( $sender_id ) : null,
                'body'            => $name,
                'message_type'    => $message_type,
                'is_private'      => $is_private,
                'attachment_url'  => $url,
                'attachment_name' => $name,
                'attachment_mime' => $mime,
                'attachment_size' => $size,
                'created_at'      => $now,
            ),
            array( '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
        );
        $message_id = (int) $wpdb->insert_id;

        if ( 'visitor' === $sender_type ) {
            $reopen = N8LC_Availability::is_open() ? 'open' : 'pending';
            $wpdb->query( $wpdb->prepare( 'UPDATE ' . N8LC_DB::table( 'conversations' ) . ' SET unread_agent=unread_agent+1,status=IF(status=%s,%s,status),last_message_at=%s,updated_at=%s WHERE id=%d', 'closed', $reopen, $now, $now, $conversation_id ) );
        } else {
            $wpdb->query( $wpdb->prepare( 'UPDATE ' . N8LC_DB::table( 'conversations' ) . ' SET agent_id=COALESCE(agent_id,%d),unread_visitor=unread_visitor+1,last_message_at=%s,updated_at=%s WHERE id=%d', absint( $sender_id ), $now, $now, $conversation_id ) );
        }

        N8LC_DB::log_event( 'attachment_uploaded', array( 'conversation_id' => $conversation_id, 'agent_id' => 'agent' === $sender_type ? $sender_id : null, 'payload' => array( 'mime' => $mime, 'size' => $size ) ) );
        do_action( 'n8lc_message_created', $message_id, absint( $conversation_id ), sanitize_key( $sender_type ) );

        return rest_ensure_response( array(
            'message_id'      => $message_id,
            'message_type'    => $message_type,
            'attachment_url'  => $url,
            'attachment_name' => $name,
            'attachment_mime' => $mime,
            'attachment_size' => $size,
            'created_at'      => $now,
        ) );
    }

    private function tags_for_conversation( $conversation_id ) {
        global $wpdb;
        $ct = N8LC_DB::table( 'conversation_tags' );
        $t  = N8LC_DB::table( 'tags' );
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT t.id,t.name,t.slug,t.color FROM {$ct} ct INNER JOIN {$t} t ON t.id=ct.tag_id WHERE ct.conversation_id=%d ORDER BY t.name ASC", absint( $conversation_id ) ),
            ARRAY_A
        );
    }

    private function conversation_exists( $id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id=%d', absint( $id ) ) );
    }

    private function sanitize_settings( array $input, array $current ) {
        $clean = array();
        foreach ( array( 'enabled','require_email','privacy_mode','business_hours_enabled','uploads_enabled','auto_assign_enabled','sla_enabled','email_notifications','escalation_email','csat_enabled','webhook_enabled','delete_data_on_uninstall' ) as $key ) {
            if ( array_key_exists( $key, $input ) ) {
                $clean[ $key ] = rest_sanitize_boolean( $input[ $key ] ) ? 1 : 0;
            }
        }
        foreach ( array( 'widget_title','welcome_message','offline_message' ) as $key ) {
            if ( array_key_exists( $key, $input ) ) {
                $clean[ $key ] = 'widget_title' === $key ? sanitize_text_field( (string) $input[ $key ] ) : N8LC_Security::sanitize_message( $input[ $key ] );
            }
        }
        if ( isset( $input['position'] ) ) {
            $clean['position'] = 'left' === $input['position'] ? 'left' : 'right';
        }
        if ( isset( $input['accent_color'] ) ) {
            $clean['accent_color'] = sanitize_hex_color( (string) $input['accent_color'] ) ?: '#111827';
        }
        if ( isset( $input['poll_interval'] ) ) {
            $clean['poll_interval'] = max( 1500, min( 30000, absint( $input['poll_interval'] ) ) );
        }
        if ( isset( $input['retention_days'] ) ) {
            $clean['retention_days'] = max( 7, min( 3650, absint( $input['retention_days'] ) ) );
        }
        if ( isset( $input['max_upload_mb'] ) ) {
            $clean['max_upload_mb'] = max( 1, min( 25, absint( $input['max_upload_mb'] ) ) );
        }
        if ( isset( $input['auto_assign_mode'] ) ) {
            $clean['auto_assign_mode'] = 'round_robin' === sanitize_key( $input['auto_assign_mode'] ) ? 'round_robin' : 'load';
        }
        if ( isset( $input['first_response_minutes'] ) ) {
            $clean['first_response_minutes'] = max( 1, min( 10080, absint( $input['first_response_minutes'] ) ) );
        }
        if ( isset( $input['resolution_minutes'] ) ) {
            $clean['resolution_minutes'] = max( 1, min( 43200, absint( $input['resolution_minutes'] ) ) );
        }
        if ( isset( $input['webhook_url'] ) ) {
            $clean['webhook_url'] = esc_url_raw( (string) $input['webhook_url'] );
        }
        if ( isset( $input['webhook_secret'] ) ) {
            $secret = sanitize_text_field( (string) $input['webhook_secret'] );
            $clean['webhook_secret'] = $secret ? substr( $secret, 0, 190 ) : ( isset( $current['webhook_secret'] ) ? $current['webhook_secret'] : wp_generate_password( 40, false, false ) );
        }
        if ( isset( $input['business_hours'] ) && is_array( $input['business_hours'] ) ) {
            $clean['business_hours'] = $this->sanitize_business_hours( $input['business_hours'] );
        }
        return $clean;
    }

    private function sanitize_business_hours( array $hours ) {
        $out = N8LC_DB::default_business_hours();
        foreach ( array( 'mon','tue','wed','thu','fri','sat','sun' ) as $day ) {
            if ( empty( $hours[ $day ] ) || ! is_array( $hours[ $day ] ) ) {
                continue;
            }
            $out[ $day ]['enabled'] = rest_sanitize_boolean( isset( $hours[ $day ]['enabled'] ) ? $hours[ $day ]['enabled'] : false ) ? 1 : 0;
            foreach ( array( 'start','end' ) as $part ) {
                $value = isset( $hours[ $day ][ $part ] ) ? preg_replace( '/[^0-9:]/', '', (string) $hours[ $day ][ $part ] ) : $out[ $day ][ $part ];
                if ( preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
                    $out[ $day ][ $part ] = $value;
                }
            }
        }
        return $out;
    }

    private function csv_line( array $values ) {
        $escaped = array();
        foreach ( $values as $value ) {
            $value = (string) $value;
            if ( preg_match( '/^[=+\-@]/', $value ) ) {
                $value = "'" . $value;
            }
            $value     = str_replace( '"', '""', $value );
            $escaped[] = '"' . $value . '"';
        }
        return implode( ',', $escaped );
    }
}
