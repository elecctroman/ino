<?php
/**
 * WP-CLI integration.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_CLI;
use WP_CLI_Command;

/**
 * Class Cli
 */
class Cli {
    /**
     * Sync service.
     *
     * @var Sync_Service
     */
    protected $sync_service;

    /**
     * API client.
     *
     * @var Api_Client
     */
    protected $api_client;

    /**
     * Constructor.
     */
    public function __construct( Sync_Service $sync_service, Api_Client $api_client ) {
        $this->sync_service = $sync_service;
        $this->api_client   = $api_client;
    }

    /**
     * Register hooks.
     */
    public function hooks() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'inovapin', new class( $this->sync_service, $this->api_client ) extends WP_CLI_Command {
                protected $sync_service;
                protected $api_client;

                public function __construct( $sync_service, $api_client ) {
                    $this->sync_service = $sync_service;
                    $this->api_client   = $api_client;
                }

                /**
                 * Test API connection.
                 */
                public function test_connection() {
                    try {
                        $this->api_client->test_connection();
                        WP_CLI::success( 'API bağlantısı başarılı.' );
                    } catch ( \Exception $e ) {
                        WP_CLI::error( $e->getMessage() );
                    }
                }

                /**
                 * Trigger synchronisation.
                 *
                 * ## OPTIONS
                 *
                 * [--categories]
                 * : Kategorileri senkron et.
                 *
                 * [--products]
                 * : Ürünleri senkron et.
                 */
                public function sync( $args, $assoc_args ) {
                    $categories = isset( $assoc_args['categories'] );
                    $products   = isset( $assoc_args['products'] );

                    if ( ! $categories && ! $products ) {
                        $categories = $products = true;
                    }

                    try {
                        $result = $this->sync_service->run_manual_sync( $categories, $products );
                        WP_CLI::success( wp_json_encode( $result ) );
                    } catch ( \Exception $e ) {
                        WP_CLI::error( $e->getMessage() );
                    }
                }

                /**
                 * Clear plugin caches.
                 */
                public function clear_cache() {
                    delete_transient( Sync_Service::LOCK_KEY );
                    delete_transient( 'inovapin_woo_sync_rate_limit' );
                    WP_CLI::success( 'Inovapin Woo Sync önbellekleri temizlendi.' );
                }
            } );
        }
    }
}
