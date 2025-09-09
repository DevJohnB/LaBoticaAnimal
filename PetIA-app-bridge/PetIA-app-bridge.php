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
 * Ensure that Composer dependencies are installed.
 *
 * Attempts to run `composer install` when the vendor autoloader is missing.
 *
 * @return bool True when dependencies are available, false otherwise.
 */
function petia_app_bridge_ensure_dependencies() {
    $autoload = __DIR__ . '/vendor/autoload.php';

    if ( file_exists( $autoload ) ) {
        return true;
    }

    $cmd         = 'cd ' . escapeshellarg( __DIR__ ) . ' && composer install --no-dev --prefer-dist 2>&1';
    $output      = array();
    $return_code = 0;
    exec( $cmd, $output, $return_code );

    if ( 0 !== $return_code ) {
        error_log( 'Composer install failed: ' . implode( "\n", $output ) );
    }

    return file_exists( $autoload );
}

if ( ! petia_app_bridge_ensure_dependencies() ) {
    add_action(
        'admin_notices',
        function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'PetIA App Bridge could not install Composer dependencies. Please run composer install.', 'petia-app-bridge' ) . '</p></div>';
        }
    );
    return;
}

require __DIR__ . '/vendor/autoload.php';

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

register_activation_hook( __FILE__, function() {
    if ( ! petia_app_bridge_ensure_dependencies() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__( 'PetIA App Bridge requires composer dependencies. Composer install failed.', 'petia-app-bridge' ) . '</p></div>';
        } );
        return;
    }

    require_once __DIR__ . '/vendor/autoload.php';

    if ( ! class_exists( 'Firebase\\JWT\\JWT' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__( 'PetIA App Bridge requires the Firebase JWT library. Please run composer install.', 'petia-app-bridge' ) . '</p></div>';
        } );
        return;
    }

    if ( ! defined( 'AUTH_KEY' ) || empty( AUTH_KEY ) || 'change_this_secret_key' === AUTH_KEY ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__( 'AUTH_KEY must be defined in wp-config.php.', 'petia-app-bridge' ) . '</p></div>';
        } );
        return;
    }

    PetIA_App_Bridge::activate();
} );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__( 'PetIA App Bridge requires WooCommerce to be active.', 'petia-app-bridge' ) . '</p></div>';
        } );
        return;
    }

    try {
        new PetIA_App_Bridge();
    } catch ( Exception $e ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() use ( $e ) {
            echo '<div class="error"><p>' . esc_html( $e->getMessage() ) . '</p></div>';
        } );
    }
} );
