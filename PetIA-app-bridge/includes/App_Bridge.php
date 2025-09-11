<?php
namespace PetIA;

use PetIA\Controllers\AuthController;
use PetIA\Controllers\UserController;
use PetIA\Controllers\OrderController;
use PetIA\Controllers\CatalogController;
use PetIA\Controllers\ProxyController;

class App_Bridge {
    private $token_manager;
    private $auth_controller;
    private $user_controller;
    private $order_controller;
    private $catalog_controller;
    private $proxy_controller;

    public function __construct() {
        $this->token_manager    = new Token_Manager();
        $this->auth_controller  = new AuthController( $this->token_manager );
        $this->user_controller  = new UserController( $this->token_manager );
        $this->order_controller = new OrderController();
        $this->catalog_controller = new CatalogController();
        $this->proxy_controller   = new ProxyController();

        add_filter( 'determine_current_user', [ $this->auth_controller, 'determine_current_user' ], 20 );
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_filter( 'rest_authentication_errors', [ $this->auth_controller, 'authenticate_requests' ] );
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

    public function register_routes() {
        $this->auth_controller->register_routes();
        $this->user_controller->register_routes();
        $this->order_controller->register_routes();
        $this->catalog_controller->register_routes();
        $this->proxy_controller->register_routes();
    }
}
