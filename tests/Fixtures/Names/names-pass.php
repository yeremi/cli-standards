<?php
/**
 * Test fixture: qualified and namespaced names — should PASS.
 *
 * Every requirement is met through `\WP_CLI::…`, `\WP_CLI\Utils\…`,
 * `Utils\…` (imported namespace) or fully qualified function names.
 */

namespace Acme\Cli;

use WP_CLI\Utils;

class Names_Pass_Command {

    /**
     * Purge records.
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
     *     wp acme purge --yes
     *
     * @since 1.0.0
     */
    public function purge( $args, $assoc_args ) {
        \WP_CLI::confirm( 'Purge all records?', $assoc_args );

        if ( Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
            return;
        }

        $term = \wp_insert_term( 'Archive', 'category' );
        if ( \is_wp_error( $term ) ) {
            \WP_CLI::error( $term );
        }

        \WP_CLI::success( "Purged into {$term['term_id']}." );
    }

    /**
     * List posts.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     *
     * ## AVAILABLE FIELDS
     *
     * * ID
     * * post_title
     *
     * ## EXAMPLES
     *
     *     wp acme list --format=json
     *
     * @since 1.0.0
     */
    public function list_posts( $args, $assoc_args ) {
        $posts    = \get_posts( [ 'numberposts' => 100 ] );
        $progress = \WP_CLI\Utils\make_progress_bar( 'Loading', count( $posts ) );

        $rows = [];
        foreach ( $posts as $post ) {
            $rows[] = [ 'ID' => $post->ID, 'post_title' => $post->post_title ];
            \WP_CLI::debug( "Loaded {$post->ID}" );
            $progress->tick();
        }
        $progress->finish();

        $format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
        \WP_CLI\Utils\format_items( $format, $rows, [ 'ID', 'post_title' ] );
    }

    /**
     * Check the environment.
     *
     * ## EXAMPLES
     *
     *     wp acme check
     *
     * @since 1.0.0
     */
    public function check( $args, $assoc_args ) {
        // Qualified class constant: fixed collection, no progress bar needed.
        foreach ( \Acme\Cli\Config::SERVICES as $service ) {
            \WP_CLI::success( "{$service} ready." );
        }
    }
}
