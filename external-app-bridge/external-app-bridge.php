<?php
/**
 * Plugin Name: External App Bridge
 * Description: REST endpoints for user registration and authentication, enabling mobile apps to use WordPress as backend.
 * Version: 1.0.0
 * Author: La Botica
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class External_App_Bridge {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_filter( 'rest_authentication_errors', [ $this, 'authenticate_request' ] );
    }

    /**
     * Register custom REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'external-app-bridge/v1', '/register', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_register' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'external-app-bridge/v1', '/login', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_login' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'external-app-bridge/v1', '/logout', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_logout' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'external-app-bridge/v1', '/validate-token', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_validate_token' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'external-app-bridge/v1', '/password-reset-request', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_password_reset_request' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'external-app-bridge/v1', '/password-reset', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_password_reset' ],
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
            return new WP_Error( 'missing_fields', __( 'Username, email and password are required.', 'external-app-bridge' ), [ 'status' => 400 ] );
        }

        if ( username_exists( $username ) || email_exists( $email ) ) {
            return new WP_Error( 'user_exists', __( 'User already exists.', 'external-app-bridge' ), [ 'status' => 409 ] );
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
            return new WP_Error( 'missing_fields', __( 'Username and password are required.', 'external-app-bridge' ), [ 'status' => 400 ] );
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
     * Revoke authentication token.
     */
    public function handle_logout( WP_REST_Request $request ) {
        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'missing_token', __( 'Authorization header not found.', 'external-app-bridge' ), [ 'status' => 401 ] );
        }

        $token   = $matches[1];
        $payload = $this->decode_token( $token );
        if ( ! $payload ) {
            return new WP_Error( 'invalid_token', __( 'Token is invalid.', 'external-app-bridge' ), [ 'status' => 401 ] );
        }

        $ttl = max( 1, $payload['exp'] - time() );
        set_transient( 'external_app_bridge_revoked_' . md5( $token ), true, $ttl );

        return [ 'success' => true ];
    }

    /**
     * Validate authentication token and return user info.
     */
    public function handle_validate_token( WP_REST_Request $request ) {
        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'missing_token', __( 'Authorization header not found.', 'external-app-bridge' ), [ 'status' => 401 ] );
        }

        if ( $this->is_token_revoked( $matches[1] ) ) {
            return new WP_Error( 'invalid_token', __( 'Token has been revoked.', 'external-app-bridge' ), [ 'status' => 401 ] );
        }

        $payload = $this->decode_token( $matches[1] );
        if ( ! $payload || $payload['exp'] < time() ) {
            return new WP_Error( 'invalid_token', __( 'Token is invalid or expired.', 'external-app-bridge' ), [ 'status' => 401 ] );
        }

        $user = get_userdata( $payload['sub'] );
        if ( ! $user ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'external-app-bridge' ), [ 'status' => 401 ] );
        }

        return [
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
        ];
    }

    /**
     * Send password reset email to user.
     */
    public function handle_password_reset_request( WP_REST_Request $request ) {
        $username = sanitize_user( $request->get_param( 'username' ) );
        $email    = sanitize_email( $request->get_param( 'email' ) );

        if ( empty( $username ) && empty( $email ) ) {
            return new WP_Error( 'missing_fields', __( 'Username or email is required.', 'external-app-bridge' ), [ 'status' => 400 ] );
        }

        $user = null;
        if ( ! empty( $username ) ) {
            $user = get_user_by( 'login', $username );
        } elseif ( ! empty( $email ) ) {
            $user = get_user_by( 'email', $email );
        }

        if ( ! $user ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'external-app-bridge' ), [ 'status' => 404 ] );
        }

        $result = retrieve_password( $user->user_login );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [ 'success' => true ];
    }

    /**
     * Reset user password using key and login.
     */
    public function handle_password_reset( WP_REST_Request $request ) {
        $key      = sanitize_text_field( $request->get_param( 'key' ) );
        $login    = sanitize_user( $request->get_param( 'login' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $key ) || empty( $login ) || empty( $password ) ) {
            return new WP_Error( 'missing_fields', __( 'Key, login and new password are required.', 'external-app-bridge' ), [ 'status' => 400 ] );
        }

        $user = check_password_reset_key( $key, $login );
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        reset_password( $user, $password );

        return [ 'success' => true ];
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
        if ( strpos( $route, '/wp-json/external-app-bridge/v1/' ) === false ) {
            return $result;
        }

        // Allow unauthenticated access to login, register and password reset endpoints.
        if (
            false !== strpos( $route, '/wp-json/external-app-bridge/v1/login' ) ||
            false !== strpos( $route, '/wp-json/external-app-bridge/v1/register' ) ||
            false !== strpos( $route, '/wp-json/external-app-bridge/v1/password-reset' ) ||
            false !== strpos( $route, '/wp-json/external-app-bridge/v1/password-reset-request' )
        ) {
            return $result;
        }

        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'external-app-bridge' ), [ 'status' => 401 ] );
        }

        $token = $matches[1];
        if ( $this->is_token_revoked( $token ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Token has been revoked.', 'external-app-bridge' ), [ 'status' => 401 ] );
        }

        $payload = $this->decode_token( $token );
        if ( ! $payload || $payload['exp'] < time() ) {
            return new WP_Error( 'rest_forbidden', __( 'Token is invalid or expired.', 'external-app-bridge' ), [ 'status' => 401 ] );
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

    /**
     * Check if token has been revoked.
     */
    protected function is_token_revoked( $token ) {
        return (bool) get_transient( 'external_app_bridge_revoked_' . md5( $token ) );
    }
}

new External_App_Bridge();

