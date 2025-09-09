<?php
spl_autoload_register(
    function ( $class ) {
        if ( strpos( $class, 'PetIA_' ) !== 0 ) {
            return;
        }

        $file = __DIR__ . '/includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
);
