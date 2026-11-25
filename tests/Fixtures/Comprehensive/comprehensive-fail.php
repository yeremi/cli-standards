<?php
/**
 * Test fixture: comprehensive FAIL — triggers multiple sniffs.
 *
 * This file demonstrates every anti-pattern the standard catches.
 */

// ❌ A1: a single-word command group that takes over WP-CLI core's "cache".
WP_CLI::add_command( 'cache', 'My_Bad_Command' );

class My_Bad_Command {

    /**
     * Delete all items.
     *
     * No ## OPTIONS or ## EXAMPLES sections.
     */
    public function delete_all( $args, $assoc_args ) {
        // ❌ C1: echo in CLI context.
        echo "Deleting all items...\n";

        // ❌ B1: snake_case flag (should be kebab-case).
        $post_type = $assoc_args['post_type'] ?? 'post';

        // ❌ F2: Unbounded query.
        $posts = get_posts([
            'numberposts'   => -1,
            'post_type'     => $post_type,
        ]);

        // ❌ D3: Unchecked WP_Error (wp_delete_term() can return WP_Error).
        $result = wp_delete_term( 1, 'category' );

        // ❌ D1: die() in CLI context.
        if ( ! $result ) {
            die( 'Failed to delete.' );
        }

        // ❌ E1: Destructive operation — no WP_CLI::confirm() in this method.
        // ❌ G1: No ## OPTIONS section in docblock.
        // ❌ G2: No ## EXAMPLES section in docblock.

        WP_CLI::success( 'All items deleted.' );
    }

    /**
     * Reset plugin data.
     */
    public function reset( $args, $assoc_args ) {
        // (No G1 here: the command reads no arguments, so ## OPTIONS is not required.)
        // ❌ E1: Destructive name, no confirm.
        // ❌ E2: confirm without $assoc_args (if it existed).
        WP_CLI::log( 'Resetting...' );

        // ❌ D1: exit() in CLI context.
        exit( 0 );
    }
}
