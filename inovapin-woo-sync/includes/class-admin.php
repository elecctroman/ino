<?php
/**
 * Admin UI handling.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Admin
 */
class Admin {
    /**
     * API client.
     *
     * @var Api_Client
     */
    protected $api_client;

    /**
     * Sync service.
     *
     * @var Sync_Service
     */
    protected $sync_service;

    /**
     * Logger.
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Reporter.
     *
     * @var Reporter
     */
    protected $reporter;

    /**
     * Constructor.
     *
     * @param Api_Client   $api_client   API client.
     * @param Sync_Service $sync_service Sync service.
     * @param Logger       $logger       Logger.
     * @param Reporter     $reporter     Reporter.
     */
    public function __construct( Api_Client $api_client, Sync_Service $sync_service, Logger $logger, Reporter $reporter ) {
        $this->api_client   = $api_client;
        $this->sync_service = $sync_service;
        $this->logger       = $logger;
        $this->reporter     = $reporter;
    }

    /**
     * Register hooks.
     */
    public function hooks() {
        add_filter( 'woocommerce_integrations', [ $this, 'register_integration' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_inovapin_get_token', [ $this, 'ajax_get_token' ] );
        add_action( 'wp_ajax_inovapin_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_inovapin_manual_sync', [ $this, 'ajax_manual_sync' ] );
        add_action( 'wp_ajax_inovapin_get_stats', [ $this, 'ajax_get_stats' ] );
    }

    /**
     * Register integration class.
     *
     * @param array $integrations Integrations.
     *
     * @return array
     */
    public function register_integration( $integrations ) {
        if ( ! class_exists( __NAMESPACE__ . '\\Admin\\Integration' ) ) {
            require_once __DIR__ . '/class-admin-integration.php';
        }

        $integrations[] = __NAMESPACE__ . '\\Admin\\Integration';
        return $integrations;
    }

    /**
     * Enqueue assets.
     *
     * @param string $hook Current hook.
     */
    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'wc-settings' ) ) {
            return;
        }

        wp_enqueue_style( 'inovapin-woo-sync-admin' );
        wp_enqueue_script( 'inovapin-woo-sync-admin' );

        wp_localize_script( 'inovapin-woo-sync-admin', 'InovapinWooSync', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'restUrl'    => esc_url_raw( rest_url( Rest_Controller::REST_NAMESPACE ) ),
            'nonce'      => wp_create_nonce( 'inovapin-admin-nonce' ),
            'manualSync' => [
                'running' => __( 'Senkronizasyon devam ediyor...', 'inovapin-woo-sync' ),
                'success' => __( 'Senkron başarıyla tamamlandı.', 'inovapin-woo-sync' ),
                'error'   => __( 'Senkron sırasında bir hata oluştu.', 'inovapin-woo-sync' ),
            ],
            'testLabels' => [
                'success' => __( 'Bağlantı başarılı 🎉', 'inovapin-woo-sync' ),
                'error'   => __( 'Bağlantı başarısız 😥', 'inovapin-woo-sync' ),
            ],
        ] );
    }

    /**
     * Handle token request.
     */
    public function ajax_get_token() {
        check_ajax_referer( 'inovapin-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'İzin yok.', 'inovapin-woo-sync' ) ], 403 );
        }

        $email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $password = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';

        if ( empty( $email ) || empty( $password ) ) {
            wp_send_json_error( [ 'message' => __( 'Email ve parola gerekli.', 'inovapin-woo-sync' ) ], 400 );
        }

        try {
            $response = $this->api_client->authenticate( $email, $password );
        } catch ( \Exception $e ) {
            $this->logger->error( 'api', $e->getMessage(), [ 'action' => 'authenticate' ] );
            wp_send_json_error( [ 'message' => esc_html( $e->getMessage() ) ], 500 );
        }

        inovapin_woo_sync_update_option( 'api_token', $response['token'] );
        if ( ! empty( $response['customerApiKey'] ) ) {
            inovapin_woo_sync_update_option( 'api_key', $response['customerApiKey'] );
        }

        wp_send_json_success( [
            'token' => $response['token'],
            'apiKey' => isset( $response['customerApiKey'] ) ? $response['customerApiKey'] : '',
        ] );
    }

    /**
     * Handle connection test.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'inovapin-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'İzin yok.', 'inovapin-woo-sync' ) ], 403 );
        }

        try {
            $result = $this->api_client->test_connection();
            wp_send_json_success( [
                'message' => __( 'Bağlantı başarılı.', 'inovapin-woo-sync' ),
                'data'    => $result,
            ] );
        } catch ( \Exception $e ) {
            $this->logger->error( 'api', $e->getMessage(), [ 'action' => 'test_connection' ] );
            wp_send_json_error( [
                'message' => esc_html( $e->getMessage() ),
            ], 500 );
        }
    }

    /**
     * Manual sync handler.
     */
    /**
     * Provide stats data.
     */
    public function ajax_get_stats() {
        check_ajax_referer( 'inovapin-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'İzin yok.', 'inovapin-woo-sync' ) ], 403 );
        }

        $range = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : 'daily';
        $stats = $this->reporter->get_stats( $range );
        wp_send_json_success( [ 'stats' => $stats ] );
    }

    public function ajax_manual_sync() {
        check_ajax_referer( 'inovapin-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'İzin yok.', 'inovapin-woo-sync' ) ], 403 );
        }

        $sync_categories = isset( $_POST['categories'] ) && 'true' === $_POST['categories'];
        $sync_products   = isset( $_POST['products'] ) && 'true' === $_POST['products'];

        try {
            $result = $this->sync_service->run_manual_sync( $sync_categories, $sync_products );
            wp_send_json_success( $result );
        } catch ( \Exception $e ) {
            $this->logger->error( 'sync', $e->getMessage(), [ 'action' => 'manual_sync' ] );
            wp_send_json_error( [ 'message' => esc_html( $e->getMessage() ) ] );
        }
    }
}
