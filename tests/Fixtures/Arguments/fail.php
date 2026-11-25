<?php
/**
 * Test fixture: assoc args recognized by name or by position.
 */

class Arguments_Command {

    /**
     * Tune the cache.
     *
     * ## OPTIONS
     *
     * [--chunk-size=<n>]
     * : Items per chunk.
     *
     * ## EXAMPLES
     *
     *     wp myplugin tune --chunk-size=50
     *
     * @since 1.0.0
     */
    public function tune( array $args, array $assocArgs ) {
        // ❌ B1 + ⚠ B2: camelCase $assocArgs is the WP-CLI assoc args.
        $size = $assocArgs['chunk_size'] ?? 100;

        WP_CLI::success( "Chunk size: {$size}" );
    }

    /**
     * Configure the plugin.
     *
     * ## OPTIONS
     *
     * [--log-level=<level>]
     * : Log level.
     *
     * ## EXAMPLES
     *
     *     wp myplugin configure --log-level=debug
     *
     * @since 1.0.0
     */
    public function configure( $args, $opts ) {
        // ❌ B1 + ⚠ B2: second parameter of a command handler, whatever its name.
        $level = $opts['logLevel'] ?? 'info';

        WP_CLI::success( "Log level: {$level}" );
    }

    // ✅ Not a command (private): its second parameter is not assoc args.
    private function merge( $defaults, $config ) {
        WP_CLI::debug( 'Merging config.' );

        return $config['some_key'] ?? $defaults;
    }
}
