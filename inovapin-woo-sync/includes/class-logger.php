<?php
/**
 * Logger implementation.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use DateTime;
use DateTimeZone;

/**
 * Class Logger
 */
class Logger {
    /**
     * Instance.
     *
     * @var Logger
     */
    protected static $instance;

    /**
     * Get instance.
     *
     * @return Logger
     */
    public static function instance() {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Log entry.
     *
     * @param string $context Context.
     * @param string $message Message.
     * @param array  $extra   Extra data.
     */
    public function info( $context, $message, array $extra = [] ) {
        $this->write( 'info', $context, $message, $extra );
    }

    /**
     * Debug log.
     */
    public function debug( $context, $message, array $extra = [] ) {
        $this->write( 'debug', $context, $message, $extra );
    }

    /**
     * Error log.
     */
    public function error( $context, $message, array $extra = [] ) {
        $this->write( 'error', $context, $message, $extra );
    }

    /**
     * Write log to file and DB.
     *
     * @param string $level   Level.
     * @param string $context Context.
     * @param string $message Message.
     * @param array  $extra   Extra.
     */
    protected function write( $level, $context, $message, array $extra = [] ) {
        $masked_extra = $this->mask_sensitive( $extra );
        $line         = sprintf( '[%s] %s.%s: %s %s', $this->now(), strtoupper( $level ), $context, $message, wp_json_encode( $masked_extra ) );

        error_log( $line );
        $this->persist( $context, $level, $message, $masked_extra );
    }

    /**
     * Persist log entry in custom table.
     *
     * @param string $context Context.
     * @param string $level   Level.
     * @param string $message Message.
     * @param array  $extra   Extra.
     */
    protected function persist( $context, $level, $message, array $extra ) {
        global $wpdb;
        $table = $wpdb->prefix . 'inovapin_logs';
        $wpdb->insert(
            $table,
            [
                'context'    => sanitize_key( $context ),
                'level'      => sanitize_key( $level ),
                'message'    => wp_strip_all_tags( $message ),
                'meta'       => wp_json_encode( $extra ),
                'created_at' => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Mask PII.
     *
     * @param array $extra Extra data.
     *
     * @return array
     */
    protected function mask_sensitive( array $extra ) {
        $sensitive_keys = [ 'password', 'token', 'email', 'Authorization' ];
        foreach ( $extra as $key => &$value ) {
            if ( in_array( $key, $sensitive_keys, true ) ) {
                $value = '***';
            }
        }
        return $extra;
    }

    /**
     * Current timestamp.
     *
     * @return string
     */
    protected function now() {
        $dt = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
        return $dt->format( 'Y-m-d H:i:s' );
    }
}
