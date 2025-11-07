<?php
/**
 * Handle dynamic required fields for products.
 *
 * @package Inovapin\WooSync
 */

namespace Inovapin\WooSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Require_Fields
 */
class Require_Fields {
    /**
     * Hooks.
     */
    public function hooks() {
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_fields' ] );
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_fields' ], 10, 4 );
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 4 );
    }

    /**
     * Render requirement fields on product page.
     */
    public function render_fields() {
        global $product;
        if ( ! $product ) {
            return;
        }

        $requirements = $this->get_requirements( $product->get_id() );
        if ( empty( $requirements ) ) {
            return;
        }

        wp_nonce_field( 'inovapin_require_fields', 'inovapin_require_nonce' );

        echo '<div class="inovapin-requirements-block">';
        echo '<h3>' . esc_html__( 'Gerekli Bilgiler', 'inovapin-woo-sync' ) . '</h3>';

        foreach ( $requirements as $require ) {
            $field_id   = 'inovapin_require_' . esc_attr( $require['identifier'] );
            $label      = esc_html( $require['title'] );
            $value      = isset( $_POST[ $field_id ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_id ] ) ) : '';
            $required   = ! empty( $require['is_required'] );
            $attributes = '';

            if ( $require['min_length'] ) {
                $attributes .= ' minlength="' . intval( $require['min_length'] ) . '"';
            }
            if ( $require['max_length'] ) {
                $attributes .= ' maxlength="' . intval( $require['max_length'] ) . '"';
            }
            if ( $required ) {
                $attributes .= ' required';
            }

            echo '<div class="inovapin-field">';
            echo '<label for="' . esc_attr( $field_id ) . '">' . $label . ( $required ? '<span class="required">*</span>' : '' ) . '</label>';

            switch ( $require['type'] ) {
                case 'select':
                    echo '<select name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '"' . $attributes . '>';
                    echo '<option value="">' . esc_html__( 'Seçiniz', 'inovapin-woo-sync' ) . '</option>';
                    foreach ( (array) $require['options'] as $option ) {
                        echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( $option ) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 'textarea':
                    echo '<textarea name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '"' . $attributes . ' placeholder="' . esc_attr( $require['placeholder'] ) . '">' . esc_textarea( $value ) . '</textarea>';
                    break;
                default:
                    echo '<input type="text" name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '"' . $attributes . ' placeholder="' . esc_attr( $require['placeholder'] ) . '" value="' . esc_attr( $value ) . '" />';
            }

            if ( ! empty( $require['tooltip'] ) ) {
                echo '<p class="description">' . esc_html( $require['tooltip'] ) . '</p>';
            }

            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Validate fields before add to cart.
     */
    public function validate_fields( $passed, $product_id, $quantity, $variation_id = 0 ) {
        $requirements = $this->get_requirements( $product_id );
        if ( empty( $requirements ) ) {
            return $passed;
        }

        if ( empty( $_POST['inovapin_require_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['inovapin_require_nonce'] ), 'inovapin_require_fields' ) ) {
            wc_add_notice( __( 'Güvenlik hatası. Lütfen tekrar deneyin.', 'inovapin-woo-sync' ), 'error' );
            return false;
        }

        foreach ( $requirements as $require ) {
            $field_id = 'inovapin_require_' . $require['identifier'];
            $value    = isset( $_POST[ $field_id ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_id ] ) ) : '';

            if ( ! empty( $require['is_required'] ) && '' === $value ) {
                wc_add_notice( sprintf( __( '%s alanı zorunlu.', 'inovapin-woo-sync' ), esc_html( $require['title'] ) ), 'error' );
                $passed = false;
            }

            if ( $require['min_length'] && strlen( $value ) < (int) $require['min_length'] ) {
                wc_add_notice( sprintf( __( '%s alanı en az %d karakter olmalıdır.', 'inovapin-woo-sync' ), esc_html( $require['title'] ), (int) $require['min_length'] ), 'error' );
                $passed = false;
            }

            if ( $require['max_length'] && strlen( $value ) > (int) $require['max_length'] ) {
                wc_add_notice( sprintf( __( '%s alanı en fazla %d karakter olabilir.', 'inovapin-woo-sync' ), esc_html( $require['title'] ), (int) $require['max_length'] ), 'error' );
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Add cart item data.
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        $requirements = $this->get_requirements( $product_id );
        if ( empty( $requirements ) ) {
            return $cart_item_data;
        }

        $data = [];
        foreach ( $requirements as $require ) {
            $field_id = 'inovapin_require_' . $require['identifier'];
            $value    = isset( $_POST[ $field_id ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_id ] ) ) : '';
            $data[]   = [
                'productRequireID' => $require['productRequireID'],
                'identifier'       => $require['identifier'],
                'title'            => $require['title'],
                'value'            => $value,
            ];
        }

        $cart_item_data['inovapin_require'] = $data;
        return $cart_item_data;
    }

    /**
     * Add order item meta.
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['inovapin_require'] ) ) {
            return;
        }

        foreach ( $values['inovapin_require'] as $require ) {
            $item->add_meta_data( $require['title'], $require['value'], true );
        }

        $item->add_meta_data( '_inovapin_require', wp_json_encode( $values['inovapin_require'] ), true );
    }

    /**
     * Get requirements meta.
     */
    protected function get_requirements( $product_id ) {
        $raw = get_post_meta( $product_id, '_inovapin_requirements', true );
        $requirements = $raw ? json_decode( $raw, true ) : [];

        if ( empty( $requirements ) ) {
            return [];
        }

        return array_map( static function ( $require ) {
            return [
                'productRequireID' => isset( $require['productRequireID'] ) ? (int) $require['productRequireID'] : 0,
                'identifier'       => isset( $require['identifier'] ) ? sanitize_key( $require['identifier'] ) : '',
                'title'            => isset( $require['title'] ) ? sanitize_text_field( $require['title'] ) : '',
                'type'             => isset( $require['type'] ) ? sanitize_key( $require['type'] ) : 'text',
                'min_length'       => isset( $require['min_length'] ) ? (int) $require['min_length'] : 0,
                'max_length'       => isset( $require['max_length'] ) ? (int) $require['max_length'] : 0,
                'placeholder'      => isset( $require['placeholder'] ) ? sanitize_text_field( $require['placeholder'] ) : '',
                'options'          => isset( $require['options'] ) ? (array) $require['options'] : [],
                'is_required'      => ! empty( $require['is_required'] ),
                'tooltip'          => isset( $require['tooltip'] ) ? sanitize_text_field( $require['tooltip'] ) : '',
            ];
        }, $requirements );
    }
}
