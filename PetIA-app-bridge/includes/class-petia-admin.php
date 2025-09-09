<?php
class PetIA_Admin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
    }

    public function register_menu() {
        add_menu_page( 'PetIA Bridge', 'PetIA Bridge', 'manage_options', 'petia-bridge', [ $this, 'render_page' ] );
    }

    public function render_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'access';
        echo '<div class="wrap"><h1>PetIA Bridge</h1><h2 class="nav-tab-wrapper">';
        echo '<a href="?page=petia-bridge&tab=access" class="nav-tab' . ( 'access' === $tab ? ' nav-tab-active' : '' ) . '">Access Control</a>';
        echo '<a href="?page=petia-bridge&tab=tests" class="nav-tab' . ( 'tests' === $tab ? ' nav-tab-active' : '' ) . '">Run Tests</a>';
        echo '</h2>';
        if ( 'tests' === $tab ) {
            if ( isset( $_POST['run-tests'] ) ) {
                $output = esc_html( shell_exec( 'npm test 2>&1' ) );
                echo '<pre>' . $output . '</pre>';
            }
            echo '<form method="post"><p><button class="button button-primary" name="run-tests">Run Node Tests</button></p></form>';
        } else {
            echo '<p>Manage user access periods here.</p>';
        }
        echo '</div>';
    }
}
