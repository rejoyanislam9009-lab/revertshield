<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Availability {
    public static function is_open( $settings = null, $timestamp = null ) {
        $settings = is_array( $settings ) ? $settings : get_option( 'n8lc_settings', array() );
        if ( empty( $settings['business_hours_enabled'] ) ) {
            return true;
        }

        $hours = isset( $settings['business_hours'] ) && is_array( $settings['business_hours'] )
            ? $settings['business_hours']
            : N8LC_DB::default_business_hours();

        $timestamp = null === $timestamp ? time() : absint( $timestamp );
        $day_map   = array( 0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat' );
        $day_index = (int) wp_date( 'w', $timestamp, wp_timezone() );
        $day       = isset( $day_map[ $day_index ] ) ? $day_map[ $day_index ] : 'mon';

        if ( empty( $hours[ $day ] ) || empty( $hours[ $day ]['enabled'] ) ) {
            return false;
        }

        $start = self::normalize_time( isset( $hours[ $day ]['start'] ) ? $hours[ $day ]['start'] : '09:00' );
        $end   = self::normalize_time( isset( $hours[ $day ]['end'] ) ? $hours[ $day ]['end'] : '17:00' );
        $now   = wp_date( 'H:i', $timestamp, wp_timezone() );

        if ( $start <= $end ) {
            return $now >= $start && $now <= $end;
        }

        return $now >= $start || $now <= $end;
    }

    public static function label( $settings = null ) {
        return self::is_open( $settings ) ? __( 'Online', 'n8-livechat-pro' ) : __( 'Away', 'n8-livechat-pro' );
    }

    private static function normalize_time( $value ) {
        $value = preg_replace( '/[^0-9:]/', '', (string) $value );
        if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
            return '09:00';
        }
        return $value;
    }
}
