<?php
/**
 * Helper functions for Inovapin Woo Sync.
 *
 * @package Inovapin\WooSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Inovapin\WooSync\Logger;

if ( ! function_exists( 'inovapin_woo_sync_get_option' ) ) {
    /**
     * Retrieve plugin option with optional default.
     *
     * @param string $key     Option key.
     * @param mixed  $default Default value.
     *
     * @return mixed
     */
    function inovapin_woo_sync_get_option( $key, $default = '' ) {
        $options = (array) get_option( 'inovapin_woo_sync_settings', [] );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }
}

if ( ! function_exists( 'inovapin_woo_sync_update_option' ) ) {
    /**
     * Update plugin option.
     *
     * @param string $key   Option key.
     * @param mixed  $value Value.
     */
    function inovapin_woo_sync_update_option( $key, $value ) {
        $options         = (array) get_option( 'inovapin_woo_sync_settings', [] );
        $options[ $key ] = $value;
        update_option( 'inovapin_woo_sync_settings', $options );
    }
}

if ( ! function_exists( 'inovapin_woo_sync_logger' ) ) {
    /**
     * Retrieve logger instance.
     *
     * @return Logger
     */
    function inovapin_woo_sync_logger() {
        return Logger::instance();
    }
}

if ( ! function_exists( 'inovapin_woo_sync_is_request' ) ) {
    /**
     * Check the type of request.
     *
     * @param string $type Type.
     *
     * @return bool
     */
    function inovapin_woo_sync_is_request( $type ) {
        switch ( $type ) {
            case 'admin':
                return is_admin();
            case 'cron':
                return defined( 'DOING_CRON' );
            case 'rest':
                return defined( 'REST_REQUEST' );
            default:
                return false;
        }
    }
}
