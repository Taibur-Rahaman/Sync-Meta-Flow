<?php
/**
 * Attribution model utilities for Sync Meta Flow.
 *
 * @package Sync_Meta_Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMF_Attribution_Model {

    public static function normalize_model( $model ) {
        $allowed = array( 'last_touch', 'first_touch', 'first_last', 'assisted' );
        $model   = sanitize_key( (string) $model );
        return in_array( $model, $allowed, true ) ? $model : 'last_touch';
    }

    public static function label( $model ) {
        $labels = array(
            'last_touch'  => __( 'Last Touch', 'sync-meta-flow' ),
            'first_touch' => __( 'First Touch', 'sync-meta-flow' ),
            'first_last'  => __( 'First + Last', 'sync-meta-flow' ),
            'assisted'    => __( 'Assisted', 'sync-meta-flow' ),
        );
        $model = self::normalize_model( $model );
        return $labels[ $model ];
    }

    public static function get_selected_campaign( $first, $last, $model ) {
        $model = self::normalize_model( $model );
        $first = is_array( $first ) ? $first : array();
        $last  = is_array( $last ) ? $last : array();

        switch ( $model ) {
            case 'first_touch':
                return $first;
            case 'first_last':
                return $last ? $last : $first;
            case 'assisted':
                return $last ? $last : $first;
            case 'last_touch':
            default:
                return $last ? $last : $first;
        }
    }

    public static function is_different_touch( $first, $last ) {
        $first_id = self::touch_id( $first );
        $last_id  = self::touch_id( $last );
        return $first_id && $last_id && $first_id !== $last_id;
    }

    public static function touch_id( $touch ) {
        if ( ! is_array( $touch ) ) {
            return '';
        }
        foreach ( array( 'ad_id', 'adset_id', 'campaign_id' ) as $key ) {
            if ( ! empty( $touch[ $key ] ) ) {
                return sanitize_text_field( (string) $touch[ $key ] );
            }
        }
        if ( ! empty( $touch['utm_campaign'] ) ) {
            return sanitize_text_field( (string) $touch['utm_campaign'] );
        }
        return '';
    }

    public static function display_name( $touch ) {
        if ( ! is_array( $touch ) ) {
            return '';
        }
        foreach ( array( 'campaign_name', 'utm_campaign', 'campaign_id', 'adset_id', 'ad_id' ) as $key ) {
            if ( ! empty( $touch[ $key ] ) ) {
                return sanitize_text_field( (string) $touch[ $key ] );
            }
        }
        return '';
    }
}
