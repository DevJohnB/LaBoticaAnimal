<?php
/**
 * Plugin Name: PetIA External Bridge
 * Description: REST endpoints for user registration and authentication, enabling mobile apps to use WordPress as backend.
 * Version: 1.0.0
 * Author: La Botica
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PetIA_External_Bridge {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_filter( 'rest_authentication_errors', [ $this, 'authenticate_request' ] );

        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        }
    }

    public static function activate() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'petia_external_bridge_access';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE $table_name ( user_id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY, allowed TINYINT(1) NOT NULL DEFAULT 1 ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Register custom REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'petia-external-bridge/v1', '/register', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_register' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-external-bridge/v1', '/login', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_login' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-external-bridge/v1', '/logout', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_logout' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-external-bridge/v1', '/validate-token', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_validate_token' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-external-bridge/v1', '/password-reset-request', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_password_reset_request' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-external-bridge/v1', '/password-reset', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_password_reset' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-external-bridge/v1', '/profile', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_profile' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-external-bridge/v1', '/profile', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_update_profile' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-external-bridge/v1', '/order/(?P<id>\d+)/addresses', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_order_addresses' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-external-bridge/v1', '/order/(?P<id>\d+)/addresses', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_update_order_addresses' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle user registration.
     */
    public function handle_register( WP_REST_Request $request ) {
        $email    = sanitize_email( $request->get_param( 'email' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $email ) || empty( $password ) ) {
            return new WP_Error( 'missing_fields', __( 'Email and password are required.', 'petia-external-bridge' ), [ 'status' => 400 ] );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'user_exists', __( 'User already exists.', 'petia-external-bridge' ), [ 'status' => 409 ] );
        }

        $base_username = sanitize_user( current( explode( '@', $email ) ) );
        if ( empty( $base_username ) ) {
            $base_username = 'user';
        }
        $username = $base_username;
        $i        = 1;
        while ( username_exists( $username ) ) {
            $username = $base_username . '_' . $i;
            $i++;
        }

        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        return [
            'success'  => true,
            'user_id'  => $user_id,
            'username' => $username,
        ];
    }

    /**
     * Handle user login and token generation.
     */
    public function handle_login( WP_REST_Request $request ) {
        $username = sanitize_user( $request->get_param( 'username' ) );
        $email    = sanitize_email( $request->get_param( 'email' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $password ) || ( empty( $username ) && empty( $email ) ) ) {
            return new WP_Error( 'missing_fields', __( 'Email or username and password are required.', 'petia-external-bridge' ), [ 'status' => 400 ] );
        }

        if ( ! empty( $email ) ) {
            $user_obj = get_user_by( 'email', $email );
            if ( ! $user_obj ) {
                return new WP_Error( 'invalid_credentials', __( 'Invalid email or password.', 'petia-external-bridge' ), [ 'status' => 401 ] );
            }
            $username = $user_obj->user_login;
        }

        $user = wp_authenticate( $username, $password );
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        if ( ! $this->user_has_access( $user->ID ) ) {
            return new WP_Error( 'access_denied', __( 'User access to API is disabled.', 'petia-external-bridge' ), [ 'status' => 403 ] );
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
            return new WP_Error( 'missing_token', __( 'Authorization header not found.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        $token   = $matches[1];
        $payload = $this->decode_token( $token );
        if ( ! $payload ) {
            return new WP_Error( 'invalid_token', __( 'Token is invalid.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        $ttl = max( 1, $payload['exp'] - time() );
        set_transient( 'petia_external_bridge_revoked_' . md5( $token ), true, $ttl );

        return [ 'success' => true ];
    }

    /**
     * Validate authentication token and return user info.
     */
    public function handle_validate_token( WP_REST_Request $request ) {
        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'missing_token', __( 'Authorization header not found.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        if ( $this->is_token_revoked( $matches[1] ) ) {
            return new WP_Error( 'invalid_token', __( 'Token has been revoked.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        $payload = $this->decode_token( $matches[1] );
        if ( ! $payload || $payload['exp'] < time() ) {
            return new WP_Error( 'invalid_token', __( 'Token is invalid or expired.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        $user = get_userdata( $payload['sub'] );
        if ( ! $user ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        if ( ! $this->user_has_access( $user->ID ) ) {
            return new WP_Error( 'access_denied', __( 'User access to API is disabled.', 'petia-external-bridge' ), [ 'status' => 403 ] );
        }

        return [
            'user_id'   => $user->ID,
            'username'  => $user->user_login,
            'email'     => $user->user_email,
        ];
    }

    /**
     * Send password reset email to user.
     */
    public function handle_password_reset_request( WP_REST_Request $request ) {
        $username = sanitize_user( $request->get_param( 'username' ) );
        $email    = sanitize_email( $request->get_param( 'email' ) );

        if ( empty( $username ) && empty( $email ) ) {
            return new WP_Error( 'missing_fields', __( 'Username or email is required.', 'petia-external-bridge' ), [ 'status' => 400 ] );
        }

        $user = null;
        if ( ! empty( $username ) ) {
            $user = get_user_by( 'login', $username );
        } elseif ( ! empty( $email ) ) {
            $user = get_user_by( 'email', $email );
        }

        if ( ! $user ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'petia-external-bridge' ), [ 'status' => 404 ] );
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
            return new WP_Error( 'missing_fields', __( 'Key, login and new password are required.', 'petia-external-bridge' ), [ 'status' => 400 ] );
        }

        $user = check_password_reset_key( $key, $login );
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        reset_password( $user, $password );

        return [ 'success' => true ];
    }

    /**
     * Retrieve optional user profile fields.
     */
    public function handle_get_profile( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        $user = get_userdata( $user_id );

        return [
            'username'    => $user->user_login,
            'email'       => $user->user_email,
            'first_name'  => get_user_meta( $user_id, 'first_name', true ),
            'last_name'   => get_user_meta( $user_id, 'last_name', true ),
            'nickname'    => get_user_meta( $user_id, 'nickname', true ),
            'description' => get_user_meta( $user_id, 'description', true ),
            'user_url'    => $user->user_url,
            'display_name'=> $user->display_name,
        ];
    }

    /**
     * Update optional user profile fields.
     */
    public function handle_update_profile( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        $meta_fields = [ 'first_name', 'last_name', 'nickname', 'description' ];
        $updated     = false;

        foreach ( $meta_fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $updated = true;
                if ( 'description' === $field ) {
                    update_user_meta( $user_id, $field, sanitize_textarea_field( $value ) );
                } else {
                    update_user_meta( $user_id, $field, sanitize_text_field( $value ) );
                }
            }
        }

        $userdata = [ 'ID' => $user_id ];
        $user_url = $request->get_param( 'user_url' );
        if ( null !== $user_url ) {
            $updated             = true;
            $userdata['user_url'] = esc_url_raw( $user_url );
        }

        $display_name = $request->get_param( 'display_name' );
        if ( null !== $display_name ) {
            $updated                = true;
            $userdata['display_name'] = sanitize_text_field( $display_name );
        }

        if ( count( $userdata ) > 1 ) {
            $result = wp_update_user( $userdata );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        if ( ! $updated ) {
            return new WP_Error( 'no_fields', __( 'No profile fields provided.', 'petia-external-bridge' ), [ 'status' => 400 ] );
        }

        return [ 'success' => true ];
    }

    /**
     * Retrieve billing and shipping addresses for an order.
     */
    public function handle_get_order_addresses( WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new WP_Error( 'woocommerce_missing', __( 'WooCommerce not available.', 'petia-external-bridge' ), [ 'status' => 500 ] );
        }

        $order_id = absint( $request['id'] );
        $order    = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Order not found.', 'petia-external-bridge' ), [ 'status' => 404 ] );
        }

        $user_id = get_current_user_id();
        if ( $order->get_user_id() !== $user_id ) {
            return new WP_Error( 'forbidden', __( 'You are not allowed to access this order.', 'petia-external-bridge' ), [ 'status' => 403 ] );
        }

        return [
            'billing'  => $order->get_address( 'billing' ),
            'shipping' => $order->get_address( 'shipping' ),
        ];
    }

    /**
     * Update billing and shipping addresses for an order.
     */
    public function handle_update_order_addresses( WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new WP_Error( 'woocommerce_missing', __( 'WooCommerce not available.', 'petia-external-bridge' ), [ 'status' => 500 ] );
        }

        $order_id = absint( $request['id'] );
        $order    = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Order not found.', 'petia-external-bridge' ), [ 'status' => 404 ] );
        }

        $user_id = get_current_user_id();
        if ( $order->get_user_id() !== $user_id ) {
            return new WP_Error( 'forbidden', __( 'You are not allowed to update this order.', 'petia-external-bridge' ), [ 'status' => 403 ] );
        }

        $billing  = $request->get_param( 'billing' );
        $shipping = $request->get_param( 'shipping' );

        if ( empty( $billing ) && empty( $shipping ) ) {
            return new WP_Error( 'no_fields', __( 'No address fields provided.', 'petia-external-bridge' ), [ 'status' => 400 ] );
        }

        if ( ! empty( $billing ) && is_array( $billing ) ) {
            $clean_billing = function_exists( 'wc_clean' ) ? array_map( 'wc_clean', $billing ) : array_map( 'sanitize_text_field', $billing );
            $order->set_address( $clean_billing, 'billing' );
        }

        if ( ! empty( $shipping ) && is_array( $shipping ) ) {
            $clean_shipping = function_exists( 'wc_clean' ) ? array_map( 'wc_clean', $shipping ) : array_map( 'sanitize_text_field', $shipping );
            $order->set_address( $clean_shipping, 'shipping' );
        }

        $order->save();

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
        if ( strpos( $route, '/wp-json/petia-external-bridge/v1/' ) === false ) {
            return $result;
        }

        // Allow unauthenticated access to login, register and password reset endpoints.
        if (
            false !== strpos( $route, '/wp-json/petia-external-bridge/v1/login' ) ||
            false !== strpos( $route, '/wp-json/petia-external-bridge/v1/register' ) ||
            false !== strpos( $route, '/wp-json/petia-external-bridge/v1/password-reset' ) ||
            false !== strpos( $route, '/wp-json/petia-external-bridge/v1/password-reset-request' )
        ) {
            return $result;
        }

        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        $token = $matches[1];
        if ( $this->is_token_revoked( $token ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Token has been revoked.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        $payload = $this->decode_token( $token );
        if ( ! $payload || $payload['exp'] < time() ) {
            return new WP_Error( 'rest_forbidden', __( 'Token is invalid or expired.', 'petia-external-bridge' ), [ 'status' => 401 ] );
        }

        wp_set_current_user( $payload['sub'] );

        if ( ! $this->user_has_access( $payload['sub'] ) ) {
            return new WP_Error( 'rest_forbidden', __( 'User access to API is disabled.', 'petia-external-bridge' ), [ 'status' => 403 ] );
        }

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
        return (bool) get_transient( 'petia_external_bridge_revoked_' . md5( $token ) );
    }

    protected function user_has_access( $user_id ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'petia_external_bridge_access';
        $allowed = $wpdb->get_var( $wpdb->prepare( "SELECT allowed FROM $table WHERE user_id = %d", $user_id ) );
        if ( null === $allowed ) {
            return true;
        }
        return (bool) $allowed;
    }

    public function register_admin_page() {
        add_users_page(
            __( 'PetIA External Bridge Access', 'petia-external-bridge' ),
            __( 'PetIA Bridge Access', 'petia-external-bridge' ),
            'manage_options',
            'petia-external-bridge-access',
            [ $this, 'render_access_page' ]
        );
    }

    public function render_access_page() {
        if ( isset( $_POST['petia_access_nonce'] ) && wp_verify_nonce( $_POST['petia_access_nonce'], 'petia_save_access' ) ) {
            $users = get_users( [ 'fields' => [ 'ID' ] ] );
            global $wpdb;
            $table = $wpdb->prefix . 'petia_external_bridge_access';
            foreach ( $users as $user ) {
                $allowed = isset( $_POST['access'][ $user->ID ] ) ? 1 : 0;
                $wpdb->replace( $table, [ 'user_id' => $user->ID, 'allowed' => $allowed ], [ '%d', '%d' ] );
            }
            echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'petia-external-bridge' ) . '</p></div>';
        }

        $users = get_users();
        global $wpdb;
        $table = $wpdb->prefix . 'petia_external_bridge_access';

        echo '<div class="wrap"><h1>' . esc_html__( 'PetIA External Bridge Access', 'petia-external-bridge' ) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field( 'petia_save_access', 'petia_access_nonce' );
        echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'User', 'petia-external-bridge' ) . '</th><th>' . esc_html__( 'Allowed', 'petia-external-bridge' ) . '</th></tr></thead><tbody>';
        foreach ( $users as $user ) {
            $allowed = $wpdb->get_var( $wpdb->prepare( "SELECT allowed FROM $table WHERE user_id = %d", $user->ID ) );
            $checked = ( null === $allowed || $allowed ) ? 'checked' : '';
            echo '<tr><td>' . esc_html( $user->user_login ) . '</td><td><input type="checkbox" name="access[' . intval( $user->ID ) . ']" value="1" ' . $checked . '></td></tr>';
        }
        echo '</tbody></table><p><input type="submit" class="button-primary" value="' . esc_attr__( 'Save Changes', 'petia-external-bridge' ) . '"></p></form></div>';
    }
}

register_activation_hook( __FILE__, [ 'PetIA_External_Bridge', 'activate' ] );
new PetIA_External_Bridge();

