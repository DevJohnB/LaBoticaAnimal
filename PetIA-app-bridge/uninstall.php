<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table = $wpdb->prefix . 'petia_app_bridge_access';
$wpdb->query( "DROP TABLE IF EXISTS $table" );

// Clean up revoked token transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_petia_app_bridge_revoked_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_petia_app_bridge_revoked_%'" );
