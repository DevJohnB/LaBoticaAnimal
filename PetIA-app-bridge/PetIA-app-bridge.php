<?php
/**
 * Plugin Name: PetIA App Bridge
 * Description: REST bridge between WordPress WooCommerce and the PetIA app.
 * Version: 1.0.0
 * Author: PetIA
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Send CORS headers for REST responses and preflight requests.
 *
 * @param bool         $served  Whether the request has already been served.
 * @param WP_HTTP_Response $result  Result to send to the client. Unused here.
 * @param WP_REST_Request $request The REST request.
 *
 * @return bool The original $served value.
 */
function labotica_rest_cors( $served = false, $result = null, $request = null ) {
    $origin          = get_http_origin();
    $allowed_origins = (array) get_option(
        'petia_app_bridge_allowed_origins',
        [
            'http://localhost',
            'http://localhost:3000',
        ]
    );

    header( 'Vary: Origin' );

    if ( $origin && ( in_array( '*', $allowed_origins, true ) || in_array( $origin, $allowed_origins, true ) ) ) {
        header( 'Access-Control-Allow-Origin: ' . $origin );
    }

    header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
    header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
    header( 'Access-Control-Expose-Headers: Authorization' );
    header( 'Access-Control-Allow-Credentials: true' );

    return $served;
}

add_action(
    'rest_api_init',
    function () {
        add_filter( 'rest_pre_serve_request', 'labotica_rest_cors', 10, 3 );
    }
);

add_action(
    'init',
    function () {
        if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
            labotica_rest_cors();
            status_header( 200 );
            exit;
        }
    }
);

function petia_app_bridge_init() {
    new PetIA\App_Bridge();
}
add_action( 'plugins_loaded', 'petia_app_bridge_init' );

register_activation_hook( __FILE__, [ 'PetIA\\App_Bridge', 'activate' ] );
