<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_DB {
	const DB_VERSION = '1.0.0';

	public static function table( $name ) {
		global $wpdb;
		$allowed = array( 'visitors', 'conversations', 'messages', 'departments', 'canned_replies', 'events' );
		if ( ! in_array( $name, $allowed, true ) ) {
			return '';
		}
		return $wpdb->prefix . 'n8lc_' . $name;
	}

	public static function activate() {
		self::install_schema();
		self::install_defaults();
		self::install_capabilities();
		update_option( 'n8lc_db_version', self::DB_VERSION );
	}

	private static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$visitors = self::table( 'visitors' );
		$conversations = self::table( 'conversations' );
		$messages = self::table( 'messages' );
		$departments = self::table( 'departments' );
		$canned = self::table( 'canned_replies' );
		$events = self::table( 'events' );

		$sql = array();
		$sql[] = "CREATE TABLE {$visitors} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			token_hash char(64) NOT NULL,
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(80) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '',
			user_agent text NULL,
			last_url text NULL,
			referrer text NULL,
			metadata longtext NULL,
			first_seen datetime NOT NULL,
			last_seen datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			KEY token_hash (token_hash),
			KEY last_seen (last_seen)
		) {$charset};";

		$sql[] = "CREATE TABLE {$departments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			description text NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_active (is_active)
		) {$charset};";

		$sql[] = "CREATE TABLE {$conversations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			visitor_id bigint(20) unsigned NOT NULL,
			agent_id bigint(20) unsigned NULL,
			department_id bigint(20) unsigned NULL,
			status varchar(24) NOT NULL DEFAULT 'open',
			priority varchar(24) NOT NULL DEFAULT 'normal',
			subject varchar(255) NOT NULL DEFAULT '',
			unread_agent int(10) unsigned NOT NULL DEFAULT 0,
			unread_visitor int(10) unsigned NOT NULL DEFAULT 0,
			last_message_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			KEY visitor_id (visitor_id),
			KEY agent_id (agent_id),
			KEY department_id (department_id),
			KEY status (status),
			KEY last_message_at (last_message_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			sender_type varchar(20) NOT NULL,
			sender_id bigint(20) unsigned NULL,
			body longtext NOT NULL,
			message_type varchar(20) NOT NULL DEFAULT 'text',
			is_private tinyint(1) NOT NULL DEFAULT 0,
			seen_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at),
			KEY sender_type (sender_type)
		) {$charset};";

		$sql[] = "CREATE TABLE {$canned} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL,
			shortcut varchar(80) NOT NULL DEFAULT '',
			body longtext NOT NULL,
			author_id bigint(20) unsigned NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY shortcut (shortcut),
			KEY is_active (is_active)
		) {$charset};";

		$sql[] = "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NULL,
			visitor_id bigint(20) unsigned NULL,
			agent_id bigint(20) unsigned NULL,
			event_type varchar(80) NOT NULL,
			payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	private static function install_defaults() {
		$defaults = array(
			'enabled' => 1,
			'widget_title' => 'Chat with us',
			'welcome_message' => 'Hi! How can we help you today?',
			'offline_message' => 'We are currently away. Leave a message and we will get back to you.',
			'position' => 'right',
			'accent_color' => '#111827',
			'require_email' => 0,
			'privacy_mode' => 1,
			'poll_interval' => 3000,
			'business_hours_enabled' => 0,
			'business_hours' => array(),
		);
		$current = get_option( 'n8lc_settings', array() );
		update_option( 'n8lc_settings', wp_parse_args( is_array( $current ) ? $current : array(), $defaults ) );

		global $wpdb;
		$table = self::table( 'departments' );
		$exists = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === $exists ) {
			$now = current_time( 'mysql' );
			$wpdb->insert(
				$table,
				array(
					'name' => 'General Support',
					'slug' => 'general-support',
					'description' => 'Default support department.',
					'is_active' => 1,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}
	}

	private static function install_capabilities() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			foreach ( array( 'n8lc_manage_chat', 'n8lc_reply_chat', 'n8lc_manage_settings', 'n8lc_view_analytics' ) as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	public static function log_event( $event_type, array $data = array() ) {
		global $wpdb;
		$wpdb->insert(
			self::table( 'events' ),
			array(
				'conversation_id' => isset( $data['conversation_id'] ) ? absint( $data['conversation_id'] ) : null,
				'visitor_id' => isset( $data['visitor_id'] ) ? absint( $data['visitor_id'] ) : null,
				'agent_id' => isset( $data['agent_id'] ) ? absint( $data['agent_id'] ) : null,
				'event_type' => sanitize_key( $event_type ),
				'payload' => isset( $data['payload'] ) ? wp_json_encode( $data['payload'] ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}
}
