<?php
/**
 * Test fixture: ignoreClasses ruleset property.
 *
 * Used with tests/Fixtures/Classes/ignore-classes.xml.
 */

namespace Acme\Cli;

use WP_CLI;

class Legacy_Command {
    // ✅ Ignored through the ruleset property (fully qualified name).
    public function run( $args, $assoc_args ) {
        WP_CLI::success( 'Legacy.' );
    }
}

class Current_Command {
    // ❌ G1: not ignored.
    public function run( $args, $assoc_args ) {
        WP_CLI::success( 'Current.' );
    }
}
