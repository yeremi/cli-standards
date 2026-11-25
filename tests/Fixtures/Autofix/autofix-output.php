<?php
/**
 * Test fixture: safe autofixes (OutputMethod) — phpcbf must turn this file into autofix-output.php.fixed.
 */

class Autofix_Output_Command {

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

        WP_CLI::success( 'Done.' );
    }
}
