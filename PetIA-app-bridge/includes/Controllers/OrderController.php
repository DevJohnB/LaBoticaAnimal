<?php
namespace PetIA\Controllers;

class OrderController {
    public function register_routes() {
        $namespace = 'petia-app-bridge/v1';
        register_rest_route( $namespace, '/payment-methods', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_payment_methods' ],
            'permission_callback' => '__return_true',
            'args' => [
                'cart_key' => [
                    'required' => false,
                ],
            ],
        ] );
        register_rest_route( $namespace, '/checkout', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_checkout' ],
            'permission_callback' => '__return_true',
            'args' => [
                'cart_key' => [
                    'required' => false,
                ],
            ],
        ] );
        register_rest_route( $namespace, '/order/(?P<id>\d+)/addresses', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_order_addresses_get' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/order/(?P<id>\d+)/addresses', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_order_addresses_post' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_order_addresses_get( \WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new \WP_Error( 'woocommerce_missing', 'WooCommerce not available', [ 'status' => 501 ] );
        }
        $order = wc_get_order( (int) $request['id'] );
        if ( ! $order ) {
            return new \WP_Error( 'not_found', 'Order not found', [ 'status' => 404 ] );
        }
        return [
            'billing'  => $order->get_address( 'billing' ),
            'shipping' => $order->get_address( 'shipping' ),
        ];
    }

    public function handle_order_addresses_post( \WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new \WP_Error( 'woocommerce_missing', 'WooCommerce not available', [ 'status' => 501 ] );
        }
        $order = wc_get_order( (int) $request['id'] );
        if ( ! $order ) {
            return new \WP_Error( 'not_found', 'Order not found', [ 'status' => 404 ] );
        }
        $params = $request->get_json_params();
        if ( isset( $params['billing'] ) && is_array( $params['billing'] ) ) {
            $order->set_address( $params['billing'], 'billing' );
        }
        if ( isset( $params['shipping'] ) && is_array( $params['shipping'] ) ) {
            $order->set_address( $params['shipping'], 'shipping' );
        }
        $order->save();
        return [
            'billing'  => $order->get_address( 'billing' ),
            'shipping' => $order->get_address( 'shipping' ),
        ];
    }

    private function maybe_load_cart( $cart_key ) {
        if ( empty( $cart_key ) || is_user_logged_in() || ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        $request = new \WP_REST_Request( 'GET', '/wc/store/cart' );
        $request->set_query_params( [ 'cart_key' => $cart_key ] );
        rest_do_request( $request );
    }

    private function proxy_store_request( $method, $endpoint, $query = [], $body = null ) {
        $request = new \WP_REST_Request( $method, '/wc/store/' . ltrim( $endpoint, '/' ) );
        if ( ! empty( $query ) ) {
            $request->set_query_params( $query );
        }
        if ( null !== $body ) {
            $request->set_header( 'Content-Type', 'application/json' );
            $request->set_body( wp_json_encode( $body ) );
        }
        return rest_do_request( $request );
    }

    public function handle_payment_methods( \WP_REST_Request $request ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new \WP_Error( 'woocommerce_missing', 'WooCommerce not available', [ 'status' => 501 ] );
        }
        $this->maybe_load_cart( $request->get_param( 'cart_key' ) );
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        return $gateways;
    }

    public function handle_checkout( \WP_REST_Request $request ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new \WP_Error( 'woocommerce_missing', 'WooCommerce not available', [ 'status' => 501 ] );
        }
        $cart_key = $request->get_param( 'cart_key' );
        $this->maybe_load_cart( $cart_key );

        $params = $request->get_json_params();

        $query = [];
        if ( $cart_key ) {
            $query['cart_key'] = $cart_key;
        }
        if ( isset( $params['cart'] ) && is_array( $params['cart'] ) ) {
            $this->proxy_store_request( 'DELETE', 'cart/items', $query );
            foreach ( $params['cart'] as $item ) {
                $this->proxy_store_request( 'POST', 'cart/add-item', $query, $item );
            }
        }

        WC()->cart->calculate_totals();

        $order_id = WC()->checkout()->create_order( [
            'billing'        => $params['billing'] ?? [],
            'shipping'       => $params['shipping'] ?? [],
            'payment_method' => $params['payment_method'] ?? '',
        ] );

        if ( is_wp_error( $order_id ) ) {
            return $order_id;
        }

        $order = wc_get_order( $order_id );
        $order->calculate_totals();

        $data = [
            'id'     => $order_id,
            'status' => $order->get_status(),
        ];
        if ( $order->needs_payment() ) {
            $data['payment_url'] = $order->get_checkout_payment_url();
        }

        return $data;
    }
}
