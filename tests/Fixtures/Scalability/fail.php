<?php
/**
 * Test fixture: ProgressBar — should FAIL.
 */

class Scalability_Fail_Command {

    /**
     * Prepare a report.
     *
     * ## OPTIONS
     *
     * [--title=<title>]
     * : Report title.
     *
     * ## EXAMPLES
     *
     *     wp myplugin prepare --title=Weekly
     *
     * @since 1.0.0
     */
    public function prepare( $args, $assoc_args ) {
        WP_CLI::log( 'Preparing...' );
        $progress = WP_CLI\Utils\make_progress_bar( 'Preparing', 1 );
        $progress->finish();
    }

    /**
     * Audit users.
     *
     * ## OPTIONS
     *
     * [--role=<role>]
     * : Limit to a role.
     *
     * ## EXAMPLES
     *
     *     wp myplugin audit --role=editor
     *
     * @since 1.0.0
     */
    public function audit( $args, $assoc_args ) {
        $this->mark_users();

        // ⚠ F1: only messages, but over an unbounded source (get_posts).
        $posts = get_posts( [ 'post_status' => 'draft' ] );
        foreach ( $posts as $post ) {
            WP_CLI::line( $post->post_title );
        }

        // ⚠ F1: for loops are bulk loops too.
        for ( $page = 1; $page <= 10; $page++ ) {
            WP_CLI::log( "Page {$page}" );
        }
    }

    private function mark_users() {
        // ⚠ F1: bulk loop over an unbounded source.
        foreach ( get_users() as $user ) {
            update_user_meta( $user->ID, 'audited', 1 );
            WP_CLI::log( "Audited {$user->ID}" );
        }
    }
}
