<?php
/**
 * Reporter service for charts.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Reporter
 */
class Reporter {
    /**
     * Record sync stats.
     *
     * @param string $range Range daily/weekly/monthly.
     * @param array  $data  Data to insert.
     */
    public function record( $range, array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'inovapin_stats';
        $wpdb->insert(
            $table,
            [
                'stat_date'        => current_time( 'mysql' ),
                'range_type'       => sanitize_key( $range ),
                'created_products' => isset( $data['created'] ) ? (int) $data['created'] : 0,
                'updated_products' => isset( $data['updated'] ) ? (int) $data['updated'] : 0,
                'error_count'      => isset( $data['errors'] ) ? (int) $data['errors'] : 0,
                'duration'         => isset( $data['duration'] ) ? (int) $data['duration'] : 0,
            ],
            [ '%s', '%s', '%d', '%d', '%d', '%d' ]
        );
    }

    /**
     * Retrieve stats for reporting.
     *
     * @param string $range Range type.
     *
     * @return array
     */
    public function get_stats( $range = 'daily' ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'inovapin_stats';
        $range  = sanitize_key( $range );

        if ( 'weekly' === $range ) {
            $query = "SELECT DATE_FORMAT(stat_date, '%x-W%v') AS stat_period, SUM(created_products) AS created_products, SUM(updated_products) AS updated_products, SUM(error_count) AS error_count, SUM(duration) AS duration, MAX(stat_date) AS stat_date FROM {$table} WHERE stat_date >= DATE_SUB( NOW(), INTERVAL 3 MONTH ) GROUP BY stat_period ORDER BY stat_date DESC LIMIT 12";
            return $wpdb->get_results( $query, ARRAY_A );
        }

        if ( 'monthly' === $range ) {
            $query = "SELECT DATE_FORMAT(stat_date, '%Y-%m') AS stat_period, SUM(created_products) AS created_products, SUM(updated_products) AS updated_products, SUM(error_count) AS error_count, SUM(duration) AS duration, MAX(stat_date) AS stat_date FROM {$table} WHERE stat_date >= DATE_SUB( NOW(), INTERVAL 1 YEAR ) GROUP BY stat_period ORDER BY stat_date DESC LIMIT 12";
            return $wpdb->get_results( $query, ARRAY_A );
        }

        $query = $wpdb->prepare( "SELECT stat_date, created_products, updated_products, error_count, duration FROM {$table} WHERE stat_date >= DATE_SUB( NOW(), INTERVAL 2 WEEK ) ORDER BY stat_date DESC LIMIT %d", 30 );
        return $wpdb->get_results( $query, ARRAY_A );
    }
}
