<?php
/**
 * Synchronisation services.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * Class Sync_Service
 */
class Sync_Service {
    /**
     * API client.
     *
     * @var Api_Client
     */
    protected $client;

    /**
     * Product mapper.
     *
     * @var Product_Mapper
     */
    protected $mapper;

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
     * Lock key.
     */
    const LOCK_KEY = 'inovapin_sync_lock';

    /**
     * Constructor.
     */
    public function __construct( Api_Client $client, Product_Mapper $mapper, Logger $logger, Reporter $reporter ) {
        $this->client   = $client;
        $this->mapper   = $mapper;
        $this->logger   = $logger;
        $this->reporter = $reporter;
    }

    /**
     * Manual sync handler.
     *
     * @param bool $sync_categories Sync categories.
     * @param bool $sync_products   Sync products.
     *
     * @return array
     * @throws Exception When lock is active.
     */
    public function run_manual_sync( $sync_categories = true, $sync_products = true ) {
        if ( ! $this->acquire_lock() ) {
            throw new Exception( __( 'Senkron zaten çalışıyor.', 'inovapin-woo-sync' ) );
        }

        $summary = [
            'categories' => 0,
            'created'    => 0,
            'updated'    => 0,
            'errors'     => 0,
        ];

        $start = microtime( true );

        try {
            if ( $sync_categories ) {
                $summary['categories'] = $this->sync_categories();
            }

            if ( $sync_products ) {
                $stats = $this->sync_products();
                $summary = array_merge( $summary, $stats );
            }

            update_option( 'inovapin_woo_sync_last_sync', current_time( 'mysql' ) );
            update_option( 'inovapin_woo_sync_last_error', '' );
        } catch ( Exception $e ) {
            $summary['errors']++;
            update_option( 'inovapin_woo_sync_last_error', $e->getMessage() );
            $this->logger->error( 'sync', $e->getMessage() );
            throw $e;
        } finally {
            $this->release_lock();
            $duration = microtime( true ) - $start;
            $this->reporter->record( 'daily', [
                'created'  => isset( $summary['created'] ) ? $summary['created'] : 0,
                'updated'  => isset( $summary['updated'] ) ? $summary['updated'] : 0,
                'errors'   => $summary['errors'],
                'duration' => (int) $duration,
            ] );
        }

        return $summary;
    }

    /**
     * Sync categories.
     *
     * @return int Number of categories processed.
     */
    public function sync_categories() {
        $response = $this->client->get_categories();
        $count    = 0;

        if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
            return $count;
        }

        foreach ( $response['data'] as $category ) {
            $this->sync_category_tree( $category );
            $count++;
        }

        return $count;
    }

    /**
     * Sync a category tree recursively.
     */
    protected function sync_category_tree( array $category, $parent_id = 0 ) {
        $name = sanitize_text_field( $category['name'] );
        $slug = sanitize_title( isset( $category['slug'] ) ? $category['slug'] : $name );

        $existing = get_term_by( 'slug', $slug, 'product_cat' );
        if ( $existing ) {
            $term_id = (int) $existing->term_id;
            wp_update_term( $term_id, 'product_cat', [ 'parent' => $parent_id ] );
        } else {
            $result = wp_insert_term( $name, 'product_cat', [
                'slug'   => $slug,
                'parent' => $parent_id,
            ] );
            if ( is_wp_error( $result ) ) {
                $this->logger->error( 'sync', $result->get_error_message(), [ 'category' => $name ] );
                return;
            }
            $term_id = (int) $result['term_id'];
        }

        if ( ! empty( $category['children'] ) ) {
            foreach ( (array) $category['children'] as $child ) {
                $this->sync_category_tree( $child, $term_id );
            }
        }
    }

    /**
     * Sync products.
     *
     * @return array
     */
    public function sync_products() {
        $page      = 1;
        $created   = 0;
        $updated   = 0;
        $errors    = 0;
        $processed = 0;

        do {
            $response = $this->client->get_products( [ 'page' => $page ] );
            $products = isset( $response['data']['items'] ) ? $response['data']['items'] : [];

            if ( empty( $products ) ) {
                break;
            }

            foreach ( $products as $product ) {
                try {
                    $existing_id = $this->mapper->map_product( $product );
                    if ( $existing_id ) {
                        $processed++;
                        if ( get_post_meta( $existing_id, '_inovapin_newly_created', true ) ) {
                            $created++;
                        } else {
                            $updated++;
                        }
                        delete_post_meta( $existing_id, '_inovapin_newly_created' );
                    }
                } catch ( Exception $e ) {
                    $errors++;
                    $this->logger->error( 'sync', $e->getMessage(), [ 'product' => isset( $product['productName'] ) ? $product['productName'] : '' ] );
                }
            }

            $page++;
            $has_more = ! empty( $response['data']['hasMore'] );
        } while ( $has_more );

        return [
            'created'  => $created,
            'updated'  => $updated,
            'errors'   => $errors,
            'processed'=> $processed,
        ];
    }

    /**
     * Acquire lock.
     */
    protected function acquire_lock() {
        if ( get_transient( self::LOCK_KEY ) ) {
            return false;
        }

        set_transient( self::LOCK_KEY, 1, MINUTE_IN_SECONDS * 10 );
        return true;
    }

    /**
     * Release lock.
     */
    protected function release_lock() {
        delete_transient( self::LOCK_KEY );
    }
}
