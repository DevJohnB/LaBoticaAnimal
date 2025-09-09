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

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    error_log( 'PetIA App Bridge: missing vendor/autoload.php. Run composer install.' );
    return;
}

define( 'PETIA_ABSPATH', plugin_dir_path( __FILE__ ) );
require_once PETIA_ABSPATH . 'autoload.php';

function petia_app_bridge_init() {
    new PetIA_App_Bridge();
}
add_action( 'plugins_loaded', 'petia_app_bridge_init' );

register_activation_hook( __FILE__, [ 'PetIA_App_Bridge', 'activate' ] );
