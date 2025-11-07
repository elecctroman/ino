<?php
/**
 * Uninstaller.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Uninstaller
 */
class Uninstaller {
    /**
     * Uninstall hook.
     */
    public static function uninstall() {
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            return;
        }

        global $wpdb;

        $tables = [
            $wpdb->prefix . 'inovapin_map',
            $wpdb->prefix . 'inovapin_logs',
            $wpdb->prefix . 'inovapin_stats',
        ];

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }

        delete_option( 'inovapin_woo_sync_settings' );
        delete_option( 'inovapin_woo_sync_last_sync' );
        delete_option( 'inovapin_woo_sync_last_error' );
    }
}
