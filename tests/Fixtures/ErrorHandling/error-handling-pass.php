<?php
/**
 * Test fixture: WpErrorHandling — should PASS.
 *
 * Each call below is handled correctly for what the function really returns
 * (return types from WordPress core).
 */

// ✅ Direct-access guard at file scope: never runs inside a command.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Error_Handling_Pass_Command {

    /**
     * Import a sample record.
     *
     * ## OPTIONS
     *
     * [--title=<title>]
     * : Post title.
     *
     * [--dry-run]
     * : Preview only.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp acme sample --title=Hello
     *
     * @since 1.0.0
     */
    public function sample( $args, $assoc_args ) {
        // The command deletes content, so it confirms first (see ConfirmOnDelete).
        WP_CLI::confirm( 'Run the sample import?', $assoc_args );

        if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
            WP_CLI::success( 'Dry run: nothing changed.' );
            return;
        }

        // ✅ WP_Post|false|null: never WP_Error.
        $deleted = wp_delete_post( 10, true );
        if ( ! $deleted ) {
            WP_CLI::warning( 'Not deleted.' );
        }

        // ✅ bool: never WP_Error.
        wp_delete_user( 5 );

        // ✅ int (0 on failure): never WP_Error.
        $category = wp_create_category( 'News' );

        // ✅ array with an "error" key: never WP_Error.
        $upload = wp_upload_bits( 'a.txt', null, 'x' );
        if ( $upload['error'] ) {
            WP_CLI::warning( $upload['error'] );
        }

        // ✅ $wp_error not passed: returns 0 on failure.
        $title = WP_CLI\Utils\get_flag_value( $assoc_args, 'title', 'x' );
        $id    = wp_insert_post( [ 'post_title' => $title ] );
        if ( ! $id ) {
            WP_CLI::warning( 'Insert failed.' );
        }

        // ✅ $wp_error is the literal false.
        $updated = wp_update_post( [ 'ID' => 1 ], false );

        // ✅ $wp_error = true and checked.
        $attachment = wp_insert_attachment( [ 'post_title' => 'a' ], 'a.jpg', 0, true );
        if ( is_wp_error( $attachment ) ) {
            WP_CLI::warning( $attachment->get_error_message() );
        }

        // ✅ Always returns WP_Error on failure, and checked.
        $term = wp_insert_term( 'News', 'category' );
        if ( is_wp_error( $term ) ) {
            WP_CLI::warning( $term->get_error_message() );
        }

        WP_CLI::success( 'Done.' );
    }
}
