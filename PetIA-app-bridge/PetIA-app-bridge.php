<?php
/**
 * Plugin Name: PetIA App Bridge
 * Description: REST endpoints and admin settings to connect the PetIA app with WordPress and external services.
 * Version: 1.0.0
 * Author: La Botica Animal
 * License: GPL2
 * Text Domain: petia-app-bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PetIA_App_Bridge {
    const OPTION_KEY = 'petia_app_bridge_options';
    const TOKEN_TTL = HOUR_IN_SECONDS;

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public static function activate() {
        if ( ! get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, [ 'n8n_chat_url' => '' ] );
        }
    }

    public function register_routes() {
        register_rest_route( 'petia-app-bridge/v1', '/login', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_login' ],
            'args'     => [
                'email'    => [ 'required' => true ],
                'password' => [ 'required' => true ],
            ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'petia-app-bridge/v1', '/profile', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_profile' ],
            'permission_callback' => [ $this, 'check_token_permission' ],
        ] );
    }

    private function generate_token( $user_id ) {
        $token = bin2hex( random_bytes( 16 ) );
        set_transient( 'petia_app_bridge_' . $token, $user_id, self::TOKEN_TTL );
        return $token;
    }

    public function check_token_permission( WP_REST_Request $request ) {
        $header = $request->get_header( 'authorization' );
        if ( ! $header || ! preg_match( '/Bearer\s+(.*)/i', $header, $m ) ) {
            return new WP_Error( 'petia_no_token', 'Falta token de autenticación', [ 'status' => 401 ] );
        }
        $user_id = get_transient( 'petia_app_bridge_' . $m[1] );
        if ( ! $user_id ) {
            return new WP_Error( 'petia_invalid_token', 'Token inválido o expirado', [ 'status' => 403 ] );
        }
        $request->set_param( 'user_id', $user_id );
        return true;
    }

    public function handle_login( WP_REST_Request $request ) {
        $email    = sanitize_email( $request['email'] );
        $password = $request['password'];
        $user     = get_user_by( 'email', $email );
        if ( ! $user ) {
            return new WP_Error( 'petia_invalid', 'Credenciales incorrectas', [ 'status' => 403 ] );
        }
        $auth = wp_authenticate( $user->user_login, $password );
        if ( is_wp_error( $auth ) ) {
            return new WP_Error( 'petia_invalid', 'Credenciales incorrectas', [ 'status' => 403 ] );
        }
        $token = $this->generate_token( $user->ID );
        return [ 'token' => $token ];
    }

    public function handle_profile( WP_REST_Request $request ) {
        $user_id = $request->get_param( 'user_id' );
        $user    = get_userdata( $user_id );
        return [
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
        ];
    }

    public function register_admin_page() {
        add_options_page(
            'PetIA App Bridge',
            'PetIA App Bridge',
            'manage_options',
            'petia-app-bridge',
            [ $this, 'render_admin_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'petia_app_bridge', self::OPTION_KEY );
        add_settings_section( 'petia_app_bridge_main', 'Configuración', '__return_null', 'petia_app_bridge' );
        add_settings_field(
            'n8n_chat_url',
            'URL del chat n8n',
            [ $this, 'field_n8n_chat_url' ],
            'petia_app_bridge',
            'petia_app_bridge_main'
        );
    }

    public function field_n8n_chat_url() {
        $opts = get_option( self::OPTION_KEY );
        $url  = isset( $opts['n8n_chat_url'] ) ? esc_url( $opts['n8n_chat_url'] ) : '';
        echo '<input type="url" name="' . self::OPTION_KEY . '[n8n_chat_url]" value="' . $url . '" class="regular-text">';
    }

    public function render_admin_page() {
        echo '<div class="wrap"><h1>PetIA App Bridge</h1><form method="post" action="options.php">';
        settings_fields( 'petia_app_bridge' );
        do_settings_sections( 'petia_app_bridge' );
        submit_button();
        echo '</form></div>';
    }
}

register_activation_hook( __FILE__, [ 'PetIA_App_Bridge', 'activate' ] );
new PetIA_App_Bridge();
