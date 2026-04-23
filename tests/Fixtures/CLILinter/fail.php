<?php
/**
 * Test fixture: CLI Linter checks — should FAIL (SHOULD-level warnings).
 *
 * This file demonstrates all 8 SHOULD-level violations.
 */

class My_CLI_Command {

    /**
     * List all items.
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Filter by type.
     *
     * ## EXAMPLES
     *
     *     wp myplugin list_items
     */
    public function list_items( $args, $assoc_args ) {
        // ⚠ B2: Direct $assoc_args access instead of get_flag_value().
        $type = $assoc_args['type'] ?? 'all';

        $items = get_posts( [ 'post_type' => $type ] );

        // ⚠ C2: Mixing WP_CLI::log() with format_items() in same method.
        WP_CLI::log( 'Fetching items...' );

        // ⚠ C3: Uses format_items but listing command doesn't have --format doc.
        WP_CLI\Utils\format_items( 'table', $items, [ 'ID', 'post_title' ] );

        // ⚠ C4: No ## AVAILABLE FIELDS in docblock (but uses format_items).
        // ⚠ G4: No @since annotation.
    }

    /**
     * Migrate all posts to new format.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip confirmation.
     *
     * ## EXAMPLES
     *
     *     wp myplugin migrate_posts
     *
     * @since 1.0.0
     */
    public function migrate_posts( $args, $assoc_args ) {
        WP_CLI::confirm( 'Migrate all posts?', $assoc_args );

        $posts = get_posts( [ 'numberposts' => 100 ] );

        // ⚠ E3: State-changing command, no --dry-run support.
        // ⚠ F3: Bulk command, no --batch-size flag.

        foreach ( $posts as $post ) {
            // ⚠ F1: Loop with WP_CLI calls but no make_progress_bar().
            wp_update_post( [ 'ID' => $post->ID, 'post_status' => 'publish' ] );
            WP_CLI::log( "Updated post {$post->ID}" );
        }

        WP_CLI::success( 'Migration complete.' );
    }
}
