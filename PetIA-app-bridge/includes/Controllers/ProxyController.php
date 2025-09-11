<?php
namespace PetIA\Controllers;

class ProxyController {
    public function register_routes() {
        $namespace = 'petia-app-bridge/v1';
        register_rest_route( $namespace, '/wc-store/(?P<endpoint>.*)', [
            'methods'  => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ],
            'callback' => [ $this, 'handle_wc_store_proxy' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/wc/(?P<endpoint>.*)', [
            'methods'  => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ],
            'callback' => [ $this, 'handle_wc_proxy' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_wc_store_proxy( \WP_REST_Request $request ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new \WP_Error( 'woocommerce_missing', 'WooCommerce not available', [ 'status' => 501 ] );
        }
        $endpoint = ltrim( $request['endpoint'], '/' );
        $method   = $request->get_method();
        $proxy_request = new \WP_REST_Request( $method, '/wc/store/' . $endpoint );
        $proxy_request->set_query_params( $request->get_query_params() );
        foreach ( $request->get_headers() as $name => $values ) {
            if ( 'host' === strtolower( $name ) ) {
                continue;
            }
            foreach ( (array) $values as $value ) {
                $proxy_request->set_header( $name, $value );
            }
        }
        if ( in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $proxy_request->set_body( $request->get_body() );
        }
        $response = rest_do_request( $proxy_request );
        return $response;
    }

    public function handle_wc_proxy( \WP_REST_Request $request ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new \WP_Error( 'woocommerce_missing', 'WooCommerce not available', [ 'status' => 501 ] );
        }
        $endpoint = ltrim( $request['endpoint'], '/' );
        $method   = $request->get_method();
        $proxy_request = new \WP_REST_Request( $method, '/wc/v3/' . $endpoint );
        $proxy_request->set_query_params( $request->get_query_params() );
        foreach ( $request->get_headers() as $name => $values ) {
            if ( 'host' === strtolower( $name ) ) {
                continue;
            }
            foreach ( (array) $values as $value ) {
                $proxy_request->set_header( $name, $value );
            }
        }
        if ( in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $proxy_request->set_body( $request->get_body() );
        }
        $response = rest_do_request( $proxy_request );
        return $response;
    }
}
