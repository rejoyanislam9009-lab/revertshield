<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Admin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 90 );
	}

	public function menu() {
		$cap = 'n8lc_manage_chat';
		add_menu_page(
			__( 'N8 LiveChat', 'n8-livechat-pro' ),
			__( 'N8 LiveChat', 'n8-livechat-pro' ),
			$cap,
			'n8-livechat',
			array( $this, 'render' ),
			'dashicons-format-chat',
			26
		);
		add_submenu_page( 'n8-livechat', __( 'Dashboard', 'n8-livechat-pro' ), __( 'Dashboard', 'n8-livechat-pro' ), $cap, 'n8-livechat', array( $this, 'render' ) );
		add_submenu_page( 'n8-livechat', __( 'Inbox', 'n8-livechat-pro' ), __( 'Inbox', 'n8-livechat-pro' ), $cap, 'n8-livechat-inbox', array( $this, 'render' ) );
		add_submenu_page( 'n8-livechat', __( 'Visitors', 'n8-livechat-pro' ), __( 'Visitors', 'n8-livechat-pro' ), $cap, 'n8-livechat-visitors', array( $this, 'render' ) );
		add_submenu_page( 'n8-livechat', __( 'Canned Replies', 'n8-livechat-pro' ), __( 'Canned Replies', 'n8-livechat-pro' ), $cap, 'n8-livechat-canned', array( $this, 'render' ) );
		add_submenu_page( 'n8-livechat', __( 'Departments', 'n8-livechat-pro' ), __( 'Departments', 'n8-livechat-pro' ), $cap, 'n8-livechat-departments', array( $this, 'render' ) );
		add_submenu_page( 'n8-livechat', __( 'Analytics', 'n8-livechat-pro' ), __( 'Analytics', 'n8-livechat-pro' ), 'n8lc_view_analytics', 'n8-livechat-analytics', array( $this, 'render' ) );
		add_submenu_page( 'n8-livechat', __( 'Settings', 'n8-livechat-pro' ), __( 'Settings', 'n8-livechat-pro' ), 'n8lc_manage_settings', 'n8-livechat-settings', array( $this, 'render' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'n8-livechat' ) ) {
			return;
		}
		wp_enqueue_style( 'n8lc-admin', N8LC_URL . 'assets/css/admin.css', array(), N8LC_VERSION );
		wp_enqueue_script( 'n8lc-admin', N8LC_URL . 'assets/js/admin.js', array(), N8LC_VERSION, true );
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'n8-livechat';
		$agents = array();
		foreach ( get_users( array( 'fields' => array( 'ID', 'display_name', 'user_email' ) ) ) as $user ) {
			if ( user_can( $user, 'n8lc_reply_chat' ) || user_can( $user, 'manage_options' ) ) {
				$agents[] = array(
					'id' => (int) $user->ID,
					'name' => $user->display_name,
					'email' => $user->user_email,
				);
			}
		}
		wp_localize_script( 'n8lc-admin', 'N8LCAdmin', array(
			'restRoot' => esc_url_raw( rest_url( N8LC_REST::NS . '/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'page' => $current_page,
			'currentUserId' => get_current_user_id(),
			'agents' => $agents,
			'pollInterval' => 4000,
			'i18n' => array(
				'loading' => __( 'Loading…', 'n8-livechat-pro' ),
				'error' => __( 'Something went wrong.', 'n8-livechat-pro' ),
				'empty' => __( 'Nothing to show yet.', 'n8-livechat-pro' ),
			),
		) );
	}

	public function render() {
		if ( ! N8LC_Security::admin_permission() && ! current_user_can( 'n8lc_view_analytics' ) && ! current_user_can( 'n8lc_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'n8-livechat-pro' ) );
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'n8-livechat';
		$titles = array(
			'n8-livechat' => __( 'LiveChat Dashboard', 'n8-livechat-pro' ),
			'n8-livechat-inbox' => __( 'Team Inbox', 'n8-livechat-pro' ),
			'n8-livechat-visitors' => __( 'Visitors', 'n8-livechat-pro' ),
			'n8-livechat-canned' => __( 'Canned Replies', 'n8-livechat-pro' ),
			'n8-livechat-departments' => __( 'Departments', 'n8-livechat-pro' ),
			'n8-livechat-analytics' => __( 'Analytics', 'n8-livechat-pro' ),
			'n8-livechat-settings' => __( 'Settings', 'n8-livechat-pro' ),
		);
		$title = isset( $titles[ $page ] ) ? $titles[ $page ] : __( 'N8 LiveChat', 'n8-livechat-pro' );
		echo '<div class="wrap n8lc-wrap">';
		echo '<div class="n8lc-page-head"><div><h1>' . esc_html( $title ) . '</h1><p>' . esc_html__( 'Real-time support operations from one WordPress dashboard.', 'n8-livechat-pro' ) . '</p></div><span class="n8lc-version">v' . esc_html( N8LC_VERSION ) . '</span></div>';
		echo '<div id="n8lc-admin-app" data-page="' . esc_attr( $page ) . '"><div class="n8lc-loading">' . esc_html__( 'Loading LiveChat…', 'n8-livechat-pro' ) . '</div></div>';
		echo '</div>';
	}

	public function admin_bar( $bar ) {
		if ( ! is_admin_bar_showing() || ! N8LC_Security::admin_permission() ) {
			return;
		}
		global $wpdb;
		$table = N8LC_DB::table( 'conversations' );
		$unread = (int) $wpdb->get_var( "SELECT COALESCE(SUM(unread_agent),0) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$bar->add_node( array(
			'id' => 'n8lc-livechat',
			'title' => sprintf( esc_html__( 'LiveChat (%d)', 'n8-livechat-pro' ), $unread ),
			'href' => admin_url( 'admin.php?page=n8-livechat-inbox' ),
		) );
	}
}
