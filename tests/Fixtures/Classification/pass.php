<?php
/**
 * Test fixture: command classification — should PASS.
 *
 * Keywords are compared with whole words of the command name, and the
 * command name users type (@subcommand) wins over the PHP method name.
 */

class Classification_Pass_Command {

    /**
     * Show dropdown usage statistics.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Period in days.
     *
     * ## EXAMPLES
     *
     *     wp acme dropdown-stats --days=7
     *
     * @since 1.0.0
     */
    public function dropdown_stats( $args, $assoc_args ) {
        // ✅ "dropdown" is not the destructive keyword "drop".
        WP_CLI::log( 'Stats ready.' );
    }

    /**
     * Count reactor events.
     *
     * ## OPTIONS
     *
     * [--since=<date>]
     * : Start date.
     *
     * ## EXAMPLES
     *
     *     wp acme nuclear-count
     *
     * @since 1.0.0
     */
    public function nuclearCount( $args, $assoc_args ) {
        // ✅ "nuclear" contains "clear" only as a substring.
        WP_CLI::log( '0 events.' );
    }

    /**
     * Refresh the feed cache.
     *
     * ## OPTIONS
     *
     * [--force]
     * : Refresh even when fresh.
     *
     * ## EXAMPLES
     *
     *     wp acme refresh-feed --force
     *
     * @since 1.0.0
     */
    public function refresh_feed( $args, $assoc_args ) {
        // ✅ Housekeeping deletions (options, meta, transients) are not
        // treated as destructive by default.
        delete_option( 'acme_feed_lock' );
        delete_transient( 'acme_feed' );
        WP_CLI::success( 'Feed refreshed.' );
    }

    /**
     * Count items processed by the last run.
     *
     * ## OPTIONS
     *
     * [--run=<id>]
     * : Run ID.
     *
     * ## EXAMPLES
     *
     *     wp acme reprocessed-count --run=12
     *
     * @since 1.0.0
     */
    public function reprocessed_count( $args, $assoc_args ) {
        // ✅ "reprocessed" is not the bulk keyword "process" (no --batch-size needed).
        WP_CLI::log( '0' );
    }

    /**
     * Describe the widget getter configuration.
     *
     * ## OPTIONS
     *
     * [--widget=<id>]
     * : Widget ID.
     *
     * ## EXAMPLES
     *
     *     wp acme widget-getter --widget=3
     *
     * @since 1.0.0
     */
    public function widget_getter( $args, $assoc_args ) {
        // ✅ "getter" is not the listing keyword "get" (no --format needed).
        WP_CLI::log( 'Configured.' );
    }

    /**
     * Inspect cache entries.
     *
     * ## OPTIONS
     *
     * [--key=<key>]
     * : Entry key.
     *
     * ## EXAMPLES
     *
     *     wp acme inspect --key=home
     *
     * @since 1.0.0
     * @subcommand inspect
     */
    public function delete_candidates( $args, $assoc_args ) {
        // ✅ Users type "wp acme inspect": the @subcommand name wins over the
        // method name, and the body destroys nothing.
        WP_CLI::log( 'Candidates: 0' );
    }
}
