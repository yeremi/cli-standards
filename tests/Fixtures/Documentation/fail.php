<?php
/**
 * Test fixture: command detection and docblock lookup — should FAIL.
 */

class Docs_Fail_Command {

    // ❌ G1: public command method without a docblock.
    public function version( $args, $assoc_args ) {
        WP_CLI::line( '1.0.0' );
    }

    // ❌ G1: camelCase $assocArgs is still a command handler, even without WP_CLI calls.
    public function config( array $args, array $assocArgs ) {
        return $assocArgs;
    }

    // ❌ G1: __invoke is the default subcommand and needs a docblock.
    public function __invoke( $args, $assoc_args ) {
        WP_CLI::success( 'Done.' );
    }

    /**
     * Show plugin status.
     *
     * ## OPTIONS
     *
     * [--short]
     * : Print a one-line summary.
     */
    public function health( $args, $assoc_args ) {
        // ❌ G2: no ## EXAMPLES. ⚠ G4: no @since.
        WP_CLI::success( 'Healthy.' );
    }

    // ❌ G1: a method that registers AND runs WP-CLI code is still a command.
    public function boot( $args, $assoc_args ) {
        WP_CLI::add_command( 'myplugin extra', self::class );
        WP_CLI::log( 'Booted.' );
    }
}
