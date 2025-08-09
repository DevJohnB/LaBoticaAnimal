<?php
/**
 * Plugin Name: La Botica App Bridge
 * Description: REST endpoints for user registration and authentication, enabling mobile apps to use WordPress as backend.
 * Version: 1.0.0
 * Author: La Botica
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LaBotica_App_Bridge {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_filter( 'rest_authentication_errors', [ $this, 'authenticate_request' ] );
    }

    /**
     * Register custom REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'labotica/v1', '/register', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_register' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'labotica/v1', '/login', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_login' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'labotica/v1', '/validate-token', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_validate_token' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle user registration.
     */
    public function handle_register( WP_REST_Request $request ) {
        $username = sanitize_user( $request->get_param( 'username' ) );
        $email    = sanitize_email( $request->get_param( 'email' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
            return new WP_Error( 'missing_fields', __( 'Username, email and password are required.', 'labotica' ), [ 'status' => 400 ] );
        }

        if ( username_exists( $username ) || email_exists( $email ) ) {
            return new WP_Error( 'user_exists', __( 'User already exists.', 'labotica' ), [ 'status' => 409 ] );
        }

        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        return [
            'success' => true,
            'user_id' => $user_id,
        ];
    }

    /**
     * Handle user login and token generation.
     */
    public function handle_login( WP_REST_Request $request ) {
        $username = sanitize_user( $request->get_param( 'username' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $username ) || empty( $password ) ) {
            return new WP_Error( 'missing_fields', __( 'Username and password are required.', 'labotica' ), [ 'status' => 400 ] );
        }

        $user = wp_authenticate( $username, $password );
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        $token = $this->generate_token( $user->ID );

        return [
            'token'   => $token,
            'user_id' => $user->ID,
        ];
    }

    /**
     * Validate authentication token and return user info.
     */
    public function handle_validate_token( WP_REST_Request $request ) {
        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\s(\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'missing_token', __( 'Authorization header not found.', 'labotica' ), [ 'status' => 401 ] );
        }

        $payload = $this->decode_token( $matches[1] );
        if ( ! $payload || $payload['exp'] < time() ) {
            return new WP_Error( 'invalid_token', __( 'Token is invalid or expired.', 'labotica' ), [ 'status' => 401 ] );
        }

        $user = get_userdata( $payload['sub'] );
        if ( ! $user ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'labotica' ), [ 'status' => 401 ] );
        }

        return [
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
        ];
    }

    /**
     * Authenticate request for protected routes.
     */
    public function authenticate_request( $result ) {
        if ( ! empty( $result ) ) {
            return $result;
        }

        // Only secure our namespace routes.
        $route = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( strpos( $route, '/wp-json/labotica/v1/' ) === false ) {
            return $result;
        }

        // Allow unauthenticated access to login and register endpoints.
        if ( false !== strpos( $route, '/wp-json/labotica/v1/login' ) || false !== strpos( $route, '/wp-json/labotica/v1/register' ) ) {
            return $result;
        }

        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\s(\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'labotica' ), [ 'status' => 401 ] );
        }

        $payload = $this->decode_token( $matches[1] );
        if ( ! $payload || $payload['exp'] < time() ) {
            return new WP_Error( 'rest_forbidden', __( 'Token is invalid or expired.', 'labotica' ), [ 'status' => 401 ] );
        }

        wp_set_current_user( $payload['sub'] );

        return $result;
    }

    /**
     * Generate a simple JWT-like token.
     */
    protected function generate_token( $user_id ) {
        $secret  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'change_this_secret_key';
        $header  = [ 'alg' => 'HS256', 'typ' => 'JWT' ];
        $payload = [
            'sub' => $user_id,
            'iat' => time(),
            'exp' => time() + DAY_IN_SECONDS,
        ];

        $segments      = [];
        $segments[]    = $this->urlsafe_b64encode( wp_json_encode( $header ) );
        $segments[]    = $this->urlsafe_b64encode( wp_json_encode( $payload ) );
        $signing_input = implode( '.', $segments );
        $signature     = hash_hmac( 'sha256', $signing_input, $secret, true );
        $segments[]    = $this->urlsafe_b64encode( $signature );

        return implode( '.', $segments );
    }

    /**
     * Decode and verify token.
     */
    protected function decode_token( $token ) {
        $parts = explode( '.', $token );
        if ( 3 !== count( $parts ) ) {
            return false;
        }

        list( $header64, $payload64, $sig64 ) = $parts;
        $secret   = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'change_this_secret_key';
        $signature = $this->urlsafe_b64decode( $sig64 );
        $valid_sig = hash_hmac( 'sha256', $header64 . '.' . $payload64, $secret, true );

        if ( ! hash_equals( $valid_sig, $signature ) ) {
            return false;
        }

        $payload = json_decode( $this->urlsafe_b64decode( $payload64 ), true );

        return $payload;
    }

    /**
     * URL-safe base64 encode.
     */
    protected function urlsafe_b64encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * URL-safe base64 decode.
     */
    protected function urlsafe_b64decode( $b64 ) {
        $b64 = strtr( $b64, '-_', '+/' );
        return base64_decode( $b64 . str_repeat( '=', ( 4 - strlen( $b64 ) % 4 ) % 4 ) );
    }

    /**
     * Retrieve Authorization header.
     */
    protected function get_authorization_header() {
        if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
        }

        if ( function_exists( 'apache_request_headers' ) ) {
            $headers = apache_request_headers();
            if ( isset( $headers['Authorization'] ) ) {
                return sanitize_text_field( $headers['Authorization'] );
            }
        }

        return '';
    }
}

new LaBotica_App_Bridge();
