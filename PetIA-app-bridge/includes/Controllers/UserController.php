<?php
namespace PetIA\Controllers;

use PetIA\Token_Manager;

class UserController {
    private $token_manager;

    public function __construct( Token_Manager $token_manager ) {
        $this->token_manager = $token_manager;
    }

    public function register_routes() {
        $namespace = 'petia-app-bridge/v1';
        register_rest_route( $namespace, '/register', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_register' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/password-reset-request', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_password_reset_request' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/password-reset', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_password_reset' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/profile', [
            'methods'  => 'GET',
            'callback' => [ $this, 'handle_profile_get' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/profile', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_profile_post' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_register( \WP_REST_Request $request ) {
        $params      = $request->get_json_params();
        $username    = sanitize_user( $params['username'] ?? '' );
        $password    = wp_unslash( (string) ( $params['password'] ?? '' ) );
        $email       = sanitize_email( $params['email'] ?? '' );
        $first_name  = sanitize_text_field( $params['first_name'] ?? '' );
        $last_name   = sanitize_text_field( $params['last_name'] ?? '' );

        if ( '' === $username ) {
            return new \WP_Error( 'missing_username', 'Username is required', [ 'status' => 400 ] );
        }

        if ( ! validate_username( $username ) ) {
            return new \WP_Error( 'invalid_username', 'Invalid username', [ 'status' => 400 ] );
        }

        if ( '' === $email ) {
            return new \WP_Error( 'missing_email', 'Email is required', [ 'status' => 400 ] );
        }

        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', 'Invalid email address', [ 'status' => 400 ] );
        }

        if ( '' === $password ) {
            return new \WP_Error( 'missing_password', 'Password is required', [ 'status' => 400 ] );
        }

        if ( strlen( $password ) < 8 ) {
            return new \WP_Error( 'weak_password', 'Password must be at least 8 characters long', [ 'status' => 400 ] );
        }

        if ( username_exists( $username ) ) {
            return new \WP_Error( 'username_exists', 'Username already exists', [ 'status' => 409 ] );
        }

        if ( email_exists( $email ) ) {
            return new \WP_Error( 'email_exists', 'Email already registered', [ 'status' => 409 ] );
        }

        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $user_update = [ 'ID' => $user_id ];
        if ( $first_name ) {
            $user_update['first_name'] = $first_name;
        }
        if ( $last_name ) {
            $user_update['last_name'] = $last_name;
        }

        if ( count( $user_update ) > 1 ) {
            $updated = wp_update_user( $user_update );
            if ( is_wp_error( $updated ) ) {
                return $updated;
            }
        }

        $token = null;
        try {
            $token = $this->token_manager->generate_token( $user_id );
        } catch ( \Throwable $e ) {
            // If token generation fails, still return a success response without the token.
        }

        $response_data = [
            'success' => true,
            'message' => 'User registered successfully',
            'user_id' => $user_id,
        ];

        if ( $token ) {
            $response_data['token'] = $token;
        }

        $response = rest_ensure_response( $response_data );

        if ( $token ) {
            $response->header( 'Authorization', 'Bearer ' . $token );
        }

        return $response;
    }

    public function handle_password_reset_request( \WP_REST_Request $request ) {
        $email = $request['email'];
        $user  = get_user_by( 'email', $email );
        if ( ! $user ) {
            return new \WP_Error( 'invalid_email', 'Email not found', [ 'status' => 400 ] );
        }
        retrieve_password( $user->user_login );
        return [ 'success' => true ];
    }

    public function handle_password_reset( \WP_REST_Request $request ) {
        $login    = $request['login'];
        $key      = $request['key'];
        $password = $request['password'];
        $user     = check_password_reset_key( $key, $login );
        if ( is_wp_error( $user ) ) {
            return $user;
        }
        reset_password( $user, $password );
        return [ 'success' => true ];
    }

    public function handle_profile_get( \WP_REST_Request $request ) {
    error_log('Authorization header: ' . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'N/A'));

    $token = $this->token_manager->get_authorization_header();
    if ( ! $token ) {
        return new \WP_Error('no_token', 'Header Authorization ausente', ['status' => 401]);
    }

    try {
        $decoded = $this->token_manager->decode_token( $token );
        $user_id = (int) $decoded->data->user_id;
        $user = wp_set_current_user( $user_id );
    } catch ( \Exception $e ) {
        return new \WP_Error('invalid_token', $e->getMessage(), ['status' => 401]);
    }
        if ( 0 === $user->ID ) {
            return new \WP_Error(
                'authentication_required',
                'Authentication required',
                [ 'status' => 401 ]
            );
        }
        return [
            'id'              => $user->ID,
            'username'        => $user->user_login,
            'display_name'    => $user->display_name,
            'email'           => $user->user_email,
            'first_name'      => $user->first_name,
            'last_name'       => $user->last_name,
            'billing_address' => $this->get_user_address( $user->ID, 'billing' ),
            'shipping_address'=> $this->get_user_address( $user->ID, 'shipping' ),
        ];
    }

    public function handle_profile_post( \WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
            return new \WP_Error( 'rest_forbidden', 'Cannot update user.', [ 'status' => rest_authorization_required_code() ] );
        }

        $params           = $request->get_json_params();
        $first_name       = sanitize_text_field( $params['first_name'] ?? '' );
        $last_name        = sanitize_text_field( $params['last_name'] ?? '' );
        $email            = sanitize_email( $params['email'] ?? '' );
        $billing_address  = $params['billing_address'] ?? [];
        $shipping_address = $params['shipping_address'] ?? [];

        $result = wp_update_user( [
            'ID'         => $user_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'user_email' => $email,
        ] );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $this->update_user_address( $user_id, 'billing', $billing_address );
        $this->update_user_address( $user_id, 'shipping', $shipping_address );

        return [ 'success' => true ];
    }

    private function get_user_address( $user_id, $type ) {
        $fields = [
            'first_name',
            'last_name',
            'company',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode',
            'country',
            'email',
            'phone',
        ];

        $address = [];
        if ( class_exists( '\\WC_Customer' ) ) {
            $customer = new \WC_Customer( $user_id );
            foreach ( $fields as $field ) {
                $getter = "get_{$type}_{$field}";
                if ( is_callable( [ $customer, $getter ] ) ) {
                    $address[ $field ] = $customer->$getter();
                }
            }
        } else {
            foreach ( $fields as $field ) {
                $address[ $field ] = get_user_meta( $user_id, "{$type}_{$field}", true );
            }
        }

        return $address;
    }

    private function update_user_address( $user_id, $type, $data ) {
        $fields = [
            'first_name',
            'last_name',
            'company',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode',
            'country',
            'email',
            'phone',
        ];

        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $value = 'email' === $field ? sanitize_email( $data[ $field ] ) : sanitize_text_field( $data[ $field ] );
                update_user_meta( $user_id, "{$type}_{$field}", $value );
            }
        }
    }
}
