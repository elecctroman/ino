<?php
/**
 * WooCommerce settings integration.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync\Admin;

use WC_Integration;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integration settings screen.
 */
class Integration extends WC_Integration {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->id                 = 'inovapin-woo-sync';
        $this->method_title       = __( 'Inovapin Woo Sync', 'inovapin-woo-sync' );
        $this->method_description = __( 'Inovapin tedarikçi katalog ve sipariş senkronizasyonu.', 'inovapin-woo-sync' );

        $this->init_form_fields();
        $this->init_settings();

        add_action( 'woocommerce_update_options_integration_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    /**
     * Init form fields.
     */
    public function init_form_fields() {
        $this->form_fields = [
            'api_base'          => [
                'title'       => __( 'API Base URL', 'inovapin-woo-sync' ) . ' 🔌',
                'type'        => 'text',
                'description' => __( 'Varsayılan: https://api.inovapin.com', 'inovapin-woo-sync' ),
                'default'     => 'https://api.inovapin.com',
            ],
            'email'             => [
                'title'       => __( 'Email', 'inovapin-woo-sync' ) . ' 🧭',
                'type'        => 'text',
                'description' => __( 'Inovapin müşteri panelinde kayıtlı email adresiniz.', 'inovapin-woo-sync' ),
            ],
            'password'          => [
                'title'       => __( 'Parola', 'inovapin-woo-sync' ) . ' 🔐',
                'type'        => 'password',
                'description' => __( 'Token almak için kullanılır. Güvenlik için kaydedilmez.', 'inovapin-woo-sync' ),
            ],
            'api_token'         => [
                'title'       => __( 'API Token', 'inovapin-woo-sync' ) . ' 🪙',
                'type'        => 'text',
                'custom_attributes' => [ 'readonly' => 'readonly' ],
                'description' => __( 'Token Al / Yenile butonu ile doldurulur.', 'inovapin-woo-sync' ),
                'default'     => '',
            ],
            'api_key'           => [
                'title'       => __( 'API Key (Apikey)', 'inovapin-woo-sync' ) . ' 🧾',
                'type'        => 'text',
                'description' => __( 'Tedarikçi yetki isteyen uçlar için.', 'inovapin-woo-sync' ),
                'default'     => '',
            ],
            'region'            => [
                'title'       => __( 'Bölge (h-region-code)', 'inovapin-woo-sync' ) . ' 🧭',
                'type'        => 'text',
                'description' => __( 'Varsayılan TR.', 'inovapin-woo-sync' ),
                'default'     => 'TR',
            ],
            'commission'        => [
                'title'       => __( 'Komisyon (%)', 'inovapin-woo-sync' ) . ' 📈',
                'type'        => 'number',
                'description' => __( 'Fiyat = API salePrice × (1 + komisyon/100).', 'inovapin-woo-sync' ),
                'default'     => '50',
            ],
            'sync_name_match'   => [
                'title'       => __( 'İlk çalıştırmada ürün adlarıyla eşleştir', 'inovapin-woo-sync' ) . ' 🧭',
                'type'        => 'checkbox',
                'label'       => __( 'Varsayılan açık', 'inovapin-woo-sync' ),
                'default'     => 'yes',
            ],
            'sync_by_id'        => [
                'title'       => __( 'Sonraki güncellemelerde tedarikçi ProductID ile eşle', 'inovapin-woo-sync' ) . ' 🧾',
                'type'        => 'checkbox',
                'default'     => 'yes',
            ],
            'sync_images'       => [
                'title'       => __( 'Görselleri indir & tekrar indirme', 'inovapin-woo-sync' ) . ' 🖼️',
                'type'        => 'checkbox',
                'default'     => 'yes',
            ],
            'sync_stock'        => [
                'title'       => __( 'Stokları otomatik güncelle', 'inovapin-woo-sync' ) . ' 📦',
                'type'        => 'checkbox',
                'default'     => 'yes',
            ],
            'sync_price'        => [
                'title'       => __( 'Fiyatları otomatik güncelle', 'inovapin-woo-sync' ) . ' 💸',
                'type'        => 'checkbox',
                'default'     => 'yes',
            ],
            'sync_categories'   => [
                'title'       => __( 'Kategori ağacını WooCommerce’e birebir kur', 'inovapin-woo-sync' ) . ' 🌳',
                'type'        => 'checkbox',
                'default'     => 'yes',
            ],
            'timeout'           => [
                'title'       => __( 'API Zaman Aşımı (saniye)', 'inovapin-woo-sync' ),
                'type'        => 'number',
                'default'     => 20,
            ],
            'rate_limit'        => [
                'title'       => __( 'Saniye başı API isteği', 'inovapin-woo-sync' ),
                'type'        => 'number',
                'default'     => 4,
            ],
        ];
    }

    /**
     * Output the settings form with enhanced UI.
     */
    public function admin_options() {
        wp_enqueue_style( 'inovapin-woo-sync-admin' );
        wp_enqueue_script( 'inovapin-woo-sync-admin' );

        parent::admin_options();

        echo '<div class="inovapin-control-panel" data-nonce="' . esc_attr( wp_create_nonce( 'inovapin-admin-nonce' ) ) . '">';
        echo '<div class="inovapin-card-grid">';
        echo '<div class="inovapin-card"><h3>🔌 ' . esc_html__( 'API Bağlantı Durumu', 'inovapin-woo-sync' ) . '</h3><div class="inovapin-status" data-status="unknown"></div></div>';
        echo '<div class="inovapin-card"><h3>🧭 ' . esc_html__( 'Son Senkron', 'inovapin-woo-sync' ) . '</h3><p class="inovapin-last-sync">' . esc_html( get_option( 'inovapin_woo_sync_last_sync', __( 'Henüz senkron yok', 'inovapin-woo-sync' ) ) ) . '</p></div>';
        echo '<div class="inovapin-card"><h3>🧾 ' . esc_html__( 'Son Hata', 'inovapin-woo-sync' ) . '</h3><p class="inovapin-last-error">' . esc_html( get_option( 'inovapin_woo_sync_last_error', __( 'Yok', 'inovapin-woo-sync' ) ) ) . '</p></div>';
        echo '<div class="inovapin-card"><h3>📈 ' . esc_html__( 'Bugün Güncellenen Ürün', 'inovapin-woo-sync' ) . '</h3><span class="badge inovapin-updated-today">' . esc_html( $this->get_today_updates() ) . '</span></div>';
        echo '</div>';
        echo '<div class="inovapin-actions">';
        echo '<button type="button" class="button button-primary inovapin-get-token">🪙 ' . esc_html__( 'Token Al / Yenile', 'inovapin-woo-sync' ) . '</button>';
        echo '<button type="button" class="button inovapin-test-connection">🧪 ' . esc_html__( 'Bağlantı Testi', 'inovapin-woo-sync' ) . '</button>';
        echo '<button type="button" class="button inovapin-start-sync">🔄 ' . esc_html__( 'Senkronu Başlat', 'inovapin-woo-sync' ) . '</button>';
        echo '</div>';
        echo '<div class="inovapin-reports">';
        echo '<h2>📈 ' . esc_html__( 'Raporlar', 'inovapin-woo-sync' ) . '</h2>';
        echo '<div class="inovapin-toggle">
                <button data-range="daily" class="active">' . esc_html__( 'Günlük', 'inovapin-woo-sync' ) . '</button>
                <button data-range="weekly">' . esc_html__( 'Haftalık', 'inovapin-woo-sync' ) . '</button>
                <button data-range="monthly">' . esc_html__( 'Aylık', 'inovapin-woo-sync' ) . '</button>
            </div>';
        echo '<canvas id="inovapin-report-chart" height="160"></canvas>';
        echo '<div class="inovapin-report-table"><table><thead><tr><th>' . esc_html__( 'Tarih', 'inovapin-woo-sync' ) . '</th><th>' . esc_html__( 'Eklenen', 'inovapin-woo-sync' ) . '</th><th>' . esc_html__( 'Güncellenen', 'inovapin-woo-sync' ) . '</th><th>' . esc_html__( 'Hata', 'inovapin-woo-sync' ) . '</th><th>' . esc_html__( 'Süre (sn)', 'inovapin-woo-sync' ) . '</th></tr></thead><tbody class="inovapin-report-body"></tbody></table></div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Get today's update count.
     *
     * @return int
     */
    protected function get_today_updates() {
        global $wpdb;
        $table = $wpdb->prefix . 'inovapin_stats';
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(updated_products) FROM {$table} WHERE stat_date = %s", current_time( 'Y-m-d' ) ) );
    }
}
