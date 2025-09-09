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

function petia_app_bridge_init() {
    new PetIA\App_Bridge();
}
add_action( 'plugins_loaded', 'petia_app_bridge_init' );

register_activation_hook( __FILE__, [ 'PetIA\\App_Bridge', 'activate' ] );
