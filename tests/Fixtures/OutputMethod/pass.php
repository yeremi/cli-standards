<?php
/**
 * Test fixture: OutputMethod — should PASS.
 *
 * This file uses only WP_CLI output methods.
 */

class My_CLI_Command {

    /**
     * Show current command status.
     *
     * ## OPTIONS
     *
     * [--verbose]
     * : Show extra diagnostic information.
     *
     * ## EXAMPLES
     *
     *     wp myplugin status --verbose
     *
     * @since 1.0.0
     */
    public function run_health_check( $args, $assoc_args ) {
        $verbose = WP_CLI\Utils\get_flag_value( $assoc_args, 'verbose', false );

        if ( $verbose ) {
            WP_CLI::log( 'Verbose diagnostics enabled.' );
        }

        WP_CLI::line( 'Plugin status: healthy.' );
        WP_CLI::success( 'Status check complete.' );
    }
}
