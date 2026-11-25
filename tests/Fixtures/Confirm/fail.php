<?php
/**
 * Test fixture: ConfirmPassthrough and explicit --yes guards — should FAIL.
 */

class Confirm_Fail_Command {

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
        // ❌ E2: no --yes bypass at all.
        WP_CLI::confirm( 'Delete all logs?' );
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
        // ❌ E2: the guard checks another flag, so --yes does not skip the prompt.
        if ( ! array_key_exists( 'force', $assoc_args ) ) {
            WP_CLI::confirm( 'Delete all backups?' );
        }
        acme_delete_backups();
        WP_CLI::success( 'Done.' );
    }
}
