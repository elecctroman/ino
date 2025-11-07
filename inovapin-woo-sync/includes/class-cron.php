<?php
/**
 * Cron handlers.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Cron
 */
class Cron {
    /**
     * Sync service.
     *
     * @var Sync_Service
     */
    protected $sync_service;

    /**
     * Constructor.
     */
    public function __construct( Sync_Service $sync_service ) {
        $this->sync_service = $sync_service;
    }

    /**
     * Hooks.
     */
    public function hooks() {
        add_action( 'init', [ $this, 'register_schedules' ] );
        add_action( 'inovapin_sync_products', [ $this, 'sync_products' ] );
        add_action( 'inovapin_sync_categories', [ $this, 'sync_categories' ] );
    }

    /**
     * Register cron schedules.
     */
    public function register_schedules() {
        if ( ! wp_next_scheduled( 'inovapin_sync_products' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'inovapin_sync_products' );
        }

        if ( ! wp_next_scheduled( 'inovapin_sync_categories' ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'inovapin_sync_categories' );
        }
    }

    /**
     * Sync products.
     */
    public function sync_products() {
        try {
            $this->sync_service->run_manual_sync( false, true );
        } catch ( \Exception $e ) {
            inovapin_woo_sync_logger()->error( 'sync', $e->getMessage(), [ 'source' => 'cron_products' ] );
        }
    }

    /**
     * Sync categories.
     */
    public function sync_categories() {
        try {
            $this->sync_service->run_manual_sync( true, false );
        } catch ( \Exception $e ) {
            inovapin_woo_sync_logger()->error( 'sync', $e->getMessage(), [ 'source' => 'cron_categories' ] );
        }
    }
}
