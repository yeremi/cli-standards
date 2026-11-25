<?php
/**
 * Test fixture: OutputMethod — should FAIL.
 *
 * This file contains echo/print inside a WP-CLI command context.
 */

class My_CLI_Command {

    public function list_items( $args, $assoc_args ) {
        $items = get_posts();

        // ❌ VIOLATION: echo in CLI context.
        echo "Found " . count($items) . " items\n";

        // ❌ VIOLATION: print in CLI context.
        print "Processing...\n";

        foreach ( $items as $item ) {
            // ❌ VIOLATION: echo for output.
            echo $item->post_title . "\n";
        }

        $this->print_footer();

        WP_CLI::success( 'Done.' );
    }

    private function print_footer() {
        // ❌ VIOLATION: echo in a helper the command calls.
        echo "--\n";
    }
}
