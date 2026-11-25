<?php
/**
 * Test fixture: real command classes are still recognized — should FAIL.
 *
 * Nothing is registered in this file: each class qualifies by its name,
 * parent or interface. Every command lacks a docblock.
 */

class Acme_Thing_Command {
    // ❌ G1: class name contains "Command".
    public function greet( $args, $assoc_args ) {
        WP_CLI::success( 'Hello.' );
    }
}

class Acme_Jobs implements Acme_Command_Interface {
    // ❌ G1: implements a "Command" interface (registered from another file).
    public function run( $args, $assoc_args ) {
        WP_CLI::success( 'Ran.' );
    }
}

class Acme_Tools extends Acme_Base_Command {
    // ❌ G1: extends a "Command" base class.
    public function check( $args, $assoc_args ) {
        WP_CLI::success( 'Checked.' );
    }
}

class AcmeCLIRunner {
    // ❌ G1: "CLI" is a word of the class name.
    public function inspect( $args, $assoc_args ) {
        WP_CLI::success( 'Inspected.' );
    }
}
