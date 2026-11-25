<?php
/**
 * Test fixture: destructiveFunctions ruleset property.
 *
 * Used with tests/Fixtures/Classification/custom-destructive.xml, which
 * replaces the default list with "acme_wipe_tenant".
 */

class Custom_Destructive_Command {

    // ❌ E1: calls the project's own destructive API.
    public function rotate( $args, $assoc_args ) {
        acme_wipe_tenant( $args[0] );
        WP_CLI::success( 'Rotated.' );
    }

    // ✅ wp_delete_attachment() is not in the replaced list.
    public function tidy( $args, $assoc_args ) {
        wp_delete_attachment( 1, true );
        WP_CLI::success( 'Tidied.' );
    }
}
