<?php
/**
 * Test fixture: safe autofixes — phpcbf must turn this file into fail.php.fixed.
 */

class Autofix_Command {

    /**
     * Print a summary.
     *
     * ## EXAMPLES
     *
     *     wp acme summary
     *
     * @since 1.0.0
     */
    public function summary( $args, $assoc_args ) {
        $total = acme_total();

        // 🔧 Output that already ends with a newline becomes WP_CLI::line().
        echo "Report\n";
        echo "Total: {$total}\n";
        echo $total . PHP_EOL;
        print "Done.\n";
        if ( $total > 10 ) echo "Large\n";

        // ❌ Not fixable: WP_CLI::line() would add a newline.
        echo $total;

        // ❌ Not fixable: several expressions, or an escaped backslash.
        echo "a", "b\n";
        echo "C:\\n";
    }

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
