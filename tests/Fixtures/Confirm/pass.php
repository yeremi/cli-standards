<?php
/**
 * Test fixture: ConfirmPassthrough and explicit --yes guards — should PASS.
 */

class Confirm_Pass_Command {

    /**
     * Delete logs.
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
     *     wp acme delete-logs --yes
     *
     * @since 1.0.0
     */
    public function delete_logs( $args, $assoc_args ) {
        // ✅ Explicit --yes guard with braces (isset( $assoc_args['yes'] ) works too).
        if ( ! array_key_exists( 'yes', $assoc_args ) ) {
            WP_CLI::confirm( 'Delete all logs?' );
        }
        acme_delete_logs();
        WP_CLI::success( 'Done.' );
    }

    /**
     * Delete backups.
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
     *     wp acme delete-backups --yes
     *
     * @since 1.0.0
     */
    public function delete_backups( $args, $assoc_args ) {
        // ✅ Explicit --yes guard without braces, through get_flag_value().
        if ( ! WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false ) )
            WP_CLI::confirm( 'Delete all backups?' );
        acme_delete_backups();
        WP_CLI::success( 'Done.' );
    }

    /**
     * Purge queue.
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
        // ✅ Standard form: $assoc_args passed through.
        WP_CLI::confirm( 'Purge the queue?', $assoc_args );
        acme_purge_queue();
        WP_CLI::success( 'Done.' );
    }
}
