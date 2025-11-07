<?php
/**
 * Plugin installer.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Installer
 */
class Installer {
    /**
     * Activation hook.
     */
    public static function activate() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        self::create_tables();
        self::schedule_events();
    }

    /**
     * Deactivation hook.
     */
    public static function deactivate() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        wp_clear_scheduled_hook( 'inovapin_sync_products' );
        wp_clear_scheduled_hook( 'inovapin_sync_categories' );
    }

    /**
     * Create custom tables.
     */
    protected static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $map_table = $wpdb->prefix . 'inovapin_map';
        $logs_table = $wpdb->prefix . 'inovapin_logs';
        $stats_table = $wpdb->prefix . 'inovapin_stats';

        $sql = "CREATE TABLE {$map_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            supplier_product_id BIGINT UNSIGNED NOT NULL,
            product_name_hash VARCHAR(64) NOT NULL,
            wc_product_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED DEFAULT 0,
            last_synced_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY supplier_product_id (supplier_product_id),
            UNIQUE KEY product_name_hash (product_name_hash),
            KEY wc_product_id (wc_product_id)
        ) {$charset_collate};";

        dbDelta( $sql );

        $sql = "CREATE TABLE {$logs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            context VARCHAR(50) NOT NULL,
            level VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY context (context)
        ) {$charset_collate};";

        dbDelta( $sql );

        $sql = "CREATE TABLE {$stats_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            stat_date DATETIME NOT NULL,
            range_type VARCHAR(20) NOT NULL,
            created_products BIGINT UNSIGNED DEFAULT 0,
            updated_products BIGINT UNSIGNED DEFAULT 0,
            error_count BIGINT UNSIGNED DEFAULT 0,
            duration BIGINT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id),
            KEY stat_date (stat_date)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Schedule cron events.
     */
    protected static function schedule_events() {
        if ( ! wp_next_scheduled( 'inovapin_sync_products' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'inovapin_sync_products' );
        }

        if ( ! wp_next_scheduled( 'inovapin_sync_categories' ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'inovapin_sync_categories' );
        }
    }
}
