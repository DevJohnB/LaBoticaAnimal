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
        $params   = $request->get_json_params();
        $username = sanitize_user( $params['username'] ?? '' );
        $password = sanitize_text_field( $params['password'] ?? '' );
        $email    = sanitize_email( $params['email'] ?? '' );

        $user = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user ) ) {
            return $user;
        }
        return [ 'success' => true, 'user_id' => $user ];
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
