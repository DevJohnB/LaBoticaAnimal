<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PetIA_Token_Manager {
    protected $secret;

    public function __construct() {
        if ( ! defined( 'AUTH_KEY' ) || empty( AUTH_KEY ) || 'change_this_secret_key' === AUTH_KEY ) {
            throw new \Exception( __( 'AUTH_KEY must be defined in wp-config.php.', 'petia-app-bridge' ) );
        }
        $this->secret = AUTH_KEY;
    }

    public function generate_token( $user_id ) {
        $payload = [
            'sub' => $user_id,
            'iat' => time(),
            'exp' => time() + DAY_IN_SECONDS,
        ];
        return JWT::encode( $payload, $this->secret, 'HS256' );
    }

    public function decode_token( $token ) {
        try {
            return (array) JWT::decode( $token, new Key( $this->secret, 'HS256' ) );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    public function get_authorization_header() {
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

    public function is_token_revoked( $token ) {
        return (bool) get_transient( 'petia_app_bridge_revoked_' . md5( $token ) );
    }

    public function revoke_token( $token, $ttl ) {
        set_transient( 'petia_app_bridge_revoked_' . md5( $token ), true, $ttl );
    }
}
