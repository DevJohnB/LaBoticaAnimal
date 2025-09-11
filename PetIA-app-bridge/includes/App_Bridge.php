<?php
namespace PetIA;

class App_Bridge {
    private $token_manager;
    private $decoded_token;

    public function __construct() {
        $this->token_manager = new Token_Manager();
        add_filter( 'determine_current_user', [ $this, 'determine_current_user' ], 20 );
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_filter( 'rest_authentication_errors', [ $this, 'authenticate_requests' ] );
        add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

        if ( is_admin() ) {
            new Admin();
        }
    }

    public static function activate() {
        global $wpdb;
        $table = $wpdb->prefix . 'petia_app_bridge_access';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            allowed TINYINT(1) NOT NULL DEFAULT 1,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            PRIMARY KEY  (user_id)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        add_option(
            'petia_app_bridge_allowed_origins',
            [ 'http://localhost', 'http://localhost:3000' ]
        );
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

    public function register_routes() {
        $namespace = 'petia-app-bridge/v1';
        register_rest_route( $namespace, '/register', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_register' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/login', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_login' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/logout', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_logout' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/validate-token', [
            'methods' => 'GET',
            'callback' => [ $this, 'handle_validate_token' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/password-reset-request', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_password_reset_request' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/password-reset', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_password_reset' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/profile', [
            'methods' => 'GET',
            'callback' => [ $this, 'handle_profile_get' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/profile', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_profile_post' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/order/(?P<id>\d+)/addresses', [
            'methods' => 'GET',
            'callback' => [ $this, 'handle_order_addresses_get' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/order/(?P<id>\d+)/addresses', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_order_addresses_post' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/product-categories', [
            'methods' => 'GET',
            'callback' => [ $this, 'handle_product_categories' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/products', [
            'methods' => 'GET',
            'callback' => [ $this, 'handle_products' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/brands', [
            'methods' => 'GET',
            'callback' => [ $this, 'handle_brands' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/wc-store/(?P<endpoint>.*)', [
            'methods' => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ],
            'callback' => [ $this, 'handle_wc_store_proxy' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $namespace, '/wc/(?P<endpoint>.*)', [
            'methods' => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ],
            'callback' => [ $this, 'handle_wc_proxy' ],
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

    // Fijar el usuario directamente usando el token (similar a handle_validate_token)
    $token = $this->token_manager->get_authorization_header();
    if ( ! $token ) {
        return new WP_Error('no_token', 'Header Authorization ausente', ['status' => 401]);
    }

    try {
        $decoded = $this->token_manager->decode_token( $token );
        $user_id = (int) $decoded->data->user_id;
        $user = wp_set_current_user( $user_id );
    } catch ( Exception $e ) {
        return new WP_Error('invalid_token', $e->getMessage(), ['status' => 401]);
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
        if ( class_exists( '\WC_Customer' ) ) {
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

    public function handle_order_addresses_get( \WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new \WP_Error( 'woocommerce_missing', 'WooCommerce not available', [ 'status' => 501 ] );
        }
        $order = wc_get_order( (int) $request['id'] );
        if ( ! $order ) {
            return new \WP_Error( 'not_found', 'Order not found', [ 'status' => 404 ] );
        }
        return [
            'billing'  => $order->get_address( 'billing' ),
            'shipping' => $order->get_address( 'shipping' ),
        ];
    }

    public function handle_order_addresses_post( \WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new \WP_Error( 'woocommerce_missing', 'WooCommerce not available', [ 'status' => 501 ] );
        }
        $order = wc_get_order( (int) $request['id'] );
        if ( ! $order ) {
            return new \WP_Error( 'not_found', 'Order not found', [ 'status' => 404 ] );
        }
        $params = $request->get_json_params();
        if ( isset( $params['billing'] ) && is_array( $params['billing'] ) ) {
            $order->set_address( $params['billing'], 'billing' );
        }
        if ( isset( $params['shipping'] ) && is_array( $params['shipping'] ) ) {
            $order->set_address( $params['shipping'], 'shipping' );
        }
        $order->save();
        return [
            'billing'  => $order->get_address( 'billing' ),
            'shipping' => $order->get_address( 'shipping' ),
        ];
    }

    public function handle_product_categories( \WP_REST_Request $request ) {
        if ( ! function_exists( 'get_terms' ) ) {
            return [];
        }
        $terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
        $data  = array_map(
            fn( $t ) => [
                'id'     => $t->term_id,
                'name'   => $t->name,
                'parent' => $t->parent,
            ],
            $terms
        );
        return $data;
    }

    public function handle_products( \WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return [];
        }
        $args = [ 'limit' => -1 ];
        $category = $request->get_param( 'category' );
        if ( $category ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => array_map( 'intval', (array) $category ),
                ],
            ];
        }
        $products = wc_get_products( $args );
        $data     = [];
        foreach ( $products as $product ) {
            $image_id = $product->get_image_id();
            $item     = [
                'id'    => $product->get_id(),
                'name'  => $product->get_name(),
                'price' => $product->get_price(),
                'image' => $image_id ? wp_get_attachment_url( $image_id ) : '',
                'type'  => $product->get_type(),
            ];
            if ( 'variable' === $product->get_type() ) {
                $attributes = [];
                foreach ( $product->get_variation_attributes() as $taxonomy => $options ) {
                    $attributes[ $taxonomy ] = array_values( $options );
                }
                $item['attributes'] = $attributes;
                $item['variations'] = array_map(
                    function( $variation ) {
                        return [
                            'id'         => $variation['variation_id'],
                            'attributes' => $variation['attributes'],
                        ];
                    },
                    $product->get_available_variations()
                );
            }
            $data[] = $item;
        }
        return $data;
    }

    public function handle_brands( \WP_REST_Request $request ) {
        if ( ! function_exists( 'get_terms' ) ) {
            return [];
        }
        $terms = get_terms( [ 'taxonomy' => 'product_brand', 'hide_empty' => false ] );
        return wp_list_pluck( $terms, 'name', 'term_id' );
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
