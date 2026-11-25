<?php
/**
 * Test fixture: DocumentedFlags — should PASS.
 */

class Documented_Flags_Pass_Command {

    /**
     * Show stats.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     *
     * [--verbose]
     * : Show details.
     *
     * [--since=<date>]
     * : Start date.
     *
     * ## EXAMPLES
     *
     *     wp acme stats --verbose
     *
     * @since 1.0.0
     */
    public function stats( $args, $assoc_args ) {
        // ✅ Every flag read is documented, and every documented flag is read.
        $format  = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
        $verbose = WP_CLI\Utils\get_flag_value( $assoc_args, 'verbose', false );
        $since   = array_key_exists( 'since', $assoc_args ) ? 'since' : 'all';

        // ✅ WP-CLI global parameters are never documented.
        $url = WP_CLI\Utils\get_flag_value( $assoc_args, 'url', '' );

        WP_CLI\Utils\format_items( $format, [ compact( 'verbose', 'since', 'url' ) ], [ 'verbose', 'since', 'url' ] );
    }

    /**
     * Purge the queue.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * [--dry-run]
     * : Preview only.
     *
     * ## EXAMPLES
     *
     *     wp acme purge-queue --yes
     *
     * @since 1.0.0
     */
    public function purge_queue( $args, $assoc_args ) {
        // ✅ confirm() reads the documented --yes.
        WP_CLI::confirm( 'Purge the queue?', $assoc_args );

        if ( ! WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
            acme_purge_queue();
        }

        WP_CLI::success( 'Purged.' );
    }

    /**
     * Summarize records.
     *
     * ## OPTIONS
     *
     * [--source=<file>]
     * : CSV file.
     *
     * [--skip-header]
     * : Ignore the first row.
     *
     * ## EXAMPLES
     *
     *     wp acme summary --source=data.csv
     *
     * @since 1.0.0
     */
    public function summary( $args, $assoc_args ) {
        // ✅ The assoc args are passed on: reads cannot be followed, so no
        // UnusedFlag for --skip-header.
        $source = WP_CLI\Utils\get_flag_value( $assoc_args, 'source', 'data.csv' );
        WP_CLI::line( acme_summary( $source, $assoc_args ) );
    }

    /**
     * Update meta for any field.
     *
     * ## OPTIONS
     *
     * [--<field>=<value>]
     * : Any meta field.
     *
     * ## EXAMPLES
     *
     *     wp acme meta --color=blue
     *
     * @since 1.0.0
     */
    public function meta( $args, $assoc_args ) {
        // ✅ A generic [--<field>=<value>] entry accepts any flag.
        $color = WP_CLI\Utils\get_flag_value( $assoc_args, 'color-name', '' );
        WP_CLI::success( "Updated {$color}." );
    }
}

// ✅ Synopsis given as a string to add_command().
WP_CLI::add_command(
    'acme warm',
    function ( $args, $assoc_args ) {
        $urls = WP_CLI\Utils\get_flag_value( $assoc_args, 'urls', '' );
        WP_CLI::success( "Warmed {$urls}." );
    },
    [
        'shortdesc' => 'Warm the cache.',
        'synopsis'  => '[--urls=<list>]',
        'longdesc'  => "## EXAMPLES\n\n    wp acme warm --urls=a,b",
    ]
);

// ✅ Synopsis built at runtime: cannot be read, so nothing is reported.
WP_CLI::add_command(
    'acme cool',
    function ( $args, $assoc_args ) {
        $ttl = WP_CLI\Utils\get_flag_value( $assoc_args, 'ttl', 60 );
        WP_CLI::success( "Cooled for {$ttl}s." );
    },
    [
        'shortdesc' => 'Cool the cache.',
        'synopsis'  => acme_synopsis( 'cool' ),
        'longdesc'  => "## EXAMPLES\n\n    wp acme cool --ttl=30",
    ]
);
