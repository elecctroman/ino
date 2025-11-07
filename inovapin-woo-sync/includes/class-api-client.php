<?php
/**
 * API client implementation.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * Class Api_Client
 */
class Api_Client {
    /**
     * Perform authentication.
     *
     * @param string      $email    Email.
     * @param string      $password Password.
     * @param string|null $callback Callback data.
     *
     * @return array
     * @throws Exception When request fails.
     */
    public function authenticate( $email, $password, $callback = null ) {
        $payload = [
            'email'    => $email,
            'password' => $password,
        ];

        if ( ! empty( $callback ) ) {
            $payload['callbackData'] = $callback;
        }

        $response = $this->request( 'POST', '/Login/Customer/Api/Verify', [ 'body' => wp_json_encode( $payload ) ], [ 'skip_auth' => true ] );

        if ( empty( $response['data']['token'] ) ) {
            throw new Exception( __( 'API token alınamadı.', 'inovapin-woo-sync' ) );
        }

        return [
            'token'           => $response['data']['token'],
            'customerApiKey'  => isset( $response['data']['customerApiKey'] ) ? $response['data']['customerApiKey'] : '',
        ];
    }

    /**
     * Test API connection.
     *
     * @return array
     * @throws Exception On failure.
     */
    public function test_connection() {
        return $this->request( 'GET', '/Customer/Get' );
    }

    /**
     * Retrieve categories.
     *
     * @return array
     */
    public function get_categories() {
        return $this->request( 'GET', '/Categories' );
    }

    /**
     * Fetch product list.
     *
     * @param array $query Query args.
     *
     * @return array
     */
    public function get_products( $query = [] ) {
        $defaults = [
            'page'              => 1,
            'pageSize'          => 50,
            'detailed'          => true,
        ];
        $query    = wp_parse_args( $query, $defaults );

        return $this->request( 'POST', '/Products/List', [ 'body' => wp_json_encode( $query ) ] );
    }

    /**
     * Retrieve product detail.
     *
     * @param int $product_id Product ID.
     *
     * @return array
     */
    public function get_product_detail( $product_id ) {
        return $this->request( 'POST', '/Products/Detail/' . absint( $product_id ) );
    }

    /**
     * Create order.
     *
     * @param array $payload Payload.
     *
     * @return array
     */
    public function create_order( array $payload ) {
        return $this->request( 'POST', '/Order/create', [ 'body' => wp_json_encode( $payload ) ] );
    }

    /**
     * Get order detail.
     *
     * @param int $order_detail_id Order detail ID.
     *
     * @return array
     */
    public function get_order_detail( $order_detail_id ) {
        return $this->request( 'GET', '/Order/detail/' . absint( $order_detail_id ) );
    }

    /**
     * Cancel order.
     *
     * @param array $payload Payload.
     *
     * @return array
     */
    public function cancel_order( array $payload ) {
        return $this->request( 'POST', '/Order/cancel', [ 'body' => wp_json_encode( $payload ) ] );
    }

    /**
     * Generic request wrapper.
     *
     * @param string $method   HTTP method.
     * @param string $endpoint Endpoint.
     * @param array  $args     wp_remote_request args.
     * @param array  $options  Additional options.
     *
     * @return array
     * @throws Exception On error.
     */
    public function request( $method, $endpoint, $args = [], $options = [] ) {
        $base    = trailingslashit( inovapin_woo_sync_get_option( 'api_base', 'https://api.inovapin.com' ) );
        $url     = untrailingslashit( $base ) . $endpoint;
        $timeout = (int) inovapin_woo_sync_get_option( 'timeout', 20 );
        $token   = inovapin_woo_sync_get_option( 'api_token', '' );
        $api_key = inovapin_woo_sync_get_option( 'api_key', '' );
        $region  = inovapin_woo_sync_get_option( 'region', 'TR' );

        $headers = [
            'Content-Type'   => 'application/json',
            'Accept'         => 'application/json',
            'h-region-code'  => $region,
        ];

        if ( empty( $options['skip_auth'] ) ) {
            if ( empty( $token ) ) {
                throw new Exception( __( 'API token gerekli.', 'inovapin-woo-sync' ) );
            }

            $headers['Authorization'] = 'Bearer ' . $token;
        }

        if ( ! empty( $options['require_api_key'] ) ) {
            if ( empty( $api_key ) ) {
                throw new Exception( __( 'Apikey gereklidir.', 'inovapin-woo-sync' ) );
            }

            $headers['Apikey'] = $api_key;
        }

        $rate_limit = (int) inovapin_woo_sync_get_option( 'rate_limit', 4 );
        $this->enforce_rate_limit( $rate_limit );

        $defaults = [
            'method'  => $method,
            'timeout' => $timeout,
            'headers' => $headers,
        ];
        $args     = wp_parse_args( $args, $defaults );

        $attempts = 0;
        $max_tries = 3;
        do {
            $attempts++;
            $response = wp_safe_remote_request( $url, $args );

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                inovapin_woo_sync_logger()->error( 'api', $error_message, [ 'endpoint' => $endpoint ] );

                if ( $attempts >= $max_tries ) {
                    throw new Exception( $error_message );
                }
            } else {
                $code = (int) wp_remote_retrieve_response_code( $response );
                $body = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( $code >= 200 && $code < 300 ) {
                    inovapin_woo_sync_logger()->debug( 'api', 'Request success', [ 'endpoint' => $endpoint ] );
                    return $body;
                }

                if ( in_array( $code, [ 429, 500, 502, 503 ], true ) && $attempts < $max_tries ) {
                    $this->backoff_sleep( $attempts );
                    continue;
                }

                $message = isset( $body['message'] ) ? $body['message'] : __( 'Bilinmeyen API hatası.', 'inovapin-woo-sync' );
                inovapin_woo_sync_logger()->error( 'api', $message, [ 'endpoint' => $endpoint, 'response_code' => $code ] );

                throw new Exception( $message, $code );
            }

            $this->backoff_sleep( $attempts );
        } while ( $attempts < $max_tries );

        throw new Exception( __( 'API isteği başarısız oldu.', 'inovapin-woo-sync' ) );
    }

    /**
     * Enforce rate limit using transient.
     *
     * @param int $rate_limit Requests per second.
     */
    protected function enforce_rate_limit( $rate_limit ) {
        if ( $rate_limit <= 0 ) {
            return;
        }

        $bucket_key = 'inovapin_woo_sync_rate_limit';
        $window     = get_transient( $bucket_key );

        if ( empty( $window ) ) {
            set_transient( $bucket_key, 1, 1 );
            return;
        }

        if ( $window >= $rate_limit ) {
            usleep( (int) ( 1 / max( 1, $rate_limit ) * 1e6 ) );
        }

        set_transient( $bucket_key, $window + 1, 1 );
    }

    /**
     * Exponential backoff.
     *
     * @param int $attempt Attempt number.
     */
    protected function backoff_sleep( $attempt ) {
        $sleep = pow( 2, $attempt );
        sleep( $sleep );
    }
}
