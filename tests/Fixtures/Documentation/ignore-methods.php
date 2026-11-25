<?php
/**
 * Test fixture: ignoreMethods ruleset property.
 *
 * Used with tests/Fixtures/Documentation/ignore-methods.xml, which ignores
 * "services" (case-insensitive). "run" is NOT ignored: in a registered
 * command class WP-CLI exposes it as a subcommand.
 */

class Ignore_Methods_Command {

    // ✅ Ignored through the ruleset property.
    public function Services() {
        WP_CLI::debug( 'Wiring services.' );
    }

    // ❌ G1: still a subcommand ("wp myplugin run").
    public function run( $args, $assoc_args ) {
        WP_CLI::success( 'Ran.' );
    }
}
