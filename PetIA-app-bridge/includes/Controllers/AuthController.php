<?php
namespace PetIA\Controllers;

use PetIA\Token_Manager;

class AuthController {
    private $token_manager;
    private $decoded_token;

    public function __construct( Token_Manager $token_manager ) {
        $this->token_manager = $token_manager;
    }

    public function register_routes() {
        $namespace = 'petia-app-bridge/v1';
        register_rest_route( $namespace, '/login', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_login' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/logout', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_logout' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/validate-token', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_validate_token' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function authenticate_requests( $result ) {
        if ( ! empty( $result ) ) {
            return $result;
        }
        $token = $this->token_manager->get_authorization_header();
        if ( ! $token ) {
            return $result;
        }
        try {
            $this->decoded_token = $this->decoded_token ?: $this->token_manager->decode_token( $token );
            $user_id             = (int) $this->decoded_token->data->user_id;
            if ( ! get_user_by( 'id', $user_id ) ) {
                error_log( 'App Bridge authentication failed: User not found' );
                return new \WP_Error( 'invalid_user', 'User not found', [ 'status' => 401 ] );
            }
            if ( $this->token_manager->is_token_revoked( $this->decoded_token->jti ) ) {
                error_log( 'App Bridge authentication failed: Token revoked' );
                return new \WP_Error( 'token_revoked', 'Token revoked', [ 'status' => 401 ] );
            }
            global $wpdb;
            $table  = $wpdb->prefix . 'petia_app_bridge_access';
            $access = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT allowed, start_date, end_date FROM $table WHERE user_id = %d",
                    $user_id
                )
            );
            $now = current_time( 'timestamp' );
            if (
                empty( $access ) ||
                (int) $access->allowed !== 1 ||
                ( $access->start_date && $now < strtotime( $access->start_date ) ) ||
                ( $access->end_date && $now > strtotime( $access->end_date ) )
            ) {
                error_log( 'App Bridge authentication failed: User not allowed' );
                return new \WP_Error( 'forbidden', 'User not allowed', [ 'status' => 403 ] );
            }
            wp_set_current_user( $user_id );

            return $result;
        } catch ( \Exception $e ) {
            error_log( 'App Bridge authentication failed: ' . $e->getMessage() );
            return new \WP_Error( 'invalid_token', $e->getMessage(), [ 'status' => 401 ] );
        }
    }

    public function determine_current_user( $user_id ) {
        if ( $user_id ) {
            return $user_id;
        }
        $token = $this->token_manager->get_authorization_header();
        if ( ! $token ) {
            return $user_id;
        }
        try {
            $this->decoded_token = $this->token_manager->decode_token( $token );
            $user_id             = (int) $this->decoded_token->data->user_id;
            if ( get_user_by( 'id', $user_id ) ) {
                wp_set_current_user( $user_id );
                return $user_id;
            }
            return 0;
        } catch ( \Exception $e ) {
            return $user_id;
        }
    }

    public function handle_login( \WP_REST_Request $request ) {
        $email    = sanitize_email( $request['email'] ?? '' );
        $username = sanitize_user( $request['username'] ?? '' );
        $password = sanitize_text_field( $request['password'] ?? '' );

        if ( ! $email && ! $username ) {
            return new \WP_Error( 'missing_email', 'Email is required', [ 'status' => 400 ] );
        }
        if ( ! $password ) {
            return new \WP_Error( 'missing_password', 'Password is required', [ 'status' => 400 ] );
        }

        $user = null;
        if ( $email ) {
            $user = get_user_by( 'email', $email );
        }
        if ( ! $user && $username ) {
            $user = get_user_by( 'login', $username );
        }
        if ( ! $user ) {
            return new \WP_Error( 'invalid_username', 'Invalid email or username', [ 'status' => 401 ] );
        }

        $creds = [
            'user_login'    => $user->user_login,
            'user_password' => $password,
            'remember'      => true,
        ];
        $user = wp_signon( $creds );
        if ( is_wp_error( $user ) ) {
            return $user;
        }
        $token       = $this->token_manager->generate_token( $user->ID );
        $wp_response = rest_ensure_response( [ 'success' => true ] );
        $wp_response->header( 'Authorization', 'Bearer ' . $token );
        return $wp_response;
    }

    public function handle_logout( \WP_REST_Request $request ) {
        $token = $this->token_manager->get_authorization_header();
        if ( $token ) {
            try {
                $decoded = $this->token_manager->decode_token( $token );
                $this->token_manager->revoke_token( $decoded->jti );
            } catch ( \Exception $e ) {
                // ignore
            }
        }
        return [ 'success' => true ];
    }

    public function handle_validate_token( \WP_REST_Request $request ) {
        $token = $this->token_manager->get_authorization_header();
        if ( ! $token ) {
            return new \WP_Error( 'no_token', 'No token provided', [ 'status' => 401 ] );
        }
        try {
            $decoded  = $this->token_manager->decode_token( $token );
            $user_id  = (int) $decoded->data->user_id;
            global $wpdb;
            $table  = $wpdb->prefix . 'petia_app_bridge_access';
            $access = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT allowed, start_date, end_date FROM $table WHERE user_id = %d",
                    $user_id
                )
            );
            $now = current_time( 'timestamp' );
            if (
                empty( $access ) ||
                (int) $access->allowed !== 1 ||
                ( $access->start_date && $now < strtotime( $access->start_date ) ) ||
                ( $access->end_date && $now > strtotime( $access->end_date ) )
            ) {
                return new \WP_Error( 'forbidden', 'User not allowed', [ 'status' => 403 ] );
            }

            return [ 'valid' => true, 'user_id' => $user_id ];
        } catch ( \Exception $e ) {
            return new \WP_Error( 'invalid_token', $e->getMessage(), [ 'status' => 401 ] );
        }
    }
}
