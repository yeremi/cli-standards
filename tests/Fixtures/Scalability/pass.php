<?php
/**
 * Test fixture: ProgressBar — should PASS.
 */

class Scalability_Pass_Command {

    const STEPS = [ 'prepare', 'apply', 'verify' ];

    /**
     * Check the environment.
     *
     * ## OPTIONS
     *
     * [--strict]
     * : Fail on warnings.
     *
     * ## EXAMPLES
     *
     *     wp myplugin check --strict
     *
     * @since 1.0.0
     */
    public function check( $args, $assoc_args ) {
        // ✅ Array literal: fixed, small collection.
        foreach ( [ 'php', 'mysql', 'redis' ] as $service ) {
            WP_CLI::log( "Checking {$service}..." );
        }

        // ✅ Class constant: fixed collection.
        foreach ( self::STEPS as $step ) {
            WP_CLI::success( "Step {$step} ready." );
        }

        // ✅ Only messages, over data that is not from an unbounded source.
        $problems = $this->collect_problems();
        foreach ( $problems as $problem ) {
            WP_CLI::warning( $problem );
        }

        WP_CLI::success( 'Environment OK.' );
    }

    private function collect_problems(): array {
        return [];
    }
}
