<?php
/**
 * Test fixture: ConfirmOnDelete — should FAIL.
 *
 * Destructive method without WP_CLI::confirm().
 */

class My_CLI_Command {

    /**
     * Delete all transient data.
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Type of transients to delete.
     *
     * ## EXAMPLES
     *
     *     wp myplugin delete-transients
     */
    public function delete_transients( $args, $assoc_args ) {
        // ❌ VIOLATION: destructive operation, no confirm().
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
        WP_CLI::success( 'Transients deleted.' );
    }

    /**
     * Flush the cache.
     *
     * ## OPTIONS
     *
     * <scope>
     * : Cache scope (all, page, object).
     *
     * ## EXAMPLES
     *
     *     wp myplugin flush all
     */
    public function flush( $args, $assoc_args ) {
        // ❌ VIOLATION: destructive operation, no confirm().
        wp_cache_flush();
        WP_CLI::success( 'Cache flushed.' );
    }

    /**
     * Purge old records.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Number of days to keep. Default: 30.
     *
     * ## EXAMPLES
     *
     *     wp myplugin purge --days=30
     */
    public function purge_old_records( $args, $assoc_args ) {
        // ❌ VIOLATION: destructive operation, no confirm().
        $days = $assoc_args['days'] ?? 30;
        WP_CLI::log( "Purging records older than {$days} days..." );
        WP_CLI::success( 'Purge complete.' );
    }
}
