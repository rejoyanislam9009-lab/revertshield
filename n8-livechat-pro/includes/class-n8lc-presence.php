<?php

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight real agent presence for the visitor widget.
 *
 * Presence is intentionally ephemeral and never writes raw browsing telemetry
 * to the database. Active LiveChat admin pages refresh a short-lived pool.
 */
final class N8LC_Presence {
    const POOL_KEY = 'n8lc_agent_presence_pool';
    const TTL      = 90;

    public static function touch( $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id || ( ! user_can( $user_id, 'n8lc_reply_chat' ) && ! user_can( $user_id, 'manage_options' ) ) ) {
            return self::snapshot();
        }

        $pool = get_transient( self::POOL_KEY );
        $pool = is_array( $pool ) ? $pool : array();
        $pool = self::prune( $pool );
        $user = get_userdata( $user_id );
        $pool[ $user_id ] = array(
            'at'   => time(),
            'name' => $user ? sanitize_text_field( $user->display_name ) : '',
        );
        set_transient( self::POOL_KEY, $pool, self::TTL + 30 );
        return self::snapshot_from_pool( $pool );
    }

    public static function clear( $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        $pool = get_transient( self::POOL_KEY );
        if ( ! is_array( $pool ) ) {
            return self::snapshot_from_pool( array() );
        }
        unset( $pool[ $user_id ] );
        $pool = self::prune( $pool );
        if ( $pool ) {
            set_transient( self::POOL_KEY, $pool, self::TTL + 30 );
        } else {
            delete_transient( self::POOL_KEY );
        }
        return self::snapshot_from_pool( $pool );
    }

    public static function snapshot( $settings = null ) {
        $pool = get_transient( self::POOL_KEY );
        $pool = is_array( $pool ) ? self::prune( $pool ) : array();
        return self::snapshot_from_pool( $pool, $settings );
    }

    public static function status( $settings = null ) {
        $snapshot = self::snapshot( $settings );
        return $snapshot['status'];
    }

    public static function is_online( $settings = null ) {
        return 'online' === self::status( $settings );
    }

    private static function prune( array $pool ) {
        $cutoff = time() - self::TTL;
        foreach ( $pool as $user_id => $entry ) {
            $at = is_array( $entry ) && isset( $entry['at'] ) ? absint( $entry['at'] ) : 0;
            if ( ! $at || $at < $cutoff ) {
                unset( $pool[ $user_id ] );
            }
        }
        return $pool;
    }

    private static function snapshot_from_pool( array $pool, $settings = null ) {
        $count = count( $pool );
        if ( 0 === $count ) {
            $status = 'offline';
        } elseif ( N8LC_Availability::is_open( $settings ) ) {
            $status = 'online';
        } else {
            $status = 'away';
        }
        return array(
            'status'       => $status,
            'active_agents'=> $count,
            'updated_at'   => time(),
        );
    }
}
