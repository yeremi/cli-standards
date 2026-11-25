<?php
/**
 * Test fixture: ProgressBar — should PASS.
 */

class Scalability_Pass_Command {

    const STEPS = [ 'prepare', 'apply', 'verify' ];

    /**
     * Check the environment.
     *
     * ## OPTIONS
     *
     * [--strict]
     * : Fail on warnings.
     *
     * ## EXAMPLES
     *
     *     wp myplugin check --strict
     *
     * @since 1.0.0
     */
    public function check( $args, $assoc_args ) {
        // ✅ Array literal: fixed, small collection.
        foreach ( [ 'php', 'mysql', 'redis' ] as $service ) {
            WP_CLI::log( "Checking {$service}..." );
        }

        // ✅ Class constant: fixed collection.
        foreach ( self::STEPS as $step ) {
            WP_CLI::success( "Step {$step} ready." );
        }

        // ✅ Only messages, over data that is not from an unbounded source.
        $problems = $this->collect_problems();
        foreach ( $problems as $problem ) {
            WP_CLI::warning( $problem );
        }

        WP_CLI::success( 'Environment OK.' );
    }

    /**
     * Reindex all posts.
     *
     * ## OPTIONS
     *
     * [--batch-size=<n>]
     * : Posts per batch.
     *
     * ## EXAMPLES
     *
     *     wp myplugin reindex --batch-size=100
     *
     * @since 1.0.0
     */
    public function reindex( $args, $assoc_args ) {
        $batch_size = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 100 );
        $total      = (int) wp_count_posts()->publish;

        // ✅ Progress bar created far (> 50 tokens) above the loop, same method.
        $progress = WP_CLI\Utils\make_progress_bar( 'Reindexing', $total );

        $paged = 1;
        $query_args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ];

        do {
            $ids = get_posts( $query_args + [ 'paged' => $paged ] );

            foreach ( $ids as $id ) {
                clean_post_cache( $id );
                WP_CLI::debug( "Reindexed {$id}" );
                $progress->tick();
            }

            $paged++;
            wp_cache_flush_runtime();
        } while ( count( $ids ) === $batch_size );

        $progress->finish();
        WP_CLI::success( 'Reindex complete.' );
    }

    private function collect_problems(): array {
        return [];
    }
}
