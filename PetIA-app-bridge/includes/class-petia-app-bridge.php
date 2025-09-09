<?php

require_once __DIR__ . '/class-petia-token-manager.php';
require_once __DIR__ . '/class-petia-admin.php';
require_once __DIR__ . '/class-petia-cors.php';

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PetIA_App_Bridge {

    protected $token_manager;
    protected $cors;

    public function __construct() {
        $this->token_manager = new PetIA_Token_Manager();
        $this->cors          = new PetIA_CORS();

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_filter( 'rest_authentication_errors', [ $this, 'authenticate_request' ] );
        add_action( 'rest_api_init', [ $this->cors, 'add_cors_support' ], 15 );

        if ( is_admin() ) {
            new PetIA_Admin();
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
            'callback'            => [ $this, 'handle_get_profile' ],
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
        register_rest_route( 'petia-app-bridge/v1', '/product-categories', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_product_categories' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/products', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_products' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/brands', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_brands' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/wc/(?P<endpoint>[\w\-\/]+)', [
            'methods'             => [ 'GET', 'POST', 'PUT', 'DELETE' ],
            'callback'            => [ $this, 'handle_wc_proxy' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // Token, CORS and admin functionality moved to dedicated classes.

    /**
     * Handle user registration.
     */
    public function handle_register( WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $email    = isset( $params['email'] ) ? sanitize_email( wp_unslash( $params['email'] ) ) : '';
        $password = isset( $params['password'] ) ? wp_unslash( $params['password'] ) : '';

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
        $params   = $request->get_json_params();
        $email    = isset( $params['email'] ) ? sanitize_email( wp_unslash( $params['email'] ) ) : '';
        $password = isset( $params['password'] ) ? wp_unslash( $params['password'] ) : '';

        if ( empty( $email ) || empty( $password ) ) {
            return new WP_Error( 'missing_fields', __( 'Email and password are required.', 'petia-app-bridge' ), [ 'status' => 400 ] );
        }

        $user_obj = get_user_by( 'email', $email );
        if ( ! $user_obj ) {
            return new WP_Error( 'invalid_credentials', __( 'Invalid email or password.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $user = wp_authenticate( $user_obj->user_login, $password );
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

        $token = $this->token_manager->generate_token( $user->ID );
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
        $auth = $this->token_manager->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'missing_token', __( 'Authorization header not found.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $token   = $matches[1];
        $payload = $this->token_manager->decode_token( $token );
        if ( ! $payload ) {
            return new WP_Error( 'invalid_token', __( 'Token is invalid.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $ttl = max( 1, $payload['exp'] - time() );
        $this->token_manager->revoke_token( $token, $ttl );

        return [ 'success' => true ];
    }

    /**
     * Send password reset email to user.
     */
    public function handle_password_reset_request( WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $username = isset( $params['username'] ) ? sanitize_user( wp_unslash( $params['username'] ) ) : '';
        $email    = isset( $params['email'] ) ? sanitize_email( wp_unslash( $params['email'] ) ) : '';

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
     * Validate authentication token and return user profile information.
     */
    public function handle_get_profile( WP_REST_Request $request ) {
        $auth = $this->token_manager->get_authorization_header();
        if ( ! preg_match( '/Bearer\s(\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'missing_token', __( 'Authorization header not found.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        if ( $this->token_manager->is_token_revoked( $matches[1] ) ) {
            return new WP_Error( 'invalid_token', __( 'Token has been revoked.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $payload = $this->token_manager->decode_token( $matches[1] );
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
            'user_id'      => $user->ID,
            'username'     => $user->user_login,
            'email'        => $user->user_email,
            'first_name'   => get_user_meta( $user->ID, 'first_name', true ),
            'last_name'    => get_user_meta( $user->ID, 'last_name', true ),
            'nickname'     => get_user_meta( $user->ID, 'nickname', true ),
            'description'  => get_user_meta( $user->ID, 'description', true ),
            'user_url'     => $user->user_url,
            'display_name' => $user->display_name,
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
     * Retrieve product categories.
     */
    public function handle_get_product_categories( WP_REST_Request $request ) {
        if ( ! get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return new WP_Error( 'woocommerce_missing', __( 'Product categories not available.', 'petia-app-bridge' ), [ 'status' => 500 ] );
        }

        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $categories = [];
        foreach ( $terms as $term ) {
            $thumb_id  = get_term_meta( $term->term_id, 'thumbnail_id', true );
            $image_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : '';
            $categories[] = [
                'id'          => $term->term_id,
                'name'        => $term->name,
                'description' => $term->description,
                'slug'        => $term->slug,
                'image'       => $image_url,
            ];
        }

        return $categories;
    }

    /**
     * Retrieve products.
     */
    public function handle_get_products( WP_REST_Request $request ) {

        if ( ! function_exists( 'wc_get_products' ) ) {
            return new WP_Error( 'woocommerce_missing', __( 'WooCommerce not available.', 'petia-app-bridge' ), [ 'status' => 500 ] );
        }

        if ( ! get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $per_page = absint( $request->get_param( 'per_page' ) );
        $page     = absint( $request->get_param( 'page' ) );

        $args = [
            'limit' => $per_page > 0 ? $per_page : -1,
            'page'  => $page > 0 ? $page : 1,
        ];

        $products = wc_get_products( $args );
        $data     = [];

        foreach ( $products as $product ) {
            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';
            $data[]    = [
                'id'          => $product->get_id(),
                'name'        => $product->get_name(),
                'description' => $product->get_description(),
                'slug'        => $product->get_slug(),
                'price'       => $product->get_price(),
                'image'       => $image_url,
            ];
        }

        return $data;
    }

    /**
     * Retrieve product brands.
     */
    public function handle_get_brands( WP_REST_Request $request ) {
        if ( ! get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $taxonomy = taxonomy_exists( 'product_brand' ) ? 'product_brand' : 'product_tag';
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'woocommerce_missing', __( 'Product brands not available.', 'petia-app-bridge' ), [ 'status' => 500 ] );
        }

        $terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $brands = [];
        foreach ( $terms as $term ) {
            $thumb_id  = get_term_meta( $term->term_id, 'thumbnail_id', true );
            $image_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : '';
            $brands[]  = [
                'id'          => $term->term_id,
                'name'        => $term->name,
                'description' => $term->description,
                'slug'        => $term->slug,
                'image'       => $image_url,
            ];
        }

        return $brands;
    }

    /**
     * Proxy WooCommerce REST API requests through the bridge.
     *
     * Centralizes server logic so the mobile app can remain a thin client.
     * Applies basic sanitization and caching for security and performance.
     */
    public function handle_wc_proxy( WP_REST_Request $request ) {
        if ( ! get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $endpoint = sanitize_text_field( $request['endpoint'] );
        $endpoint = preg_replace( '#[^a-zA-Z0-9_\/-]#', '', $endpoint );

        $method = $request->get_method();
        if ( 'GET' !== $method ) {
            return new WP_Error( 'rest_forbidden', __( 'Only GET requests are supported.', 'petia-app-bridge' ), [ 'status' => 403 ] );
        }

        $user_id   = get_current_user_id();
        $cache_key = 'petia_wc_' . md5( $user_id . '|' . $endpoint );
        $cached    = get_transient( $cache_key );
        if ( $cached ) {
            return rest_ensure_response( $cached );
        }

        $keys = $this->get_wc_api_keys( $user_id );
        if ( ! $keys ) {
            return new WP_Error( 'no_keys', __( 'Missing API keys.', 'petia-app-bridge' ), [ 'status' => 500 ] );
        }

        $url      = rest_url( 'wc/v3/' . ltrim( $endpoint, '/' ) );
        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $keys['consumer_key'] . ':' . $keys['consumer_secret'] ),
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        set_transient( $cache_key, $body, 300 );

        return rest_ensure_response( $body );
    }

    /**
     * Authenticate request for protected routes.
     */
    public function authenticate_request( $result ) {
        if ( ! empty( $result ) ) {
            return $result;
        }

        if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
            return $result;
        }

        if ( ! is_ssl() ) {
            return new WP_Error( 'rest_forbidden', __( 'SSL is required.', 'petia-app-bridge' ), [ 'status' => 403 ] );
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

        $auth = $this->token_manager->get_authorization_header();
        if ( ! preg_match( '/Bearer\\s(\\S+)/', $auth, $matches ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $token = $matches[1];
        if ( $this->token_manager->is_token_revoked( $token ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Token has been revoked.', 'petia-app-bridge' ), [ 'status' => 401 ] );
        }

        $payload = $this->token_manager->decode_token( $token );
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
}

