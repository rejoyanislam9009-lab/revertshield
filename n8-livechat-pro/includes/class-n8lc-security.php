<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Security {
	public static function generate_token() {
		return wp_generate_password( 48, false, false );
	}

	public static function hash_token( $token ) {
		return hash( 'sha256', (string) $token );
	}

	public static function request_token( WP_REST_Request $request ) {
		$header = $request->get_header( 'X-N8LC-Token' );
		if ( $header ) {
			return sanitize_text_field( $header );
		}
		$token = $request->get_param( 'token' );
		return $token ? sanitize_text_field( $token ) : '';
	}

	public static function verify_visitor_access( $conversation_id, $token ) {
		global $wpdb;
		if ( ! $conversation_id || ! $token ) {
			return false;
		}
		$conversations = N8LC_DB::table( 'conversations' );
		$visitors = N8LC_DB::table( 'visitors' );
		$hash = self::hash_token( $token );
		$sql = $wpdb->prepare(
			"SELECT c.id FROM {$conversations} c INNER JOIN {$visitors} v ON v.id = c.visitor_id WHERE c.id = %d AND v.token_hash = %s LIMIT 1",
			absint( $conversation_id ),
			$hash
		);
		return (bool) $wpdb->get_var( $sql );
	}

	public static function client_ip_hash() {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		if ( '' === $ip ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	public static function rate_limit( $bucket, $limit = 30, $window = 60 ) {
		$bucket = sanitize_key( $bucket );
		$key = 'n8lc_rl_' . md5( $bucket . '|' . self::client_ip_hash() );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			set_transient( $key, array( 'count' => 1 ), max( 1, absint( $window ) ) );
			return true;
		}
		$count = isset( $data['count'] ) ? absint( $data['count'] ) : 0;
		if ( $count >= absint( $limit ) ) {
			return false;
		}
		$data['count'] = $count + 1;
		set_transient( $key, $data, max( 1, absint( $window ) ) );
		return true;
	}

	public static function sanitize_message( $message ) {
		$message = is_string( $message ) ? wp_unslash( $message ) : '';
		$message = trim( wp_strip_all_tags( $message ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $message, 0, 5000 );
		}
		return substr( $message, 0, 5000 );
	}

	public static function admin_permission() {
		return current_user_can( 'n8lc_manage_chat' ) || current_user_can( 'manage_options' );
	}

	public static function reply_permission() {
		return current_user_can( 'n8lc_reply_chat' ) || current_user_can( 'manage_options' );
	}
}
