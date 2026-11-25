<?php
/**
 * Test fixture: Safety — should PASS.
 *
 * Destructive methods with proper WP_CLI::confirm() and $assoc_args passthrough.
 */

class My_CLI_Command {

    /**
     * Delete all transient data.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip confirmation prompt.
     *
     * [--dry-run]
     * : Preview the deletion without changing data.
     *
     * ## EXAMPLES
     *
     *     wp myplugin delete-transients --dry-run
     *     wp myplugin delete-transients --yes
     *
     * @since 1.0.0
     */
    public function delete_transients( $args, $assoc_args ) {
        $dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

        // ✅ CORRECT: confirm with $assoc_args passthrough.
        WP_CLI::confirm( 'This will delete all transients. Are you sure?', $assoc_args );

        if ( $dry_run ) {
            WP_CLI::log( 'Dry run: transients would be deleted.' );
            return;
        }

        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
        WP_CLI::success( 'Transients deleted.' );
    }

    /**
     * Flush the cache safely.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip confirmation prompt.
     *
     * [--dry-run]
     * : Preview the cache flush without executing it.
     *
     * ## EXAMPLES
     *
     *     wp myplugin flush --dry-run
     *     wp myplugin flush --yes
     *
     * @since 1.0.0
     */
    public function flush( $args, $assoc_args ) {
        $dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

        // ✅ CORRECT: confirm before destructive operation.
        WP_CLI::confirm( 'Flush all caches?', $assoc_args );

        if ( $dry_run ) {
            WP_CLI::log( 'Dry run: caches would be flushed.' );
            return;
        }

        wp_cache_flush();
        WP_CLI::success( 'Cache flushed.' );
    }

    /**
     * List active items (non-destructive, no confirm needed).
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Default: table.
     *
     * ## AVAILABLE FIELDS
     *
     * These fields are available:
     *
     * - `ID`
     * - `post_title`
     *
     * ## EXAMPLES
     *
     *     wp myplugin list --format=json
     *
     * @since 1.0.0
     */
    public function list_items( $args, $assoc_args ) {
        // ✅ CORRECT: non-destructive, no confirm needed.
        $items = get_posts();
        $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

        WP_CLI\Utils\format_items( $format, $items, [ 'ID', 'post_title' ] );
    }
}
