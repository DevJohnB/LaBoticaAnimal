<?php
/**
 * Plugin Name: PetIA App Bridge
 * Description: REST endpoints for user registration and authentication, enabling mobile apps to use WordPress as backend.
 * Version: 1.0.0
 * Author: La Botica
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PetIA_App_Bridge {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_filter( 'rest_authentication_errors', [ $this, 'authenticate_request' ] );

        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        }
    }

    public static function activate() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'petia_app_bridge_access';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE $table_name ( user_id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY, allowed TINYINT(1) NOT NULL DEFAULT 1, start_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, end_date DATETIME NOT NULL DEFAULT '9999-12-31 23:59:59' ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Register custom REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'petia-app-bridge/v1', '/register', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_register' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/login', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_login' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/logout', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_logout' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/validate-token', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_validate_token' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/password-reset-request', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_password_reset_request' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/password-reset', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_password_reset' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/profile', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_profile' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/profile', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_update_profile' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/order/(?P<id>\d+)/addresses', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_order_addresses' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/order/(?P<id>\d+)/addresses', [
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
            return new WP_Error( 'missing_fields', __( 'Email and password are required.', 'petia-app-bridge' ), [ 'status' => 400 ] );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'user_exists', __( 'User already exists.', 'petia-app-bridge' ), [ 'status' => 409 ] );
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
            return new WP_Error( 'missing_fields', __( 'Email or username and password are required.', 'petia-app-bridge' ), [ 'status' => 400 ] );
        }

        if ( ! empty( $email ) ) {
            $user_obj = get_user_by( 'email', $email );
            if ( ! $user_obj ) {
                return new WP_Error( 'invalid_credentials', __( 'Invalid email or password.', 'petia-app-bridge' ), [ 'status' => 401 ] );
            }
            $username = $user_obj->user_login;
        }

        $user = wp_authenticate( $username, $password );
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'petia_app_bridge_access';
        $exists  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE user_id = %d", $user->ID ) );
        if ( ! $exists ) {
            $wpdb->insert(
                $table,
                [
                    'user_id'    => $user->ID,
                    'allowed'    => 1,
                    'start_date' => current_time( 'mysql' ),
                    'end_date'   => '9999-12-31 23:59:59',
                ],
                [ '%d', '%d', '%s', '%s' ]
            );
        }

        if ( ! $this->user_has_access( $user->ID ) ) {
            return new WP_Error( 'access_denied', __( 'User access to API is disabled.', 'petia-app-bridge' ), [ 'status' => 403 ] );
        }

        $token = $this->generate_token( $user->ID );
        $wc_keys = $this->get_wc_api_keys( $user->ID );

        return [
            'token'          => $token,
            'user_id'        => $user->ID,
            'consumer_key'   => $wc_keys['consumer_key'] ?? null,
            'consumer_secret'=> $wc_keys['consumer_secret'] ?? null,
        ];
    }

    /**
     * Revoke authentication token.
     */
    public function handle_logout( WP_REST_Request $request ) {
        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'missing_token', __( 'Authorization header not found.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $token   = $matches[1];
        $payload = $this->decode_token( $token );
        if ( ! $payload ) {
            return new WP_Error( 'invalid_token', __( 'Token is invalid.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $ttl = max( 1, $payload['exp'] - time() );
        set_transient( 'petia_app_bridge_revoked_' . md5( $token ), true, $ttl );

        return [ 'success' => true ];
    }

    /**
     * Validate authentication token and return user info.
     */
    public function handle_validate_token( WP_REST_Request $request ) {
        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'missing_token', __( 'Authorization header not found.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        if ( $this->is_token_revoked( $matches[1] ) ) {
            return new WP_Error( 'invalid_token', __( 'Token has been revoked.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $payload = $this->decode_token( $matches[1] );
        if ( ! $payload || $payload['exp'] < time() ) {
            return new WP_Error( 'invalid_token', __( 'Token is invalid or expired.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $user = get_userdata( $payload['sub'] );
        if ( ! $user ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        if ( ! $this->user_has_access( $user->ID ) ) {
            return new WP_Error( 'access_denied', __( 'User access to API is disabled.', 'petia-app-bridge' ), [ 'status' => 403 ] );
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
            return new WP_Error( 'missing_fields', __( 'Username or email is required.', 'petia-app-bridge' ), [ 'status' => 400 ] );
        }

        $user = null;
        if ( ! empty( $username ) ) {
            $user = get_user_by( 'login', $username );
        } elseif ( ! empty( $email ) ) {
            $user = get_user_by( 'email', $email );
        }

        if ( ! $user ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'petia-app-bridge' ), [ 'status' => 404 ] );
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
            return new WP_Error( 'missing_fields', __( 'Key, login and new password are required.', 'petia-app-bridge' ), [ 'status' => 400 ] );
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
            return new WP_Error( 'invalid_user', __( 'User not found.', 'petia-app-bridge' ), [ 'status' => 401 ] );
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
            return new WP_Error( 'invalid_user', __( 'User not found.', 'petia-app-bridge' ), [ 'status' => 401 ] );
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
            return new WP_Error( 'no_fields', __( 'No profile fields provided.', 'petia-app-bridge' ), [ 'status' => 400 ] );
        }

        return [ 'success' => true ];
    }

    /**
     * Retrieve billing and shipping addresses for an order.
     */
    public function handle_get_order_addresses( WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new WP_Error( 'woocommerce_missing', __( 'WooCommerce not available.', 'petia-app-bridge' ), [ 'status' => 500 ] );
        }

        $order_id = absint( $request['id'] );
        $order    = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Order not found.', 'petia-app-bridge' ), [ 'status' => 404 ] );
        }

        $user_id = get_current_user_id();
        if ( $order->get_user_id() !== $user_id ) {
            return new WP_Error( 'forbidden', __( 'You are not allowed to access this order.', 'petia-app-bridge' ), [ 'status' => 403 ] );
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
            return new WP_Error( 'woocommerce_missing', __( 'WooCommerce not available.', 'petia-app-bridge' ), [ 'status' => 500 ] );
        }

        $order_id = absint( $request['id'] );
        $order    = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Order not found.', 'petia-app-bridge' ), [ 'status' => 404 ] );
        }

        $user_id = get_current_user_id();
        if ( $order->get_user_id() !== $user_id ) {
            return new WP_Error( 'forbidden', __( 'You are not allowed to update this order.', 'petia-app-bridge' ), [ 'status' => 403 ] );
        }

        $billing  = $request->get_param( 'billing' );
        $shipping = $request->get_param( 'shipping' );

        if ( empty( $billing ) && empty( $shipping ) ) {
            return new WP_Error( 'no_fields', __( 'No address fields provided.', 'petia-app-bridge' ), [ 'status' => 400 ] );
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
        if ( strpos( $route, '/wp-json/petia-app-bridge/v1/' ) === false ) {
            return $result;
        }

        // Allow unauthenticated access to login, register and password reset endpoints.
        if (
            false !== strpos( $route, '/wp-json/petia-app-bridge/v1/login' ) ||
            false !== strpos( $route, '/wp-json/petia-app-bridge/v1/register' ) ||
            false !== strpos( $route, '/wp-json/petia-app-bridge/v1/password-reset' ) ||
            false !== strpos( $route, '/wp-json/petia-app-bridge/v1/password-reset-request' )
        ) {
            return $result;
        }

        $auth = $this->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $token = $matches[1];
        if ( $this->is_token_revoked( $token ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Token has been revoked.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $payload = $this->decode_token( $token );
        if ( ! $payload || $payload['exp'] < time() ) {
            return new WP_Error( 'rest_forbidden', __( 'Token is invalid or expired.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        wp_set_current_user( $payload['sub'] );

        if ( ! $this->user_has_access( $payload['sub'] ) ) {
            return new WP_Error( 'rest_forbidden', __( 'User access to API is disabled.', 'petia-app-bridge' ), [ 'status' => 403 ] );
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
        return (bool) get_transient( 'petia_app_bridge_revoked_' . md5( $token ) );
    }

    /**
     * Retrieve or generate WooCommerce API keys for a user.
     */
    protected function get_wc_api_keys( $user_id ) {
        if ( ! function_exists( 'wc_rand_hash' ) || ! function_exists( 'wc_api_hash' ) ) {
            return null;
        }

        $ck = get_user_meta( $user_id, '_petia_wc_consumer_key', true );
        $cs = get_user_meta( $user_id, '_petia_wc_consumer_secret', true );
        if ( $ck && $cs ) {
            return [ 'consumer_key' => $ck, 'consumer_secret' => $cs ];
        }

        $consumer_key    = 'ck_' . wc_rand_hash();
        $consumer_secret = 'cs_' . wc_rand_hash();
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'woocommerce_api_keys',
            [
                'user_id'       => $user_id,
                'description'   => 'PetIA App Key',
                'permissions'   => 'read',
                'consumer_key'  => wc_api_hash( $consumer_key ),
                'consumer_secret'=> $consumer_secret,
                'truncated_key' => substr( $consumer_key, -7 ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
        update_user_meta( $user_id, '_petia_wc_consumer_key', $consumer_key );
        update_user_meta( $user_id, '_petia_wc_consumer_secret', $consumer_secret );

        return [
            'consumer_key'   => $consumer_key,
            'consumer_secret'=> $consumer_secret,
        ];
    }

    protected function user_has_access( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'petia_app_bridge_access';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT allowed, end_date FROM $table WHERE user_id = %d", $user_id ) );
        if ( null === $row ) {
            return true;
        }
        if ( ! $row->allowed ) {
            return false;
        }
        return strtotime( $row->end_date ) > current_time( 'timestamp' );
    }

    public function register_admin_page() {
        add_users_page(
            __( 'PetIA App Bridge Access', 'petia-app-bridge' ),
            __( 'PetIA App Bridge Access', 'petia-app-bridge' ),
            'manage_options',
            'petia-app-bridge-access',
            [ $this, 'render_access_page' ]
        );
    }

    public function render_access_page() {
        if ( isset( $_POST['petia_access_nonce'] ) && wp_verify_nonce( $_POST['petia_access_nonce'], 'petia_save_access' ) ) {
            $users = get_users( [ 'fields' => [ 'ID' ] ] );
            global $wpdb;
            $table = $wpdb->prefix . 'petia_app_bridge_access';
            foreach ( $users as $user ) {
                $allowed    = isset( $_POST['access'][ $user->ID ] ) ? 1 : 0;
                $start_date = isset( $_POST['start_date'][ $user->ID ] ) && $_POST['start_date'][ $user->ID ] !== '' ? sanitize_text_field( $_POST['start_date'][ $user->ID ] ) . ' 00:00:00' : current_time( 'mysql' );
                $end_date   = isset( $_POST['end_date'][ $user->ID ] ) && $_POST['end_date'][ $user->ID ] !== '' ? sanitize_text_field( $_POST['end_date'][ $user->ID ] ) . ' 23:59:59' : '9999-12-31 23:59:59';
                $wpdb->replace(
                    $table,
                    [
                        'user_id'    => $user->ID,
                        'allowed'    => $allowed,
                        'start_date' => $start_date,
                        'end_date'   => $end_date,
                    ],
                    [ '%d', '%d', '%s', '%s' ]
                );
            }
            echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'petia-app-bridge' ) . '</p></div>';
        }

        $users = get_users();
        global $wpdb;
        $table = $wpdb->prefix . 'petia_app_bridge_access';

        echo '<div class="wrap"><h1>' . esc_html__( 'PetIA App Bridge Access', 'petia-app-bridge' ) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field( 'petia_save_access', 'petia_access_nonce' );
        echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'User', 'petia-app-bridge' ) . '</th><th>' . esc_html__( 'Allowed', 'petia-app-bridge' ) . '</th><th>' . esc_html__( 'Start Date', 'petia-app-bridge' ) . '</th><th>' . esc_html__( 'End Date', 'petia-app-bridge' ) . '</th></tr></thead><tbody>';
        foreach ( $users as $user ) {
            $row     = $wpdb->get_row( $wpdb->prepare( "SELECT allowed, start_date, end_date FROM $table WHERE user_id = %d", $user->ID ) );
            $allowed = $row ? $row->allowed : null;
            $checked = ( null === $allowed || $allowed ) ? 'checked' : '';
            $start_v = $row ? substr( $row->start_date, 0, 10 ) : '';
            $end_v   = $row ? substr( $row->end_date, 0, 10 ) : '9999-12-31';
            echo '<tr><td>' . esc_html( $user->user_login ) . '</td><td><input type="checkbox" name="access[' . intval( $user->ID ) . ']" value="1" ' . $checked . '></td><td><input type="date" name="start_date[' . intval( $user->ID ) . ']" value="' . esc_attr( $start_v ) . '"></td><td><input type="date" name="end_date[' . intval( $user->ID ) . ']" value="' . esc_attr( $end_v ) . '"></td></tr>';
        }
        echo '</tbody></table><p><input type="submit" class="button-primary" value="' . esc_attr__( 'Save Changes', 'petia-app-bridge' ) . '"></p></form></div>';
    }
}

register_activation_hook( __FILE__, [ 'PetIA_App_Bridge', 'activate' ] );
new PetIA_App_Bridge();

