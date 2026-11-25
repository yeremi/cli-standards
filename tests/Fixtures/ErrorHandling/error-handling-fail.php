<?php
/**
 * Test fixture: WpErrorHandling — should FAIL.
 */

// ✅ Direct-access guard at file scope: never runs inside a command.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Error_Handling_Fail_Command {

    /**
     * Import a sample record.
     *
     * ## OPTIONS
     *
     * [--title=<title>]
     * : Post title.
     *
     * ## EXAMPLES
     *
     *     wp acme sample --title=Hello
     *
     * @since 1.0.0
     */
    public function sample( $args, $assoc_args ) {
        // ⚠ D3: asked for WP_Error ($wp_error = true), not checked.
        $id = wp_insert_post( [ 'post_title' => 'x' ], true );
        if ( ! $id ) {
            WP_CLI::warning( 'Insert failed.' );
        }

        // ⚠ D3: named argument wp_error: true, result discarded.
        wp_update_post( postarr: [ 'ID' => 1 ], wp_error: true );

        // ⚠ D3: $wp_error comes from a variable, so it may be true.
        $strict     = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'strict', false );
        $attachment = wp_insert_attachment( [ 'post_title' => 'a' ], 'a.jpg', 0, $strict );

        // ⚠ D3: always returns WP_Error on failure; not checked.
        $term = wp_insert_term( 'News', 'category' );

        WP_CLI::success( "Done: {$attachment} {$term['term_id']}" );
    }

    /**
     * Abort the import.
     *
     * ## OPTIONS
     *
     * [--code=<code>]
     * : Exit code.
     *
     * ## EXAMPLES
     *
     *     wp acme abort --code=2
     *
     * @since 1.0.0
     */
    public function abort( $args, $assoc_args ) {
        // ❌ D1: exit() inside a command.
        exit( 2 );
    }
}
