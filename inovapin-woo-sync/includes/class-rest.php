<?php
/**
 * REST endpoints.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Rest_Controller
 */
class Rest_Controller {
    /**
     * Namespace.
     */
    const REST_NAMESPACE = 'inovapin/v1';

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
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        register_rest_route( self::REST_NAMESPACE, '/sync/run', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run_sync' ],
            'permission_callback' => [ $this, 'permission_admin' ],
            'args'                => [
                'categories' => [ 'type' => 'boolean', 'default' => true ],
                'products'   => [ 'type' => 'boolean', 'default' => true ],
            ],
        ] );

        register_rest_route( self::REST_NAMESPACE, '/health', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'health_check' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::REST_NAMESPACE, '/callback/products', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'products_update_callback' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Admin permission.
     */
    public function permission_admin() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Run sync.
     */
    public function run_sync( WP_REST_Request $request ) {
        $categories = rest_sanitize_boolean( $request->get_param( 'categories' ) );
        $products   = rest_sanitize_boolean( $request->get_param( 'products' ) );

        try {
            $result = $this->sync_service->run_manual_sync( $categories, $products );
            return new WP_REST_Response( $result, 200 );
        } catch ( \Exception $e ) {
            return new WP_Error( 'inovapin_sync_error', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    /**
     * Health check.
     */
    public function health_check() {
        try {
            $response = $this->api_client->test_connection();
            return new WP_REST_Response( [
                'status' => 'ok',
                'data'   => $response,
            ], 200 );
        } catch ( \Exception $e ) {
            return new WP_REST_Response( [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500 );
        }
    }

    /**
     * Handle products update callback.
     */
    public function products_update_callback( WP_REST_Request $request ) {
        $api_key = inovapin_woo_sync_get_option( 'api_key', '' );
        $header  = $request->get_header( 'apikey' );

        if ( empty( $api_key ) || strtolower( $header ) !== strtolower( $api_key ) ) {
            return new WP_Error( 'inovapin_unauthorized', __( 'Apikey doğrulanamadı.', 'inovapin-woo-sync' ), [ 'status' => 401 ] );
        }

        $body = $request->get_json_params();
        inovapin_woo_sync_logger()->info( 'sync', 'Ürün güncelleme callback alındı.', [ 'body' => $body ] );

        return new WP_REST_Response( [ 'received' => true ], 200 );
    }
}
