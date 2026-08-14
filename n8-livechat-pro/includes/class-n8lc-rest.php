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
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'create_session' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/messages', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'visitor_messages' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'visitor_send' ),
				'permission_callback' => '__return_true',
			),
		) );
		register_rest_route( self::NS, '/heartbeat', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'heartbeat' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/stats', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'admin_stats' ),
			'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
		) );
		register_rest_route( self::NS, '/admin/conversations', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'admin_conversations' ),
			'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
		) );
		register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)/messages', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'admin_conversation_messages' ),
			'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
		) );
		register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)/reply', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'admin_reply' ),
			'permission_callback' => array( 'N8LC_Security', 'reply_permission' ),
		) );
		register_rest_route( self::NS, '/admin/conversations/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::EDITABLE,
			'callback' => array( $this, 'admin_update_conversation' ),
			'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
		) );
		register_rest_route( self::NS, '/admin/visitors', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'admin_visitors' ),
			'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
		) );
		register_rest_route( self::NS, '/admin/canned-replies', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'canned_replies' ),
				'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'save_canned_reply' ),
				'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
			),
		) );
		register_rest_route( self::NS, '/admin/canned-replies/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::DELETABLE,
			'callback' => array( $this, 'delete_canned_reply' ),
			'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
		) );
		register_rest_route( self::NS, '/admin/departments', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'departments' ),
				'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'save_department' ),
				'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
			),
		) );
		register_rest_route( self::NS, '/admin/analytics', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'analytics' ),
			'permission_callback' => function() {
				return current_user_can( 'n8lc_view_analytics' ) || current_user_can( 'manage_options' );
			},
		) );
		register_rest_route( self::NS, '/admin/settings', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_settings' ),
				'permission_callback' => array( 'N8LC_Security', 'admin_permission' ),
			),
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'save_settings' ),
				'permission_callback' => function() {
					return current_user_can( 'n8lc_manage_settings' ) || current_user_can( 'manage_options' );
				},
			),
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

		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		$phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );
		if ( ! empty( $settings['require_email'] ) && ! is_email( $email ) ) {
			return new WP_Error( 'n8lc_email_required', __( 'A valid email address is required.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
		}

		$token = N8LC_Security::generate_token();
		$visitor_public_id = wp_generate_uuid4();
		$conversation_public_id = wp_generate_uuid4();
		$now = current_time( 'mysql' );
		$metadata = array(
			'timezone' => sanitize_text_field( (string) $request->get_param( 'timezone' ) ),
			'language' => sanitize_text_field( (string) $request->get_param( 'language' ) ),
			'screen' => sanitize_text_field( (string) $request->get_param( 'screen' ) ),
		);
		$user_agent = ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$ok = $wpdb->insert(
			N8LC_DB::table( 'visitors' ),
			array(
				'public_id' => $visitor_public_id,
				'token_hash' => N8LC_Security::hash_token( $token ),
				'name' => $name,
				'email' => $email,
				'phone' => $phone,
				'ip_hash' => N8LC_Security::client_ip_hash(),
				'user_agent' => $user_agent,
				'last_url' => esc_url_raw( (string) $request->get_param( 'url' ) ),
				'referrer' => esc_url_raw( (string) $request->get_param( 'referrer' ) ),
				'metadata' => wp_json_encode( $metadata ),
				'first_seen' => $now,
				'last_seen' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'n8lc_db_error', __( 'Unable to start chat.', 'n8-livechat-pro' ), array( 'status' => 500 ) );
		}
		$visitor_id = (int) $wpdb->insert_id;

		$department_id = absint( $request->get_param( 'department_id' ) );
		if ( ! $department_id ) {
			$department_id = (int) $wpdb->get_var( 'SELECT id FROM ' . N8LC_DB::table( 'departments' ) . ' WHERE is_active = 1 ORDER BY id ASC LIMIT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$wpdb->insert(
			N8LC_DB::table( 'conversations' ),
			array(
				'public_id' => $conversation_public_id,
				'visitor_id' => $visitor_id,
				'department_id' => $department_id ?: null,
				'status' => 'open',
				'priority' => 'normal',
				'subject' => sanitize_text_field( (string) $request->get_param( 'subject' ) ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$conversation_id = (int) $wpdb->insert_id;

		$welcome = isset( $settings['welcome_message'] ) ? N8LC_Security::sanitize_message( $settings['welcome_message'] ) : '';
		if ( $welcome ) {
			$wpdb->insert(
				N8LC_DB::table( 'messages' ),
				array(
					'conversation_id' => $conversation_id,
					'sender_type' => 'system',
					'body' => $welcome,
					'message_type' => 'text',
					'is_private' => 0,
					'created_at' => $now,
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s' )
			);
		}
		N8LC_DB::log_event( 'conversation_created', array( 'conversation_id' => $conversation_id, 'visitor_id' => $visitor_id ) );
		do_action( 'n8lc_conversation_created', $conversation_id, $visitor_id );

		return rest_ensure_response( array(
			'conversation_id' => $conversation_id,
			'conversation_public_id' => $conversation_public_id,
			'visitor_id' => $visitor_id,
			'visitor_public_id' => $visitor_public_id,
			'token' => $token,
			'poll_interval' => isset( $settings['poll_interval'] ) ? absint( $settings['poll_interval'] ) : 3000,
		) );
	}

	public function visitor_messages( WP_REST_Request $request ) {
		global $wpdb;
		$conversation_id = absint( $request->get_param( 'conversation_id' ) );
		$token = N8LC_Security::request_token( $request );
		if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
			return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
		}
		$after_id = absint( $request->get_param( 'after_id' ) );
		$table = N8LC_DB::table( 'messages' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, sender_type, body, message_type, seen_at, created_at FROM {$table} WHERE conversation_id = %d AND is_private = 0 AND id > %d ORDER BY id ASC LIMIT 200",
			$conversation_id,
			$after_id
		), ARRAY_A );
		$wpdb->update( N8LC_DB::table( 'conversations' ), array( 'unread_visitor' => 0 ), array( 'id' => $conversation_id ), array( '%d' ), array( '%d' ) );
		return rest_ensure_response( array( 'messages' => $rows ) );
	}

	public function visitor_send( WP_REST_Request $request ) {
		global $wpdb;
		if ( ! N8LC_Security::rate_limit( 'message', 45, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'n8lc_rate_limited', __( 'You are sending messages too quickly.', 'n8-livechat-pro' ), array( 'status' => 429 ) );
		}
		$conversation_id = absint( $request->get_param( 'conversation_id' ) );
		$token = N8LC_Security::request_token( $request );
		if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
			return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
		}
		$body = N8LC_Security::sanitize_message( $request->get_param( 'message' ) );
		if ( '' === $body ) {
			return new WP_Error( 'n8lc_empty_message', __( 'Message cannot be empty.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
		}
		$now = current_time( 'mysql' );
		$wpdb->insert( N8LC_DB::table( 'messages' ), array(
			'conversation_id' => $conversation_id,
			'sender_type' => 'visitor',
			'body' => $body,
			'message_type' => 'text',
			'is_private' => 0,
			'created_at' => $now,
		), array( '%d', '%s', '%s', '%s', '%d', '%s' ) );
		$message_id = (int) $wpdb->insert_id;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . N8LC_DB::table( 'conversations' ) . ' SET unread_agent = unread_agent + 1, status = IF(status = %s, %s, status), last_message_at = %s, updated_at = %s WHERE id = %d',
			'closed', 'open', $now, $now, $conversation_id
		) );
		$visitor_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT visitor_id FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id = %d', $conversation_id ) );
		$wpdb->update( N8LC_DB::table( 'visitors' ), array( 'last_seen' => $now, 'last_url' => esc_url_raw( (string) $request->get_param( 'url' ) ) ), array( 'id' => $visitor_id ), array( '%s', '%s' ), array( '%d' ) );
		N8LC_DB::log_event( 'visitor_message', array( 'conversation_id' => $conversation_id, 'visitor_id' => $visitor_id ) );
		do_action( 'n8lc_message_created', $message_id, $conversation_id, 'visitor' );
		return rest_ensure_response( array( 'message_id' => $message_id, 'created_at' => $now ) );
	}

	public function heartbeat( WP_REST_Request $request ) {
		global $wpdb;
		$conversation_id = absint( $request->get_param( 'conversation_id' ) );
		$token = N8LC_Security::request_token( $request );
		if ( ! N8LC_Security::verify_visitor_access( $conversation_id, $token ) ) {
			return new WP_Error( 'n8lc_forbidden', __( 'Invalid chat session.', 'n8-livechat-pro' ), array( 'status' => 403 ) );
		}
		$visitor_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT visitor_id FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id = %d', $conversation_id ) );
		$wpdb->update( N8LC_DB::table( 'visitors' ), array( 'last_seen' => current_time( 'mysql' ), 'last_url' => esc_url_raw( (string) $request->get_param( 'url' ) ) ), array( 'id' => $visitor_id ), array( '%s', '%s' ), array( '%d' ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function admin_stats() {
		global $wpdb;
		$c = N8LC_DB::table( 'conversations' );
		$v = N8LC_DB::table( 'visitors' );
		$m = N8LC_DB::table( 'messages' );
		$today = current_time( 'Y-m-d 00:00:00' );
		$online_cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - 300 );
		return rest_ensure_response( array(
			'open' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} WHERE status = 'open'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'pending' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$c} WHERE status = 'pending'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'unread' => (int) $wpdb->get_var( "SELECT COALESCE(SUM(unread_agent),0) FROM {$c}" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'online_visitors' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$v} WHERE last_seen >= %s", $online_cutoff ) ),
			'conversations_today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$c} WHERE created_at >= %s", $today ) ),
			'messages_today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$m} WHERE created_at >= %s", $today ) ),
		) );
	}

	public function admin_conversations( WP_REST_Request $request ) {
		global $wpdb;
		$c = N8LC_DB::table( 'conversations' );
		$v = N8LC_DB::table( 'visitors' );
		$d = N8LC_DB::table( 'departments' );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$page = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = min( 100, max( 10, absint( $request->get_param( 'per_page' ) ) ?: 30 ) );
		$where = '1=1';
		$args = array();
		if ( in_array( $status, array( 'open', 'pending', 'closed' ), true ) ) {
			$where .= ' AND c.status = %s';
			$args[] = $status;
		}
		if ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (v.name LIKE %s OR v.email LIKE %s OR c.subject LIKE %s)';
			array_push( $args, $like, $like, $like );
		}
		$offset = ( $page - 1 ) * $per_page;
		$args[] = $per_page;
		$args[] = $offset;
		$sql = "SELECT c.*, v.name visitor_name, v.email visitor_email, v.last_seen visitor_last_seen, d.name department_name, u.display_name agent_name
			FROM {$c} c INNER JOIN {$v} v ON v.id = c.visitor_id
			LEFT JOIN {$d} d ON d.id = c.department_id
			LEFT JOIN {$wpdb->users} u ON u.ID = c.agent_id
			WHERE {$where}
			ORDER BY COALESCE(c.last_message_at,c.created_at) DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return rest_ensure_response( array( 'conversations' => $rows, 'page' => $page ) );
	}

	public function admin_conversation_messages( WP_REST_Request $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		$table = N8LC_DB::table( 'messages' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id ASC LIMIT 500", $id ), ARRAY_A );
		$wpdb->update( N8LC_DB::table( 'conversations' ), array( 'unread_agent' => 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
		return rest_ensure_response( array( 'messages' => $rows ) );
	}

	public function admin_reply( WP_REST_Request $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		$body = N8LC_Security::sanitize_message( $request->get_param( 'message' ) );
		$is_private = rest_sanitize_boolean( $request->get_param( 'is_private' ) );
		if ( '' === $body ) {
			return new WP_Error( 'n8lc_empty_message', __( 'Message cannot be empty.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
		}
		$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . N8LC_DB::table( 'conversations' ) . ' WHERE id = %d', $id ) );
		if ( ! $exists ) {
			return new WP_Error( 'n8lc_not_found', __( 'Conversation not found.', 'n8-livechat-pro' ), array( 'status' => 404 ) );
		}
		$now = current_time( 'mysql' );
		$agent_id = get_current_user_id();
		$wpdb->insert( N8LC_DB::table( 'messages' ), array(
			'conversation_id' => $id,
			'sender_type' => $is_private ? 'note' : 'agent',
			'sender_id' => $agent_id,
			'body' => $body,
			'message_type' => $is_private ? 'note' : 'text',
			'is_private' => $is_private ? 1 : 0,
			'created_at' => $now,
		), array( '%d', '%s', '%d', '%s', '%s', '%d', '%s' ) );
		$message_id = (int) $wpdb->insert_id;
		if ( $is_private ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . N8LC_DB::table( 'conversations' ) . ' SET agent_id = COALESCE(agent_id,%d), updated_at = %s WHERE id = %d', $agent_id, $now, $id ) );
		} else {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . N8LC_DB::table( 'conversations' ) . ' SET agent_id = COALESCE(agent_id,%d), unread_visitor = unread_visitor + 1, last_message_at = %s, updated_at = %s WHERE id = %d', $agent_id, $now, $now, $id ) );
		}
		N8LC_DB::log_event( $is_private ? 'private_note' : 'agent_message', array( 'conversation_id' => $id, 'agent_id' => $agent_id ) );
		do_action( 'n8lc_message_created', $message_id, $id, $is_private ? 'note' : 'agent' );
		return rest_ensure_response( array( 'message_id' => $message_id, 'created_at' => $now ) );
	}

	public function admin_update_conversation( WP_REST_Request $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		$data = array();
		$formats = array();
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$priority = sanitize_key( (string) $request->get_param( 'priority' ) );
		if ( in_array( $status, array( 'open', 'pending', 'closed' ), true ) ) {
			$data['status'] = $status; $formats[] = '%s';
		}
		if ( in_array( $priority, array( 'low', 'normal', 'high', 'urgent' ), true ) ) {
			$data['priority'] = $priority; $formats[] = '%s';
		}
		if ( null !== $request->get_param( 'agent_id' ) ) {
			$data['agent_id'] = absint( $request->get_param( 'agent_id' ) ) ?: null; $formats[] = '%d';
		}
		if ( null !== $request->get_param( 'department_id' ) ) {
			$data['department_id'] = absint( $request->get_param( 'department_id' ) ) ?: null; $formats[] = '%d';
		}
		if ( null !== $request->get_param( 'subject' ) ) {
			$data['subject'] = sanitize_text_field( (string) $request->get_param( 'subject' ) ); $formats[] = '%s';
		}
		if ( empty( $data ) ) {
			return new WP_Error( 'n8lc_no_changes', __( 'No valid changes supplied.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
		}
		$data['updated_at'] = current_time( 'mysql' ); $formats[] = '%s';
		$wpdb->update( N8LC_DB::table( 'conversations' ), $data, array( 'id' => $id ), $formats, array( '%d' ) );
		N8LC_DB::log_event( 'conversation_updated', array( 'conversation_id' => $id, 'agent_id' => get_current_user_id(), 'payload' => $data ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function admin_visitors( WP_REST_Request $request ) {
		global $wpdb;
		$v = N8LC_DB::table( 'visitors' );
		$c = N8LC_DB::table( 'conversations' );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$where = '1=1'; $args = array();
		if ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (v.name LIKE %s OR v.email LIKE %s OR v.phone LIKE %s)';
			$args = array( $like, $like, $like );
		}
		$sql = "SELECT v.id, v.public_id, v.name, v.email, v.phone, v.last_url, v.referrer, v.first_seen, v.last_seen, COUNT(c.id) conversations
			FROM {$v} v LEFT JOIN {$c} c ON c.visitor_id = v.id WHERE {$where} GROUP BY v.id ORDER BY v.last_seen DESC LIMIT 200";
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return rest_ensure_response( array( 'visitors' => $rows ) );
	}

	public function canned_replies() {
		global $wpdb;
		$table = N8LC_DB::table( 'canned_replies' );
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY title ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return rest_ensure_response( array( 'canned_replies' => $rows ) );
	}

	public function save_canned_reply( WP_REST_Request $request ) {
		global $wpdb;
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$body = N8LC_Security::sanitize_message( $request->get_param( 'body' ) );
		if ( ! $title || ! $body ) {
			return new WP_Error( 'n8lc_invalid_reply', __( 'Title and message are required.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
		}
		$now = current_time( 'mysql' );
		$wpdb->insert( N8LC_DB::table( 'canned_replies' ), array(
			'title' => $title,
			'shortcut' => sanitize_key( (string) $request->get_param( 'shortcut' ) ),
			'body' => $body,
			'author_id' => get_current_user_id(),
			'is_active' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		), array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' ) );
		return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id ) );
	}

	public function delete_canned_reply( WP_REST_Request $request ) {
		global $wpdb;
		$wpdb->delete( N8LC_DB::table( 'canned_replies' ), array( 'id' => absint( $request['id'] ) ), array( '%d' ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function departments() {
		global $wpdb;
		$table = N8LC_DB::table( 'departments' );
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY is_active DESC, name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return rest_ensure_response( array( 'departments' => $rows ) );
	}

	public function save_department( WP_REST_Request $request ) {
		global $wpdb;
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( ! $name ) {
			return new WP_Error( 'n8lc_invalid_department', __( 'Department name is required.', 'n8-livechat-pro' ), array( 'status' => 400 ) );
		}
		$now = current_time( 'mysql' );
		$slug = sanitize_title( (string) $request->get_param( 'slug' ) ?: $name );
		$wpdb->insert( N8LC_DB::table( 'departments' ), array(
			'name' => $name,
			'slug' => $slug,
			'description' => sanitize_textarea_field( (string) $request->get_param( 'description' ) ),
			'is_active' => 1,
			'created_at' => $now,
			'updated_at' => $now,
		), array( '%s', '%s', '%s', '%d', '%s', '%s' ) );
		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'n8lc_department_exists', __( 'A department with that slug may already exist.', 'n8-livechat-pro' ), array( 'status' => 409 ) );
		}
		return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id ) );
	}

	public function analytics( WP_REST_Request $request ) {
		global $wpdb;
		$days = min( 90, max( 7, absint( $request->get_param( 'days' ) ) ?: 30 ) );
		$start = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp', true ) - ( DAY_IN_SECONDS * ( $days - 1 ) ) );
		$c = N8LC_DB::table( 'conversations' );
		$m = N8LC_DB::table( 'messages' );
		$daily_conversations = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) day, COUNT(*) total FROM {$c} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC", $start ), ARRAY_A );
		$daily_messages = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) day, COUNT(*) total FROM {$m} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC", $start ), ARRAY_A );
		$by_status = $wpdb->get_results( "SELECT status, COUNT(*) total FROM {$c} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_department = $wpdb->get_results( 'SELECT COALESCE(d.name,\'Unassigned\') department, COUNT(c.id) total FROM ' . $c . ' c LEFT JOIN ' . N8LC_DB::table( 'departments' ) . ' d ON d.id=c.department_id GROUP BY c.department_id ORDER BY total DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return rest_ensure_response( array(
			'days' => $days,
			'daily_conversations' => $daily_conversations,
			'daily_messages' => $daily_messages,
			'by_status' => $by_status,
			'by_department' => $by_department,
		) );
	}

	public function get_settings() {
		return rest_ensure_response( get_option( 'n8lc_settings', array() ) );
	}

	public function save_settings( WP_REST_Request $request ) {
		$current = get_option( 'n8lc_settings', array() );
		$input = $request->get_json_params();
		$next = is_array( $current ) ? $current : array();
		$next['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
		$next['widget_title'] = sanitize_text_field( isset( $input['widget_title'] ) ? $input['widget_title'] : '' );
		$next['welcome_message'] = N8LC_Security::sanitize_message( isset( $input['welcome_message'] ) ? $input['welcome_message'] : '' );
		$next['offline_message'] = N8LC_Security::sanitize_message( isset( $input['offline_message'] ) ? $input['offline_message'] : '' );
		$next['position'] = isset( $input['position'] ) && 'left' === $input['position'] ? 'left' : 'right';
		$color = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
		$next['accent_color'] = $color ?: '#111827';
		$next['require_email'] = ! empty( $input['require_email'] ) ? 1 : 0;
		$next['privacy_mode'] = ! empty( $input['privacy_mode'] ) ? 1 : 0;
		$next['poll_interval'] = min( 15000, max( 1500, absint( isset( $input['poll_interval'] ) ? $input['poll_interval'] : 3000 ) ) );
		$next['retention_days'] = min( 3650, max( 7, absint( isset( $input['retention_days'] ) ? $input['retention_days'] : 365 ) ) );
		update_option( 'n8lc_settings', $next );
		N8LC_DB::log_event( 'settings_updated', array( 'agent_id' => get_current_user_id() ) );
		return rest_ensure_response( $next );
	}
}
