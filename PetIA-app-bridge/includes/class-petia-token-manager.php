<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class PetIA_Token_Manager {
    private $secret;

    public function __construct() {
        if ( ! defined( 'AUTH_KEY' ) ) {
            throw new Exception( 'AUTH_KEY is not defined' );
        }
        $this->secret = AUTH_KEY;
    }

    public function generate_token( $user_id ) {
        $issued = time();
        $payload = [
            'iss' => get_site_url(),
            'iat' => $issued,
            'exp' => $issued + DAY_IN_SECONDS,
            'data' => [ 'user_id' => $user_id ],
            'jti'  => wp_generate_uuid4(),
        ];
        return JWT::encode( $payload, $this->secret, 'HS256' );
    }

    public function decode_token( $token ) {
        return JWT::decode( $token, new Key( $this->secret, 'HS256' ) );
    }

    public function get_authorization_header() {
        $header = null;
        if ( isset( $_SERVER['Authorization'] ) ) {
            $header = trim( $_SERVER['Authorization'] );
        } elseif ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            $header = trim( $_SERVER['HTTP_AUTHORIZATION'] );
        }
        if ( $header && preg_match( '/Bearer\s+(.*)/', $header, $matches ) ) {
            return $matches[1];
        }
        return null;
    }

    public function is_token_revoked( $jti ) {
        return (bool) get_transient( 'petia_revoked_' . $jti );
    }

    public function revoke_token( $jti ) {
        set_transient( 'petia_revoked_' . $jti, true, DAY_IN_SECONDS );
    }
}
