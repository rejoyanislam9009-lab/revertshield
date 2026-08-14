<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

wp_clear_scheduled_hook( 'n8lc_daily_cleanup' );
wp_clear_scheduled_hook( 'n8lc_automation_tick' );

$caps = array(
    'n8lc_manage_chat',
    'n8lc_reply_chat',
    'n8lc_manage_settings',
    'n8lc_view_analytics',
    'n8lc_manage_tags',
    'n8lc_export_chat',
    'n8lc_manage_team',
    'n8lc_manage_routing',
    'n8lc_manage_fields',
    'n8lc_manage_integrations',
    'n8lc_manage_privacy',
    'n8lc_manage_knowledge',
    'n8lc_view_health',
);

$roles = wp_roles();
if ( $roles ) {
    foreach ( array_keys( $roles->roles ) as $role_name ) {
        $role = get_role( $role_name );
        if ( ! $role ) {
            continue;
        }
        foreach ( $caps as $cap ) {
            $role->remove_cap( $cap );
        }
    }
}
remove_role( 'n8_livechat_agent' );
remove_role( 'n8_livechat_manager' );

$settings = get_option( 'n8lc_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
    return;
}

global $wpdb;
foreach ( array(
    'agent_profiles',
    'routing_rules',
    'saved_views',
    'segments',
    'custom_fields',
    'integrations',
    'blocks',
    'conversation_tags',
    'tags',
    'events',
    'canned_replies',
    'messages',
    'conversations',
    'departments',
    'visitors',
) as $name ) {
    $table = $wpdb->prefix . 'n8lc_' . $name;
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$knowledge_ids = get_posts( array(
    'post_type'      => 'n8lc_article',
    'post_status'    => 'any',
    'numberposts'    => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
) );
foreach ( $knowledge_ids as $post_id ) {
    wp_delete_post( absint( $post_id ), true );
}

delete_option( 'n8lc_settings' );
delete_option( 'n8lc_db_version' );
delete_option( 'n8lc_rr_last_agent' );
delete_option( 'n8lc_platform_settings' );
delete_option( 'n8lc_platform_schema_version' );
