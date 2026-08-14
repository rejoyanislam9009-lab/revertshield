<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

wp_clear_scheduled_hook( 'n8lc_daily_cleanup' );
wp_clear_scheduled_hook( 'n8lc_automation_tick' );

foreach ( array( 'administrator', 'editor', 'author', 'shop_manager', 'n8_livechat_agent' ) as $role_name ) {
    $role = get_role( $role_name );
    if ( ! $role ) {
        continue;
    }
    foreach ( array( 'n8lc_manage_chat', 'n8lc_reply_chat', 'n8lc_manage_settings', 'n8lc_view_analytics', 'n8lc_manage_tags', 'n8lc_export_chat' ) as $cap ) {
        $role->remove_cap( $cap );
    }
}
remove_role( 'n8_livechat_agent' );

$settings = get_option( 'n8lc_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
    return;
}

global $wpdb;
foreach ( array( 'conversation_tags', 'tags', 'events', 'canned_replies', 'messages', 'conversations', 'departments', 'visitors' ) as $name ) {
    $table = $wpdb->prefix . 'n8lc_' . $name;
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'n8lc_settings' );
delete_option( 'n8lc_db_version' );
delete_option( 'n8lc_rr_last_agent' );
