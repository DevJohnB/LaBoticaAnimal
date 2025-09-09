<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PetIA_CORS {
    public function add_cors_support() {
        remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
        add_filter( 'rest_pre_serve_request', function( $value ) {
            $origin  = $this->get_request_origin();
            $allowed = $this->is_origin_allowed( $origin );

            if ( $origin && $allowed ) {
                $this->send_cors_headers( $origin );
            }

            if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
                status_header( $allowed ? 200 : 403 );
                return true;
            }

            if ( $origin && ! $allowed ) {
                status_header( 403 );
            }

            return $value;
        }, 10, 3 );
    }

    public function get_request_origin() {
        $origin = '';
        if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
            $origin = trim( $_SERVER['HTTP_ORIGIN'] );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_ORIGIN'] ) ) {
            $origin = trim( $_SERVER['HTTP_X_FORWARDED_ORIGIN'] );
        }
        return $origin;
    }

    public function is_origin_allowed( $origin ) {
        $allowed = defined( 'PETIA_ALLOWED_ORIGINS' ) ? array_map( 'trim', explode( ',', PETIA_ALLOWED_ORIGINS ) ) : [ get_site_url() ];
        return in_array( $origin, $allowed, true );
    }

    protected function send_cors_headers( $origin ) {
        header( "Access-Control-Allow-Origin: {$origin}" );
        header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
        header( 'Access-Control-Allow-Credentials: true' );
    }
}
