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

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/class-petia-app-bridge.php';

register_activation_hook( __FILE__, [ 'PetIA_App_Bridge', 'activate' ] );
new PetIA_App_Bridge();
