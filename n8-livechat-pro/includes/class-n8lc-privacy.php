<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Privacy {
    private static $instance;

    public static function instance() {
        if ( ! self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public function hooks() {
        add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
        add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );
    }

    public function register_exporter( $exporters ) {
        $exporters['n8-livechat-pro'] = array(
            'exporter_friendly_name' => __( 'N8 LiveChat Pro', 'n8-livechat-pro' ),
            'callback'               => array( $this, 'export_personal_data' ),
        );
        return $exporters;
    }

    public function register_eraser( $erasers ) {
        $erasers['n8-livechat-pro'] = array(
            'eraser_friendly_name' => __( 'N8 LiveChat Pro', 'n8-livechat-pro' ),
            'callback'             => array( $this, 'erase_personal_data' ),
        );
        return $erasers;
    }

    public function privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) return;
        $content  = '<p>' . esc_html__( 'N8 LiveChat Pro may store information visitors submit through the support widget, such as name, email address, phone number, conversation messages, attachments, rating comments, browser metadata, page URLs and a privacy-preserving hash of the network address.', 'n8-livechat-pro' ) . '</p>';
        $content .= '<p>' . esc_html__( 'The site administrator controls retention, external integrations and optional webhooks. When integrations are enabled, selected support events may be sent to administrator-configured HTTPS endpoints. The plugin provides WordPress personal-data export and erasure callbacks.', 'n8-livechat-pro' ) . '</p>';
        wp_add_privacy_policy_content( __( 'N8 LiveChat Pro', 'n8-livechat-pro' ), wp_kses_post( $content ) );
    }

    public function export_personal_data( $email_address, $page = 1 ) {
        global $wpdb;
        $email = sanitize_email( $email_address );
        $page  = max( 1, absint( $page ) );
        $limit = 25;
        $offset = ( $page - 1 ) * $limit;
        $visitors = N8LC_DB::table( 'visitors' );
        $conversations = N8LC_DB::table( 'conversations' );
        $messages = N8LC_DB::table( 'messages' );

        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$visitors} WHERE email=%s ORDER BY id ASC LIMIT %d OFFSET %d", $email, $limit, $offset ),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $data = array();
        foreach ( $rows as $visitor ) {
            $visitor_id = (int) $visitor['id'];
            $data[] = array(
                'group_id'    => 'n8-livechat-profile',
                'group_label' => __( 'LiveChat visitor profile', 'n8-livechat-pro' ),
                'item_id'     => 'visitor-' . $visitor_id,
                'data'        => array(
                    array( 'name' => __( 'Name', 'n8-livechat-pro' ), 'value' => $visitor['name'] ),
                    array( 'name' => __( 'Email', 'n8-livechat-pro' ), 'value' => $visitor['email'] ),
                    array( 'name' => __( 'Phone', 'n8-livechat-pro' ), 'value' => $visitor['phone'] ),
                    array( 'name' => __( 'Last page', 'n8-livechat-pro' ), 'value' => $visitor['last_url'] ),
                    array( 'name' => __( 'Referrer', 'n8-livechat-pro' ), 'value' => $visitor['referrer'] ),
                    array( 'name' => __( 'First seen', 'n8-livechat-pro' ), 'value' => $visitor['first_seen'] ),
                    array( 'name' => __( 'Last seen', 'n8-livechat-pro' ), 'value' => $visitor['last_seen'] ),
                ),
            );

            $chats = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$conversations} WHERE visitor_id=%d ORDER BY id ASC", $visitor_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            foreach ( $chats as $chat ) {
                $conversation_id = (int) $chat['id'];
                $chat_data = array(
                    array( 'name' => __( 'Status', 'n8-livechat-pro' ), 'value' => $chat['status'] ),
                    array( 'name' => __( 'Priority', 'n8-livechat-pro' ), 'value' => $chat['priority'] ),
                    array( 'name' => __( 'Subject', 'n8-livechat-pro' ), 'value' => $chat['subject'] ),
                    array( 'name' => __( 'Created', 'n8-livechat-pro' ), 'value' => $chat['created_at'] ),
                    array( 'name' => __( 'CSAT rating', 'n8-livechat-pro' ), 'value' => $chat['csat_rating'] ),
                    array( 'name' => __( 'CSAT comment', 'n8-livechat-pro' ), 'value' => $chat['csat_comment'] ),
                );
                $thread = $wpdb->get_results( $wpdb->prepare( "SELECT sender_type,body,attachment_name,created_at FROM {$messages} WHERE conversation_id=%d AND is_private=0 ORDER BY id ASC LIMIT 500", $conversation_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                foreach ( $thread as $message ) {
                    $chat_data[] = array(
                        'name'  => sprintf( __( 'Message (%1$s, %2$s)', 'n8-livechat-pro' ), $message['sender_type'], $message['created_at'] ),
                        'value' => trim( $message['body'] . ( $message['attachment_name'] ? ' [' . $message['attachment_name'] . ']' : '' ) ),
                    );
                }
                $data[] = array(
                    'group_id'    => 'n8-livechat-conversations',
                    'group_label' => __( 'LiveChat conversations', 'n8-livechat-pro' ),
                    'item_id'     => 'conversation-' . $conversation_id,
                    'data'        => $chat_data,
                );
            }
        }

        return array( 'data' => $data, 'done' => count( $rows ) < $limit );
    }

    public function erase_personal_data( $email_address, $page = 1 ) {
        global $wpdb;
        $email = sanitize_email( $email_address );
        $page  = max( 1, absint( $page ) ); // WordPress passes the page number; erasure intentionally processes the first remaining batch.
        $limit = 25;
        $visitors = N8LC_DB::table( 'visitors' );

        // Always process the first remaining batch. Anonymized rows stop matching the email,
        // so using an OFFSET here would skip records on subsequent privacy-erasure passes.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$visitors} WHERE email=%s ORDER BY id ASC LIMIT %d", $email, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $removed = false;
        foreach ( $rows as $row ) {
            if ( $this->anonymize_visitor( absint( $row['id'] ) ) ) {
                $removed = true;
            }
        }
        return array(
            'items_removed'  => $removed,
            'items_retained' => false,
            'messages'       => array(),
            'done'           => count( $rows ) < $limit,
        );
    }
    public function anonymize_visitor( $visitor_id ) {
        global $wpdb;
        $visitor_id = absint( $visitor_id );
        if ( ! $visitor_id ) {
            return false;
        }
        $visitors      = N8LC_DB::table( 'visitors' );
        $conversations = N8LC_DB::table( 'conversations' );
        $messages      = N8LC_DB::table( 'messages' );
        $conversation_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$conversations} WHERE visitor_id=%d", $visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ( $conversation_ids as $conversation_id ) {
            $wpdb->query( $wpdb->prepare( "UPDATE {$messages} SET body=%s,attachment_name='',attachment_url=NULL WHERE conversation_id=%d AND sender_type=%s", '[personal data removed]', absint( $conversation_id ), 'visitor' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->update( $conversations, array( 'custom_data' => null, 'csat_comment' => '' ), array( 'id' => absint( $conversation_id ) ) );
        }
        $result = $wpdb->update(
            $visitors,
            array(
                'name'       => 'Anonymous visitor',
                'email'      => '',
                'phone'      => '',
                'user_agent' => '',
                'last_url'   => '',
                'referrer'   => '',
                'metadata'   => null,
                'ip_hash'    => '',
            ),
            array( 'id' => $visitor_id )
        );
        return false !== $result;
    }

}
