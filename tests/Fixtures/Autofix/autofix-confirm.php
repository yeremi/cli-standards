<?php
/**
 * Test fixture: safe autofixes (ConfirmPassthrough) — phpcbf must turn this file into autofix-confirm.php.fixed.
 */

class Autofix_Confirm_Command {

    /**
     * Rotate the API keys.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp acme rotate --yes
     *
     * @since 1.0.0
     */
    public function rotate( $args, $opts ) {
        // 🔧 The handler's assoc args are passed, whatever their name.
        WP_CLI::confirm( 'Rotate the API keys?' );
        $this->ask();
        $this->rotate_core( $opts );
        WP_CLI::success( 'Rotated.' );
    }

    private function rotate_core( array $assoc_args ) {
        // 🔧 A helper parameter named $assoc_args is passed too.
        WP_CLI::confirm( 'Rotate now?' );
        acme_rotate_keys();
    }

    private function ask() {
        // ❌ Not fixable: not a command handler, so no assoc args to pass.
        WP_CLI::confirm( 'Really?' );
    }
}
