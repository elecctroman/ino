<?php
/**
 * Product mapper for WooCommerce.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use WC_Product_Simple;

/**
 * Class Product_Mapper
 */
class Product_Mapper {
    /**
     * Map incoming product to WooCommerce.
     *
     * @param array $product Product payload from API.
     *
     * @return int Product ID.
     * @throws Exception When creation fails.
     */
    public function map_product( array $product ) {
        $supplier_id   = isset( $product['productID'] ) ? absint( $product['productID'] ) : 0;
        $product_name  = isset( $product['productName'] ) ? wp_strip_all_tags( $product['productName'] ) : __( 'İsimsiz Ürün', 'inovapin-woo-sync' );
        $name_hash     = $this->get_name_hash( $product_name );
        $wc_product_id = $this->get_wc_product_id( $supplier_id, $name_hash, $product_name );
        $is_new        = false;

        if ( $wc_product_id ) {
            $wc_product = wc_get_product( $wc_product_id );
        } else {
            $wc_product = new WC_Product_Simple();
            $is_new     = true;
        }

        if ( ! $wc_product ) {
            throw new Exception( __( 'WooCommerce ürünü oluşturulamadı.', 'inovapin-woo-sync' ) );
        }

        $wc_product->set_name( $product_name );
        $wc_product->set_status( 'publish' );
        $wc_product->set_catalog_visibility( 'visible' );

        $description = isset( $product['productDescription'] ) ? wp_kses_post( $product['productDescription'] ) : '';
        $wc_product->set_description( $description );

        $commission = (float) inovapin_woo_sync_get_option( 'commission', 0 );
        $sale_price = isset( $product['salePrice'] ) ? (float) $product['salePrice'] : 0.0;
        $price      = $this->apply_commission( $sale_price, $commission );

        if ( inovapin_woo_sync_get_option( 'sync_price', 'yes' ) === 'yes' ) {
            $wc_product->set_regular_price( wc_format_decimal( $price, wc_get_price_decimals() ) );
            $wc_product->set_price( wc_format_decimal( $price, wc_get_price_decimals() ) );
        }

        if ( inovapin_woo_sync_get_option( 'sync_stock', 'yes' ) === 'yes' ) {
            $stock = isset( $product['totalStock'] ) ? (int) $product['totalStock'] : 0;
            $wc_product->set_manage_stock( true );
            $wc_product->set_stock_quantity( $stock );
            $wc_product->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );
        }

        $wc_product->update_meta_data( '_inovapin_product_id', $supplier_id );
        if ( isset( $product['customerStoreProductID'] ) ) {
            $wc_product->update_meta_data( '_inovapin_customer_store_product_id', sanitize_text_field( $product['customerStoreProductID'] ) );
        }
        if ( isset( $product['categoryID'] ) ) {
            $wc_product->update_meta_data( '_inovapin_category_id', absint( $product['categoryID'] ) );
        }
        $wc_product->update_meta_data( '_inovapin_last_sync', current_time( 'mysql' ) );
        if ( $is_new ) {
            $wc_product->update_meta_data( '_inovapin_newly_created', 'yes' );
        }

        $wc_product_id = $wc_product->save();

        $this->maybe_assign_category( $wc_product_id, $product );
        $this->maybe_assign_images( $wc_product_id, $product );
        $this->persist_mapping( $wc_product_id, $supplier_id, $name_hash, $product );
        $this->persist_requirements( $wc_product_id, $product );

        inovapin_woo_sync_logger()->info( 'sync', $is_new ? 'Ürün oluşturuldu' : 'Ürün güncellendi', [
            'product_id'      => $wc_product_id,
            'supplier_id'     => $supplier_id,
            'is_new'          => $is_new,
        ] );

