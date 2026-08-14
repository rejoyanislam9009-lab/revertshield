<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Automation {
    private static $instance;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function hooks() {
        add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
        add_action( 'n8lc_automation_tick', array( $this, 'process_sla' ) );
        add_action( 'n8lc_automation_tick', array( $this, 'process_idle_conversations' ), 20 );
        add_action( 'n8lc_conversation_created', array( $this, 'conversation_created' ), 10, 2 );
        add_action( 'n8lc_message_created', array( $this, 'message_created' ), 10, 3 );
    }

    public function cron_schedules( $schedules ) {
        if ( ! isset( $schedules['n8lc_five_minutes'] ) ) {
            $schedules['n8lc_five_minutes'] = array(
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __( 'Every five minutes (N8 LiveChat)', 'n8-livechat-pro' ),
            );
        }
        return $schedules;
    }

    public function conversation_created( $conversation_id, $visitor_id ) {
        $settings = get_option( 'n8lc_settings', array() );
        $this->apply_sla_targets( $conversation_id, $settings );

        if ( N8LC_Presence::is_online( $settings ) ) {
            $this->auto_assign( $conversation_id, $settings );
        }

        if ( ! N8LC_Presence::is_online( $settings ) ) {
            global $wpdb;
            $wpdb->update(
                N8LC_DB::table( 'conversations' ),
                array( 'status' => 'pending', 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => absint( $conversation_id ) ),
                array( '%s', '%s' ),
                array( '%d' )
            );
            $offline = isset( $settings['offline_message'] ) ? N8LC_Security::sanitize_message( $settings['offline_message'] ) : '';
            if ( $offline ) {
                $wpdb->insert(
                    N8LC_DB::table( 'messages' ),
                    array(
                        'conversation_id' => absint( $conversation_id ),
                        'sender_type'     => 'system',
                        'body'            => $offline,
                        'message_type'    => 'text',
                        'is_private'      => 0,
                        'created_at'      => current_time( 'mysql' ),
                    ),
                    array( '%d', '%s', '%s', '%s', '%d', '%s' )
                );
            }
        }
    }

    public function message_created( $message_id, $conversation_id, $sender_type ) {
        global $wpdb;
        $conversation_id = absint( $conversation_id );
        $sender_type     = sanitize_key( $sender_type );

        if ( 'agent' === $sender_type ) {
            $conversation = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT first_response_at FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id = %d',
                    $conversation_id
                ),
                ARRAY_A
            );
            if ( $conversation && empty( $conversation['first_response_at'] ) ) {
                $wpdb->update(
                    N8LC_DB::table( 'conversations' ),
                    array( 'first_response_at' => current_time( 'mysql' ), 'sla_breached' => 0 ),
                    array( 'id' => $conversation_id ),
                    array( '%s', '%d' ),
                    array( '%d' )
                );
                N8LC_DB::log_event( 'first_response', array( 'conversation_id' => $conversation_id, 'agent_id' => get_current_user_id() ) );
            }
        }

        if ( 'visitor' === $sender_type ) {
            $this->send_new_message_notification( $conversation_id, absint( $message_id ) );
        }
    }

    private function apply_sla_targets( $conversation_id, $settings ) {
        if ( empty( $settings['sla_enabled'] ) ) {
            return;
        }
        global $wpdb;
        $now        = time();
        $first_mins = max( 1, min( 10080, absint( isset( $settings['first_response_minutes'] ) ? $settings['first_response_minutes'] : 15 ) ) );
        $res_mins   = max( $first_mins, min( 43200, absint( isset( $settings['resolution_minutes'] ) ? $settings['resolution_minutes'] : 480 ) ) );
        $wpdb->update(
            N8LC_DB::table( 'conversations' ),
            array(
                'first_response_due_at' => wp_date( 'Y-m-d H:i:s', $now + ( $first_mins * MINUTE_IN_SECONDS ), wp_timezone() ),
                'resolution_due_at'     => wp_date( 'Y-m-d H:i:s', $now + ( $res_mins * MINUTE_IN_SECONDS ), wp_timezone() ),
            ),
            array( 'id' => absint( $conversation_id ) ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    private function candidate_agents() {
        $users = get_users(
            array(
                'capability' => 'n8lc_reply_chat',
                'fields'     => array( 'ID', 'display_name', 'user_email' ),
            )
        );
        if ( empty( $users ) ) {
            $users = get_users(
                array(
                    'role'   => 'administrator',
                    'fields' => array( 'ID', 'display_name', 'user_email' ),
                )
            );
        }
        return $users;
    }

    public function auto_assign( $conversation_id, $settings = null ) {
        $settings = is_array( $settings ) ? $settings : get_option( 'n8lc_settings', array() );
        if ( empty( $settings['auto_assign_enabled'] ) ) {
            return 0;
        }

        $agents = $this->candidate_agents();
        if ( empty( $agents ) ) {
            return 0;
        }

        global $wpdb;
        $mode     = isset( $settings['auto_assign_mode'] ) ? sanitize_key( $settings['auto_assign_mode'] ) : 'load';
        $agent_id = 0;

        if ( 'round_robin' === $mode ) {
            $ids  = array_map( static function ( $user ) { return (int) $user->ID; }, $agents );
            sort( $ids, SORT_NUMERIC );
            $last = absint( get_option( 'n8lc_rr_last_agent', 0 ) );
            $pick = $ids[0];
            foreach ( $ids as $id ) {
                if ( $id > $last ) {
                    $pick = $id;
                    break;
                }
            }
            $agent_id = $pick;
            update_option( 'n8lc_rr_last_agent', $agent_id, false );
        } else {
            $best_count = null;
            foreach ( $agents as $agent ) {
                $count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM " . N8LC_DB::table( 'conversations' ) . " WHERE agent_id = %d AND status IN ('open','pending')",
                        $agent->ID
                    )
                );
                if ( null === $best_count || $count < $best_count ) {
                    $best_count = $count;
                    $agent_id   = (int) $agent->ID;
                }
            }
        }

        if ( $agent_id ) {
            $wpdb->update(
                N8LC_DB::table( 'conversations' ),
                array( 'agent_id' => $agent_id, 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => absint( $conversation_id ) ),
                array( '%d', '%s' ),
                array( '%d' )
            );
            N8LC_DB::log_event( 'auto_assigned', array( 'conversation_id' => $conversation_id, 'agent_id' => $agent_id, 'payload' => array( 'mode' => $mode ) ) );
        }

        return $agent_id;
    }

    public function process_sla() {
        $settings = get_option( 'n8lc_settings', array() );
        if ( empty( $settings['sla_enabled'] ) ) {
            return;
        }

        global $wpdb;
        $table = N8LC_DB::table( 'conversations' );
        $now   = current_time( 'mysql' );
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id,agent_id,first_response_at,first_response_due_at,resolution_due_at,status FROM {$table}
                 WHERE status IN ('open','pending') AND sla_breached = 0 AND
                 ((first_response_at IS NULL AND first_response_due_at IS NOT NULL AND first_response_due_at < %s)
                 OR (resolution_due_at IS NOT NULL AND resolution_due_at < %s))
                 LIMIT 200",
                $now,
                $now
            ),
            ARRAY_A
        );

        foreach ( $rows as $row ) {
            $wpdb->update(
                $table,
                array( 'sla_breached' => 1, 'priority' => 'urgent', 'updated_at' => $now ),
                array( 'id' => absint( $row['id'] ) ),
                array( '%d', '%s', '%s' ),
                array( '%d' )
            );
            N8LC_DB::log_event( 'sla_breached', array( 'conversation_id' => $row['id'], 'agent_id' => $row['agent_id'] ) );
            if ( ! empty( $settings['escalation_email'] ) ) {
                $this->send_escalation_email( absint( $row['id'] ) );
            }
        }
    }

    public function process_idle_conversations() {
        if ( ! class_exists( 'N8LC_Platform' ) ) {
            return;
        }
        $platform = N8LC_Platform::settings();
        if ( empty( $platform['chat_auto_close_idle'] ) ) {
            return;
        }

        $minutes = max( 5, min( 1440, absint( isset( $platform['chat_idle_timeout_minutes'] ) ? $platform['chat_idle_timeout_minutes'] : 15 ) ) );
        $cutoff  = wp_date( 'Y-m-d H:i:s', time() - ( $minutes * MINUTE_IN_SECONDS ), wp_timezone() );
        $now     = current_time( 'mysql' );
        global $wpdb;
        $c = N8LC_DB::table( 'conversations' );
        $v = N8LC_DB::table( 'visitors' );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.id,c.visitor_id FROM {$c} c INNER JOIN {$v} v ON v.id=c.visitor_id WHERE c.status IN ('open','pending') AND v.last_seen < %s ORDER BY v.last_seen ASC LIMIT 200",
                $cutoff
            ),
            ARRAY_A
        );

        foreach ( $rows as $row ) {
            $conversation_id = absint( $row['id'] );
            $wpdb->update(
                $c,
                array( 'status' => 'closed', 'closed_at' => $now, 'closed_reason' => 'idle', 'updated_at' => $now ),
                array( 'id' => $conversation_id ),
                array( '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            delete_transient( 'n8lc_vtyping_' . $conversation_id );
            delete_transient( 'n8lc_atyping_' . $conversation_id );
            N8LC_DB::log_event( 'conversation_auto_closed', array(
                'conversation_id' => $conversation_id,
                'visitor_id'      => absint( $row['visitor_id'] ),
                'payload'         => array( 'idle_minutes' => $minutes ),
            ) );
            do_action( 'n8lc_conversation_updated', $conversation_id, array( 'status' => 'closed', 'reason' => 'visitor_inactive' ) );
        }
    }

    private function send_new_message_notification( $conversation_id, $message_id ) {
        $settings = get_option( 'n8lc_settings', array() );
        if ( empty( $settings['email_notifications'] ) ) {
            return;
        }
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT c.agent_id,v.name,v.email,m.body FROM ' . N8LC_DB::table( 'conversations' ) . ' c INNER JOIN ' . N8LC_DB::table( 'visitors' ) . ' v ON v.id=c.visitor_id INNER JOIN ' . N8LC_DB::table( 'messages' ) . ' m ON m.id=%d WHERE c.id=%d',
                $message_id,
                $conversation_id
            ),
            ARRAY_A
        );
        if ( ! $row ) {
            return;
        }
        $recipient = get_option( 'admin_email' );
        if ( ! empty( $row['agent_id'] ) ) {
            $user = get_userdata( absint( $row['agent_id'] ) );
            if ( $user && is_email( $user->user_email ) ) {
                $recipient = $user->user_email;
            }
        }
        if ( ! is_email( $recipient ) ) {
            return;
        }
        $name    = $row['name'] ? $row['name'] : __( 'Visitor', 'n8-livechat-pro' );
        $subject = sprintf( __( 'New live chat message from %s', 'n8-livechat-pro' ), $name );
        $body    = $row['body'] . "\n\n" . admin_url( 'admin.php?page=n8-livechat-inbox' );
        wp_mail( $recipient, $subject, $body );
    }

    private function send_escalation_email( $conversation_id ) {
        $recipient = get_option( 'admin_email' );
        if ( ! is_email( $recipient ) ) {
            return;
        }
        $subject = sprintf( __( 'LiveChat SLA breached: conversation #%d', 'n8-livechat-pro' ), $conversation_id );
        $body    = sprintf( __( 'Conversation #%d has breached its SLA and was marked urgent.', 'n8-livechat-pro' ), $conversation_id );
        $body   .= "\n\n" . admin_url( 'admin.php?page=n8-livechat-inbox' );
        wp_mail( $recipient, $subject, $body );
    }

    public function email_transcript( $conversation_id ) {
        global $wpdb;
        $conversation_id = absint( $conversation_id );
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT c.*,v.name,v.email FROM ' . N8LC_DB::table( 'conversations' ) . ' c INNER JOIN ' . N8LC_DB::table( 'visitors' ) . ' v ON v.id=c.visitor_id WHERE c.id=%d',
                $conversation_id
            ),
            ARRAY_A
        );
        if ( ! $conversation || ! is_email( $conversation['email'] ) ) {
            return new WP_Error( 'n8lc_no_email', __( 'This visitor does not have a valid email address.', 'n8-livechat-pro' ) );
        }
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT sender_type,body,message_type,attachment_url,attachment_name,created_at FROM ' . N8LC_DB::table( 'messages' ) . ' WHERE conversation_id=%d AND is_private=0 ORDER BY id ASC',
                $conversation_id
            ),
            ARRAY_A
        );
        $lines = array( sprintf( __( 'Chat transcript #%d', 'n8-livechat-pro' ), $conversation_id ), '' );
        foreach ( $messages as $message ) {
            $label = ucfirst( sanitize_key( $message['sender_type'] ) );
            $text  = $message['body'];
            if ( ! empty( $message['attachment_url'] ) ) {
                $text .= ' ' . $message['attachment_url'];
            }
            $lines[] = '[' . $message['created_at'] . '] ' . $label . ': ' . $text;
        }
        $sent = wp_mail(
            $conversation['email'],
            sprintf( __( 'Your chat transcript #%d', 'n8-livechat-pro' ), $conversation_id ),
            implode( "\n", $lines )
        );
        if ( ! $sent ) {
            return new WP_Error( 'n8lc_mail_failed', __( 'WordPress could not send the transcript email.', 'n8-livechat-pro' ) );
        }
        N8LC_DB::log_event( 'transcript_sent', array( 'conversation_id' => $conversation_id, 'agent_id' => get_current_user_id() ) );
        return true;
    }
}
