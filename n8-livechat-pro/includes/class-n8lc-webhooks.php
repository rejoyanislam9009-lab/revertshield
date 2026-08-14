<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Webhooks {
    private static $instance;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function hooks() {
        add_action( 'n8lc_conversation_created', array( $this, 'conversation_created' ), 30, 2 );
        add_action( 'n8lc_message_created', array( $this, 'message_created' ), 30, 3 );
        add_action( 'n8lc_conversation_updated', array( $this, 'conversation_updated' ), 30, 2 );
        add_action( 'n8lc_csat_submitted', array( $this, 'csat_submitted' ), 30, 2 );
    }

    public function conversation_created( $conversation_id, $visitor_id ) {
        $this->send( 'conversation.created', array( 'conversation_id' => absint( $conversation_id ), 'visitor_id' => absint( $visitor_id ) ) );
    }

    public function message_created( $message_id, $conversation_id, $sender_type ) {
        global $wpdb;
        $message = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id,conversation_id,sender_type,sender_id,body,message_type,is_private,attachment_url,attachment_name,attachment_mime,attachment_size,created_at FROM ' . N8LC_DB::table( 'messages' ) . ' WHERE id=%d',
                absint( $message_id )
            ),
            ARRAY_A
        );
        if ( ! $message || ! empty( $message['is_private'] ) ) {
            return;
        }
        $this->send(
            'message.created',
            array(
                'conversation_id' => absint( $conversation_id ),
                'sender_type'     => sanitize_key( $sender_type ),
                'message'         => $message,
            )
        );
    }

    public function conversation_updated( $conversation_id, $changes ) {
        $this->send( 'conversation.updated', array( 'conversation_id' => absint( $conversation_id ), 'changes' => $changes ) );
    }

    public function csat_submitted( $conversation_id, $rating ) {
        $this->send( 'conversation.csat', array( 'conversation_id' => absint( $conversation_id ), 'rating' => absint( $rating ) ) );
    }

    private function send( $event, array $data ) {
        $settings = get_option( 'n8lc_settings', array() );
        if ( empty( $settings['webhook_enabled'] ) || empty( $settings['webhook_url'] ) ) {
            return;
        }

        $url = esc_url_raw( $settings['webhook_url'] );
        if ( ! wp_http_validate_url( $url ) ) {
            return;
        }

        $payload = array(
            'event'      => sanitize_key( str_replace( '.', '_', $event ) ),
            'event_name' => sanitize_text_field( $event ),
            'site_url'   => home_url( '/' ),
            'sent_at'    => gmdate( 'c' ),
            'data'       => $data,
        );
        $json   = wp_json_encode( $payload );
        $secret = isset( $settings['webhook_secret'] ) ? (string) $settings['webhook_secret'] : '';
        $sig    = hash_hmac( 'sha256', $json, $secret );

        wp_safe_remote_post(
            $url,
            array(
                'timeout'  => 4,
                'blocking' => false,
                'headers'  => array(
                    'Content-Type'      => 'application/json',
                    'X-N8LC-Event'      => $event,
                    'X-N8LC-Signature'  => 'sha256=' . $sig,
                ),
                'body'     => $json,
            )
        );
    }
}
