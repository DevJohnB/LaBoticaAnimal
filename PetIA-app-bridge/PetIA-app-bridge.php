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

/**
 * Check if Composer dependencies are available.
 *
 * @return bool True when dependencies are available, false otherwise.
 */
function petia_app_bridge_ensure_dependencies() {
    $autoload = __DIR__ . '/vendor/autoload.php';

    return file_exists( $autoload );
}

if ( ! petia_app_bridge_ensure_dependencies() ) {
    add_action(
        'admin_notices',
        function () {
            printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'PetIA App Bridge could not install Composer dependencies. Please run composer install.', 'petia-app-bridge' ) );
        }
    );
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

if ( ! class_exists( '\\Firebase\\JWT\\JWT' ) ) {
    add_action(
        'admin_notices',
        function () {
            printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'PetIA App Bridge requires the firebase/php-jwt library. Please run composer install.', 'petia-app-bridge' ) );
        }
    );
    return;
}

require_once __DIR__ . '/includes/class-petia-app-bridge.php';

register_activation_hook( __FILE__, function() {
    if ( ! petia_app_bridge_ensure_dependencies() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            printf( '<div class="error"><p>%s</p></div>', esc_html__( 'PetIA App Bridge requires composer dependencies. Composer install failed.', 'petia-app-bridge' ) );
        } );
        return;
    }

    require_once __DIR__ . '/vendor/autoload.php';

    if ( ! class_exists( 'Firebase\\JWT\\JWT' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            printf( '<div class="error"><p>%s</p></div>', esc_html__( 'PetIA App Bridge requires the Firebase JWT library. Please run composer install.', 'petia-app-bridge' ) );
        } );
        return;
    }

    if ( ! defined( 'AUTH_KEY' ) || empty( AUTH_KEY ) || 'change_this_secret_key' === AUTH_KEY ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            printf( '<div class="error"><p>%s</p></div>', esc_html__( 'AUTH_KEY must be defined in wp-config.php.', 'petia-app-bridge' ) );
        } );
        return;
    }

    PetIA_App_Bridge::activate();
} );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            printf( '<div class="error"><p>%s</p></div>', esc_html__( 'PetIA App Bridge requires WooCommerce to be active.', 'petia-app-bridge' ) );
        } );
        return;
    }

    try {
        new PetIA_App_Bridge();
    } catch ( Exception $e ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() use ( $e ) {
            printf( '<div class="error"><p>%s</p></div>', esc_html( $e->getMessage() ) );
        } );
    }
} );
