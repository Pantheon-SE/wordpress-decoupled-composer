<?php
/*
Plugin Name: Pantheon Demo Importer
Plugin URI: https://pantheon.io
Description: WP CLI commands for managing demo content.
Author: Kyle Taylor
Version: 1.0.0
Author URI: https://github.com/kyletaylored
*/

/**
 * WP CLI commands for demonstration.
 */
class PANTHEON_SE_DEMO_CLI {

    /**
     * Return demo categories
     *
     * @param Array $args Arguments in array format.
     * @param Array $assoc_args Key value arguments stored in associated array format.
     */
    public function get_categories($args, $assoc_args) {
        $demos = OceanWP_Demos::get_demos_data();
        $categories = OceanWP_Demos::get_demo_all_categories( $demos );


        $data = json_encode($categories, JSON_PRETTY_PRINT);

        WP_CLI::log( $data );
    }

}

/**
 * Register WP CLI command
 *
 * @since  1.0.0
 * @author Scott Anderson
 */
function pantheon_se_demo_register_commands() {
    WP_CLI::add_command( 'pantheon', 'PANTHEON_SE_DEMO_CLI' );
}

add_action( 'cli_init', 'pantheon_se_demo_register_commands' );
