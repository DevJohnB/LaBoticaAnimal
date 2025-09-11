<?php
namespace PetIA\Controllers;

class OrderController {
    public function register_routes() {
        $namespace = 'petia-app-bridge/v1';
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
}