        return $wc_product_id;
    }

    /**
     * Find Woo product id.
     */
    protected function get_wc_product_id( $supplier_id, $name_hash, $product_name = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'inovapin_map';

        if ( 'yes' === inovapin_woo_sync_get_option( 'sync_by_id', 'yes' ) && $supplier_id ) {
            $product_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT wc_product_id FROM {$table} WHERE supplier_product_id = %d", $supplier_id ) );
            if ( $product_id ) {
                return $product_id;
            }
        }

        if ( 'yes' === inovapin_woo_sync_get_option( 'sync_name_match', 'yes' ) ) {
            $product_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT wc_product_id FROM {$table} WHERE product_name_hash = %s", $name_hash ) );
            if ( $product_id ) {
                return $product_id;
            }

            if ( $product_name ) {
                $existing = get_page_by_title( $product_name, OBJECT, 'product' );
                if ( $existing ) {
                    return (int) $existing->ID;
                }
            }
        }

        return 0;
    }

    /**
     * Persist mapping row.
     */
    protected function persist_mapping( $wc_product_id, $supplier_id, $name_hash, array $product ) {
        global $wpdb;
        $table = $wpdb->prefix . 'inovapin_map';

        $category_id = isset( $product['categoryID'] ) ? (int) $product['categoryID'] : 0;

        $wpdb->replace(
            $table,
            [
                'supplier_product_id' => $supplier_id,
                'product_name_hash'  => $name_hash,
                'wc_product_id'      => $wc_product_id,
                'category_id'        => $category_id,
                'last_synced_at'     => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%d', '%d', '%s' ]
        );
    }

    /**
     * Save product requirements meta.
     */
    protected function persist_requirements( $wc_product_id, array $product ) {
        if ( empty( $product['productRequire'] ) || ! is_array( $product['productRequire'] ) ) {
            return;
        }

        $requires = [];
        foreach ( $product['productRequire'] as $require ) {
            $requires[] = [
                'productRequireID' => isset( $require['productRequireID'] ) ? (int) $require['productRequireID'] : 0,
                'identifier'       => isset( $require['identifier'] ) ? sanitize_key( $require['identifier'] ) : '',
                'title'            => isset( $require['title'] ) ? sanitize_text_field( $require['title'] ) : '',
                'type'             => isset( $require['type'] ) ? sanitize_key( $require['type'] ) : 'text',
                'min_length'       => isset( $require['min_length'] ) ? (int) $require['min_length'] : 0,
                'max_length'       => isset( $require['max_length'] ) ? (int) $require['max_length'] : 0,
                'placeholder'      => isset( $require['placeholder'] ) ? sanitize_text_field( $require['placeholder'] ) : '',
                'options'          => isset( $require['options'] ) ? array_map( 'sanitize_text_field', (array) $require['options'] ) : [],
                'is_required'      => ! empty( $require['is_required'] ),
            ];
        }

        update_post_meta( $wc_product_id, '_inovapin_requirements', wp_json_encode( $requires ) );
    }

    /**
     * Assign category.
     */
    protected function maybe_assign_category( $product_id, array $product ) {
        if ( empty( $product['categoryTree'] ) || 'yes' !== inovapin_woo_sync_get_option( 'sync_categories', 'yes' ) ) {
            return;
        }

        $term_ids = [];
        foreach ( (array) $product['categoryTree'] as $category ) {
            $term_ids[] = $this->ensure_category( $category );
        }

        if ( ! empty( $term_ids ) ) {
            wp_set_object_terms( $product_id, $term_ids, 'product_cat' );
        }
    }

    /**
     * Ensure WooCommerce category exists.
     */
    protected function ensure_category( array $category ) {
        $name = sanitize_text_field( $category['name'] );
        $slug = sanitize_title( $category['slug'] );
        $parent_id = 0;

        if ( isset( $category['parent'] ) && is_array( $category['parent'] ) ) {
            $parent_id = $this->ensure_category( $category['parent'] );
        }

        $existing = get_term_by( 'slug', $slug, 'product_cat' );
        if ( $existing ) {
            return (int) $existing->term_id;
        }

        $result = wp_insert_term( $name, 'product_cat', [
            'slug'   => $slug,
            'parent' => $parent_id,
        ] );

        if ( is_wp_error( $result ) ) {
            inovapin_woo_sync_logger()->error( 'sync', $result->get_error_message(), [ 'category' => $name ] );
            return 0;
        }

        return (int) $result['term_id'];
    }

    /**
     * Assign media.
     */
    protected function maybe_assign_images( $product_id, array $product ) {
        if ( 'yes' !== inovapin_woo_sync_get_option( 'sync_images', 'yes' ) ) {
            return;
        }

        $main_image = isset( $product['productMainImage'] ) ? esc_url_raw( $product['productMainImage'] ) : '';
        $gallery    = isset( $product['productImages'] ) ? (array) $product['productImages'] : [];

        if ( $main_image ) {
            $attachment_id = $this->maybe_download_image( $main_image );
            if ( $attachment_id ) {
                set_post_thumbnail( $product_id, $attachment_id );
            }
        }

        $gallery_ids = [];
        foreach ( $gallery as $image_url ) {
            $attachment_id = $this->maybe_download_image( esc_url_raw( $image_url ) );
            if ( $attachment_id ) {
                $gallery_ids[] = $attachment_id;
            }
        }

        if ( ! empty( $gallery_ids ) ) {
            update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_map( 'absint', $gallery_ids ) ) );
        }
    }

    /**
     * Download image if not already present.
     */
    protected function maybe_download_image( $url ) {
        if ( empty( $url ) ) {
            return 0;
        }

        global $wpdb;
        $hash  = md5( $url );
        $found = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_inovapin_image_hash' AND meta_value = %s LIMIT 1", $hash ) );

        if ( $found ) {
            return (int) $found;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) {
            inovapin_woo_sync_logger()->error( 'image', $tmp->get_error_message(), [ 'url' => $url ] );
            return 0;
        }

        $file_array = [
            'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload( $file_array, 0 );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $file_array['tmp_name'] );
            inovapin_woo_sync_logger()->error( 'image', $attachment_id->get_error_message(), [ 'url' => $url ] );
            return 0;
        }

        update_post_meta( $attachment_id, '_inovapin_image_hash', $hash );

        return (int) $attachment_id;
    }

    /**
     * Create hash.
     */
    protected function get_name_hash( $name ) {
        return md5( strtolower( $name ) );
    }

    /**
     * Apply commission.
     */
    protected function apply_commission( $price, $commission ) {
        $multiplier = ( 100 + $commission ) / 100;
        return $price * $multiplier;
    }
}
