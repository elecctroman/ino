<?php
/**
 * Core plugin orchestrator.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

use Inovapin\WooSync\Admin;
use Inovapin\WooSync\Cron;
use Inovapin\WooSync\Require_Fields;
use Inovapin\WooSync\Rest_Controller;
use Inovapin\WooSync\Cli; 

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Plugin
 */
class Plugin {
    /**
     * Singleton instance.
     *
     * @var Plugin
     */
    protected static $instance;

    /**
     * Admin instance.
     *
     * @var Admin
     */
    protected $admin;

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
     * Reporter service.
     *
     * @var Reporter
     */
    protected $reporter;

    /**
     * REST Controller.
     *
     * @var Rest_Controller
     */
    protected $rest_controller;

    /**
     * CLI handler.
     *
     * @var Cli
     */
    protected $cli;

    /**
     * Get instance.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Init plugin.
     */
    public function init() {
        $this->logger        = Logger::instance();
        $this->api_client    = new Api_Client();
        $this->reporter      = new Reporter();
        $this->sync_service  = new Sync_Service( $this->api_client, new Product_Mapper(), $this->logger, $this->reporter );
        $this->admin         = new Admin( $this->api_client, $this->sync_service, $this->logger, $this->reporter );
        $this->rest_controller = new Rest_Controller( $this->sync_service, $this->api_client );
        $this->cli           = new Cli( $this->sync_service, $this->api_client );

        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'register_assets' ] );

        if ( is_admin() ) {
            $this->admin->hooks();
        }

        $require_fields = new Require_Fields();
        $require_fields->hooks();

        $cron = new Cron( $this->sync_service );
        $cron->hooks();

        $this->rest_controller->hooks();
        $this->cli->hooks();
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'inovapin-woo-sync', false, basename( INOVAPIN_WOO_SYNC_PATH ) . '/languages/' );
    }

    /**
     * Register admin assets.
     */
    public function register_assets() {
        wp_register_style( 'inovapin-woo-sync-admin', INOVAPIN_WOO_SYNC_URL . 'assets/admin.css', [], INOVAPIN_WOO_SYNC_VERSION );
        wp_register_script( 'chart-js', INOVAPIN_WOO_SYNC_URL . 'assets/vendor/chart.min.js', [], '4.4.0', true );
        wp_register_script( 'inovapin-woo-sync-charts', INOVAPIN_WOO_SYNC_URL . 'assets/charts.js', [ 'chart-js' ], INOVAPIN_WOO_SYNC_VERSION, true );
        wp_register_script( 'inovapin-woo-sync-admin', INOVAPIN_WOO_SYNC_URL . 'assets/admin.js', [ 'jquery', 'wp-util', 'inovapin-woo-sync-charts' ], INOVAPIN_WOO_SYNC_VERSION, true );
    }
}
