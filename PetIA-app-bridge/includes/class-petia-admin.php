<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PetIA_Admin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
    }

    public function register_admin_page() {
        add_menu_page(
            __( 'PetIA App Bridge', 'petia-app-bridge' ),
            __( 'PetIA Bridge', 'petia-app-bridge' ),
            'manage_options',
            'petia-app-bridge',
            [ $this, 'render_admin_page' ]
        );
    }

    public function render_admin_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'access';
        $base_url = menu_page_url( 'petia-app-bridge', false );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'PetIA App Bridge', 'petia-app-bridge' ) . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="' . esc_url( add_query_arg( 'tab', 'access', $base_url ) ) . '" class="nav-tab ' . ( 'tests' !== $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Access Control', 'petia-app-bridge' ) . '</a>';
        echo '<a href="' . esc_url( add_query_arg( 'tab', 'tests', $base_url ) ) . '" class="nav-tab ' . ( 'tests' === $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Run Tests', 'petia-app-bridge' ) . '</a>';
        echo '</h2>';

        if ( 'tests' === $tab ) {
            $this->render_tests_tab();
        } else {
            $this->render_access_tab();
        }
        echo '</div>';
    }

    private function render_access_tab() {
        if ( isset( $_POST['petia_access_nonce'] ) && wp_verify_nonce( $_POST['petia_access_nonce'], 'petia_save_access' ) ) {
            $users = get_users( [ 'fields' => [ 'ID' ] ] );
            global $wpdb;
            $table = $wpdb->prefix . 'petia_app_bridge_access';
            foreach ( $users as $user ) {
                $allowed    = isset( $_POST['access'][ $user->ID ] ) ? 1 : 0;
                $start_date = isset( $_POST['start_date'][ $user->ID ] ) && $_POST['start_date'][ $user->ID ] !== '' ? sanitize_text_field( $_POST['start_date'][ $user->ID ] ) . ' 00:00:00' : current_time( 'mysql' );
                $end_date   = isset( $_POST['end_date'][ $user->ID ] ) && $_POST['end_date'][ $user->ID ] !== '' ? sanitize_text_field( $_POST['end_date'][ $user->ID ] ) . ' 23:59:59' : '9999-12-31 23:59:59';
                $wpdb->replace(
                    $table,
                    [
                        'user_id'    => $user->ID,
                        'allowed'    => $allowed,
                        'start_date' => $start_date,
                        'end_date'   => $end_date,
                    ],
                    [ '%d', '%d', '%s', '%s' ]
                );
            }
            echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'petia-app-bridge' ) . '</p></div>';
        }

        $users = get_users();
        global $wpdb;
        $table = $wpdb->prefix . 'petia_app_bridge_access';

        echo '<form method="post">';
        wp_nonce_field( 'petia_save_access', 'petia_access_nonce' );
        echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'User', 'petia-app-bridge' ) . '</th><th>' . esc_html__( 'Allowed', 'petia-app-bridge' ) . '</th><th>' . esc_html__( 'Start Date', 'petia-app-bridge' ) . '</th><th>' . esc_html__( 'End Date', 'petia-app-bridge' ) . '</th></tr></thead><tbody>';
        foreach ( $users as $user ) {
            $row     = $wpdb->get_row( $wpdb->prepare( "SELECT allowed, start_date, end_date FROM $table WHERE user_id = %d", $user->ID ) );
            $allowed = $row ? $row->allowed : null;
            $checked = ( null === $allowed || $allowed ) ? 'checked' : '';
            $start_v = $row ? substr( $row->start_date, 0, 10 ) : '';
            $end_v   = $row ? substr( $row->end_date, 0, 10 ) : '9999-12-31';
            echo '<tr><td>' . esc_html( $user->user_login ) . '</td><td><input type="checkbox" name="access[' . intval( $user->ID ) . ']" value="1" ' . $checked . '></td><td><input type="date" name="start_date[' . intval( $user->ID ) . ']" value="' . esc_attr( $start_v ) . '"></td><td><input type="date" name="end_date[' . intval( $user->ID ) . ']" value="' . esc_attr( $end_v ) . '"></td></tr>';
        }
        echo '</tbody></table><p><input type="submit" class="button-primary" value="' . esc_attr__( 'Save Changes', 'petia-app-bridge' ) . '"></p></form>';
    }

    private function render_tests_tab() {
        echo '<p>' . esc_html__( 'Running tests from the admin panel is disabled for security reasons.', 'petia-app-bridge' ) . '</p>';
        echo '<p><code>npm test</code></p>';
    }
}
