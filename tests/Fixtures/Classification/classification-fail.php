<?php
/**
 * Test fixture: command classification — should FAIL.
 *
 * Destructive commands are found by behavior and by the command name users
 * type, not only by the PHP method name.
 */

WP_CLI::add_command( 'acme cache flush', 'acme_cache_handler' );

/**
 * Empty the object cache group.
 *
 * ## OPTIONS
 *
 * [--group=<group>]
 * : Cache group.
 *
 * ## EXAMPLES
 *
 *     wp acme cache flush --group=feeds
 *
 * @since 1.0.0
 */
function acme_cache_handler( $args, $assoc_args ) {
    // ❌ E1 + ⚠ E3: registered as "flush"; the function name does not say so.
    wp_cache_flush_group( 'feeds' );
    WP_CLI::success( 'Flushed.' );
}

class Classification_Fail_Command {

    /**
     * Clean up orphan attachments.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Keep attachments newer than this.
     *
     * ## EXAMPLES
     *
     *     wp acme cleanup --days=30
     *
     * @since 1.0.0
     */
    public function cleanup( $args, $assoc_args ) {
        // ❌ E1 + ⚠ E3: no destructive keyword in the name, but deletes attachments.
        foreach ( acme_orphan_attachment_ids() as $attachment_id ) {
            wp_delete_attachment( $attachment_id, true );
        }
        WP_CLI::success( 'Done.' );
    }

    /**
     * Prune the event log.
     *
     * ## OPTIONS
     *
     * [--before=<date>]
     * : Remove events before this date.
     *
     * ## EXAMPLES
     *
     *     wp acme prune --before=2024-01-01
     *
     * @since 1.0.0
     */
    public function prune( $args, $assoc_args ) {
        // ❌ E1 + ⚠ E3: raw DELETE through $wpdb->query().
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}acme_log WHERE created < %s", '2024-01-01' ) );
        WP_CLI::success( 'Pruned.' );
    }

    /**
     * Delete stored items.
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Item type.
     *
     * ## EXAMPLES
     *
     *     wp acme delete --type=draft
     *
     * @since 1.0.0
     * @subcommand delete
     */
    public function remove_items( $args, $assoc_args ) {
        // ❌ E1 + ⚠ E3: users type "wp acme delete"; reported once.
        WP_CLI::success( 'Deleted.' );
    }
}
