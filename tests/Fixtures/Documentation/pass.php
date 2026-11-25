<?php
/**
 * Test fixture: command detection and docblock lookup — should PASS.
 *
 * Only public, non-magic methods (plus __invoke) are WP-CLI subcommands.
 * Constructors, registration bootstraps and private helpers must not be
 * required to carry command docblocks.
 */

class Docs_Pass_Command {

    // ✅ Constructor: never a subcommand, even when it uses WP_CLI.
    public function __construct() {
        WP_CLI::debug( 'Booting command class.' );
    }

    // ✅ Registration bootstrap: only calls WP_CLI::add_command().
    public static function register() {
        WP_CLI::add_command( 'myplugin docs', self::class );
        WP_CLI::add_command(
            'myplugin ping',
            function ( $args, $assoc_args ) {
                WP_CLI::success( 'pong' );
            }
        );
    }

    /**
     * Show the plugin version.
     *
     * ## OPTIONS
     *
     * [--short]
     * : Print only the version number.
     *
     * ## EXAMPLES
     *
     *     wp myplugin docs version --short
     *
     * @since 1.0.0
     */
    #[ReturnTypeWillChange]
    public function version( $args, $assoc_args ) {
        // ✅ Docblock is found even with an attribute in between.
        WP_CLI::line( $this->format_version( '1.0.0' ) );
    }

    /**
     * Run the default action.
     *
     * ## OPTIONS
     *
     * [--quiet]
     * : Suppress informational messages.
     *
     * ## EXAMPLES
     *
     *     wp myplugin docs
     *
     * @since 1.0.0
     */
    public function __invoke( $args, $assoc_args ) {
        WP_CLI::success( 'Done.' );
    }

    /**
     * List registered items.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     *
     * ## AVAILABLE FIELDS
     *
     * * id
     * * name
     *
     * ## EXAMPLES
     *
     *     wp myplugin docs list_items --format=json
     *
     * @since 1.0.0
     */
    public function list_items( $args, $assoc_args ) {
        // ✅ Section headings on their own line, including multi-word ones.
        $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
        WP_CLI\Utils\format_items( $format, [], [ 'id', 'name' ] );
    }

    // ✅ Private helper: not a subcommand.
    private function format_version( string $version ): string {
        WP_CLI::debug( 'Formatting version.' );

        return 'v' . $version;
    }

    // ✅ Protected helper: not a subcommand.
    protected function log_context( array $assocArgs ): void {
        WP_CLI::debug( 'Context: ' . implode( ',', array_keys( $assocArgs ) ) );
    }
}
