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

if ( ! defined( 'PETIA_ALLOWED_ORIGINS' ) ) {
    define( 'PETIA_ALLOWED_ORIGINS', '*' );
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    add_action(
        'admin_notices',
        function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'PetIA App Bridge requires Composer dependencies. Please run composer install.', 'petia-app-bridge' ) . '</p></div>';
        }
    );
    return;
}

if ( ! class_exists( '\\Firebase\\JWT\\JWT' ) ) {
    add_action(
        'admin_notices',
        function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'PetIA App Bridge requires the firebase/php-jwt library. Please run composer install.', 'petia-app-bridge' ) . '</p></div>';
        }
    );
    return;
}

require_once __DIR__ . '/includes/class-petia-app-bridge.php';

register_activation_hook( __FILE__, [ 'PetIA_App_Bridge', 'activate' ] );
new PetIA_App_Bridge();
