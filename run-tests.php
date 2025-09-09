<?php
/*
Plugin Name: Test Runner
Description: Allows administrators to execute tests and view log output.
*/

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_management_page(
        'Run Tests',
        'Run Tests',
        'manage_options',
        'run-tests',
        'run_tests_admin_page'
    );
});

function run_tests_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $log_file = plugin_dir_path(__FILE__) . 'test.log';

    if (isset($_POST['run_tests'])) {
        $output = shell_exec('NODE_OPTIONS=--experimental-vm-modules npx jest 2>&1');
        file_put_contents($log_file, $output);
        echo '<h2>' . esc_html__('Test Output', 'run-tests') . '</h2>';
        echo '<pre>' . esc_html($output) . '</pre>';
    }

    echo '<form method="post">';
    submit_button(__('Run Tests', 'run-tests'), 'primary', 'run_tests');
    echo '</form>';

    if (file_exists($log_file)) {
        echo '<h2>' . esc_html__('Test Log', 'run-tests') . '</h2>';
        echo '<pre>' . esc_html(file_get_contents($log_file)) . '</pre>';
    }
}
