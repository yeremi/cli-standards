<?php
/**
 * Test fixture: delegation to helpers and services — should FAIL.
 */

class Delegation_Fail_Command {

    private $logger;

    /**
     * Delete the cache.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview only.
     *
     * ## EXAMPLES
     *
     *     wp acme delete-cache --dry-run
     *
     * @since 1.0.0
     * @cli-standards confirm-delegated
     */
    public function delete_cache( $args, $assoc_args ) {
        // ❌ E1: the delegation tag has no reason, so it does not count.
        $this->logger->confirm_and_clear( $assoc_args );
    }

    /**
     * Drop custom tables.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview only.
     *
     * ## EXAMPLES
     *
     *     wp acme drop-tables --dry-run
     *
     * @since 1.0.0
     */
    public function drop_tables( $args, $assoc_args ) {
        // ❌ E1: confirm() is three calls deep; only two levels are followed.
        $this->level_one( $assoc_args );
    }

    /**
     * Truncate the log table.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview only.
     *
     * ## EXAMPLES
     *
     *     wp acme truncate-logs --dry-run
     *
     * @since 1.0.0
     */
    public function truncate_logs( $args, $assoc_args ) {
        // ❌ E1: delegates to another object without declaring it.
        $this->logger->truncate( $assoc_args );
    }

    private function level_one( array $assoc_args ): void {
        $this->level_two( $assoc_args );
    }

    private function level_two( array $assoc_args ): void {
        $this->level_three( $assoc_args );
    }

    private function level_three( array $assoc_args ): void {
        WP_CLI::confirm( 'Drop tables?', $assoc_args );
    }
}
