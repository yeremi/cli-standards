<?php
/**
 * Test fixture: DocumentedFlags — should FAIL.
 */

class Documented_Flags_Fail_Command {

    /**
     * Show stats.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     *
     * ## EXAMPLES
     *
     *     wp acme stats --format=json
     *
     * @since 1.0.0
     */
    public function stats( $args, $assoc_args ) {
        $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

        // ⚠ UndocumentedFlag: --verbose is read through get_flag_value().
        $verbose = WP_CLI\Utils\get_flag_value( $assoc_args, 'verbose', false );

        // ⚠ UndocumentedFlag: --since is read through array_key_exists().
        $since = array_key_exists( 'since', $assoc_args ) ? 'since' : 'all';

        // ⚠ UndocumentedFlag: --limit is read directly (reported once).
        $limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10;

        WP_CLI\Utils\format_items( $format, [ compact( 'verbose', 'since', 'limit' ) ], [ 'verbose', 'since', 'limit' ] );
    }

    /**
     * Describe settings.
     *
     * ## OPTIONS
     *
     * [--output=<file>]
     * : Target file.
     *
     * [--pretty]
     * : Pretty-print the JSON.
     *
     * ## EXAMPLES
     *
     *     wp acme describe --output=settings.json
     *
     * @since 1.0.0
     */
    public function describe( $args, $assoc_args ) {
        // ⚠ UnusedFlag: --pretty is documented, never read.
        $file = WP_CLI\Utils\get_flag_value( $assoc_args, 'output', 'settings.json' );

        // ⚠ UndocumentedFlag: flag names are case-sensitive.
        $mode = WP_CLI\Utils\get_flag_value( $assoc_args, 'Output', 'w' );

        // ✅ unset() and assignments write, they do not read: no
        // DocumentedFlags report (FlagExtraction still asks for get_flag_value()).
        unset( $assoc_args['version'] );
        $assoc_args['fields'] = 'id';

        WP_CLI::line( "Settings from {$file} ({$mode})." );
    }

    /**
     * Purge the queue.
     *
     * ## OPTIONS
     *
     * [--queue=<name>]
     * : Queue name.
     *
     * ## EXAMPLES
     *
     *     wp acme purge-queue --queue=emails --yes
     *
     * @since 1.0.0
     */
    public function purge_queue( $args, $assoc_args ) {
        // ⚠ UndocumentedFlag: confirm() reads --yes, which WP-CLI then rejects as unknown.
        WP_CLI::confirm( 'Purge the queue?', $assoc_args );

        if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) { // ⚠ UndocumentedFlag
            return;
        }

        acme_purge_queue( WP_CLI\Utils\get_flag_value( $assoc_args, 'queue', 'default' ) );
        WP_CLI::success( 'Purged.' );
    }

    /**
     * Show index health.
     *
     * ## OPTIONS
     *
     * [--network]
     * : Check every site.
     *
     * ## EXAMPLES
     *
     *     wp acme health --network
     *
     * @since 1.0.0
     */
    public function health( $args ) {
        // ⚠ UnusedFlag: the handler has no assoc-args parameter at all.
        WP_CLI::success( acme_index_health() );
    }
}

// ⚠ UndocumentedFlag + ⚠ UnusedFlag: the synopsis argument wins over the code.
WP_CLI::add_command(
    'acme warm',
    function ( $args, $assoc_args ) {
        $urls = WP_CLI\Utils\get_flag_value( $assoc_args, 'url-list', '' );
        WP_CLI::success( "Warmed {$urls}." );
    },
    [
        'shortdesc' => 'Warm the cache.',
        'synopsis'  => [
            [ 'type' => 'assoc', 'name' => 'urls', 'optional' => true ],
        ],
        'longdesc'  => "## EXAMPLES\n\n    wp acme warm --urls=a,b",
    ]
);
