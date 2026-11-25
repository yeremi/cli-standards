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
    #[ReturnTypeWillChange]
    public function health( $args, $assoc_args ) {
        // ❌ G2: no ## EXAMPLES. ⚠ G4: no @since. The docblock itself is found
        // through the attribute, so no MissingDocblock is reported.
        WP_CLI::success( 'Healthy.' );
    }

    /**
     * Refresh the cache.
     *
     * Flags are described under ## OPTIONS in the handbook; see ## EXAMPLES there too.
     *
     * @since 1.0.0
     */
    public function refresh( $args, $assoc_args ) {
        // ❌ G1 + G2: sections are only mentioned in prose, not used as headings.
        WP_CLI::success( 'Refreshed.' );
    }

    /**
     * List registered items.
     *
     * Fields are listed under ## AVAILABLE FIELDS in the README.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     *
     * ## EXAMPLES
     *
     *     wp myplugin docs list_items --format=json
     *
     * @since 1.0.0
     */
    public function list_items( $args, $assoc_args ) {
        // ⚠ C4: AVAILABLE FIELDS is only mentioned in prose.
        $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
        WP_CLI\Utils\format_items( $format, [], [ 'id', 'name' ] );
    }

    // ❌ G1: a method that registers AND runs WP-CLI code is still a command.
    public function boot( $args, $assoc_args ) {
        WP_CLI::add_command( 'myplugin extra', self::class );
        WP_CLI::log( 'Booted.' );
    }
}
