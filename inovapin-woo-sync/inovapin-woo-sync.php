<?php
/**
 * Plugin Name:       Inovapin Woo Sync
 * Plugin URI:        https://inovapin.com/
 * Description:       Seamlessly synchronise Inovapin supplier catalogue and orders with WooCommerce.
 * Version:           1.0.0
 * Author:            Inovapin Integrations Team
 * Author URI:        https://inovapin.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       inovapin-woo-sync
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'INOVAPIN_WOO_SYNC_PATH' ) ) {
    define( 'INOVAPIN_WOO_SYNC_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'INOVAPIN_WOO_SYNC_URL' ) ) {
    define( 'INOVAPIN_WOO_SYNC_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'INOVAPIN_WOO_SYNC_VERSION' ) ) {
    define( 'INOVAPIN_WOO_SYNC_VERSION', '1.0.0' );
}

require_once INOVAPIN_WOO_SYNC_PATH . 'includes/helpers.php';

spl_autoload_register( static function ( $class ) {
    $prefix   = 'Inovapin\\WooSync\\';
    $base_dir = INOVAPIN_WOO_SYNC_PATH . 'includes/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $relative_path  = 'class-' . str_replace( '\\', '-', strtolower( $relative_class ) ) . '.php';
    $file           = $base_dir . $relative_path;

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * Boot the plugin.
 */
function inovapin_woo_sync_boot() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $plugin = Inovapin\WooSync\Plugin::instance();
    $plugin->init();
}
add_action( 'plugins_loaded', 'inovapin_woo_sync_boot' );

register_activation_hook( __FILE__, [ 'Inovapin\\WooSync\\Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Inovapin\\WooSync\\Installer', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ 'Inovapin\\WooSync\\Uninstaller', 'uninstall' ] );
